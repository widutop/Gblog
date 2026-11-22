<?php
/**
 * Template Name: 详情页·歌曲
 * Context: $song, $songCat, $related
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$song = $song ?? [];
if (!$song) {
    echo '<section class="content"><div class="card"><p>当前内容不是歌曲，请为这个栏目选择「文章内容」模板。</p></div></section>';
    return;
}
$songCat = $songCat ?? null;
$related = $related ?? [];
$showSongPlayer  = get_setting('music_player_song', '1') === '1';
$songDl          = song_download_state($song, function_exists('current_user') ? current_user() : null);
$songBackUrl     = 'index.php?do=songs';
?>

<section class="content song-detail">
  <?php if ($showSongPlayer): ?>
  <?php
    $mpTitle    = $song['title'];
    $mpSubtitle = '';
    if ($song['artist'])   $mpSubtitle .= '歌手：' . e($song['artist']);
    if ($song['lyricist']) $mpSubtitle .= ($mpSubtitle ? ' · ' : '') . '作词：' . e($song['lyricist']);
    $mpAudio    = $song['audio_url'];
    $mpCover    = $song['cover_url'] ?? '';
    $mpLyrics   = $song['lyrics'] ?? '';
    $mpDesc     = '歌曲详情';
    $mpAutoplay = true;
    render_slot('music_player', get_defined_vars());
  ?>
  <?php endif; ?>

  <article class="song-hero card">
    <div class="song-hero-cover">
      <?php if (!empty($song['cover_url'])): ?>
        <img src="<?= e($song['cover_url']) ?>" alt="<?= e($song['title']) ?>" loading="lazy">
      <?php else: ?>
        <span class="song-hero-ph"><?= gblog_icon('music', 36) ?></span>
      <?php endif; ?>
    </div>
    <div class="song-hero-main">
      <h1 class="song-hero-title"><?= e($song['title']) ?></h1>
      <div class="song-hero-artist">
        <?= gblog_icon('user', 15) ?> <?= e($song['artist'] ?: '佚名') ?>
        <?php if (!empty($song['lyricist'])): ?><span class="song-hero-sep">·</span>作词 <?= e($song['lyricist']) ?><?php endif; ?>
      </div>
      <div class="song-hero-meta">
        <span><?= gblog_icon('play', 14) ?> <?= (int)$song['play_count'] ?> 次播放</span>
        <span><?= gblog_icon('clock', 14) ?> <?= e(fmt_date($song['created_at'])) ?></span>
        <?php if ($songCat): ?><span><?= gblog_icon('music', 14) ?> <?= e($songCat['name']) ?></span><?php endif; ?>
      </div>
      <div class="song-hero-actions">
        <?php if (!empty($song['download_url'])): ?>
          <?php if ($songDl['allowed']): ?>
            <a class="btn btn-primary" href="index.php?do=song_download&amp;id=<?= (int)$song['id'] ?>">
              <?= gblog_icon('download', 15) ?> 下载歌曲<?= $songDl['level'] === 'coin' && (int)$songDl['price'] > 0 ? '（' . (int)$songDl['price'] . ' 金币）' : '' ?>
            </a>
          <?php else: ?>
            <span class="btn btn-locked" title="<?= e($songDl['reason']) ?>"><?= gblog_icon('download', 15) ?> <?= e($songDl['reason']) ?></span>
            <?php if ($songDl['level'] !== 'free' && !current_user()): ?>
              <a class="song-hero-login" href="javascript:;" onclick="document.getElementById('login-modal').style.display='flex'">去登录</a>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
        <a class="song-hero-back" href="<?= e($songBackUrl) ?>"><?= gblog_icon('next', 14) ?> 全部歌曲</a>
      </div>
    </div>
  </article>

  <?php if (!empty($song['lyrics'])): ?>
  <section class="card song-lyrics">
    <h2 class="song-lyrics-title">歌词</h2>
    <div class="song-lyrics-body"><?= e(trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/', '', preg_replace('/^\xEF\xBB\xBF/', '', $song['lyrics']))))) ?></div>
  </section>
  <?php endif; ?>

  <section class="card song-note">
    <h2 class="song-note-title">创作说明 / 版权声明</h2>
    <div class="song-note-body"><?= e($song['note'] ?: '本歌曲歌词由惟度原创，曲调旋律 AI 辅助创作。可转载，转载须注明作者惟度；本作品仅限非商业使用，任何因商业使用产生的一切后果由使用者自行承担。') ?></div>
  </section>

  <?php
  $relType  = 'song';
  $siteName = $songCat['name'] ?? '';
  render_slot('related', get_defined_vars());
  ?>

  <?php render_slot('comments', get_defined_vars()); ?>
</section>

<?php if ($showSongPlayer): ?>
<script src="<?= gblog_theme_asset_url('assets/js/music_player.js') ?>"></script>
<?php endif; ?>
