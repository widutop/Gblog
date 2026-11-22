<?php
require_once __DIR__ . '/../../core/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !gblog_music_site_active()) { http_response_code(404); echo ''; exit; }

$st = db()->prepare("SELECT lyrics FROM songs WHERE id=? AND site_id=? AND status='published'");
$st->execute([$id, gblog_music_site_id()]);
$row = $st->fetch();
echo preg_replace('/^\xEF\xBB\xBF/', '', (string)($row['lyrics'] ?? ''));
