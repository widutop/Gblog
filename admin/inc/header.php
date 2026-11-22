<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$menu = [
    [gblog_admin_url('index.php?tab=post'), gblog_admin_icon('content') . ' 内容管理', 'post.view'],
    [gblog_admin_url('system/sites.php'),   gblog_admin_icon('sites') . ' 栏目管理', 'settings.manage'],
    [gblog_admin_url('content/comments.php'), gblog_admin_icon('comments') . ' 评论管理', 'comment.manage'],
    [gblog_admin_url('account/users.php'),   gblog_admin_icon('users') . ' 用户管理', 'user.manage'],
    [gblog_admin_url('system/themes.php'),   gblog_admin_icon('themes') . ' 主题管理', 'settings.manage'],
    [gblog_admin_url('content/links.php'),   gblog_admin_icon('links') . ' 友情链接', 'settings.manage'],
    [gblog_admin_url('system/settings.php'), gblog_admin_icon('settings') . ' 站点设置', 'settings.manage'],
    [gblog_admin_url('system/backup.php'),  gblog_admin_icon('backup') . ' 安全管理', 'system.backup'],
    [gblog_admin_url('system/appcenter.php'), gblog_admin_icon('appcenter') . ' 应用中心', 'system.apps'],
];
$menu = apply_filters('admin_menu', $menu);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台 - <?= e($_SESSION['admin_username'] ?? '管理') ?></title>
<link rel="stylesheet" href="<?= gblog_admin_url('assets/admin.css') ?>?v=20261114.1">
<script src="<?= gblog_admin_url('assets/table_sort.js') ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/table_sort.js') ?>" defer></script>
</head>
<body class="admin-body">
<script>/* 后台日夜模式：localStorage 记忆，刷新保持 */
(function(){var d=localStorage.getItem('admin_theme');if(d==='dark'){document.body.classList.add('admin-dark');}})();</script>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-logo">Gblog 后台</div>
    <nav class="admin-nav">
      <?php foreach ($menu as $item):
        if (!gblog_role_can((string)($GLOBALS['gblog_admin_role'] ?? ''), (string)($item[2] ?? 'post.view'))) continue;
        $href = $item[0]; $isActive = false;
        if (strpos($href, '?') !== false) {
            [$base, $qs] = explode('?', $href, 2);
            parse_str($qs, $q);
            $tab = $q['tab'] ?? '';
            $isActive = ($current_page === basename(parse_url($base, PHP_URL_PATH) ?: $base)) && (isset($_GET['tab']) && $_GET['tab'] === $tab);
        } else {
            $isActive = ($current_page === basename(parse_url($href, PHP_URL_PATH) ?: $href));
        }
      ?>
        <a href="<?= $href ?>" class="<?= $isActive ? 'active' : '' ?>"><?= $item[1] ?></a>
      <?php endforeach; ?>
      <a href="<?= gblog_admin_url('logout.php') ?>"><?= gblog_admin_icon('logout') ?> 退出登录</a>
    </nav>
    <div class="admin-user">
      <?= e($_SESSION['admin_username'] ?? '') ?>
      <div class="admin-user-meta">Gblog <?= e(defined('GBLOG_CORE_VERSION') ? GBLOG_CORE_VERSION : '?') ?> · <a href="<?= gblog_admin_url('system/backup.php?tab=env') ?>">环境自检</a></div>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <span class="admin-breadcrumb"><?= e($page_title ?? '管理') ?></span>
      <span style="display:flex;align-items:center;gap:12px;">
        <button type="button" id="admin-theme-toggle" title="切换日夜模式" style="background:var(--card);border:1px solid var(--border);border-radius:6px;padding:5px 8px;cursor:pointer;color:var(--text);line-height:1;display:inline-flex;align-items:center;justify-content:center;"><?= gblog_admin_icon('moon') ?></button>
        <a class="admin-front-link" href="<?= gblog_root_absolute('index.php') ?>" target="_blank">查看前台 ↗</a>
      </span>
    </header>
    <div class="admin-content">
<script>
window.GBLOG_ADMIN_ICON = { sun: '<?= gblog_admin_icon('sun') ?>', moon: '<?= gblog_admin_icon('moon') ?>' };
(function(){
  var btn = document.getElementById('admin-theme-toggle');
  if (!btn) return;
  var sync = function(){ btn.innerHTML = document.body.classList.contains('admin-dark') ? window.GBLOG_ADMIN_ICON.sun : window.GBLOG_ADMIN_ICON.moon; };
  sync();
  btn.addEventListener('click', function(){
    var dark = document.body.classList.toggle('admin-dark');
    localStorage.setItem('admin_theme', dark ? 'dark' : 'light');
    sync();
  });
})();
</script>
<script>
(function () {
  function mobile() {
    return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  }
  function syncBody() {
    var any = document.querySelector('.gb-drawer[data-open="1"]');
    document.body.classList.toggle('gb-drawer-open', !!any);
  }
  function setOpen(box, open, btn) {
    box.setAttribute('data-open', open ? '1' : '0');
    if (btn) btn.textContent = open ? (btn.getAttribute('data-label-close') || '收起') : (btn.getAttribute('data-label-add') || '＋ 添加');
    syncBody();
    if (open && mobile()) window.scrollTo(0, 0);
  }
  function closeOpen() {
    var b = document.querySelector('.gb-drawer[data-open="1"]');
    if (b) setOpen(b, false, b.id ? document.querySelector('[data-drawer-toggle="' + b.id + '"]') : null);
  }
  document.addEventListener('click', function (e) {
    var el = (e.target && e.target.closest) ? e.target : null;
    if (!el) return;
    var t = el.closest('[data-drawer-toggle]');
    if (t) {
      e.preventDefault();
      var box = document.getElementById(t.getAttribute('data-drawer-toggle'));
      if (box) setOpen(box, box.getAttribute('data-open') !== '1', t);
      return;
    }
    var c = el.closest('[data-drawer-close]');
    if (c) {
      e.preventDefault();
      var b = c.closest('.gb-drawer');
      if (b) setOpen(b, false, b.id ? document.querySelector('[data-drawer-toggle="' + b.id + '"]') : null);
      return;
    }
    if (mobile() && document.querySelector('.gb-drawer[data-open="1"]')) closeOpen();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeOpen();
  });
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  ready(function () {
    syncBody();
    if (!mobile()) return;
    var list = document.querySelectorAll('.admin-content > .alert');
    for (var i = 0; i < list.length; i++) {
      (function (a) {
        a.title = '点击关闭';
        a.style.cursor = 'pointer';
        a.addEventListener('click', function () { a.style.display = 'none'; });
        setTimeout(function () {
          a.style.transition = 'opacity .3s';
          a.style.opacity = '0';
          setTimeout(function () { a.style.display = 'none'; }, 320);
        }, 5000);
      })(list[i]);
    }
  });
})();
</script>
