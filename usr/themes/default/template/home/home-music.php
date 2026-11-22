<?php
/**
 * Template Name: 栏目首页·音乐
 * Context: $songs, $currentCat, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$musicCats       = $musicCats       ?? [];
$musicTotal      = $musicTotal      ?? 0;
$showTopicPlayer = $showTopicPlayer ?? true;
$site            = $site            ?? [];
?>
<section class="content list-posts">
  <?php if ($showTopicPlayer): ?>
  <?php
    $mpTitle    = '选择下方歌曲播放';
    $mpSubtitle = '共 ' . $musicTotal . ' 首原创音乐';
    $mpAudio    = '';
    $mpCover    = '';
    $mpLyrics   = '';
    $mpDesc     = ($site['description'] ?? '') ?: '原创音乐';
    $mpAutoplay = false;
    render_slot('music_player', get_defined_vars());
  ?>
  <?php endif; ?>

  <?php render_slot('music_reel', ['musicTopSongs' => $musicTopSongs ?? [], 'mpEnabled' => $showTopicPlayer]); ?>
  <?php render_slot('music_charts', ['musicChartCols' => $musicChartCols ?? []]); ?>

  <?php render_slot('music_grid', [
    'musicListSongs'      => $musicListSongs ?? [],
    'musicListTabs'       => $musicListTabs ?? [],
    'musicListPage'       => $musicListPage ?? 1,
    'musicListPages'      => $musicListPages ?? 1,
    'musicListTotal'      => $musicListTotal ?? 0,
    'musicListBase'       => $musicListBase ?? gblog_pretty_base('index.php?do=site&slug=music'),
    'musicListCatMissing' => $musicListCatMissing ?? false,
    'showTopicPlayer'     => $showTopicPlayer,
  ]); ?>
</section>

<?php if ($showTopicPlayer): ?>
<script src="<?= gblog_theme_asset_url('assets/js/music_player.js') ?>"></script>
<?php endif; ?>
