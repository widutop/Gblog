<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$me = current_user();
if (!$me) {
    header('Location: index.php?do=login');
    exit;
}

$tab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'content', 'wallet', 'security'], true)) $tab = 'overview';
$uid = (int)$me['id'];

$msg = '';
$msgType = 'ok';
if (!empty($_SESSION['uc_flash']) && is_array($_SESSION['uc_flash'])) {
    $msg = (string)($_SESSION['uc_flash'][0] ?? '');
    $msgType = (string)($_SESSION['uc_flash'][1] ?? 'ok');
    unset($_SESSION['uc_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    if (!csrf_check()) {
        $msg = '表单校验失败，请重试。';
        $msgType = 'err';
    } elseif ($act === 'profile') {
        $sets = [];
        $vals = [];
        foreach (['nickname', 'email', 'phone', 'qq', 'wechat'] as $f) {
            if (!array_key_exists($f, $_POST)) continue;
            $v = trim((string)$_POST[$f]);
            $sets[] = $f . '=?';
            $vals[] = $v !== '' ? $v : null;
            $me[$f] = $v;
        }
        if ($sets) {
            $vals[] = $uid;
            db()->prepare('UPDATE users SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
        }
        $msg = '资料已保存。';
    } elseif ($act === 'password') {
        $oldpass = (string)($_POST['oldpassword'] ?? '');
        $newpass = (string)($_POST['newpassword'] ?? '');
        if ($newpass === '') {
            $msg = '请填写新密码。';
            $msgType = 'err';
        } elseif ($oldpass === '') {
            $msg = '修改密码需要填写旧密码。';
            $msgType = 'err';
        } elseif (!password_verify($oldpass, (string)($me['password_hash'] ?? ''))) {
            $msg = '旧密码不正确，密码未修改。';
            $msgType = 'err';
        } elseif (!gblog_password_strong($newpass)) {
            $msg = '新密码至少 8 位，且必须同时包含大写字母和小写字母。';
            $msgType = 'err';
        } else {
            db()->prepare('UPDATE users SET password_hash=?, password_version=password_version+1, must_change_password=0 WHERE id=?')
                ->execute([password_hash($newpass, PASSWORD_DEFAULT), $uid]);
            gblog_reset_token_invalidate_user($uid);
            $_SESSION['user_pv'] = (int)($me['password_version'] ?? 0) + 1;
            unset($_SESSION['force_pw_change']);
            gblog_audit('password.changed', $uid, 'profile');
            $msg = '密码已修改，下次登录请使用新密码。';
        }
    } elseif ($act === 'recovery') {
        $msg = '恢复码只能由管理员在后台生成，请联系站务。';
        $msgType = 'err';
    }
    if ($msgType === 'ok' && $msg !== '') {
        $_SESSION['uc_flash'] = [$msg, 'ok'];
        $backSub = (string)($_GET['sub'] ?? '');
        if (!in_array($backSub, ['profile', 'password'], true)) $backSub = 'profile';
        header('Location: index.php?do=profile&tab=security&sub=' . $backSub);
        exit;
    }
}

if (($_GET['act'] ?? '') === 'sign') {
    // 签到加金币属写操作，必须 POST + CSRF（GET 会被预取/外链误触发刷金币）
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
        http_response_code(403);
        exit('非法请求');
    }
    $signGold = (int)get_setting('sign_gold', 0);
    if (get_setting('gold_enabled', '1') !== '1') {
        $err = '金币功能已关闭。';
    } elseif ($signGold <= 0) {
        $err = '站点暂未开启签到奖励。';
    } else {
        $err = gblog_sign_daily($uid, $signGold);
    }
    $_SESSION['uc_flash'] = [$err ?: "签到成功，获得 {$signGold} 金币。", $err ? 'err' : 'ok'];
    header('Location: index.php?do=profile&tab=wallet');
    exit;
}

$role = (string)($me['role'] ?? 'member');
$roleLabels = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'contributor' => '协作者', 'member' => '评论者'];
$roleLabel = $roleLabels[$role] ?? $role;
$canPost = in_array($role, ['admin', 'editor', 'author', 'contributor'], true);
$signed = signed_today($uid);

$ucSubMap = [
    'overview' => ['history', 'gold', 'comments'],
    'content'  => $canPost
        ? ['posts', 'history', 'comments', 'downloads', 'favorites', 'likes']
        : ['history', 'comments', 'downloads', 'favorites', 'likes'],
    'wallet'   => ['logs', 'purchases'],
    'security' => ['profile', 'password'],
];
$ucSubKeys = $ucSubMap[$tab];
$ucSub = (string)($_GET['sub'] ?? '');
if (!in_array($ucSub, $ucSubKeys, true)) $ucSub = $ucSubKeys[0];

$ucStatusPost = static function ($s): string {
    return ['published' => '已发布', 'draft' => '草稿'][(string)$s] ?? (string)$s;
};
$ucStatusComment = static function ($s): string {
    return ['approved' => '已通过', 'pending' => '待审核', 'hidden' => '已隐藏', 'spam' => '垃圾评论'][(string)$s] ?? (string)$s;
};
$ucEmpty = static function (string $text, string $href = '', string $label = ''): string {
    $h = '<div class="uc-empty"><span class="uc-empty-icon">' . gblog_icon('inbox', 22) . '</span><p>' . e($text) . '</p>';
    if ($href !== '') $h .= '<a class="uc-link" href="' . e($href) . '">' . e($label !== '' ? $label : '去看看') . ' →</a>';
    return $h . '</div>';
};
$ucCount = static function (string $sql) use ($uid): int {
    $st = db()->prepare($sql);
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
};

$stats = [];
$recentHistory = [];
$recentGold = [];
$recentComments = [];
$myPosts = [];
$myPostsTotal = 0;
$myPostsPages = 1;
$myPostsPage = 1;
$myComments = [];
$favorites = [];
$likes = [];
$viewHistory = [];
$downloads = [];
$goldLogs = [];
$purchases = [];

if ($tab === 'overview') {
    $stats = [
        'posts' => $canPost ? $ucCount('SELECT COUNT(*) FROM posts WHERE user_id=? AND ' . gblog_active_site_clause('site_id')) : 0,
        'comments' => $ucCount("SELECT COUNT(*) FROM comments c LEFT JOIN posts p ON p.id=c.target_id AND c.target_type='post' LEFT JOIN songs sng ON sng.id=c.target_id AND c.target_type='song' WHERE c.user_id=? AND ((c.target_type='post' AND p.site_id=c.site_id AND " . gblog_active_site_clause('p.site_id') . ") OR (c.target_type='song' AND sng.site_id=c.site_id AND " . gblog_active_site_clause('sng.site_id') . '))'),
        'favorites' => $ucCount("SELECT COUNT(*) FROM post_reactions r INNER JOIN posts p ON p.id=r.post_id WHERE r.user_id=? AND r.rtype='favorite' AND p.status='published' AND " . gblog_active_site_clause('p.site_id')),
        'downloads' => $ucCount('SELECT COUNT(*) FROM download_logs d INNER JOIN posts p ON p.id=d.post_id WHERE d.user_id=? AND ' . gblog_active_site_clause('p.site_id')),
        'gold' => (int)($me['gold'] ?? 0),
    ];
    $recentHistory = get_user_view_history($uid, 6);
    $recentGold = get_gold_logs($uid, 6);
    $recentComments = get_user_comments($uid, 5);
} elseif ($tab === 'content') {
    if ($canPost && $ucSub === 'posts') {
        $ucPostsPer = 15;
        $myPostsTotal = $ucCount('SELECT COUNT(*) FROM posts WHERE user_id=? AND ' . gblog_active_site_clause('site_id'));
        $myPostsPages = max(1, (int)ceil($myPostsTotal / $ucPostsPer));
        $myPostsPage = max(1, min($myPostsPages, (int)($_GET['page'] ?? 1)));
        $myPosts = get_user_posts($uid, $ucPostsPer, ($myPostsPage - 1) * $ucPostsPer);
    }
    $myComments = get_user_comments($uid, 30);
    $favorites = get_user_reactions_list($uid, 'favorite', 30);
    $likes = get_user_reactions_list($uid, 'like', 30);
    $viewHistory = get_user_view_history($uid, 30);
    $downloads = get_user_downloads($uid, 30);
} elseif ($tab === 'wallet') {
    $goldLogs = get_gold_logs($uid, 30);
    $purchases = get_user_purchases($uid, 30);
}

$GLOBALS['gblog_no_sidebar'] = true;
$GLOBALS['gblog_user_center'] = true;
$page_title = '用户中心';
get_header();
$__ucPart = gblog_theme_part('template/parts/user_center.php');
if ($__ucPart !== '') require $__ucPart;
get_footer();
