<?php
/**
 * Template Name: 文章归档
 * View: archive
 * Context: $archives（年月汇总）, $posts（某月文章，无则 null）, $year, $month
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$archives = $archives ?? [];
$posts    = $posts ?? null;
$year     = (int)($year ?? 0);
$month    = (int)($month ?? 0);
?>
<section class="content">
  <div class="post-full">
    <h1 class="post-full-title">文章归档</h1>

    <?php if ($posts !== null): ?>
      <h2 style="font-size:1.1em;margin:18px 0 12px;"><?= sprintf('%04d年%02d月', $year, $month) ?>（<?= count($posts) ?> 篇）</h2>
      <?php if (!$posts): ?>
        <p class="empty">该月没有文章。</p>
      <?php else: ?>
        <ul class="archive-list">
          <?php foreach ($posts as $p): ?>
            <li class="archive-item">
              <span class="archive-date"><?= e(date('Y-m-d', gblog_parse_time($p['created_at']))) ?></span>
              <a href="<?= e(gblog_post_url($p)) ?>"><?= e($p['title']) ?></a>
              <?php if (!empty($p['category_name'])): ?>
                <span class="archive-cat">[<?= e($p['category_name']) ?>]</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p style="margin-top:16px;"><a href="<?= e(gblog_url('archive')) ?>">← 返回归档总览</a></p>
      <?php endif; ?>
    <?php else: ?>
      <?php if (!$archives): ?>
        <p class="empty">还没有文章。</p>
      <?php else: ?>
        <ul class="archive-list">
          <?php foreach ($archives as $a): ?>
            <li class="archive-item">
              <a href="index.php?do=archive&y=<?= (int)$a['y'] ?>&m=<?= (int)$a['m'] ?>"><?= (int)$a['y'] ?> 年 <?= (int)$a['m'] ?> 月</a>
              <span class="archive-cat">（<?= (int)$a['cnt'] ?> 篇）</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
