(function () {
  var wrap = document.querySelector('.gb-music-reel');
  if (!wrap) return;
  var track = wrap.querySelector('.gb-music-reel-track');
  if (!track) return;

  function icon(name) {
    var I = window.GBLOG_ICON || {};
    return I[name] || '';
  }

  // 卡片任意处（除歌名链接）只切歌不进详情；歌名链接保持原生跳转
  wrap.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    if (t.closest('.gb-music-reel-title')) return;
    var item = t.closest('.gb-music-reel-item');
    if (!item) return;
    var hit = item.querySelector('.gb-music-reel-play');
    if (!hit) return;
    ev.preventDefault();
    if (!window.GBPlayerPlay) return;
    window.GBPlayerPlay({
      id:    hit.getAttribute('data-song-id'),
      audio: hit.getAttribute('data-audio'),
      title: hit.getAttribute('data-song-title')
    });
  });

  document.addEventListener('gb:player-state', function (ev) {
    var d = ev.detail || {};
    var id = d.id ? String(d.id) : '';
    var list = wrap.querySelectorAll('.gb-music-reel-play');
    for (var i = 0; i < list.length; i++) {
      var b = list[i];
      var on = id !== '' && b.getAttribute('data-song-id') === id && !!d.playing;
      var state = on ? 'playing' : 'idle';
      if (b.getAttribute('data-state') === state) continue;
      var title = b.getAttribute('data-song-title') || '';
      b.setAttribute('data-state', state);
      b.classList.toggle('is-playing', on);
      b.innerHTML = on ? icon('pause') : icon('play');
      b.setAttribute('aria-label', (on ? '暂停 ' : '播放 ') + title);
    }
  });

  if (track.children.length < 6) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var GAP = 5, SLIDE = 450, HOLD = 2800;
  var timer = null, busy = false;

  function unit() {
    var first = track.firstElementChild;
    return first ? first.getBoundingClientRect().width + GAP : 0;
  }

  function reset() {
    track.style.transition = 'none';
    track.style.transform = 'translateX(0)';
    void track.offsetWidth;
    track.style.transition = '';
  }

  function step() {
    if (busy) return;
    var u = unit();
    if (!u) return;
    busy = true;
    track.style.transform = 'translateX(-' + u + 'px)';
    window.setTimeout(function () {
      track.style.transition = 'none';
      track.appendChild(track.firstElementChild);
      track.style.transform = 'translateX(0)';
      void track.offsetWidth;
      track.style.transition = '';
      busy = false;
    }, SLIDE + 40);
  }

  function start() { if (!timer) timer = window.setInterval(step, HOLD); }
  function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

  wrap.addEventListener('mouseenter', stop);
  wrap.addEventListener('mouseleave', start);
  wrap.addEventListener('focusin', stop);
  wrap.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
  window.addEventListener('resize', function () { stop(); reset(); start(); });
  start();
})();
