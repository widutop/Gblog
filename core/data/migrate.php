<?php
/**
 * 幂等迁移铁律：优先 ADD COLUMN / CREATE TABLE IF NOT EXISTS / 补默认设置；仅清理已确认弃用且无引用的字段。
 */

if (!defined('GBLOG_SCHEMA_VERSION')) define('GBLOG_SCHEMA_VERSION', 12);

function gblog_has_table(PDO $pdo, string $table): bool
{
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
            $st->execute([$table]);
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $st->execute([$table]);
        }
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function gblog_has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info("' . $table . '")')->fetchAll();
            foreach ($rows as $r) {
                if (strcasecmp((string)($r['name'] ?? ''), $column) === 0) return true;
            }
            return false;
        }
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$table, $column]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false; // 判断失败时宁可尝试 ADD，也不能误判成"已存在"而跳过
    }
}

function gblog_set_schema_version(PDO $pdo, int $version): void
{
    $pdo->prepare("DELETE FROM settings WHERE skey = 'schema_version'")->execute();
    $st = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES ('schema_version', ?)");
    $st->execute([(string)$version]);
}

function gblog_create_table(PDO $pdo, string $mysql, string $sqlite): void
{
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? $sqlite : $mysql;
    $pdo->exec($sql);
}

// 必须放在版本判断之前：备份恢复会 DROP TABLE 连带删掉触发器。
function gblog_sqlite_ensure_triggers(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return;
    try {
        $hasPosts = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='posts'")->fetchColumn();
        if ($hasPosts === 0) return;
        $has = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='trigger' AND name IN ('trg_posts_updated','trg_chapters_updated')")->fetchColumn();
        if ($has >= 2) return;
        $pdo->exec('CREATE TRIGGER IF NOT EXISTS trg_posts_updated AFTER UPDATE ON posts
            FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
            BEGIN UPDATE posts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; END');
        $pdo->exec('CREATE TRIGGER IF NOT EXISTS trg_chapters_updated AFTER UPDATE ON novel_chapters
            FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
            BEGIN UPDATE novel_chapters SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; END');
    } catch (Throwable $e) {
    }
}

function gblog_ensure_builtin_sites(PDO $pdo): void
{
    try {
        if (!gblog_has_column($pdo, 'sites', 'is_builtin')) {
            $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $def = $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0';
            $pdo->exec("ALTER TABLE sites ADD COLUMN is_builtin {$def}");
        }
        $cStmt = $pdo->query("SELECT COUNT(*) FROM sites WHERE is_builtin = 1");
        $cnt   = (int)$cStmt->fetchColumn();
        $cStmt->closeCursor();
        if ($cnt > 0) return;

        $iStmt = $pdo->query("SELECT id FROM sites ORDER BY id ASC LIMIT 5");
        $ids   = $iStmt->fetchAll(PDO::FETCH_COLUMN);
        $iStmt->closeCursor();
        if (!$ids) return;

        $upd = $pdo->prepare("UPDATE sites SET is_builtin = 1 WHERE id = ?");
        foreach ($ids as $bid) { $upd->execute([(int)$bid]); }
        $upd->closeCursor();
    } catch (Throwable $e) {
    }
}

function gblog_ensure_builtin_apps(PDO $pdo): void
{
    $builtins = [
        ['type' => 'plugin', 'id' => 'portal-top',     'version' => '1.0.0'],
    ];
    try {
        $pdo->prepare("DELETE FROM apps WHERE app_id = 'soft-header' AND type = 'plugin'")->execute();
        $chk = $pdo->prepare('SELECT COUNT(*) FROM apps WHERE app_id = ? AND type = ?');
        $ins = $pdo->prepare('INSERT INTO apps (app_id, type, enabled, version) VALUES (?, ?, 1, ?)');
        foreach ($builtins as $b) {
            $chk->execute([$b['id'], $b['type']]);
            if ((int)$chk->fetchColumn() > 0) continue;
            $ins->execute([$b['id'], $b['type'], $b['version']]);
        }
    } catch (Throwable $e) {
    }
}

function gblog_migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
// 触发器自愈必须优先于版本判断（备份恢复会删掉触发器但版本号仍是最新）
        gblog_sqlite_ensure_triggers($pdo);

        $st   = $pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version'");
        $ver  = (int)($st->fetchColumn() ?: 0);
        $st->closeCursor();

        gblog_ensure_builtin_sites($pdo);
        gblog_ensure_builtin_apps($pdo);

        $missing = !gblog_has_column($pdo, 'sites', 'is_builtin')
                || !gblog_has_column($pdo, 'sites', 'home_mode')
                || !gblog_has_column($pdo, 'posts', 'file_size')
                || !gblog_has_column($pdo, 'posts', 'download_count')
                || !gblog_has_column($pdo, 'users', 'password_version')
                || !gblog_has_column($pdo, 'users', 'must_change_password')
                || !gblog_has_column($pdo, 'posts', 'software_version')
                || !gblog_has_column($pdo, 'posts', 'official_url')
                || !gblog_has_column($pdo, 'posts', 'language')
                || !gblog_has_column($pdo, 'posts', 'rating')
                || !gblog_has_column($pdo, 'posts', 'software_type')
                || !gblog_has_column($pdo, 'posts', 'software_platform')
                || !gblog_has_column($pdo, 'comments', 'target_type')
                || !gblog_has_column($pdo, 'comments', 'target_id')
                || !gblog_has_column($pdo, 'songs', 'site_id')
                || !gblog_has_column($pdo, 'comments', 'site_id')
                || !gblog_has_column($pdo, 'tags', 'site_id')
                || !gblog_has_column($pdo, 'content_ids', 'seq')
                || gblog_has_column($pdo, 'posts', 'is_featured')
                || gblog_has_column($pdo, 'sites', 'theme')
                || gblog_has_column($pdo, 'posts', 'slug')
                || gblog_has_column($pdo, 'categories', 'slug')
                || gblog_has_column($pdo, 'tags', 'slug')
                || !gblog_has_table($pdo, 'link_categories')
                || !gblog_has_column($pdo, 'links', 'category_id');
        if ($ver >= GBLOG_SCHEMA_VERSION && !$missing) return;

        if (!gblog_has_column($pdo, 'sites', 'home_mode')) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN home_mode VARCHAR(20) NOT NULL DEFAULT 'default'");
        }
        if (!gblog_has_column($pdo, 'posts', 'file_size')) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN file_size VARCHAR(50) NULL');
        }
        if (!gblog_has_column($pdo, 'posts', 'download_count')) {
            $cntCol = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
            $pdo->exec("ALTER TABLE posts ADD COLUMN download_count {$cntCol} NOT NULL DEFAULT 0");
        }
        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS apps (
                app_id       VARCHAR(100) NOT NULL,
                type         VARCHAR(20)  NOT NULL,
                enabled      TINYINT(1)   NOT NULL DEFAULT 0,
                version      VARCHAR(30)  NOT NULL DEFAULT "",
                installed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (app_id, type)
            )',
            'CREATE TABLE IF NOT EXISTS apps (
                app_id TEXT NOT NULL, type TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
                version TEXT NOT NULL DEFAULT \'\', installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (app_id, type)
            )'
        );

        $intCol = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'INT';
        if (!gblog_has_column($pdo, 'users', 'password_version')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_version {$intCol} NOT NULL DEFAULT 0");
        }
        if (!gblog_has_column($pdo, 'users', 'must_change_password')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password {$intCol} NOT NULL DEFAULT 0");
        }

        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS password_resets (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id     INT UNSIGNED NOT NULL,
                token_hash  VARCHAR(64)  NOT NULL,
                channel     VARCHAR(20)  NOT NULL DEFAULT "",
                expires_at  INT          NOT NULL,
                used        TINYINT(1)   NOT NULL DEFAULT 0,
                ip          VARCHAR(64)  NULL,
                created_at  INT          NOT NULL,
                PRIMARY KEY (id), KEY idx_reset_hash (token_hash), KEY idx_reset_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL, channel TEXT NOT NULL DEFAULT \'\',
                expires_at INTEGER NOT NULL, used INTEGER NOT NULL DEFAULT 0,
                ip TEXT, created_at INTEGER NOT NULL
            )'
        );
        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS rate_limits (
                bucket       VARCHAR(64)  NOT NULL,
                identity     VARCHAR(127) NOT NULL,
                hits         INT          NOT NULL DEFAULT 0,
                window_start INT          NOT NULL,
                PRIMARY KEY (bucket, identity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS rate_limits (
                bucket TEXT NOT NULL, identity TEXT NOT NULL,
                hits INTEGER NOT NULL DEFAULT 0, window_start INTEGER NOT NULL,
                PRIMARY KEY (bucket, identity)
            )'
        );
        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS security_logs (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                action      VARCHAR(64)  NOT NULL,
                user_id     INT          NOT NULL DEFAULT 0,
                detail      VARCHAR(255) NULL,
                ip          VARCHAR(64)  NULL,
                created_at  INT          NOT NULL,
                PRIMARY KEY (id), KEY idx_log_time (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS security_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL,
                user_id INTEGER NOT NULL DEFAULT 0, detail TEXT, ip TEXT,
                created_at INTEGER NOT NULL
            )'
        );
        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS content_ids (
                seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (seq)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS content_ids (
                seq INTEGER PRIMARY KEY AUTOINCREMENT
            )'
        );

        try {
            $rows = $pdo->query("SELECT id, security_code FROM users WHERE security_code IS NOT NULL AND security_code <> ''")->fetchAll();
            if ($rows) {
                $upd = $pdo->prepare('UPDATE users SET security_code = ? WHERE id = ?');
                foreach ($rows as $r) {
                    $cur = (string)$r['security_code'];
                    if (preg_match('/^\$(2[aby]|argon2)/', $cur)) continue;
                    $upd->execute([password_hash($cur, PASSWORD_DEFAULT), (int)$r['id']]);
                }
            }
        } catch (Throwable $e) {
        }

        try {
            $s = $pdo->prepare("SELECT svalue FROM settings WHERE skey = 'app_secret'");
            $s->execute();
            if (!$s->fetchColumn()) {
                $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES ('app_secret', ?)")
                    ->execute([bin2hex(random_bytes(32))]);
            }
        } catch (Throwable $e) {
        }

        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS post_reactions (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    INT UNSIGNED NOT NULL,
                post_id    INT UNSIGNED NOT NULL,
                rtype      VARCHAR(16)  NOT NULL DEFAULT \'like\',
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_react (user_id, post_id, rtype),
                KEY idx_react_post (post_id),
                KEY idx_react_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS post_reactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                post_id INTEGER NOT NULL,
                rtype TEXT NOT NULL DEFAULT \'like\',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_react ON post_reactions (user_id, post_id, rtype)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_react_post ON post_reactions (post_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_react_user ON post_reactions (user_id)');
        }

        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS view_history (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    INT UNSIGNED NOT NULL,
                post_id    INT UNSIGNED NOT NULL,
                viewed_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_vh_user (user_id),
                KEY idx_vh_post (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS view_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                post_id INTEGER NOT NULL,
                viewed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vh_user ON view_history (user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vh_post ON view_history (post_id)');
        }

        $uintCol = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
        if (!gblog_has_column($pdo, 'comments', 'user_id')) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN user_id {$uintCol} NOT NULL DEFAULT 0");
        }
        // 评论通用目标：target_type + target_id（post/song/…）。target_id 是本条评论真正挂载的内容 id，
        // post_id 自 v5 起仅作历史列保留（歌曲评论写 0），所有读写一律走 target_*。
        if (!gblog_has_column($pdo, 'comments', 'target_type')) {
            try {
                $pdo->exec("ALTER TABLE comments ADD COLUMN target_type VARCHAR(20) NOT NULL DEFAULT 'post'");
            } catch (Throwable $e) {
            }
        }
        if (!gblog_has_column($pdo, 'comments', 'target_id')) {
            try {
                $pdo->exec("ALTER TABLE comments ADD COLUMN target_id {$uintCol} NOT NULL DEFAULT 0");
                $pdo->exec("UPDATE comments SET target_id = post_id WHERE target_id = 0");
            } catch (Throwable $e) {
            }
        }
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comment_target ON comments (target_type, target_id, status)');
            } else {
                $idx = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
                                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments'
                                      AND INDEX_NAME = 'idx_comment_target'");
                if ((int)$idx->fetchColumn() === 0) {
                    $pdo->exec('ALTER TABLE comments ADD INDEX idx_comment_target (target_type, target_id, status)');
                }
            }
        } catch (Throwable $e) {
        }
        // 歌曲评论与本表 post_id 无关，fk_comment_post 会在写入/删除时误伤，故移除；文章删除时由代码清理评论。
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            try {
                $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
                                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'comments'
                                     AND CONSTRAINT_NAME = 'fk_comment_post'");
                if ($fk && $fk->fetchColumn()) {
                    $pdo->exec('ALTER TABLE comments DROP FOREIGN KEY fk_comment_post');
                }
            } catch (Throwable $e) {
            }
        }

        // 歌曲 / 评论 归属站点：补列 → 回填 → 建索引。幂等，可重复执行。
        $uintCol = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
        if (!gblog_has_column($pdo, 'songs', 'site_id')) {
            $pdo->exec("ALTER TABLE songs ADD COLUMN site_id {$uintCol} NOT NULL DEFAULT 0");
        }
        if (!gblog_has_column($pdo, 'comments', 'site_id')) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN site_id {$uintCol} NOT NULL DEFAULT 0");
        }
        // 回填：歌曲归首个启用音乐站点；评论按目标表反查站点。
        try {
            $musicSite = $pdo->query("SELECT id FROM sites WHERE type = 'song' AND enabled = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($musicSite !== false && $musicSite !== null) {
                $pdo->exec("UPDATE songs SET site_id = " . (int)$musicSite . " WHERE site_id = 0");
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] songs.site_id backfill failed: ' . $e->getMessage());
        }
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec("UPDATE comments SET site_id = (SELECT p.site_id FROM posts p WHERE p.id = comments.target_id) WHERE target_type = 'post' AND (site_id IS NULL OR site_id = 0)");
                $pdo->exec("UPDATE comments SET site_id = (SELECT s.site_id FROM songs s WHERE s.id = comments.target_id) WHERE target_type = 'song' AND (site_id IS NULL OR site_id = 0)");
            } else {
                $pdo->exec("UPDATE comments c JOIN posts p ON p.id = c.target_id AND c.target_type = 'post' SET c.site_id = p.site_id WHERE c.site_id = 0");
                $pdo->exec("UPDATE comments c JOIN songs s ON s.id = c.target_id AND c.target_type = 'song' SET c.site_id = s.site_id WHERE c.site_id = 0 AND c.target_type = 'song'");
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] comments.site_id backfill failed: ' . $e->getMessage());
        }
        // 标签归属站点：补列 → 回填（按标签下文章的多数站点）。
        if (!gblog_has_column($pdo, 'tags', 'site_id')) {
            $pdo->exec("ALTER TABLE tags ADD COLUMN site_id {$uintCol} NOT NULL DEFAULT 0");
        }
        try {
            $rows = $pdo->query("SELECT pt.tag_id AS tid, p.site_id AS sid, COUNT(*) AS n FROM post_tags pt JOIN posts p ON p.id = pt.post_id GROUP BY pt.tag_id, p.site_id")->fetchAll(PDO::FETCH_ASSOC);
            $best = [];
            foreach ($rows as $r) {
                $tid = (int)$r['tid'];
                if (!isset($best[$tid]) || (int)$r['n'] > $best[$tid]['n']) $best[$tid] = ['sid' => (int)$r['sid'], 'n' => (int)$r['n']];
            }
            $st = $pdo->prepare('UPDATE tags SET site_id = ? WHERE id = ? AND site_id = 0');
            foreach ($best as $tid => $b) $st->execute([$b['sid'], $tid]);
        } catch (Throwable $e) {
            error_log('[Gblog migration] tags.site_id backfill failed: ' . $e->getMessage());
        }
        // 索引：MySQL 先查 information_schema 避免重复 ADD；SQLite 用 CREATE INDEX IF NOT EXISTS。
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $idxDefs = [
            'songs'   => [
                'idx_song_site_status' => '(site_id, status)',
                'idx_song_status_play' => '(status, play_count)',
            ],
            'comments' => [
                'idx_comment_site'      => '(site_id, status)',
            ],
            'tags' => [
                'idx_tags_site' => '(site_id)',
            ],
        ];
        foreach ($idxDefs as $tbl => $idxs) {
            foreach ($idxs as $iname => $idef) {
                try {
                    if ($isSqlite) {
                        $pdo->exec("CREATE INDEX IF NOT EXISTS {$iname} ON {$tbl} {$idef}");
                    } else {
                        $has = $pdo->query(
                            "SELECT COUNT(*) FROM information_schema.STATISTICS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tbl}' AND INDEX_NAME = '{$iname}'"
                        )->fetchColumn();
                        if ((int)$has === 0) {
                            $pdo->exec("ALTER TABLE {$tbl} ADD INDEX {$iname} {$idef}");
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[Gblog migration] index ' . $iname . ' creation failed: ' . $e->getMessage());
                }
            }
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            && function_exists('gblog_apply_mysql_constraints')) {
            gblog_apply_mysql_constraints($pdo);
        }

        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $swCols = [
            'software_type'     => $isSqlite ? 'TEXT' : 'VARCHAR(100) NULL',
            'software_platform' => $isSqlite ? 'TEXT' : 'VARCHAR(100) NULL',
            'software_version' => $isSqlite ? 'TEXT'        : 'VARCHAR(60)  NULL',
            'official_url'     => $isSqlite ? 'TEXT'        : 'VARCHAR(1000) NULL',
            'language'         => $isSqlite ? 'TEXT'        : 'VARCHAR(50)  NULL',
            'rating'           => $isSqlite ? 'REAL NOT NULL DEFAULT 0.0' : 'DECIMAL(2,1) NOT NULL DEFAULT 0.0',
        ];
        foreach ($swCols as $swCol => $swDef) {
            if (!gblog_has_column($pdo, 'posts', $swCol)) {
                try {
                    $pdo->exec("ALTER TABLE posts ADD COLUMN `{$swCol}` {$swDef}");
                } catch (Throwable $e) {
                }
            }
        }

        foreach ([['posts', 'is_featured'], ['sites', 'theme']] as [$table, $column]) {
            if (!gblog_has_column($pdo, $table, $column)) continue;
            try {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
            } catch (Throwable $e) {
                error_log('[Gblog migration] unused column ' . $table . '.' . $column . ' removal failed: ' . $e->getMessage());
            }
        }

        gblog_create_table($pdo,
            'CREATE TABLE IF NOT EXISTS link_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY uq_link_category_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS link_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        if ($isSqlite) {
            $pdo->exec("INSERT OR IGNORE INTO link_categories (id, name, sort_order) VALUES (1, '未分类', 0)");
        } else {
            $pdo->exec("INSERT IGNORE INTO link_categories (id, name, sort_order) VALUES (1, '未分类', 0)");
        }
        if (!gblog_has_column($pdo, 'links', 'category_id')) {
            $linkCatCol = $isSqlite ? 'INTEGER NOT NULL DEFAULT 1' : 'INT UNSIGNED NOT NULL DEFAULT 1';
            $pdo->exec("ALTER TABLE links ADD COLUMN category_id {$linkCatCol}");
        }
        try {
            if ($isSqlite) {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_category ON links (category_id)');
            } else {
                $idx = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'links' AND INDEX_NAME = 'idx_link_category'")->fetchColumn();
                if ($idx === 0) $pdo->exec('ALTER TABLE links ADD INDEX idx_link_category (category_id)');
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] link category index creation failed: ' . $e->getMessage());
        }

// 发号表在内容之后才建的库，空表会让新建内容从 ID 1 开始撞已有内容，这里补一个高位号
        try {
            $seqMax = (int)$pdo->query('SELECT COALESCE(MAX(seq),0) FROM content_ids')->fetchColumn();
            if ($seqMax === 0) {
                $idMax = max(
                    (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM posts')->fetchColumn(),
                    (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM songs')->fetchColumn()
                );
                if ($idMax > 0) $pdo->exec('INSERT INTO content_ids (seq) VALUES (' . $idMax . ')');
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] content id sequence seed failed: ' . $e->getMessage());
        }

// v12「只保留栏目 slug」：删 posts/categories/tags 的 slug 列与对应唯一键。
// 只有栏目（sites）保留 slug —— 它是内容页 URL 前缀与版式/自定义 CSS 的配置键；
// 文章/分类/标签一律按全局 id 定位。改列前必须先删唯一键/索引，否则 DROP COLUMN 会失败。
        try {
            if ($isSqlite) {
                $pdo->exec('DROP INDEX IF EXISTS uq_post_slug');
                $pdo->exec('DROP INDEX IF EXISTS uq_cat_slug');
                $pdo->exec('DROP INDEX IF EXISTS uq_tag_slug');
            } else {
                foreach (['posts' => 'uq_post_slug', 'categories' => 'uq_cat_slug', 'tags' => 'uq_tag_slug'] as $t => $idx) {
                    $n = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $t . "' AND INDEX_NAME = '" . $idx . "'")->fetchColumn();
                    if ($n > 0) $pdo->exec("ALTER TABLE {$t} DROP INDEX {$idx}");
                }
            }
            foreach (['posts', 'categories', 'tags'] as $t) {
                if (gblog_has_column($pdo, $t, 'slug')) {
                    $pdo->exec("ALTER TABLE {$t} DROP COLUMN slug");
                }
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] slug column drop failed: ' . $e->getMessage());
        }
// 主题目录改名 aggregate → default（v11）：设置里存的是目录名，不改会让主题链落空。
// 只在新目录存在、旧目录已不在时执行，用户自己建的同名主题不受影响。
        try {
            $themeRoot = defined('THEMES_DIR') ? THEMES_DIR : (defined('GBLOG_ROOT') ? GBLOG_ROOT . '/usr/themes' : '');
            if ($themeRoot !== '' && is_dir($themeRoot . '/default') && !is_dir($themeRoot . '/aggregate')) {
                $pdo->exec("UPDATE settings SET svalue = 'default' WHERE skey IN ('current_theme', 'home_theme') AND svalue = 'aggregate'");
            }
        } catch (Throwable $e) {
            error_log('[Gblog migration] theme rename failed: ' . $e->getMessage());
        }

        gblog_set_schema_version($pdo, GBLOG_SCHEMA_VERSION);
    } catch (Throwable $e) {
    }
}
