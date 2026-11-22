-- 表结构说明、字段字典与兼容性约束见《Gblog 开发笔记》。

-- ---------- 栏目/站点表（多站点核心，程序内置栏目，不可删除，可启停） ----------
CREATE TABLE IF NOT EXISTS sites (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)     NOT NULL,                  -- 站名
    slug        VARCHAR(120)     NOT NULL,                  -- 栏目标识
    description VARCHAR(500)     NULL,                      -- 简介
    type        VARCHAR(20)      NOT NULL DEFAULT 'article',-- 栏目类型：article/song/software/image/novel
    menu_name   VARCHAR(100)     NOT NULL DEFAULT '',       -- 菜单显示名
    domain      VARCHAR(190)     NULL,                      -- 绑定域名（可空）
    home_mode   VARCHAR(20)      NOT NULL DEFAULT 'default',-- 栏目首页形态：default=列表 / aggregate=聚合版式
    enabled     TINYINT(1)       NOT NULL DEFAULT 1,        -- 启用开关
    show_in_menu TINYINT(1)      NOT NULL DEFAULT 1,        -- 是否在首页导航菜单显示
    is_builtin  TINYINT(1)      NOT NULL DEFAULT 0,        -- 1=程序内置栏目：身份字段只读、不可删除
    sort_order  INT              NOT NULL DEFAULT 0,
    created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_slug (slug),
    UNIQUE KEY uq_site_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cat_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id    INT UNSIGNED NOT NULL DEFAULT 0,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tags_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    content     LONGTEXT     NOT NULL,               -- 正文（HTML，可含视频）
    excerpt     TEXT         NULL,
    cover       VARCHAR(1000) NULL,
    status      ENUM('published','draft') NOT NULL DEFAULT 'published',
    is_top      TINYINT(1)    NOT NULL DEFAULT 0,
    sort_order  INT           NOT NULL DEFAULT 0,
    source_url  VARCHAR(1000) NULL,
    author      VARCHAR(100)  NULL,
    template    VARCHAR(50)   NULL,
    download_level ENUM('free','login','coin') NOT NULL DEFAULT 'free',
    download_price INT        NOT NULL DEFAULT 0,
    download_url VARCHAR(1000) NULL,
    file_size    VARCHAR(50)   NULL,                      -- 软件大小（如 12.4 MB）
    download_count INT UNSIGNED NOT NULL DEFAULT 0,      -- 下载计数
    software_type    VARCHAR(100) NULL,                  -- 软件类型（自由文本，如 PHP 源码 / EXE 安装版 / 安卓 APP）
    software_platform VARCHAR(100) NULL,                -- 运行平台（自由文本，如 Windows 10/11 64位 / 跨平台 / 需 PHP 环境）
    software_version VARCHAR(60)  NULL,                  -- 软件版本（只填版本号，如 V4.2.1）
    official_url    VARCHAR(1000) NULL,                  -- 官方网站
    language        VARCHAR(50)   NULL,                  -- 软件语言（如 中文）
    rating          DECIMAL(2,1)  NOT NULL DEFAULT 0.0,  -- 推荐星级（0.0-5.0，头部块星级展示）
    user_id     INT UNSIGNED  NULL,
    view_count  INT UNSIGNED  NOT NULL DEFAULT 0,
    favorite_count INT UNSIGNED NOT NULL DEFAULT 0,
    like_count  INT UNSIGNED  NOT NULL DEFAULT 0,
    category_id INT UNSIGNED  NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_post_site_status (site_id, status),
    KEY idx_post_cat (category_id),
    KEY idx_post_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    KEY idx_pt_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS songs (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    site_id      INT UNSIGNED     NOT NULL DEFAULT 0,       -- 归属音乐站点（多音乐站点隔离；默认 0 由迁移回填）
    post_id      INT UNSIGNED     NULL,
    user_id      INT UNSIGNED     DEFAULT 1,
    title        VARCHAR(255)     NOT NULL,
    artist       VARCHAR(255)     NULL,
    lyricist     VARCHAR(100)     NULL,
    cover_url    VARCHAR(1000)    NULL,
    audio_url    VARCHAR(1000)    NULL,
    download_url VARCHAR(1000)    NULL,
    download_level ENUM('free','login','coin') NOT NULL DEFAULT 'free',
    download_price INT            NOT NULL DEFAULT 0,
    lyrics       TEXT             NULL,
    note         TEXT             NULL,
    is_upload    TINYINT(1)       NOT NULL DEFAULT 0,
    sort_order   INT              NOT NULL DEFAULT 0,
    status       ENUM('published','draft') NOT NULL DEFAULT 'published',
    category_id  INT UNSIGNED     NOT NULL DEFAULT 0,
    play_count   INT UNSIGNED     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_song_post (post_id),
    KEY idx_song_site_status (site_id, status),
    KEY idx_song_status_play (status, play_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id     INT UNSIGNED NOT NULL DEFAULT 0,
    target_type VARCHAR(20)  NOT NULL DEFAULT 'post',
    target_id   INT UNSIGNED NOT NULL DEFAULT 0,
    site_id     INT UNSIGNED NOT NULL DEFAULT 0,            -- 目标所属站点（文章评论=posts.site_id；歌曲评论=原 songs.site_id 回填）
    parent_id   INT UNSIGNED NULL,
    user_id     INT UNSIGNED NOT NULL DEFAULT 0, -- 登录用户评论归属；匿名评论为 0
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(190) NULL,
    content     TEXT         NOT NULL,
    status      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comment_post (post_id, status),
    KEY idx_comment_target (target_type, target_id, status),
    KEY idx_comment_site (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS link_categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_link_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO link_categories (id, name, sort_order) VALUES (1, '未分类', 0);

CREATE TABLE IF NOT EXISTS links (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL DEFAULT 1,
    name        VARCHAR(100) NOT NULL,
    url         VARCHAR(500) NOT NULL,
    description VARCHAR(255) NULL,
    icon_url    VARCHAR(255) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_link_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_key  VARCHAR(50)  NOT NULL,      -- 广告位标识：header_top/sidebar/article_bottom/article_sidebar
    title   VARCHAR(100) NOT NULL,
    code    TEXT         NULL,
    enabled TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ad_key (ad_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    skey   VARCHAR(100) NOT NULL,
    svalue TEXT         NULL,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apps (
    app_id       VARCHAR(100) NOT NULL,
    type         VARCHAR(20)  NOT NULL,             -- theme | plugin
    enabled      TINYINT(1)   NOT NULL DEFAULT 0,
    version      VARCHAR(30)  NOT NULL DEFAULT '',
    installed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (app_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role         ENUM('admin','editor','author','contributor','member') NOT NULL DEFAULT 'member',
    nickname     VARCHAR(50)  NULL,
    email        VARCHAR(100) NULL,
    phone        VARCHAR(20)  NULL,
    qq           VARCHAR(30)  NULL,
    wechat       VARCHAR(50)  NULL,
    security_code VARCHAR(64) NULL,          -- 恢复码（bcrypt hash，绝不明文存）
    password_version INT      NOT NULL DEFAULT 0,  -- 改密后 +1，使所有旧会话失效
    must_change_password TINYINT(1) NOT NULL DEFAULT 0, -- 后台重置后首次登录强制改密
    gold         INT          NOT NULL DEFAULT 0,
    status       ENUM('active','banned') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 安全表：一次性重置令牌 / 限流 / 审计日志 ----------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL,       -- 只存 sha256，明文令牌只出现一次
    channel     VARCHAR(20)  NOT NULL DEFAULT '',
    expires_at  INT          NOT NULL,       -- unix 时间戳，15 分钟有效
    used        TINYINT(1)   NOT NULL DEFAULT 0,
    ip          VARCHAR(64)  NULL,
    created_at  INT          NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reset_hash (token_hash),
    KEY idx_reset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket       VARCHAR(64)  NOT NULL,
    identity     VARCHAR(127) NOT NULL,
    hits         INT          NOT NULL DEFAULT 0,
    window_start INT          NOT NULL,
    PRIMARY KEY (bucket, identity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action      VARCHAR(64)  NOT NULL,
    user_id     INT          NOT NULL DEFAULT 0,
    detail      VARCHAR(255) NULL,
    ip          VARCHAR(64)  NULL,
    created_at  INT          NOT NULL,
    PRIMARY KEY (id),
    KEY idx_log_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coin_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    amount     INT          NOT NULL,          -- 正=收入 负=支出
    reason     VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_coin_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS download_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    post_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dl_user_post (user_id, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_reactions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    post_id    INT UNSIGNED NOT NULL,
    rtype      VARCHAR(16)  NOT NULL DEFAULT 'like', -- like | favorite
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_react (user_id, post_id, rtype),
    KEY idx_react_post (post_id),
    KEY idx_react_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS view_history (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    post_id    INT UNSIGNED NOT NULL,
    viewed_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vh_user (user_id),
    KEY idx_vh_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS novel_chapters (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    novel_id   INT UNSIGNED NOT NULL,          -- 对应 posts.id（小说作品）
    title      VARCHAR(255) NOT NULL,
    content    LONGTEXT     NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    status     ENUM('published','draft') NOT NULL DEFAULT 'published',
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_novel (novel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================= 程序内置栏目（is_builtin=1：不可删除、身份字段只读，可在后台启用/停用） 用户新建栏目不写此列，默认 0：可改全部字
INSERT INTO sites (name, slug, description, type, menu_name, enabled, sort_order, is_builtin) VALUES
('文章', 'post',     '文章发布',          'article',  '文章',     1, 0, 1),
('音乐', 'music',    '惟将心绪，酿成歌',  'song',     '音乐',     1, 2, 1),
('软件', 'soft',     '软件下载',          'software', '软件',     1, 1, 1),
('图片', 'image',    '图片集/卡片杂志',   'image',    '图片',     1, 3, 1),
('小说', 'novel',    '小说作品与章节阅读','novel',    '小说',     1, 3, 1);

-- 分类（categories）与标签（tags）不预置任何数据：新站从空开始，由管理员按自己内容建。
-- 早期版本这里塞了 12 个分类 + 4 个标签，装完后台一堆用不上的条目，还得手工删。

INSERT INTO ads (ad_key, title, code, enabled) VALUES
('header_top', '首页顶部广告', NULL, 0),
('sidebar', '侧边栏广告', NULL, 0),
('article_bottom', '文章正文下方广告', NULL, 0),
('article_sidebar', '文章页侧边栏广告', NULL, 0);

INSERT INTO settings (skey, svalue) VALUES
('site_name', 'Gblog'),
('site_desc', '记录技术、音乐与生活'),
('site_title', ''),
('about_content', ''),
('comment_open', '1'),
('comment_moderate', '1'),
('stat_code', ''),
('sidebar_hot_scope', 'global'),
('sidebar_hot_sites', ''),
('register_open', '1'),
('register_email', 'optional'),
('register_phone', 'optional'),
('register_qq', 'optional'),
('register_wechat', 'optional'),
('register_gold', '0'),
('sign_gold', '1'),
('gold_enabled', '1'),
('home_theme', 'default'),
('home_aggregate_sites', ''),
('logo_url', ''),
('footer_copyright', ''),
('footer_icp', ''),
('footer_powered', ' 由 Gblog 驱动'),
('footer_powered_url', ''),
('timezone', 'Asia/Shanghai'),
('schema_version', '4'),
('app_store_url', ''),
('permalink_enabled', '0'),
('permalink_style', 's3');
