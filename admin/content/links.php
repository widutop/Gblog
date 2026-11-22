<?php
$page_title = '友情链接';
require_once __DIR__ . '/../inc/header.php';

$error = '';
$success = '';
$categoryFormOpen = false;
$categoryFormId = 0;
$categoryFormName = '';
$categoryFormSort = 0;
$activeLinkView = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $url  = trim((string)($_POST['url'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $icon = trim((string)($_POST['icon_url'] ?? ''));
            $sort = (int)($_POST['sort_order'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 1);
            if ($name === '' || $url === '') {
                $error = '请填写网站名称和网址。';
            } else {
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'http://' . $url;
                }
                try {
                    save_link($name, $url, $desc, $sort, $id, $icon, $categoryId);
                    $success = '友情链接已保存。';
                } catch (Throwable $e) {
                    $error = '保存失败，请检查分类名称是否重复或数据库状态。';
                }
            }
        } elseif ($action === 'delete') {
            delete_link((int)($_POST['id'] ?? 0));
            $success = '友情链接已删除。';
        } elseif ($action === 'category_save') {
            $categoryFormId = (int)($_POST['category_id'] ?? 0);
            $categoryFormName = trim((string)($_POST['category_name'] ?? ''));
            $categoryFormSort = (int)($_POST['category_sort'] ?? 0);
            $activeLinkView = (string)($_POST['return_view'] ?? 'category-manage');
            $categoryFormOpen = true;
            if ($categoryFormName === '') {
                $error = '请填写分类名称。';
            } else {
                try {
                    save_link_category($categoryFormName, $categoryFormSort, $categoryFormId);
                    $success = $categoryFormId > 0 ? '分类已更新。' : '分类已添加。';
                    $categoryFormOpen = false;
                    $categoryFormId = 0;
                    $categoryFormName = '';
                    $categoryFormSort = 0;
                } catch (Throwable $e) {
                    $error = '分类保存失败，请检查名称是否重复。';
                }
            }
        } elseif ($action === 'category_delete') {
            $activeLinkView = (string)($_POST['return_view'] ?? 'category-manage');
            if (delete_link_category((int)($_POST['category_id'] ?? 0))) {
                $success = '分类已删除，原分类链接已移至“未分类”。';
            } else {
                $error = '“未分类”不能删除。';
            }
        }
    }
}

$categories = get_link_categories();
$links = get_links();
$editId = (int)($_GET['edit'] ?? 0);
$editLink = null;
if ($editId > 0) {
    foreach ($links as $l) {
        if ((int)$l['id'] === $editId) { $editLink = $l; break; }
    }
}
$drawerOpen = ((string)($_GET['drawer'] ?? '') === '1')
    || $editLink !== null
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save');
$categoryDrawerOpen = $categoryFormOpen;
?>
<div class="gb-drawer" id="link-drawer" data-open="<?= $drawerOpen ? '1' : '0' ?>">
  <div class="drawer-head">
    <h3><?= $editLink ? '编辑：' . e($editLink['name']) : '添加友情链接' ?></h3>
    <button type="button" class="drawer-close" data-drawer-close aria-label="关闭">✕</button>
  </div>
  <form method="post" action="<?= gblog_admin_url('content/links.php?drawer=1') ?><?= $editLink ? '&amp;edit=' . (int)$editLink['id'] : '' ?>" id="link-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editLink ? (int)$editLink['id'] : 0 ?>">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:140px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">网站名称 *</label>
        <input type="text" name="name" value="<?= e($editLink['name'] ?? '') ?>" required class="admin-input" style="width:100%;">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">网址 *</label>
        <input type="text" name="url" value="<?= e($editLink['url'] ?? '') ?>" placeholder="https://example.com" required class="admin-input" style="width:100%;">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">简介</label>
        <input type="text" name="description" value="<?= e($editLink['description'] ?? '') ?>" placeholder="一句话描述（可空）" class="admin-input" style="width:100%;">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">图标 URL</label>
        <input type="text" name="icon_url" value="<?= e($editLink['icon_url'] ?? '') ?>" placeholder="https://favicon" class="admin-input" style="width:100%;">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">分类</label>
        <select name="category_id" class="admin-input" style="width:100%;">
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>" <?= (int)($editLink['category_id'] ?? 1) === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="width:80px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">排序</label>
        <input type="number" name="sort_order" value="<?= (int)($editLink['sort_order'] ?? 0) ?>" class="admin-input" style="width:100%;">
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
      <button type="submit" class="btn btn-primary">保存</button>
      <?php if ($editLink): ?><a class="btn" href="<?= gblog_admin_url('content/links.php') ?>">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="gb-drawer" id="link-category-drawer" data-open="<?= $categoryDrawerOpen ? '1' : '0' ?>">
  <div class="drawer-head">
    <h3 data-category-drawer-title><?= $categoryFormId > 0 ? '编辑链接分类' : '添加链接分类' ?></h3>
    <button type="button" class="drawer-close" data-drawer-close aria-label="关闭">✕</button>
  </div>
  <form method="post" action="<?= gblog_admin_url('content/links.php') ?>" id="link-category-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="category_save">
    <input type="hidden" name="category_id" value="<?= (int)$categoryFormId ?>">
    <input type="hidden" name="return_view" value="<?= e($activeLinkView) ?>">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:180px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">分类名称 *</label>
        <input type="text" name="category_name" maxlength="100" required value="<?= e($categoryFormName) ?>" class="admin-input" style="width:100%;" data-category-name>
      </div>
      <div style="width:110px;">
        <label style="font-size:13px;font-weight:600;margin-bottom:4px;display:block;">排序</label>
        <input type="number" name="category_sort" value="<?= (int)$categoryFormSort ?>" class="admin-input" style="width:100%;" data-category-sort>
      </div>
      <button type="submit" class="btn btn-primary">保存分类</button>
    </div>
  </form>
</div>

<div class="card">
  <nav class="tab-bar link-admin-tabs" aria-label="友情链接分类">
    <button type="button" class="tab-btn <?= $activeLinkView === 'all' ? 'on' : '' ?>" data-link-view="all"><?= gblog_admin_icon('all') ?> 友情链接（<?= count($links) ?>）</button>
    <?php foreach ($categories as $category): ?>
      <button type="button" class="tab-btn <?= $activeLinkView === (string)$category['id'] ? 'on' : '' ?>" data-link-view="<?= (int)$category['id'] ?>"><?= gblog_admin_icon('categories') ?> <?= e($category['name']) ?>（<?= (int)$category['link_count'] ?>）</button>
    <?php endforeach; ?>
    <button type="button" class="tab-btn <?= $activeLinkView === 'category-manage' ? 'on' : '' ?>" data-link-view="category-manage"><?= gblog_admin_icon('categories') ?> 链接分类</button>
    <span class="link-admin-tab-actions">
      <?php if ($error): ?><span class="alert alert-error"><?= e($error) ?></span><?php endif; ?>
      <?php if ($success): ?><span class="alert alert-success"><?= e($success) ?></span><?php endif; ?>
      <button type="button" class="btn" data-drawer-toggle="link-category-drawer" data-label-add="＋ 添加分类" data-label-close="收起分类表单">＋ 添加分类</button>
      <button type="button" class="btn btn-primary" data-drawer-toggle="link-drawer" data-label-add="＋ 添加链接" data-label-close="收起表单"><?= $drawerOpen ? '收起表单' : '＋ 添加链接' ?></button>
    </span>
  </nav>
  <div class="link-admin-tab-panel" data-link-panel="category-manage" hidden>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>名称</th><th>排序</th><th>条数</th><th>编辑</th><th>删除</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
          <tr>
            <td><b><?= e($category['name']) ?></b></td>
            <td><?= (int)$category['sort_order'] ?></td>
            <td><?= (int)$category['link_count'] ?></td>
            <td>
              <button type="button" class="btn btn-sm" data-category-edit data-id="<?= (int)$category['id'] ?>" data-name="<?= e($category['name']) ?>" data-sort="<?= (int)$category['sort_order'] ?>">编辑</button>
            </td>
            <td>
              <?php if ((int)$category['id'] !== 1): ?>
                <form method="post" action="<?= gblog_admin_url('content/links.php') ?>" onsubmit="return confirm('删除此分类？其中的链接将移至“未分类”。');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="category_delete">
                  <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                  <input type="hidden" name="return_view" value="<?= e($activeLinkView) ?>">
                  <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="table-wrap" data-link-table>
    <table class="data">
      <thead><tr><th>图标</th><th>网站</th><th>分类</th><th>网址</th><th>简介</th><th>排序</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$links): ?>
        <tr data-link-empty-row><td colspan="7" style="color:#6b7280;">当前分类暂无友情链接。</td></tr>
      <?php else: ?>
        <?php foreach ($links as $l): ?>
          <tr data-link-category="<?= (int)($l['category_id'] ?? 1) ?>">
            <td><?php if (!empty($l['icon_url'])): ?><img src="<?= e($l['icon_url']) ?>" style="width:20px;height:20px;border-radius:4px;" onerror="this.style.display='none'"><?php else: ?>-<?php endif; ?></td>
            <td><b><?= e($l['name']) ?></b></td>
            <td><?= e($l['category_name'] ?? '未分类') ?></td>
            <td><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['url']) ?></a></td>
            <td><?= e($l['description'] ?? '-') ?></td>
            <td><?= (int)$l['sort_order'] ?></td>
            <td>
              <a class="btn btn-sm" href="<?= gblog_admin_url('content/links.php?drawer=1&amp;edit=' . (int)$l['id']) ?>">编辑</a>
              <form method="post" action="<?= gblog_admin_url('content/links.php') ?>" style="display:inline;"
                    onsubmit="return confirm('删除友情链接「<?= e($l['name']) ?>」？');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
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
<script>
(function(){
  var tabs=document.querySelectorAll('[data-link-view]');
  var rows=document.querySelectorAll('tr[data-link-category]');
  var empty=document.querySelector('[data-link-empty-row]');
  var table=document.querySelector('[data-link-table]');
  var panels=document.querySelectorAll('[data-link-panel]');
  function showView(view){
    var special=view==='category-manage',shown=0;
    Array.prototype.forEach.call(tabs,function(item){item.classList.toggle('on',item.getAttribute('data-link-view')===view);});
    if(table)table.hidden=special;
    Array.prototype.forEach.call(panels,function(panel){panel.hidden=panel.getAttribute('data-link-panel')!==view;});
    if(!special){
      Array.prototype.forEach.call(rows,function(row){var visible=view==='all'||row.getAttribute('data-link-category')===view;row.hidden=!visible;if(visible)shown++;});
      if(empty)empty.hidden=shown>0;
    }
  }
  Array.prototype.forEach.call(tabs,function(tab){tab.addEventListener('click',function(){showView(tab.getAttribute('data-link-view'));});});
  showView(<?= json_encode($activeLinkView) ?>);
  var categoryForm=document.getElementById('link-category-form');
  var categoryDrawer=document.getElementById('link-category-drawer');
  var categoryId=categoryForm.querySelector('[name="category_id"]');
  var categoryName=categoryForm.querySelector('[data-category-name]');
  var categorySort=categoryForm.querySelector('[data-category-sort]');
  var categoryTitle=document.querySelector('[data-category-drawer-title]');
  var returnView=categoryForm.querySelector('[name="return_view"]');
  document.addEventListener('click',function(e){
    var viewTab=e.target.closest('[data-link-view]');
    if(viewTab)returnView.value=viewTab.getAttribute('data-link-view');
    var add=e.target.closest('[data-drawer-toggle="link-category-drawer"]');
    if(add){categoryId.value='0';categoryName.value='';categorySort.value='0';categoryTitle.textContent='添加链接分类';return;}
    var edit=e.target.closest('[data-category-edit]');
    if(edit){
      if(categoryDrawer.getAttribute('data-open')!=='1')document.querySelector('[data-drawer-toggle="link-category-drawer"]').click();
      categoryId.value=edit.getAttribute('data-id');categoryName.value=edit.getAttribute('data-name');categorySort.value=edit.getAttribute('data-sort');
      categoryTitle.textContent='编辑链接分类';
    }
  });
})();
</script>
<?php require __DIR__ . '/../inc/footer.php'; ?>
