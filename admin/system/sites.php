<?php
require_once __DIR__ . '/../inc/icons.php';
$gbTabs = ['sites' => gblog_admin_icon('sites') . ' 栏目管理', 'categories' => gblog_admin_icon('categories') . ' 分类管理', 'tags' => gblog_admin_icon('tags') . ' 标签管理'];
$gbTab  = (string)($_GET['tab'] ?? 'sites');
if (!isset($gbTabs[$gbTab])) { $gbTab = 'sites'; }
$page_title = '栏目管理';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/tabs.php';
if ($gbTab !== 'sites') {
    require __DIR__ . '/../inc/sections/' . $gbTab . '.php';
    require __DIR__ . '/../inc/footer.php';
    return;
}

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $menu_name = trim((string)($_POST['menu_name'] ?? ''));
            $type = gblog_pick($_POST, 'type', ['article','song','software','image','novel'], 'article');
            $domain = strtolower(trim((string)($_POST['domain'] ?? '')));
            $homeMode = gblog_pick($_POST, 'home_mode', ['default','aggregate'], 'default');

            $existing = null;
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM sites WHERE id=?");
                $old->execute([$id]);
                $existing = $old->fetch() ?: null;
                $old->closeCursor();
                if (!$existing) {
                    $error = '栏目不存在。';
                } elseif (gblog_site_is_builtin($existing)) {
                    $name   = (string)$existing['name'];
                    $slug   = (string)$existing['slug'];
                    $type   = (string)$existing['type'];
                    $domain = strtolower((string)($existing['domain'] ?? ''));
                }
            }
            if ($error === '' && $name === '') {
                $error = '请填写栏目名称。';
            } elseif ($error === '') {
                // slug 留空时按类型前缀自动生成（文章post / 软件soft / 音乐music / 图片image / 小说novel），
                // 不再用中文名转拼音；编辑时留空则保留原 slug，避免改 URL 导致旧链接失效。
                // slug 同时是内容页 URL 前缀（如 ganwu/101.html），必须用 rewrite 能识别的字符，
                // 且不能占用 column/category/tag/chapter 等专用规则名，否则生成的前台链接会 404。
                if ($slug === '') {
                    $slug = ($id > 0 && $existing) ? (string)$existing['slug'] : gblog_type_prefix($type);
                }
                $slugBad = '栏目 slug 只能用英文字母、数字、-、_（需以字母或数字开头），'
                         . '且不能占用 column / category / tag / chapter / novel-数字 等系统保留名；'
                         . '它会作为内容页 URL 前缀，如 ganwu/101.html。';
                if (!function_exists('gblog_site_slug_is_url_safe') || !gblog_site_slug_is_url_safe($slug)) {
                    $error = $slugBad;
                }
                if ($error === '') {
                    $slug = gblog_unique_site_slug($slug, $id > 0 ? $id : 0);
                    // 自动追加序号后仍需复检：用户填 novel 时内置栏目已占用，
                    // 会自动变成 novel-2，恰好撞上章节页专用规则。
                    if (!gblog_site_slug_is_url_safe($slug)) $error = $slugBad;
                }
                if ($error === '') {
                    try {
                        if ($id > 0) {
                            $enabled = (int)$existing['enabled'];
                            $sort = (int)$existing['sort_order'];
                            $stmt = $pdo->prepare(
                                "UPDATE sites SET name=?, slug=?, description=?, type=?, menu_name=?, domain=?, home_mode=?, enabled=?, sort_order=? WHERE id=?"
                            );
                            $stmt->execute([$name, $slug, $desc ?: null, $type, $menu_name ?: $name, $domain ?: null, $homeMode, $enabled, $sort, $id]);
                            $success = '栏目已保存。';
                        } else {
                            $stmt = $pdo->prepare(
                                "INSERT INTO sites (name, slug, description, type, menu_name, domain, home_mode, enabled, sort_order, show_in_menu) VALUES (?,?,?,?,?,?,?,?,?,1)"
                            );
                            $stmt->execute([$name, $slug, $desc ?: null, $type, $menu_name ?: $name, $domain ?: null, $homeMode, 1, 0]);
                            $success = '栏目已添加。';
                        }
                    } catch (PDOException $e) {
                        $error = '保存失败：栏目 slug 或域名已被占用。';
                    }
                }
            }
        } elseif ($action === 'save_sort') {
            $sid = (int)($_POST['id'] ?? 0);
            $sort = (int)($_POST['sort_order'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare("UPDATE sites SET sort_order=? WHERE id=?")->execute([$sort, $sid]);
                $success = '排序已更新。';
            }
        } elseif ($action === 'toggle_enabled') {
            $sid = (int)($_POST['id'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare("UPDATE sites SET enabled = 1 - enabled WHERE id=?")->execute([$sid]);
                $success = '状态已切换。';
            }
        } elseif ($action === 'toggle_menu') {
            $sid = (int)($_POST['id'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare("UPDATE sites SET show_in_menu = 1 - show_in_menu WHERE id=?")->execute([$sid]);
                $success = '菜单显示已切换。';
            }
        } elseif ($action === 'delete_site') {
            $sid = (int)($_POST['id'] ?? 0);
            if ($sid <= 0) {
                $error = '参数无效。';
            } else {
                $st = $pdo->prepare("SELECT id, name, is_builtin FROM sites WHERE id = ?");
                $st->execute([$sid]);
                $row = $st->fetch();
                $st->closeCursor();
                if (!$row) {
                    $error = '栏目不存在。';
                } elseif (gblog_site_is_builtin($row)) {
                    $error = '系统内置栏目不能删除。';
                } else {
                    $ps = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE site_id = ?");
                    $ps->execute([$sid]);
                    $postCount = (int)$ps->fetchColumn();
                    $ps->closeCursor();
                    $cs = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE site_id = ?");
                    $cs->execute([$sid]);
                    $catCount = (int)$cs->fetchColumn();
                    $cs->closeCursor();
                    if ($postCount > 0 || $catCount > 0) {
                        $error = "该栏目下还有 {$postCount} 篇文章、{$catCount} 个分类，请先转移或清空后再删除。";
                    } else {
                        $del = $pdo->prepare("DELETE FROM sites WHERE id = ? AND is_builtin = 0");
                        $del->execute([$sid]);
                        $success = $del->rowCount() > 0 ? '栏目已删除。' : '栏目不存在或不可删除。';
                    }
                }
            }
        }
    }
}

$sites = get_all_sites();
$editId = (int)($_GET['edit'] ?? 0);
$editSite = null;
if ($editId > 0) {
    foreach ($sites as $s) {
        if ((int)$s['id'] === $editId) { $editSite = $s; break; }
    }
}
?>
<?php
$drawerOpen = ((string)($_GET['drawer'] ?? '') === '1')
    || $editSite !== null
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save');
$isBuiltin = $editSite !== null && gblog_site_is_builtin($editSite);
$lockAttr  = $isBuiltin ? 'disabled' : '';
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html($error, $success));
?>
<div class="gb-drawer" id="site-drawer" data-open="<?= $drawerOpen ? '1' : '0' ?>">
  <div class="drawer-head">
    <h3><?= $editSite ? ($isBuiltin ? '编辑内置栏目：' : '编辑栏目：') . e($editSite['name']) : '添加栏目' ?></h3>
    <button type="button" class="drawer-close" data-drawer-close aria-label="关闭">✕</button>
  </div>
  <form method="post" action="<?= gblog_admin_url('system/sites.php?drawer=1') ?><?= $editSite ? '&amp;edit=' . (int)$editSite['id'] : '' ?>" id="site-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editSite ? (int)$editSite['id'] : 0 ?>">
    <?php if ($isBuiltin): ?>
        <div class="gb-notice">系统内置栏目：仅菜单名称与栏目简介可修改，不可删除。</div>
    <?php endif; ?>
    <div class="form-grid">
      <div class="form-row">
        <div class="form-field">
          <label>栏目名称（后台显示）*<?= $isBuiltin ? ' · 内置固定' : '' ?></label>
          <input type="text" name="name" value="<?= e($editSite['name'] ?? '') ?>" <?= $lockAttr ?> required>
        </div>
        <div class="form-field">
          <label>菜单名称（首页导航显示）</label>
          <input type="text" name="menu_name" value="<?= e($editSite['menu_name'] ?? '') ?>" placeholder="留空用栏目名称">
        </div>
        <div class="form-field">
          <label>slug（URL别名，留空自动）<?= $isBuiltin ? ' · 内置固定' : '' ?></label>
          <input type="text" name="slug" value="<?= e($editSite['slug'] ?? '') ?>" <?= $lockAttr ?> placeholder="留空按类型生成：文章post / 软件soft / 音乐music / 图片image / 小说novel">
        </div>
        <div class="form-field">
          <label>绑定域名（可空）<?= $isBuiltin ? ' · 内置固定' : '' ?></label>
          <input type="text" name="domain" value="<?= e($editSite['domain'] ?? '') ?>" <?= $lockAttr ?> placeholder="如 music.yourdomain.com">
        </div>
        <div class="form-field">
          <label>栏目类型<?= $isBuiltin ? ' · 内置固定' : '' ?></label>
          <select name="type" <?= $lockAttr ?>>
            <option value="article" <?= ($editSite['type'] ?? 'article') === 'article' ? 'selected' : '' ?>>文章（普通博客）</option>
            <option value="song" <?= ($editSite['type'] ?? '') === 'song' ? 'selected' : '' ?>>音乐（歌曲）</option>
            <option value="software" <?= ($editSite['type'] ?? '') === 'software' ? 'selected' : '' ?>>软件（下载站）</option>
            <option value="image" <?= ($editSite['type'] ?? '') === 'image' ? 'selected' : '' ?>>多媒体（图片/视频图集）</option>
            <option value="novel" <?= ($editSite['type'] ?? '') === 'novel' ? 'selected' : '' ?>>小说（作品+章节）</option>
          </select>
        </div>
        <div class="form-field">
          <label>首页形态</label>
          <select name="home_mode">
            <option value="default" <?= ($editSite['home_mode'] ?? 'default') === 'default' ? 'selected' : '' ?>>默认（栏目自有布局）</option>
            <option value="aggregate" <?= ($editSite['home_mode'] ?? '') === 'aggregate' ? 'selected' : '' ?>>聚合首页（复用全站版式）</option>
          </select>
          <small style="color:#6b7280;display:block;margin-top:4px;">选「聚合首页」时，该栏目页会复用全站聚合首页的版式与模块，仅换取数。</small>
        </div>
      </div>
      <div class="form-field full">
        <label>栏目简介</label>
        <input type="text" name="description" value="<?= e($editSite['description'] ?? '') ?>" placeholder="前台显示的站点简介">
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
      <button type="submit" class="btn btn-primary">保存栏目</button>
      <?php if ($editSite): ?><a class="btn" href="<?= gblog_admin_url('system/sites.php') ?>">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card card-connected">
  <div class="list-head">
    <h2>全部栏目（<?= count($sites) ?>）</h2>
    <div class="list-head-actions">
      <button type="button" class="btn btn-primary" data-drawer-toggle="site-drawer" data-label-add="＋ 添加栏目" data-label-close="收起表单"><?= $drawerOpen ? '收起表单' : '＋ 添加栏目' ?></button>
    </div>
  </div>
    <p class="gb-notice">系统内置栏目不可删除；用户新建栏目在无内容时可删除。</p>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>栏目</th><th>类型</th><th>菜单名</th><th>slug</th><th>绑定域名</th><th>首页形态</th><th>文章数</th><th>排序</th><th>首页菜单</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$sites): ?>
        <tr><td colspan="11" style="color:#6b7280;">暂无栏目。</td></tr>
      <?php else: ?>
        <?php foreach ($sites as $s): ?>
          <?php $pStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE site_id = ?"); $pStmt->execute([$s['id']]); ?>
          <tr>
            <td><b><?= e($s['name']) ?></b><?= gblog_site_is_builtin($s) ? ' <span class="badge badge-builtin" title="程序内置栏目：身份字段只读、不可删除">系统内置</span>' : '' ?></td>
            <td>
              <?php $tm = gblog_site_type_meta((string)($s['type'] ?? 'article')); ?>
              <?= gblog_admin_icon($tm['icon']) ?> <?= e($tm['label']) ?>
            </td>
            <td><?= e($s['menu_name'] ?: $s['name']) ?></td>
            <td><code><?= e($s['slug']) ?></code></td>
            <td><?= e($s['domain'] ?? '-') ?></td>
            <td><?= ($s['home_mode'] ?? 'default') === 'aggregate'
              ? '<span class="badge badge-on" title="该栏目页复用全站聚合首页版式">聚合首页</span>'
              : '<span style="color:#6b7280;">默认</span>' ?></td>
            <td><?= (int)$pStmt->fetchColumn() ?></td>
            <td>
              <form method="post" action="<?= gblog_admin_url('system/sites.php') ?>" style="display:flex;gap:4px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_sort">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>" style="width:60px;" onchange="this.form.submit()">
              </form>
            </td>
            <td>
              <form method="post" action="<?= gblog_admin_url('system/sites.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_menu">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="badge <?= (int)($s['show_in_menu'] ?? 1) === 1 ? 'badge-on' : 'badge-off' ?>" style="border:none;cursor:pointer;<?= (int)$s['enabled'] === 1 ? '' : 'opacity:.45;' ?>" title="只控制首页导航是否显示本栏目；不影响内容发布与首页调用。栏目停用后菜单始终不显示">
                  <?= (int)($s['show_in_menu'] ?? 1) === 1 ? '显示' : '隐藏' ?>
                </button>
              </form>
            </td>
            <td>
              <form method="post" action="<?= gblog_admin_url('system/sites.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_enabled">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="badge <?= (int)$s['enabled'] === 1 ? 'badge-enabled' : 'badge-disabled' ?>" style="border:none;cursor:pointer;" title="栏目总开关：停用后导航、栏目页、首页/侧栏内容全部不显示，也不能再发布内容到本栏目">
                  <?= (int)$s['enabled'] === 1 ? '启用' : '停用' ?>
                </button>
              </form>
            </td>
            <td>
              <a class="btn btn-sm" href="<?= gblog_admin_url('system/sites.php?drawer=1&amp;edit=' . (int)$s['id']) ?>">编辑</a>
              <?php if (!gblog_site_is_builtin($s)): ?>
                <form method="post" action="<?= gblog_admin_url('system/sites.php') ?>" style="display:inline;margin-left:4px;" onsubmit="return confirm('确定删除栏目「<?= e($s['name']) ?>」？仅当栏目下没有文章和分类时才能删除，删除后不可恢复。');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_site">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// 菜单名默认与栏目名称相同：栏目名输入时自动带入（用户手动改过则不再覆盖）；
// slug 占位符随栏目类型实时更新，明确“留空即按类型前缀生成”。
(function () {
  var nameEl = document.querySelector('#site-form [name="name"]');
  var menuEl = document.querySelector('#site-form [name="menu_name"]');
  var typeEl = document.querySelector('#site-form [name="type"]');
  var slugEl = document.querySelector('#site-form [name="slug"]');
  if (nameEl && menuEl) {
    nameEl.addEventListener('input', function () {
      if (menuEl.dataset.auto === '1' || menuEl.value.trim() === '') {
        menuEl.value = nameEl.value;
        menuEl.dataset.auto = '1';
      }
    });
    menuEl.addEventListener('input', function () {
      menuEl.dataset.auto = menuEl.value.trim() === '' ? '1' : '0';
    });
  }
  if (typeEl && slugEl) {
    var prefix = { article: 'post', song: 'music', software: 'soft', image: 'image', novel: 'novel' };
    function upd() { slugEl.placeholder = '留空按类型生成：' + (prefix[typeEl.value] || 'post'); }
    typeEl.addEventListener('change', upd);
    upd();
  }
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
