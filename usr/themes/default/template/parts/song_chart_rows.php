<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$songs = $songs ?? [];
foreach ($songs as $mcIdx => $mcSong):
    $mcId    = (int)$mcSong['id'];
    $mcHref  = 'index.php?do=song&id=' . $mcId;
    $mcTitle = (string)$mcSong['title'];
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
    <a class="gb-music-chart-songplay" href="<?= $mcHref ?>" aria-label="查看 <?= e($mcTitle) ?>"></a>
    <span class="gb-music-chart-artist"><?= e((string)($mcSong['artist'] ?: '未知歌手')) ?></span>
  </li>
<?php endforeach; ?>
