<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$rows = $comments ?? ($nbComments ?? []);
if (!is_array($rows)) $rows = [];

$fans = [];
foreach ($rows as $c) {
    $uid  = (int)($c['user_id'] ?? 0);
    $name = trim((string)($c['name'] ?? ''));
    if ($name === '') continue;
    $key = $uid > 0 ? 'u#' . $uid : 'n#' . mb_strtolower($name, 'UTF-8');
    if (!isset($fans[$key])) $fans[$key] = ['name' => $name, 'n' => 0];
    $fans[$key]['n']++;
    $fans[$key]['name'] = $name;
}
$fans = array_values($fans);
usort($fans, function ($a, $b) {
    if ($a['n'] !== $b['n']) return $b['n'] - $a['n'];
    return strcmp($a['name'], $b['name']);
});
$fans = array_slice($fans, 0, 5);

if (!$fans) {
    echo '<p class="nb-side-empty">还没有书迷数据，来评论区抢个沙发吧。</p>';
    return;
}
?>
<ol class="nb-fans-list">
  <?php foreach ($fans as $i => $f): ?>
    <li>
      <span class="nb-fans-rank"><?= $i + 1 ?></span>
      <span class="nb-fans-name"><?= e($f['name']) ?></span>
      <span class="nb-fans-n"><?= (int)$f['n'] ?> 条评论</span>
    </li>
  <?php endforeach; ?>
</ol>
<p class="nb-fans-note">按本作品评论数统计</p>
