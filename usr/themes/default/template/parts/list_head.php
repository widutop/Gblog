<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$softHead = $softHead ?? [];
// 发布时间倒序前 11 条：0-5 轮播、6-7 右侧两卡、8-10 下方三卡；数据不足时逐级降级
$shCarousel = array_slice($softHead, 0, 6);
$shSide     = array_slice($softHead, 6, 2);
$shTopics   = array_slice($softHead, 8, 3);
$shPal = [
    ['#1b3566', '#2f6fed'],
    ['#7a4a22', '#c8964a'],
    ['#125c58', '#37b3a4'],
    ['#d94f7d', '#f58fab'],
    ['#3549a8', '#7f77dd'],
    ['#c06a1c', '#93b83c'],
];
$shBg = static function (int $i) use ($shPal): string {
    [$a, $b] = $shPal[$i % count($shPal)];
    return 'background:linear-gradient(135deg,' . $a . ',' . $b . ');';
};
$shFootOf = static function (array $r): string {
    $foot = [$r['author'] ?: '匿名'];
    foreach (['software_type', 'software_platform', 'file_size'] as $k) {
        if (!empty($r[$k])) $foot[] = (string)$r[$k];
    }
    if (!empty($r['download_count'])) $foot[] = '下载 ' . (int)$r['download_count'] . ' 次';
    return implode(' · ', $foot);
};
?>
<?php if ($shCarousel): ?>
<section class="soft-head">
  <div class="soft-head-hero">
    <div class="soft-hero-carousel" data-soft-carousel>
      <div class="soft-carousel-track">
        <?php foreach ($shCarousel as $i => $shMain): ?>
        <a class="soft-hero-main<?= $i === 0 ? ' on' : '' ?>" style="<?= $shBg($i) ?>" href="index.php?do=post&id=<?= (int)$shMain['id'] ?>">
          <span class="sh-brand"><?= e($shMain['category_name'] ?: 'Gblog 软件') ?></span>
          <span class="sh-title"><?= e($shMain['title']) ?></span>
          <span class="sh-sub"><?= e(excerpt($shMain['excerpt'] ?: '', 70)) ?></span>
          <span class="sh-foot"><?= e($shFootOf($shMain)) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if (count($shCarousel) > 1): ?>
      <button type="button" class="soft-carousel-prev" aria-label="上一张">&#8249;</button>
      <button type="button" class="soft-carousel-next" aria-label="下一张">&#8250;</button>
      <div class="soft-carousel-dots">
        <?php foreach ($shCarousel as $i => $sc): ?><button type="button" class="soft-carousel-dot<?= $i === 0 ? ' on' : '' ?>" aria-label="第 <?= $i + 1 ?> 张"></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($shSide): ?>
    <div class="soft-hero-side">
      <?php foreach ($shSide as $shI => $shMini): ?>
        <a class="soft-hero-mini" style="<?= $shBg($shI + 6) ?>" href="index.php?do=post&id=<?= (int)$shMini['id'] ?>">
          <span class="sh-title"><?= e($shMini['title']) ?></span>
          <span class="sh-sub"><?= e(excerpt($shMini['excerpt'] ?: '', 40)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($shTopics): ?>
  <div class="soft-topics">
    <h2 class="soft-topics-title">专题介绍</h2>
    <div class="soft-topics-grid">
      <?php foreach ($shTopics as $shI => $shTopic): ?>
        <a class="soft-topic" href="index.php?do=post&id=<?= (int)$shTopic['id'] ?>">
          <span class="soft-topic-card" style="<?= $shBg($shI + 8) ?>">
            <span class="stc-title"><?= e($shTopic['title']) ?></span>
            <span class="stc-sub"><?= e(excerpt($shTopic['excerpt'] ?: '', 40)) ?></span>
            <span class="stc-logo">
              <?php if (!empty($shTopic['cover'])): ?><img src="<?= e($shTopic['cover']) ?>" alt=""><?php else: ?><?= gblog_icon('package') ?><?php endif; ?>
            </span>
          </span>
          <span class="soft-topic-name"><?= e($shTopic['title']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
<script>
(function () {
  document.querySelectorAll('[data-soft-carousel]').forEach(function (box) {
    var slides = box.querySelectorAll('.soft-hero-main');
    var dots = box.querySelectorAll('.soft-carousel-dot');
    if (slides.length < 2) return;
    var idx = 0;
    var timer = null;
    function go(n) {
      idx = (n + slides.length) % slides.length;
      for (var i = 0; i < slides.length; i++) {
        slides[i].classList.toggle('on', i === idx);
        if (dots[i]) dots[i].classList.toggle('on', i === idx);
      }
    }
    function play() { timer = setInterval(function () { go(idx + 1); }, 4000); }
    function stop() { clearInterval(timer); }
    var prev = box.querySelector('.soft-carousel-prev');
    var next = box.querySelector('.soft-carousel-next');
    if (prev) prev.addEventListener('click', function () { go(idx - 1); });
    if (next) next.addEventListener('click', function () { go(idx + 1); });
    for (var j = 0; j < dots.length; j++) {
      (function (k) { dots[k].addEventListener('click', function () { go(k); }); })(j);
    }
    box.addEventListener('mouseenter', stop);
    box.addEventListener('mouseleave', play);
    play();
  });
})();
</script>
<?php endif; ?>
