<?php if (!defined('GBLOG_CORE')) { http_response_code(403); exit; } ?>
<div id="login-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);color:var(--text);border-radius:12px;padding:28px;width:460px;max-width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:17px;">登录</h3>
      <button onclick="document.getElementById('login-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <form id="login-modal-form" method="post" action="index.php?do=login">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div id="login-modal-error" style="display:none;margin-bottom:12px;padding:8px 10px;border:1px solid #f5c2c2;background:#fde8e8;color:#c0392b;border-radius:6px;font-size:14px;"></div>
      <div style="margin-bottom:12px;">
        <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">用户名</label>
        <input type="text" name="username" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">密码</label>
        <input type="password" name="password" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
      </div>
      <button type="submit" style="width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:15px;">登录</button>
      <div style="margin-top:12px;display:flex;justify-content:space-between;font-size:14px;">
        <a href="javascript:;" onclick="document.getElementById('login-modal').style.display='none';document.getElementById('forgot-modal').style.display='flex'" style="color:var(--primary);">忘记密码？</a>
        <a href="javascript:;" onclick="document.getElementById('login-modal').style.display='none';document.getElementById('register-modal').style.display='flex'" style="color:var(--primary);">没有账号？去注册</a>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  (function(){
  var f = document.getElementById('login-modal-form');
  if (!f) return;
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var err = document.getElementById('login-modal-error');
    if (err) err.style.display = 'none';
    var fd = new FormData(f);
    fd.append('back', location.pathname + location.search);
    fetch('index.php?do=login', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.ok) { window.location.href = d.redirect || 'index.php?do=profile'; return; }
        if (err) { err.textContent = (d && d.error) ? d.error : '登录失败，请重试。'; err.style.display = 'block'; }
      })
      .catch(function(){ if (err) { err.textContent = '网络错误，请重试。'; err.style.display = 'block'; } });
  });
})();

(function(){
  var f = document.getElementById('register-modal-form');
  if (!f) return;
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var err = document.getElementById('register-modal-error');
    if (err) err.style.display = 'none';
    var fd = new FormData(f);
    fd.append('back', location.pathname + location.search);
    fetch('index.php?do=register', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.ok) {
          window.location.href = (d.redirect) || 'index.php';
          return;
        }
        if (err) { err.textContent = (d && d.error) ? d.error : '注册失败，请重试。'; err.style.display = 'block'; }
      })
      .catch(function(){ if (err) { err.textContent = '网络错误，请重试。'; err.style.display = 'block'; } });
  });
})();

window.gblogForgotTab = function(which){
  var code = document.getElementById('tab-code'), three = document.getElementById('tab-3in1');
  if (!code || !three) return;
  var show = which === '3in1' ? three : code;
  var hide = which === '3in1' ? code : three;
  show.style.display = ''; hide.style.display = 'none';
  var si = show.querySelectorAll('input'), hi = hide.querySelectorAll('input'), i;
  for (i = 0; i < si.length; i++) si[i].disabled = false;
  for (i = 0; i < hi.length; i++) hi[i].disabled = true;
  var m = document.getElementById('forgot-mode');
  if (m) m.value = which === '3in1' ? '3in1' : 'code';
  var bc = document.getElementById('tab-code-btn'), bt = document.getElementById('tab-3in1-btn');
  if (bc && bt) {
    var on = which === 'code' ? bc : bt, off = which === 'code' ? bt : bc;
    on.style.color = 'var(--primary)'; on.style.borderBottom = '2px solid var(--primary)';
    off.style.color = 'var(--text-muted)'; off.style.borderBottom = '2px solid transparent';
  }
};

(function(){
  var f = document.getElementById('forgot-modal-form');
  if (!f) return;
  var bc = document.getElementById('tab-code-btn'), bt = document.getElementById('tab-3in1-btn');
  if (bc) bc.onclick = function(){ window.gblogForgotTab('code'); };
  if (bt) bt.onclick = function(){ window.gblogForgotTab('3in1'); };
  window.gblogForgotTab('code');
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var err = document.getElementById('forgot-modal-error');
    var ok = document.getElementById('forgot-modal-success');
    if (err) err.style.display = 'none';
    if (ok) ok.style.display = 'none';
    var fd = new FormData(f);
    fetch('index.php?do=forgot', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.captcha) {
          var q = document.getElementById('forgot-captcha-q');
          if (q) q.textContent = d.captcha;
          var c = f.querySelector('[name="captcha"]'); if (c) c.value = '';
        }
        if (d && d.ok) {
          if (ok) { ok.textContent = d.message || '验证通过。'; ok.style.display = 'block'; }
          if (d.redirect) { window.location.href = d.redirect; }
          return;
        }
        if (err) { err.textContent = (d && d.error) ? d.error : '找回失败，请重试。'; err.style.display = 'block'; }
      })
      .catch(function(){ if (err) { err.textContent = '网络错误，请重试。'; err.style.display = 'block'; } });
  });
})();

(function(){
  var q = new URLSearchParams(window.location.search);
  if (q.get('login') === '1' && document.getElementById('login-modal')) document.getElementById('login-modal').style.display = 'flex';
  else if (q.get('register') === '1' && document.getElementById('register-modal')) document.getElementById('register-modal').style.display = 'flex';
  else if (q.get('forgot') === '1' && document.getElementById('forgot-modal')) document.getElementById('forgot-modal').style.display = 'flex';
})();
});
</script>

<div id="forgot-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);color:var(--text);border-radius:12px;padding:28px;width:460px;max-width:90%;max-height:90%;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:17px;">找回密码</h3>
      <button onclick="document.getElementById('forgot-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid var(--border);">
      <button type="button" id="tab-code-btn" style="flex:1;padding:8px;background:none;border:none;cursor:pointer;font-size:15px;color:var(--primary);border-bottom:2px solid var(--primary);">恢复码找回</button>
      <button type="button" id="tab-3in1-btn" style="flex:1;padding:8px;background:none;border:none;cursor:pointer;font-size:15px;color:var(--text-muted);border-bottom:2px solid transparent;">三号验证</button>
    </div>
    <form id="forgot-modal-form" method="post" action="index.php?do=forgot">
      <div id="forgot-modal-error" style="display:none;margin-bottom:12px;padding:8px 10px;border:1px solid #f5c2c2;background:#fde8e8;color:#c0392b;border-radius:6px;font-size:14px;"></div>
      <div id="forgot-modal-success" style="display:none;margin-bottom:12px;padding:8px 10px;border:1px solid #c2e0c2;background:#e8f7e8;color:#2e7d32;border-radius:6px;font-size:14px;"></div>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="mode" id="forgot-mode" value="code">
      <div style="margin-bottom:14px;padding:10px 12px;border:1px solid var(--border);border-radius:6px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:14px;color:var(--text-muted);white-space:nowrap;">验证问题</span>
        <b id="forgot-captcha-q" style="font-size:15px;"><?= e(gblog_captcha_get()) ?></b>
        <input type="text" name="captcha" required inputmode="numeric" autocomplete="off" placeholder="答案" style="flex:1;min-width:70px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text);">
      </div>
      <div id="tab-code">
        <div style="margin-bottom:12px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">用户名 *</label>
          <input type="text" name="username" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <div style="margin-bottom:16px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">恢复码 *</label>
          <input type="text" name="security_code" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">由管理员发放，请向站务索取。</div>
        </div>
      </div>
      <div id="tab-3in1" style="display:none;">
        <div style="margin-bottom:12px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">用户名 *</label>
          <input type="text" name="username" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <div style="margin-bottom:12px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">手机号 *</label>
          <input type="tel" name="phone" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <div style="margin-bottom:12px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">QQ号 *</label>
          <input type="text" name="qq" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <div style="margin-bottom:16px;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">微信号 *</label>
          <input type="text" name="wechat" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
      </div>
      <button type="submit" style="width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:15px;">验证并重置密码</button>
      <div style="margin-top:12px;text-align:center;font-size:14px;">
        <a href="javascript:;" onclick="document.getElementById('forgot-modal').style.display='none';document.getElementById('login-modal').style.display='flex'" style="color:var(--primary);">返回登录</a>
      </div>
    </form>
  </div>
</div>

<div id="register-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);color:var(--text);border-radius:12px;padding:28px;width:460px;max-width:90%;max-height:90%;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:17px;">注册账号</h3>
      <button onclick="document.getElementById('register-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <?php
      $reg_email = get_setting('register_email', 'off');
      $reg_phone = get_setting('register_phone', 'off');
      $reg_qq = get_setting('register_qq', 'off');
      $reg_wechat = get_setting('register_wechat', 'off');
    ?>
    <form id="register-modal-form" method="post" action="index.php?do=register">
      <div id="register-modal-error" style="display:none;margin-bottom:12px;padding:8px 10px;border:1px solid #f5c2c2;background:#fde8e8;color:#c0392b;border-radius:6px;font-size:14px;"></div>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div style="margin-bottom:12px;">
        <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">用户名 *</label>
        <input type="text" name="username" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
      </div>
      <div style="display:flex;gap:12px;margin-bottom:12px;">
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">密码 *</label>
          <input type="password" name="password" required oninput="var p2=this.form.querySelector('input[name=password2]');if(p2){p2.setCustomValidity(p2.value!==this.value?'两次密码不一致':'');}" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">确认密码 *</label>
          <input type="password" name="password2" required oninput="this.setCustomValidity(this.value!==this.form.querySelector('input[name=password]').value?'两次密码不一致':'')" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">昵称</label>
        <input type="text" name="nickname" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
      </div>
      <?php if ($reg_email !== 'off' || $reg_phone !== 'off'): ?>
      <div style="display:flex;gap:12px;margin-bottom:12px;">
        <?php if ($reg_email !== 'off'): ?>
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">邮箱<?= $reg_email==='required' ? ' *' : '' ?></label>
          <input type="email" name="email" <?= $reg_email==='required' ? 'required' : '' ?> style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <?php endif; ?>
        <?php if ($reg_phone !== 'off'): ?>
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">手机号<?= $reg_phone==='required' ? ' *' : '' ?></label>
          <input type="tel" name="phone" <?= $reg_phone==='required' ? 'required' : '' ?> style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($reg_qq !== 'off' || $reg_wechat !== 'off'): ?>
      <div style="display:flex;gap:12px;margin-bottom:16px;">
        <?php if ($reg_qq !== 'off'): ?>
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">QQ号<?= $reg_qq==='required' ? ' *' : '' ?></label>
          <input type="text" name="qq" <?= $reg_qq==='required' ? 'required' : '' ?> style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <?php endif; ?>
        <?php if ($reg_wechat !== 'off'): ?>
        <div style="flex:1;min-width:0;">
          <label style="font-size:14px;color:var(--text-muted);display:block;margin-bottom:4px;">微信号<?= $reg_wechat==='required' ? ' *' : '' ?></label>
          <input type="text" name="wechat" <?= $reg_wechat==='required' ? 'required' : '' ?> style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <button type="submit" style="width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:15px;">注册</button>
      <div style="margin-top:12px;text-align:center;font-size:14px;">
        <a href="javascript:;" onclick="document.getElementById('register-modal').style.display='none';document.getElementById('login-modal').style.display='flex'" style="color:var(--primary);">已有账号？去登录</a>
      </div>
    </form>
  </div>
</div>
<?php $ad_header = render_ad('header_top'); if ($ad_header !== ''): ?>
<div class="container ad-zone-top"><?php echo $ad_header; ?></div>
<?php endif; ?>
