<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 章节阅读页视图：DOM 全部在此模板渲染，控制器只负责取数（gblog_not_found / 评论处理在控制器完成）。
 * 阅读器交互脚本保留在本视图内（仅绑定已存在的 DOM，无取数逻辑）。
 */
$ch        = $ch        ?? [];
$novel     = $novel     ?? [];
$chapters  = $chapters  ?? [];
$prevCh    = $prevCh    ?? null;
$nextCh    = $nextCh    ?? null;
$comments  = $comments  ?? [];
$bookUrl   = $bookUrl   ?? '';
$chapterId = (int)($id ?? ($ch['id'] ?? 0));
?>
<section class="rd-page">

  <div class="rd-tools" aria-label="阅读工具">
    <button type="button" data-rd-open="catalog" title="目录"><?= gblog_icon('book') ?><span>目录</span></button>
    <button type="button" data-rd-open="settings" title="设置"><?= gblog_icon('settings') ?><span>设置</span></button>
    <a href="<?= e($bookUrl) ?>" title="作品介绍页"><?= gblog_icon('file') ?><span>作品</span></a>
    <button type="button" data-rd-open="comments" title="评论"><?= gblog_icon('comment') ?><span>评论（<?= count($comments) ?>）</span></button>
  </div>

  <div class="rd-drawer" id="rd-drawer" hidden>
    <div class="rd-drawer-mask" data-rd-close></div>
    <div class="rd-drawer-panel">
      <div class="rd-drawer-head">
        <b>《<?= e($novel['title'] ?? '') ?>》目录（<?= (int)($chapterTotal ?? count($chapters)) ?> 章）</b>
        <button type="button" data-rd-close aria-label="关闭">✕</button>
      </div>
      <div class="rd-drawer-body">
        <?php render_slot('novel_chapter_list', array_merge(get_defined_vars(), ['chapters' => $chapters, 'currentId' => $chapterId, 'listClass' => 'novel-chapters rd-catalog-list'])); ?>
      </div>
    </div>
  </div>

  <div class="rd-drawer" id="rd-cdrawer" hidden>
    <div class="rd-drawer-mask" data-rd-close></div>
    <div class="rd-drawer-panel">
      <div class="rd-drawer-head">
        <b>《<?= e($novel['title'] ?? '') ?>》评论（<?= count($comments) ?>）</b>
        <button type="button" data-rd-close aria-label="关闭">✕</button>
      </div>
      <div class="rd-drawer-body">
        <?php render_slot('comments', get_defined_vars()); ?>
      </div>
    </div>
  </div>

  <div class="rd-settings" id="rd-settings" hidden>
    <div class="rd-set-row">
      <span>字号</span>
      <button type="button" id="rd-font-minus" class="btn btn-sm">A-</button>
      <b id="rd-font-val">18</b>
      <button type="button" id="rd-font-plus" class="btn btn-sm">A+</button>
    </div>
    <div class="rd-set-row">
      <span>背景</span>
      <button type="button" class="btn btn-sm" data-rd-bg="default">默认</button>
      <button type="button" class="btn btn-sm" data-rd-bg="paper">羊皮纸</button>
      <button type="button" class="btn btn-sm" data-rd-bg="green">护眼绿</button>
      <button type="button" class="btn btn-sm" data-rd-bg="dark">夜间</button>
    </div>
    <div class="rd-set-row">
      <span>字体</span>
      <button type="button" class="btn btn-sm" data-rd-fontface="sans">黑体</button>
      <button type="button" class="btn btn-sm" data-rd-fontface="serif">宋体</button>
    </div>
  </div>

  <div class="reader" id="rd-reader">
    <div class="reader-book-title">
      <a href="<?= e($bookUrl) ?>">《<?= e($novel['title'] ?? '') ?>》</a>
      · <?= e($novel['author'] ?? '佚名') ?>
    </div>
    <h1 class="reader-title"><?= e($ch['title'] ?? '') ?></h1>

    <div class="reader-content" id="reader-content"><?= nl2br(e($ch['content'] ?? '')) ?></div>

    <div class="reader-nav">
      <?php if ($prevCh): ?>
        <a class="rn-link" href="index.php?do=chapter&id=<?= (int)$prevCh['id'] ?>">← 上一章</a>
      <?php else: ?>
        <span class="rn-link rn-disable">← 上一章</span>
      <?php endif; ?>
      <button type="button" class="rn-link rn-catalog" data-rd-open="catalog"><?= gblog_icon('book') ?> 目录</button>
      <?php if ($nextCh): ?>
        <a class="rn-link" href="index.php?do=chapter&id=<?= (int)$nextCh['id'] ?>">下一章 →</a>
      <?php else: ?>
        <span class="rn-link rn-disable">下一章 →</span>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var reader  = document.getElementById('rd-reader');
  var content = document.getElementById('reader-content');
  var drawer  = document.getElementById('rd-drawer');
  var cdrawer = document.getElementById('rd-cdrawer');
  var settings= document.getElementById('rd-settings');
  if (!reader || !content) return;

  function store(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function load(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function mark(attr, val) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-rd-' + attr + ']'), function (b) {
      b.classList.toggle('active', b.getAttribute('data-rd-' + attr) === val);
    });
  }

  function openDrawer() {
    if (drawer) drawer.hidden = false;
    if (cdrawer) cdrawer.hidden = true;
    if (settings) settings.hidden = true;
  }
  function closeDrawers() {
    if (drawer) drawer.hidden = true;
    if (cdrawer) cdrawer.hidden = true;
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-rd-open]'), function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-rd-open');
      if (t === 'catalog') openDrawer();
      else if (t === 'comments') { if (cdrawer) cdrawer.hidden = false; if (drawer) drawer.hidden = true; if (settings) settings.hidden = true; }
      else if (settings) { settings.hidden = !settings.hidden; closeDrawers(); }
    });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-rd-close]'), function (b) {
    b.addEventListener('click', function () { closeDrawers(); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawers(); if (settings) settings.hidden = true; }
  });

  var size = parseInt(load('gblog_reader_size', '18'), 10);
  if (isNaN(size)) size = 18;
  function applySize(n) {
    size = Math.min(26, Math.max(14, n));
    content.style.fontSize = size + 'px';
    var v = document.getElementById('rd-font-val'); if (v) v.textContent = size;
    store('gblog_reader_size', size);
  }
  applySize(size);
  var minus = document.getElementById('rd-font-minus'), plus = document.getElementById('rd-font-plus');
  if (minus) minus.addEventListener('click', function () { applySize(size - 1); });
  if (plus)  plus.addEventListener('click',  function () { applySize(size + 1); });

  function applyBg(v) { reader.setAttribute('data-bg', v); mark('bg', v); store('gblog_reader_bg', v); }
  function applyFace(v) { reader.setAttribute('data-fontface', v); mark('fontface', v); store('gblog_reader_fontface', v); }
  applyBg(load('gblog_reader_bg', 'default'));
  applyFace(load('gblog_reader_fontface', 'sans'));
  Array.prototype.forEach.call(document.querySelectorAll('[data-rd-bg]'), function (b) {
    b.addEventListener('click', function () { applyBg(b.getAttribute('data-rd-bg')); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-rd-fontface]'), function (b) {
    b.addEventListener('click', function () { applyFace(b.getAttribute('data-rd-fontface')); });
  });
});
</script>
