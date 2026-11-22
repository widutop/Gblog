<?php
$page_title = '章节管理';
require_once __DIR__ . '/../inc/header.php';

$pdo     = db();
$novelId = (int)($_GET['novel_id'] ?? 0);

$novel = null;
if ($novelId > 0) {
    $ns = $pdo->prepare("SELECT p.*, s.name AS site_name, s.enabled AS site_enabled FROM posts p LEFT JOIN sites s ON s.id = p.site_id WHERE p.id = ? LIMIT 1");
    $ns->execute([$novelId]);
    $novel = $ns->fetch() ?: null;
    if (!$novel) {
        echo '<div class="card"><p>作品不存在，无法管理章节。</p><p><a class="btn btn-sm" href="' . gblog_admin_url('index.php?tab=novel') . '">← 返回小说列表</a></p></div>';
        require __DIR__ . '/../inc/footer.php';
        exit;
    }
}

// 列表筛选 / 分页；单本模式默认停在「添加章节」tab，长篇不会一次渲染整本表格
$ctab         = gblog_pick($_GET, 'ctab', ['add', 'list'], 'add');
if ($novelId <= 0) $ctab = 'list';   // 全部小说章节视图没有「添加」表单，直接落在列表 tab
$kw           = mb_substr(trim((string)($_GET['kw'] ?? '')), 0, 100);
$statusFilter = gblog_pick($_GET, 'status', ['all', 'published', 'draft'], 'all');
$bookFilter   = (int)($_GET['book'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 30;

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 「全部小说章节」视图的删除表单带所属作品 id，避免越权改到别的书
    $formNovelId = $novelId > 0 ? $novelId : (int)($_POST['novel_id'] ?? 0);
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } elseif ($formNovelId <= 0) {
        $error = '请先选择作品。';
    } elseif ((int)($novel['site_enabled'] ?? 0) !== 1) {
        $error = '所属栏目已停用，不能新增或改动章节。';
    } else {
        $action    = (string)($_POST['action'] ?? '');
        $title     = trim((string)($_POST['title'] ?? ''));
        $content   = (string)($_POST['content'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status    = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

        if ($action !== 'delete' && $title === '') {
            $error = '章节标题不能为空。';
        } elseif ($action === 'add') {
            // 发布 / 新增章节：章序自动接在本书最后一章之后（含草稿），忽略表单排序值。
            // 用 INSERT...SELECT 一条语句算 MAX+1，避免手填重复值、也避免并发发布撞号。
            $ins = $pdo->prepare(
                "INSERT INTO novel_chapters (novel_id, title, content, sort_order, status, updated_at)
                 SELECT ?, ?, ?, COALESCE(MAX(sort_order), 0) + 1, ?, CURRENT_TIMESTAMP
                 FROM novel_chapters WHERE novel_id = ?"
            );
            $ins->execute([$formNovelId, $title, $content, $status, $formNovelId]);
            $newId = (int)$pdo->lastInsertId();
            $gs = $pdo->prepare("SELECT sort_order FROM novel_chapters WHERE id = ?");
            $gs->execute([$newId]);
            $success = '章节已添加，自动排在第 ' . (int)$gs->fetchColumn() . ' 章。';
        } elseif ($action === 'edit') {
            $cid = (int)($_POST['id'] ?? 0);
            if ($cid <= 0) { $error = '参数错误。'; }
            else {
                // 编辑时允许手动调序，但排序值不能与同书其它章节重复：冲突 / 非正数一律自动顺延到末尾
                $dup = $pdo->prepare("SELECT COUNT(*) FROM novel_chapters WHERE novel_id = ? AND sort_order = ? AND id <> ?");
                $dup->execute([$formNovelId, $sortOrder, $cid]);
                if ($sortOrder <= 0 || (int)$dup->fetchColumn() > 0) {
                    $mx = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM novel_chapters WHERE novel_id = ? AND id <> ?");
                    $mx->execute([$formNovelId, $cid]);
                    $sortOrder = (int)$mx->fetchColumn();
                    $success = '章节已更新；排序值重复或无效，已自动排到第 ' . $sortOrder . ' 章。';
                } else {
                    $success = '章节已更新。';
                }
                $pdo->prepare("UPDATE novel_chapters SET title = ?, content = ?, sort_order = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND novel_id = ?")->execute([$title, $content, $sortOrder, $status, $cid, $formNovelId]);
            }
        } elseif ($action === 'delete') {
            $cid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM novel_chapters WHERE id = ? AND novel_id = ?")->execute([$cid, $formNovelId]);
            $success = '章节已删除。';
        }
    }
}

$editCh = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0 && $novelId > 0) {
    $es = $pdo->prepare("SELECT * FROM novel_chapters WHERE id = ? AND novel_id = ?");
    $es->execute([$editId, $novelId]);
    $editCh = $es->fetch() ?: null;
    if ($editCh) $ctab = 'add';   // 编辑链接一律落到表单 tab
}

// 添加表单展示的下一个章序（含草稿，保证发布后不会与已有章节排序相同）
$nextSort = 1;
if ($novelId > 0) {
    $ns2 = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM novel_chapters WHERE novel_id = ?");
    $ns2->execute([$novelId]);
    $nextSort = (int)$ns2->fetchColumn();
}

// 保留筛选条件的链接构造（空值 / 默认值不进 URL）
$mkUrl = function (array $extra = []) use ($novelId, $kw, $statusFilter, $bookFilter): string {
    $p = array_merge([
        'novel_id' => $novelId,
        'kw'       => $kw,
        'status'   => $statusFilter,
        'book'     => $bookFilter,
    ], $extra);
    $p = array_filter($p, static function ($v) {
        return $v !== '' && $v !== null && $v !== 0 && $v !== 'all';
    });
    return gblog_admin_url('content/novel_chapters.php' . ($p ? '?' . http_build_query($p) : ''));
};

// 全部视图：作品下拉
$novels = [];
if ($novelId <= 0) {
    $nv = $pdo->query(
        "SELECT p.id, p.title FROM posts p LEFT JOIN sites s ON s.id = p.site_id
         WHERE s.type = 'novel' ORDER BY p.title ASC, p.id ASC"
    );
    $novels = $nv->fetchAll() ?: [];
}

// 章节列表：一律 COUNT + LIMIT/OFFSET，禁止 fetchAll 整本
$where  = [];
$params = [];
if ($novelId > 0) {
    $where[]  = 'c.novel_id = ?';
    $params[] = $novelId;
} elseif ($bookFilter > 0) {
    $where[]  = 'c.novel_id = ?';
    $params[] = $bookFilter;
}
if ($kw !== '') {
    if (ctype_digit($kw)) {
        $where[]  = '(c.id = ? OR c.title LIKE ?)';
        $params[] = (int)$kw;
        $params[] = '%' . $kw . '%';
    } else {
        $where[]  = 'c.title LIKE ?';
        $params[] = '%' . $kw . '%';
    }
}
if ($statusFilter !== 'all') {
    $where[]  = 'c.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM novel_chapters c $whereSql");
$cnt->execute($params);
$totalRows  = (int)$cnt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// 单本按书内章序，全部视图按最新添加
$order = $novelId > 0 ? 'c.sort_order ASC, c.id ASC' : 'c.id DESC';
$chs = $pdo->prepare(
    "SELECT c.*, p.title AS novel_title FROM novel_chapters c
     LEFT JOIN posts p ON p.id = c.novel_id
     $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset"
);
$chs->execute($params);
$chapters = $chs->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
  <h2 style="margin:0;display:flex;align-items:center;gap:8px;"><?= gblog_admin_icon('novel') ?> <?= $novelId > 0 ? '《' . e($novel['title']) . '》章节管理' : '小说章节' ?><?= $novelId > 0 && (int)($novel['site_enabled'] ?? 0) !== 1 ? ' <span class="badge badge-off">栏目已停用 · 仅可查看</span>' : '' ?></h2>
  <a class="btn btn-sm" href="<?= gblog_admin_url('index.php?tab=novel') ?>">← 返回小说列表</a>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php
// 与 settings / sites / themes / backup 等后台页共用统一 tab 组件；
// 搜索表单放进 $rightHtml 插槽，保持「tab 同行最右端」
require_once __DIR__ . '/../inc/tabs.php';
ob_start();
?>
<form method="get" action="<?= gblog_admin_url('content/novel_chapters.php') ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0;">
  <input type="hidden" name="ctab" value="list">
  <?php if ($novelId > 0): ?><input type="hidden" name="novel_id" value="<?= (int)$novelId ?>"><?php endif; ?>
  <input type="text" name="kw" value="<?= e($kw) ?>" placeholder="章节标题 / 章节 ID" class="admin-input-sm" style="min-width:200px;">
  <?php if ($novelId <= 0): ?>
    <select name="book" class="admin-input-sm">
      <option value="0">全部作品</option>
      <?php foreach ($novels as $nv): ?>
        <option value="<?= (int)$nv['id'] ?>" <?= $bookFilter === (int)$nv['id'] ? 'selected' : '' ?>><?= e($nv['title']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <select name="status" class="admin-input-sm">
    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>全部状态</option>
    <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>已发布</option>
    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>草稿</option>
  </select>
  <button type="submit" class="btn btn-sm">查询</button>
  <a class="btn btn-sm" href="<?= e($mkUrl(['ctab' => 'list', 'kw' => '', 'status' => 'all', 'book' => 0, 'page' => 1])) ?>">重置</a>
</form>
<?php
$searchHtml = (string)ob_get_clean();
$chTabs = [];
if ($novelId > 0) {
    $chTabs['add'] = gblog_admin_icon('novel') . ' ' . ($editCh ? '编辑章节' : '添加章节');
}
$chTabs['list'] = '小说章节（' . $totalRows . '）';
admin_sub_tabs($chTabs, $ctab, $searchHtml, function (string $k) use ($mkUrl): string {
    return $mkUrl(['ctab' => $k]);
});
?>

<?php if ($ctab === 'add' && $novelId > 0): ?>
<div class="card">
  <h2><?= $editCh ? ('编辑章节 #' . (int)$editCh['id']) : '添加章节' ?></h2>
  <form method="post" action="<?= e($mkUrl(['ctab' => 'add'] + ($editCh ? ['edit' => (int)$editCh['id']] : []))) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editCh ? 'edit' : 'add' ?>">
    <?php if ($editCh): ?><input type="hidden" name="id" value="<?= (int)$editCh['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-row">
        <div class="form-field" style="flex:3;">
          <label>章节标题 *</label>
          <input type="text" name="title" value="<?= $editCh ? e($editCh['title']) : '' ?>" required>
        </div>
        <div class="form-field" style="flex:1;">
          <label><?= $editCh ? '排序（重复自动顺延）' : '章序（自动）' ?></label>
          <?php if ($editCh): ?>
            <input type="number" name="sort_order" value="<?= (int)$editCh['sort_order'] ?>">
          <?php else: ?>
            <input type="text" value="第 <?= $nextSort ?> 章 · 自动接在最后" disabled>
          <?php endif; ?>
        </div>
        <div class="form-field" style="flex:1;">
          <label>状态</label>
          <select name="status">
            <option value="published" <?= $editCh && $editCh['status'] === 'draft' ? '' : 'selected' ?>>发布</option>
            <option value="draft" <?= $editCh && $editCh['status'] === 'draft' ? 'selected' : '' ?>>草稿</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field" style="width:100%;">
          <label>章节正文</label>
          <textarea name="content" rows="14" style="width:100%;font-family:inherit;line-height:1.8;"><?= $editCh ? e($editCh['content']) : '' ?></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <button type="submit" class="btn btn-primary"><?= $editCh ? '保存修改' : '添加章节' ?></button>
        <?php if ($editCh): ?><a class="btn btn-primary" href="<?= e($mkUrl(['ctab' => 'add'])) ?>">取消编辑</a><?php endif; ?>
        <a class="btn btn-sm" href="<?= e($mkUrl(['ctab' => 'list'])) ?>">查看章节列表 →</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($ctab === 'list'): ?>
<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th><?= $novelId > 0 ? '章序' : 'ID' ?></th>
        <th>章节标题</th>
        <?php if ($novelId <= 0): ?><th>所属作品</th><?php endif; ?>
        <th>状态</th><th>排序</th><th>浏览</th><th>更新时间</th><th>操作</th>
      </tr></thead>
      <tbody>
      <?php if (!$chapters): ?>
        <tr><td colspan="<?= $novelId > 0 ? 7 : 8 ?>" style="color:#6b7280;">没有符合条件的章节。</td></tr>
      <?php else: foreach ($chapters as $i => $c): ?>
        <?php $cNovelId = (int)$c['novel_id']; ?>
        <tr>
          <td><?= $novelId > 0 ? ($offset + $i + 1) : (int)$c['id'] ?></td>
          <td>
            <?php if ($c['status'] === 'published'): ?>
              <a href="<?= e(gblog_web_base() . ltrim(gblog_url('chapter', ['id' => (int)$c['id']]), '/')) ?>" target="_blank"><?= e($c['title']) ?></a>
            <?php else: ?>
              <a href="<?= e(gblog_root_absolute('index.php?do=chapter&id=' . (int)$c['id'] . '&preview=1')) ?>" target="_blank" title="仅管理员可预览草稿，普通访客仍看不到"><?= e($c['title']) ?></a>
              <span style="color:#9ca3af;font-size:12px;">（草稿预览）</span>
            <?php endif; ?>
          </td>
          <?php if ($novelId <= 0): ?><td><?= e((string)($c['novel_title'] ?? '—')) ?></td><?php endif; ?>
          <td><span class="badge badge-<?= $c['status'] === 'published' ? 'published' : 'draft' ?>"><?= $c['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
          <td><?= (int)$c['sort_order'] ?></td>
          <td><?= (int)$c['view_count'] ?></td>
          <td><?= e(fmt_date($c['updated_at'])) ?></td>
          <td>
            <a class="btn btn-sm" href="<?= e($mkUrl(['ctab' => 'add', 'edit' => (int)$c['id'], 'novel_id' => $cNovelId])) ?>">编辑</a>
            <form method="post" action="<?= e($mkUrl(['ctab' => 'list'])) ?>" onsubmit="return confirm('删除该章节？');" style="display:inline-block;margin:0;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <?php if ($novelId <= 0): ?><input type="hidden" name="novel_id" value="<?= $cNovelId ?>"><?php endif; ?>
              <button type="submit" class="btn btn-sm btn-danger">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:12px;color:#6b7280;">
    <span>共 <?= $totalRows ?> 章<?= $kw !== '' || $statusFilter !== 'all' || $bookFilter > 0 ? '（已筛选）' : '' ?>，每页 <?= $perPage ?> 章。</span>
    <div style="display:flex;align-items:center;gap:8px;">
      <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($mkUrl(['ctab' => 'list', 'page' => $page - 1])) ?>">上一页</a><?php endif; ?>
      <span>第 <?= $page ?> / <?= $totalPages ?> 页</span>
      <?php if ($page < $totalPages): ?><a class="btn btn-sm" href="<?= e($mkUrl(['ctab' => 'list', 'page' => $page + 1])) ?>">下一页</a><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
