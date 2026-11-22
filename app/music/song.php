<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (!gblog_music_site_active()) {
    gblog_not_found('音乐栏目不可用', '音乐栏目不存在或已关闭。');
}

$id = (int)($_GET['id'] ?? 0);
$musicSiteId = gblog_music_site_id();
$stmt = db()->prepare("SELECT s.*, u.username AS author_name FROM songs s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.site_id = ? AND s.status = 'published'");
$stmt->execute([$id, $musicSiteId]);
$song = $stmt->fetch();

if (!$song) {
    gblog_not_found('歌曲不存在', '歌曲不存在。');
}

$songCat = null;
if (!empty($song['category_id'])) {
    $c = db()->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
    $c->execute([(int)$song['category_id']]);
    $songCat = $c->fetch() ?: null;
}

$related = [];
try {
    if ($songCat) {
        $r = db()->prepare("SELECT id, title, artist, cover_url, play_count FROM songs WHERE status = 'published' AND site_id = ? AND category_id = ? AND id <> ? ORDER BY play_count DESC, id DESC LIMIT 6");
        $r->execute([(int)($song['site_id'] ?? 0), (int)$songCat['id'], (int)$song['id']]);
    } else {
        $r = db()->prepare("SELECT id, title, artist, cover_url, play_count FROM songs WHERE status = 'published' AND site_id = ? AND id <> ? ORDER BY play_count DESC, id DESC LIMIT 6");
        $r->execute([(int)($song['site_id'] ?? 0), (int)$song['id']]);
    }
    $related = $r->fetchAll() ?: [];
} catch (Throwable $e) {
}

$cm = gblog_handle_comments((int)$song['id'], 'song');
$comments      = $cm['comments'];
$comment_open  = $cm['open'];
$comment_msg   = $cm['msg'];
$comment_ok    = $cm['ok'];
$commentAction = 'index.php?do=song&id=' . (int)$song['id'] . '#comments';

$page_title = $song['title'];
$GLOBALS['site'] = db()->query("SELECT * FROM sites WHERE slug = 'music' AND enabled = 1")->fetch() ?: $GLOBALS['site'] ?? [];
get_header();

render_view('detail', get_defined_vars(), 'music');

get_footer();
