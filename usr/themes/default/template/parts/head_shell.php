<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
// 前台维护模式：仅已登录的后台账号可用 ?mbypass=1 预览， 任意访客不能再靠拼一个 GET 参数绕过维护页
$mBypass = !empty($_GET['mbypass']) && is_admin_logged_in();
if ((string)get_setting('maintenance_mode', '') === '1' && !$mBypass) {
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    $mSite = get_setting('site_name', SITE_NAME);
    $mDesc = get_setting('maintenance_notice', '本站正在维护升级，稍后恢复访问，敬请谅解。');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>维护中 - ' . e($mSite) . '</title><style>body{margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;background:#f6f8fb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:#24303f}.mcard{max-width:420px;text-align:center;padding:40px 36px;background:#fff;border:1px solid #e5e9f0;border-radius:12px;box-shadow:0 1px 3px rgba(20,35,60,.08)}.mcard .gear{font-size:46px;line-height:1}.mcard h1{font-size:22px;margin:14px 0 10px}.mcard p{font-size:15px;color:#7a8798;margin:0;line-height:1.8}</style></head><body><div class="mcard"><div class="gear">' . gblog_icon('settings', 40) . '</div><h1>' . e($mSite) . '</h1><p>' . e($mDesc) . '</p></div></body></html>';
    exit;
}

$site = current_site();
$site_name = $site['name'] ?? SITE_NAME;
$site_desc = $site['description'] ?? SITE_DESC;
$logo_site_name = get_setting('site_name', SITE_NAME);
$site_title = get_setting('site_title', '');
if ($site_title === '') $site_title = $logo_site_name;
$current_uri = $_GET['do'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' - ' . e($site_title) : e($site_title) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= gblog_theme_asset_url('assets/favicon.svg') ?>">
<meta name="description" content="<?= isset($page_desc) ? e($page_desc) : e($site_desc) ?>">
<meta name="keywords" content="<?= isset($page_keywords) ? e($page_keywords) : e(get_setting('site_keywords', '博客,技术,原创音乐')) ?>">
<meta property="og:title" content="<?= isset($page_title) ? e($page_title) : e($site_title) ?>">
<meta property="og:description" content="<?= isset($page_desc) ? e($page_desc) : e($site_desc) ?>">
<meta property="og:type" content="website">
<?php $gbCanonical = function_exists('gblog_canonical_url') ? gblog_canonical_url() : ''; ?>
<?php if ($gbCanonical !== ''): ?>
<link rel="canonical" href="<?= e($gbCanonical) ?>">
<meta property="og:url" content="<?= e($gbCanonical) ?>">
<?php endif; ?>
<?php if (function_exists('gblog_should_noindex') && gblog_should_noindex()): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?= theme_stylesheets() ?>
<script>
function setMode(m) {
  var modes = {
    light: { bg:'#f0f2f5', bgAlt:'#eef1f5', bgCard:'#ffffff', card:'#ffffff', elev:'#f1f5f9', text:'#1f2937', muted:'#6b7280', border:'#e5e7eb', headerBg:'rgba(255,255,255,.88)' },
    dark:  { bg:'#10151d', bgAlt:'#161d28', bgCard:'#1a212c', card:'#1a212c', elev:'#1f2430', text:'#e5eaf1', muted:'#94a3b8', border:'#2b3544', headerBg:'rgba(26,33,44,.88)' }
  };
  var c = modes[m];
  if (!c) return;
  var r = document.documentElement.style;
  r.setProperty('--bg', c.bg);
  r.setProperty('--bg-alt', c.bgAlt);
  r.setProperty('--bg-card', c.bgCard);
  r.setProperty('--bg-elev', c.elev);
  r.setProperty('--card', c.card);
  r.setProperty('--text', c.text);
  r.setProperty('--text-muted', c.muted);
  r.setProperty('--border', c.border);
  r.setProperty('--header-bg', c.headerBg);
  document.body.setAttribute('data-mode', m);
  var t = document.getElementById('mode-toggle');
  if (t) { t.innerHTML = m === 'dark' ? window.GBLOG_ICON.moon : window.GBLOG_ICON.sun; t.title = m === 'dark' ? '切换白天模式' : '切换夜间模式'; }
  try { localStorage.setItem('theme_mode', m); } catch(e) {}
}
function toggleMode() {
  var cur = document.body.getAttribute('data-mode');
  setMode(cur === 'dark' ? 'light' : 'dark');
}
(function(){
  function initMode() {
    try {
      var m = localStorage.getItem('theme_mode');
      setMode(m === 'dark' ? 'dark' : 'light');
    } catch(e) { setMode('light'); }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMode);
  } else {
    initMode();
  }
})();
</script>
<script>window.GBLOG_CSRF = '<?= e(csrf_token()) ?>';</script>
<script>window.GBLOG_ICON = {
  play: '<?= gblog_icon('play', 16) ?>',
  pause: '<?= gblog_icon('pause', 16) ?>',
  prev: '<?= gblog_icon('prev', 16) ?>',
  next: '<?= gblog_icon('next', 16) ?>',
  star: '<?= gblog_icon('star', 16) ?>',
  starFill: '<?= gblog_icon('star-fill', 16) ?>',
  sun: '<?= gblog_icon('sun', 16) ?>',
  moon: '<?= gblog_icon('moon', 16) ?>'
};</script>
<?php $stat_code = get_setting('stat_code', ''); if ($stat_code !== ''): ?>
<?php echo $stat_code; ?>
<?php endif; ?>
<?= function_exists('custom_css_block') ? custom_css_block() : '' ?>
<?= function_exists('gblog_app_assets') ? gblog_app_assets('css') : '' ?>
<?php if (function_exists('do_action')) do_action('head_meta'); ?>
<script src="<?= gblog_theme_asset_url('assets/js/react.js') ?>" defer></script>
<script src="<?= gblog_theme_asset_url('assets/js/list_tabs.js') ?>" defer></script>
<script src="<?= gblog_theme_asset_url('assets/js/table_sort.js') ?>" defer></script>
</head>
<?php
$gbCtx = function_exists('page_context') ? page_context() : ['site' => ['slug' => 'post'], 'skin' => 'classic', 'view' => 'page', 'home_mode' => 'default'];
?>
<body data-site="<?= e(!empty($gbCtx['is_home']) ? '_home' : ($gbCtx['site']['slug'] ?? 'main')) ?>" data-site-type="<?= e($gbCtx['site']['type'] ?? '') ?>" data-view="<?= e($gbCtx['view']) ?>" data-skin="<?= e($gbCtx['skin']) ?>" data-home-mode="<?= e($gbCtx['home_mode']) ?>" data-layout="<?= e(function_exists('gblog_layout_attr') ? gblog_layout_attr() : 'default') ?>">
<?php if (function_exists('do_action')) do_action('body_start'); ?>
