<?php
/**
 * Template Name: 通用单页（关于等富文本页）
 * View: page
 * Context: $page_title, $page_heading, $page_html, $page_empty
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$page_heading = $page_heading ?? ($page_title ?? '');
$page_html    = (string)($page_html ?? '');
$page_empty   = (string)($page_empty ?? '');
?>
<section class="content">
  <article class="post-full">
    <h1 class="post-full-title"><?= e($page_heading) ?></h1>
    <div class="post-content">
      <?php if (trim($page_html) !== ''): ?>
        <?= $page_html ?>
      <?php elseif ($page_empty !== ''): ?>
        <p><?= e($page_empty) ?></p>
      <?php else: ?>
        <p>暂无内容。</p>
      <?php endif; ?>
    </div>
  </article>
</section>
