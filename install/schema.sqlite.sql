-- 表结构说明、字段字典与兼容性约束见《Gblog 开发笔记》。

CREATE TABLE IF NOT EXISTS `sites` (
    `id`          INTEGER PRIMARY KEY AUTOINCREMENT,
    `name`        TEXT NOT NULL,
    `slug`        TEXT NOT NULL,
    `description` TEXT,
    `type`        TEXT NOT NULL DEFAULT 'article',
    `menu_name`   TEXT NOT NULL DEFAULT '',
    `domain`      TEXT,
    `home_mode`   TEXT NOT NULL DEFAULT 'default',
    `enabled`     INTEGER NOT NULL DEFAULT 1,
    `show_in_menu` INTEGER NOT NULL DEFAULT 1,
    `is_builtin`  INTEGER NOT NULL DEFAULT 0,
    `sort_order`  INTEGER NOT NULL DEFAULT 0,
    `created_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_site_slug` ON `sites` (`slug`);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_site_domain` ON `sites` (`domain`);

CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `site_id`    INTEGER NOT NULL REFERENCES `sites`(`id`) ON DELETE CASCADE,
    `name`       TEXT NOT NULL,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_cat_site` ON `categories` (`site_id`);

CREATE TABLE IF NOT EXISTS `tags` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `site_id`    INTEGER NOT NULL DEFAULT 0,
    `name`       TEXT NOT NULL,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_tags_site` ON `tags` (`site_id`);

CREATE TABLE IF NOT EXISTS `posts` (
    `id`          INTEGER PRIMARY KEY AUTOINCREMENT,
    `site_id`     INTEGER NOT NULL REFERENCES `sites`(`id`) ON DELETE CASCADE,
    `title`       TEXT NOT NULL,
    `content`     TEXT NOT NULL,
    `excerpt`     TEXT,
    `cover`       TEXT,
    `status`      TEXT NOT NULL DEFAULT 'published',
    `is_top`      INTEGER NOT NULL DEFAULT 0,
    `sort_order`  INTEGER NOT NULL DEFAULT 0,
    `source_url`  TEXT,
    `author`      TEXT,
    `template`    TEXT,
    `download_level` TEXT NOT NULL DEFAULT 'free',
    `download_price` INTEGER NOT NULL DEFAULT 0,
    `download_url`   TEXT,
    `file_size`      TEXT,
    `download_count` INTEGER NOT NULL DEFAULT 0,
    `software_type`     TEXT,
    `software_platform` TEXT,
    `software_version` TEXT,
    `official_url`    TEXT,
    `language`        TEXT,
    `rating`          REAL NOT NULL DEFAULT 0.0,
    `user_id`     INTEGER,
    `view_count`  INTEGER NOT NULL DEFAULT 0,
    `favorite_count` INTEGER NOT NULL DEFAULT 0,
    `like_count`  INTEGER NOT NULL DEFAULT 0,
    `category_id` INTEGER REFERENCES `categories`(`id`) ON DELETE SET NULL,
    `created_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_post_site_status` ON `posts` (`site_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_post_cat` ON `posts` (`category_id`);
CREATE INDEX IF NOT EXISTS `idx_post_created` ON `posts` (`created_at`);

CREATE TABLE IF NOT EXISTS `post_tags` (
    `post_id` INTEGER NOT NULL REFERENCES `posts`(`id`) ON DELETE CASCADE,
    `tag_id`  INTEGER NOT NULL REFERENCES `tags`(`id`) ON DELETE CASCADE,
    PRIMARY KEY (`post_id`, `tag_id`)
);
CREATE INDEX IF NOT EXISTS `idx_pt_tag` ON `post_tags` (`tag_id`);

CREATE TABLE IF NOT EXISTS `songs` (
    `id`          INTEGER PRIMARY KEY AUTOINCREMENT,
    `post_id`     INTEGER REFERENCES `posts`(`id`) ON DELETE CASCADE,
    `user_id`     INTEGER DEFAULT 1,
    `title`       TEXT NOT NULL,
    `artist`      TEXT,
    `lyricist`    TEXT,
    `cover_url`   TEXT,
    `audio_url`   TEXT,
    `download_url` TEXT,
    `download_level` TEXT NOT NULL DEFAULT 'free',
    `download_price` INTEGER NOT NULL DEFAULT 0,
    `lyrics`      TEXT,
    `note`        TEXT,
    `is_upload`   INTEGER NOT NULL DEFAULT 0,
    `sort_order`  INTEGER NOT NULL DEFAULT 0,
    `status`      TEXT NOT NULL DEFAULT 'published',
    `category_id` INTEGER NOT NULL DEFAULT 0,
    `play_count`  INTEGER NOT NULL DEFAULT 0,
    `created_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_song_post` ON `songs` (`post_id`);

CREATE TABLE IF NOT EXISTS `comments` (
    `id`          INTEGER PRIMARY KEY AUTOINCREMENT,
    `post_id`     INTEGER NOT NULL DEFAULT 0,
    `target_type` TEXT NOT NULL DEFAULT 'post',
    `target_id`   INTEGER NOT NULL DEFAULT 0,
    `parent_id`   INTEGER,
    `user_id`     INTEGER NOT NULL DEFAULT 0,
    `name`        TEXT NOT NULL,
    `email`       TEXT,
    `content`     TEXT NOT NULL,
    `status`      TEXT NOT NULL DEFAULT 'pending',
    `created_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_comment_post` ON `comments` (`post_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_comment_target` ON `comments` (`target_type`, `target_id`, `status`);

CREATE TABLE IF NOT EXISTS `link_categories` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `name`       TEXT NOT NULL UNIQUE,
    `sort_order` INTEGER NOT NULL DEFAULT 0,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO `link_categories` (`id`, `name`, `sort_order`) VALUES (1, '未分类', 0);

CREATE TABLE IF NOT EXISTS `links` (
    `id`          INTEGER PRIMARY KEY AUTOINCREMENT,
    `category_id` INTEGER NOT NULL DEFAULT 1,
    `name`        TEXT NOT NULL,
    `url`         TEXT NOT NULL,
    `description` TEXT,
    `icon_url`    TEXT,
    `sort_order`  INTEGER NOT NULL DEFAULT 0,
    `created_at`  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_link_category` ON `links` (`category_id`);

CREATE TABLE IF NOT EXISTS `ads` (
    `id`      INTEGER PRIMARY KEY AUTOINCREMENT,
    `ad_key`  TEXT NOT NULL,
    `title`   TEXT NOT NULL,
    `code`    TEXT,
    `enabled` INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_ad_key` ON `ads` (`ad_key`);

CREATE TABLE IF NOT EXISTS `settings` (
    `skey`   TEXT NOT NULL PRIMARY KEY,
    `svalue` TEXT
);

CREATE TABLE IF NOT EXISTS `apps` (
    `app_id`       TEXT NOT NULL,
    `type`         TEXT NOT NULL,
    `enabled`      INTEGER NOT NULL DEFAULT 0,
    `version`      TEXT NOT NULL DEFAULT '',
    `installed_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`app_id`, `type`)
);

CREATE TABLE IF NOT EXISTS `users` (
    `id`           INTEGER PRIMARY KEY AUTOINCREMENT,
    `username`     TEXT NOT NULL,
    `password_hash` TEXT NOT NULL,
    `role`         TEXT NOT NULL DEFAULT 'member',
    `nickname`     TEXT,
    `email`        TEXT,
    `phone`        TEXT,
    `qq`           TEXT,
    `wechat`       TEXT,
    `security_code` TEXT,
    `password_version` INTEGER NOT NULL DEFAULT 0,
    `must_change_password` INTEGER NOT NULL DEFAULT 0,
    `gold`         INTEGER NOT NULL DEFAULT 0,
    `status`       TEXT NOT NULL DEFAULT 'active',
    `created_at`   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_username` ON `users` (`username`);

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id`    INTEGER NOT NULL,
    `token_hash` TEXT NOT NULL,
    `channel`    TEXT NOT NULL DEFAULT '',
    `expires_at` INTEGER NOT NULL,
    `used`       INTEGER NOT NULL DEFAULT 0,
    `ip`         TEXT,
    `created_at` INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS `idx_reset_hash` ON `password_resets` (`token_hash`);
CREATE INDEX IF NOT EXISTS `idx_reset_user` ON `password_resets` (`user_id`);

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `bucket`       TEXT NOT NULL,
    `identity`     TEXT NOT NULL,
    `hits`         INTEGER NOT NULL DEFAULT 0,
    `window_start` INTEGER NOT NULL,
    PRIMARY KEY (`bucket`, `identity`)
);

CREATE TABLE IF NOT EXISTS `security_logs` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `action`     TEXT NOT NULL,
    `user_id`    INTEGER NOT NULL DEFAULT 0,
    `detail`     TEXT,
    `ip`         TEXT,
    `created_at` INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS `idx_log_time` ON `security_logs` (`created_at`);

CREATE TABLE IF NOT EXISTS `coin_logs` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id`    INTEGER NOT NULL,
    `amount`     INTEGER NOT NULL,
    `reason`     TEXT,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_coin_user` ON `coin_logs` (`user_id`);

CREATE TABLE IF NOT EXISTS `download_logs` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id`    INTEGER NOT NULL,
    `post_id`    INTEGER NOT NULL,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (`user_id`, `post_id`)
);

CREATE TABLE IF NOT EXISTS `post_reactions` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id`    INTEGER NOT NULL,
    `post_id`    INTEGER NOT NULL,
    `rtype`      TEXT NOT NULL DEFAULT 'like',
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_react` ON `post_reactions` (`user_id`, `post_id`, `rtype`);
CREATE INDEX IF NOT EXISTS `idx_react_post` ON `post_reactions` (`post_id`);
CREATE INDEX IF NOT EXISTS `idx_react_user` ON `post_reactions` (`user_id`);

CREATE TABLE IF NOT EXISTS `view_history` (
    `id`        INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id`   INTEGER NOT NULL,
    `post_id`   INTEGER NOT NULL,
    `viewed_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_vh_user` ON `view_history` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_vh_post` ON `view_history` (`post_id`);

CREATE TABLE IF NOT EXISTS `novel_chapters` (
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `novel_id`   INTEGER NOT NULL,
    `title`      TEXT NOT NULL,
    `content`    TEXT NOT NULL,
    `sort_order` INTEGER NOT NULL DEFAULT 0,
    `status`     TEXT NOT NULL DEFAULT 'published',
    `view_count` INTEGER NOT NULL DEFAULT 0,
    `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_novel` ON `novel_chapters` (`novel_id`);

-- SQLite 没有 ON UPDATE CURRENT_TIMESTAMP，用触发器补齐
CREATE TRIGGER IF NOT EXISTS `trg_posts_updated` AFTER UPDATE ON `posts`
FOR EACH ROW WHEN NEW.`updated_at` = OLD.`updated_at`
BEGIN
    UPDATE `posts` SET `updated_at` = CURRENT_TIMESTAMP WHERE `id` = NEW.`id`;
END;

CREATE TRIGGER IF NOT EXISTS `trg_chapters_updated` AFTER UPDATE ON `novel_chapters`
FOR EACH ROW WHEN NEW.`updated_at` = OLD.`updated_at`
BEGIN
    UPDATE `novel_chapters` SET `updated_at` = CURRENT_TIMESTAMP WHERE `id` = NEW.`id`;
END;

-- ============================================= 程序内置栏目（is_builtin=1：不可删除、身份字段只读，可在后台启用/停用） 用户新建栏目不写此列，默认 0：可改全部字
INSERT OR IGNORE INTO `sites` (`name`, `slug`, `description`, `type`, `menu_name`, `enabled`, `sort_order`, `is_builtin`) VALUES
('文章', 'post',     '文章发布',          'article',  '文章',     1, 0, 1),
('音乐', 'music',    '惟将心绪，酿成歌',  'song',     '音乐',     1, 2, 1),
('软件', 'soft',     '软件下载',          'software', '软件',     1, 1, 1),
('图片', 'image',    '图片集/卡片杂志',   'image',    '图片',     1, 3, 1),
('小说', 'novel',    '小说作品与章节阅读','novel',    '小说',     1, 3, 1);

-- 分类（categories）与标签（tags）不预置任何数据：新站从空开始，由管理员按自己内容建。
-- 早期版本这里塞了 12 个分类 + 4 个标签，装完后台一堆用不上的条目，还得手工删。

INSERT OR IGNORE INTO `ads` (`ad_key`, `title`, `code`, `enabled`) VALUES
('header_top', '首页顶部广告', NULL, 0),
('sidebar', '侧边栏广告', NULL, 0),
('article_bottom', '文章正文下方广告', NULL, 0),
('article_sidebar', '文章页侧边栏广告', NULL, 0);

INSERT OR IGNORE INTO `settings` (`skey`, `svalue`) VALUES
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
