<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$tabUrl = gblog_admin_url('system/themes.php?tab=appearance');
$views = [
    'home'   => '栏目列表页',
    'list'   => '分类页',
    'detail' => '内容页',
    'page'   => '其他页面',
];

$sites = db()->query("SELECT id, name, slug FROM sites ORDER BY sort_order ASC, id ASC")->fetchAll();
$rows = [['slug' => '_home', 'name' => '全站首页']];
foreach ($sites as $s) {
    $rows[] = ['slug' => $s['slug'], 'name' => $s['name']];
}
$allowedSlugs = array_column($rows, 'slug');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = '表单校验失败。';
    } else {
        $posted = $_POST['css'] ?? [];
        $n = 0;
        if (is_array($posted)) {
            foreach ($posted as $slug => $byView) {
                if (!in_array((string)$slug, $allowedSlugs, true) || !is_array($byView)) continue;
                foreach ($byView as $view => $css) {
                    if (!isset($views[(string)$view])) continue;
                    $css = trim((string)$css);
                    $css = str_ireplace(['</style', '@import', 'expression(', 'javascript:'], '', $css);
                    save_setting('css:' . $slug . ':' . $view, $css);
                    $n++;
                }
            }
        }
        $msg = '已保存 ' . $n . ' 格自定义样式。清空某一格并保存即可恢复该处的内置默认样式。';
    }
}
require_once __DIR__ . '/../tabs.php';
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html('', $msg));
?>
<div class="card card-connected">
    <p style="color:#c0392b;margin-bottom:8px;"><b>注意</b>：自定义 CSS 最后加载，优先级高于模板样式；留空即恢复内置样式。</p>

  <form method="post" action="<?= e($tabUrl) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php foreach ($rows as $i => $row): ?>
    <details <?= $i === 0 ? 'open' : '' ?> style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-bottom:10px;">
      <summary style="cursor:pointer;font-size:14px;"><b><?= e($row['name']) ?></b> <code><?= e($row['slug']) ?></code> <a href="<?= gblog_admin_url('system/themes.php?tab=layout') ?>" onclick="event.stopPropagation();" style="font-size:12px;color:var(--primary);text-decoration:none;">页面模板 →</a></summary>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:12px;">
        <?php foreach ($views as $vk => $vn): ?>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:4px;"><?= e($vn) ?> <code style="font-size:11px;color:var(--muted);">css:<?= e($row['slug']) ?>:<?= e($vk) ?></code></label>
          <textarea name="css[<?= e($row['slug']) ?>][<?= e($vk) ?>]" rows="5" spellcheck="false" placeholder="例如：body[data-site=&quot;<?= e($row['slug']) ?>&quot;] .card { border-radius: 16px; }" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:12px;padding:8px;border:1px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);"><?= e(get_setting('css:' . $row['slug'] . ':' . $vk, '')) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary" style="margin-top:8px;">保存全部</button>
  </form>
</div>
