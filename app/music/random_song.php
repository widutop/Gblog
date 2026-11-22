<?php
require_once __DIR__ . '/../../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!gblog_music_site_active()) {
    echo json_encode(['ok' => false]);
    exit;
}

$where = "WHERE s.status = 'published' AND s.site_id = " . gblog_music_site_id() . " AND s.audio_url IS NOT NULL AND s.audio_url <> ''";
$count = (int)db()->query("SELECT COUNT(*) FROM songs s $where")->fetchColumn();
if ($count <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}
$offset = random_int(0, $count - 1);
$st = db()->prepare(
    "SELECT s.id, s.title, s.artist, s.lyricist, s.cover_url, s.audio_url,
            p.id AS post_id
     FROM songs s
     LEFT JOIN posts p ON p.id = s.post_id AND p.status = 'published'
                       AND " . gblog_active_site_clause('p.site_id') . "
     $where
     LIMIT 1 OFFSET " . (int)$offset
);
$st->execute();
$row = $st->fetch();
if (!$row) {
    echo json_encode(['ok' => false]);
    exit;
}
echo json_encode([
    'ok'         => true,
    'id'         => (int)$row['id'],
    'title'      => $row['title'],
    'artist'     => $row['artist'] ?: '',
    'lyricist'   => $row['lyricist'] ?: '',
    'cover_url'  => $row['cover_url'] ?: '',
    'audio_url'  => $row['audio_url'],
    'post_id'    => (int)($row['post_id'] ?? 0),
], JSON_UNESCAPED_UNICODE);
