<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }

// 与 plugin.php 保持一致；settings 在应用中心独立加载，不复用 plugin.php 以避免重复注册钩子。
function gblog_share_set_platforms(): array
{
    return [
        'weibo'    => '微博',
        'qqzone'   => 'QQ空间',
        'qqfriend' => 'QQ好友',
        'weixin'   => '微信',
        'douban'   => '豆瓣',
        'facebook' => 'Facebook',
        'twitter'  => 'Twitter',
        'linkedin' => 'Linkedin',
    ];
}

function gblog_share_set_defaults(): array
{
    return [
        'platforms' => ['weibo' => 1, 'qqzone' => 1, 'qqfriend' => 1, 'weixin' => 1,
                       'douban' => 1, 'facebook' => 0, 'twitter' => 0, 'linkedin' => 0],
        'back2top'  => 1,
        'qr'        => 1,
        'copy'      => 1,
        'favorite'  => 1,
        'scroll'    => 300,
    ];
}

$PLATFORMS = gblog_share_set_platforms();
$DEFAULTS  = gblog_share_set_defaults();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('csrf_check') || !csrf_check()) {
        $err = '表单校验失败，请重试。';
    } else {
        $platforms = [];
        foreach (array_keys($PLATFORMS) as $id) {
            $platforms[$id] = isset($_POST['p_' . $id]) ? 1 : 0;
        }
        $cfg = [
            'platforms' => $platforms,
            'back2top'  => isset($_POST['back2top']) ? 1 : 0,
            'qr'        => isset($_POST['qr']) ? 1 : 0,
            'copy'      => isset($_POST['copy']) ? 1 : 0,
            'favorite'  => isset($_POST['favorite']) ? 1 : 0,
            'scroll'    => min(2000, max(0, (int)($_POST['scroll'] ?? 300))),
        ];
        save_setting('share_toolbar', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $msg = '已保存。';
    }
}

// 读当前配置（合并默认，避免旧数据缺字段）
$saved = [];
$raw = function_exists('get_setting') ? get_setting('share_toolbar', '') : '';
if ($raw !== '') {
    $dec = json_decode($raw, true);
    if (is_array($dec)) $saved = $dec;
}
$cfg = array_merge($DEFAULTS, $saved);
$cfg['platforms'] = array_merge($DEFAULTS['platforms'], is_array($cfg['platforms'] ?? null) ? $cfg['platforms'] : []);

$isOn = function (string $k) use ($cfg): bool { return !empty($cfg['platforms'][$k]); };
?>
<form method="post" action="appcenter.php?tab=installed&amp;app=share-toolbar">
  <input type="hidden" name="csrf_token" value="<?php echo e(function_exists('csrf_token') ? csrf_token() : ''); ?>">

  <div class="card card-connected" style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
      <div class="section-title"><b>分享平台</b></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn btn-sm" data-preset="domestic">国内常用</button>
        <button type="button" class="btn btn-sm" data-preset="all">全部开启</button>
        <button type="button" class="btn btn-sm" data-preset="none">全部关闭</button>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;">
      <?php foreach ($PLATFORMS as $id => $name): ?>
      <label style="display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:normal;
                    border:1px solid var(--border);border-radius:8px;padding:8px 10px;background:var(--bg-card);">
        <input type="checkbox" name="p_<?php echo $id; ?>" value="1" <?php echo $isOn($id) ? 'checked' : ''; ?>>
        <?php echo e($name); ?>
      </label>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:10px 0 0;">国内站可关掉 Facebook / Twitter / Linkedin 等访问不畅的海外平台；海外站可全部开启。微信无网页分享接口，开启后点击会展示二维码供扫码分享。</p>
  </div>

  <div class="card card-connected" style="margin-bottom:14px;">
    <div class="section-title" style="margin-bottom:10px;"><b>工具条选项</b></div>
    <div style="display:flex;gap:22px;flex-wrap:wrap;font-size:14px;">
      <label style="font-weight:normal;"><input type="checkbox" name="back2top" value="1" <?php echo !empty($cfg['back2top']) ? 'checked' : ''; ?>> 显示「返回顶部」箭头</label>
      <label style="font-weight:normal;"><input type="checkbox" name="qr" value="1" <?php echo !empty($cfg['qr']) ? 'checked' : ''; ?>> 显示「当前页二维码」图标</label>
      <label style="font-weight:normal;"><input type="checkbox" name="copy" value="1" <?php echo !empty($cfg['copy']) ? 'checked' : ''; ?>> 面板内显示「复制链接」按钮</label>
      <label style="font-weight:normal;"><input type="checkbox" name="favorite" value="1" <?php echo !empty($cfg['favorite']) ? 'checked' : ''; ?>> 显示「收藏本页」按钮</label>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:12px;font-size:14px;flex-wrap:wrap;">
      <label style="font-weight:normal;white-space:nowrap;">滚动多少像素后显示「返回顶部」箭头</label>
      <input type="number" name="scroll" value="<?php echo (int)$cfg['scroll']; ?>" min="0" max="2000" class="admin-input-sm" style="width:90px;">
      <span class="hint">设为 0 则始终显示。</span>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">保存</button>
  <?php echo function_exists('admin_flash_html') ? admin_flash_html($err, $msg) : ''; ?>
</form>

<template id="gb-share-preset-tpl" data-domestic="weibo,qqzone,qqfriend,weixin,douban" data-all="" data-none=""></template>
<script>
(function () {
  var tpl = document.getElementById('gb-share-preset-tpl');
  if (!tpl) return;
  var domestic = (tpl.getAttribute('data-domestic') || '').split(',').filter(Boolean);
  function apply(preset) {
    var boxes = document.querySelectorAll('input[name^="p_"]');
    boxes.forEach(function (b) {
      var id = b.name.replace('p_', '');
      var on = preset === 'all' ? true : (preset === 'none' ? false : domestic.indexOf(id) !== -1);
      b.checked = on;
    });
  }
  document.querySelectorAll('[data-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () { apply(btn.getAttribute('data-preset')); });
  });
})();
</script>
