<?php
/**
 * Template Name: 分类列表·音乐
 * Context: $songs, $currentCat, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$songs          = $songs          ?? [];
$cats           = $cats           ?? [];
$currentCat     = $currentCat     ?? null;
$catMissing     = $catMissing     ?? false;
$total          = $total          ?? count($songs);
$page           = $page           ?? 1;
$totalPages     = $totalPages     ?? 1;
$showListPlayer = $showListPlayer ?? true;
$page_title     = $page_title     ?? '全部歌曲';
?>
<section class="content list-posts">

  <?php if ($showListPlayer): ?>
  <?php
    $mpTitle    = '选择下方歌曲播放';
    $mpSubtitle = '共 ' . $total . ' 首原创音乐';
    $mpAudio    = '';
    $mpCover    = '';
    $mpLyrics   = '';
    $mpDesc     = $currentCat ? ('分类：' . $currentCat['name']) : '全部歌曲';
    $mpAutoplay = false;
    render_slot('music_player', get_defined_vars());
  ?>
  <?php endif; ?>

  <div class="card" style="padding:14px 18px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
    <a href="<?= e(gblog_url('site', ['slug' => 'music'])) ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;margin-right:4px;">← 音乐首页</a>
    <a href="<?= e(gblog_url('songs')) ?>" class="btn btn-sm <?= !$currentCat && !$catMissing ? 'btn-primary' : '' ?>">全部歌曲</a>
    <?php foreach ($cats as $c): ?>
      <a href="<?= e(gblog_pretty_base('index.php?do=songs&cat=' . (int)$c['id'])) ?>" class="btn btn-sm <?= $currentCat && (int)$currentCat['id'] === (int)$c['id'] ? 'btn-primary' : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <h2 class="list-title" style="margin:16px 0 10px;font-size:18px;font-weight:600;color:var(--text);">
    <?= $catMissing ? '分类不存在' : e($page_title) ?>
    <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:8px;"><?= $total ?> 首</span>
  </h2>

  <?php if ($catMissing): ?>
    <div class="card"><p>没有找到该分类，可能已被删除。请从上方重新选择，或<a href="<?= e(gblog_url('songs')) ?>">查看全部歌曲</a>。</p></div>
  <?php elseif (!$songs): ?>
    <div class="card"><p>这里还没有歌曲。</p></div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <?php foreach ($songs as $p): ?>
        <?php $mpEnabled = $showListPlayer; render_slot('song_item', get_defined_vars()); ?>
      <?php endforeach; ?>
    </div>

    <?php
      $pgPage  = $page;
      $pgPages = $totalPages;
      $pgBase  = gblog_pretty_base('index.php?do=songs' . ($currentCat ? '&cat=' . (int)$currentCat['id'] : ''));
      $pgLabel = '歌曲列表分页';
      render_slot('song_pagination', get_defined_vars());
    ?>
  <?php endif; ?>
</section>

<?php if ($showListPlayer): ?>
<script src="<?= gblog_theme_asset_url('assets/js/music_player.js') ?>"></script>
<?php endif; ?>
