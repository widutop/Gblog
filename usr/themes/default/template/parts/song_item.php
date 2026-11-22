<?php
/** Template Name: 歌曲列表条目（可播放/纯链接两种形态），入参 $p、$mpEnabled；主题可覆盖 */
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!isset($mpEnabled)) $mpEnabled = false;

require_once GBLOG_ROOT . '/core/render/music_theme.php';
$siPal = gblog_music_player_palette('custom', gblog_theme_primary_color());

$siId       = (int)$p['id'];
$siTitle    = (string)$p['title'];
$siCover    = $p['cover_url'] ?? '';
$siAudio    = $p['audio_url'] ?? '';
$siLyricist = $p['lyricist'] ?? '';
$siLyr      = $p['lyrics'] ?? '';
$siPlain    = $siLyr ? trim(preg_replace('/\[\d{2}:\d{2}\.\d{2,3}\]/', '', $siLyr)) : '';
$siHref     = 'index.php?do=song&id=' . $siId;

ob_start();
if ($siCover): ?>
  <img src="<?= e($siCover) ?>" alt="<?= e($siTitle) ?>" style="width:50px;height:50px;border-radius:6px;object-fit:cover;flex-shrink:0;">
<?php else: ?>
  <div class="gb-cover-mini" data-cover="music"><?= gblog_icon('music') ?></div>
<?php endif;
$siCoverHtml = ob_get_clean();
?>
<?php if ($mpEnabled): ?>
<div class="main-player-item" data-audio="<?= e($siAudio) ?>" data-id="<?= $siId ?>" data-title="<?= e($siTitle) ?>" style="display:flex;align-items:center;gap:14px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;cursor:pointer;">
  <?= $siCoverHtml ?>
  <div style="flex:1;min-width:0;">
    <div style="font-size:16px;font-weight:600;color:var(--text);"><?= e($siTitle) ?></div>
  </div>
  <?php if ($siPlain): ?>
  <a href="<?= $siHref ?>" onclick="event.stopPropagation();" title="查看歌词与歌曲详情" style="flex:2;min-width:0;font-size:13px;color:var(--text-muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none;">
    <?= e($siPlain) ?>
  </a>
  <?php endif; ?>
  <?php if ($siLyricist): ?><div style="font-size:14px;color:var(--text);flex-shrink:0;"><?= e($siLyricist) ?></div><?php endif; ?>
  <span style="padding:6px 14px;background:<?= $siPal['bg'] ?>;color:<?= $siPal['fg'] ?>;border-radius:6px;font-size:14px;flex-shrink:0;">播放</span>
</div>
<?php else: ?>
<a href="<?= $siHref ?>" style="display:flex;align-items:center;gap:14px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;text-decoration:none;color:inherit;">
  <?= $siCoverHtml ?>
  <div style="flex:1;min-width:0;">
    <div style="font-size:16px;font-weight:600;color:var(--text);"><?= e($siTitle) ?></div>
  </div>
  <?php if ($siPlain): ?>
  <div style="flex:2;min-width:0;font-size:13px;color:var(--text-muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"><?= e($siPlain) ?></div>
  <?php endif; ?>
  <?php if ($siLyricist): ?><div style="font-size:14px;color:var(--text);flex-shrink:0;"><?= e($siLyricist) ?></div><?php endif; ?>
  <span style="padding:6px 14px;background:<?= $siPal['bg'] ?>;color:<?= $siPal['fg'] ?>;border-radius:6px;font-size:14px;flex-shrink:0;">详情</span>
</a>
<?php endif; ?>
