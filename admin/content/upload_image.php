<?php
/* 后台正文图片上传：wangEditor 上传图片按钮与「插入图片」控件共用。
 * 仅登录且具备 post.edit 能力、CSRF 通过才受理；返回 wangEditor 5 的 {errno,data:{url}} 结构。 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload_helper.php';
gblog_admin_require_cap('post.edit');

header('Content-Type: application/json; charset=utf-8');
function gb_upload_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// wangEditor 的 extraData 在部分配置下不会随 multipart 请求提交，
// 因此同时接受 URL query 里的 token；两者都从当前会话校验，安全性不变。
if (empty($_POST['csrf_token']) && !empty($_GET['csrf_token'])) {
    $_POST['csrf_token'] = (string)$_GET['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    gb_upload_json(['errno' => 1, 'message' => '上传校验失败，请刷新页面后重试'], 403);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    gb_upload_json(['errno' => 1, 'message' => '未接收到图片']);
}

$r = handle_upload($file, 'image');
if (!$r['ok']) {
    gb_upload_json(['errno' => 1, 'message' => $r['error']]);
}

gb_upload_json(['errno' => 0, 'data' => ['url' => $r['url'], 'alt' => '', 'href' => '']]);
