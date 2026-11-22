<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
// 软件详情信息卡（原 soft-header 插件的设计并入模板）
$post  = $post  ?? [];
$dlMsg = $dlMsg ?? '';
if ((int)($post['id'] ?? 0) <= 0) return;
// 本零件是软件详情页的独立信息头部，不再受 detail_head（下载卡）插件钩子影响。

$me    = function_exists('current_user') ? current_user() : null;
$dl    = download_state($post, $me);
$dlUrl = apply_filters('download_link', 'index.php?do=download&id=' . (int)$post['id'], $post);

$title = (string)($post['title'] ?? '');
$cover = trim((string)($post['cover'] ?? ''));
$initial = mb_substr($title, 0, 1, 'UTF-8');
$hue = abs(crc32($title) % 360);
$logoStyle = $cover !== ''
    ? 'background-image:url(' . e($cover) . ');background-size:cover;background-position:center;'
    : 'background:linear-gradient(135deg,hsl(' . $hue . ',75%,55%),hsl(' . (($hue + 28) % 360) . ',80%,45%));color:#fff;';

$rating = (float)($post['rating'] ?? 0);
$stars = '';
for ($i = 1; $i <= 5; $i++) {
    if ($rating >= $i)            $stars .= '<span class="sh-star sh-full">★</span>';
    elseif ($rating >= $i - 0.5)  $stars .= '<span class="sh-star sh-half">★</span>';
    else                          $stars .= '<span class="sh-star sh-empty">★</span>';
}

$swType     = trim((string)($post['software_type'] ?? ''));
$swPlatform = trim((string)($post['software_platform'] ?? ''));
$official = trim((string)($post['official_url'] ?? ''));
$officialSafe = (bool)preg_match('#^https?://#i', $official);
$lang     = trim((string)($post['language'] ?? ''));
$swVer    = trim((string)($post['software_version'] ?? ''));
$size     = trim((string)($post['file_size'] ?? ''));
$catName  = trim((string)($post['category_name'] ?? ''));
$catId    = (int)($post['category_id'] ?? 0);
$created  = (string)($post['created_at'] ?? '');
$updated  = trim((string)($post['updated_at'] ?? '')) ?: $created;
$fmtDate  = function_exists('fmt_date') ? fmt_date($updated) : $updated;
$tags     = is_array($post['tags'] ?? null) ? $post['tags'] : [];

$shBlank = '<span class="sh-blank">—</span>';
// 内容类链接跟随后台伪静态形态（分类按 id，posts.slug 已移除）
$shCatUrl = function_exists('gblog_url')
    ? gblog_url('category', ['id' => $catId])
    : 'index.php?do=category&id=' . $catId;
$shCat = $catName !== ''
    ? '<a href="' . e($shCatUrl) . '">' . e($catName) . '</a>'
    : $shBlank;
$shPostAction = function_exists('gblog_post_url')
    ? gblog_post_url($post)
    : 'index.php?do=post&id=' . (int)($post['id'] ?? 0);
$shLoginUrl = function_exists('gblog_url') ? gblog_url('login') : 'index.php?do=login';
$shOfficial = $official === '' ? $shBlank
    : ($officialSafe
        ? '<a href="' . e($official) . '" target="_blank" rel="noopener">' . e($official) . '</a>'
        : e($official));
$shRows = [
    ['软件类别', $shCat],
    ['软件类型', $swType     !== '' ? e($swType)     : $shBlank],
    ['运行平台', $swPlatform !== '' ? e($swPlatform) : $shBlank],
    ['软件版本', $swVer      !== '' ? e($swVer)      : $shBlank],
    ['官方网站', $shOfficial],
    ['软件大小', $size       !== '' ? e($size)       : $shBlank],
    ['软件语言', $lang       !== '' ? e($lang)       : $shBlank],
];
?>
<div class="soft-header-card">
  <div class="sh-logo" style="<?= $logoStyle ?>"><?= $cover === '' ? e($initial) : '' ?></div>

  <div class="sh-body">
    <div class="sh-head">
      <h1 class="sh-title"><?= e($title) ?></h1>
    </div>

    <div class="sh-meta">
      <?php foreach ($shRows as [$shLabel, $shHtml]): ?>
        <div class="sh-row"><span class="sh-label"><?= e($shLabel) ?></span><span class="sh-val"><?= $shHtml ?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="sh-tags">
      <span class="sh-label">相关标签</span>
      <?php if ($tags): foreach ($tags as $t): ?>
        <?php $shTagUrl = function_exists('gblog_url') ? gblog_url('tag', ['id' => (int)($t['id'] ?? 0)]) : 'index.php?do=tag&id=' . (int)($t['id'] ?? 0); ?>
        <a class="sh-tag" href="<?= e($shTagUrl) ?>">#<?= e((string)($t['name'] ?? '')) ?></a>
      <?php endforeach; else: ?>
        <?= $shBlank ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="sh-side">
    <div class="sh-row sh-update"><span class="sh-label">更新时间</span><span class="sh-val"><?= e($fmtDate) ?></span></div>
    <div class="sh-row sh-rating"><span class="sh-label">推荐星级</span><span class="sh-val"><?= $stars ?></span></div>
    <div class="sh-row sh-downloads"><span class="sh-label">下载次数</span><span class="sh-val"><?= (int)($post['download_count'] ?? 0) ?> 次</span></div>
    <div class="sh-download">
      <?php if ($dlMsg !== ''): ?><p class="comment-msg <?= !empty($dlOk) ? 'ok' : 'err' ?>"><?= e($dlMsg) ?></p><?php endif; ?>
      <?php if (!empty($dl['allowed'])): ?>
        <a class="sh-btn" href="<?= e($dlUrl) ?>" target="_blank" rel="noopener">立即下载</a>
      <?php elseif (($dl['level'] ?? '') === 'login'): ?>
        <a class="sh-btn" href="<?= e($shLoginUrl) ?>">登录后下载</a>
      <?php else: ?>
        <span class="sh-need"><?= e($dl['reason'] ?? '') ?></span>
        <form method="post" action="<?= e($shPostAction) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" name="buy_dl" value="1" class="sh-btn sh-btn-long">消耗 <?= (int)($dl['price'] ?? 0) ?> 金币下载</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
