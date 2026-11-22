<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$msg = '';
$tabUrl = gblog_admin_url('system/appcenter.php?tab=installed');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = (string)($_POST['action'] ?? '');
    $type   = (string)($_POST['type'] ?? '');
    $id     = (string)($_POST['id'] ?? '');
    if (in_array($action, ['enable', 'disable'], true) && $type === 'plugin' && gblog_app_id_valid($id)) {
        gblog_register_app($type, $id, (string)(gblog_read_manifest($type, $id)['version'] ?? '1.0'), true);
        gblog_set_app_enabled($type, $id, $action === 'enable');
        $msg = $action === 'enable' ? '插件已启用，刷新前台即可看到效果。' : '插件已停用。';
    } elseif ($action === 'uninstall' && $type === 'plugin' && gblog_app_id_valid($id)
              && !gblog_app_is_builtin($type, $id)) {
        $dir = gblog_app_dir($type, $id);
        if ($dir !== null && is_dir($dir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($dir);
            gblog_remove_app_record($type, $id);
            $msg = '插件已卸载。';
        }
    } elseif ($action === 'uninstall' && $type === 'plugin' && gblog_app_id_valid($id)
              && gblog_app_is_builtin($type, $id)) {
        $msg = '该插件是系统内置组件，不能卸载；可在本页随时启用或停用。';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = '表单校验失败。';
}

$apps = gblog_scan_apps();
require_once __DIR__ . '/../tabs.php';
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html('', $msg));
?>
<div class="card card-connected">
  <p style="color:var(--muted);margin-bottom:8px;">核心版本 <code><?= e(GBLOG_CORE_VERSION) ?></code> · 应用包声明的 <code>requires_core</code> 高于此版本时会被拒绝加载。<a href="<?= gblog_admin_url('system/appcenter.php?tab=store') ?>">去应用中心安装新应用 →</a></p>
  <p style="color:var(--muted);margin-bottom:16px;">系统内置应用不可卸载，可随时启用或停用。</p>

  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>类型</th><th>应用</th><th>版本 / 要求</th><th>说明</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$apps): ?>
        <tr><td colspan="6" style="color:#6b7280;">暂无应用包。可在 <code>plugins/&lt;id&gt;/</code> 放 manifest.json + plugin.php。</td></tr>
      <?php endif; ?>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td><?= $a['type'] === 'theme' ? gblog_admin_icon('themes') . ' 皮肤' : gblog_admin_icon('appcenter') . ' 插件' ?></td>
          <td><b><?= e($a['name']) ?></b><br><code style="font-size:11px;"><?= e($a['id']) ?></code></td>
          <td><?= e($a['version']) ?><br><code style="font-size:11px;color:var(--muted);">≥ <?= e($a['requires_core']) ?></code></td>
          <td style="max-width:320px;"><?= e($a['desc']) ?>
            <?php if (!$a['compatible']): ?><div style="color:#c0392b;">核心版本不兼容，已跳过加载</div><?php endif; ?>
            <?php if ($a['type'] === 'plugin' && !$a['has_entry']): ?><div style="color:#c0392b;">缺少入口文件 <?= e($a['entry']) ?></div><?php endif; ?>
            <?php if ($a['hooks']): ?><div style="color:var(--muted);font-size:11px;margin-top:4px;">钩子：<?= e(implode(', ', array_map('strval', $a['hooks']))) ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($a['type'] === 'theme'): ?>
              <span class="badge <?= $a['enabled'] ? 'badge-on' : 'badge-off' ?>"><?= $a['enabled'] ? '使用中' : '未使用' ?></span>
              <div style="font-size:11px;color:var(--muted);margin-top:4px;"><a href="<?= gblog_admin_url('system/themes.php?tab=layout') ?>">到主题模板选皮肤</a></div>
            <?php else: ?>
              <span class="badge <?= $a['enabled'] ? 'badge-on' : 'badge-off' ?>"><?= $a['enabled'] ? '已启用' : '已停用' ?></span>
              <?php if (!empty($a['builtin'])): ?><div style="font-size:11px;color:var(--muted);margin-top:4px;">系统内置 · 不可卸载</div><?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;">
            <?php if ($a['type'] === 'plugin' && $a['compatible'] && $a['has_entry']): ?>
              <?php if (!empty($a['settings'])): ?>
              <a class="btn btn-sm" href="<?= gblog_admin_url('system/appcenter.php?tab=installed&amp;app=' . e($a['id'])) ?>">设置</a>
              <?php endif; ?>
              <form method="post" action="<?= e($tabUrl) ?>" style="display:inline;margin-left:4px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="type" value="plugin">
                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <input type="hidden" name="action" value="<?= $a['enabled'] ? 'disable' : 'enable' ?>">
                <button type="submit" class="btn btn-sm <?= $a['enabled'] ? '' : 'btn-primary' ?>"><?= $a['enabled'] ? '停用' : '启用' ?></button>
              </form>
              <?php if (empty($a['builtin'])): ?>
              <form method="post" action="<?= e($tabUrl) ?>" style="display:inline;margin-left:4px;" onsubmit="return confirm('确定卸载插件 <?= e($a['id']) ?>？其目录将被删除。');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="type" value="plugin">
                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <input type="hidden" name="action" value="uninstall">
                <button type="submit" class="btn btn-sm">卸载</button>
              </form>
              <?php else: ?>
              <span style="font-size:11px;color:var(--muted);margin-left:4px;">内置组件</span>
              <?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
