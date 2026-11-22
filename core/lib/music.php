<?php
/* 音乐 —— 歌曲查询、榜单、随机、播放计数。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function get_songs_by_post_id(int $postId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM songs WHERE post_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

/** 侧栏播放器列表：全部已发布歌曲，随机顺序（每次刷新重排）。 */
function get_songs_random(int $limit = 500): array
{
    if (!gblog_music_site_active()) return [];
    $limit = max(1, min(1000, $limit));
    $rand  = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'RANDOM()' : 'RAND()';
    $sql   = "SELECT s.id, s.title, s.artist, s.lyricist, s.cover_url, s.audio_url
            FROM songs s
            WHERE s.status = 'published' AND s.site_id = " . gblog_music_site_id() . "
            ORDER BY " . $rand . "
            LIMIT " . (int)$limit;
    return db()->query($sql)->fetchAll();
}

function get_ranked_songs(int $offset = 0, int $limit = 10): array{
    if (!gblog_music_site_active()) return [];
    $offset = max(0, $offset);
    $limit  = max(1, min(100, $limit));
    $sql = "SELECT s.id, s.title, s.artist, s.cover_url, s.audio_url
            FROM songs s
            WHERE s.status = 'published' AND s.site_id = " . gblog_music_site_id() . "
            ORDER BY s.sort_order DESC, s.id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    return db()->query($sql)->fetchAll();
}

function gblog_music_rec_ranges(): array
{
    return [
        'rec'  => ['offset' => 10, 'limit' => 30, 'pick' => 10],
        'deep' => ['offset' => 40, 'limit' => 20, 'pick' => 15],
    ];
}

function gblog_random_song_pick(array $pool, int $pick, array $exclude = []): array
{
    if (!$pool) return [];
    $pick = max(1, min(count($pool), $pick));
    if ($exclude) {
        $ex = array_map('intval', $exclude);
        $fresh = array_values(array_filter($pool, function ($s) use ($ex) {
            return !in_array((int)($s['id'] ?? 0), $ex, true);
        }));
        if (count($fresh) >= $pick) $pool = $fresh;
    }
    shuffle($pool);
    return array_slice($pool, 0, $pick);
}

function gblog_random_ranked_songs(string $kind, array $exclude = []): array
{
    $ranges = gblog_music_rec_ranges();
    if (!isset($ranges[$kind])) return [];
    $c = $ranges[$kind];
    return gblog_random_song_pick(get_ranked_songs($c['offset'], $c['limit']), $c['pick'], $exclude);
}

function increment_song_play(int $songId): bool
{
    if ($songId <= 0) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['played_songs']) || !is_array($_SESSION['played_songs'])) $_SESSION['played_songs'] = [];
    if (in_array($songId, $_SESSION['played_songs'], true)) return true;
    $st = db()->prepare("UPDATE songs SET play_count = play_count + 1 WHERE id = ? AND site_id = ? AND status = 'published'");
    $st->execute([$songId, gblog_music_site_id()]);
    if ($st->rowCount() < 1) return false;
    $_SESSION['played_songs'][] = $songId;
    return true;
}

// 歌曲下载权限（free/login/coin）；coin 以 coin_logs 的「歌曲下载 #id」记录判定是否已付过费
function song_download_state(array $song, ?array $user): array
{
    $level = $song['download_level'] ?? 'free';
    $price = (int)($song['download_price'] ?? 0);
    if ($level === 'free') {
        return ['level' => 'free', 'price' => 0, 'allowed' => true, 'reason' => ''];
    }
    if (!$user) {
        return ['level' => $level, 'price' => $price, 'allowed' => false, 'reason' => $level === 'login' ? '请先登录后下载' : '需登录后下载'];
    }
    if ($level === 'login') {
        return ['level' => 'login', 'price' => 0, 'allowed' => true, 'reason' => ''];
    }
    try {
        $st = db()->prepare('SELECT id FROM coin_logs WHERE user_id = ? AND reason = ? LIMIT 1');
        $st->execute([(int)$user['id'], '歌曲下载 #' . (int)($song['id'] ?? 0)]);
        if ($st->fetch()) {
            return ['level' => 'coin', 'price' => 0, 'allowed' => true, 'reason' => ''];
        }
    } catch (Throwable $e) {
        gblog_log('song_download_state', $e->getMessage());
    }
    return ['level' => 'coin', 'price' => $price, 'allowed' => false, 'reason' => '需 ' . $price . ' 金币'];
}
