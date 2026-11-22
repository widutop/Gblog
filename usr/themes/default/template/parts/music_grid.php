<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$mgSongs  = $musicListSongs ?? [];
$mgTabs   = $musicListTabs ?? [];
$mgPage   = max(1, (int)($musicListPage ?? 1));
$mgPages  = max(1, (int)($musicListPages ?? 1));
$mgBase   = e((string)($musicListBase ?? 'index.php?do=site&slug=music'));
$mgMiss   = !empty($musicListCatMissing);
$mgPlayer = !empty($showTopicPlayer);
$mgFrag   = !empty($musicGridFrag);
?>
<section class="gb-music-grid" id="gb-music-grid">
  <div class="gb-music-grid-head">
    <h2>歌曲列表</h2>
    <nav class="gb-music-grid-tabs" aria-label="歌曲分类">
      <span class="gb-tab-group">
      <?php foreach ($mgTabs as $mgTab): ?>
      <a class="gb-music-grid-tab<?= !empty($mgTab['active']) ? ' is-active' : '' ?>" href="<?= e((string)$mgTab['url']) ?>"<?= !empty($mgTab['active']) ? ' aria-current="page"' : '' ?>><?= e((string)$mgTab['name']) ?></a>
      <?php endforeach; ?>
      </span>
    </nav>
  </div>
  <?php if ($mgMiss): ?>
  <div class="gb-music-grid-note">没有找到该分类，已显示全部歌曲。</div>
  <?php endif; ?>
  <?php if (!$mgSongs): ?>
  <div class="gb-music-grid-empty">这里还没有歌曲。</div>
  <?php else: ?>
  <div class="gb-music-grid-list">
    <?php foreach ($mgSongs as $mgSong):
        $mgId    = (int)$mgSong['id'];
        $mgHref  = 'index.php?do=song&amp;id=' . $mgId;
        $mgTitle = (string)$mgSong['title'];
        $mgAudio = (string)($mgSong['audio_url'] ?? '');
    ?>
    <div class="gb-music-grid-card">
      <a class="gb-music-grid-cover" href="<?= $mgHref ?>" aria-label="<?= e($mgTitle) ?>">
        <?php if (!empty($mgSong['cover_url'])): ?>
        <img src="<?= e((string)$mgSong['cover_url']) ?>" alt="<?= e($mgTitle) ?>" loading="lazy">
        <?php else: ?>
        <?= gblog_icon('music', 22) ?>
        <?php endif; ?>
      </a>
      <div class="gb-music-grid-meta">
        <a class="gb-music-grid-title" href="<?= $mgHref ?>" title="<?= e($mgTitle) ?>"><?= e($mgTitle) ?></a>
        <span class="gb-music-grid-artist"><?= e((string)($mgSong['artist'] ?: '未知歌手')) ?></span>
      </div>
      <?php if ($mgPlayer && $mgAudio !== ''): ?>
      <button type="button" class="gb-music-grid-play main-player-item" data-audio="<?= e($mgAudio) ?>" data-id="<?= $mgId ?>" data-title="<?= e($mgTitle) ?>" aria-label="播放 <?= e($mgTitle) ?>"><?= gblog_icon('play', 16) ?></button>
      <?php else: ?>
      <a class="gb-music-grid-play" href="<?= $mgHref ?>" aria-label="查看 <?= e($mgTitle) ?>"><?= gblog_icon('play', 16) ?></a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php
    $pgPage  = $mgPage;
    $pgPages = $mgPages;
    $pgBase  = $mgBase;
    $pgLabel = '歌曲列表分页';
    render_slot('song_pagination', get_defined_vars());
  ?>
  <?php endif; ?>
</section>
<?php if (!$mgFrag): ?>
<script src="<?= gblog_theme_asset_url('assets/js/music_grid.js') ?>"></script>
<?php endif; ?>
