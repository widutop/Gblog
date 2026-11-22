<?php
/* 用户与金币 —— 账号、登录、金币、下载权限、互动足迹。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $u = null;
    static $loaded = false;
    if (!$loaded) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();
        $u = ($row && $row['status'] === 'active') ? $row : null;
        if ($u && (int)($_SESSION['user_pv'] ?? -1) !== (int)($u['password_version'] ?? 0)) $u = null;
        if ($u && (int)($u['must_change_password'] ?? 0) === 1) $_SESSION['force_pw_change'] = 1;
        if (!$u) { unset($_SESSION['user_id'], $_SESSION['user_pv'], $_SESSION['force_pw_change']); }
        $loaded = true;
    }
    return $u;
}

function is_user_logged_in(): bool { return current_user() !== null; }

function username_exists(string $username): bool
{
    $stmt = db()->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    return (bool)$stmt->fetch();
}

function register_user(string $username, string $password, string $email = '', string $nickname = '', string $phone = '', string $qq = '', string $wechat = '', string $security_code = ''): array
{
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return ['ok' => false, 'msg' => '用户名需为 3-20 位字母/数字/下划线'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'msg' => '密码至少 6 位'];
    }
    if ($security_code !== '') {
        $codeErr = gblog_recovery_code_error($security_code);
        if ($codeErr !== '') {
            return ['ok' => false, 'msg' => $codeErr];
        }
    }
    $fieldRules = [
        'email' => ['value' => trim($email), 'label' => '邮箱', 'max' => 190],
        'phone' => ['value' => trim($phone), 'label' => '手机号', 'max' => 32],
        'qq' => ['value' => trim($qq), 'label' => 'QQ', 'max' => 32],
        'wechat' => ['value' => trim($wechat), 'label' => '微信号', 'max' => 100],
    ];
    foreach ($fieldRules as $key => $field) {
        $rule = get_setting('register_' . $key, 'optional');
        $value = $field['value'];
        if ($rule === 'required' && $value === '') return ['ok' => false, 'msg' => $field['label'] . '为必填项'];
        if ($rule === 'off') $value = '';
        if (mb_strlen($value) > $field['max']) return ['ok' => false, 'msg' => $field['label'] . '长度超出限制'];
        if ($key === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'msg' => '邮箱格式不正确'];
        $fieldRules[$key]['value'] = $value;
    }
    $email = $fieldRules['email']['value'];
    $phone = $fieldRules['phone']['value'];
    $qq = $fieldRules['qq']['value'];
    $wechat = $fieldRules['wechat']['value'];
    if (username_exists($username)) {
        return ['ok' => false, 'msg' => '用户名已被占用'];
    }
    $gold = get_setting('gold_enabled', '1') === '1' ? max(0, (int)get_setting('register_gold', 0)) : 0;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $nick = $nickname !== '' ? $nickname : $username;
    $stmt = db()->prepare(
        "INSERT INTO users (username, password_hash, nickname, email, phone, qq, wechat, security_code, role, gold, status)
         VALUES (?,?,?,?,?,?,?,?, 'member', ?, 'active')"
    );
    $stmt->execute([$username, $hash, $nick, $email ?: null, $phone ?: null, $qq ?: null, $wechat ?: null, $security_code !== '' ? gblog_recovery_hash($security_code) : null, $gold]);
    $uid = (int)db()->lastInsertId();
    if ($gold > 0) {
        change_gold($uid, $gold, '注册赠送');
    }
    return ['ok' => true, 'msg' => '注册成功', 'user' => ['id' => $uid, 'username' => $username]];
}

function verify_login(string $username, string $password): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) return null;
    if ($u['status'] !== 'active') return null;
    return $u;
}

function change_gold(int $userId, int $delta, string $reason): bool
{
    if ($userId <= 0 || $delta == 0) return false;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $chk->execute([$userId]);
        if (!$chk->fetch()) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE users SET gold = gold + ? WHERE id = ?')->execute([$delta, $userId]);
        $pdo->prepare('INSERT INTO coin_logs (user_id, amount, reason) VALUES (?,?,?)')
            ->execute([$userId, $delta, $reason]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        gblog_log('change_gold', $e->getMessage());
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

function get_gold_logs(int $userId, int $limit = 30): array
{
    $stmt = db()->prepare("SELECT amount, reason, created_at FROM coin_logs WHERE user_id = ? ORDER BY id DESC LIMIT " . (int)$limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_user_downloads(int $userId, int $limit = 30): array
{
    $sql = "SELECT d.created_at, p.title, p.id FROM download_logs d
            JOIN posts p ON p.id = d.post_id
            WHERE d.user_id = ? AND " . gblog_active_site_clause('p.site_id') . "
            ORDER BY d.id DESC LIMIT " . (int)$limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_user_posts(int $userId, int $limit = 50, int $offset = 0): array
{
    $sql = "SELECT id, title, status, view_count, created_at FROM posts WHERE user_id = ? AND " . gblog_active_site_clause('site_id') . " ORDER BY id DESC LIMIT " . (int)$limit;
    if ($offset > 0) $sql .= " OFFSET " . (int)$offset;
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function download_state(array $post, ?array $user): array
{
    $level = $post['download_level'] ?? 'free';
    $price = (int)($post['download_price'] ?? 0);
    if ($level === 'free') {
        return ['level' => 'free', 'price' => 0, 'allowed' => true, 'reason' => ''];
    }
    if (!$user) {
        return ['level' => $level, 'price' => $price, 'allowed' => false, 'reason' => $level === 'login' ? '请先登录后下载' : '需登录后下载'];
    }
    if ($level === 'login') {
        return ['level' => 'login', 'price' => 0, 'allowed' => true, 'reason' => ''];
    }
    if (has_downloaded($user['id'], (int)$post['id'])) {
        return ['level' => 'coin', 'price' => $price, 'allowed' => true, 'reason' => ''];
    }
    return ['level' => 'coin', 'price' => $price, 'allowed' => false, 'reason' => '需 ' . $price . ' 金币'];
}

function has_downloaded(int $userId, int $postId): bool
{
    $stmt = db()->prepare("SELECT id FROM download_logs WHERE user_id = ? AND post_id = ? LIMIT 1");
    $stmt->execute([$userId, $postId]);
    return (bool)$stmt->fetch();
}

function do_coin_download(int $userId, int $postId, int $price): bool
{
    if ($price < 0) return false;
    if (has_downloaded($userId, $postId)) return true;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($price > 0) {
            $st = $pdo->prepare('UPDATE users SET gold = gold - ? WHERE id = ? AND gold >= ?');
            $st->execute([$price, $userId, $price]);
            if ($st->rowCount() === 0) { $pdo->rollBack(); return false; }
            $pdo->prepare('INSERT INTO coin_logs (user_id, amount, reason) VALUES (?,?,?)')
                ->execute([$userId, -$price, '下载资源 #' . $postId]);
        }
        $pdo->prepare('INSERT INTO download_logs (user_id, post_id) VALUES (?,?)')->execute([$userId, $postId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return has_downloaded($userId, $postId);
    }
}

function signed_today(int $userId): bool
{
    $todayStart = strtotime('today 00:00:00');
    $todayEnd   = strtotime('tomorrow 00:00:00');
    $local = gblog_sql_local_time('created_at');
    $stmt = db()->prepare(
        "SELECT id FROM coin_logs
         WHERE user_id = ? AND reason = '每日签到'
           AND {$local} >= ? AND {$local} < ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId, date('Y-m-d H:i:s', $todayStart), date('Y-m-d H:i:s', $todayEnd)]);
    return (bool)$stmt->fetch();
}

function gblog_sign_daily(int $userId, int $gold): ?string
{
    if ($userId <= 0 || $gold <= 0) return '签到奖励未开启。';
    $pdo = db();
    try {
        $pdo->beginTransaction();
// 锁用户行串行化并发签到，判定与加币必须同一事务
        $lock = 'SELECT id FROM users WHERE id = ? LIMIT 1';
        if (gblog_db_driver() === 'mysql') $lock .= ' FOR UPDATE';
        $st = $pdo->prepare($lock);
        $st->execute([$userId]);
        if (!$st->fetch()) { $pdo->rollBack(); return '账号不存在。'; }
        if (signed_today($userId)) { $pdo->rollBack(); return '今天已经签到过了。'; }
        $pdo->prepare('UPDATE users SET gold = gold + ? WHERE id = ?')->execute([$gold, $userId]);
        $pdo->prepare('INSERT INTO coin_logs (user_id, amount, reason) VALUES (?,?,?)')
            ->execute([$userId, $gold, '每日签到']);
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return '签到失败，请稍后重试。';
    }
}

function get_user_reactions(int $userId, array $postIds): array
{
    $map = [];
    $ids = [];
    foreach ($postIds as $v) {
        $v = (int)$v;
        if ($v > 0) $ids[] = $v;
    }
    if (!$ids) return $map;
    $ph = str_repeat('?,', count($ids) - 1) . '?';
    $params = array_merge([$userId], $ids);
    $stmt = db()->prepare("SELECT r.post_id, r.rtype FROM post_reactions r
        INNER JOIN posts p ON p.id = r.post_id
        WHERE r.user_id = ? AND r.post_id IN ($ph) AND " . gblog_active_site_clause('p.site_id'));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $map[$r['post_id'] . ':' . $r['rtype']] = true;
    }
    return $map;
}

// 列名经白名单校验（favorite_count / like_count）
function toggle_post_reaction(int $userId, int $postId, string $type): array
{
    $type = $type === 'favorite' ? 'favorite' : 'like';
    $col  = $type === 'favorite' ? 'favorite_count' : 'like_count';
    $pdo  = db();
    $ex   = $pdo->prepare('SELECT 1 FROM post_reactions WHERE user_id = ? AND post_id = ? AND rtype = ?');
    $ex->execute([$userId, $postId, $type]);
    $exists = (bool)$ex->fetchColumn();
    $ex->closeCursor();
    if ($exists) {
        $pdo->prepare('DELETE FROM post_reactions WHERE user_id = ? AND post_id = ? AND rtype = ?')
            ->execute([$userId, $postId, $type]);
        $pdo->prepare("UPDATE posts SET `$col` = CASE WHEN `$col` > 0 THEN `$col` - 1 ELSE 0 END WHERE id = ?")
            ->execute([$postId]);
        $state = false;
    } else {
        $pdo->prepare('INSERT INTO post_reactions (user_id, post_id, rtype) VALUES (?, ?, ?)')
            ->execute([$userId, $postId, $type]);
        $pdo->prepare("UPDATE posts SET `$col` = `$col` + 1 WHERE id = ?")
            ->execute([$postId]);
        $state = true;
    }
// 必须先 execute() 再 fetchColumn()：漏掉 execute 会让计数恒为 0
    $cs = $pdo->prepare("SELECT `$col` FROM posts WHERE id = ?");
    $cs->execute([$postId]);
    $cnt = (int)$cs->fetchColumn();
    $cs->closeCursor();
    return ['state' => $state, 'count' => $cnt];
}

function get_user_reactions_list(int $userId, string $type, int $limit = 50): array
{
    $type = $type === 'favorite' ? 'favorite' : 'like';
    $lim  = max(1, min(200, $limit));
    $stmt = db()->prepare(
        "SELECT p.id, p.title, p.status, r.id AS rid
         FROM post_reactions r
         INNER JOIN posts p ON p.id = r.post_id
         WHERE r.user_id = ? AND r.rtype = ? AND p.status = 'published'
           AND " . gblog_active_site_clause('p.site_id') . "
         ORDER BY r.id DESC
         LIMIT ?"
    );
    $stmt->execute([$userId, $type, $lim]);
    return $stmt->fetchAll();
}

function record_view_history(?array $user, int $postId): void
{
    if (!$user || empty($user['id']) || $postId <= 0) return;
    $uid = (int)$user['id'];
    try {
        $today = date('Y-m-d') . '%';
        $st = db()->prepare("SELECT id FROM view_history WHERE user_id = ? AND post_id = ? AND viewed_at LIKE ? LIMIT 1");
        $st->execute([$uid, $postId, $today]);
        if ($st->fetch()) return;
        db()->prepare("INSERT INTO view_history (user_id, post_id, viewed_at) VALUES (?,?,?)")
            ->execute([$uid, $postId, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        gblog_log('record_view_history', $e->getMessage());
    }
}

function get_user_view_history(int $userId, int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT v.post_id, p.id, p.title, p.status, p.view_count, v.viewed_at
         FROM view_history v
         INNER JOIN posts p ON p.id = v.post_id
         WHERE v.user_id = ? AND " . gblog_active_site_clause('p.site_id') . "
         ORDER BY v.viewed_at DESC
         LIMIT 200"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $seen = [];
    $out  = [];
    foreach ($rows as $r) {
        $pid = (int)$r['post_id'];
        if (isset($seen[$pid])) continue;
        $seen[$pid] = true;
        $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}

function get_user_comments(int $userId, int $limit = 30): array
{
    $sql = "SELECT c.id, c.target_type, c.target_id, c.content, c.status, c.created_at,
                   COALESCE(p.title, sng.title, '') AS title
            FROM comments c
            LEFT JOIN posts p ON p.id = c.target_id AND c.target_type = 'post'
            LEFT JOIN songs sng ON sng.id = c.target_id AND c.target_type = 'song'
            WHERE c.user_id = ?
              AND ((c.target_type = 'song' AND sng.site_id = c.site_id AND " . gblog_active_site_clause('sng.site_id') . ")
                   OR (c.target_type = 'post' AND p.site_id = c.site_id AND " . gblog_active_site_clause('p.site_id') . "))
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT " . (int)$limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_user_purchases(int $userId, int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT id, amount, reason, created_at FROM coin_logs
         WHERE user_id = ? AND amount < 0
         ORDER BY id DESC LIMIT " . (int)$limit
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $postId = 0;
        if (preg_match('/#(\d+)$/', (string)$r['reason'], $m)) {
            $postId = (int)$m[1];
        }
        $title = '';
        if ($postId > 0) {
            $t = db()->prepare("SELECT title FROM posts WHERE id = ? AND " . gblog_active_site_clause('site_id') . " LIMIT 1");
            $t->execute([$postId]);
            if ($row = $t->fetch()) {
                $title = $row['title'];
            }
        }
        $r['post_id']    = $postId;
        $r['post_title'] = $title;
        $out[] = $r;
    }
    return $out;
}
