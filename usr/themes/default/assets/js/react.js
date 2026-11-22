document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.react-btn');
    if (!btn) return;
    e.preventDefault();
    var pid = btn.getAttribute('data-post-id');
    var type = btn.getAttribute('data-type');
    if (!pid) return;

    var fd = new FormData();
    fd.append('post_id', pid);
    fd.append('type', type);
    fd.append('csrf_token', window.GBLOG_CSRF || '');

    fetch('index.php?do=like', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) {
          if (data && data.need_login) {
            var base = location.pathname + location.search;
            base = base.replace(/[?&]login=1/g, '');
            location.href = base + (base.indexOf('?') === -1 ? '?' : '&') + 'login=1';
            return;
          }
          alert((data && data.msg) ? data.msg : '操作失败');
          return;
        }
        btn.classList.toggle('active', data.state);
        var span = btn.querySelector('.rc');
        if (span) span.textContent = data.count;
        if (type === 'favorite') {
          var ico = btn.querySelector('.react-ico');
          if (ico) ico.innerHTML = data.state ? window.GBLOG_ICON.starFill : window.GBLOG_ICON.star;
        }
      })
      .catch(function () { alert('网络错误，请重试'); });
  });
});
