<?php
$page_title = '站点设置';
require_once __DIR__ . '/../inc/header.php';
gblog_admin_require_cap('settings.manage');
require_once __DIR__ . '/../inc/tabs.php';
require_once __DIR__ . '/../inc/upload_helper.php';

$error = '';
$success = '';
$tab = (string)($_POST['tab'] ?? $_GET['tab'] ?? 'base');
if (!in_array($tab, ['base', 'func', 'home', 'ads'], true)) $tab = 'base';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $ptab = (string)($_POST['tab'] ?? 'base');
        if ($ptab === 'base') {
            $fields = [
                'site_name'        => trim((string)($_POST['site_name'] ?? '')),
                'site_title'       => trim((string)($_POST['site_title'] ?? '')),
                'site_desc'        => trim((string)($_POST['site_desc'] ?? '')),
                'logo_url'         => trim((string)($_POST['logo_url'] ?? '')),
                'timezone'         => (string)($_POST['timezone'] ?? 'Asia/Shanghai'),
                'stat_code'        => (string)($_POST['stat_code'] ?? ''),
                'about_content'    => (string)($_POST['about_content'] ?? ''),
                'footer_copyright' => (string)($_POST['footer_copyright'] ?? ''),
                'footer_icp'       => (string)($_POST['footer_icp'] ?? ''),
                'footer_powered'   => (string)($_POST['footer_powered'] ?? ''),
                'footer_powered_url'=> trim((string)($_POST['footer_powered_url'] ?? '')),
                'site_keywords'     => trim((string)($_POST['site_keywords'] ?? '')),
                'site_url'          => trim((string)($_POST['site_url'] ?? '')),
            ];
            if (!empty($_FILES['logo_upload']['tmp_name'])) {
                $r = handle_upload($_FILES['logo_upload'], 'image');
                if ($r['ok']) { $fields['logo_url'] = $r['url']; }
                else { $error = 'Logo 上传失败：' . $r['error']; }
            }
            if ($fields['site_name'] === '') { $error = '请填写网站名称。'; }
            elseif ($error === '') {
                foreach ($fields as $k => $v) save_setting($k, (string)$v);
                $success = '基础设置已保存。';
            }
        } elseif ($ptab === 'func') {
            $fields = [
                'register_open'    => ($_POST['register_open'] ?? '1') === '1' ? '1' : '0',
                'comment_open'     => ($_POST['comment_open'] ?? '1') === '1' ? '1' : '0',
                'comment_moderate' => ($_POST['comment_moderate'] ?? '1') === '1' ? '1' : '0',
                'gold_enabled'     => ($_POST['gold_enabled'] ?? '1') === '1' ? '1' : '0',
                'register_gold'    => (string)max(0, (int)($_POST['register_gold'] ?? 0)),
                'sign_gold'        => (string)max(0, (int)($_POST['sign_gold'] ?? 0)),
                'register_email'   => gblog_pick($_POST, 'register_email', ['off','optional','required'], 'off'),
                'register_phone'   => gblog_pick($_POST, 'register_phone', ['off','optional','required'], 'off'),
                'register_qq'      => gblog_pick($_POST, 'register_qq', ['off','optional','required'], 'off'),
                'register_wechat'  => gblog_pick($_POST, 'register_wechat', ['off','optional','required'], 'off'),
                'permalink_enabled' => (($_POST['permalink_enabled'] ?? '0') === '1') ? '1' : '0',
                'permalink_style'   => gblog_pick($_POST, 'permalink_style', ['s1','s2','s3'], 's3'),
            ];
            foreach ($fields as $k => $v) save_setting($k, (string)$v);
            $success = '功能开关已保存。';
        } elseif ($ptab === 'home') {
            $fields = [
                'home_notice' => (string)($_POST['home_notice'] ?? ''),
            ];
            foreach ($fields as $k => $v) save_setting($k, (string)$v);
            $success = '首页设置已保存。';
        } elseif ($ptab === 'ads') {
            $adKeys = ['header_top', 'sidebar', 'article_bottom', 'article_sidebar'];
            foreach ($adKeys as $key) {
                $code = (string)($_POST['ad_' . $key] ?? '');
                $enabled = isset($_POST['en_' . $key]) ? 1 : 0;
                save_ad($key, $code, $enabled);
            }
            $success = '广告设置已保存。';
        }
    }
}

$set = get_all_settings();
$ads = get_ads();
$adMap = []; foreach ($ads as $ad) { $adMap[$ad['ad_key']] = $ad; }
$curTz = $set['timezone'] ?? 'Asia/Shanghai';
?>
<?php
admin_sub_tabs([
    'base' => gblog_admin_icon('base') . ' 基础设置',
    'func' => gblog_admin_icon('func') . ' 功能开关',
    'home' => gblog_admin_icon('home') . ' 首页展示',
    'ads'  => gblog_admin_icon('ads')  . ' 广告设置',
], $tab, admin_flash_html($error, $success));
?>

<?php if ($tab === 'base'): ?>
<form method="post" action="<?= gblog_admin_url('system/settings.php') ?>" enctype="multipart/form-data">
  <input type="hidden" name="tab" value="base">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card card-connected" style="padding:20px 24px;">
    <div class="form-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="form-field">
        <label>网站名称（Logo 显示用）*</label>
        <input type="text" name="site_name" value="<?= e($set['site_name'] ?? SITE_NAME) ?>" required>
      </div>
      <div class="form-field">
        <label>网站标题（浏览器标签/SEO 用）</label>
        <input type="text" name="site_title" value="<?= e($set['site_title'] ?? '') ?>" placeholder="留空则用网站名称">
      </div>
      <div class="form-field">
        <label>SEO 关键词（meta keywords，逗号分隔）</label>
        <input type="text" name="site_keywords" value="<?= e($set['site_keywords'] ?? '') ?>" placeholder="博客,技术,原创音乐">
      </div>
      <div class="form-field">
        <label>站点地址（sitemap 根域名，留空自动取当前域名）</label>
        <input type="text" name="site_url" value="<?= e($set['site_url'] ?? '') ?>" placeholder="https://example.com">
      </div>
      <div class="form-field">
        <label>Logo 图片 URL（可空）</label>
        <input type="text" name="logo_url" value="<?= e($set['logo_url'] ?? '') ?>" placeholder="https:// 或留空显示首字">
      </div>
      <div class="form-field">
        <label>或上传 Logo 图片</label>
        <input type="file" name="logo_upload" accept=".jpg,.jpeg,.png,.webp,.gif">
      </div>
      <div class="form-field">
        <label>时区</label>
        <select name="timezone">
          <?php $tzs = ['Asia/Shanghai' => '北京时间 (UTC+8)', 'Asia/Hong_Kong' => '香港时间 (UTC+8)', 'Asia/Taipei' => '台北时间 (UTC+8)', 'Asia/Singapore' => '新加坡时间 (UTC+8)', 'Asia/Tokyo' => '东京时间 (UTC+9)', 'America/New_York' => '纽约时间 (UTC-5)', 'Europe/London' => '伦敦时间 (UTC+0)', 'UTC' => 'UTC']; ?>
          <?php foreach ($tzs as $tz => $label): ?>
            <option value="<?= $tz ?>" <?= $curTz === $tz ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="section-title" style="grid-column:1/-1;margin-top:8px;"><b>统计与关于</b></div>
      <div style="grid-column:1/-1;display:flex;gap:16px;">
        <div class="form-field" style="flex:1;">
          <label>网站简介</label>
          <textarea name="site_desc" rows="12" style="height:160px;"><?= e($set['site_desc'] ?? SITE_DESC) ?></textarea>
        </div>
        <div class="form-field" style="flex:1;">
          <label>第三方统计代码（放 &lt;head&gt;）</label>
          <textarea name="stat_code" rows="12" style="font-family:Consolas,monospace;font-size:13px;height:160px;"><?= e($set['stat_code'] ?? '') ?></textarea>
        </div>
        <div class="form-field" style="flex:1;">
          <label>关于页内容（支持 Markdown）</label>
          <textarea name="about_content" rows="12" style="height:160px;"><?= e($set['about_content'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="section-title" style="grid-column:1/-1;margin-top:8px;"><b>页脚设置</b></div>
      <div style="grid-column:1/-1;display:flex;gap:16px;">
        <div class="form-field" style="flex:1;"><label>版权文字（显示在 © 年份后）</label><input type="text" name="footer_copyright" value="<?= e($set['footer_copyright'] ?? '版权所有') ?>" placeholder="版权所有"></div>
        <div class="form-field" style="flex:1;"><label>备案编号</label><input type="text" name="footer_icp" value="<?= e($set['footer_icp'] ?? '') ?>" placeholder="如：辽ICP备12016417号-1"></div>
        <div class="form-field" style="flex:1;"><label>驱动信息（显示在 | 后）</label><input type="text" name="footer_powered" value="<?= e($set['footer_powered'] ?? '由 Gblog 驱动') ?>" placeholder="由 Gblog 驱动"></div>
        <div class="form-field" style="flex:1;"><label>驱动链接（点击驱动文字跳转）</label><input type="text" name="footer_powered_url" value="<?= e($set['footer_powered_url'] ?? '') ?>" placeholder="留空则链接到本站首页"></div>
      </div>
    </div>
  </div>
  <div style="margin-top:18px;"><button type="submit" class="btn btn-primary">保存基础设置</button></div>
</form>

<?php elseif ($tab === 'func'): ?>
<form method="post" action="<?= gblog_admin_url('system/settings.php') ?>">
  <input type="hidden" name="tab" value="func">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card card-connected" style="padding:20px 24px;">
    <div style="display:flex;flex-direction:column;gap:14px;align-items:flex-start;">
      <label style="display:flex;align-items:center;gap:6px;font-size:14px;">用户注册：
        <select name="register_open" class="admin-input-sm">
          <option value="1" <?= (($set['register_open'] ?? '1') === '1') ? 'selected' : '' ?>>开放</option>
          <option value="0" <?= (($set['register_open'] ?? '1') === '0') ? 'selected' : '' ?>>关闭</option>
        </select>
      </label>
      <label style="display:flex;align-items:center;gap:6px;font-size:14px;">文章评论：
        <select name="comment_open" class="admin-input-sm">
          <option value="1" <?= (($set['comment_open'] ?? '1') === '1') ? 'selected' : '' ?>>开放</option>
          <option value="0" <?= (($set['comment_open'] ?? '1') === '0') ? 'selected' : '' ?>>关闭</option>
        </select>
      </label>
      <label style="display:flex;align-items:center;gap:6px;font-size:14px;">评论审核：
        <select name="comment_moderate" class="admin-input-sm">
          <option value="1" <?= (($set['comment_moderate'] ?? '1') === '1') ? 'selected' : '' ?>>开启</option>
          <option value="0" <?= (($set['comment_moderate'] ?? '1') === '0') ? 'selected' : '' ?>>关闭</option>
        </select>
      </label>
      <label style="display:flex;align-items:center;gap:6px;font-size:14px;">金币系统：
        <select name="gold_enabled" onchange="document.getElementById('gold-options').style.display = this.value === '1' ? '' : 'none'" class="admin-input-sm">
          <option value="1" <?= (($set['gold_enabled'] ?? '1') === '1') ? 'selected' : '' ?>>开启</option>
          <option value="0" <?= (($set['gold_enabled'] ?? '1') === '0') ? 'selected' : '' ?>>关闭</option>
        </select>
      </label>
      <span id="gold-options" style="<?= (($set['gold_enabled'] ?? '1') === '1') ? 'display:flex' : 'display:none' ?>;gap:14px;align-items:center;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">每日签到：
          <input type="text" name="sign_gold" value="<?= e($set['sign_gold'] ?? '0') ?>" list="gold-list" class="admin-input-sm" style="width:60px;">
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">注册赠送：
          <input type="text" name="register_gold" value="<?= e($set['register_gold'] ?? '0') ?>" list="gold-list" class="admin-input-sm" style="width:60px;">
        </label>
      </span>
      <datalist id="gold-list">
        <option value="1"><option value="5"><option value="10"><option value="15"><option value="20"><option value="30"><option value="50"><option value="100">
      </datalist>
    </div>
    <div class="section-title" style="margin:18px 0 10px;"><b>注册必填字段</b></div>
    <div style="display:flex;flex-direction:column;gap:12px;align-items:flex-start;">
      <div style="display:flex;gap:20px;align-items:center;">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">邮箱：
          <select name="register_email" class="admin-input-sm">
            <option value="off" <?= ($set['register_email'] ?? 'off')==='off' ? 'selected' : '' ?>>关闭</option>
            <option value="optional" <?= ($set['register_email'] ?? 'off')==='optional' ? 'selected' : '' ?>>选填</option>
            <option value="required" <?= ($set['register_email'] ?? 'off')==='required' ? 'selected' : '' ?>>必填</option>
          </select>
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">手机号：
          <select name="register_phone" class="admin-input-sm">
            <option value="off" <?= ($set['register_phone'] ?? 'off')==='off' ? 'selected' : '' ?>>关闭</option>
            <option value="optional" <?= ($set['register_phone'] ?? 'off')==='optional' ? 'selected' : '' ?>>选填</option>
            <option value="required" <?= ($set['register_phone'] ?? 'off')==='required' ? 'selected' : '' ?>>必填</option>
          </select>
        </label>
      </div>
      <div style="display:flex;gap:20px;align-items:center;">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">QQ号：
          <select name="register_qq" class="admin-input-sm">
            <option value="off" <?= ($set['register_qq'] ?? 'off')==='off' ? 'selected' : '' ?>>关闭</option>
            <option value="optional" <?= ($set['register_qq'] ?? 'off')==='optional' ? 'selected' : '' ?>>选填</option>
            <option value="required" <?= ($set['register_qq'] ?? 'off')==='required' ? 'selected' : '' ?>>必填</option>
          </select>
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">微信号：
          <select name="register_wechat" class="admin-input-sm">
            <option value="off" <?= ($set['register_wechat'] ?? 'off')==='off' ? 'selected' : '' ?>>关闭</option>
            <option value="optional" <?= ($set['register_wechat'] ?? 'off')==='optional' ? 'selected' : '' ?>>选填</option>
            <option value="required" <?= ($set['register_wechat'] ?? 'off')==='required' ? 'selected' : '' ?>>必填</option>
          </select>
        </label>
      </div>
    </div>

    <div class="section-title" style="margin:18px 0 10px;"><b>伪静态（固定链接）</b></div>
    <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-start;">
      <label style="display:flex;align-items:center;gap:6px;font-size:14px;">启用伪静态：
        <select name="permalink_enabled" class="admin-input-sm" onchange="document.getElementById('permalink-style-box').style.display = this.value === '1' ? 'flex' : 'none'">
          <option value="1" <?= gblog_permalink_enabled() ? 'selected' : '' ?>>开启（.html 固定链接）</option>
          <option value="0" <?= !gblog_permalink_enabled() ? 'selected' : '' ?>>关闭（原始 ?do= 形式，应急回退）</option>
        </select>
      </label>
      <div id="permalink-style-box" style="<?= gblog_permalink_enabled() ? 'display:flex' : 'display:none' ?>;flex-direction:column;align-items:flex-start;width:100%;">
        <label style="display:flex;align-items:center;gap:6px;font-size:14px;">伪静态形态：
          <select name="permalink_style" class="admin-input-sm" style="min-width:430px;">
            <option value="s1" <?= gblog_permalink_style() === 's1' ? 'selected' : '' ?>>方案一 栏目目录 + 纯 ID（/post/101.html、/music/401.html）</option>
            <option value="s2" <?= gblog_permalink_style() === 's2' ? 'selected' : '' ?>>方案二 栏目目录 + 发布日期ID（/post/20261114101.html）</option>
            <option value="s3" <?= gblog_permalink_style() === 's3' ? 'selected' : '' ?>>方案三 顶级 栏目-ID（/post-101.html、/music-401.html，推荐）</option>
          </select>
        </label>
      </div>
    </div>

  </div>
  <div style="margin-top:18px;"><button type="submit" class="btn btn-primary">保存功能开关</button></div>
</form>

<?php elseif ($tab === 'home'): ?>
<form method="post" action="<?= gblog_admin_url('system/settings.php') ?>">
  <input type="hidden" name="tab" value="home">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card card-connected" style="padding:20px 24px;">
    <div class="section-title" style="margin-bottom:10px;"><b>首页公告</b>
      <span class="hint" style="margin-left:8px;">显示在首页右侧「最新公告」标签内，支持 HTML（可写多条公告、加链接）。留空则不显示公告。</span>
    </div>
    <textarea name="home_notice" rows="6" class="admin-input" style="width:100%;min-height:140px;box-sizing:border-box;font-family:Consolas,monospace;font-size:13px;"><?= e($set['home_notice'] ?? '') ?></textarea>

    <div class="section-title" style="margin:20px 0 8px;"><b>头图区</b>
      <span class="hint" style="margin-left:8px;">由「门户顶部」插件提供，显示位置与条目在 <a href="<?= gblog_admin_url('system/appcenter.php?tab=installed&amp;app=portal-top') ?>">插件设置</a> 里配置。</span>
    </div>
  </div>
  <div style="margin-top:18px;"><button type="submit" class="btn btn-primary">保存首页设置</button></div>
</form>

<?php elseif ($tab === 'ads'): ?>
<form method="post" action="<?= gblog_admin_url('system/settings.php') ?>">
  <input type="hidden" name="tab" value="ads">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card card-connected">
    <p class="hint" style="margin-bottom:16px;">
      把广告平台的代码（如 Google AdSense、百度联盟，或任意 HTML 代码）粘贴到对应的广告位，并勾选「启用」，保存后前台对应位置就会显示。
    </p>
    <?php $adTitles = ['header_top'=>'首页顶部广告','sidebar'=>'侧边栏 / 列表底部广告','article_bottom'=>'文章正文下方广告','article_sidebar'=>'文章页侧边广告']; ?>
    <?php foreach ($adTitles as $key => $title): $ad = $adMap[$key] ?? null; ?>
      <div class="song-block">
        <div class="song-block-head">
          <b><?= e($title) ?></b>
          <label style="font-size:13px;font-weight:normal;color:var(--muted);">
            <input type="checkbox" name="en_<?= $key ?>" value="1" <?= ($ad && (int)$ad['enabled'] === 1) ? 'checked' : '' ?>> 启用
          </label>
        </div>
        <textarea name="ad_<?= $key ?>" rows="5" class="admin-input" style="width:100%;font-family:Consolas,monospace;font-size:13px;"><?= e($ad['code'] ?? '') ?></textarea>
        <span class="hint" style="margin-top:6px;display:block;">示例：&lt;script async src="...广告联盟代码..."&gt;&lt;/script&gt; 或 &lt;a href="...">图片&lt;/a&gt;</span>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary">保存广告设置</button>
  </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
