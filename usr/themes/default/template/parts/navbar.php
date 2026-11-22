<?php if (!defined('GBLOG_CORE')) { http_response_code(403); exit; } ?>
<header class="site-header">
  <div class="container header-inner">
    <a class="site-logo" href="index.php">
      <?php $logoUrl = get_setting('logo_url', ''); ?>
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($logo_site_name) ?>" class="logo-img">
      <?php else: ?>
        <span class="logo-text"><?= e($logo_site_name) ?></span>
      <?php endif; ?>
    </a>
    <nav class="site-nav">
      <a href="index.php"<?= $current_uri === 'home' ? ' class="active"' : '' ?>><?= gblog_icon('home') ?> 首页</a>
      <?php foreach (get_all_sites() as $navSite): ?>
        <?php if ((int)$navSite['enabled'] !== 1) continue; ?>
        <?php if ((int)($navSite['show_in_menu'] ?? 1) !== 1) continue; ?>
        <a href="<?= e(gblog_url('site', ['slug' => $navSite['slug']])) ?>"<?= $current_uri === 'site' && ($_GET['slug'] ?? '') === $navSite['slug'] ? ' class="active"' : '' ?>><?= e($navSite['menu_name'] ?: $navSite['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="header-right">
      <div class="gb-search-wrap" id="gb-search-wrap">
        <button type="button" class="icon-btn" id="gb-search-toggle" aria-label="搜索" aria-expanded="false" aria-controls="gb-search-pop"><?= gblog_icon('search') ?></button>
        <?php $__searchQueryStyle = !gblog_permalink_enabled(); ?>
        <form class="gb-search-pop" id="gb-search-pop" action="<?= e($__searchQueryStyle ? 'index.php' : gblog_url('search')) ?>" method="get" role="search">
          <?php if ($__searchQueryStyle): ?><input type="hidden" name="do" value="search"><?php endif; ?>
          <input type="text" name="q" placeholder="搜索文章标题 / 正文…" autocomplete="off" aria-label="搜索关键词">
          <button type="submit">搜索</button>
        </form>
      </div>
      <?php $__cu = current_user(); ?>
      <?php if ($__cu): ?>
        <a href="index.php?do=profile" class="login-link"><?= gblog_icon('user') ?> <?= e($__cu['nickname'] ?: $__cu['username']) ?></a>
      <?php else: ?>
        <a href="javascript:;" onclick="document.getElementById('login-modal').style.display='flex'" class="login-link">登录</a>
        <a href="javascript:;" onclick="document.getElementById('register-modal').style.display='flex'" class="login-link">注册</a>
      <?php endif; ?>
      <div style="display:flex;gap:6px;align-items:center;margin-left:12px;padding-left:12px;border-left:1px solid var(--border);">
        <button type="button" id="mode-toggle" class="mode-btn" onclick="toggleMode()" title="切换夜间模式" aria-label="切换夜间模式"><?= gblog_icon('sun') ?></button>
      </div>
    </div>
  </div>
</header>

<script>
(function () {
  var wrap = document.getElementById('gb-search-wrap');
  var btn  = document.getElementById('gb-search-toggle');
  var pop  = document.getElementById('gb-search-pop');
  if (!wrap || !btn || !pop) return;
  function setOpen(on) {
    wrap.classList.toggle('open', on);
    btn.setAttribute('aria-expanded', String(on));
    if (on) { var input = pop.querySelector('input[name="q"]'); if (input) input.focus(); }
  }
  btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(!wrap.classList.contains('open')); });
  document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>
