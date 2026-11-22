<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$post          = $post          ?? [];
$novelChapters = $novelChapters ?? [];
$novelBook     = $novelBook     ?? [];
$nbComments    = $comments      ?? [];

$nbStats   = (array)($novelBook['stats']   ?? []);
$nbLatest  = (array)($novelBook['latest']  ?? []);
$nbOther   = (array)($novelBook['other']   ?? []);
$nbSimilar = array_slice((array)($novelBook['similar'] ?? []), 0, 5);
$nbAuthor  = (string)($post['author'] ?? '') !== '' ? (string)$post['author'] : '佚名';
$nbWords   = (int)($nbStats['words'] ?? 0);
$nbWordTxt = $nbWords >= 10000 ? number_format($nbWords / 10000, 1) . ' 万字' : $nbWords . ' 字';
$nbWorks   = (int)($nbStats['works'] ?? 0);
$nbDays    = (int)($nbStats['days'] ?? 0);
$nbFirst   = $nbFirst ?? ($novelChapters[0] ?? null);
$nbIntro   = trim((string)($post['excerpt'] ?? ''));
$nbCoverSlug = 'novel';
?>
<section class="nb-book">

  <div class="nb-hero card">
    <div class="nb-hero-cover">
      <?php if (!empty($post['cover'])): ?>
        <img class="novel-cover-lg" src="<?= e($post['cover']) ?>" alt="<?= e($post['title']) ?>">
      <?php else: ?>
        <div class="novel-cover-lg placeholder" data-cover="<?= e($nbCoverSlug) ?>"><?= gblog_icon('book') ?></div>
      <?php endif; ?>
    </div>
    <div class="nb-hero-main">
      <h1 class="nb-hero-title"><?= e($post['title']) ?></h1>
      <div class="nb-hero-byline">作者：<?= e($nbAuthor) ?></div>
      <div class="nb-hero-meta">
        <?php if (!empty($post['category_name'])): ?><span class="novel-cat"><?= e($post['category_name']) ?></span><?php endif; ?>
        <span class="novel-status"><?= (int)($novelChapterTotal ?? count($novelChapters)) ?> 章</span>
        <?php if (!empty($post['updated_at'])): ?><span>更新于 <?= e(fmt_date($post['updated_at'])) ?></span><?php endif; ?>
        <span><?= (int)($post['view_count'] ?? 0) ?> 次阅读</span>
        <?php if ($nbWorks > 0): ?><span><?= $nbWorks ?> 部作品</span><?php endif; ?>
        <?php if ($nbWords > 0): ?><span><?= e($nbWordTxt) ?></span><?php endif; ?>
        <?php if ($nbDays > 0): ?><span>创作 <?= $nbDays ?> 天</span><?php endif; ?>
      </div>

      <div class="nb-hero-intro">
        <div class="nb-hero-intro-label">作品简介</div>
        <div class="nb-hero-intro-text"><?= $nbIntro !== '' ? nl2br(e($nbIntro)) : '暂无简介。' ?></div>
      </div>

      <div class="nb-hero-actions">
        <?php if ($nbFirst): ?>
          <a class="btn btn-primary" href="index.php?do=chapter&id=<?= (int)$nbFirst['id'] ?>"><?= gblog_icon('play') ?> 开始阅读</a>
        <?php else: ?>
          <span class="btn" style="opacity:.6;cursor:default;">暂无章节</span>
        <?php endif; ?>
        <a class="btn" href="#nb-catalog"><?= gblog_icon('book') ?> 章节目录</a>
        <a class="btn" href="#comments"><?= gblog_icon('comment') ?> 评论（<?= count($nbComments) ?>）</a>
      </div>
    </div>
  </div>

  <div class="nb-grid">
    <div class="nb-col-main">

      <div class="card nb-tabs-card" id="nb-catalog">
        <div class="nb-tabs">
          <span class="gb-tab-group">
          <button type="button" class="active" data-tab="latest">最新章节</button>
          <button type="button" data-tab="catalog">目录（<?= (int)($novelChapterTotal ?? count($novelChapters)) ?> 章）</button>
          </span>
        </div>
        <div class="nb-tab-panel" data-panel="latest">
          <?php if (!$nbLatest): ?>
            <p class="empty">暂无章节。</p>
          <?php else: ?>
            <ul class="nb-latest-list">
              <?php foreach ($nbLatest as $lc): ?>
                <li>
                  <a href="index.php?do=chapter&id=<?= (int)$lc['id'] ?>"><?= e($lc['title']) ?></a>
                  <?php if (!empty($lc['created_at'])): ?><span><?= e(fmt_date($lc['created_at'])) ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <div class="nb-tab-panel" data-panel="catalog" hidden>
          <?php render_slot('novel_chapter_list', array_merge(get_defined_vars(), ['chapters' => $novelChapters, 'currentId' => 0, 'listClass' => 'novel-chapters nb-catalog-list'])); ?>
        </div>
      </div>

      <?php render_slot('related', get_defined_vars()); ?>

      <?php
      $GLOBALS['gblog_comments_taken'] = true;
      render_slot('comments', get_defined_vars());
      ?>

    </div>

    <aside class="nb-col-side">

      <div class="card nb-side-card nb-other">
        <h3 class="nb-h3">其他作品<span class="nb-carousel-count" data-nb-count>1 / <?= count($nbOther) ?></span></h3>
        <?php if ($nbOther): ?>
          <div class="nb-carousel" data-nb-carousel>
            <div class="nb-carousel-track">
              <?php foreach ($nbOther as $wp) { $p = $wp; $coverSlug = $nbCoverSlug; include __DIR__ . '/work_card.php'; } ?>
            </div>
          </div>
          <div class="nb-carousel-ctrl">
            <button type="button" data-nb-prev aria-label="上一部作品">‹</button>
            <div class="nb-carousel-dots" data-nb-dots></div>
            <button type="button" data-nb-next aria-label="下一部作品">›</button>
          </div>
        <?php else: ?>
          <p class="nb-side-empty">暂无其他作品。</p>
        <?php endif; ?>
      </div>

      <div class="card nb-side-card nb-fans">
        <h3 class="nb-h3">书迷排行</h3>
        <?php render_slot('novel_book_extras', get_defined_vars()); ?>
      </div>

      <div class="card nb-side-card nb-similar">
        <h3 class="nb-h3">同类作品</h3>
        <?php if ($nbSimilar): ?>
          <ol class="nb-similar-list">
            <?php foreach ($nbSimilar as $i => $wp): ?>
              <li>
                <span class="nb-similar-rank"><?= $i + 1 ?></span>
                <a href="index.php?do=post&id=<?= (int)$wp['id'] ?>"><?= e($wp['title']) ?></a>
                <span class="nb-similar-meta"><?= (int)($wp['chapter_count'] ?? 0) ?> 章</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="nb-side-empty">暂无同类作品。</p>
        <?php endif; ?>
      </div>

    </aside>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var root = document.querySelector('.nb-tabs-card');
  if (root) {
    var tabs = root.querySelectorAll('.nb-tabs [data-tab]');
    Array.prototype.forEach.call(tabs, function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-tab');
        Array.prototype.forEach.call(tabs, function (b) { b.classList.toggle('active', b === btn); });
        Array.prototype.forEach.call(root.querySelectorAll('.nb-tab-panel'), function (p) {
          p.hidden = p.getAttribute('data-panel') !== key;
        });
      });
    });
  }

  var car = document.querySelector('[data-nb-carousel]');
  if (!car) return;
  var cards  = car.querySelectorAll('.nb-work-card');
  var total  = cards.length;
  var count  = document.querySelector('[data-nb-count]');
  var dots   = document.querySelector('[data-nb-dots]');
  var prev   = document.querySelector('[data-nb-prev]');
  var next   = document.querySelector('[data-nb-next]');
  if (!total) return;

  function index() {
    var w = car.clientWidth || 1;
    return Math.min(total - 1, Math.max(0, Math.round(car.scrollLeft / w)));
  }
  function sync() {
    var cur = index();
    if (count) count.textContent = (cur + 1) + ' / ' + total;
    if (prev) prev.disabled = cur === 0;
    if (next) next.disabled = cur === total - 1;
    Array.prototype.forEach.call(dots.children, function (d, i) { d.classList.toggle('active', i === cur); });
  }
  function go(i) {
    i = Math.min(total - 1, Math.max(0, i));
    car.scrollTo({ left: i * car.clientWidth, behavior: 'smooth' });
  }
  for (var i = 0; i < total; i++) {
    var d = document.createElement('button');
    d.type = 'button';
    d.setAttribute('aria-label', '第 ' + (i + 1) + ' 部');
    d.dataset.nbDot = String(i);
    d.addEventListener('click', function () { go(parseInt(this.dataset.nbDot, 10)); });
    dots.appendChild(d);
  }
  if (prev) prev.addEventListener('click', function () { go(index() - 1); });
  if (next) next.addEventListener('click', function () { go(index() + 1); });
  car.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync);
  sync();
});
</script>
