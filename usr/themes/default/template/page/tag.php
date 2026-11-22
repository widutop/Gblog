<?php
/**
 * Template Name: 标签文章列表
 * View: tag
 * Context: $tag（null 表示标签不存在）, $posts, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$tag        = $tag ?? null;
$posts      = $posts ?? [];
$page       = max(1, (int)($page ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
?>
<section class="content list-posts">
  <?php if (!$tag): ?>
    <p class="empty">该标签不存在。</p>
  <?php elseif (!$posts): ?>
    <p class="empty">标签「<?= e($tag['name']) ?>」下还没有文章。</p>
  <?php else: ?>
    <h2 class="list-title">标签：#<?= e($tag['name']) ?></h2>
    <?php foreach ($posts as $post): ?>
      <article class="post-card">
        <div class="post-card-body">
          <h2 class="post-title"><a href="<?= e(gblog_post_url($post)) ?>"><?= e($post['title']) ?></a></h2>
          <div class="post-meta"><span class="date"><?= e(fmt_date($post['created_at'])) ?></span></div>
          <p class="post-excerpt"><?= e($post['excerpt'] ?: excerpt($post['title'] . '…', 100)) ?></p>
        </div>
      </article>
    <?php endforeach; ?>

    <?php render_slot('pagination', [
        'pgPage'  => $page,
        'pgPages' => $totalPages,
        'pgBase'  => gblog_pretty_base('index.php?do=tag&id=' . (int)$tag['id']),
        'pgLabel' => '标签文章分页',
    ]); ?>
  <?php endif; ?>
</section>
