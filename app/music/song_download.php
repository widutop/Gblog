<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (!gblog_rate_allow('download_ip', gblog_client_ip(), 60, 60)) {
    http_response_code(429);
    exit('下载过于频繁，请稍后再试。');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !gblog_music_site_active()) {
    gblog_not_found('歌曲不存在', '歌曲不存在或已下架。');
}
$st = db()->prepare("SELECT * FROM songs WHERE id = ? AND site_id = ? AND status = 'published' LIMIT 1");
$st->execute([$id, gblog_music_site_id()]);
$song = $st->fetch();
$url  = $song ? trim((string)$song['download_url']) : '';
if (!$song || $url === '') {
    gblog_not_found('资源不存在', '这首歌曲未提供下载。');
}

$back = 'index.php?do=song&id=' . $id;
$me   = current_user();
$dl   = song_download_state($song, $me);

if (!$dl['allowed']) {
    $page_title = '无法下载';
    get_header();
    echo '<section class="content"><div class="card"><p>' . e($dl['reason']) . '</p>'
       . '<a class="btn btn-primary" href="' . e($back) . '">返回歌曲页</a></div></section>';
    get_footer();
    exit;
}

if ($dl['level'] === 'coin' && (int)$dl['price'] > 0) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE users SET gold = gold - ? WHERE id = ? AND gold >= ?');
        $st->execute([(int)$dl['price'], (int)$me['id'], (int)$dl['price']]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();
            $page_title = '金币不足';
            get_header();
            echo '<section class="content"><div class="card"><p>金币不足，需 ' . (int)$dl['price'] . ' 金币。</p>'
               . '<a class="btn btn-primary" href="' . e($back) . '">返回歌曲页</a></div></section>';
            get_footer();
            exit;
        }
        $pdo->prepare('INSERT INTO coin_logs (user_id, amount, reason) VALUES (?,?,?)')
            ->execute([(int)$me['id'], -(int)$dl['price'], '歌曲下载 #' . $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $page_title = '下载失败';
        get_header();
        echo '<section class="content"><div class="card"><p>扣费失败，请稍后重试。</p>'
           . '<a class="btn btn-primary" href="' . e($back) . '">返回歌曲页</a></div></section>';
        get_footer();
        exit;
    }
}

$target = gblog_safe_redirect($url, $back);
header('Location: ' . $target, true, 302);
exit;
