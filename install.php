<?php
session_start();
$root = __DIR__;
$userDataDir = $root . '/usr/data';
// 本次安装刚完成时允许显示第 4 步完成页，否则会被下面的防重装逻辑直接踢到首页
$step = (int)($_GET['step'] ?? 1);
$justInstalled = ($step === 4 && !empty($_SESSION['gblog_install_just_done']));
require_once __DIR__ . '/core/auth/security.php';
// 版本常量 GBLOG_SCHEMA_VERSION 只定义在 migrate.php：完成页的完整性校验要靠它判断
// 「期望版本」，不加载就只能回落到 4，会出现「schema_version=10（应为 4）」的假告警。
require_once __DIR__ . '/core/data/migrate.php';

// 安装向导 CSRF 与 core/data/db.php 同算法、同会话键。
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_check')) {
    function csrf_check(): bool
    {
        $token  = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';
        if (!is_string($token) || $token === '' || !is_string($stored) || $stored === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }
}

// 一键删除安装文件：仅已安装完成（install.lock 存在）且通过 CSRF 校验才允许
if (($root ?? null) !== null
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['action'] ?? '') === 'del_install') {
    if (!function_exists('csrf_check') || !csrf_check()) {
        http_response_code(403);
        exit('CSRF 校验失败');
    }
    if (!is_file(__DIR__ . '/install.lock')) {
        exit('尚未安装完成，无法删除安装文件。');
    }
    $delTarget = __DIR__ . '/install.php';
    if (is_file($delTarget) && @unlink($delTarget)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>安装完成</title>';
        echo '<style>body{font-family:-apple-system,"Microsoft YaHei",sans-serif;background:#f0f2f5;margin:0;padding:60px 16px;color:#2b2f36}.box{max-width:600px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:32px;text-align:center}.ok{background:#e8f8ef;color:#2e9e63;padding:10px 14px;border-radius:6px;display:inline-block}.btn{display:inline-block;background:#1d6fe0;color:#fff;border:none;padding:11px 26px;border-radius:6px;font-size:15px;text-decoration:none;margin:16px 6px 0}</style>';
        echo '</head><body><div class="box"><div class="ok">✅ install.php 已删除</div><p>站点已无法被恶意重装，安全性更高。</p><a class="btn" href="index.php">访问首页</a><a class="btn" href="admin/login.php" style="background:#2e9e63">登录后台</a></div></body></html>';
        exit;
    }
}

function gblog_sqlite_default_path(): string
{
    return str_replace('\\', '/', __DIR__) . '/usr/data/gblog.sqlite';
}

function gblog_sqlite_path_allowed(string $path, string $webRoot): bool
{
    $norm = function ($p) {
        return rtrim(strtolower(str_replace('\\', '/', (string)$p)), '/');
    };
    $root = $norm(realpath($webRoot) ?: $webRoot);
    $dir = $norm(realpath(dirname($path)) ?: dirname($path));
    if ($root === '' || $dir === '') return false;
    $protected = $norm($root . '/usr/data');
    if ($dir === $protected || strpos($dir . '/', $protected . '/') === 0) return true;
    return $dir !== $root && strpos($dir . '/', $root . '/') !== 0;
}

function gblog_install_pdo(): ?PDO
{
    try {
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3];
        if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
            if (!defined('DB_PATH') || !is_file(DB_PATH)) return null;
            return new PDO('sqlite:' . DB_PATH, null, null, $opts);
        }
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) return null;
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            defined('DB_PASS') ? DB_PASS : '',
            $opts
        );
    } catch (Throwable $e) {
        return null;
    }
}

function gblog_db_reachable(): bool
{
    try {
        $pdo = gblog_install_pdo();
        if (!$pdo) return false;
        $pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version' LIMIT 1")->fetchColumn();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$alreadyInstalled = false;
if (is_file($userDataDir . '/config.php')) {
    require_once $userDataDir . '/config.php';
    if (defined('DB_INSTALLED') && DB_INSTALLED === true && gblog_db_reachable()) {
        $alreadyInstalled = true;
    }
}
if ($alreadyInstalled && !$justInstalled) {
    header('Location: index.php');
    exit;
}

if (is_file($root . '/install.lock') && !$justInstalled) {
    $reinstallAllowed = false;
    if (!empty($_SESSION['admin_id']) && gblog_db_reachable()) {
        try {
            $chk = gblog_install_pdo();
            if ($chk) {
                $s = $chk->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
                $s->execute([(int)$_SESSION['admin_id']]);
                $r = $s->fetch(PDO::FETCH_ASSOC);
                if ($r && $r['role'] === 'admin' && $r['status'] === 'active') $reinstallAllowed = true;
            }
        } catch (Throwable $e) {
        }
    }
    if (!$reinstallAllowed) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>已安装</title></head>'
          . '<body style="font-family:system-ui,sans-serif;padding:40px;line-height:1.8;">'
          . '<h2>站点已安装</h2><p>检测到 <code>install.lock</code>，为防止被恶意重装，安装向导已锁定。</p>'
          . '<p>如需重新安装：请先以管理员身份登录后台，或手动删除站点根目录下的 <code>install.lock</code>。</p>'
          . '<a href="index.php">返回首页</a></body></html>');
    }
}

$error = '';
$formType = (string)($_POST['db_type'] ?? 'mysql');
if (!in_array($formType, ['mysql', 'sqlite'], true)) $formType = 'mysql';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    $siteName  = trim((string)($_POST['site_name'] ?? 'Gblog')) ?: 'Gblog';
    $adminUser = trim((string)($_POST['admin_user'] ?? 'admin'));
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    try {
        // 安装与重装都必须过 CSRF；install.lock 在场即视为重装，需管理员口令二次确认（防 CSRF 毁站）
        if (!csrf_check()) {
            throw new Exception('CSRF 校验失败，请返回上一步刷新后重试。');
        }
        if (is_file($root . '/install.lock')) {
            $confirmHash = null;
            $pdo0 = gblog_install_pdo();
            if ($pdo0 && !empty($_SESSION['admin_id'])) {
                $s = $pdo0->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $s->execute([(int)$_SESSION['admin_id']]);
                $confirmHash = (string)$s->fetchColumn();
            }
            if ($confirmHash === '' || !password_verify((string)($_POST['admin_confirm_pass'] ?? ''), $confirmHash)) {
                throw new Exception('重装被拒绝：检测到 install.lock，重装会清空全部数据，需已登录管理员并填写正确口令二次确认。');
            }
        }
        if (!gblog_password_strong($adminPass)) {
            throw new Exception('管理员密码至少 8 位，且必须同时包含大写字母和小写字母。');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $adminUser)) {
            throw new Exception('管理员用户名需为 3-20 位字母数字下划线。');
        }

        if ($formType === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) throw new Exception('未开启 pdo_sqlite 扩展。');
            $dbPath = trim((string)($_POST['db_path'] ?? '')) ?: gblog_sqlite_default_path();
            $dbPath = str_replace('\\', '/', $dbPath);
            if (!preg_match('~^(?:[A-Za-z]:/|/)~', $dbPath)) {
                throw new Exception('SQLite 数据库文件必须填写绝对路径，例如 ' . gblog_sqlite_default_path());
            }
            if (!preg_match('~\.(sqlite|sqlite3|db)$~i', $dbPath)) {
                throw new Exception('数据库文件建议使用 .sqlite / .db 后缀。');
            }
            $dataDir = dirname($dbPath);
            if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
                throw new Exception('数据目录不可写，无法创建：' . $dataDir);
            }
// 安全红线：只允许站外目录或受保护的 usr/data（server 级已 403）
            if (!gblog_sqlite_path_allowed($dbPath, $root)) {
                throw new Exception('数据库文件只能放在站点目录之外，或放在受保护的 usr/data 目录内。请改用 ' . gblog_sqlite_default_path());
            }
            if (!is_writable($dataDir)) throw new Exception('数据目录不可写：' . $dataDir);

// 仅当不存在时创建，绝不覆盖 usr/data 分发自带的防护文件
            if (!is_file($dataDir . '/.htaccess')) {
                @file_put_contents($dataDir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            }
            if (!is_file($dataDir . '/web.config')) {
                @file_put_contents($dataDir . '/web.config', '<?xml version="1.0" encoding="utf-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
            }
            if (!is_file($dataDir . '/index.html')) {
                @file_put_contents($dataDir . '/index.html', '');
            }

            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $known = function_exists('gblog_known_tables') ? gblog_known_tables() : [];
            if ($known) {
                $pdo->exec('PRAGMA foreign_keys = OFF');
                $existing = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                foreach (array_intersect($known, $existing) as $t) {
                    $pdo->exec("DROP TABLE IF EXISTS `$t`");
                }
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
            $schema = preg_replace('/^--.*$/m', '', (string)file_get_contents($root . '/install/schema.sqlite.sql'));
            $pdo->exec($schema);
            @chmod($dbPath, 0600);

            $cfgDb  = "define('DB_TYPE', 'sqlite');\n";
            $cfgDb .= "define('DB_PATH', " . var_export($dbPath, true) . ");\n";
            $driverLabel = 'SQLite';
            $dbLocation  = $dbPath;
        } else {
            if (!extension_loaded('pdo_mysql')) throw new Exception('未开启 pdo_mysql 扩展。');
            $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
            $dbName = trim((string)($_POST['db_name'] ?? ''));
            $dbUser = trim((string)($_POST['db_user'] ?? ''));
            $dbPass = (string)($_POST['db_pass'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $dbName)) throw new Exception('数据库名不合法（仅允许字母/数字/下划线，最长 64 位）。');
            if ($dbUser === '') throw new Exception('请填写数据库用户。');

            $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            try {
                $pdo->exec("USE `{$dbName}`");
            } catch (PDOException $e) {
                // PDO::getCode() 返回的是 SQLSTATE（'42000'），MySQL 错误号在 errorInfo[1]。
                // 直接比较 '1049' 永远不成立，库不存在时会被误报成「无权限」，用户白折腾面板。
                $errno = (int)($e->errorInfo[1] ?? 0);
                if ($errno === 1049) {
                    try {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("USE `{$dbName}`");
                    } catch (PDOException $e2) {
                        throw new Exception("数据库「{$dbName}」不存在，且当前用户无创建权限（{$e2->getMessage()}）。虚拟主机请先在控制面板创建该数据库，并确认安装表单填写的库名与其完全一致（含大小写/前缀）。");
                    }
                } else {
                    throw new Exception("无法访问数据库「{$dbName}」（{$e->getMessage()}）。请到主机控制面板的 MySQL 管理里，把用户「{$dbUser}」添加到数据库「{$dbName}」并授予全部权限，然后重试。");
                }
            }
// 只删 Gblog 已知表，绝不动库内其他无关表（共享主机安全）
            $known = function_exists('gblog_known_tables') ? gblog_known_tables() : [];
            if ($known) {
                $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $drop = array_intersect($known, $existing);
                if ($drop) {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach ($drop as $t) {
                        $pdo->exec("DROP TABLE IF EXISTS `$t`");
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                }
            }
            $schema = (string)file_get_contents($root . '/install/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
                $clean = preg_replace('/^--.*$/m', '', $stmt);
                if (trim($clean) !== '') $pdo->exec($clean);
            }
            $fkWarnings = function_exists('gblog_apply_mysql_constraints') ? gblog_apply_mysql_constraints($pdo) : [];
            if ($fkWarnings) {
                $_SESSION['gblog_install_fk_warning'] = $fkWarnings;
            }

            $cfgDb  = "define('DB_TYPE', 'mysql');\n";
            $cfgDb .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $cfgDb .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $cfgDb .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $cfgDb .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $cfgDb .= "define('DB_CHARSET', 'utf8mb4');\n";
            $driverLabel = 'MySQL';
            $dbLocation  = $dbName . ' @ ' . $dbHost;
        }

        $verb = ($formType === 'sqlite') ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO';
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare($verb . " users (username, password_hash, nickname, role, status) VALUES (?,?,?, 'admin', 'active')");
        $stmt->execute([$adminUser, $hash, '管理员']);

        $pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = 'site_name'")->execute([$siteName]);
        foreach (['portal-top' => '1.0.0'] as $appId => $appVer) {
            $pdo->prepare($verb . " apps (app_id, type, enabled, version) VALUES (?, 'plugin', 1, ?)")->execute([$appId, $appVer]);
        }
// install/schema.*.sql 是首装基线（schema_version=4），后续结构由幂等迁移补齐。
// 装完当场跑一次，否则完成页的完整性校验会读到「21/22 表、version=4」而误报安装不完整，
// 用户还会在首次访问前拿到一个旧结构库。
        require_once __DIR__ . '/core/data/migrate.php';
        gblog_migrate($pdo);
// 站点密钥固化在 config.php：不进数据库，就不会随备份外泄
        $appSecret = bin2hex(random_bytes(32));

        $cfg  = "<?php\n";
        $cfg .= "define('DB_INSTALLED', true);\n";
        $cfg .= "define('GBLOG_ROOT', dirname(__DIR__, 2));\n";
        $cfg .= $cfgDb;
        $cfg .= "define('GBLOG_SECRET', " . var_export($appSecret, true) . ");\n";
        $cfg .= "define('SITE_NAME', " . var_export($siteName, true) . ");\n";
        $cfg .= "define('SITE_DESC', 'Gblog - 简洁优雅的个人博客');\n";
        $cfg .= "define('POSTS_PER_PAGE', 6);\n";
        $cfg .= "define('UPLOAD_DIR', GBLOG_ROOT . '/usr/uploads');\n";
        $cfg .= "define('UPLOAD_URL', '/usr/uploads');\n";
        $cfg .= "define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024);\n";
        $cfg .= "define('MAX_FILE_UPLOAD_SIZE', 100 * 1024 * 1024);\n";
        $cfg .= "define('ALLOWED_AUDIO_EXT', ['mp3','m4a','ogg','wav']);\n";
        $cfg .= "define('ALLOWED_IMAGE_EXT', ['jpg','jpeg','png','webp','gif']);\n";
        $cfg .= "define('ALLOWED_FILE_EXT', ['zip','rar','7z','tar','gz','tgz','apk','exe','msi','dmg','pdf','doc','docx','xls','xlsx','ppt','pptx']);\n";
        $cfg .= "define('THEMES_DIR', GBLOG_ROOT . '/usr/themes');\n";
        $cfg .= "define('ADMIN_SESSION_TTL', 7200);\n";
        $cfg .= "\ndate_default_timezone_set('Asia/Shanghai');\n";
        if (file_put_contents($userDataDir . '/config.php', $cfg) === false) {
            throw new Exception('config.php 写入失败，请检查 usr/data 目录权限。');
        }

        if (!is_dir($userDataDir) && !@mkdir($userDataDir, 0755, true) && !is_dir($userDataDir)) {
        }
        if (!is_file($userDataDir . '/.htaccess')) {
            @file_put_contents($userDataDir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!is_file($userDataDir . '/web.config')) {
            @file_put_contents($userDataDir . '/web.config', '<?xml version="1.0" encoding="utf-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        }
        @file_put_contents($userDataDir . '/index.html', '');

// 伪静态规则落盘：不存在则整份写入，主机已有 .htaccess 则追加（绝不覆盖，
// 覆盖会丢掉主机商的 AddHandler 等 PHP 处理器指令导致 503）。写入失败在完成页提示手工合并。
        $htaccessNotice = '';
        $rulesFile = $root . '/install/apache-htaccess.txt';
        $rules = is_file($rulesFile) ? (string)@file_get_contents($rulesFile) : '';
        if ($rules !== '') {
            $cur = is_file($root . '/.htaccess') ? (string)@file_get_contents($root . '/.htaccess') : '';
            // 标记必须行首锚定：说明文字里也引用了标记词，strpos 会误命中说明行
            $startPos = null; $endPos = null;
            if (preg_match('/^# GBLOG-REWRITE-BLOCK-START[ \t]*$/m', $cur, $m, PREG_OFFSET_CAPTURE)) {
                $startPos = $m[0][1];
            }
            if (preg_match('/^# GBLOG-REWRITE-BLOCK-END[ \t]*$/m', $cur, $m, PREG_OFFSET_CAPTURE)) {
                $endPos = $m[0][1];
            }
            $upToDate = strpos($cur, '# GBLOG-RW-REV: 2') !== false;
            if ($cur === '') {
                // 全新站点：整份写入
                if (@file_put_contents($root . '/.htaccess', $rules) === false) {
                    $htaccessNotice = '站点根目录 .htaccess 未能自动写入（目录不可写）。Apache 主机请手工把 <code>install/apache-htaccess.txt</code> 的内容复制为站点根目录的 <code>.htaccess</code>（若已有 .htaccess 则追加到末尾），否则启用伪静态后页面会 404。nginx 主机请按 nginx.htaccess 配置。';
                }
            } elseif ($startPos !== null && !$upToDate) {
                // 已有旧版 Gblog 规则块：整段升级替换（保留块外的主机商自有指令）。
                // 旧版无 END 标记，规则段一直到文件尾；新版有 END 标记，只替换块内。
                $endTag = '# GBLOG-REWRITE-BLOCK-END';
                // 注意 $endPos 初始为 null：必须判 !== null（null !== false 恒为真，会误走块间替换）
                $body   = $endPos !== null
                    ? substr($cur, 0, $startPos) . $rules . substr($cur, $endPos + strlen($endTag))
                    : substr($cur, 0, $startPos) . $rules;
                if (@file_put_contents($root . '/.htaccess', $body) !== false) {
                    $htaccessNotice = '已自动把站点根目录 .htaccess 的 Gblog 伪静态规则升级到最新版（支持自建栏目 slug 地址）。若你曾在规则块之后追加过自有规则，请核对是否被移动。';
                } else {
                    $htaccessNotice = '.htaccess 规则升级写入失败（目录不可写）。请手工用 <code>install/apache-htaccess.txt</code> 中「GBLOG-REWRITE-BLOCK-START」至「GBLOG-REWRITE-BLOCK-END」的规则段，替换站点根 .htaccess 里同名标记起的旧规则段，否则自建栏目页面会 404。';
                }
            } elseif ($startPos === null) {
                // 主机商已有 .htaccess（无 Gblog 规则块）：追加，绝不覆盖
                $body = rtrim($cur) . "\n\n" . $rules;
                if (@file_put_contents($root . '/.htaccess', $body) === false) {
                    $htaccessNotice = '站点根目录 .htaccess 未能自动写入（目录不可写）。Apache 主机请手工把 <code>install/apache-htaccess.txt</code> 的内容追加到站点根目录的 <code>.htaccess</code> 末尾，否则启用伪静态后页面会 404。nginx 主机请按 nginx.htaccess 配置。';
                }
            }
            // 已是新版（startPos 有且 REV:2 在）：跳过不动
        } else {
            $htaccessNotice = '安装包缺少 <code>install/apache-htaccess.txt</code>。Apache 主机启用伪静态前，请手工在站点根目录创建 .htaccess（规则见官方文档），否则伪静态页面会 404。';
        }

// 安装锁写不进去不能静默略过：完成页会明确提示手动补建
        $lockOk = @file_put_contents($root . '/install.lock', date('Y-m-d H:i:s')) !== false;
        $_SESSION['gblog_install_just_done']      = true;
        $_SESSION['gblog_install_lock_warning']   = !$lockOk;
        $_SESSION['gblog_install_htaccess_notice'] = $htaccessNotice;
        $_SESSION['gblog_install_driver']   = $driverLabel;
        $_SESSION['gblog_install_location'] = $dbLocation;
        header('Location: install.php?step=4');
        exit;
    } catch (Throwable $e) {
        $error = '安装失败：' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装 Gblog</title>
<style>
  body { font-family: -apple-system, "Microsoft YaHei", sans-serif; background:#f0f2f5; margin:0; padding:40px 16px; color:#2b2f36; }
  .box { max-width:600px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:32px; }
  h1 { margin:0 0 8px; font-size:24px; color:#1d6fe0; text-align:center; }
  .steps { display:flex; gap:6px; margin:18px 0; }
  .steps span { flex:1; text-align:center; padding:6px; border-radius:6px; background:#eef1f5; font-size:13px; color:#8a919c; }
  .steps span.on { background:#1d6fe0; color:#fff; }
  .field { margin:14px 0; }
  label { display:block; font-size:14px; margin-bottom:5px; font-weight:600; }
  input,select { width:100%; padding:9px 10px; border:1px solid #d8dde5; border-radius:6px; box-sizing:border-box; font-size:14px; }
  .row { display:flex; gap:12px; } .row .field { flex:1; }
  .btn { display:inline-block; background:#1d6fe0; color:#fff; border:none; padding:11px 26px; border-radius:6px; font-size:15px; cursor:pointer; text-decoration:none; }
  .alert { background:#fdecec; color:#c0392b; padding:10px 14px; border-radius:6px; margin:12px 0; font-size:14px; }
  .ok { background:#e8f8ef; color:#2e9e63; }
  .hint { font-size:12px; color:#8a919c; line-height:1.7; }
  .features { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:18px 0 6px; }
  .feat { background:#f6f8fc; border:1px solid #e8edf5; border-radius:8px; padding:12px 14px; }
  .feat h3 { margin:0 0 4px; font-size:14px; color:#1d6fe0; }
  .feat p { margin:0; font-size:12.5px; color:#5a626c; line-height:1.65; }
  @media (max-width:480px){ .features{ grid-template-columns:1fr; } }
  .check { font-size:14px; line-height:2; }
  .check .y { color:#2e9e63; } .check .n { color:#e4574d; }
  .warn { background:#fff7e6; border:1px solid #ffd591; color:#a05a00; padding:10px 12px; border-radius:6px; font-size:13px; line-height:1.7; margin:12px 0; }
</style>
</head>
<body>
<div class="box">
  <h1>Gblog 安装</h1>
  <div class="steps">
    <span class="<?= $step==1?'on':'' ?>">1 欢迎</span>
    <span class="<?= $step==2?'on':'' ?>">2 环境检测</span>
    <span class="<?= $step==3?'on':'' ?>">3 配置</span>
    <span class="<?= $step==4?'on':'' ?>">4 完成</span>
  </div>

  <?php if ($step == 1): ?>
    <p>欢迎使用 <b>Gblog</b> —— 简洁优雅的个人博客系统。</p>
    <p class="hint">MIT 开源协议，自由使用与修改。支持 <b>MySQL</b> 与 <b>SQLite</b> 两种数据库；安装前请确认已上传全部文件，且 <code>usr/uploads/</code> 目录可写。</p>
    <div class="features">
      <div class="feat"><h3>双数据库驱动</h3><p>MySQL 与 SQLite 任选，SQLite 零配置、单文件部署，个人站开箱即用。</p></div>
      <div class="feat"><h3>前台会员体系</h3><p>注册登录、金币、付费下载、评论、点赞、收藏与浏览历史，一站闭环。</p></div>
      <div class="feat"><h3>音乐模块</h3><p>独立歌曲分类与在线播放器，三级浏览（栏目 → 分类 → 单曲）。</p></div>
      <div class="feat"><h3>栏目 × 形态 × 版式</h3><p>栏目、首页形态、版式三个维度自由组合，一个站点可同时跑博客、软件站与音乐站；主题支持子主题继承，改配色不必动核心。</p></div>
      <div class="feat"><h3>文章与互动</h3><p>文章发布、评论回复、点赞收藏，内容沉淀与读者连接俱全。</p></div>
      <div class="feat"><h3>安全与备份</h3><p>防重装锁、加密备份与一键恢复、CSRF 防护，部署更安心。</p></div>
    </div>
    <a class="btn" href="install.php?step=2">开始检测 →</a>

  <?php elseif ($step == 2):
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $pdoOk = extension_loaded('pdo');
    $pdoMysql = extension_loaded('pdo_mysql');
    $pdoSqlite = extension_loaded('pdo_sqlite');
    $gd = extension_loaded('gd');
    $uploadDir = $root . '/usr/uploads'; if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); } $uploadWritable = is_dir($uploadDir) && is_writable($uploadDir);
    $defaultDataDir = dirname(gblog_sqlite_default_path());
    $dataDirSafe = gblog_sqlite_path_allowed(gblog_sqlite_default_path(), $root);
    $anyDriver = $pdoMysql || $pdoSqlite;

    $mbOk      = extension_loaded('mbstring');
    $jsonOk    = function_exists('json_encode');
    $sessionOk = extension_loaded('session');
    $cryptoOk  = function_exists('hash_hmac') && function_exists('random_bytes');
    $extOk     = $mbOk && $jsonOk && $sessionOk && $cryptoOk;

    $dataDirPath = $root . '/usr/data';
    if (!is_dir($dataDirPath)) { @mkdir($dataDirPath, 0755, true); }
    $dataWritable = is_dir($dataDirPath) && is_writable($dataDirPath);
    $zipOk = class_exists('ZipArchive');

    $openBasedir = trim((string)ini_get('open_basedir'));
    $sqliteDirAllowed = true;
    if ($openBasedir !== '') {
        $norm = function ($p) { return rtrim(strtolower(str_replace('\\', '/', (string)$p)), '/'); };
        $target = $norm($defaultDataDir);
        $sqliteDirAllowed = false;
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allow) {
            $allow = $norm(trim($allow));
            if ($allow !== '' && ($target === $allow || strpos($target . '/', $allow . '/') === 0)) {
                $sqliteDirAllowed = true;
                break;
            }
        }
    }
  ?>
    <div class="check">
      <div>PHP 版本 ≥ 8.0：<?= $phpOk ? "<span class='y'>✔ " . PHP_VERSION . "</span>" : "<span class='n'>✘ 版本过低（代码使用 PHP 8 语法，7.x 会直接 Fatal）</span>" ?></div>
      <div>PDO 扩展：<?= $pdoOk ? "<span class='y'>✔</span>" : "<span class='n'>✘ 未开启</span>" ?></div>
      <div>MySQL 驱动（pdo_mysql）：<?= $pdoMysql ? "<span class='y'>✔ 可选</span>" : "<span>— 未开启</span>" ?></div>
      <div>SQLite 驱动（pdo_sqlite）：<?= $pdoSqlite ? "<span class='y'>✔ 可选</span>" : "<span>— 未开启</span>" ?></div>
      <div>至少一个数据库驱动：<?= $anyDriver ? "<span class='y'>✔</span>" : "<span class='n'>✘ 必需</span>" ?></div>
      <div>必需扩展（mbstring / json / session / hash）：<?= $extOk ? "<span class='y'>✔</span>" : "<span class='n'>✘ 缺少必需扩展，页面会报 Fatal</span>" ?></div>
      <div>GD 库（可选，核心功能不依赖）：<?= $gd ? "<span class='y'>✔</span>" : "<span>— 未开启，不影响安装</span>" ?></div>
      <div>ZIP 扩展（后台完整备份 / 应用包安装）：<?= $zipOk ? "<span class='y'>✔</span>" : "<span>— 未开启，只能做「仅数据库」备份</span>" ?></div>
      <div>usr/data 目录可写（写入 config.php）：<?= $dataWritable ? "<span class='y'>✔</span>" : "<span class='n'>✘ 请设置可写</span>" ?></div>
      <div>usr/uploads 目录可写：<?= $uploadWritable ? "<span class='y'>✔</span>" : "<span class='n'>✘ 请设置可写</span>" ?></div>
      <div>SQLite 数据目录位于 Web 根外或受保护的 usr/data：<?= $dataDirSafe ? "<span class='y'>✔</span>" : "<span class='n'>✘ 请调整目录</span>" ?></div>
      <div>SQLite 数据目录在 open_basedir 允许范围内：
        <?php if ($openBasedir === ''): ?><span class='y'>✔ 未限制</span>
        <?php elseif ($sqliteDirAllowed): ?><span class='y'>✔</span>
        <?php else: ?><span class='n'>✘ 受限（见下方提示）</span><?php endif; ?>
      </div>
    </div>
    <p class="warn">选 SQLite 时，数据库文件必须放在站点目录之外，或放在受保护的 <code>usr/data/</code> 目录内（向导会强制校验）。单文件库一旦能被 URL 直接访问，整站用户数据会被下载。</p>
    <?php if ($openBasedir !== '' && !$sqliteDirAllowed): ?>
      <p class="warn">检测到 <code>open_basedir</code> 限制，默认 SQLite 数据目录不在允许路径内。请在第 3 步把「数据库文件」改到允许的路径（可使用主机提供的 <code>/tmp</code>，或站点内受保护的 <code>usr/data/</code>），或直接改选 MySQL。</p>
    <?php endif; ?>
    <?php $canGo = $phpOk && $pdoOk && $anyDriver && $uploadWritable && $dataWritable && $dataDirSafe && $extOk; ?>
    <?php if ($canGo): ?>
      <a class="btn" href="install.php?step=3">下一步 →</a>
    <?php else: ?>
      <p class="alert">有未通过项，请先解决后刷新。</p>
    <?php endif; ?>

  <?php elseif ($step == 3): ?>
    <?php $isReinstall = is_file($root . '/install.lock'); ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($isReinstall): ?><p class="warn">检测到 <code>install.lock</code>：本次提交为<b>重装</b>，会清空全部数据表，请输入当前管理员口令二次确认。</p><?php endif; ?>
    <form method="post" action="install.php?step=3">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <?php if ($isReinstall): ?>
        <div class="field"><label>管理员口令二次确认</label><input type="password" name="admin_confirm_pass" required></div>
      <?php endif; ?>
      <div class="field"><label>数据库类型</label>
        <select name="db_type" id="db_type" onchange="gblogSwitchDriver()">
          <option value="mysql" <?= $formType==='mysql'?'selected':'' ?>>MySQL（需数据库账号，适合正式站点）</option>
          <option value="sqlite" <?= $formType==='sqlite'?'selected':'' ?>>SQLite（单文件、零配置，适合个人站/本地）</option>
        </select>
      </div>

      <div id="mysql-fields">
        <div class="row">
          <div class="field"><label>数据库主机</label><input name="db_host" value="localhost"></div>
          <div class="field"><label>数据库名</label><input name="db_name" placeholder="gblog"></div>
        </div>
        <div class="row">
          <div class="field"><label>数据库用户</label><input name="db_user" value="root"></div>
          <div class="field"><label>数据库密码</label><input type="password" name="db_pass"></div>
        </div>
      </div>

      <div id="sqlite-fields">
        <div class="field"><label>数据库文件（绝对路径，站外或受保护的 usr/data/）</label>
          <input name="db_path" value="<?= htmlspecialchars(gblog_sqlite_default_path()) ?>">
        </div>
        <p class="hint">留空即使用 <code>usr/data/gblog.sqlite</code>。目录会自动写入 .htaccess / web.config 拒绝直接访问；站点内除 usr/data 外的路径会被拒绝。</p>
      </div>

      <div class="row">
        <div class="field"><label>网站名称</label><input name="site_name" value="Gblog"></div>
      </div>
      <div class="row">
        <div class="field"><label>管理员账号</label><input name="admin_user" value="admin"></div>
        <div class="field"><label>管理员密码（≥8位，含大小写字母）</label><input type="password" name="admin_pass"></div>
      </div>
      <button class="btn" type="submit">开始安装</button>
    </form>
    <script>
    function gblogSwitchDriver() {
      var t = document.getElementById('db_type').value;
      var m = document.getElementById('mysql-fields');
      var s = document.getElementById('sqlite-fields');
      m.style.display = (t === 'mysql') ? '' : 'none';
      s.style.display = (t === 'sqlite') ? '' : 'none';
      [m, s].forEach(function (box) {
        var off = (box.style.display === 'none');
        box.querySelectorAll('input, select').forEach(function (el) { el.disabled = off; });
      });
    }
    gblogSwitchDriver();
    </script>

  <?php else: ?>
    <div class="alert ok">🎉 安装成功！</div>
    <p>数据库：<b><?= htmlspecialchars((string)($_SESSION['gblog_install_driver'] ?? 'MySQL')) ?></b>
       <?php if (!empty($_SESSION['gblog_install_location'])): ?>
         · <code><?= htmlspecialchars((string)$_SESSION['gblog_install_location']) ?></code>
       <?php endif; ?>
    </p>
    <?php if (($_SESSION['gblog_install_driver'] ?? '') === 'SQLite'): ?>
      <p class="warn">请确认数据库文件无法通过浏览器直接访问；不要把它移动到站点目录内。同时请定期备份该文件（备份/恢复功能在后台「安全管理」）。</p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['gblog_install_lock_warning'])): ?>
      <p class="alert">install.lock 未能自动写入，请在站点根目录手动创建一个空的 install.lock 文件，否则站点存在被他人重装的风险。</p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['gblog_install_htaccess_notice'])): ?>
      <p class="warn"><b>伪静态规则提醒</b>：<?= $_SESSION['gblog_install_htaccess_notice'] ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['gblog_install_fk_warning']) && is_array($_SESSION['gblog_install_fk_warning'])): ?>
      <p class="warn"><b>外键约束未创建</b>：数据库账号缺少 <code>REFERENCES</code> 权限，级联删除不会自动生效。后果——删除文章时不会自动清理其评论 / 歌曲 / 标签关联，删除分类时文章的分类不会被自动置空。站点其余功能不受影响。建议向主机商申请为该账号授予 REFERENCES 权限，或在 phpMyAdmin 中执行 <code>install/mysql_constraints.sql</code> 手动补建外键。</p>
    <?php endif; ?>
    <?php
      $v = function_exists('gblog_verify_install') ? gblog_verify_install() : null;
      if ($v && empty($v['error'])):
        if ($v['ok']):
    ?>
    <div class="alert ok">✅ 数据库完整性校验通过：<?= count($v['tables_present']) ?>/<?= count($v['tables_expected']) ?> 张表齐全，schema_version=<?= htmlspecialchars((string)$v['schema_version']) ?><?php if ($v['fk_count'] !== null): ?>，外键 <?= $v['fk_count'] ?> 条（无 REFERENCES 权限时为 0，属正常）<?php endif; ?>。</div>
    <?php else: ?>
    <div class="alert">⚠️ 安装校验发现不完整，建议处理后再使用：
      <ul style="margin:8px 0 0;padding-left:20px;line-height:1.9;">
        <?php if (!empty($v['tables_missing'])): ?><li>缺失数据表：<code><?= htmlspecialchars(implode(', ', $v['tables_missing'])) ?></code></li><?php endif; ?>
        <?php if ($v['schema_version'] !== $v['schema_expected']): ?><li>schema_version = <code><?= htmlspecialchars((string)($v['schema_version'] ?? 'null')) ?></code>（应为 <?= $v['schema_expected'] ?>），迁移可能未执行完。</li><?php endif; ?>
        <?php if (!$v['article_ok']): ?><li>缺少启用的文章类栏目（当前 <?= (int)$v['article_count'] ?> 个），首页可能无法访问。</li><?php endif; ?>
        <?php if (!empty($v['tables_extra'])): ?><li>库内存在非 Gblog 表：<code><?= htmlspecialchars(implode(', ', $v['tables_extra'])) ?></code>（多为同库其他程序，可忽略）。</li><?php endif; ?>
      </ul>
      建议：删除 <code>config.php</code> 与 <code>install.lock</code> 后重新访问 <code>install.php</code> 重装，或手动补建缺失表。
    </div>
    <?php
        endif;
      endif;
    ?>
    <p class="hint">建议删除 <code>install.php</code>；<b>请保留 <code>install.lock</code></b>，它是防止站点被恶意重装的保护锁。</p>
    <?php if ($justInstalled && is_file($root . '/install.php')): ?>
    <form method="post" action="install.php?step=4" style="display:inline;">
      <input type="hidden" name="action" value="del_install">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button class="btn" type="submit" style="background:#c0392b;" onclick="return confirm('确定删除 install.php？删除后站点将不能被重装，如需重装需手动恢复该文件。')">一键删除 install.php（推荐）</button>
    </form>
    <?php endif; ?>
    <a class="btn" href="index.php">访问首页</a>
    <a class="btn" href="admin/login.php" style="background:#2e9e63;">登录后台</a>
    <?php unset($_SESSION['gblog_install_just_done'], $_SESSION['gblog_install_lock_warning'], $_SESSION['gblog_install_fk_warning'], $_SESSION['gblog_install_htaccess_notice']); ?>
  <?php endif; ?>
</div>
</body>
</html>
