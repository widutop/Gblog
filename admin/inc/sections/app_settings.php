<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$gbAppId  = (string)($_GET['app'] ?? '');
$gbAppM   = (gblog_app_id_valid($gbAppId) && function_exists('gblog_read_manifest')) ? gblog_read_manifest('plugin', $gbAppId) : null;
$gbSettFile = $gbAppM ? gblog_app_settings_file($gbAppM) : null;
$gbEnabled = false;
if ($gbAppM) {
    try {
        $gbSt = db()->prepare('SELECT enabled FROM apps WHERE app_id = ? AND type = ? LIMIT 1');
        $gbSt->execute([$gbAppId, 'plugin']);
        $gbEnabled = (int)$gbSt->fetchColumn() === 1;
    } catch (Throwable $e) {
    }
}
?>
<div class="card card-connected" style="margin-bottom:14px;">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <a class="btn btn-sm" href="<?= gblog_admin_url('system/appcenter.php?tab=installed') ?>">← 返回应用管理</a>
    <b><?= e((string)($gbAppM['name'] ?? '插件设置')) ?></b>
    <code style="font-size:11px;color:var(--muted);"><?= e($gbAppId) ?></code>
    <?php if ($gbAppM): ?>
    <span class="badge <?= $gbEnabled ? 'badge-on' : 'badge-off' ?>"><?= $gbEnabled ? '已启用' : '已停用' ?></span>
    <?php endif; ?>
  </div>
</div>
<?php
if (!$gbAppM) {
    echo '<div class="card card-connected"><p>插件不存在或已被移除。</p></div>';
    return;
}
if ($gbSettFile === null) {
    echo '<div class="card card-connected"><p>该插件没有提供设置页。</p></div>';
    return;
}
include $gbSettFile;
