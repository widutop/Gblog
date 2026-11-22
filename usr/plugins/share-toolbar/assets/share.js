/* 分享工具条前端逻辑（依赖 qrcode.min.js 提供 window.QRCode）
   定位：悬浮在「右侧栏的右侧」5px —— 以内容容器右缘为基准，
   无论页面是否渲染右侧栏，位置都相同（无侧栏时内容列右缘 = 容器右缘）。
   面板向右展开（不遮挡左侧正文）；右侧空间不足时翻到左侧（侧栏区，不压正文）。 */
(function () {
  if (window.__gbShareInit) return;
  window.__gbShareInit = true;

  var box = document.querySelector('[data-gb-share="1"]');
  if (!box) return;

  var GAP = 5;                       // 与右侧栏的间距
  var panel   = box.querySelector('[data-gb-share-panel]');
  var toggle  = box.querySelector('[data-gb-share-toggle]');
  var slotShare = box.querySelector('[data-gb-share-slot="share"]');
  var slotQr  = box.querySelector('[data-gb-share-slot="qr"]');
  var qrBtn   = box.querySelector('[data-gb-share-qrbtn]');
  var qrPop   = box.querySelector('[data-gb-share-qrpop]');
  var qrWrap  = box.querySelector('[data-gb-share-qrwrap]');
  var qrEl    = box.querySelector('[data-gb-share-qr]');
  var favBtn  = box.querySelector('[data-gb-share-fav]');
  var topBtn  = box.querySelector('[data-gb-share-top]');
  var copyBtn = box.querySelector('[data-gb-share-copy]');
  var toastEl = box.querySelector('[data-gb-share-toast]');

  /* ---------- 当前页信息 ---------- */
  function pageUrl() {
    var c = document.querySelector('link[rel="canonical"]');
    var u = c && c.getAttribute('href') ? c.getAttribute('href') : '';
    if (!u) u = location.href;
    return u.split('#')[0];
  }
  function pageTitle() { return document.title || ''; }
  function pagePic() {
    var og = document.querySelector('meta[property="og:image"]');
    return og ? og.getAttribute('content') : '';
  }

  /* ---------- 各平台分享意图地址 ---------- */
  function shareUrl(p) {
    var u  = encodeURIComponent(pageUrl());
    var t  = encodeURIComponent(pageTitle());
    var pic = encodeURIComponent(pagePic());
    switch (p) {
      case 'weibo':    return 'https://service.weibo.com/share/share.php?url=' + u + '&title=' + t + '&pic=' + pic;
      case 'qqzone':   return 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' + u + '&title=' + t + '&summary=' + t + '&pics=' + pic;
      case 'qqfriend': return 'https://connect.qq.com/widget/shareqq/index.html?url=' + u + '&title=' + t + '&summary=' + t + '&pics=' + pic;
      case 'douban':   return 'https://www.douban.com/recommend/?url=' + u + '&title=' + t;
      case 'facebook': return 'https://www.facebook.com/sharer/sharer.php?u=' + u;
      case 'twitter':  return 'https://twitter.com/intent/tweet?url=' + u + '&text=' + t;
      case 'linkedin': return 'https://www.linkedin.com/sharing/share-offsite/?url=' + u;
      default:         return '';
    }
  }

  function popup(url) {
    if (!url) return;
    var w = 640, h = 540;
    var left = (screen.width - w) / 2, top = (screen.height - h) / 2;
    window.open(url, '_blank',
      'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
      ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1');
  }

  /* ---------- 定位：贴右侧栏右缘 5px ----------
     注意：必须用 clientWidth（不含经典滚动条）而不是 innerWidth ——
     position:fixed 的 right 是相对「不含滚动条」的布局视口计算的，
     用 innerWidth 会差出一个滚动条宽度，导致工具条被推左压住侧栏。 */
  function layoutWidth() {
    return document.documentElement.clientWidth || window.innerWidth || 0;
  }
  function anchorEl() {
    // 有右侧栏 → 以栏右缘为基准；无侧栏 → 以内容列右缘为基准（两者在版式上同位）
    return document.querySelector('.site-main .sidebar, .site-main .agg-sidebar')
        || document.querySelector('.site-main .content-col')
        || document.querySelector('.site-main')
        || document.querySelector('.container');
  }
  function computePosition() {
    var el = anchorEl();
    var w = box.offsetWidth || 46;
    var vw = layoutWidth();
    var anchorRight = el ? el.getBoundingClientRect().right : vw;

    // 需要「工具条宽 + 5px 间隙」才放得下；
    // 放不下时给页面右侧预留一条空间（padding-right），把容器推窄腾出位置。
    var need = w + GAP;
    var reserve = 0;
    if (vw - anchorRight < need) {
      reserve = need - (vw - anchorRight);
    }
    var html = document.documentElement;
    html.style.setProperty('--gb-share-reserve', Math.ceil(reserve) + 'px');
    html.classList.toggle('gb-share-reserve', reserve > 0);
    // 预留生效后容器被推窄，右缘随之变化 → 重新量一次真实右缘
    if (reserve > 0 && el) {
      anchorRight = el.getBoundingClientRect().right;
    }

    // 期望：工具条左缘 = 侧栏右缘 + 5px
    var boxRight = anchorRight + GAP + w;
    var right = vw - boxRight;
    if (right < 0) right = 0;             // 仍不足 → 贴布局视口右缘（已被预留推开，不压内容）
    box.style.setProperty('--gb-share-right', Math.round(right) + 'px');
    return boxRight;
  }
  var boxRightEdge = 0;

  /* ---------- 面板展开方向：右侧够就向右，否则翻左 ---------- */
  function panelWidth(panelEl) {
    if (!panelEl) return 252;
    var w = panelEl.offsetWidth;
    return w > 0 ? w : (layoutWidth() <= 768 ? 220 : 252);
  }
  function applyFlip(panelEl) {
    if (!panelEl) return;
    var avail = layoutWidth() - (boxRightEdge + GAP) - 8;
    var flip = avail < panelWidth(panelEl);
    panelEl.classList.toggle('gb-share-flip', flip);
    // 缝隙桥接跟随翻转方向（否则鼠标从按钮移向面板会误关）
    var slot = panelEl.parentElement;
    if (slot) slot.classList.toggle('gb-share-flip-slot', flip);
    // 轻提示跟随当前展开侧，避免溢出视口
    box.classList.toggle('gb-share-flip-panel', flip);
  }

  /* ---------- 二维码（懒生成，首次打开时） ---------- */
  var qrDone = false;
  function renderQr() {
    if (qrDone || !qrEl || !window.QRCode) return;
    qrDone = true;
    try {
      qrEl.innerHTML = '';
      new window.QRCode(qrEl, {
        text: pageUrl(),
        width: 128,
        height: 128,
        colorDark: '#111',
        colorLight: '#fff',
        correctLevel: window.QRCode.CorrectLevel.M
      });
    } catch (e) { qrEl.innerHTML = ''; }
  }

  /* ---------- 面板开合 ---------- */
  function openPanel() {
    if (!panel) return;
    panel.hidden = false;
    box.classList.add('gb-share-open');
    applyFlip(panel);
  }
  function closePanel() {
    if (!panel) return;
    panel.hidden = true;
    box.classList.remove('gb-share-open');
  }
  function togglePanel() {
    if (panel && panel.hidden) openPanel(); else closePanel();
  }
  function openQr() {
    if (!qrPop) return;
    qrPop.hidden = false;
    renderQr();
    applyFlip(qrPop);
  }
  function closeQr() {
    if (!qrPop) return;
    qrPop.hidden = true;
  }
  function toggleQr() {
    if (qrPop && qrPop.hidden) openQr(); else closeQr();
  }

  /* 悬浮整个 slot（按钮 + 面板 + 间隙桥接）即保持展开 */
  function bindHover(slot, open, close) {
    if (!slot) return;
    slot.addEventListener('mouseenter', open);
    slot.addEventListener('mouseleave', function () {
      setTimeout(function () {
        if (slot.matches(':hover')) return;
        close();
      }, 160);
    });
  }
  bindHover(slotShare, openPanel, closePanel);
  bindHover(slotQr, openQr, closeQr);

  /* 点击也可切换（触屏友好） */
  if (toggle) {
    toggle.addEventListener('click', function (e) { e.stopPropagation(); togglePanel(); });
  }
  if (qrBtn) {
    qrBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleQr(); });
  }

  /* 点击空白处收起 */
  document.addEventListener('click', function (e) {
    if (!box.contains(e.target)) { closePanel(); closeQr(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closePanel(); closeQr(); }
  });

  /* ---------- 平台点击 ---------- */
  box.querySelectorAll('[data-share]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var p = btn.getAttribute('data-share');
      if (btn.getAttribute('data-qr') === '1') {
        // 微信等无网页分享接口：引导扫码（打开独立二维码浮层）
        openQr();
        if (qrWrap) {
          qrWrap.classList.add('gb-share-qr-flash');
          setTimeout(function () { qrWrap.classList.remove('gb-share-qr-flash'); }, 1200);
        }
        toast('微信请扫码分享');
        return;
      }
      popup(shareUrl(p));
    });
  });

  /* ---------- 返回顶部 ---------- */
  if (topBtn) {
    topBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- 轻提示 ---------- */
  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.hidden = true; }, 1800);
  }

  /* ---------- 收藏当前页到浏览器收藏夹 ---------- */
  if (favBtn) {
    favBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var url = pageUrl(), title = pageTitle();
      // IE / 旧内核：可直接写入收藏夹
      try {
        if (window.external && typeof window.external.AddFavorite === 'function') {
          window.external.AddFavorite(url, title);
          toast('已加入收藏夹');
          return;
        }
      } catch (err) { /* 继续走降级方案 */ }
      // 现代浏览器不开放写收藏夹 API：给出快捷键引导
      var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent);
      toast('请按 ' + (isMac ? '⌘ + D' : 'Ctrl + D') + ' 收藏本页');
    });
  }

  /* ---------- 复制链接 ---------- */
  if (copyBtn) {
    copyBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var text = pageUrl();
      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); toast('已复制链接'); }
        catch (e) { toast('复制失败，请手动复制'); }
        document.body.removeChild(ta);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { toast('已复制链接'); }, fallback);
      } else {
        fallback();
      }
    });
  }

  /* ---------- 滚动显形（箭头在列末位，滚动后出现） ---------- */
  var threshold = parseInt(box.getAttribute('data-scroll') || '300', 10);
  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var show = threshold <= 0 || window.pageYOffset > threshold;
      box.classList.toggle('gb-share-show', show);
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- 尺寸变化：重算位置与展开方向 ---------- */
  function relayout() {
    boxRightEdge = computePosition();
    if (panel && !panel.hidden) applyFlip(panel);
    if (qrPop && !qrPop.hidden) applyFlip(qrPop);
  }
  var rt = null;
  window.addEventListener('resize', function () {
    clearTimeout(rt);
    rt = setTimeout(relayout, 120);
    onScroll();
  });
  window.addEventListener('load', relayout);

  relayout();
  onScroll();
})();
