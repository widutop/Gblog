<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * Template Name: 聚合首页侧栏
 * Context: $site
 */

$aggPlayerSongs = get_songs_random();
$aggHotPosts    = get_hot_posts('all', [], 10);
$aggHotSoft     = []; // 软件栏目热门 5 条：按 type 动态解析栏目，禁止按 slug 硬编码（新装 slug 与主站可能不同）
foreach (get_all_sites() as $__aggSite) {
    if ((string)$__aggSite['type'] === 'software') {
        $aggHotSoft = get_hot_posts('specific', [(int)$__aggSite['id']], 5);
        break;
    }
}
$aggComments    = get_hot_comments(15, 0, 'hot');
$aggPlayerFirst = isset($aggPlayerSongs[0]) ? $aggPlayerSongs[0] : null;

if (!function_exists('gblog_music_player_palette')) {
    require_once GBLOG_ROOT . '/core/render/music_theme.php';
}
$aggMp = gblog_music_player_palette('custom', gblog_theme_primary_color());
?>
<aside class="sidebar agg-sidebar">
  <div class="widget widget-notice">
    <h3>最新公告</h3>
    <div class="agg-notice-body">
      <?php
      $aggNotice = get_setting('home_notice', '');
      if (trim((string)$aggNotice) !== ''):
      ?>
        <div class="agg-notice-content" style="font-size:14px;line-height:1.7;"><?= $aggNotice /* 后台「首页公告」，管理员撰写，允许 HTML，与广告位同策略 */ ?></div>
      <?php else: ?>
        <p style="font-size:14px;color:var(--text-muted);margin:0;">暂无公告。</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="widget widget-agg-player">
    <div class="agg-rank-head" id="agg-rank-head" role="button" tabindex="0" aria-expanded="false" aria-controls="agg-rank-body">
      <span class="agg-rank-head-label"><?= gblog_icon('music') ?> 音乐播放器</span>
      <span class="agg-rank-head-ctrl">
        <button type="button" class="agg-head-play" id="agg-head-play" aria-label="播放/暂停" title="播放/暂停"><?= gblog_icon('play', 12) ?></button>
        <span class="agg-rank-caret" id="agg-rank-caret">▾</span>
      </span>
    </div>
    <div class="agg-rank-body" id="agg-rank-body" style="display:none;">
      <div class="agg-player" style="background:linear-gradient(135deg,<?= $aggMp['bg'] ?>,<?= $aggMp['bg2'] ?>);color:<?= $aggMp['fg'] ?>;">
        <div class="agg-player-top">
          <div class="agg-player-cover" id="agg-player-cover"><?php if ($aggPlayerFirst && !empty($aggPlayerFirst['cover_url'])): ?><img src="<?= e($aggPlayerFirst['cover_url']) ?>" alt=""><?php else: ?><?= gblog_icon('music') ?><?php endif; ?>
            <button type="button" class="agg-play-toggle" aria-label="播放"><?= gblog_icon('play', 14) ?></button>
          </div>
          <div class="agg-player-info">
            <div class="agg-player-title" id="agg-player-title">顺序播放</div>
            <div class="agg-player-artist" id="agg-player-artist">音乐栏目</div>
          </div>
        </div>
        <div class="agg-player-bar">
          <input type="range" class="agg-seek" min="0" max="100" value="0" style="accent-color:<?= $aggMp['chip'] ?>;">
          <span class="agg-time">0:00</span>
          <button type="button" class="agg-next-btn" id="agg-next-btn" aria-label="下一首" title="下一首" style="border:none;background:<?= $aggMp['chip'] ?>;color:<?= $aggMp['chipFg'] ?>;border-radius:6px;width:26px;height:24px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><?= gblog_icon('next', 13) ?></button>
        </div>
        <audio id="agg-audio" preload="none" style="display:none;"></audio>
      </div>
      <?php if ($aggPlayerSongs): ?>
        <ul class="agg-rank-list" id="agg-rank-list">
          <?php foreach ($aggPlayerSongs as $hs): ?>
            <li data-id="<?= (int)$hs['id'] ?>" data-title="<?= e($hs['title']) ?>" data-artist="<?= e(($hs['lyricist'] ?? '') ?: ($hs['artist'] ?? '')) ?>" data-audio="<?= e($hs['audio_url']) ?>" data-cover="<?= e($hs['cover_url']) ?>">
              <span class="agg-rank-name"><?= e($hs['title']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="empty" style="font-size:14px;color:var(--text-muted);">暂无音乐</p>
      <?php endif; ?>
      <div class="agg-statusbar" id="agg-statusbar">
        <span class="agg-status-icon" id="agg-status-icon"><?= gblog_icon('pause', 12) ?></span>
        <span class="agg-status-text" id="agg-status-text">未在播放</span>
      </div>
    </div>
  </div>

  <div class="widget">
    <h3><?= gblog_icon('fire') ?> 热门文章</h3>
    <?php if ($aggHotPosts): ?>
      <ul class="player-list">
        <?php foreach ($aggHotPosts as $i => $hp): ?>
          <li>
            <span class="sb-no"><?= $i+1 ?></span>
            <a class="sb-title" href="index.php?do=post&id=<?= (int)$hp['id'] ?>"><?= e($hp['title']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty">暂无文章</p>
    <?php endif; ?>
  </div>

  <div class="widget">
    <h3><?= gblog_icon('package') ?> 热门软件</h3>
    <?php if ($aggHotSoft): ?>
      <ul class="player-list">
        <?php foreach ($aggHotSoft as $i => $hs): ?>
          <li>
            <span class="sb-no"><?= $i+1 ?></span>
            <a class="sb-title" href="index.php?do=post&id=<?= (int)$hs['id'] ?>"><?= e($hs['title']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty">暂无软件</p>
    <?php endif; ?>
  </div>

  <div class="widget widget-hot-comments">
    <h3><?= gblog_icon('comment') ?> 热门评论</h3>
    <?php if ($aggComments): ?>
      <ul class="hot-comments">
        <?php foreach ($aggComments as $ac):
            $aggCUrl = (($ac['target_type'] ?? '') === 'song' && (int)($ac['song_id'] ?? 0) > 0)
                ? gblog_url('song', ['id' => (int)$ac['song_id']])
                : 'index.php?do=post&id=' . (int)($ac['target_id'] ?? 0);
        ?>
          <li>
            <a href="<?= e($aggCUrl) ?>#comments">
              <b><?= e($ac['name']) ?></b>：<?= e(excerpt($ac['content'], 36)) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty">暂无评论</p>
    <?php endif; ?>
  </div>
</aside>
<script>
(function () {
  var audio   = document.getElementById('agg-audio');
  var toggle  = document.querySelector('.agg-play-toggle');
  var nextBtn = document.getElementById('agg-next-btn');
  var seek    = document.querySelector('.agg-seek');
  var timeEl  = document.querySelector('.agg-time');
  var titleEl = document.getElementById('agg-player-title');
  var artistEl= document.getElementById('agg-player-artist');
  var coverEl = document.getElementById('agg-player-cover');
  var currentSongId = null;
  var rankHead = document.getElementById('agg-rank-head');
  var rankBody = document.getElementById('agg-rank-body');
  var rankCaret= document.getElementById('agg-rank-caret');
  var headPlay = document.getElementById('agg-head-play');
  var statusIcon = document.getElementById('agg-status-icon');
  var statusText = document.getElementById('agg-status-text');
  var rankList  = document.getElementById('agg-rank-list');

  function fmt(s) { if (!isFinite(s)) return '0:00'; s = Math.floor(s || 0); var m = Math.floor(s / 60); var ss = s % 60; return m + ':' + (ss < 10 ? '0' + ss : ss); }
  function syncUI() {
    if (!audio || !seek) return;
    if (audio.duration) { seek.max = audio.duration; seek.value = audio.currentTime; }
    if (timeEl) timeEl.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration);
  }
  function setPlaying(on) {
    if (toggle)   toggle.innerHTML   = on ? window.GBLOG_ICON.pause : window.GBLOG_ICON.play;
    if (headPlay) headPlay.innerHTML = on ? window.GBLOG_ICON.pause : window.GBLOG_ICON.play;
  }
  function curTitle() { return titleEl ? titleEl.textContent : ''; }
  function updateStatus(state) {
    if (!statusText) return;
    if (state === 'playing')      { statusText.textContent = '正在播放：' + curTitle(); if (statusIcon) statusIcon.innerHTML = window.GBLOG_ICON.play; }
    else if (state === 'paused')  { statusText.textContent = '已暂停';                   if (statusIcon) statusIcon.innerHTML = window.GBLOG_ICON.pause; }
    else                          { statusText.textContent = '未在播放';                 if (statusIcon) statusIcon.innerHTML = window.GBLOG_ICON.pause; }
  }
  function highlightActive(id) {
    if (!rankList) return;
    rankList.querySelectorAll('li').forEach(function (li) {
      li.classList.toggle('active', id !== null && parseInt(li.getAttribute('data-id'), 10) === id);
    });
  }
  function rebuildCover(coverUrl) {
    if (!coverEl) return;
    while (coverEl.firstChild) coverEl.removeChild(coverEl.firstChild);
    if (coverUrl) {
      var img = document.createElement('img');
      img.setAttribute('src', coverUrl);
      coverEl.appendChild(img);
    }
    var nt = document.createElement('button');
    nt.type = 'button';
    nt.className = 'agg-play-toggle';
    nt.setAttribute('aria-label', '播放');
    nt.innerHTML = window.GBLOG_ICON.play;
    coverEl.appendChild(nt);
    nt.addEventListener('click', onToggle);
    toggle = nt;
  }
  var listItems = rankList ? Array.prototype.slice.call(rankList.querySelectorAll('li')) : [];
  var currentIdx = -1;
  function playLi(li) {
    if (!audio || !li) return;
    var src = li.getAttribute('data-audio');
    if (!src) return;
    currentSongId = parseInt(li.getAttribute('data-id'), 10) || null;
    audio.src = src;
    highlightActive(currentSongId);
    if (seek) seek.value = 0;
    if (timeEl) timeEl.textContent = '0:00 / 0:00';
    if (titleEl)  titleEl.textContent  = li.getAttribute('data-title');
    if (artistEl) artistEl.textContent = li.getAttribute('data-artist');
    rebuildCover(li.getAttribute('data-cover') || '');
    audio.play().then(function () { setPlaying(true); updateStatus('playing'); }).catch(function () {});
    fetch('index.php?do=song_play&id=' + encodeURIComponent(currentSongId)).catch(function () {});
  }
  function playIndex(idx) {
    if (!listItems.length) return;
    var n = listItems.length;
    currentIdx = ((idx % n) + n) % n;
    playLi(listItems[currentIdx]);
  }
  function onToggle() {
    if (!audio) return;
    if (currentIdx < 0) { playIndex(0); return; }
    if (audio.paused) { audio.play().then(function () { setPlaying(true); updateStatus('playing'); }).catch(function () {}); }
    else { audio.pause(); setPlaying(false); updateStatus('paused'); if (timeEl) timeEl.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration); }
  }
  if (toggle) toggle.addEventListener('click', onToggle);
  if (nextBtn) nextBtn.addEventListener('click', function () { playIndex(currentIdx + 1); });

  if (audio) {
    audio.addEventListener('timeupdate', syncUI);
    audio.addEventListener('loadedmetadata', function () { seek.max = audio.duration; syncUI(); });
    audio.addEventListener('play', function () { setPlaying(true); updateStatus('playing'); });
    audio.addEventListener('pause', function () { setPlaying(false); updateStatus('paused'); });
    audio.addEventListener('ended', function () { setPlaying(false); updateStatus('idle'); if (seek) seek.value = 0; playIndex(currentIdx + 1); });
  }
  if (seek) {
    seek.addEventListener('input', function () { if (audio && audio.duration) audio.currentTime = seek.value; });
    seek.addEventListener('change', function () { if (audio && audio.duration) audio.currentTime = seek.value; });
  }
  listItems.forEach(function (li, idx) {
    li.addEventListener('click', function () { currentIdx = idx; playLi(li); });
  });

  function toggleCollapse(e) {
    if (e && headPlay && headPlay.contains(e.target)) return;
    if (!rankBody) return;
    var open = rankBody.style.display !== 'none';
    rankBody.style.display = open ? 'none' : 'block';
    if (rankCaret) rankCaret.classList.toggle('open', !open);
    if (rankHead) rankHead.setAttribute('aria-expanded', String(!open));
  }
  if (rankHead) {
    rankHead.addEventListener('click', toggleCollapse);
    rankHead.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleCollapse(); }
    });
  }
  if (headPlay) headPlay.addEventListener('click', function (e) { e.stopPropagation(); onToggle(); });
})();
</script>
