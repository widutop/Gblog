<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!empty($GLOBALS['gblog_detail_title_taken'])) return;

$post = $post ?? [];
?>
<h1 class="post-full-title"><?= e($post['title'] ?? '') ?></h1>
