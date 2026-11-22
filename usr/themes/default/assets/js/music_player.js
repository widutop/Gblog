(function () {
  'use strict';

  var audio = document.getElementById('main-audio');
  if (!audio) return;

  var btn         = document.getElementById('main-player-btn');
  var prevBtn     = document.getElementById('main-player-prev');
  var nextBtn     = document.getElementById('main-player-next');
  var progress    = document.getElementById('main-progress');
  var timeCurrent = document.getElementById('main-time-current');
  var timeDuration = document.getElementById('main-time-duration');
  var titleEl     = document.getElementById('main-player-title');
  var subEl       = document.getElementById('main-player-subtitle');
  var lyricsBox   = document.getElementById('main-lyrics');

  // 列表会被局部刷新整块替换（音乐页切分类/翻页），所以每次用前重新收集，不能缓存节点
  var items = [];
  function refresh() { items = Array.prototype.slice.call(document.querySelectorAll('.main-player-item')); }
  refresh();
  var currentIdx = -1;
  var loaded = false;
  var lrcLines = [];

  function formatTime(s) {
    if (isNaN(s)) return '0:00';
    var m = Math.floor(s / 60);
    var sec = Math.floor(s % 60);
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  function parseLRC(text) {
    var out = [];
    (text || '').split('\n').forEach(function (ln) {
      ln = ln.trim();
      if (!ln) return;
      var m = ln.match(/^\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]\s*(.*)$/);
      if (m) {
        var t = parseInt(m[1], 10) * 60 + parseInt(m[2], 10) +
                (m[3] ? parseInt(m[3], 10) / (m[3].length === 2 ? 100 : 1000) : 0);
        out.push({ time: t, text: m[4] });
      }
    });
    return out;
  }

  var lrcSrc = document.getElementById('main-lyrics-src');
  lrcLines = parseLRC(lrcSrc ? lrcSrc.textContent : '');

  function updateLyrics() {
    if (!lyricsBox || !lrcLines.length) return;
    var t = audio.currentTime;
    var idx = -1;
    for (var i = 0; i < lrcLines.length; i++) {
      if (lrcLines[i].time <= t) idx = i; else break;
    }
    if (idx < 0) return;
    while (lyricsBox.firstChild) lyricsBox.removeChild(lyricsBox.firstChild);
    for (var j = idx - 2; j <= idx + 2; j++) {
      if (j < 0 || j >= lrcLines.length) continue;
      var div = document.createElement('div');
      div.textContent = lrcLines[j].text;   // 歌词来自自由文本字段，必须 textContent 防 DOM XSS
      div.style.opacity = (j === idx) ? '1' : (j < idx ? '.4' : '.6');
      if (j === idx) div.style.fontWeight = '600';
      lyricsBox.appendChild(div);
    }
  }

  var audioContext = null, analyser = null, dataArray = null;
  function initAudioAnalyser() {
    if (audioContext) return;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    audioContext = new AC();
    var source = audioContext.createMediaElementSource(audio);
    analyser = audioContext.createAnalyser();
    analyser.fftSize = 256;
    source.connect(analyser);
    analyser.connect(audioContext.destination);
    dataArray = new Uint8Array(analyser.frequencyBinCount);
  }
  function updateWaveform() {
    if (!analyser) return;
    analyser.getByteFrequencyData(dataArray);
    var bars = document.querySelectorAll('#waveform div');
    bars.forEach(function (bar, i) {
      var v = dataArray[i] / 255;
      bar.style.height = Math.max(20, v * 100) + '%';
    });
    requestAnimationFrame(updateWaveform);
  }

  // 播放状态广播给页面上其它播放入口（音乐页轮播）
  function broadcast(playing) {
    var ev;
    try {
      ev = new CustomEvent('gb:player-state', { detail: { id: audio.getAttribute('data-cur-id') || '', playing: playing } });
    } catch (e) { return; }
    document.dispatchEvent(ev);
  }

  if (btn) {
    btn.addEventListener('click', function () {
      if (audio.paused) {
        refresh();
        if (!loaded && items.length) { playItem(0); return; }
        audio.play();
      } else {
        audio.pause();
      }
    });
  }
  audio.addEventListener('loadedmetadata', function () {
    progress.max = audio.duration;
    timeDuration.textContent = formatTime(audio.duration);
  });
  audio.addEventListener('timeupdate', function () {
    progress.value = audio.currentTime;
    timeCurrent.textContent = formatTime(audio.currentTime);
    updateLyrics();
  });
  if (progress) {
    progress.addEventListener('input', function () { audio.currentTime = progress.value; });
  }
  audio.addEventListener('play', function () {
    if (btn) btn.innerHTML = window.GBLOG_ICON.pause;
    initAudioAnalyser();
    updateWaveform();
    broadcast(true);
  });
  audio.addEventListener('pause', function () {
    if (btn) btn.innerHTML = window.GBLOG_ICON.play;
    broadcast(false);
  });
  audio.addEventListener('ended', function () {
    refresh();
    if (items.length) playItem(currentIdx + 1);
  });

  function loadSong(opts) {
    var url = opts.url || '';
    if (!url) return false;
    var id  = opts.id ? String(opts.id) : '';
    var li  = opts.li || null;
    var idx = (typeof opts.idx === 'number') ? opts.idx : -1;

    loaded = true;
    audio.src = url;
    audio.setAttribute('data-cur-id', id);
    if (titleEl) titleEl.textContent = opts.title || '';
    if (subEl) subEl.textContent = (idx >= 0 && items.length)
      ? '正在播放 ' + (idx + 1) + '/' + items.length
      : '正在播放';
    if (lyricsBox) lyricsBox.textContent = '歌词加载中...';
    lrcLines = [];
    refresh();
    items.forEach(function (x) { x.classList.remove('playing'); });
    if (li) li.classList.add('playing');
    audio.play().catch(function () {});
    if (id) {
      fetch('index.php?do=song_lyrics&id=' + encodeURIComponent(id))
        .then(function (r) { return r.text(); })
        .then(function (txt) {
          lrcLines = parseLRC(txt);
          if (!lrcLines.length && lyricsBox) lyricsBox.textContent = '暂无歌词';
          var plain = txt.replace(/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/g, '').trim();
          var lt = document.getElementById('lyrics-text');
          if (lt) lt.textContent = plain || '暂无歌词';
        })
        .catch(function () { if (lyricsBox) lyricsBox.textContent = '歌词加载失败'; });
    }
    return true;
  }

  function playItem(idx) {
    refresh();
    if (idx < 0 || idx >= items.length) return;
    currentIdx = idx;
    var li = items[idx];
    loadSong({
      url:   li.getAttribute('data-audio'),
      id:    li.getAttribute('data-id'),
      title: li.getAttribute('data-title'),
      li:    li,
      idx:   idx
    });
  }

  // 站内其它播放入口（轮播）调用：只换曲目，不依赖列表位置
  window.GBPlayerPlay = function (song) {
    if (!song) return false;
    var id = song.id ? String(song.id) : '';
    if (id && audio.getAttribute('data-cur-id') === id && audio.getAttribute('src')) {
      if (audio.paused) audio.play().catch(function () {}); else audio.pause();
      return true;
    }
    refresh();
    var li = null, idx = -1;
    for (var i = 0; i < items.length; i++) {
      if (id && items[i].getAttribute('data-id') === id) { li = items[i]; idx = i; break; }
    }
    currentIdx = idx;
    var url = song.audio || (li ? li.getAttribute('data-audio') : '');
    if (!url) return false;
    if (id) fetch('index.php?do=song_play&id=' + encodeURIComponent(id)).catch(function () {});
    return loadSong({
      url:   url,
      id:    id,
      title: song.title || (li ? li.getAttribute('data-title') : ''),
      li:    li,
      idx:   idx
    });
  };

  // 委托到 document：列表被替换后新按钮无需重新绑定
  document.addEventListener('click', function (ev) {
    var li = ev.target && ev.target.closest ? ev.target.closest('.main-player-item') : null;
    if (!li) return;
    refresh();
    var idx = items.indexOf(li);
    if (idx < 0) return;
    var pid = li.getAttribute('data-id');
    if (pid) fetch('index.php?do=song_play&id=' + encodeURIComponent(pid)).catch(function () {});
    playItem(idx);
  });
  if (prevBtn) prevBtn.addEventListener('click', function () { playItem(currentIdx - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { playItem(currentIdx + 1); });

  if (items.length) {
    var playParam = new URLSearchParams(location.search).get('play');
    var startIdx = 0;
    if (playParam) {
      for (var k = 0; k < items.length; k++) {
        if (parseInt(items[k].getAttribute('data-id'), 10) === parseInt(playParam, 10)) { startIdx = k; break; }
      }
    }
    playItem(startIdx);
  } else {
// 自动播放标记挂在 .music-shared-player 上
    var playerEl = document.querySelector('.music-shared-player');
    var autoplay = audio.getAttribute('data-autoplay') === '1' ||
                   (playerEl && playerEl.getAttribute('data-autoplay') === '1');
    if (autoplay) audio.play().catch(function () {});
  }
})();
