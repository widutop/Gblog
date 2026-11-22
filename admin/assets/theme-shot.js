/* 主题缩略图悬浮预览：在卡片旁显示放大图，键盘 focus 同样可用。 */
(function () {
  'use strict';

  var MAX_W = 560;
  var RATIO = 16 / 10;

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); return; }
    document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var shots = document.querySelectorAll('img.theme-shot');
    if (!shots.length) { return; }

    var box = document.createElement('div');
    box.className = 'theme-shot-preview';
    var img = document.createElement('img');
    img.alt = '';
    box.appendChild(img);
    document.body.appendChild(box);

    function place(target) {
      var r = target.getBoundingClientRect();
      var w = Math.min(MAX_W, window.innerWidth - 24);
      var h = w / RATIO;
      var left = r.right + 14;
      if (left + w > window.innerWidth - 12) { left = Math.max(12, r.left - w - 14); }
      box.style.width = w + 'px';
      box.style.left = left + 'px';
      box.style.top = Math.min(Math.max(12, r.top - 8), window.innerHeight - h - 12) + 'px';
    }

    function show(target) {
      place(target);
      img.src = target.getAttribute('data-shot-full') || target.src;
      box.classList.add('is-on');
    }

    function hide() { box.classList.remove('is-on'); }

    Array.prototype.forEach.call(shots, function (el) {
      el.addEventListener('mouseenter', function () { show(el); });
      el.addEventListener('mouseleave', hide);
      el.addEventListener('focus', function () { show(el); });
      el.addEventListener('blur', hide);
    });

    window.addEventListener('scroll', hide, true);
  });
})();
