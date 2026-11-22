<?php
/**
 * Template Name: 404 页面不存在
 * View: 404
 * Context: $page_title, $message
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$page_title = $page_title ?? '页面不存在';
$message    = $message    ?? '抱歉，你访问的内容不存在或已被删除。';
?>
<section class="content">
  <div class="card">
    <h1 class="post-full-title"><?= e($page_title) ?></h1>
    <p class="empty"><?= e($message) ?></p>
    <p style="margin-top:16px;"><a href="index.php">← 返回首页</a></p>
  </div>
</section>
