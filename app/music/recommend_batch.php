<?php
require_once __DIR__ . '/../../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!gblog_music_site_active()) {
    echo json_encode(['ok' => false, 'reason' => 'music_off']);
    exit;
}

$kind   = (string)($_GET['kind'] ?? 'rec');
$ranges = gblog_music_rec_ranges();
if (!isset($ranges[$kind])) $kind = 'rec';

$exclude = [];
if (isset($_GET['exclude']) && $_GET['exclude'] !== '') {
    $exclude = array_slice(array_filter(array_map('intval', explode(',', (string)$_GET['exclude']))), 0, 60);
}

$songs = gblog_random_ranked_songs($kind, $exclude);
$out = [];
foreach ($songs as $s) {
    $out[] = [
        'id'        => (int)$s['id'],
        'title'     => (string)$s['title'],
        'artist'    => (string)($s['artist'] ?: '未知歌手'),
        'cover_url' => (string)($s['cover_url'] ?: ''),
        'audio_url' => (string)($s['audio_url'] ?: ''),
        // 根绝对：s1/s2 的 /post/101.html 会被浏览器当目录，相对地址会打错。
        'href'      => gblog_root_absolute(gblog_url('song', ['id' => (int)$s['id']])),
    ];
}
echo json_encode([
    'ok'    => true,
    'kind'  => $kind,
    'pick'  => $ranges[$kind]['pick'],
    'count' => count($out),
    'songs' => $out,
], JSON_UNESCAPED_UNICODE);
