<?php
require_once __DIR__ . '/../inc/icons.php';
$gbTabs = [
    'skins'      => gblog_admin_icon('themes') . ' 主题模板',
    'layout'     => gblog_admin_icon('appearance') . ' 页面模板',
    'appearance' => gblog_admin_icon('code') . ' 自定义 CSS',
];
$gbTab  = (string)($_GET['tab'] ?? 'skins');
if (!isset($gbTabs[$gbTab])) { $gbTab = 'skins'; }
$page_title = ['skins' => '主题模板', 'layout' => '页面模板', 'appearance' => '自定义 CSS'][$gbTab] ?? '主题模板';
require_once __DIR__ . '/../inc/header.php';
gblog_admin_require_cap('settings.manage');
require_once __DIR__ . '/../inc/tabs.php';
if ($gbTab !== 'skins') {
    $__section = $gbTab === 'layout' ? 'layout.php' : 'appearance.php';
    require __DIR__ . '/../inc/sections/' . $__section;
    require __DIR__ . '/../inc/footer.php';
    return;
}

$pdo = db();
$msg = '';
$themeNames = [];
foreach (get_theme_list() as $t) { $themeNames[$t['dir']] = $t['name']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = '表单校验失败。';
    } else {
        $theme = (string)($_POST['theme'] ?? '');
        if (isset($themeNames[$theme])) {
            save_setting('current_theme', $theme);
            $msg = '全站主题已设为「' . $themeNames[$theme] . '」。';
        } else {
            $msg = '主题不存在。';
        }
    }
}

$active = get_setting('current_theme', get_setting('home_theme', 'default'));
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html('', $msg));
?>
<div class="card card-connected">
  <p style="color:var(--muted);margin-bottom:16px;">选一个主题包作为<b>全站统一主题</b>：整站共用一套版式与配色。各栏目内容类型（文章/软件/音乐/小说/图片）在「页面模板」tab 分配；主题切换在此完成。</p>
  <form method="post" action="<?= gblog_admin_url('system/themes.php?tab=skins') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
      <?php foreach (get_theme_list() as $t):
        $dir      = $t['dir'];
        $isActive = $dir === $active;
        $desc     = $t['description'] ?: ($t['desc'] ?? '');
        $tags     = $t['tags'] ?? [];
        $supports = $t['supports'] ?? [];
        $parent   = (string)($t['parent'] ?? '');
        $childOf  = $parent !== '' ? ($themeNames[$parent] ?? $parent) : '';
        // 主题截图：theme.json 声明的文件（相对于主题目录，仅允许文件名）。后台在 /admin/ 下，必须用根绝对路径
        // screenshot 含 .. 可指向主题目录外任意文件（DB 被篡改时借预览外读），必须拒绝
        $shotFile = (string)($t['screenshot'] ?? '');
        if ($shotFile === '' || strpos($shotFile, '..') !== false || preg_match('#^[\\\\/]|://#', $shotFile)) {
            $shotFile = '';
        }
        $shotUrl  = ($shotFile !== '' && is_file(THEMES_DIR . '/' . $dir . '/' . $shotFile))
            ? '/usr/themes/' . rawurlencode($dir) . '/' . $shotFile : '';
        $descShort = mb_substr($desc, 0, 42, 'UTF-8');
        if ($descShort !== $desc) $descShort .= '…';
      ?>
      <div class="card" style="border:2px solid <?= $isActive ? 'var(--primary)' : 'var(--border)' ?>;display:flex;flex-direction:column;">
        <?php if ($shotUrl): ?>
        <img class="theme-shot" src="<?= e($shotUrl) ?>" alt="<?= e($t['name']) ?> 预览" loading="lazy"
             data-shot-full="<?= e($shotUrl) ?>" tabindex="0" title="悬停或用 Tab 聚焦查看大图"
             style="width:100%;height:110px;object-fit:cover;object-position:top;border-radius:8px;border:1px solid var(--border);margin-bottom:10px;background:var(--bg-alt);">
        <?php else: ?>
        <div style="height:56px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:15px;letter-spacing:.04em;margin-bottom:10px;">
          <?= e($t['name']) ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <code style="font-size:12px;color:var(--muted);"><?= e($dir) ?></code>
          <?php if ($isActive): ?><span class="btn btn-sm" style="background:var(--primary-light);color:var(--primary);cursor:default;font-weight:600;font-size:11px;">使用中</span><?php endif; ?>
          <?php if ($childOf): ?><span style="font-size:11px;color:var(--muted);">子主题 · <?= e($childOf) ?></span><?php endif; ?>
        </div>
        <?php if ($descShort !== ''): ?><p title="<?= e($desc) ?>" style="font-size:12px;margin:6px 0 0;color:var(--text-muted);line-height:1.5;"><?= e($descShort) ?></p><?php endif; ?>
        <?php if ($tags): ?><div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
          <?php foreach ($tags as $tg): ?><span style="font-size:11px;background:var(--bg-alt);border:1px solid var(--border);border-radius:999px;padding:2px 8px;color:var(--muted);"><?= e($tg) ?></span><?php endforeach; ?>
        </div><?php endif; ?>
        <p style="color:var(--muted);font-size:11px;margin:6px 0 0;">作者：<?= e($t['author'] ?: '—') ?><?php if (!empty($t['version'])): ?> · v<?= e($t['version']) ?><?php endif; ?></p>
        <div style="margin-top:auto;padding-top:12px;">
        <?php if ($isActive): ?>
          <span class="btn btn-sm" style="background:var(--primary-light);color:var(--primary);cursor:default;font-weight:600;">● 当前使用</span>
        <?php else: ?>
          <button type="submit" class="btn btn-primary btn-sm" name="theme" value="<?= e($dir) ?>">设为主题模板</button>
        <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </form>
</div>
<script src="<?= gblog_admin_url('assets/theme-shot.js') ?>" defer></script>
<?php require __DIR__ . '/../inc/footer.php'; ?>
