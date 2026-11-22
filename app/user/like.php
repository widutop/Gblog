<?php
// 点赞 / 收藏 端点（必须登录后才能操作） 前端由主题 script/react.js 调用，返回 JSON
require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/auth/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => '请求方法不允许']);
    exit;
}
if (!csrf_check()) {
    echo json_encode(['ok' => false, 'msg' => '请求已失效，请刷新页面后重试']);
    exit;
}

$me = current_user();
if (!$me) {
    echo json_encode(['ok' => false, 'need_login' => true, 'msg' => '请先登录后再操作']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$type   = ($_POST['type'] ?? 'like') === 'favorite' ? 'favorite' : 'like';
$post   = get_post_by_id($postId);
if (!$post) {
    echo json_encode(['ok' => false, 'msg' => '内容不存在或已停用']);
    exit;
}

if (!gblog_rate_allow('react_' . $type, (int)$me['id'], 30, 60)) {
    echo json_encode(['ok' => false, 'msg' => '操作过于频繁，请稍后再试']);
    exit;
}

$res = toggle_post_reaction((int)$me['id'], $postId, $type);
echo json_encode([
    'ok'    => true,
    'type'  => $type,
    'state' => $res['state'],
    'count' => $res['count'],
]);
