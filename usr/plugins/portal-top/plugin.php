<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!function_exists('add_action')) return;

require_once GBLOG_ROOT . '/core/ext/agg_modules.php';

$__ptIcons = [
    'article' => 'file', 'software' => 'package', 'image' => 'image',
    'song'    => 'music', 'video'    => 'video', 'novel' => 'book', 'default' => 'file',
];
$GLOBALS['gblog_pt_icons'] = $__ptIcons;

function gblog_agg_module_html(array $m, array $rows, array $icons, bool $bare = false): string
{
    if (!$rows) return '';
    $key = (string)$m['key'];

    if ($key === 'carousel') {
        $seconds = min(10, max(2, (int)($m['seconds'] ?? 4)));
        ob_start(); ?>
<?php if ($bare): ?><div class="agg-carousel" data-agg-carousel data-seconds="<?= $seconds ?>"><?php else: ?><section class="agg-section agg-carousel" data-agg-carousel data-seconds="<?= $seconds ?>"><?php endif; ?>
  <div class="agg-carousel-track">
    <?php foreach ($rows as $i => $r): ?>
      <?php $slideHref = (string)($r['url'] ?? '') ?: ('index.php?do=post&id=' . (int)($r['id'] ?? 0)); ?>
      <a class="agg-slide<?= $i === 0 ? ' on' : '' ?>" href="<?= e($slideHref) ?>">
        <span class="agg-slide-cover<?= empty($r['cover']) ? ' gb-cover-agg' : '' ?>"<?= empty($r['cover']) ? ' data-cover="' . e($r['site_slug'] ?? '') . '"' : '' ?>><?php if (!empty($r['cover'])): ?><img src="<?= e($r['cover']) ?>" alt="<?= e($r['title']) ?>"><?php else: ?><?= gblog_icon($icons[$r['site_type']] ?? ($icons['default'] ?? 'file')) ?><?php endif; ?></span>
        <span class="agg-slide-body">
          <span class="agg-slide-title"><?= e($r['title']) ?></span>
          <span class="agg-slide-excerpt"><?= e(excerpt($r['excerpt'] ?: ($r['content'] ?? ''), 90)) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($rows) > 1): ?>
    <button type="button" class="agg-carousel-prev" aria-label="上一张">&#8249;</button>
    <button type="button" class="agg-carousel-next" aria-label="下一张">&#8250;</button>
    <div class="agg-carousel-dots">
      <?php foreach ($rows as $i => $r): ?><button type="button" class="agg-carousel-dot<?= $i === 0 ? ' on' : '' ?>" aria-label="第 <?= $i + 1 ?> 张"></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($bare): ?></div><?php else: ?></section><?php endif; ?>
<script>
(function () {
  document.querySelectorAll('[data-agg-carousel]').forEach(function (box) {
    var slides = box.querySelectorAll('.agg-slide');
    var dots = box.querySelectorAll('.agg-carousel-dot');
    if (slides.length < 2) return;
    var idx = 0;
    var secs = Math.max(2, parseInt(box.getAttribute('data-seconds') || '4', 10));
    function go(n) {
      idx = (n + slides.length) % slides.length;
      for (var i = 0; i < slides.length; i++) {
        slides[i].classList.toggle('on', i === idx);
        if (dots[i]) dots[i].classList.toggle('on', i === idx);
      }
    }
    var prev = box.querySelector('.agg-carousel-prev');
    var next = box.querySelector('.agg-carousel-next');
    if (prev) prev.addEventListener('click', function () { go(idx - 1); });
    if (next) next.addEventListener('click', function () { go(idx + 1); });
    for (var j = 0; j < dots.length; j++) {
      (function (k) { dots[k].addEventListener('click', function () { go(k); }); })(j);
    }
    var timer = setInterval(function () { go(idx + 1); }, secs * 1000);
    box.addEventListener('mouseenter', function () { clearInterval(timer); });
    box.addEventListener('mouseleave', function () { timer = setInterval(function () { go(idx + 1); }, secs * 1000); });
  });
})();
</script>
<?php return (string)ob_get_clean();
    }

    if ($key === 'cards') {
        ob_start(); ?>
<?php if ($bare): ?><div class="agg-colcards"><?php else: ?><section class="agg-section agg-colcards"><?php endif; ?>
  <div class="agg-section-body">
    <div class="agg-colcards-grid">
      <?php foreach ($rows as $r): ?>
        <a class="agg-col-card" href="<?= e($r['url']) ?>" title="<?= e($r['title']) ?>">
          <span class="agg-col-card-cover"><?php if (!empty($r['cover'])): ?><img src="<?= e($r['cover']) ?>" alt="<?= e($r['title']) ?>"><?php else: ?><?= gblog_icon('link') ?><?php endif; ?></span>
          <span class="agg-col-card-name">
            <span class="agg-col-card-title"><?= e($r['title']) ?></span>
            <?php if (!empty($r['subtitle'])): ?><span class="agg-col-card-sub"><?= e($r['subtitle']) ?></span><?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php if ($bare): ?></div><?php else: ?></section><?php endif; ?>
<?php return (string)ob_get_clean();
    }

    return '';
}

if (!function_exists('gblog_pt_prepare')) {
    function gblog_pt_prepare(): void
    {
        $mods  = gblog_home_modules();
        $icons = $GLOBALS['gblog_pt_icons'] ?? [];
        $hero  = '';
        foreach ($mods as $m) {
            if (empty($m['enabled'])) continue;
            if ($m['key'] === 'carousel' || $m['key'] === 'cards') {
                $rows  = gblog_agg_module_rows($m);
                $hero .= gblog_agg_module_html($m, $rows, $icons, true);
            }
        }
        $GLOBALS['agg_hero_html']     = $hero;
        $GLOBALS['agg_hero_position'] = (string)get_setting('home_hero_position', 'list');
    }
}

if (!function_exists('gblog_pt_render')) {
    function gblog_pt_render(): void
    {
        $heroHtml = (string)($GLOBALS['agg_hero_html'] ?? '');
        $heroPos  = (string)($GLOBALS['agg_hero_position'] ?? 'list');

        if ($heroPos !== 'page' && trim($heroHtml) !== '') {
            echo '<div class="agg-hero">' . $heroHtml . '</div>';
        }
    }
}

add_action('portal_top_prepare', 'gblog_pt_prepare', 10);
add_action('portal_top', 'gblog_pt_render', 10);
