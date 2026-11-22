<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
require_once GBLOG_ROOT . '/core/ext/agg_modules.php';

function gblog_pt_collect_items($raw): array
{
    $out = [];
    if (!is_array($raw)) return $out;
    foreach ($raw as $it) {
        if (!is_array($it)) continue;
        $on  = isset($it['on']) ? 1 : 0;
        $src = ((string)($it['source'] ?? 'post') === 'custom') ? 'custom' : 'post';
        if ($src === 'post') {
            $pid = (int)($it['post_id'] ?? 0);
            if ($pid <= 0) continue;
            $out[] = ['on' => $on, 'source' => 'post', 'post_id' => $pid];
            continue;
        }
        $title = trim((string)($it['title'] ?? ''));
        $url   = trim((string)($it['url'] ?? ''));
        if ($title === '' || $url === '' || !preg_match('~^(https?://|/|index\.php|\?|#)~i', $url)) continue;
        $image = trim((string)($it['image'] ?? ''));
        if ($image !== '' && !preg_match('~^(https?://|/)~i', $image)) $image = '';
        $out[] = [
            'on'       => $on,
            'source'   => 'custom',
            'title'    => $title,
            'subtitle' => trim((string)($it['subtitle'] ?? '')),
            'image'    => $image,
            'url'      => $url,
        ];
    }
    return $out;
}

$ptMsg = '';
$ptErr = '';

/** 内容 ID 输入：支持逗号 / 中文逗号 / 分号 / 空格分隔，正整数、去重、按输入顺序。 */
function gblog_pt_parse_ids($raw): array
{
    return gblog_parse_id_list((string)$raw);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $ptErr = '表单校验失败，请重试。';
    } else {
        $ptPos  = gblog_pick($_POST, 'hero_position', ['list', 'page'], 'list');
        $ptMods = gblog_home_modules();
        foreach ($ptMods as &$pm) {
            if ($pm['key'] === 'carousel') {
                $pm['enabled'] = isset($_POST['carousel_on']) ? 1 : 0;
                $pm['seconds'] = min(10, max(2, (int)($_POST['carousel_seconds'] ?? 4)));
                $pm['mode']    = 'manual';
                $pm['post_ids'] = '';
                $pm['ids']     = implode(',', gblog_pt_parse_ids($_POST['carousel_ids'] ?? ''));
                $pm['items']   = gblog_pt_collect_items($_POST['carousel_items'] ?? []);
            } elseif ($pm['key'] === 'cards') {
                $pm['enabled'] = isset($_POST['cards_on']) ? 1 : 0;
                $pm['ids']     = implode(',', gblog_pt_parse_ids($_POST['cards_ids'] ?? ''));
                $pm['items']   = gblog_pt_collect_items($_POST['cards_items'] ?? []);
            }
        }
        unset($pm);
        save_setting('home_modules', json_encode($ptMods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        save_setting('home_hero_position', $ptPos);
        $ptMsg = '已保存。';
    }
}

$ptMods = gblog_home_modules();
$ptCar  = ['enabled' => 1, 'seconds' => 4, 'items' => []];
$ptCard = ['enabled' => 1, 'items' => []];
foreach ($ptMods as $pm) {
    if ($pm['key'] === 'carousel') $ptCar  = $pm;
    if ($pm['key'] === 'cards')    $ptCard = $pm;
}
$ptPos = (string)get_setting('home_hero_position', 'list');

$ptPosts = [];
try {
    $ptPosts = db()->query("SELECT id, title, status FROM posts ORDER BY created_at DESC, id DESC LIMIT 200")->fetchAll();
} catch (Throwable $e) {
}

$ptRowHtml = function (string $ns, $item) use ($ptPosts): string {
    $it     = is_array($item) ? $item : [];
    $src    = ((string)($it['source'] ?? 'post') === 'custom') ? 'custom' : 'post';
    $on     = !isset($it['on']) || (int)$it['on'] === 1;
    $pid    = (int)($it['post_id'] ?? 0);
    ob_start(); ?>
  <div class="pt-item" data-item style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px;background:var(--bg);">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;font-weight:normal;">
        <input type="checkbox" name="<?= e($ns) ?>[__i__][on]" value="1" <?= $on ? 'checked' : '' ?>> 显示
      </label>
      <select name="<?= e($ns) ?>[__i__][source]" data-src class="admin-input-sm">
        <option value="post" <?= $src === 'post' ? 'selected' : '' ?>>指定文章</option>
        <option value="custom" <?= $src === 'custom' ? 'selected' : '' ?>>自定义地址</option>
      </select>
      <span data-for="post" style="flex:1;min-width:220px;<?= $src === 'custom' ? 'display:none;' : '' ?>">
        <select name="<?= e($ns) ?>[__i__][post_id]" class="admin-input-sm" style="width:100%;">
          <option value="0">— 选择文章 —</option>
          <?php foreach ($ptPosts as $pp): ?>
          <option value="<?= (int)$pp['id'] ?>" <?= $pid === (int)$pp['id'] ? 'selected' : '' ?>><?= e((string)$pp['title']) ?><?= $pp['status'] === 'published' ? '' : '（草稿）' ?></option>
          <?php endforeach; ?>
        </select>
      </span>
      <span data-for="custom" style="flex:1;min-width:280px;display:flex;gap:6px;flex-wrap:wrap;<?= $src === 'post' ? 'display:none;' : '' ?>">
        <input type="text" name="<?= e($ns) ?>[__i__][title]" value="<?= e((string)($it['title'] ?? '')) ?>" placeholder="标题" class="admin-input-sm" style="flex:1;min-width:120px;">
        <input type="text" name="<?= e($ns) ?>[__i__][image]" value="<?= e((string)($it['image'] ?? '')) ?>" placeholder="封面图 URL（可空）" class="admin-input-sm" style="flex:1;min-width:140px;">
        <input type="text" name="<?= e($ns) ?>[__i__][url]" value="<?= e((string)($it['url'] ?? '')) ?>" placeholder="链接：https://… / 栏目页 / 自定义地址" class="admin-input-sm" style="flex:2;min-width:200px;">
      </span>
      <span style="display:inline-flex;gap:4px;">
        <button type="button" class="btn btn-sm" data-up title="上移">↑</button>
        <button type="button" class="btn btn-sm" data-down title="下移">↓</button>
        <button type="button" class="btn btn-sm" data-del title="删除">删除</button>
      </span>
    </div>
  </div>
<?php return (string)ob_get_clean();
};

$ptCarRows  = [];
foreach ((array)($ptCar['items'] ?? []) as $it) { $ptCarRows[] = $ptRowHtml('carousel_items', $it); }
$ptCardRows = [];
foreach ((array)($ptCard['items'] ?? []) as $it) { $ptCardRows[] = $ptRowHtml('cards_items', $it); }
?>
<form method="post" action="appcenter.php?tab=installed&amp;app=portal-top">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <div class="card card-connected" style="margin-bottom:14px;">
    <div class="section-title" style="margin-bottom:10px;"><b>显示位置</b></div>
    <div style="display:flex;gap:22px;flex-wrap:wrap;font-size:13px;">
      <label style="font-weight:normal;"><input type="radio" name="hero_position" value="list" <?= $ptPos === 'list' ? 'checked' : '' ?>> 列表内（门户首页内容列顶部，侧栏在右）</label>
      <label style="font-weight:normal;"><input type="radio" name="hero_position" value="page" <?= $ptPos === 'page' ? 'checked' : '' ?>> 整页顶部（门户首页通栏，侧栏在其下方）</label>
    </div>
  </div>

  <div class="card card-connected" style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:6px;">
      <label style="display:inline-flex;align-items:center;gap:6px;"><input type="checkbox" name="carousel_on" value="1" <?= !empty($ptCar['enabled']) ? 'checked' : '' ?>> <b>轮播</b></label>
      <label style="font-size:13px;font-weight:normal;">每 <input type="number" name="carousel_seconds" value="<?= (int)($ptCar['seconds'] ?? 4) ?>" min="2" max="10" class="admin-input-sm" style="width:64px;"> 秒切换</label>
      <span class="hint">条目留空时自动取最新 4 篇文章。</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:4px 0 12px;font-size:13px;">
      <label style="font-weight:normal;white-space:nowrap;">按内容 ID 指定</label>
      <input type="text" name="carousel_ids" value="<?= e((string)($ptCar['ids'] ?? '')) ?>" class="admin-input-sm" style="flex:1;min-width:220px;" placeholder="如 12,7,45（按此顺序显示，可多个）">
      <span class="hint">文章与歌曲共用一套全局编号；填写后优先于下方条目。内容 ID 见后台首页内容列表「ID」列。</span>
    </div>
    <div data-list="carousel_items">
      <?= implode('', $ptCarRows) ?>
    </div>
    <button type="button" class="btn btn-sm" data-add="carousel_items">+ 添加轮播条目</button>
  </div>

  <div class="card card-connected" style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:6px;">
      <label style="display:inline-flex;align-items:center;gap:6px;"><input type="checkbox" name="cards_on" value="1" <?= !empty($ptCard['enabled']) ? 'checked' : '' ?>> <b>卡片</b></label>
      <span class="hint">条目留空时自动取最新 4 篇文章。</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:4px 0 12px;font-size:13px;">
      <label style="font-weight:normal;white-space:nowrap;">按内容 ID 指定</label>
      <input type="text" name="cards_ids" value="<?= e((string)($ptCard['ids'] ?? '')) ?>" class="admin-input-sm" style="flex:1;min-width:220px;" placeholder="如 12,7,45（按此顺序显示，可多个）">
      <span class="hint">文章与歌曲共用一套全局编号；填写后优先于下方条目。内容 ID 见后台首页内容列表「ID」列。</span>
    </div>
    <div data-list="cards_items">
      <?= implode('', $ptCardRows) ?>
    </div>
    <button type="button" class="btn btn-sm" data-add="cards_items">+ 添加卡片条目</button>
  </div>

  <button type="submit" class="btn btn-primary">保存</button>
  <?= admin_flash_html($ptErr, $ptMsg) ?>
</form>

<template id="pt-item-tpl">
<?= $ptRowHtml('__NS__', ['on' => 1, 'source' => 'post', 'post_id' => 0]) ?>
</template>

<script>
(function () {
  var seq = 0;
  function bind(box) {
    box.querySelectorAll('[data-item]').forEach(function (it) {
      if (it.getAttribute('data-bound') === '1') return;
      it.setAttribute('data-bound', '1');
      var sel = it.querySelector('[data-src]');
      function sync() {
        var custom = sel && sel.value === 'custom';
        it.querySelectorAll('[data-for="post"]').forEach(function (n) { n.style.display = custom ? 'none' : ''; });
        it.querySelectorAll('[data-for="custom"]').forEach(function (n) { n.style.display = custom ? 'flex' : 'none'; });
      }
      if (sel) { sel.addEventListener('change', sync); sync(); }
      var del = it.querySelector('[data-del]');
      if (del) del.addEventListener('click', function () { it.remove(); });
      var up = it.querySelector('[data-up]');
      if (up) up.addEventListener('click', function () { if (it.previousElementSibling) it.parentNode.insertBefore(it, it.previousElementSibling); });
      var down = it.querySelector('[data-down]');
      if (down) down.addEventListener('click', function () { if (it.nextElementSibling) it.parentNode.insertBefore(it.nextElementSibling, it); });
    });
  }
  document.querySelectorAll('[data-list]').forEach(function (list) {
    bind(list);
    var ns = list.getAttribute('data-list');
    var btn = document.querySelector('[data-add="' + ns + '"]');
    var tpl = document.getElementById('pt-item-tpl');
    if (!btn || !tpl) return;
    btn.addEventListener('click', function () {
      seq += 1;
      var i = 'n' + seq;
      var node = tpl.content.firstElementChild.cloneNode(true);
      node.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace('__NS__', ns).replace('__i__', i);
      });
      node.setAttribute('data-new-index', i);
      list.appendChild(node);
      bind(list);
    });
  });
})();
</script>
