/* 列表 tab / 翻页：拉取目标页并只替换内容区域，不整页重载。 */
(function () {
  'use strict';
  if (!window.fetch || !window.DOMParser || !history.pushState) { return; }

  var SEL  = '[data-gb-region]';
  var busy = false;

  function region() { return document.querySelector(SEL); }

  function syncBody(doc) {
    var b = doc.body;
    if (!b || !document.body) { return; }
    ['data-site', 'data-site-type', 'data-view', 'data-skin', 'data-home-mode', 'data-layout'].forEach(function (k) {
      if (b.hasAttribute(k)) { document.body.setAttribute(k, b.getAttribute(k)); }
      else { document.body.removeAttribute(k); }
    });
  }

  function paint(html, opts) {
    var doc  = new DOMParser().parseFromString(html, 'text/html');
    var next = doc.querySelector(SEL);
    var cur  = region();
    if (!next || !cur) { throw new Error('no-region'); }
    cur.innerHTML = next.innerHTML;
    syncBody(doc);
    if (doc.title) { document.title = doc.title; }
    if (opts.push) { history.pushState({ gbRegion: 1 }, '', opts.page); }
    var top = cur.getBoundingClientRect().top;
    if (top < 60 || top > window.innerHeight - 120) {
      cur.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function load(url, opts) {
    if (busy) { return; }
    busy = true;
    var cur = region();
    if (cur) { cur.classList.add('is-loading'); cur.setAttribute('aria-busy', 'true'); }
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('http-' + r.status); }
        return r.text();
      })
      .then(function (html) { paint(html, opts); })
      .catch(function () { location.href = url; })
      .then(function () {
        busy = false;
        var c = region();
        if (c) { c.classList.remove('is-loading'); c.removeAttribute('aria-busy'); }
      });
  }

  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
    var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
    if (!a || a.target === '_blank') { return; }
    if (!a.closest(SEL)) { return; }
    var isTab  = a.classList.contains('gb-list-tab');
    var isPage = a.classList.contains('gb-page-btn');
    if (!isTab && !isPage) { return; }
    if (a.hasAttribute('aria-current') || a.classList.contains('is-active')) { ev.preventDefault(); return; }
    ev.preventDefault();
    var u = new URL(a.href, location.href);
    var to = u.pathname + u.search;
    load(to, { push: true, page: to });
  });

  window.addEventListener('popstate', function () {
    if (!region()) { location.reload(); return; }
    load(location.pathname + location.search, { push: false });
  });
})();
