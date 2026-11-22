<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!function_exists('gblog_layout_catalog')) {
    require_once __DIR__ . '/../../../core/render/layout.php';
}
$tabUrl = gblog_admin_url('system/themes.php?tab=layout');
$catalog = gblog_layout_catalog();
$pageViews = gblog_page_views();
$scanAll = gblog_scan_templates();
$roles = gblog_layout_role_names();
$themeNames = [];
foreach (get_theme_list() as $t) { $themeNames[$t['dir']] = $t['name']; }

$sites = db()->query('SELECT id, name, slug, type, home_mode FROM sites ORDER BY sort_order ASC, id ASC')->fetchAll();
$rows  = [[
    'slug' => '_home', 'name' => '全站首页', 'type' => '_home',
    'home_mode' => 'aggregate',
]];
foreach ($sites as $s) {
    $rows[] = [
        'slug'      => $s['slug'],
        'name'      => $s['name'],
        'type'      => $s['type'],
        'home_mode' => $s['home_mode'],
    ];
}
$allowedSlugs = array_column($rows, 'slug');

$gbDefaults = gblog_layout_recommended();
foreach ($rows as $__row) {
    if ($__row['slug'] === '_home') continue;
    $__set = $gbDefaults[$__row['type']] ?? $gbDefaults['article'];
    foreach (['home', 'list', 'detail'] as $__rk) {
        if (!isset($catalog[$__rk][$__set[$__rk]])) continue;
        if (gblog_layout_selected($__rk, $__row['slug']) === '') {
            save_setting('layout:' . $__rk . ':' . $__row['slug'], $__set[$__rk]);
        }
    }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = '表单校验失败。';
    } else {
            $posted = $_POST['layout'] ?? [];
            $n = 0;
            $lbad = [];
            if (is_array($posted)) {
                foreach ($posted as $role => $bySlug) {
                    if (!isset($catalog[$role]) || !is_array($bySlug)) continue;
                    foreach ($bySlug as $slug => $id) {
                        $slug = (string)$slug;
                        if (!in_array($slug, $allowedSlugs, true)) continue;
                        $raw = trim((string)$id);
                        $id  = gblog_layout_id_from_input($role, $raw);
                        if ($raw !== '' && !isset($catalog[$role][$id])) {
                            $lbad[] = $roles[$role] . '（' . $slug . '）：模板「' . $raw . '」不存在';
                            continue;
                        }
                        save_setting('layout:' . $role . ':' . $slug, $id);
                        $n++;
                    }
                }
            }
            save_setting('layout:home:_home', '');
            save_setting('layout:list:_home', '');
            save_setting('layout:detail:_home', '');

            $postedPage = $_POST['page_template'] ?? [];
            $pn   = 0;
            $pbad = [];
            if (is_array($postedPage)) {
                foreach ($postedPage as $view => $id) {
                    $view = (string)$view;
                    if (!isset($pageViews[$view])) continue;
                    $id = trim((string)$id);
                    if ($id === $view || $id === '') {
                        save_setting('page_template:' . $view, '');
                        $pn++;
                        continue;
                    }
                    $id = gblog_page_template_from_input($id);
                    if ($id === $view) $id = '';   // 解析后正好是本视图内置默认 → 存空，不落冗余值
                    if ($id === '') {
                        save_setting('page_template:' . $view, '');
                        $pn++;
                        continue;
                    }
                    if (!preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $id) || !isset($scanAll[$id])) {
                        $pbad[] = $pageViews[$view] . '：模板「' . $id . '」不存在';
                        continue;
                    }
                    if (!gblog_page_template_matches($id, $view)) {
                        $pbad[] = $pageViews[$view] . '：' . $scanAll[$id] . ' 未声明 View: ' . $view;
                        continue;
                    }
                    save_setting('page_template:' . $view, $id);
                    $pn++;
                }
            }

            $stMode = db()->prepare('UPDATE sites SET home_mode = ? WHERE slug = ?');
            foreach ($allowedSlugs as $slug) {
                if ($slug === '_home') continue;
                $agg  = is_array($posted['aggregate'] ?? null) ? trim((string)($posted['aggregate'][$slug] ?? '')) : '';
                $agg  = gblog_layout_id_from_input('aggregate', $agg);
                $mode = ($agg !== '' && isset($catalog['aggregate'][$agg])) ? 'aggregate' : 'default';
                $stMode->execute([$mode, $slug]);
                foreach ($rows as &$rowRef) {
                    if ($rowRef['slug'] === $slug) {
                        $rowRef['home_mode'] = $mode;
                    }
                }
                unset($rowRef);
            }
            $msg = '已保存 ' . $n . ' 项模板设置、' . $pn . ' 项页面模板。栏目模板选中专题款会覆盖列表页，刷新前台即可看到变化。';
            if ($lbad) {
                $msg .= ' 未保存 ' . count($lbad) . ' 项：' . implode('；', $lbad) . '。';
            }
            if ($pbad) {
                $msg .= ' 未保存 ' . count($pbad) . ' 项：' . implode('；', $pbad) . '。模板需在文件头写 View: 对应页面，或按 视图名-xxx.php 命名。';
            }
        }
    }
require_once __DIR__ . '/../tabs.php';
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html('', $msg));
?>
<div class="card card-connected">
  <p style="color:var(--muted);margin-bottom:8px;"><b>一行配齐一个栏目</b>：皮肤换配色；<b>栏目模板</b>是栏目页的顶级形态，留空＝使用列表页（占位文字为当前生效的列表款），填了＝专题形态并覆盖列表页；<b>列表页／分类页／内容页</b>各按栏目选一款。全站首页固定聚合。</p>
  <p style="color:var(--muted);margin-bottom:8px;">每个格子都可以<b>下拉选</b>，也可以<b>直接输入</b>：模板 ID（如 <code>software_category</code>）、文件名（如 <code>list-software</code>）或模板显示名（如「软件分类」）。输入框下的灰字是它在主题里的实际文件，文件不存在会标红。模板放在主题的哪个子目录、子目录叫什么名字都不影响识别——认的是文件名。</p>
  <p style="color:#c0392b;margin-bottom:16px;"><b>注意</b>：「自定义 CSS」tab 的样式最后加载，选择器写错会覆盖主题模板与页面模板，导致页面错乱。</p>

  <form method="post" action="<?= e($tabUrl) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php foreach ($roles as $rk => $rn): ?>
    <datalist id="gb-lay-<?= e($rk) ?>">
      <?php foreach ($catalog[$rk] as $id => $name): ?>
      <option value="<?= e($id) ?>" label="<?= e($name) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <?php endforeach; ?>
    <?php foreach ($pageViews as $pv => $pvName): ?>
    <datalist id="gb-page-<?= e($pv) ?>">
      <?php foreach (gblog_page_templates_for($pv) as $base => $label): ?>
      <option value="<?= e($base) ?>" label="<?= e($label) ?><?= e(' · ' . gblog_tpl_dir_label($base)) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <?php endforeach; ?>
    <div style="overflow-x:auto;">
      <table class="data" style="width:100%;">
        <thead>
          <tr>
            <th style="text-align:left;">栏目</th>
            <?php foreach ($roles as $rn): ?>
              <th style="text-align:left;"><?= e($rn) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <?php $isHomeRow = ($r['type'] === '_home'); ?>
          <tr>
            <td style="white-space:nowrap;">
              <b><?= e($r['name']) ?></b><br>
              <code style="font-size:11px;color:var(--muted);"><?= e($r['slug']) ?></code>
            </td>
            <?php foreach ($roles as $rk => $rn): ?>
            <?php
              if ($isHomeRow && $rk !== 'aggregate') {
                  echo '<td style="color:var(--muted);">—</td>';
                  continue;
              }
              $cur = gblog_layout_selected($rk, $r['slug']);
              $aggCell = ($rk === 'aggregate' && !$isHomeRow);
              if ($aggCell && $r['home_mode'] !== 'aggregate') $cur = '';
              if (!$aggCell && $cur === '') {
                  $defSet = $gbDefaults[$r['type']] ?? $gbDefaults['article'];
                  $cur = $defSet[$rk] ?? '';
                  if (!isset($catalog[$rk][$cur])) $cur = (string)array_key_first($catalog[$rk]);
              }
            ?>
            <td style="min-width:170px;">
              <?php
                $__ph = $aggCell ? '留空＝使用列表页（' . ($catalog['home'][gblog_layout_selected('home', $r['slug'])] ?? '通用列表') . '）' : '可下拉选，也可填模板 ID';
              ?>
              <input list="gb-lay-<?= e($rk) ?>" name="layout[<?= e($rk) ?>][<?= e($r['slug']) ?>]" value="<?= e($cur) ?>" placeholder="<?= e($__ph) ?>" autocomplete="off" spellcheck="false" style="width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);font-size:13px;font-family:inherit;">
              <?php if ($cur !== ''): ?>
                <?php $__f = gblog_tpl_path(gblog_layout_template_name($rk, $cur)); ?>
                <div style="font-size:11px;color:var(--muted);margin-top:4px;word-break:break-all;"><?= $__f !== '' ? e('→ ' . preg_replace('#^.*?/usr/themes/[^/]+/#', '', str_replace('\\', '/', $__f))) : '<span style="color:#c0392b;">文件不存在</span>' ?></div>
              <?php endif; ?>
              <?php if ($rk === 'aggregate' && !$isHomeRow && $r['home_mode'] === 'aggregate'): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:4px;">专题形态 · 已覆盖列表页</div>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);">
      <p style="margin-bottom:10px;"><b>页面模板</b></p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;">
        <?php foreach ($pageViews as $pv => $pvName): ?>
        <?php
          $curPage = function_exists('gblog_page_template_selected') ? gblog_page_template_selected($pv) : '';
          $defName = (string)($scanAll[$pv] ?? '');
          if ($defName === $pv) $defName = '';
        ?>
        <label style="display:block;font-size:13px;">
          <span style="display:block;margin-bottom:4px;color:var(--text);"><?= e($pvName) ?></span>
          <input list="gb-page-<?= e($pv) ?>" name="page_template[<?= e($pv) ?>]" value="<?= e($curPage) ?>" placeholder="留空＝内置默认<?= $defName !== '' ? '（' . e($defName) . '）' : '' ?>" autocomplete="off" spellcheck="false" style="width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);font-size:13px;font-family:inherit;">
          <?php if ($curPage !== ''): ?>
            <span style="display:block;margin-top:4px;font-size:11px;color:var(--muted);word-break:break-all;"><?= $curPage !== '' ? e('→ ' . (gblog_tpl_dir_label($curPage) ?: '根目录') . '/' . $curPage . '.php') : '' ?></span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-top:12px;">
      <button type="submit" class="btn btn-primary">保存全部</button>
    </div>
  </form>
</div>
