/* 音乐列表：切分类 / 翻页只替换 grid 内容并改地址，不整页重载（无刷新、无跳动）。 */
(function () {
  'use strict';

  var grid = document.getElementById('gb-music-grid');
  if (!grid || !window.fetch || !window.DOMParser || !window.history || !history.pushState) { return; }

  var busy = false;

  function parts(href) {
    var u = new URL(href, location.href);
    u.hash = '';
    var page = u.pathname + u.search;
    u.searchParams.set('frag', 'grid');
    return { page: page, frag: u.pathname + u.search };
  }

  function playingId() {
    var el = grid.querySelector('.main-player-item.playing');
    return el ? (el.getAttribute('data-id') || '') : '';
  }

  function paint(html, opts) {
    var next = new DOMParser().parseFromString(html, 'text/html').getElementById('gb-music-grid');
    if (!next) { throw new Error('no-fragment'); }

    // 播放器在 grid 外，不会中断；只把「正在播放」标记接回去
    var keep = playingId();
    grid.innerHTML = next.innerHTML;
    if (keep) {
      var el = grid.querySelector('.main-player-item[data-id="' + keep.replace(/["\\]/g, '') + '"]');
      if (el) { el.classList.add('playing'); }
    }

    if (opts.push) { history.pushState({ gbMusicGrid: 1 }, '', opts.page); }
    // tab 头看不到就平滑带回（翻页必然触发；切分类只在列表变短把 tab 挤出视口时触发）
    var head = grid.querySelector('.gb-music-grid-head');
    var top = (head || grid).getBoundingClientRect().top;
    if (top < 80 || top > window.innerHeight - 80) {
      grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function load(page, frag, opts) {
    if (busy) { return; }
    busy = true;
    grid.classList.add('is-loading');
    grid.setAttribute('aria-busy', 'true');
    fetch(frag, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('http-' + r.status); }
        return r.text();
      })
      .then(function (html) { paint(html, opts); })
      .catch(function () { location.href = page; })
      .then(function () {
        busy = false;
        grid.classList.remove('is-loading');
        grid.removeAttribute('aria-busy');
      });
  }

  grid.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
    if (!a || !grid.contains(a)) { return; }
    var isTab  = a.classList.contains('gb-music-grid-tab');
    var isPage = a.classList.contains('gb-song-page');
    if (!isTab && !isPage) { return; }
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || a.target === '_blank') { return; }
    ev.preventDefault();
    if (a.hasAttribute('aria-current')) { return; }
    var u = parts(a.href);
    load(u.page, u.frag, { push: true, page: u.page });
  });

  // 当前条目打标记，后退/前进回本页时才能区分是不是自己产生的历史
  history.replaceState({ gbMusicGrid: 1 }, '', location.pathname + location.search);

  window.addEventListener('popstate', function (ev) {
    if (!ev.state || !ev.state.gbMusicGrid) { location.reload(); return; }
    var u = parts(location.href);
    load(u.page, u.frag, { push: false, page: u.page });
  });
})();
