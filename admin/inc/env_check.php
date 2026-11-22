<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit('Forbidden'); }

function gblog_env_row(string $label, $value, string $state = 'info', string $note = ''): array
{
    return ['label' => $label, 'value' => (string)$value, 'state' => $state, 'note' => $note];
}

function gblog_env_ini(string $key): string
{
    $v = @ini_get($key);
    return ($v === false || $v === '') ? '(未设置)' : (string)$v;
}

function gblog_core_fingerprint(): array
{
    $root  = dirname(__DIR__, 2);
    $skip  = ['config.php', 'install.php'];
    $items = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if (in_array($rel, $skip, true)) continue;
            if (strpos($rel, '_') === 0) continue;
            foreach (['.git/', 'usr/uploads/', 'usr/plugins/', 'gblog_data/', 'gblog_backups/', 'docs/'] as $p) {
                if (strpos($rel, $p) === 0) { continue 2; }
            }
            $items[$rel] = substr(hash('sha256', (string)@file_get_contents($f->getPathname())), 0, 16);
        }
    } catch (Throwable $e) {
    }
    ksort($items);
    $buf = '';
    foreach ($items as $rel => $h) { $buf .= $rel . ':' . $h . "\n"; }
    return ['files' => count($items), 'hash' => substr(hash('sha256', $buf), 0, 12)];
}

function gblog_env_probe(): array
{
    $root = dirname(__DIR__, 2);
    $fp   = gblog_core_fingerprint();
    $ver  = defined('GBLOG_CORE_VERSION') ? (string)GBLOG_CORE_VERSION : '未知';
    $g    = [];

    $schema = '';
    try { $schema = (string)get_setting('schema_version', ''); } catch (Throwable $e) {}
    $schemaExpected = defined('GBLOG_SCHEMA_VERSION') ? (string)GBLOG_SCHEMA_VERSION : '';
    $secret      = (defined('GBLOG_SECRET') && (string)GBLOG_SECRET !== '');
    $installLeft = is_file($root . '/install.php');
    $lockOk      = is_file($root . '/install.lock');
    $g['程序'] = [
        gblog_env_row('程序版本', $ver, 'info', '覆盖升级后应与发布包一致'),
        gblog_env_row('核心文件指纹', $fp['hash'] . '（' . $fp['files'] . ' 个 PHP 文件）', 'info', '本地包与线上对比此值即可判断是否同一版'),
        gblog_env_row('数据库结构版本', 'schema v' . ($schema !== '' ? $schema : '?'),
            ($schemaExpected !== '' && $schema === $schemaExpected) ? 'ok' : 'warn',
            $schemaExpected !== '' ? ('当前程序期望 v' . $schemaExpected . '，老库由 migrate 幂等补齐') : '无法读取程序期望版本'),
        gblog_env_row('站点密钥 GBLOG_SECRET', $secret ? '已配置（值不显示）' : '未配置', $secret ? 'ok' : 'warn', '备份签名密钥，请单独备份 config.php'),
        gblog_env_row('install.php', $installLeft ? '仍存在' : '已删除', $installLeft ? 'warn' : 'ok', '安装完成后建议删除'),
        gblog_env_row('install.lock', $lockOk ? '存在' : '缺失', $lockOk ? 'ok' : 'bad', '防重装锁，覆盖升级时切勿删除'),
    ];

    $disp      = gblog_env_ini('display_errors');
    $dispLower = strtolower($disp);
    $dispBad   = ($dispLower === '1' || $dispLower === 'on');
    $opcache   = function_exists('opcache_get_configuration');
    $validate  = '未启用';
    $validBad  = false;
    if ($opcache) {
        $cfg = @opcache_get_configuration();
        $v   = $cfg['directives']['opcache.validate_timestamps'] ?? null;
        if ($v === null)      { $validate = '未知'; }
        elseif ($v)           { $validate = '1（正常）'; }
        else                  { $validate = '0（覆盖后仍会跑旧代码）'; $validBad = true; }
    }
    $g['PHP 运行时'] = [
        gblog_env_row('PHP 版本', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'bad', '最低 8.0（代码使用 str_starts_with 等 PHP 8 语法，7.x 会 Fatal）；推荐 8.1+，8.2 已做回归'),
        gblog_env_row('运行方式 SAPI', PHP_SAPI, 'info', '虚拟主机多为 FPM / CGI'),
        gblog_env_row('时区 / 当前时间', date_default_timezone_get() . ' / ' . date('Y-m-d H:i:s'), 'info', '应为 Asia/Shanghai'),
        gblog_env_row('display_errors', $disp, $dispBad ? 'warn' : 'ok', $dispBad ? '生产环境建议在面板关闭' : '生产环境已关闭'),
        gblog_env_row('output_buffering', gblog_env_ini('output_buffering'), 'info', '后台在 auth.php 自行开启缓冲，不依赖此值'),
        gblog_env_row('upload_max_filesize', gblog_env_ini('upload_max_filesize'), 'info', '程序内置上传上限 20MB'),
        gblog_env_row('post_max_size', gblog_env_ini('post_max_size'), 'info', ''),
        gblog_env_row('memory_limit', gblog_env_ini('memory_limit'), 'info', ''),
        gblog_env_row('opcache', $opcache ? '已启用' : '未启用', 'info', ''),
        gblog_env_row('opcache.validate_timestamps', $validate, $validBad ? 'warn' : 'ok', $validBad ? '覆盖升级后需在面板重载 PHP 才生效' : ''),
    ];

    $drivers = [];
    foreach (['pdo_mysql', 'pdo_sqlite'] as $e) { if (extension_loaded($e)) $drivers[] = $e; }
    $ext = [gblog_env_row('数据库驱动', $drivers ? implode(' / ', $drivers) : '一个都没有', $drivers ? 'ok' : 'bad', '至少需要其中一个')];
    foreach (['pdo', 'mbstring', 'json', 'session', 'hash'] as $e) {
        $ok = extension_loaded($e);
        $ext[] = gblog_env_row($e, $ok ? '已加载' : '缺失', $ok ? 'ok' : 'bad', $ok ? '' : '缺此项可能导致白屏');
    }
    foreach (['fileinfo', 'zip', 'openssl'] as $e) {
        $ok = extension_loaded($e);
        $need = ($e === 'zip');
        $ext[] = gblog_env_row($e, $ok ? '已加载' : '未加载', $ok ? 'ok' : ($need ? 'warn' : 'info'),
            $ok ? '' : ($need ? '后台「完整备份」与应用包安装需要；未开启时只能导出 SQL' : '建议开启；当前版本不强制'));
    }
    $g['扩展'] = $ext;

    $db = [];
    try {
        $pdo    = db();
        $driver = gblog_db_driver();
        $db[]   = gblog_env_row('当前驱动', $driver, 'ok', '备份不能跨驱动恢复');
        if ($driver === 'mysql') {
            $server = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            $mode   = '';
            try { $mode = (string)$pdo->query('SELECT @@sql_mode')->fetchColumn(); } catch (Throwable $e) {}
            $fullGroup = (strpos($mode, 'ONLY_FULL_GROUP_BY') !== false);
            $db[] = gblog_env_row('MySQL 版本', $server, 'info', 'MySQL 8 请关注默认认证插件与排序规则');
            $db[] = gblog_env_row('sql_mode', $mode === '' ? '(读取失败)' : $mode, $fullGroup ? 'warn' : 'ok', $fullGroup ? 'ONLY_FULL_GROUP_BY 已开启，聚合查询需注意' : '');
            $db[] = gblog_env_row('数据库名', defined('DB_NAME') ? (string)DB_NAME : '(未知)', 'info', '口令不会显示或导出');
        } else {
            $server = (string)$pdo->query('select sqlite_version()')->fetchColumn();
            $path   = defined('DB_PATH') ? (string)DB_PATH : '';
            $rp     = @realpath(dirname($path));
            $rr     = @realpath($root);
            $outside = ($rp && $rr && strpos($rp, $rr) !== 0);
            $db[] = gblog_env_row('SQLite 版本', $server, 'info', '');
            $db[] = gblog_env_row('数据文件', $path, 'info', '完整路径仅供本机核对，导出诊断会保留');
            $db[] = gblog_env_row('数据文件在 Web 根之外', $outside ? '是' : '否 / 无法判断', $outside ? 'ok' : 'warn', '单文件库一旦可被 URL 直接访问，整站数据会被下载');
        }
        $tables = gblog_db_tables();
        $db[] = gblog_env_row('数据表数量', count($tables), 'info', '新表由 db_dump_sql 自动纳入备份');
    } catch (Throwable $e) {
        $db[] = gblog_env_row('数据库连接', '失败：' . $e->getMessage(), 'bad', '请检查 config.php 与数据库服务');
    }
    $g['数据库'] = $db;

    $verify = function_exists('gblog_verify_install') ? gblog_verify_install() : null;
    if ($verify && empty($verify['error'])) {
        $vrows = [];
        $vrows[] = gblog_env_row('数据表完整性',
            count($verify['tables_present']) . '/' . count($verify['tables_expected']) . ' 张'
            . (empty($verify['tables_missing']) ? '' : '（缺：' . implode(', ', $verify['tables_missing']) . '）'),
            empty($verify['tables_missing']) ? 'ok' : 'bad',
            '缺少数据表会导致对应功能报错，重新访问站点可自动补建');
        $vrows[] = gblog_env_row('schema_version',
            ($verify['schema_version'] === null ? '缺失' : $verify['schema_version']) . ' / 期望 ' . $verify['schema_expected'],
            ($verify['schema_version'] === $verify['schema_expected']) ? 'ok' : 'bad',
            '版本不符说明迁移未执行完，访问一次前台即可触发');
        $vrows[] = gblog_env_row('文章栏目',
            $verify['article_ok'] ? ('正常（共 ' . (int)$verify['sites_total'] . ' 个栏目）') : ('缺少启用的文章栏目（count=' . (int)$verify['article_count'] . '）'),
            $verify['article_ok'] ? 'ok' : 'bad',
            '首页与文章列表需要至少一个启用的文章类栏目');
        if ($verify['fk_count'] !== null) {
            $vrows[] = gblog_env_row('外键约束', $verify['fk_count'] . ' 条', 'info',
                '无 REFERENCES 权限时为 0，级联删除不自动生效，不影响使用');
        }
        if (!empty($verify['tables_extra'])) {
            $vrows[] = gblog_env_row('非 Gblog 表', implode(', ', $verify['tables_extra']), 'warn',
                '同库其他程序遗留，通常可忽略');
        }
        $g['数据库完整性'] = $vrows;
    }

    $backupDir = function_exists('gblog_backup_dir') ? gblog_backup_dir() : '';
    $ob        = gblog_env_ini('open_basedir');
    $g['目录与权限'] = [
        gblog_env_row('站点根目录', $root, 'info', ''),
        gblog_env_row('站点根可写', @is_writable($root) ? '是' : '否', @is_writable($root) ? 'ok' : 'info', '仅安装期需要写入'),
        gblog_env_row('usr/uploads 可写', @is_writable($root . '/usr/uploads') ? '是' : '否', @is_writable($root . '/usr/uploads') ? 'ok' : 'bad', '附件上传需要'),
        gblog_env_row('备份目录', $backupDir !== '' ? $backupDir : '无处可写', $backupDir !== '' ? 'ok' : 'bad', '站点内 usr/data/backups（随站迁移、禁止 HTTP 访问）；不可写时回退：外置实例目录 → 系统临时目录'),
        gblog_env_row('open_basedir', $ob, $ob === '(未设置)' ? 'ok' : 'info', '受限时程序会自动回退备份目录，不要对受限路径调用文件函数'),
    ];

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
          || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $hsts = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Strict-Transport-Security') === 0) {
            $parts = explode(':', $h, 2);
            $hsts  = trim($parts[1] ?? '');
            break;
        }
    }
    $g['Web 服务'] = [
        gblog_env_row('Server', (string)($_SERVER['SERVER_SOFTWARE'] ?? '(未知)'), 'info', ''),
        gblog_env_row('当前访问协议', $https ? 'HTTPS' : 'HTTP', $https ? 'ok' : 'info', '后台口令与会话 Cookie 建议走 HTTPS'),
        gblog_env_row('HSTS 响应头（PHP 层）', $hsts !== '' ? $hsts : 'PHP 未下发', 'info', '主机 / 网关层另加的头不在此显示，请用浏览器 F12 查看'),
        gblog_env_row('Host', (string)($_SERVER['HTTP_HOST'] ?? ''), 'info', ''),
    ];

    return $g;
}

function gblog_env_diagnostic(): string
{
    $out  = "Gblog 环境诊断\n";
    $out .= "生成时间: " . date('Y-m-d H:i:s') . "\n";
    $out .= "脱敏说明: 不含数据库口令、站点密钥、Cookie 与用户口令摘要，可安全外发。\n\n";
    foreach (gblog_env_probe() as $group => $rows) {
        $out .= '[' . $group . "]\n";
        foreach ($rows as $r) {
            $out .= '  ' . $r['label'] . ': ' . $r['value'];
            if ($r['note'] !== '') { $out .= '   // ' . $r['note']; }
            $out .= "\n";
        }
        $out .= "\n";
    }
    $out .= "[最近 20 条安全审计]\n";
    try {
        $st = db()->query('SELECT action, detail, created_at FROM security_logs ORDER BY id DESC LIMIT 20');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out .= '  ' . (string)$r['created_at'] . '  ' . (string)$r['action']
                  . '  ' . mb_substr((string)$r['detail'], 0, 120) . "\n";
        }
        $st->closeCursor();
    } catch (Throwable $e) {
        $out .= '  (读取失败: ' . $e->getMessage() . ")\n";
    }
    return $out;
}
