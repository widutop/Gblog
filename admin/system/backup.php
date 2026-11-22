<?php
$page_title = '安全管理';
require_once __DIR__ . '/../inc/header.php';
gblog_admin_require_cap('system.backup');
require_once __DIR__ . '/../inc/tabs.php';
require_once __DIR__ . '/../inc/env_check.php';

$backupDir      = gblog_backup_dir();
$backupDirReady = ($backupDir !== '');

$error   = '';
$success = '';
// 恢复码明文只在生成的这一次请求里出现，绝不写进会话或数据库
$restoreCodePlain = '';
$tab = gblog_pick($_GET, 'tab', ['data', 'security', 'env'], 'data');

function gblog_backup_text(array $src, string $key): string
{
    $v = $src[$key] ?? '';
    return is_scalar($v) ? (string)$v : '';
}

function gblog_restore_extra_note(): string
{
    $n = (int)($GLOBALS['gblog_restore_skipped'] ?? 0);
    return $n > 0
        ? '（已忽略来源站账号 / 令牌 / 审计记录共 ' . $n . ' 条，当前管理员账号与密码保持不变）'
        : '';
}

function gblog_backup_uploads_dir(): string
{
    return defined('UPLOAD_DIR') ? rtrim(str_replace('\\', '/', UPLOAD_DIR), '/') : GBLOG_ROOT . '/usr/uploads';
}

// 完整备份：数据库 SQL 与 usr/uploads 附件同包，避免「数据回来了、图片和音频全丢」
function gblog_backup_pack(string $dst, string $sql): int
{
    if (!class_exists('ZipArchive')) return -1;
    $zip = new ZipArchive();
    if ($zip->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return -1;
    $zip->addFromString('database.sql', $sql);
    $root = gblog_backup_uploads_dir();
    $n = 0;
    if (is_dir($root)) {
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($root) + 1);
                if ($rel === '' || strpos($rel, '..') !== false) continue;
                $zip->addFile($f->getPathname(), 'uploads/' . $rel);
                if (++$n >= 20000) break;
            }
        } catch (Throwable $e) {
            $zip->close();
            @unlink($dst);
            return -1;
        }
    }
    if (!$zip->close()) return -1;
    return $n;
}

// 从完整备份包里取出 database.sql（.sql 文件也可直接用）
function gblog_backup_read_sql(string $path, string $name): string
{
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
        return (string)@file_get_contents($path);
    }
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $sql = (string)$zip->getFromName('database.sql');
    $zip->close();
    return $sql;
}

function gblog_backup_unpack(string $path): int
{
    if (!class_exists('ZipArchive')) return -1;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return -1;
    $root = gblog_backup_uploads_dir();
    $n = 0;
    for ($i = 0, $c = $zip->numFiles; $i < $c; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (strpos($name, 'uploads/') !== 0 || substr($name, -1) === '/') continue;
        $rel = substr($name, 8);
        if ($rel === '' || strpos($rel, '..') !== false) continue;
        $dst = $root . '/' . $rel;
        $dir = dirname($dst);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) continue;
        $in = $zip->getStream($name);
        if (!$in) continue;
        $out = @fopen($dst, 'wb');
        if ($out) {
            stream_copy_to_stream($in, $out);
            fclose($out);
            $n++;
        }
        fclose($in);
    }
    $zip->close();
    return $n;
}

if (isset($_GET['download'])) {
    if (!is_admin_logged_in()) { header('Location: ' . gblog_admin_url('login.php')); exit; }
    $name = basename((string)($_GET['download']));
    if ($backupDirReady && $name !== '' && preg_match('/^gblog_backup_.*\.(sql|zip)$/', $name) && is_file($backupDir . '/' . $name)) {
// 下载内容必须是纯 SQL/二进制包，否则 HTML 会混进备份让文件再也无法恢复
        for ($i = 0, $n = ob_get_level(); $i < $n; $i++) {
            if (!@ob_end_clean()) break;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($backupDir . '/' . $name));
        readfile($backupDir . '/' . $name);
        exit;
    }
    $error = '备份文件不存在。';
}

if (isset($_GET['diag']) && (string)$_GET['diag'] === '1' && $tab === 'env') {
    if (!is_admin_logged_in()) { header('Location: ' . gblog_admin_url('login.php')); exit; }
    for ($i = 0, $n = ob_get_level(); $i < $n; $i++) {
        if (!@ob_end_clean()) break;
    }
    $text = gblog_env_diagnostic();
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="gblog_diag_' . date('Ymd_His') . '.txt"');
    header('Content-Length: ' . strlen($text));
    echo $text;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedTab = gblog_pick($_POST, 'tab', ['data', 'security', 'env'], 'data');
    $tab = $postedTab;
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            switch ($action) {
                case 'backup':
                    $file = db_dump_sql();
                    if ($file === '') { $error = '备份失败：无法生成 SQL。'; break; }
                    if (!$backupDirReady) {
                        $error = '备份失败：服务器没有可写的备份目录（多为 open_basedir 限制），请联系主机商放行目录。';
                        break;
                    }
                    $name = 'gblog_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.sql';
                    if (@file_put_contents($backupDir . '/' . $name, $file) === false) {
                        $error = '备份失败：无法写入备份目录，请检查目录权限。';
                        break;
                    }
                    $success = '备份成功，已保存到服务器备份目录：' . e($name);
                    break;

// 完整备份：数据库 + 附件一起打包，整站迁移或灾难恢复用它
                case 'backup_full':
                    if (!class_exists('ZipArchive')) { $error = '完整备份失败：服务器未启用 ZIP 扩展，请改用「仅数据库备份」。'; break; }
                    if (!$backupDirReady) {
                        $error = '备份失败：服务器没有可写的备份目录（多为 open_basedir 限制），请联系主机商放行目录。';
                        break;
                    }
                    $file = db_dump_sql();
                    if ($file === '') { $error = '备份失败：无法生成 SQL。'; break; }
                    $name = 'gblog_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.zip';
                    $files = gblog_backup_pack($backupDir . '/' . $name, $file);
                    if ($files < 0) { $error = '完整备份失败：无法写入备份文件，请检查目录权限与磁盘空间。'; break; }
                    $success = '完整备份成功：' . e($name) . '（含数据库与 ' . (int)$files . ' 个附件文件）';
                    break;

                case 'restore_file':
                    $name = basename(gblog_backup_text($_POST, 'backup_name'));
                    if (!$backupDirReady || $name === '' || !preg_match('/^gblog_backup_.*\.(sql|zip)$/', $name) || !is_file($backupDir . '/' . $name)) { $error = '无效的备份文件。'; break; }
                    $sql = gblog_backup_read_sql($backupDir . '/' . $name, $name);
                    if ($sql === '') { $error = '恢复失败：备份包内没有 database.sql，或文件已损坏。'; break; }
// 他站 / 无签名备份必须填写一次性恢复码
                    if (db_restore_sql($sql, gblog_backup_text($_POST, 'restore_code'))) {
                        $extra = '';
                        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip') {
                            $n = gblog_backup_unpack($backupDir . '/' . $name);
                            if ($n >= 0) $extra = '，附件还原 ' . $n . ' 个';
                        }
                        $success = '已从备份 ' . e($name) . ' 恢复成功' . $extra . '。' . gblog_restore_extra_note();
                    }
                    else { $error = '恢复失败：' . (($GLOBALS['gblog_restore_error'] ?? '') !== '' ? $GLOBALS['gblog_restore_error'] : '备份无效或已损坏。'); }
                    break;

                case 'restore_upload':
                    if (empty($_FILES['sql_file']['tmp_name'])) { $error = '请选择要上传的 .sql 或 .zip 备份文件。'; break; }
                    $upSize = (int)($_FILES['sql_file']['size'] ?? 0);
                    $upExt  = strtolower(pathinfo((string)($_FILES['sql_file']['name'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($upExt, ['sql', 'zip'], true)) { $error = '只支持 .sql 或 .zip 备份文件。'; break; }
                    $limit  = $upExt === 'zip' ? 128 : 32;
                    if ($upSize <= 0 || $upSize > $limit * 1024 * 1024) { $error = '备份文件为空或超过 ' . $limit . 'MB 上限。'; break; }
                    $sql = gblog_backup_read_sql((string)$_FILES['sql_file']['tmp_name'], 'x.' . $upExt);
                    if ($sql === '') { $error = '上传的备份文件为空，或包内没有 database.sql。'; break; }
                    if (db_restore_sql($sql, gblog_backup_text($_POST, 'restore_code'))) {
                        $extra = '';
                        if ($upExt === 'zip' && gblog_backup_text($_POST, 'restore_files') === '1') {
                            $n = gblog_backup_unpack((string)$_FILES['sql_file']['tmp_name']);
                            if ($n >= 0) $extra = '，附件还原 ' . $n . ' 个';
                        }
                        $success = '上传备份恢复成功' . $extra . '。' . gblog_restore_extra_note();
                    }
                    else { $error = '恢复失败：' . (($GLOBALS['gblog_restore_error'] ?? '') !== '' ? $GLOBALS['gblog_restore_error'] : '备份无效或已损坏。'); }
                    break;

                case 'clean_all':
                    db_clean(true);
                    $success = '已清理全部内容（文章/歌曲/评论等）、用户自建栏目、分类与广告位；已保留 5 个内置栏目、站点设置与管理员账号。';
                    break;

                case 'clean_test':
                    db_clean(false);
                    $success = '已清理测试数据，保留栏目、分类、网站设置、广告位与管理员。';
                    break;

                case 'maintenance':
                    $v = ($_POST['maintenance_mode'] ?? '0') === '1' ? '1' : '0';
                    save_setting('maintenance_mode', $v);
                    $success = $v === '1' ? '已开启维护模式：前台将显示维护提示，后台不受影响。' : '已关闭维护模式。';
                    break;

// ----- 生成跨站恢复授权码（step-up：必须核对管理员密码） -----
                case 'gen_restore_code':
                    $adminId = (int)($_SESSION['admin_id'] ?? 0);
                    if (!gblog_rate_allow('restore_code_gen', gblog_client_ip(), 5, 900)) {
                        $error = '尝试过于频繁，请 15 分钟后再试。';
                        break;
                    }
                    $pw   = gblog_backup_text($_POST, 'admin_password');
                    $hash = (string)($GLOBALS['gblog_admin_user']['password_hash'] ?? '');
                    if ($pw === '' || $hash === '' || !password_verify($pw, $hash)) {
                        gblog_audit('restore.code.bad_password', $adminId, '');
                        $error = '管理员密码不正确，未生成恢复码。';
                        break;
                    }
                    $restoreCodePlain = gblog_restore_code_create();
                    gblog_audit('restore.code.generated', $adminId, 'ttl=900');
                    $success = '恢复码已生成，请立即复制保存。';
                    break;

                case 'revoke_restore_code':
                    gblog_restore_code_consume();
                    gblog_audit('restore.code.revoked', (int)($_SESSION['admin_id'] ?? 0), '');
                    $success = '恢复码已撤销，跨站恢复通道已关闭。';
                    break;

                default:
                    $error = '未知操作。';
            }
        } catch (Throwable $e) {
            $error = '操作失败：' . $e->getMessage();
        }
    }
}

$backups = [];
if ($backupDirReady) {
    foreach (array_merge(glob($backupDir . '/*.sql') ?: [], glob($backupDir . '/*.zip') ?: []) as $f) {
        $backups[] = [
            'name' => basename($f),
            'size' => (int)filesize($f),
            'time' => (int)filemtime($f),
            'full' => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip',
        ];
    }
}
usort($backups, function ($a, $b) { return $b['time'] <=> $a['time']; });

$maintenanceMode = get_setting('maintenance_mode', '') === '1';
?>
<?php
admin_sub_tabs([
    'data'     => gblog_admin_icon('data') . ' 数据备份',
    'security' => gblog_admin_icon('security') . ' 安全设置',
    'env'      => gblog_admin_icon('settings') . ' 环境自检',
], $tab, admin_flash_html($error, $success));
?>

<?php if ($tab === 'data'): ?>
<div class="card card-connected" style="padding:20px 24px;">
  <?php if (!$backupDirReady): ?>
  <div style="background:#fdecea;color:#a93226;border:1px solid #f5c6cb;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px;line-height:1.7;">
    当前服务器没有可写的备份目录（常见于虚拟主机的 <code>open_basedir</code> 限制），「立即备份」与「恢复前自动备份」已停用。请在主机面板放行目录权限后再试；<b>恢复数据前请务必自行留存一份当前数据库备份</b>。
  </div>
  <?php endif; ?>
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
    <div style="flex:1;min-width:200px;border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
      <b>数据备份</b>
      <p style="font-size:12px;color:var(--muted);margin:6px 0 10px;">将全部数据库导出为 SQL 保存到服务器备份目录，可从下方列表下载到本地。</p>
      <?php if ($backupDir !== ''): ?>
      <p style="font-size:11px;color:var(--muted);margin:-4px 0 10px;word-break:break-all;line-height:1.6;">服务器备份目录：<?= e($backupDir) ?><br>位于站点内，随站点目录一起备份/搬家；该目录已整目录禁止 HTTP 访问。</p>
      <?php endif; ?>
      <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn btn-primary">立即备份</button>
      </form>
    </div>
    <div style="flex:1;min-width:200px;border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
      <b>完整备份（推荐）</b>
      <p style="font-size:12px;color:var(--muted);margin:6px 0 10px;line-height:1.7;">数据库 + 全部上传附件打包成一个 .zip。换服务器、整站搬家、灾难恢复用它，不会出现「数据回来了、图片和音频全丢」。</p>
      <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="hidden" name="action" value="backup_full">
        <button type="submit" class="btn btn-primary">完整备份</button>
      </form>
    </div>
    <div style="flex:1;min-width:200px;border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
      <b>数据恢复</b>
      <p style="font-size:12px;color:var(--muted);margin:6px 0 10px;line-height:1.7;">
        上传 Gblog 导出的 .sql 或 .zip 还原。<b style="color:#e53935;">将覆盖当前数据，请谨慎。</b><br>
        本站备份留空即可；<b>其他站点 / 无签名备份</b>须先在「安全设置」生成一次性恢复码。
      </p>
      <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>" enctype="multipart/form-data" onsubmit="return confirm('确定上传并恢复？将覆盖当前数据库内容！');">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="hidden" name="action" value="restore_upload">
        <input type="file" name="sql_file" accept=".sql,.zip" required style="font-size:13px;">
        <input type="text" name="restore_code" placeholder="恢复码（本站备份留空）" autocomplete="off"
               style="width:100%;box-sizing:border-box;margin-top:6px;font-size:13px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-top:8px;">
          <input type="checkbox" name="restore_files" value="1" style="margin:0;"> 同时还原 .zip 内的上传附件
        </label>
        <button type="submit" class="btn" style="margin-top:6px;">上传并恢复</button>
      </form>
      <p style="font-size:11px;color:var(--muted);margin:8px 0 0;line-height:1.6;">
        跨站恢复不会替换管理员账号与密码；.sql 备份不含附件文件。仅支持 Gblog 自身导出的备份格式。
      </p>
    </div>
    <div style="flex:1;min-width:200px;border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
      <b>数据清理</b>
      <p style="font-size:12px;color:var(--muted);margin:6px 0 10px;line-height:1.7;">清理不可恢复，如需保留请先备份。<br>「清理全部」会清空全部内容、自建栏目、分类与广告位；<b>5 个内置栏目、站点设置与管理员账号保留</b>。</p>
      <div style="display:flex;gap:8px;">
        <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>" onsubmit="return confirm('确定清理测试数据？此操作不可恢复！');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="tab" value="<?= e($tab) ?>">
          <input type="hidden" name="action" value="clean_test">
          <button type="submit" class="btn btn-sm" style="background:#ff9800;border-color:#ff9800;color:#fff;">清理测试数据</button>
        </form>
        <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>" onsubmit="return confirm('危险操作！将清空全部内容、自建栏目、分类与广告位；5 个内置栏目、站点设置与管理员账号会保留。不可恢复，确定继续？');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="tab" value="<?= e($tab) ?>">
          <input type="hidden" name="action" value="clean_all">
          <button type="submit" class="btn btn-sm btn-danger">清理全部</button>
        </form>
      </div>
    </div>
  </div>

  <div style="margin-top:24px;border-top:1px solid var(--border);padding-top:16px;">
    <b style="display:block;margin-bottom:4px;">已有备份（可下载到本地 / 一键恢复）</b>
    <p style="font-size:12px;color:var(--muted);margin:0 0 10px;">服务器上的备份均为本站导出，无需恢复码；若手动放入了其他站点的备份，可在下方填写恢复码。</p>
    <?php if (!$backups): ?>
      <p style="font-size:13px;color:var(--muted);">暂无备份，先在上方执行「立即备份」。</p>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border);">
          <th style="padding:8px 6px;">备份文件</th>
          <th style="padding:8px 6px;">大小</th>
          <th style="padding:8px 6px;">时间</th>
          <th style="padding:8px 6px;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($backups as $b): ?>
        <tr style="border-bottom:1px solid #f2f4f7;">
          <td style="padding:8px 6px;">
            <?= e($b['name']) ?>
            <span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;font-size:11px;<?= $b['full'] ? 'background:#e8f0fe;color:#1a56b8;' : 'background:#f1f3f5;color:#6b7280;' ?>"><?= $b['full'] ? '完整' : '仅数据库' ?></span>
          </td>
          <td style="padding:8px 6px;"><?= $b['size'] >= 1048576 ? number_format($b['size'] / 1048576, 2) . ' MB' : number_format($b['size'] / 1024, 1) . ' KB' ?></td>
          <td style="padding:8px 6px;"><?= e(date('Y-m-d H:i:s', $b['time'])) ?></td>
          <td style="padding:8px 6px;display:flex;gap:8px;">
            <a class="btn btn-sm" href="<?= gblog_admin_url('system/backup.php?tab=data&download=' . urlencode($b['name'])) ?>">下载到本地</a>
            <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>" style="display:inline;" onsubmit="return confirm('<?= $b['full'] ? '确定从该完整备份恢复？将覆盖当前数据库并还原附件文件！' : '确定从该备份恢复？将覆盖当前数据！' ?>');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <input type="hidden" name="action" value="restore_file">
              <input type="hidden" name="backup_name" value="<?= e($b['name']) ?>">
              <input type="text" name="restore_code" placeholder="恢复码" autocomplete="off" style="width:110px;font-size:12px;">
              <button type="submit" class="btn btn-sm btn-danger">恢复</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'security'): ?>
<div class="card card-connected" style="padding:20px 24px;margin-bottom:16px;">
  <b>管理员密码</b>
  <p style="font-size:12px;color:var(--muted);margin:6px 0 12px;">修改密码统一使用当前密码验证。</p>
  <a class="btn btn-primary" href="<?= gblog_admin_url('account/password.php') ?>">前往修改密码</a>
</div>

<div class="card" style="padding:20px 24px;">
  <b>前台维护模式</b>
  <p style="font-size:12px;color:var(--muted);margin:6px 0 12px;">开启后，前台所有页面显示维护提示，访客无法访问；后台登录与管理不受影响。</p>
  <form method="post" action="<?= gblog_admin_url('system/backup.php') ?>" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="hidden" name="action" value="maintenance">
    <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
      <input type="checkbox" name="maintenance_mode" value="1" <?= $maintenanceMode ? 'checked' : '' ?>> 开启维护模式
    </label>
    <button type="submit" class="btn">保存</button>
  </form>
</div>

<div class="card" style="padding:20px 24px;margin-top:16px;">
  <b>跨站恢复授权码</b>
  <p style="font-size:12px;color:var(--muted);margin:6px 0 12px;line-height:1.8;">
    默认只允许恢复<b>本站导出</b>的备份（带本站签名）。需要导入其他站点、本地旧备份或手工处理过的备份时，
    用当前管理员密码生成一个<b>一次性恢复码</b>：15 分钟内有效、用一次即失效、明文只显示一次、可随时撤销。
    授权状态只存在当前后台会话里，不写入数据库，因此不会被恢复动作本身覆盖。
  </p>

  <?php if ($restoreCodePlain !== ''): ?>
  <div style="background:#eef7ee;border:1px solid #b7dfbc;color:#1b6b34;border-radius:8px;padding:14px 16px;margin-bottom:14px;">
    <div style="font-size:13px;margin-bottom:8px;">请立即复制保存，刷新或离开本页后将不再显示：</div>
    <code style="display:inline-block;font-size:22px;letter-spacing:2px;user-select:all;"><?= e($restoreCodePlain) ?></code>
  </div>
  <?php endif; ?>

  <?php if (gblog_restore_code_pending()): ?>
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
    <span style="font-size:13px;">当前存在有效恢复码，剩余约 <b><?= (int)ceil(gblog_restore_code_remaining() / 60) ?></b> 分钟。</span>
    <form method="post" action="<?= gblog_admin_url('system/backup.php?tab=security') ?>" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="revoke_restore_code">
      <button type="submit" class="btn btn-sm btn-danger">撤销恢复码</button>
    </form>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= gblog_admin_url('system/backup.php?tab=security') ?>" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="gen_restore_code">
    <div>
      <label style="font-size:13px;">当前管理员密码（生成新码会作废旧码）</label>
      <input type="password" name="admin_password" required autocomplete="current-password" style="width:220px;">
    </div>
    <button type="submit" class="btn btn-primary">生成恢复码</button>
  </form>
  <p style="font-size:11px;color:var(--muted);margin:10px 0 0;line-height:1.7;">
    恢复码仅对「数据恢复」中缺少本站签名的备份生效；本站备份不需要它。跨站恢复会保留当前管理员账号与密码，
    忽略来源站的账号 / 令牌 / 限流 / 审计记录，并复位站点密钥、维护状态与 schema 版本。
  </p>
</div>

<?php elseif ($tab === 'env'): ?>
<div class="card card-connected" style="padding:20px 24px;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:4px;">
    <div>
      <b>环境自检</b>
      <p style="font-size:12px;color:var(--muted);margin:6px 0 0;line-height:1.8;">
        只读检测，不会修改任何配置。覆盖升级后先核对「核心文件指纹」是否与发布包一致；
        排障时可导出诊断文本（不含数据库口令、站点密钥与 Cookie）。
      </p>
    </div>
    <a class="btn btn-primary" href="<?= gblog_admin_url('system/backup.php?tab=env&amp;diag=1') ?>">导出诊断信息</a>
  </div>

  <?php foreach (gblog_env_probe() as $group => $rows): ?>
  <div style="margin-top:20px;">
    <b style="font-size:13px;"><?= e($group) ?></b>
    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:8px;table-layout:fixed;">
      <colgroup><col style="width:26%;"><col style="width:40%;"><col style="width:34%;"></colgroup>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <?php
          $colors = ['ok' => '#1b6b34', 'warn' => '#a8680b', 'bad' => '#c0392b'];
          $marks  = ['ok' => '正常', 'warn' => '注意', 'bad' => '异常'];
          $c = $colors[$r['state']] ?? 'var(--muted)';
          $m = $marks[$r['state']] ?? '';
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px 8px 8px 0;color:var(--muted);vertical-align:top;"><?= e($r['label']) ?></td>
          <td style="padding:8px 8px 8px 0;word-break:break-all;vertical-align:top;">
            <?= e($r['value']) ?>
            <?php if ($m !== ''): ?><span style="color:<?= $c ?>;font-size:12px;white-space:nowrap;">［<?= e($m) ?>］</span><?php endif; ?>
          </td>
          <td style="padding:8px 0;color:var(--muted);font-size:12px;line-height:1.6;vertical-align:top;"><?= e($r['note']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
