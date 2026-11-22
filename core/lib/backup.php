<?php
/* 备份与运维 —— 路径守卫、数据库导出/恢复/清理。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function gblog_path_allowed(string $path): bool
{
    $limit = (string)ini_get('open_basedir');
    if ($limit === '') return true;
    $path = rtrim(str_replace('\\', '/', $path), '/');
    foreach (explode(PATH_SEPARATOR, $limit) as $base) {
        $base = rtrim(str_replace('\\', '/', trim($base)), '/');
        if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) return true;
    }
    return false;
}

function gblog_backup_guard(string $dir): void
{
    $guards = [
        '.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
        'web.config' => '<?xml version="1.0" encoding="utf-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>',
        'index.php'  => "<?php http_response_code(403); exit('Forbidden');\n",
    ];
    foreach ($guards as $name => $body) {
        $p = $dir . '/' . $name;
        if (!is_file($p)) @file_put_contents($p, $body);
    }
}

// 备份目录的实例标识：取站点根目录名（安全字符化）。仅用于「站点根内不可写」时的外置回退目录。
function gblog_backup_site_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $root = str_replace('\\', '/', dirname(__DIR__, 2));
    $base = strtolower((string)basename($root));
    $base = (string)preg_replace('/[^a-z0-9_-]/', '', $base);
    return $key = ($base !== '' ? $base : 'site');
}

// 把旧位置里「确属本站」的备份收进当前备份目录（用本站 HMAC 验签判断归属）。
// 旧位置有两个：早期平铺的 gblog_backups/（多站共用）与过渡期的 gblog_backups/{站点名}/。
// 验签不通过（别站导出）的一律留在原处不动，避免把别人的数据搬进本站。
function gblog_backup_adopt(string $target): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if ($target === '' || !is_dir($target)) return;
    if (glob($target . '/*.sql') || glob($target . '/*.zip')) return;

    $root    = str_replace('\\', '/', dirname(__DIR__, 2));
    $parent  = dirname($root);
    $sources = [
        $parent . '/gblog_backups/' . gblog_backup_site_key(),
        $parent . '/gblog_backups',
        $root . '/gblog_backups',
    ];
    $seen = [];
    foreach ($sources as $src) {
        $src = rtrim(str_replace('\\', '/', $src), '/');
        // 虚拟主机 open_basedir 常禁止访问站点根目录之外（如 ../gblog_backups），
        // 直接 is_dir()/glob() 会抛 warning。与 gblog_backup_dir() 口径一致先过滤。
        if (!gblog_path_allowed($src)) continue;
        if (!is_dir($src) || isset($seen[$src]) || $src === $target) continue;
        $seen[$src] = true;
        $files = array_merge(glob($src . '/*.sql') ?: [], glob($src . '/*.zip') ?: []);
        foreach ($files as $f) {
            if (!is_file($f)) continue;
            try {
                if (!gblog_backup_is_mine($f)) continue;
            } catch (Throwable $e) {
                continue; // 密钥缺失等异常不能影响备份页打开
            }
            @rename($f, $target . '/' . basename($f));
        }
    }
}

// 备份文件是否本站导出：SQL 直接验签；zip 取包内 SQL 验签。
function gblog_backup_is_mine(string $file): bool
{
    $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'sql') {
        $sql = @file_get_contents($file);
        return is_string($sql) && $sql !== '' && gblog_backup_verify($sql);
    }
    if ($ext !== 'zip' || !class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) return false;
    $mine = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') continue;
        $sql = $zip->getFromIndex($i);
        if (is_string($sql) && $sql !== '' && gblog_backup_verify($sql)) { $mine = true; break; }
    }
    $zip->close();
    return $mine;
}

function gblog_backup_dir(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $rootRaw = dirname(__DIR__, 2);
    $root    = str_replace('\\', '/', $rootRaw);
    // 首选站点内 usr/data/backups：备份跟着站点目录走（搬家/整目录复制自动带走），
    // 且天然按安装实例隔离；该目录已被 nginx `location ^~ /usr/data/` 与目录内 .htaccess 整目录拒绝。
    $primary = $root . '/usr/data/backups';
    $candidates = [
        $primary,
        dirname($root) . '/gblog_backups/' . gblog_backup_site_key(),
        dirname($root) . '/gblog_backups',
        $root . '/gblog_backups',
        rtrim(sys_get_temp_dir(), '/\\') . '/gblog_backups_' . substr(md5($rootRaw), 0, 12),
    ];
    foreach ($candidates as $dir) {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || !gblog_path_allowed($dir)) continue;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) continue;
        gblog_backup_guard($dir);
        if ($dir === $primary) gblog_backup_adopt($primary);
        return $cached = $dir;
    }
    return $cached = '';
}

function db_dump_sql(): string
{
    $pdo    = db();
    $driver = gblog_db_driver();
    $tables = gblog_db_tables();
    if (!$tables) {
        return '';
    }
    $sql  = "-- =============================================\n";
    $sql .= "-- Gblog 数据备份\n";
    $sql .= "-- 驱动：" . $driver . "（备份只能恢复到同类型数据库）\n";
    $sql .= "-- 时间：" . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- =============================================\n";
    if ($driver === 'mysql') $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $table = (string)$table;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
        $create = gblog_db_create($table);
        if ($create === '') continue;
// MySQL 8 的 utf8mb4 默认排序规则是 utf8mb4_0900_ai_ci，MySQL 5.5/5.7 不认识它
        if ($driver === 'mysql') {
            $create = str_ireplace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $create);
        }
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= rtrim($create, "; \n\r\t") . ";\n\n";

        $cols = gblog_db_columns($table);
        $qs   = $pdo->query("SELECT * FROM `$table`");
        $rows = $qs->fetchAll(PDO::FETCH_NUM);
        $qs->closeCursor();
        if ($cols && $rows) {
            $colList = '`' . implode('`,`', $cols) . '`';
            $keyPos  = array_search('skey', $cols, true);
            foreach ($rows as $row) {
// app_secret 绝不导出（否则可用备份伪造签名）
                if ($table === 'settings' && $keyPos !== false
                    && (string)($row[$keyPos] ?? '') === 'app_secret') continue;
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $sql .= "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
    }

    if ($driver === 'mysql') $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return gblog_backup_sign($sql);
}

function split_sql_statements(string $sql): array
{
    $sql = trim($sql);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^#.*$/m', '', $sql);
    $out   = [];
    $buf   = '';
    $len   = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buf .= $ch;
        if ($quote !== null) {
            if ($ch === '\\' && $i + 1 < $len) { $i++; $buf .= $sql[$i]; continue; }
            if ($ch === $quote) { $quote = null; }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; continue; }
        if ($ch === ';') {
            $stmt = trim($buf, ";\n\r\t ");
            if ($stmt !== '') { $out[] = $stmt; }
            $buf = '';
        }
    }
    $stmt = trim($buf, ";\n\r\t ");
    if ($stmt !== '') { $out[] = $stmt; }
    return $out;
}

// 跨站 / 无签名备份必须带一次性恢复码（生成时需管理员密码）
function db_restore_sql(string $sql, string $restoreCode = ''): bool
{
    $GLOBALS['gblog_restore_error'] = '';
    $GLOBALS['gblog_restore_skipped'] = 0;

// 只判空不改写 $sql：签名覆盖含末尾换行的完整文本，trim 后必然验签失败
    if (trim($sql) === '') {
        $GLOBALS['gblog_restore_error'] = '备份内容为空。';
        return false;
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $loose = false;
    if (!gblog_backup_verify($sql)) {
        if ($restoreCode === '') {
            gblog_audit('backup.restore.need_code', $adminId, '');
            $GLOBALS['gblog_restore_error'] = '该备份不是本站导出的（缺少有效签名）。如需恢复其他站点或处理过的备份，请先在「安全设置」用管理员密码生成一次性恢复码，并在恢复时填写。';
            return false;
        }
        if (!gblog_restore_code_verify($restoreCode)) {
            gblog_audit('backup.restore.bad_code', $adminId, '');
            $GLOBALS['gblog_restore_error'] = '恢复码不正确、已用过、已过期，或尝试过于频繁。请核对后重试，必要时重新生成一枚恢复码。';
            return false;
        }
        gblog_restore_code_consume();
        gblog_audit('backup.restore.code_used', $adminId, 'foreign_backup');
        $loose = true;
    }
// 驱动不一致提前拒绝：半途失败会把库留在半恢复状态
    $dumpDriver = 'mysql';
    if (preg_match('/^--\s*驱动：\s*([a-z]+)/mi', $sql, $dm)) $dumpDriver = strtolower($dm[1]);
    if ($dumpDriver !== gblog_db_driver()) {
        gblog_audit('backup.restore.driver_mismatch', $adminId, $dumpDriver . ' -> ' . gblog_db_driver());
        $GLOBALS['gblog_restore_error'] = '备份驱动为 ' . $dumpDriver
            . '，当前数据库为 ' . gblog_db_driver() . '，两者不能互相恢复。';
        return false;
    }

    $stmts = split_sql_statements($sql);
    if (!$stmts) {
        $GLOBALS['gblog_restore_error'] = '备份内容无法解析为有效的 SQL 语句。';
        return false;
    }
    foreach ($stmts as $i => $stmt) {
        if (!gblog_sql_allowed($stmt)) {
            gblog_audit('backup.restore.blocked', $adminId, substr($stmt, 0, 120));
            $GLOBALS['gblog_restore_error'] = '第 ' . ($i + 1) . ' 条 SQL 不在允许列表内，已拒绝恢复。';
            return false;
        }
    }

    if ($loose) {
        $kept = [];
        foreach ($stmts as $stmt) {
            if (gblog_restore_protected_stmt($stmt)) {
                $GLOBALS['gblog_restore_skipped']++;
                continue;
            }
            $kept[] = $stmt;
        }
        $stmts = $kept;
        if (!$stmts) {
            $GLOBALS['gblog_restore_error'] = '备份中没有可恢复的内容语句（账号、令牌与审计记录不允许跨站导入）。';
            return false;
        }
    }

// 旧站 app_secret 必须保住，否则之后备份签名会变
    $keepSettings = [];
    if ($loose) {
        try {
            $secret = get_setting('app_secret', '');
// 旧站回退密钥：空值不能写回（会写一条空 app_secret 让 gblog_secret() 失效）
            if ($secret !== '') $keepSettings['app_secret'] = $secret;
// 维护模式是本机运行状态，必须复位
            $keepSettings['maintenance_mode'] = get_setting('maintenance_mode', '0');
        } catch (Throwable $e) {
        }
    }

    try {
        $dir = gblog_backup_dir();
        if ($dir !== '') {
            @file_put_contents($dir . '/gblog_backup_before_restore_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.sql', db_dump_sql());
        }
    } catch (Throwable $e) {
    }

    foreach ($GLOBALS as $__gk => $__gv) {
        if ($__gv instanceof PDOStatement) {
            try { $__gv->closeCursor(); } catch (Throwable $__e) {}
        }
    }
    unset($__gk, $__gv);
    $pdo = db(true);
    gblog_db_fk(false);
    try {
        foreach ($stmts as $stmt) {
            if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $stmt)) continue;
            $pdo->exec($stmt);
        }
    } catch (Throwable $e) {
        gblog_db_fk(true);
        gblog_audit('backup.restore.error', $adminId, substr($e->getMessage(), 0, 120));
        $GLOBALS['gblog_restore_error'] = '数据库执行失败：' . $e->getMessage();
        return false;
    }
    gblog_db_fk(true);
    if (gblog_db_driver() === 'sqlite' && function_exists('gblog_sqlite_ensure_triggers')) {
        gblog_sqlite_ensure_triggers($pdo);
    }
    if ($loose) {
        try {
            foreach ($keepSettings as $key => $val) save_setting($key, $val);
            save_setting('schema_version', (string)(defined('GBLOG_SCHEMA_VERSION') ? GBLOG_SCHEMA_VERSION : 4));
        } catch (Throwable $e) {
        }
    }
    gblog_audit('backup.restore.ok', $adminId,
        count($stmts) . ' statements' . ($loose ? ' / foreign, skipped ' . $GLOBALS['gblog_restore_skipped'] : ''));
    return true;
}

/**
 * 补齐 5 个程序内置栏目（与 install/schema*.sql 种子一致），幂等。
 * 清理全部后调用，保证栏目不会空；后台只能改可改字段、不能删内置栏目。
 */
function gblog_restore_builtin_sites(PDO $pdo): void
{
    static $seeds = [
        ['文章', 'post',  '文章发布',           'article',  '文章', 0],
        ['音乐', 'music', '惟将心绪，酿成歌',    'song',     '音乐', 2],
        ['软件', 'soft',  '软件下载',           'software', '软件', 1],
        ['图片', 'image', '图片集/卡片杂志',    'image',    '图片', 3],
        ['小说', 'novel', '小说作品与章节阅读', 'novel',    '小说', 3],
    ];
    $hasBuiltin = true;
    try { $pdo->query('SELECT is_builtin FROM sites LIMIT 1'); } catch (Throwable $e) { $hasBuiltin = false; }
    $exists = [];
    foreach ($pdo->query('SELECT slug FROM sites') as $r) $exists[(string)$r['slug']] = true;
    if ($hasBuiltin) {
        $ins = $pdo->prepare('INSERT INTO sites (name,slug,description,type,menu_name,enabled,sort_order,is_builtin) VALUES (?,?,?,?,?,1,?,1)');
        foreach ($seeds as $s) {
            if (isset($exists[$s[1]])) continue;
            $ins->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5]]);
        }
    } else {
        $ins = $pdo->prepare('INSERT INTO sites (name,slug,description,type,menu_name,enabled,sort_order) VALUES (?,?,?,?,?,1,?)');
        foreach ($seeds as $s) {
            if (isset($exists[$s[1]])) continue;
            $ins->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5]]);
        }
    }
}

function db_clean(bool $all): void
{
    $pdo = db();
    gblog_db_fk(false);

    foreach (['posts', 'post_tags', 'songs', 'comments', 'post_reactions', 'tags', 'links', 'coin_logs', 'download_logs'] as $t) {
        try { $pdo->exec("DELETE FROM `$t`"); } catch (Throwable $e) {}
    }

    $pdo->exec("DELETE FROM users WHERE role <> 'admin'");

    if ($all) {
        // 内置栏目（is_builtin=1）是站点骨架，不可清理，口径与后台删除的 is_builtin=0 一致。
        // 只清用户自建栏目；分类/广告可全清，随后补回内置栏目（含被误删的情况）。
        try { $pdo->exec("DELETE FROM sites WHERE is_builtin = 0"); } catch (Throwable $e) {}
        foreach (['categories', 'ads'] as $t) {
            try { $pdo->exec("DELETE FROM `$t`"); } catch (Throwable $e) {}
        }
        gblog_restore_builtin_sites($pdo);
    }

    gblog_db_fk(true);
}
