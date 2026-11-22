<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$mcCols = $musicChartCols ?? [];
if (!$mcCols) return;
?>
<div class="gb-music-charts">
  <?php foreach ($mcCols as $mcCol):
      $mcSongs = $mcCol['songs'] ?? [];
      $mcFirst = $mcSongs ? (int)$mcSongs[0]['id'] : 0;
      $mcTitle = (string)($mcCol['title'] ?? '');
  ?>
  <section class="gb-music-chart gb-music-chart--<?= e((string)($mcCol['tone'] ?? 'red')) ?>">
    <div class="gb-music-chart-head">
      <h2><?= e($mcTitle) ?></h2>
      <?php if ($mcFirst): ?>
      <a class="gb-music-chart-play" href="index.php?do=site&amp;slug=music&amp;play=<?= $mcFirst ?>" title="播放本榜第一首" aria-label="播放<?= e($mcTitle) ?>"></a>
      <?php endif; ?>
    </div>
    <?php if (!$mcSongs): ?>
    <div class="gb-music-chart-empty">暂无歌曲</div>
    <?php else: ?>
    <ol class="gb-music-chart-list">
      <?php foreach ($mcSongs as $mcIdx => $mcSong):
          $mcId    = (int)$mcSong['id'];
          $mcHref  = 'index.php?do=song&amp;id=' . $mcId;
          $mcTitle = (string)$mcSong['title'];
          $mcAudio = (string)($mcSong['audio_url'] ?? '');
      ?>
      <li class="gb-music-chart-row">
        <a class="gb-music-chart-link" href="<?= $mcHref ?>">
          <span class="gb-music-chart-no"><?= str_pad((string)($mcIdx + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <?php if ($mcIdx < 3): ?>
          <span class="gb-music-chart-cover" aria-hidden="true">
            <?php if (!empty($mcSong['cover_url'])): ?>
            <img src="<?= e((string)$mcSong['cover_url']) ?>" alt="" loading="lazy">
            <?php else: ?>
            <?= gblog_icon('music', 18) ?>
            <?php endif; ?>
          </span>
          <?php endif; ?>
          <span class="gb-music-chart-title"><?= e($mcTitle) ?></span>
        </a>
        <?php if ($mcAudio !== ''): ?>
        <button type="button" class="gb-music-chart-songplay main-player-item" data-audio="<?= e($mcAudio) ?>" data-id="<?= $mcId ?>" data-title="<?= e($mcTitle) ?>" aria-label="播放 <?= e($mcTitle) ?>"><?= gblog_icon('play', 14) ?></button>
        <?php else: ?>
        <a class="gb-music-chart-songplay" href="<?= $mcHref ?>" aria-label="查看 <?= e($mcTitle) ?>"><?= gblog_icon('play', 14) ?></a>
        <?php endif; ?>
        <span class="gb-music-chart-artist"><?= e((string)($mcSong['artist'] ?: '未知歌手')) ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>
