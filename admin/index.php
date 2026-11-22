<?php
$page_title = '内容管理';
require_once __DIR__ . '/inc/header.php';

$pdo = db();
$sites = get_all_sites();

// site_ 后必须是纯数字且确实存在于 sites，否则一律回退 all，不接受任意值
$tabRaw = (string)($_GET['tab'] ?? 'all');
$siteTabId = 0;
$siteTab = null;
if (preg_match('/^site_(\d+)$/', $tabRaw, $m)) {
    foreach ($sites as $_s) {
        if ((int)$_s['id'] === (int)$m[1]) {
            $siteTabId = (int)$_s['id'];
            $siteTab   = $_s;
            break;
        }
    }
}
$tab = $siteTabId > 0
    ? 'site_' . $siteTabId
    : gblog_pick($_GET, 'tab', ['all','post','song','software','novel'], 'all');
$kw = mb_substr(trim((string)($_GET['kw'] ?? '')), 0, 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$statusFilter = gblog_pick($_GET, 'status', ['all', 'published', 'draft'], 'all');
$perPage = 30;

// 列表内排序直改：数字框 onchange 即提交，CSRF 校验后更新并 PRG 回当前列表
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save_sort') {
    if (!csrf_check()) {
        http_response_code(403);
        exit('CSRF 校验失败');
    }
    $sid  = (int)($_POST['cid'] ?? 0);
    $ctype = ($_POST['ctype'] ?? '') === 'song' ? 'song' : 'post';
    $sval = max(0, min(999999, (int)($_POST['sort_val'] ?? 0)));
    if ($sid > 0) {
        $st = $pdo->prepare($ctype === 'song'
            ? 'UPDATE songs SET sort_order = ? WHERE id = ?'
            : 'UPDATE posts SET sort_order = ? WHERE id = ?');
        $st->execute([$sval, $sid]);
    }
    while (ob_get_level()) { ob_end_clean(); }
    header('Location: ' . gblog_admin_url('index.php?tab=' . urlencode($tab)
        . '&kw=' . urlencode($kw) . '&status=' . urlencode($statusFilter) . '&page=' . $page));
    exit;
}

$__coveredSiteIds = [];
$__typeTabDef = [
    'post'     => ['post',   'article'],
    'software' => ['soft',   'software'],
    'novel'    => ['novel',  'novel'],
    'song'     => ['music',  'song'],
];
foreach ($__typeTabDef as $__def) {
    $__hit = null;
    foreach ($sites as $_s) {
        if (!gblog_site_is_builtin($_s)) continue;
        if (($_s['slug'] ?? '') === $__def[0]) { $__hit = $_s; break; }
    }
    if (!$__hit) {
        foreach ($sites as $_s) {
            if (!gblog_site_is_builtin($_s)) continue;
            if (($_s['type'] ?? '') === $__def[1]) { $__hit = $_s; break; }
        }
    }
    if ($__hit) $__coveredSiteIds[(int)$__hit['id']] = true;
}
$siteTabs   = [];
$customTabs = [];
foreach ($sites as $_s) {
    if (!gblog_site_is_builtin($_s)) { $customTabs[] = $_s; continue; }
    if (!isset($__coveredSiteIds[(int)$_s['id']])) $siteTabs[] = $_s;
}
$siteTypeIcon  = [];
$siteTypeLabel = [];
foreach (gblog_site_type_meta() as $__tk => $__tv) {
    $siteTypeIcon[$__tk]  = $__tv['icon'];
    $siteTypeLabel[$__tk] = $__tv['label'];
}

$stats = [
    'total'   => (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn() + (int)$pdo->query("SELECT COUNT(*) FROM songs")->fetchColumn(),
    'posts'   => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'drafts'  => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='draft'")->fetchColumn(),
    'songs'   => (int)$pdo->query("SELECT COUNT(*) FROM songs")->fetchColumn(),
    'software'=> (int)$pdo->query("SELECT COUNT(*) FROM posts p INNER JOIN sites s ON s.id=p.site_id WHERE s.type='software'")->fetchColumn(),
    'novels'  => (int)$pdo->query("SELECT COUNT(*) FROM posts p INNER JOIN sites s ON s.id=p.site_id WHERE s.type='novel'")->fetchColumn(),
    'sites'   => (int)$pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn(),
];
$activeByType = [];
foreach ($sites as $_s) {
    if ((int)$_s['enabled'] === 1) $activeByType[(string)$_s['type']][] = $_s;
}
?>

<div class="stat-grid">
  <a class="stat-card" href="?tab=all"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">内容总数</div></a>
  <a class="stat-card" href="?tab=post"><div class="stat-num"><?= $stats['posts'] ?></div><div class="stat-label">文章总数</div></a>
  <a class="stat-card" href="?tab=song"><div class="stat-num"><?= $stats['songs'] ?></div><div class="stat-label">歌曲总数</div></a>
  <a class="stat-card" href="?tab=software"><div class="stat-num"><?= $stats['software'] ?></div><div class="stat-label">软件总数</div></a>
  <a class="stat-card" href="?tab=novel"><div class="stat-num"><?= $stats['novels'] ?></div><div class="stat-label">小说总数</div></a>
  <a class="stat-card" href="<?= gblog_admin_url('system/sites.php') ?>"><div class="stat-num"><?= $stats['sites'] ?></div><div class="stat-label">栏目总数</div></a>
  <a class="stat-card" href="?tab=post"><div class="stat-num"><?= $stats['drafts'] ?></div><div class="stat-label">草稿</div></a>
</div>

<div class="card card-tabbar">
  <div class="admin-toolbar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:10px;">
    <div class="tab-bar">
      <?php $__kw = $kw ? '&amp;kw=' . urlencode($kw) : ''; ?>
      <a href="?tab=all<?= $__kw ?>" class="tab-btn <?= $tab === 'all' ? 'on' : '' ?>"><?= gblog_admin_icon('all') ?> 全部</a>
      <a href="?tab=post<?= $__kw ?>" class="tab-btn <?= $tab === 'post' ? 'on' : '' ?>"><?= gblog_admin_icon('post') ?> 文章</a>
      <a href="?tab=software<?= $__kw ?>" class="tab-btn <?= $tab === 'software' ? 'on' : '' ?>"><?= gblog_admin_icon('software') ?> 软件</a>
      <a href="?tab=song<?= $__kw ?>" class="tab-btn <?= $tab === 'song' ? 'on' : '' ?>"><?= gblog_admin_icon('song') ?> 歌曲</a>
      <a href="?tab=novel<?= $__kw ?>" class="tab-btn <?= $tab === 'novel' ? 'on' : '' ?>"><?= gblog_admin_icon('novel') ?> 小说</a>
      <?php
      $__allSiteTabs = array_merge($siteTabs, $customTabs);
      $__splitAt     = count($siteTabs);
      ?>
      <?php foreach ($__allSiteTabs as $__i => $_s): ?>
        <?php if ($__i === $__splitAt): ?>
          <span style="display:inline-flex;align-items:center;padding:0 8px;margin-left:2px;border-left:1px solid rgba(0,0,0,.12);font-size:12px;color:var(--muted);">新建栏目</span>
        <?php endif; ?>
        <?php $__sid = (int)$_s['id']; $__off = (int)$_s['enabled'] !== 1; ?>
        <a href="?tab=site_<?= $__sid ?><?= $__kw ?>" class="tab-btn <?= $tab === 'site_' . $__sid ? 'on' : '' ?>" <?= $__off ? 'style="opacity:.55;" title="该栏目已停用，仍可管理已有内容"' : '' ?>><?= gblog_admin_icon($siteTypeIcon[(string)($_s['type'] ?? '')] ?? 'post') ?> <?= e($_s['name']) ?><?= $__off ? '（停用）' : '' ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <form method="get" action="<?= gblog_admin_url('index.php') ?>" style="display:flex;gap:6px;align-items:center;margin:0;">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="text" name="kw" value="<?= e($kw) ?>" placeholder="关键字搜索" class="admin-input-sm">
        <select name="status" class="admin-input-sm"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>全部状态</option><option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>已发布</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>草稿</option></select>
        <button type="submit" class="btn btn-sm">查询</button>
      </form>
      <?php
      $__typeMap = ['post' => 'article', 'software' => 'software', 'novel' => 'novel'];
      $__slugMap = ['post' => 'post', 'software' => 'soft', 'novel' => 'novel'];
      $__target = null;
      if ($siteTabId > 0) {
          $__target = $siteTab;
      } else {
          foreach ($sites as $_s) {
              if (($_s['slug'] ?? '') === ($__slugMap[$tab] ?? '')) { $__target = $_s; break; }
          }
          if (!$__target && isset($__typeMap[$tab])) {
              foreach ($sites as $_s) {
                  if ((string)($_s['type'] ?? '') === $__typeMap[$tab]) { $__target = $_s; break; }
              }
          }
      }
      if ($__target && (int)$__target['enabled'] !== 1) $__target = null;
      $__canPublish = $siteTabId > 0
          ? gblog_site_is_active($siteTabId)
          : ($tab === 'song' ? gblog_music_site_active()
              : ($tab === 'all' ? (bool)$activeByType : (bool)$__target));
      $__href = $tab === 'song' ? gblog_admin_url('content/song_edit.php') : gblog_admin_url('content/post_edit.php' . ($__target ? '?site=' . (int)$__target['id'] : ''));
      if ($siteTabId > 0) {
          $__href = (($siteTab['type'] ?? '') === 'song')
              ? gblog_admin_url('content/song_edit.php')
              : gblog_admin_url('content/post_edit.php?site=' . $siteTabId);
      }
      $__tip = (!$siteTabId && $tab === 'all') ? 'return confirm(\'当前为「全部」栏目，将默认发布文章。\');' : '';
      ?>
      <?php if ($siteTabId > 0): ?>
        <span style="font-size:12px;color:var(--muted);">发布到：<b><?= e($siteTab['name']) ?></b>（<?= e($siteTypeLabel[(string)($siteTab['type'] ?? '')] ?? '文章') ?>）</span>
      <?php endif; ?>
      <?php if ($__canPublish): ?>
        <a class="btn btn-primary btn-sm" href="<?= e($__href) ?>" <?= $__tip ? ('onclick="' . $__tip . '"') : '' ?> <?= ($siteTabId > 0 && ($siteTab['type'] ?? '') === 'song') ? 'title="歌曲不按栏目隔离，发布仍走歌曲发布页"' : '' ?>>＋ 发布</a>
      <?php else: ?>
        <button type="button" class="btn btn-sm" disabled title="对应栏目已停用，不能发布内容" style="opacity:.5;cursor:not-allowed;">＋ 发布</button>
      <?php endif; ?>
    </div>
  </div>

  <?php
  $postCond = [];
  $songCond = [];
  if ($siteTabId > 0)         { $postCond[] = "p.site_id = " . $siteTabId; $songCond[] = "1=0"; }
  elseif ($tab === 'song')    { $postCond[] = "1=0"; }
  elseif ($tab === 'software'){ $postCond[] = "s.type='software'"; $songCond[] = "1=0"; }
  elseif ($tab === 'novel')   { $postCond[] = "s.type='novel'"; $songCond[] = "1=0"; }
  elseif ($tab === 'post')    { $postCond[] = "s.type='article'"; $songCond[] = "1=0"; }
  $params = [];
  if ($statusFilter !== 'all') {
    $postCond[] = 'p.status = ?';
    $songCond[] = 'sg.status = ?';
    $params[] = $statusFilter;
  }
  if ($kw !== '') {
    $like = '%' . $kw . '%';
    $postCond[] = "(p.title LIKE ? OR p.author LIKE ?)";
    $songCond[] = "(sg.title LIKE ? OR sg.lyricist LIKE ? OR sg.artist LIKE ?)";
    $params = array_merge($params, [$like, $like]);
  }
  $postWhereSql = $postCond ? 'WHERE ' . implode(' AND ', $postCond) : '';
  $songWhereSql = $songCond ? 'WHERE ' . implode(' AND ', $songCond) : '';
  if ($kw !== '') { $params = array_merge($params, [$like, $like, $like]); }
  $sql = "
    SELECT * FROM (
      SELECT p.id, p.title, p.cover, p.author, p.status, p.sort_order, p.view_count, p.created_at,
             s.name AS site_name, c.name AS category_name, s.type AS site_type,
             'post' AS type, 'post_edit.php' AS edit_url, 'post_delete.php' AS delete_url,
             '确定删除这篇文章及其歌曲？' AS confirm_msg
      FROM posts p
      LEFT JOIN sites s ON s.id = p.site_id
      LEFT JOIN categories c ON c.id = p.category_id
      $postWhereSql
      UNION ALL
      SELECT sg.id, sg.title, sg.cover_url, sg.artist, sg.status, sg.sort_order, sg.play_count, sg.created_at,
             ms.name AS site_name, c.name AS category_name, 'song' AS site_type,
             'song' AS type, 'song_edit.php' AS edit_url, 'song_delete.php' AS delete_url,
             '确定删除这首歌？' AS confirm_msg
      FROM songs sg
      LEFT JOIN categories c ON c.id = sg.category_id
      LEFT JOIN sites ms ON ms.id = sg.site_id
      $songWhereSql
    ) t
  ";
  $countSql = 'SELECT COUNT(*) FROM (' . preg_replace('/^\s*SELECT \* FROM \(/', '', $sql, 1);
  $countSql = preg_replace('/\)\s*t\s*$/', ') AS content_rows', $countSql);
  $countStmt = $pdo->prepare($countSql);
  $countStmt->execute($params);
  $totalRows = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $perPage;
  $stmt = $pdo->prepare($sql . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
  ?>
    <?php if ($siteTabId > 0): ?>
      <p style="font-size:12px;color:var(--muted);margin:0 0 10px;">
        当前栏目：<b><?= e($siteTab['name']) ?></b>（类型：<?= e($siteTypeLabel[(string)($siteTab['type'] ?? '')] ?? '文章') ?>）。
        <?php if (($siteTab['type'] ?? '') === 'song'): ?>
          歌曲暂不按栏目隔离，请到「歌曲」标签管理；本标签显示该栏目下的文章。
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>封面</th><th>ID</th><th>标题</th><th>作者</th><th>栏目</th><th>分类</th><th>状态</th><th>排序</th><th>浏览</th><th>发布时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11" style="color:#6b7280;">暂无内容。</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?php if (!empty($r['cover'])): ?><img src="<?= e($r['cover']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"><?php else: ?>-<?php endif; ?></td>
            <td style="font-family:Consolas,monospace;color:var(--muted);"><?= (int)$r['id'] ?></td>
            <td>
              <?= gblog_admin_icon($r['type'] === 'song' ? 'song' : (($r['site_type'] ?? '') === 'novel' ? 'novel' : 'post')) ?>
              <a href="<?= e(gblog_admin_url('content/' . $r['edit_url'])) ?>?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a>
            </td>
            <td><?= e($r['author'] ?: '-') ?></td>
            <td><?= e($r['site_name'] ?? '-') ?></td>
            <td><?= e($r['category_name'] ?? '-') ?></td>
            <td><span class="badge badge-<?= $r['status'] === 'published' ? 'published' : 'draft' ?>"><?= $r['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
            <td>
              <form method="post" action="<?= e(gblog_admin_url('index.php?tab=' . urlencode($tab) . '&kw=' . urlencode($kw) . '&status=' . urlencode($statusFilter) . '&page=' . $page)) ?>" style="display:inline;margin:0;">
                <input type="hidden" name="action" value="save_sort">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="cid" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="ctype" value="<?= $r['type'] === 'song' ? 'song' : 'post' ?>">
                <input type="number" name="sort_val" value="<?= (int)$r['sort_order'] ?>" title="排序值，改完自动保存" style="width:64px;padding:3px 6px;" onchange="this.form.submit()">
              </form>
            </td>
            <td><?= (int)$r['view_count'] ?></td>
            <td><?= e(fmt_date($r['created_at'])) ?></td>
            <td>
              <a class="btn btn-sm" href="<?= e(gblog_admin_url('content/' . $r['edit_url'])) ?>?id=<?= (int)$r['id'] ?>">编辑</a>
              <?php if ($r['type'] === 'post' && ($r['site_type'] ?? '') === 'novel'): ?>
                <a class="btn btn-sm" href="<?= gblog_admin_url('content/novel_chapters.php?novel_id=' . (int)$r['id']) ?>">章节</a>
              <?php endif; ?>
              <form method="post" action="<?= e(gblog_admin_url('content/' . $r['delete_url'])) ?>?id=<?= (int)$r['id'] ?>" style="display:inline;" onsubmit="return confirm('<?= e($r['confirm_msg']) ?>');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                <input type="hidden" name="return_kw" value="<?= e($kw) ?>">
                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="return_page" value="<?= (int)$page ?>">
                <button type="submit" class="btn btn-sm btn-danger">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:12px;color:#6b7280;">
    <span>共 <?= $totalRows ?> 条，每页 <?= $perPage ?> 条；文章与歌曲共用全局内容编号。</span>
    <div style="display:flex;align-items:center;gap:8px;">
      <?php if ($page > 1): ?><a class="btn btn-sm" href="?tab=<?= e(urlencode($tab)) ?>&amp;kw=<?= e(urlencode($kw)) ?>&amp;status=<?= e(urlencode($statusFilter)) ?>&amp;page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
      <span>第 <?= $page ?> / <?= $totalPages ?> 页</span>
      <?php if ($page < $totalPages): ?><a class="btn btn-sm" href="?tab=<?= e(urlencode($tab)) ?>&amp;kw=<?= e(urlencode($kw)) ?>&amp;status=<?= e(urlencode($statusFilter)) ?>&amp;page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
    </div>
  </div>
</div>


<?php require __DIR__ . '/inc/footer.php'; ?>
