<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$reelSongs   = $musicTopSongs ?? [];
$reelPlayable = !empty($mpEnabled);
if (!$reelSongs) return;

$reelOrig = count($reelSongs);
$reelLoop = $reelSongs;
while (count($reelLoop) < 10) {
    $reelLoop[] = $reelSongs[count($reelLoop) % $reelOrig];
}
?>
<div class="gb-music-reel">
  <div class="gb-music-reel-track">
    <?php foreach ($reelLoop as $reelSong): ?>
    <?php
      $reelId    = (int)$reelSong['id'];
      $reelTitle = (string)$reelSong['title'];
      $reelAudio = trim((string)($reelSong['audio_url'] ?? ''));
      $reelHref  = 'index.php?do=song&amp;id=' . $reelId;
    ?>
    <div class="gb-music-reel-item">
      <span class="gb-music-reel-cover">
        <?php if (!empty($reelSong['cover_url'])): ?>
        <img src="<?= e($reelSong['cover_url']) ?>" alt="<?= e($reelTitle) ?>" loading="lazy">
        <?php else: ?>
        <span class="gb-music-reel-ph"><?= gblog_icon('music', 28) ?></span>
        <?php endif; ?>
        <?php if ($reelPlayable && $reelAudio !== ''): ?>
        <button type="button" class="gb-music-reel-play" data-state="idle" data-song-id="<?= $reelId ?>" data-song-title="<?= e($reelTitle) ?>" data-audio="<?= e($reelAudio) ?>" aria-label="播放 <?= e($reelTitle) ?>"><?= gblog_icon('play', 20) ?></button>
        <?php endif; ?>
      </span>
      <span class="gb-music-reel-meta">
        <a class="gb-music-reel-title" href="<?= $reelHref ?>" title="<?= e($reelTitle) ?>"><?= e($reelTitle) ?></a>
        <span class="gb-music-reel-artist"><?= e((string)($reelSong['artist'] ?: '未知歌手')) ?></span>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script src="<?= gblog_theme_asset_url('assets/js/music_reel.js') ?>"></script>
