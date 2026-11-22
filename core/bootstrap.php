<?php
/* Gblog 内核入口：只做加载与启动，不承载业务函数。
 *
 * core/ 按职责分组，新增模块必须插在依赖它的文件之前：
 *   data/   数据层（连接、迁移、内容取数原语）
 *   route/  路由与作用域（伪静态 URL、页面上下文、请求作用域）
 *   render/ 渲染层（主题资源、视图、版式、模板标签、图标）
 *   ext/    扩展机制（钩子、应用包、聚合模块）
 *   auth/   权限与安全（角色、CSRF、限流）
 *   lib/    业务函数（由 bootstrap 时代拆出，纯函数定义）
 */

// —— 业务函数：纯定义无副作用，先加载，后续文件在载入期即可调用
require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/posts.php';
require_once __DIR__ . '/lib/comments.php';
require_once __DIR__ . '/lib/users.php';
require_once __DIR__ . '/lib/music.php';
require_once __DIR__ . '/lib/backup.php';
require_once __DIR__ . '/lib/links.php';
require_once __DIR__ . '/lib/misc.php';

// —— 内核模块（顺序沿用拆分前的依赖关系）
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/render/icons.php';
require_once __DIR__ . '/render/theme.php';
require_once __DIR__ . '/ext/hooks.php';
require_once __DIR__ . '/ext/apps.php';
require_once __DIR__ . '/render/view.php';
require_once __DIR__ . '/route/permalink.php';
require_once __DIR__ . '/render/layout.php';
require_once __DIR__ . '/render/template-tags.php';
require_once __DIR__ . '/data/content_blocks.php';

gblog_load_apps();

// 前台也需要会话（评论区 CSRF 校验依赖 $_SESSION）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tz = get_setting('timezone', 'Asia/Shanghai');
date_default_timezone_set($tz);
