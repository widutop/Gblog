<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$pdo    = db();
$sites  = get_all_sites();
$tabUrl = gblog_admin_url('system/sites.php?tab=categories');
$filterSite = (int)($_GET['site'] ?? 0);
$filterQs   = $filterSite > 0 ? '&site=' . $filterSite : '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $siteId = (int)($_POST['site_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($siteId <= 0 || $name === '') {
                $error = '请选择栏目并填写分类名称。';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (site_id, name) VALUES (?,?)");
                    $stmt->execute([$siteId, $name]);
                    $success = '分类已添加。';
                } catch (PDOException $e) {
                    $error = '添加失败：该栏目下已存在同名分类。';
                }
            }
        } elseif ($action === 'edit') {
            $cid = (int)($_POST['id'] ?? 0);
            $siteId = (int)($_POST['site_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($cid <= 0 || $siteId <= 0 || $name === '') {
                $error = '请选择栏目并填写分类名称。';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET site_id = ?, name = ? WHERE id = ?");
                    $stmt->execute([$siteId, $name, $cid]);
                    $success = '分类已更新。';
                } catch (PDOException $e) {
                    $error = '更新失败：该栏目下已存在同名分类。';
                }
            }
        } elseif ($action === 'delete') {
            $cid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
            $success = '分类已删除（该分类下的文章不受影响）。';
        }
    }
}

$editCat = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $st->execute([$editId]);
    $editCat = $st->fetch() ?: null;
}

$sql = "SELECT c.*, s.name AS site_name FROM categories c LEFT JOIN sites s ON s.id = c.site_id";
if ($filterSite > 0) {
    $sql .= " WHERE c.site_id = ?";
    $st = $pdo->prepare($sql . " ORDER BY s.sort_order, c.id");
    $st->execute([$filterSite]);
    $cats = $st->fetchAll();
} else {
    $cats = $pdo->query($sql . " ORDER BY s.sort_order, c.id")->fetchAll();
}
$selectedSite = $editCat ? (int)$editCat['site_id'] : $filterSite;
require_once __DIR__ . '/../tabs.php';
$drawerOpen = ((string)($_GET['drawer'] ?? '') === '1')
    || $editCat !== null
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['action'] ?? ''), ['add', 'edit'], true));
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html($error, $success));
?>
<div class="gb-drawer" id="cat-drawer" data-open="<?= $drawerOpen ? '1' : '0' ?>">
  <div class="drawer-head">
    <h3><?= $editCat ? ('编辑分类 #' . (int)$editCat['id']) : '添加分类' ?></h3>
    <button type="button" class="drawer-close" data-drawer-close aria-label="关闭">✕</button>
  </div>
  <form method="post" action="<?= e($tabUrl) ?>&amp;drawer=1<?= $editCat ? '&amp;edit=' . (int)$editCat['id'] : '' ?><?= e($filterQs) ?>" class="drawer-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'add' ?>">
    <?php if ($editCat): ?><input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>"><?php endif; ?>
    <div class="form-field" style="min-width:160px;">
      <label>所属栏目</label>
      <select name="site_id">
        <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $selectedSite === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field" style="min-width:180px;">
      <label>分类名称 *</label>
      <input type="text" name="name" value="<?= $editCat ? e($editCat['name']) : '' ?>" required>
    </div>
    <button type="submit" class="btn btn-primary"><?= $editCat ? '保存修改' : '添加' ?></button>
    <?php if ($editCat): ?><a class="btn" href="<?= e($tabUrl . $filterQs) ?>">取消编辑</a><?php endif; ?>
  </form>
</div>

<div class="card card-connected">
  <div class="list-head">
    <h2><?= $filterSite > 0 ? '该栏目的分类' : '全部分类' ?>（<?= count($cats) ?>）</h2>
    <div class="list-head-actions">
      <button type="button" class="btn btn-primary" data-drawer-toggle="cat-drawer" data-label-add="＋ 添加分类" data-label-close="收起表单"><?= $drawerOpen ? '收起表单' : '＋ 添加分类' ?></button>
    <form method="get" action="<?= gblog_admin_url('system/sites.php') ?>" style="display:flex;align-items:center;gap:8px;margin:0;">
      <input type="hidden" name="tab" value="categories">
      <label style="font-size:13px;color:var(--muted);white-space:nowrap;">按栏目查看</label>
      <select name="site" onchange="this.form.submit()" style="min-width:140px;">
        <option value="0">全部分类</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $filterSite === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm">查看</button>
    </form>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>名称</th><th>栏目</th><th>文章数</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="4" style="color:#6b7280;">暂无分类。</td></tr>
      <?php else: ?>
        <?php foreach ($cats as $c): ?>
          <?php $cStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category_id = ?"); $cStmt->execute([$c['id']]); $cnt = (int)$cStmt->fetchColumn(); ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td><?= e($c['site_name'] ?? '-') ?></td>
            <td><?= $cnt ?></td>
            <td>
              <a class="btn btn-sm" href="<?= e($tabUrl . '&drawer=1&edit=' . (int)$c['id'] . $filterQs) ?>">编辑</a>
              <form method="post" action="<?= e($tabUrl . $filterQs) ?>" onsubmit="return confirm('删除该分类？分类下的文章不会被删除。');" style="display:inline-block;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
