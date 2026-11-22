<?php
require_once __DIR__ . '/../inc/icons.php';
$gbTabs = ['store' => gblog_admin_icon('store') . ' 应用中心', 'installed' => gblog_admin_icon('installed') . ' 应用管理'];
$gbTab  = (string)($_GET['tab'] ?? 'store');
if (!isset($gbTabs[$gbTab])) { $gbTab = 'store'; }
$gbApp  = $gbTab === 'installed' ? (string)($_GET['app'] ?? '') : '';
$page_title = $gbApp !== '' ? '插件设置' : ($gbTab === 'installed' ? '应用管理' : '应用中心');
require_once __DIR__ . '/../inc/header.php';
gblog_admin_require_cap('system.apps');
require_once __DIR__ . '/../inc/tabs.php';
if ($gbTab === 'installed') {
    require __DIR__ . ($gbApp !== '' ? '/../inc/sections/app_settings.php' : '/../inc/sections/apps.php');
    require __DIR__ . '/../inc/footer.php';
    return;
}

$msg = '';
$err = '';

function gblog_download_zip(string $url, string $expectedSha256 = ''): string
{
    if (!gblog_url_safe_for_fetch($url)) return '';
    $ctx  = stream_context_create(['http' => [
        'timeout' => 10, 'follow_location' => 0, 'max_redirects' => 0,
        'header' => "User-Agent: Gblog-AppCenter\r\n",
    ]]);
    $data = @file_get_contents($url, false, $ctx, 0, 8 * 1024 * 1024);
    if ($data === false || strlen($data) < 100) return '';
    if (substr($data, 0, 2) !== 'PK') return '';
// 索引声明了 sha256 就必须匹配
    if ($expectedSha256 !== '' && preg_match('/^[a-f0-9]{64}$/i', $expectedSha256)) {
        if (!hash_equals(strtolower($expectedSha256), hash('sha256', $data))) return '';
    }
    $tmp = sys_get_temp_dir() . '/gblog_app_' . bin2hex(random_bytes(6)) . '.zip';
    return file_put_contents($tmp, $data) !== false ? $tmp : '';
}

function gblog_remote_app_sha256(string $url): string
{
    $src = (string)get_setting('app_store_url', '');
    if ($src === '' || !gblog_url_safe_for_fetch($src)) return '';
    $ctx = stream_context_create(['http' => [
        'timeout' => 6, 'follow_location' => 0, 'max_redirects' => 0,
        'header' => "User-Agent: Gblog-AppCenter\r\n",
    ]]);
    $raw = @file_get_contents($src, false, $ctx, 0, 1024 * 1024);
    $idx = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($idx) || !isset($idx['apps']) || !is_array($idx['apps'])) return '';
    foreach ($idx['apps'] as $a) {
        if (is_array($a) && (string)($a['download'] ?? '') === $url) {
            return (string)($a['sha256'] ?? '');
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = '表单校验失败。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_source') {
            $newSource = trim((string)($_POST['app_store_url'] ?? ''));
            if ($newSource !== '' && !gblog_url_safe_for_fetch($newSource)) {
                $err = '应用源地址不合法或指向内网，未保存。';
            } else {
                save_setting('app_store_url', $newSource);
                $msg = '应用源已保存。';
            }
        } elseif ($action === 'upload') {
            if (!empty($_FILES['app_zip']['tmp_name']) && is_uploaded_file($_FILES['app_zip']['tmp_name'])) {
                [$ok, $text] = gblog_install_zip($_FILES['app_zip']['tmp_name']);
                $ok ? $msg = $text : $err = $text;
            } else {
                $err = '请选择 .zip 应用包。';
            }
        } elseif ($action === 'remote_install') {
            $url = (string)($_POST['download'] ?? '');
            $tmp = gblog_download_zip($url, gblog_remote_app_sha256($url));
            if ($tmp === '') {
                $err = '下载失败、文件不是 zip，或校验值与索引不一致。';
            } else {
                [$ok, $text] = gblog_install_zip($tmp);
                $ok ? $msg = $text : $err = $text;
                @unlink($tmp);
            }
        }
    }
}

$source = (string)get_setting('app_store_url', '');
$remoteApps = [];
$remoteErr  = '';
if ($source !== '') {
    if (!gblog_url_safe_for_fetch($source)) {
        $remoteErr = '应用源地址不合法或指向内网，已拒绝访问。';
    } else {
        $ctx  = stream_context_create(['http' => [
            'timeout' => 6, 'follow_location' => 0, 'max_redirects' => 0,
            'header' => "User-Agent: Gblog-AppCenter\r\n",
        ]]);
        $raw  = @file_get_contents($source, false, $ctx, 0, 1024 * 1024);
        $json = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($json) && isset($json['apps']) && is_array($json['apps'])) {
            $remoteApps = $json['apps'];
        } else {
            $remoteErr = '应用源暂时不可用或格式不正确（需要 {"apps":[...]}）。';
        }
    }
}
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html($err, $msg));
?>
<div class="card card-connected">
  <p style="color:var(--muted);margin-bottom:16px;">核心版本 <code><?= e(GBLOG_CORE_VERSION) ?></code>，安装时校验 <code>requires_core</code>；安装后默认关闭，请到「应用管理」开启。</p>

  <form method="post" action="<?= gblog_admin_url('system/appcenter.php') ?>" style="margin-bottom:16px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_source">
    <label style="display:block;font-size:13px;margin-bottom:4px;">应用源索引 URL（留空则只用本地上传）</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <input type="url" name="app_store_url" value="<?= e($source) ?>" placeholder="https://example.com/gblog-apps.json" style="flex:1;min-width:260px;">
      <button type="submit" class="btn btn-primary">保存并刷新</button>
    </div>
  </form>

  <form method="post" action="<?= gblog_admin_url('system/appcenter.php') ?>" enctype="multipart/form-data" style="border-top:1px solid var(--border);padding-top:14px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <label style="display:block;font-size:13px;margin-bottom:4px;">本地上传应用包（.zip，包内含 manifest.json）</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <input type="file" name="app_zip" accept=".zip,application/zip" required>
      <button type="submit" class="btn">上传并安装</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>可下载的应用</h2>
  <?php if ($source === ''): ?>
    <p style="color:var(--muted);">尚未配置应用源。填入索引 URL 后，这里会列出可一键下载安装的主题 / 插件；也可以直接用上面的本地上传。</p>
  <?php elseif ($remoteErr !== ''): ?>
    <p style="color:#c0392b;"><?= e($remoteErr) ?></p>
  <?php elseif (!$remoteApps): ?>
    <p style="color:var(--muted);">应用源里暂无应用。</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>类型</th><th>应用</th><th>版本</th><th>说明</th><th>核心要求</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($remoteApps as $a): ?>
          <?php $compatible = gblog_core_compatible((string)($a['requires_core'] ?? '1.0.0')); ?>
          <tr>
            <td><?= ($a['type'] ?? '') === 'theme' ? gblog_admin_icon('themes') . ' 皮肤' : gblog_admin_icon('appcenter') . ' 插件' ?></td>
            <td><b><?= e((string)($a['name'] ?? $a['id'] ?? '')) ?></b><br><code style="font-size:11px;"><?= e((string)($a['id'] ?? '')) ?></code></td>
            <td><?= e((string)($a['version'] ?? '')) ?></td>
            <td><?= e((string)($a['desc'] ?? '')) ?></td>
            <td><code><?= e((string)($a['requires_core'] ?? '1.0.0')) ?></code><?= $compatible ? '' : '<div style="color:#c0392b;">版本不兼容</div>' ?></td>
            <td>
              <?php if ($compatible && !empty($a['download'])): ?>
              <form method="post" action="<?= gblog_admin_url('system/appcenter.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remote_install">
                <input type="hidden" name="download" value="<?= e((string)$a['download']) ?>">
                <button type="submit" class="btn btn-sm btn-primary">下载安装</button>
              </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
