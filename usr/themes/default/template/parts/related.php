<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 通用「相关推荐」模块：音乐内容页与文章/软件/图片/小说简介页共用同一套结构 + 样式（.song-related）。
 * 入参：
 *   $related  必传，相关条目数组
 *   $relType  'post'（默认：文章/软件/图片/小说简介，按 site_id 同栏目）或 'song'（音乐，按分类）
 * 仅当 $related 非空时输出，不足时也整体不显示。标题统一为「相关推荐」。
 */
$related = $related ?? [];
$relType = $relType ?? 'post';
if (empty($related)) return;
?>
<section class="card song-related">
  <h2 class="song-related-title">相关推荐</h2>
  <div class="song-related-grid">
    <?php foreach ($related as $rel): ?>
    <?php if ($relType === 'song'): ?>
      <?php
        $relHref  = 'index.php?do=song&id=' . (int)($rel['id'] ?? 0);
        $relCover = $rel['cover_url'] ?? '';
        $relName  = $rel['title'] ?? '';
        $relSub   = e($rel['artist'] ?: '佚名') . ' · ' . (int)($rel['play_count'] ?? 0) . ' 播放';
        $relIcon  = gblog_icon('music', 22);
      ?>
    <?php else: ?>
      <?php
        $relHref  = 'index.php?do=post&id=' . (int)($rel['id'] ?? 0);
        $relCover = $rel['cover'] ?? '';
        $relName  = $rel['title'] ?? '';
        $relSub   = e($rel['author'] ?: '匿名') . ' · ' . e(fmt_date($rel['created_at'] ?? ''));
        $relIcon  = gblog_icon('file', 22);
      ?>
    <?php endif; ?>
    <a class="song-rel-item" href="<?= $relHref ?>">
      <div class="song-rel-cover">
        <?php if (!empty($relCover)): ?>
          <img src="<?= e($relCover) ?>" alt="<?= e($relName) ?>" loading="lazy">
        <?php else: ?>
          <?= $relIcon ?>
        <?php endif; ?>
      </div>
      <div class="song-rel-info">
        <div class="song-rel-name"><?= e($relName) ?></div>
        <div class="song-rel-artist"><?= $relSub ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
