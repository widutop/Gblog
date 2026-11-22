<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$ok = false;
if ($id > 0 && gblog_music_site_active()) {
    $ok = increment_song_play($id);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => $ok]);
