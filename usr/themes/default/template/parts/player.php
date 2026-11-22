<?php
/** Template Name: 单曲播放卡（音频 + 封面 + 歌词），入参 $song；主题可覆盖 */
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (empty($song)) {
    return;
}
?>
<div class="music-player" data-audio="<?= e($song['audio_url']) ?>">
  <div class="mp-head">
    <?php if (!empty($song['cover_url'])): ?>
      <img class="mp-cover" src="<?= e($song['cover_url']) ?>" alt="封面" loading="lazy">
    <?php else: ?>
      <div class="mp-cover mp-cover-placeholder" data-cover="music"><?= gblog_icon('music', 28) ?></div>
    <?php endif; ?>
    <div class="mp-info">
      <div class="mp-title"><?= e($song['title']) ?></div>
      <?php if (!empty($song['artist'])): ?>
        <div class="mp-artist"><?= e($song['artist']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <audio class="mp-audio" controls preload="metadata" src="<?= e($song['audio_url']) ?>"></audio>

  <?php
    $dlUrl = !empty($song['download_url']) ? $song['download_url'] : $song['audio_url'];
    if (!empty($dlUrl)):
  ?>
    <div class="mp-actions">
      <a class="mp-download" href="<?= e($dlUrl) ?>" target="_blank" rel="noopener"><?= gblog_icon('download') ?> 下载</a>
    </div>
  <?php endif; ?>

  <?php if (!empty($song['lyrics'])): ?>
    <div class="mp-lyrics">
      <pre class="mp-lyrics-src" hidden><?= e($song['lyrics']) ?></pre>
      <div class="mp-lyrics-inner"></div>
    </div>
  <?php endif; ?>
</div>
