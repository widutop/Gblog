<?php
/* 后台正文预览：仅登录且有编辑权限、CSRF 通过才返回渲染后的 HTML。 */
require_once __DIR__ . '/../inc/auth.php';
gblog_admin_require_cap('post.edit');

header('Content-Type: text/html; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    exit('预览校验失败');
}
$raw = (string)($_POST['content'] ?? '');
echo function_exists('render_content') ? render_content($raw) : nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
