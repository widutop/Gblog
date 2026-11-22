<?php
/** Template Name: 音乐栏目共享播放器（含进度/歌词/波形），入参 $mp* 系列；主题可覆盖 */
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!isset($mpTitle))    $mpTitle    = '选择下方歌曲播放';
if (!isset($mpSubtitle)) $mpSubtitle = '';
if (!isset($mpAudio))    $mpAudio    = '';
if (!isset($mpCover))    $mpCover    = '';
if (!isset($mpLyrics))   $mpLyrics   = '';
$mpLyrics = preg_replace('/^\xEF\xBB\xBF/', '', $mpLyrics);
if (!isset($mpDesc))     $mpDesc     = '歌曲详情';
if (!isset($mpAutoplay)) $mpAutoplay = false;

require_once GBLOG_ROOT . '/core/render/music_theme.php';
if (!isset($mpThemeColor)) $mpThemeColor = gblog_theme_primary_color();
$mp = gblog_music_player_palette('custom', $mpThemeColor);
$mpBlur   = !empty($mp['blur'])   ? 'backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);' : '';
$mpBorder = !empty($mp['border']) ? 'border:1px solid ' . $mp['border'] . ';' : '';

$mpStripped = $mpLyrics ? trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/', '', $mpLyrics))) : '';
?>
<div class="song-player music-shared-player"<?= $mpAutoplay ? ' data-autoplay="1"' : '' ?> style="background:linear-gradient(135deg,<?= $mp['bg'] ?>,<?= $mp['bg2'] ?>);border-radius:10px;padding:12px;color:<?= $mp['fg'] ?>;margin-bottom:12px;display:flex;gap:12px;<?= $mpBlur ?><?= $mpBorder ?>">
  <div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <?php if ($mpCover): ?>
          <img src="<?= e($mpCover) ?>" alt="<?= e($mpTitle) ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;flex-shrink:0;">
        <?php else: ?>
          <div style="width:50px;height:50px;border-radius:8px;background:<?= $mp['panel'] ?>;<?= $mpBorder ?>display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;"><?= gblog_icon('music') ?></div>
        <?php endif; ?>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:16px;font-weight:600;" id="main-player-title"><?= e($mpTitle) ?></div>
              <div style="font-size:13px;opacity:.8;margin-top:2px;" id="main-player-subtitle"><?= $mpSubtitle ?></div>
            </div>
            <div id="waveform" class="song-player-wave" style="height:30px;display:flex;align-items:center;gap:2px;flex:1;margin:0 20px;">
              <?php for ($i = 0; $i < 50; $i++): ?>
              <div style="width:3px;height:60%;background:<?= $mp['bar'] ?>;border-radius:2px;"></div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
      </div>
      <div style="font-size:15px;font-weight:500;color:<?= $mp['fg'] ?>;margin-bottom:12px;"><?= e($mpDesc) ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <button type="button" id="main-player-prev" style="width:32px;height:32px;border-radius:50%;background:<?= $mp['panel'] ?>;color:<?= $mp['fg'] ?>;<?= $mpBorder ?>font-size:14px;cursor:pointer;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;"><?= gblog_icon('prev', 14) ?></button>
      <button type="button" id="main-player-btn" style="width:40px;height:40px;border-radius:50%;background:<?= $mp['chip'] ?>;color:<?= $mp['chipFg'] ?>;<?= $mpBorder ?>font-size:15px;cursor:pointer;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;"><?= gblog_icon('play', 16) ?></button>
      <button type="button" id="main-player-next" style="width:32px;height:32px;border-radius:50%;background:<?= $mp['panel'] ?>;color:<?= $mp['fg'] ?>;<?= $mpBorder ?>font-size:14px;cursor:pointer;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;"><?= gblog_icon('next', 14) ?></button>
      <div style="flex:1;display:flex;align-items:center;gap:8px;">
        <span id="main-time-current" style="font-size:13px;opacity:.8;min-width:35px;">0:00</span>
        <input type="range" id="main-progress" min="0" max="100" value="0" style="flex:1;height:4px;-webkit-appearance:none;background:<?= $mp['track'] ?>;border-radius:2px;outline:none;cursor:pointer;">
        <span id="main-time-duration" style="font-size:13px;opacity:.8;min-width:35px;">0:00</span>
      </div>
    </div>
  </div>
  <div class="song-player-lyrics" style="width:200px;background:<?= $mp['panel'] ?>;<?= $mpBorder ?>border-radius:8px;padding:10px;overflow:hidden;position:relative;flex-shrink:0;margin-left:-8px;">
    <div id="main-lyrics" style="font-size:14px;line-height:1.8;opacity:.9;text-align:center;position:absolute;top:50%;left:0;right:0;transform:translateY(-50%);color:<?= $mp['fg'] ?>;white-space:pre-line;">
      <?= $mpStripped ? e($mpStripped) : '暂无歌词' ?>
    </div>
  </div>
  <audio id="main-audio" src="<?= e($mpAudio) ?>" preload="<?= $mpAutoplay ? 'metadata' : 'none' ?>"></audio>
  <?php if ($mpLyrics): ?><pre id="main-lyrics-src" hidden><?= e($mpLyrics) ?></pre><?php endif; ?>
</div>
