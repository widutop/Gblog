<?php
$page_title = '用户管理';
require_once __DIR__ . '/../inc/header.php';
gblog_admin_require_cap('user.manage');

$pdo = db();
$msg = '';
$plainCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = '表单校验失败。';
    } else {
        $act = $_POST['act'] ?? '';
        $uid = (int)($_POST['uid'] ?? 0);
        if ($act === 'gen_recovery' && $uid > 0) {
            $plainCode = gblog_recovery_generate();
            $pdo->prepare('UPDATE users SET security_code = ? WHERE id = ?')
                ->execute([gblog_recovery_hash($plainCode), $uid]);
            gblog_audit('recovery_code.admin_generated', $uid, 'users.php');
            $msg = '已为用户 #' . $uid . ' 生成新恢复码，旧码立即失效。';
        } elseif ($act === 'role' && $uid > 0) {
            $role = gblog_pick($_POST, 'role', ['admin','editor','author','contributor','member'], 'member');
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);
            $msg = '角色已更新。';
        } elseif ($act === 'status' && $uid > 0) {
            $st = $_POST['status'] === 'banned' ? 'banned' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$st, $uid]);
            $msg = $st === 'banned' ? '已禁用该用户。' : '已启用该用户。';
        } elseif ($act === 'create') {
            $newUser = trim($_POST['new_username'] ?? '');
            $newPass = $_POST['new_password'] ?? '';
            $newNick = trim($_POST['new_nickname'] ?? '');
            $newEmail = trim($_POST['new_email'] ?? '');
            $newRole = gblog_pick($_POST, 'new_role', ['admin','editor','author','contributor','member'], 'member');
            $newGold = max(0, (int)($_POST['new_gold'] ?? 0));
            $newCode = trim((string)($_POST['new_security_code'] ?? ''));
            if ($newUser === '' || $newPass === '') {
                $msg = '请填写用户名和密码。';
            } elseif (!gblog_password_strong($newPass)) {
                $msg = '密码至少 8 位，且必须同时包含大写字母和小写字母。';
            } elseif ($newCode !== '' && gblog_recovery_code_error($newCode) !== '') {
                $msg = gblog_recovery_code_error($newCode);
            } else {
                try {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, nickname, email, security_code, role, status, gold) VALUES (?,?,?,?,?,?,'active',?)");
                    $stmt->execute([$newUser, $hash, $newNick ?: $newUser, $newEmail,
                                    $newCode !== '' ? gblog_recovery_hash($newCode) : null, $newRole, $newGold]);
                    $msg = '用户「' . $newUser . '」已创建。';
                } catch (PDOException $e) {
                    $msg = '创建失败：' . $e->getMessage();
                }
            }
        } elseif ($act === 'update' && $uid > 0) {
            $updNick = trim($_POST['upd_nickname'] ?? '');
            $updEmail = trim($_POST['upd_email'] ?? '');
            $updRole = gblog_pick($_POST, 'upd_role', ['admin','editor','author','contributor','member'], 'member');
            $updPass = $_POST['upd_password'] ?? '';
            $updGold = max(0, (int)($_POST['upd_gold'] ?? 0));
            $pdo->prepare("UPDATE users SET nickname=?, email=?, role=?, gold=? WHERE id=?")
                ->execute([$updNick, $updEmail, $updRole, $updGold, $uid]);
            $msg = '用户信息已更新。';
            if ($updPass !== '') {
                if (!gblog_password_strong($updPass)) {
                    $msg = '资料已更新，但密码未修改：至少 8 位且须含大小写字母。';
                } else {
                    $pdo->prepare('UPDATE users SET password_hash = ?, password_version = password_version + 1, must_change_password = 1 WHERE id = ?')
                        ->execute([password_hash($updPass, PASSWORD_DEFAULT), $uid]);
                    gblog_reset_token_invalidate_user($uid);
                    gblog_audit('password.admin_reset', $uid, 'users.php');
                    $msg = '用户信息已更新；密码已重置，该用户下次登录需自行修改。';
                }
            }
        }
    }
    if ($plainCode === '') {
        header('Location: ' . gblog_admin_url('account/users.php?msg=' . urlencode($msg)));
        exit;
    }
}

$f_role = $_GET['f_role'] ?? '';
$f_kw   = trim($_GET['f_kw'] ?? '');
if ($msg === '') $msg = $_GET['msg'] ?? '';

$where = [];
$params = [];
if ($f_role !== '') { $where[] = 'role = ?'; $params[] = $f_role; }
if ($f_kw !== '') { $where[] = '(username LIKE ? OR nickname LIKE ? OR email LIKE ?)'; $params[] = "%$f_kw%"; $params[] = "%$f_kw%"; $params[] = "%$f_kw%"; }

$sql = "SELECT id, username, nickname, role, status, gold, created_at FROM users";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles = ['member' => '评论者', 'contributor' => '协作者', 'author' => '作者', 'editor' => '编辑', 'admin' => '管理员'];

$postCount = [];
$pStmt = $pdo->query("SELECT user_id, COUNT(*) AS cnt FROM posts WHERE user_id IS NOT NULL GROUP BY user_id");
while ($row = $pStmt->fetch()) { $postCount[$row['user_id']] = (int)$row['cnt']; }
?>
<?php if (!empty($msg)): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($plainCode !== ''): ?>
<div class="alert alert-success" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
  <span>新恢复码</span>
  <code style="font-size:16px;font-weight:600;letter-spacing:.6px;padding:2px 8px;background:var(--bg-alt);border-radius:4px;"><?= e($plainCode) ?></code>
  <span style="font-size:13px;">只显示这一次，请立即复制并发给用户。</span>
</div>
<?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h2 style="margin:0;">用户管理（<?= count($users) ?> 人）</h2>
    <form method="get" action="<?= gblog_admin_url('account/users.php') ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <select name="f_role" class="admin-input-sm">
        <option value="">任意级别</option>
        <?php foreach ($roles as $rk => $rv): ?>
          <option value="<?= $rk ?>" <?= $f_role === $rk ? 'selected' : '' ?>><?= e($rv) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="f_kw" value="<?= e($f_kw) ?>" placeholder="用户名/昵称/邮箱" class="admin-input-sm" style="width:180px;">
      <button type="submit" class="btn btn-primary btn-sm">搜索</button>
      <span style="width:1px;height:20px;background:var(--border);margin:0 4px;"></span>
      <button type="button" class="btn btn-sm" onclick="document.getElementById('create-user-modal').style.display='flex'">＋ 新建用户</button>
      <button type="button" class="btn btn-sm" onclick="document.getElementById('permission-modal').style.display='flex'">查看权限</button>
    </form>
  </div>

  <div id="create-user-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:var(--card);border-radius:12px;padding:24px;width:480px;max-width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;font-size:17px;">新建用户</h3>
        <button onclick="document.getElementById('create-user-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);">✕</button>
      </div>
      <form method="post" action="<?= gblog_admin_url('account/users.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="create">
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div>
            <label style="font-size:13px;color:var(--muted);">用户名 *</label>
            <input type="text" name="new_username" required class="admin-input" style="width:100%;margin-top:4px;">
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">密码 *</label>
            <input type="password" name="new_password" required class="admin-input" style="width:100%;margin-top:4px;">
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">恢复码（可选，用户凭它找回密码）</label>
            <input type="text" name="new_security_code" class="admin-input" style="width:100%;margin-top:4px;" placeholder="至少 8 位，须含大小写字母；留空则之后在编辑里生成">
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">昵称</label>
            <input type="text" name="new_nickname" class="admin-input" style="width:100%;margin-top:4px;">
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">邮箱</label>
            <input type="email" name="new_email" class="admin-input" style="width:100%;margin-top:4px;">
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">角色</label>
            <select name="new_role" class="admin-input" style="width:100%;margin-top:4px;">
              <?php foreach ($roles as $rk => $rv): ?>
                <option value="<?= $rk ?>"><?= e($rv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:13px;color:var(--muted);">初始金币</label>
            <input type="number" name="new_gold" value="0" min="0" class="admin-input" style="width:100%;margin-top:4px;">
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="btn" onclick="document.getElementById('create-user-modal').style.display='none'">取消</button>
          <button type="submit" class="btn btn-primary">创建用户</button>
        </div>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>ID</th>
          <th>用户级别</th>
          <th>名称</th>
          <th>别名</th>
          <th>金币</th>
          <th>文章数</th>
          <th>状态</th>
          <th>注册时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="9" style="color:var(--muted);text-align:center;">暂无用户。</td></tr>
      <?php else: foreach ($users as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td>
            <form method="post" action="<?= gblog_admin_url('account/users.php') ?>" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="act" value="role">
              <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <select name="role" onchange="this.form.submit()" class="admin-input-sm">
                <?php foreach ($roles as $rk => $rv): ?>
                  <option value="<?= $rk ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>><?= e($rv) ?><?= $u['role'] === $rk ? ' #' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <span style="color:var(--primary);"><?= gblog_admin_icon('links') ?></span>
            <?= e($u['username']) ?>
          </td>
          <td><?= e($u['nickname'] ?: '-') ?></td>
          <td><?= (int)$u['gold'] ?></td>
          <td><?= $postCount[$u['id']] ?? 0 ?></td>
          <td>
            <?php if ($u['status'] === 'active'): ?>
              <span class="badge badge-published">正常</span>
            <?php else: ?>
              <span class="badge badge-draft">禁用</span>
            <?php endif; ?>
          </td>
          <td><?= e(fmt_date($u['created_at'])) ?></td>
          <td>
            <button class="btn btn-sm" onclick='openEditModal(<?= htmlspecialchars(json_encode($u, JSON_HEX_APOS | JSON_HEX_AMP), ENT_QUOTES) ?>)'>编辑</button>
            <form method="post" action="<?= gblog_admin_url('account/users.php') ?>" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="act" value="status">
              <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'banned' : 'active' ?>">
              <button class="btn btn-sm btn-danger"><?= $u['status'] === 'active' ? '禁用' : '启用' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="edit-user-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:var(--card);border-radius:12px;padding:24px;width:480px;max-width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:17px;">编辑用户</h3>
      <button onclick="document.getElementById('edit-user-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);">✕</button>
    </div>
    <form method="post" action="<?= gblog_admin_url('account/users.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="uid" id="edit-uid" value="">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div>
          <label style="font-size:13px;color:var(--muted);">用户名（不可改）</label>
          <input type="text" id="edit-username" disabled class="admin-input" style="width:100%;margin-top:4px;background:var(--bg-alt);color:var(--muted);">
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">昵称</label>
          <input type="text" name="upd_nickname" id="edit-nickname" class="admin-input" style="width:100%;margin-top:4px;">
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">邮箱</label>
          <input type="email" name="upd_email" id="edit-email" class="admin-input" style="width:100%;margin-top:4px;">
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">角色</label>
          <select name="upd_role" id="edit-role" class="admin-input" style="width:100%;margin-top:4px;">
            <?php foreach ($roles as $rk => $rv): ?>
              <option value="<?= $rk ?>"><?= e($rv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">金币</label>
          <input type="number" name="upd_gold" id="edit-gold" min="0" class="admin-input" style="width:100%;margin-top:4px;">
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">重置密码（留空不改）</label>
          <input type="password" name="upd_password" class="admin-input" style="width:100%;margin-top:4px;">
        </div>
        <div>
          <label style="font-size:13px;color:var(--muted);">恢复码</label>
          <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
            <button type="button" class="btn btn-sm" onclick="genRecovery()">生成新恢复码</button>
            <span style="font-size:12px;color:var(--muted);">生成后旧码立即失效，新码只显示一次</span>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
        <button type="button" class="btn" onclick="document.getElementById('edit-user-modal').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">保存修改</button>
      </div>
    </form>
  </div>
</div>

<div id="permission-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:var(--card);border-radius:12px;padding:24px;width:520px;max-width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:17px;">用户角色权限说明</h3>
      <button onclick="document.getElementById('permission-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);">✕</button>
    </div>
    <table class="data" style="width:100%;font-size:13px;">
      <thead><tr><th>角色</th><th>权限</th></tr></thead>
      <tbody>
        <tr><td><b>管理员</b></td><td>全部权限：文章/歌曲/栏目/用户/设置/主题/广告/友链/评论管理</td></tr>
        <tr><td><b>编辑</b></td><td>管理文章、歌曲、评论，不能改系统设置和用户</td></tr>
        <tr><td><b>作者</b></td><td>发布和编辑自己的文章、歌曲</td></tr>
        <tr><td><b>协作者</b></td><td>投稿待审核，不能直接发布</td></tr>
        <tr><td><b>评论者</b></td><td>只能评论，不能发布内容</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
function openEditModal(u) {
  document.getElementById('edit-uid').value = u.id;
  document.getElementById('edit-username').value = u.username;
  document.getElementById('edit-nickname').value = u.nickname || '';
  document.getElementById('edit-email').value = u.email || '';
  document.getElementById('edit-role').value = u.role;
  document.getElementById('edit-gold').value = u.gold || 0;
  document.getElementById('edit-user-modal').style.display = 'flex';
}
function genRecovery() {
  var uid = document.getElementById('edit-uid').value;
  if (!uid) return;
  if (!confirm('为该用户生成新恢复码？旧恢复码立即失效，新码只显示一次。')) return;
  var f = document.createElement('form');
  f.method = 'post';
  f.action = '<?= gblog_admin_url('account/users.php') ?>';
  [['csrf_token', <?= json_encode(csrf_token()) ?>], ['act', 'gen_recovery'], ['uid', uid]].forEach(function (p) {
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = p[0]; i.value = p[1];
    f.appendChild(i);
  });
  document.body.appendChild(f);
  f.submit();
}
</script>
<?php require __DIR__ . '/../inc/footer.php'; ?>
