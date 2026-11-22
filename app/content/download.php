<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (!gblog_rate_allow('download_ip', gblog_client_ip(), 60, 60)) {
    http_response_code(429);
    exit('下载过于频繁，请稍后再试。');
}

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM posts WHERE id = ? AND status = 'published' LIMIT 1");
$st->execute([$id]);
$post = $st->fetch();
$url = $post ? trim((string)($post['download_url'] ?? '')) : '';

if (!$post || $url === '' || !gblog_site_is_active((int)$post['site_id'])) {
    gblog_not_found('资源不存在', '资源不存在或已下架。');
}

$dl = download_state($post, current_user());
if (!$dl['allowed']) {
    $page_title = '无法下载';
    get_header();
    echo '<section class="content"><div class="card"><p>' . e($dl['reason']) . '</p>'
       . '<a class="btn btn-primary" href="index.php?do=post&id=' . (int)$post['id'] . '">返回资源页</a></div></section>';
    get_footer();
    exit;
}

db()->prepare('UPDATE posts SET download_count = download_count + 1 WHERE id = ?')->execute([$id]);
// 只接受 http(s) 与站内相对地址：伪协议 / 协议相对 / 头注入由 gblog_safe_redirect 拦截
$target = (string)apply_filters('download_link_target', $url, $post);

$target = gblog_safe_redirect($target, 'index.php?do=post&id=' . (int)$post['id']);
header('Location: ' . $target, true, 302);
exit;
