<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$pdo    = db();
$tabUrl = gblog_admin_url('system/sites.php?tab=tags');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            $siteId = (int)($_POST['site_id'] ?? 0);
            if ($name === '') {
                $error = '请填写标签名称。';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tags (site_id, name) VALUES (?,?)");
                    $stmt->execute([$siteId > 0 ? $siteId : 0, $name]);
                    $success = '标签已添加。';
                } catch (PDOException $e) {
                    $error = '添加失败：已存在同名标签。';
                }
            }
        } elseif ($action === 'delete') {
            $tid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$tid]);
            $success = '标签已删除（文章不受影响）。';
        }
    }
}

$tags = $pdo->query(
    "SELECT t.*, s.name AS site_name, COUNT(pt.post_id) AS post_count
     FROM tags t
     LEFT JOIN post_tags pt ON pt.tag_id = t.id
     LEFT JOIN sites s ON s.id = t.site_id
     GROUP BY t.id ORDER BY t.id DESC"
)->fetchAll();
require_once __DIR__ . '/../tabs.php';
$drawerOpen = ((string)($_GET['drawer'] ?? '') === '1')
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add');
admin_sub_tabs($gbTabs, $gbTab, admin_flash_html($error, $success));
?>
<div class="gb-drawer" id="tag-drawer" data-open="<?= $drawerOpen ? '1' : '0' ?>">
  <div class="drawer-head">
    <h3>添加标签</h3>
    <button type="button" class="drawer-close" data-drawer-close aria-label="关闭">✕</button>
  </div>
  <form method="post" action="<?= e($tabUrl) ?>&amp;drawer=1" class="drawer-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    <div class="form-field" style="min-width:180px;">
      <label>标签名称 *</label>
      <input type="text" name="name" required>
    </div>
    <div class="form-field" style="min-width:170px;">
      <label>所属栏目</label>
      <select name="site_id">
        <option value="0">通用（全站可用）</option>
        <?php foreach (get_all_sites() as $__tagSite): ?>
          <option value="<?= (int)$__tagSite['id'] ?>"><?= e((string)$__tagSite['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">添加</button>
  </form>
</div>

<div class="card card-connected">
  <div class="list-head">
    <h2>全部标签（<?= count($tags) ?>）</h2>
    <div class="list-head-actions">
      <button type="button" class="btn btn-primary" data-drawer-toggle="tag-drawer" data-label-add="＋ 添加标签" data-label-close="收起表单"><?= $drawerOpen ? '收起表单' : '＋ 添加标签' ?></button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>名称</th><th>所属栏目</th><th>文章数</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$tags): ?>
        <tr><td colspan="4" style="color:#6b7280;">暂无标签。</td></tr>
      <?php else: ?>
        <?php foreach ($tags as $t): ?>
          <tr>
            <td><?= e($t['name']) ?></td>
            <td><?= $t['site_name'] !== null && $t['site_name'] !== '' ? e((string)$t['site_name']) : '通用' ?></td>
            <td><?= (int)$t['post_count'] ?></td>
            <td>
              <form method="post" action="<?= e($tabUrl) ?>&amp;drawer=1" onsubmit="return confirm('删除该标签？');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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
