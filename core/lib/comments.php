<?php
/* 评论 —— 评论读写、审核、热门评论。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function get_comments_by_post(int $targetId, string $status = 'approved', string $targetType = 'post'): array
{
    if ($targetId <= 0 || !in_array($targetType, ['post', 'song'], true)) return [];
    $table = $targetType === 'song' ? 'songs' : 'posts';
    $target = db()->prepare("SELECT site_id FROM {$table} WHERE id = ? LIMIT 1");
    $target->execute([$targetId]);
    $siteId = (int)$target->fetchColumn();
    if ($siteId <= 0) return [];
    $stmt = db()->prepare(
        "SELECT * FROM comments WHERE target_type = ? AND target_id = ? AND site_id = ? AND status = ?
         ORDER BY created_at ASC, id ASC"
    );
    $stmt->execute([$targetType, $targetId, $siteId, $status]);
    return $stmt->fetchAll();
}

function add_comment(int $targetId, string $name, string $email, string $content, int $parentId = 0, int $userId = 0, string $targetType = 'post'): bool
{
    if (!gblog_rate_allow('comment_ip', gblog_client_ip(), 10, 600)) return false;
    $name = trim($name);
    $email = trim($email);
    $content = trim($content);
    if ($name === '' || $content === '' || mb_strlen($name) > 80 || mb_strlen($email) > 190 || mb_strlen($content) > 5000) return false;
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    if (!in_array($targetType, ['post', 'song'], true) || $targetId <= 0) return false;
    // 回复必须挂在本目标下，避免跨内容串楼
    if ($parentId > 0) {
        $chk = db()->prepare("SELECT COUNT(*) FROM comments WHERE id = ? AND target_type = ? AND target_id = ?");
        $chk->execute([$parentId, $targetType, $targetId]);
        if ((int)$chk->fetchColumn() === 0) $parentId = 0;
    }
    $moderate = get_setting('comment_moderate', '1') === '1';
    $status = $moderate ? 'pending' : 'approved';
    $siteId = 0;
    if ($targetType === 'post') {
        $sst = db()->prepare('SELECT site_id FROM posts WHERE id = ? LIMIT 1');
        $sst->execute([$targetId]);
        $siteId = (int)($sst->fetchColumn() ?: 0);
    } elseif ($targetType === 'song') {
        $sst = db()->prepare('SELECT site_id FROM songs WHERE id = ? LIMIT 1');
        $sst->execute([$targetId]);
        $siteId = (int)($sst->fetchColumn() ?: 0);
    }
    $stmt = db()->prepare(
        "INSERT INTO comments (post_id, target_type, target_id, site_id, parent_id, name, email, content, status, user_id)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    return $stmt->execute([
        $targetType === 'post' ? $targetId : 0, $targetType, $targetId, $siteId,
        $parentId > 0 ? $parentId : null, $name, $email ?: null, $content, $status, $userId,
    ]);
}

function gblog_handle_comments(int $targetId, string $targetType = 'post'): array
{
    $open     = get_setting('comment_open', '1') === '1';
    $moderate = get_setting('comment_moderate', '1') === '1';
    $msg      = '';
    $ok       = false;

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && !empty($_SESSION['gblog_comment_flash'])) {
        unset($_SESSION['gblog_comment_flash']);
        $ok  = true;
        $msg = $moderate ? '评论已提交，审核通过后会显示。' : '评论发表成功。';
    }

    if ($open && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['comment_submit'])) {
        if (!csrf_check()) {
            $msg = '提交失败，请刷新页面后重试。';
        } else {
            $cname    = trim((string)($_POST['cname'] ?? ''));
            $cemail   = trim((string)($_POST['cemail'] ?? ''));
            $ccontent = trim((string)($_POST['ccontent'] ?? ''));
            $parentId = max(0, (int)($_POST['parent_id'] ?? 0));
            if ($cname === '' || $ccontent === '') {
                $msg = '请填写昵称和评论内容。';
            } else {
                // 时间比较改用 PHP 侧时间戳：DATE_SUB(NOW(), ...) 只有 MySQL 有，
                // SQLite 站点提交评论会直接 Fatal。created_at 存的是 Asia/Shanghai 本地时间。
                $dup = db()->prepare(
                    "SELECT COUNT(*) FROM comments
                     WHERE target_type = ? AND target_id = ? AND name = ? AND content = ?
                       AND created_at > ?"
                );
                $dup->execute([$targetType, $targetId, $cname, $ccontent, date('Y-m-d H:i:s', time() - 120)]);
                if ((int)$dup->fetchColumn() > 0) {
                    $msg = '相同评论刚刚已提交，请勿重复发送。';
                } else {
                    $meId = (int)(current_user()['id'] ?? 0);
                    if (add_comment($targetId, $cname, $cemail, $ccontent, $parentId, $meId, $targetType)) {
                        $_SESSION['gblog_comment_flash'] = 1;
                        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/index.php');
                        $uri = preg_replace('/#.*$/', '', str_replace(["\r", "\n"], '', $uri));
                        if ($uri === '' || $uri[0] !== '/' || strncmp($uri, '//', 2) === 0) {
                            $uri = '/index.php';
                        }
                        header('Location: ' . $uri . '#comments', true, 303);
                        exit;
                    }
                    $msg = '提交失败，请稍后再试。';
                }
            }
        }
    }
    return [
        'open'     => $open,
        'msg'      => $msg,
        'ok'       => $ok,
        'comments' => $open ? get_comments_by_post($targetId, 'approved', $targetType) : [],
    ];
}

function get_all_comments(string $statusFilter = '', string $keyword = ''): array
{
    $where = [];
    $params = [];
    if (in_array($statusFilter, ['pending', 'approved', 'hidden'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $statusFilter;
    }
    if ($keyword !== '') {
        $where[] = '(c.name LIKE ? OR c.content LIKE ?)';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
    }
    $sql = "SELECT c.*, p.title AS post_title, st.name AS site_name, sng.title AS song_title
            FROM comments c
            LEFT JOIN posts p ON p.id = c.target_id AND c.target_type = 'post'
            LEFT JOIN sites st ON st.id = p.site_id
            LEFT JOIN songs sng ON sng.id = c.target_id AND c.target_type = 'song'";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.created_at DESC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function set_comment_status(int $id, string $status): void
{
    if (in_array($status, ['pending', 'approved', 'hidden'], true)) {
        db()->prepare("UPDATE comments SET status = ? WHERE id = ?")->execute([$status, $id]);
    }
}

function delete_comment(int $id): void
{
    db()->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
}

/**
 * 侧栏评论列表取数。
 *
 * $mode = 'latest' → 最新评论：按评论时间倒序（最新在前）
 * $mode = 'hot'    → 热门评论：按被评论内容的热度（阅读量）倒序，同热度按评论时间倒序
 *
 * 评论可挂在 post 或 song 上（comments.target_type），两种都取，
 * 并按所属栏目过滤；返回 target_id / song_id / target_type 供模板拼链接。
 */
function get_hot_comments(int $limit = 5, int $siteId = 0, string $mode = 'latest'): array{
    $limit = max(1, min(200, (int)$limit));
    $sql = "SELECT c.name, c.content, c.created_at, c.target_type, c.target_id,
                   p.title AS post_title, p.view_count AS post_views,
                   sng.id AS song_id, sng.title AS song_title, sng.site_id AS song_site_id
            FROM comments c
            LEFT JOIN posts p ON p.id = c.target_id AND c.target_type = 'post'
            LEFT JOIN songs sng ON sng.id = c.target_id AND c.target_type = 'song'
            WHERE c.status = 'approved'
              AND ((c.target_type = 'post' AND p.site_id = c.site_id AND " . gblog_active_site_clause('p.site_id') . ")
                OR (c.target_type = 'song' AND sng.site_id = c.site_id AND " . gblog_active_site_clause('sng.site_id') . "))";
    if ($siteId > 0) {
        $sql .= " AND c.site_id = " . (int)$siteId;
    }
    // 热门：先按被评论内容阅读量，再按评论时间；最新：直接按评论时间
    $sql .= ($mode === 'hot'
        ? " ORDER BY COALESCE(p.view_count, 0) DESC, c.created_at DESC, c.id DESC"
        : " ORDER BY c.created_at DESC, c.id DESC");
    $sql .= " LIMIT " . $limit;
    return db()->query($sql)->fetchAll();
}
