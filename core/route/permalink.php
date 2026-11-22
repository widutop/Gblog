<?php
/**
 * 伪静态（固定链接）助手 —— 三种内容形态 + 一个应急回退开关
 *
 * 内容页 URL（后台「基础设置 → 伪静态形态」三选一，前缀 = 所属栏目 slug）：
 *   s1  栏目目录 + 纯 ID：        /post/101.html      /ganwu/101.html
 *   s2  栏目目录 + 发布日期+ID：  /post/20261114101.html /ganwu/20261114101.html
 *   s3  顶级 栏目-ID：            /post-101.html      /ganwu-101.html
 * 内置栏目 slug 恰是 post/music/soft/image/novel，与旧 type 前缀完全一致；
 * 自建栏目直接用自身 slug，如 ganwu/101.html、ganwu-101.html、ganwu/20261114101.html。
 *
 * 只有栏目（sites）保留 slug —— 它同时是内容页 URL 前缀与版式/自定义 CSS 的配置键。
 * 文章 / 分类 / 标签一律按全局 id 定位：?do=post&id=N、?do=category&id=N、?do=tag&id=N，
 * 伪静态形态里同样是纯 id（category-N.html / tag-N.html / 内容页三种形态）。
 *
 * 非内容页默认顶级 .html；其中章节页跟随伪静态形态切换「novel」与正文间的分隔：
 *   /post.html /soft.html /music.html /image.html /novel.html   栏目首页（slug 与 type 前缀同名）
 *   /{栏目slug}.html                                            栏目首页通用形态（用户可控）
 *   /column-N.html                                              slug 不安全时的兜底（同 type 非首个栏目）
 *   /category-N.html /tag-N.html                                分类 / 标签
 *   /novel-{作品id}-{第几章}.html  (s3)   /novel/{作品id}-{第几章}.html  (s1)   /novel/{Ymd}-{作品id}-{第几章}.html  (s2)   章节（Ymd 取章节发布日期）
 *   /chapter-N.html                                            章节旧链接兼容（按章节全局 id）
 *   /about.html /links.html /archive.html /search.html /songs.html /sitemap.html
 *
 * 设计要点：
 *  0. 开关 permalink_enabled=0 时全部出链退回原始 ?do= 形式，用于服务器未配 rewrite 或应急回退；
 *     新装默认关闭，确认 nginx.htaccess / Apache .htaccess 生效后再到后台开启。
 *  1. 服务端 rewrite 同时接受三种形态 + 任意 slug 前缀 + 原始 ?do= 链接，切换形态无需 reload。
 *  2. 栏目首页地址 = 栏目 slug（用户可控）：/ganwu.html；内置栏目 slug 与 type 前缀同名，
 *     自然得到 /post.html 等缩写顶级页。slug 不安全或与 type 前缀错位时回退
 *     /column-{id}.html，rewrite 末尾有通用 slug 规则承接。
 *  3. 内容 id 全局唯一（文章/歌曲共用 content_ids 序列），按 id 唯一定位；前缀与日期只作可读/SEO，
 *     不参与归属校验：改写栏目 slug 后旧链接仍能访问，canonical 会自动指向新前缀。
 *  4. 老模板里仍有大量 'index.php?do=xxx' 硬编码出链，由输出层 gblog_rewrite_hrefs() 统一改写；
 *     AJAX 接口、二进制下载、sitemap XML 不参与。
 *  5. 所有静态资源与相对接口地址统一用 gblog_web_base() 根绝对化：
 *     s1/s2 的 /post/101.html 会被浏览器当作目录，相对 usr/themes/... 会解析错（整页掉样式）。
 */

if (!function_exists('gblog_web_base')) {
    /**
     * 站点 Web 根前缀（以 / 结尾，根安装为 "/"，子目录安装为 "/blog/"）。
     *
     * 前台是 index.php 单入口；nginx/Apache 内部重写后 SCRIPT_NAME 仍是
     * /index.php（子目录安装为 /blog/index.php），取 dirname 即站点根。
     * 后台 admin/*.php 需剥掉 admin 段，避免算成 /admin/。
     */
    function gblog_web_base(): string
    {
        $s = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($s === '' || $s[0] !== '/') {
            $s = str_replace('\\', '/', (string)($_SERVER['PHP_SELF'] ?? '/index.php'));
        }
        // 后台独立脚本：/blog/admin/xxx.php → /blog/index.php
        $pos = strpos($s, '/admin/');
        if ($pos !== false) $s = substr($s, 0, $pos) . '/index.php';
        if (basename($s) !== 'index.php') return '/';
        $dir = rtrim(str_replace('\\', '/', dirname($s)), '/');
        return ($dir === '' || $dir === '.' || $dir === '/') ? '/' : $dir . '/';
    }
}

if (!function_exists('gblog_root_absolute')) {
    /**
     * 把相对 index.php... / usr/... 地址转成站点根绝对；绝对地址、锚点、协议 URL 原样返回。
     * 用于服务端 Location/AJAX redirect：从 /post/101.html 这类页面提交表单时，
     * 相对 Location: index.php?do=profile 会被浏览器解析成 /post/index.php?... 而 404。
     */
    function gblog_root_absolute(string $u): string
    {
        if ($u === '' || $u[0] === '/' || $u[0] === '#' || $u[0] === '?') return $u;
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $u)) return $u;
        if (preg_match('#^(?:index\.php|usr/(?:themes|uploads|plugins)/)#', $u)) {
            return gblog_web_base() . $u;
        }
        return $u;
    }
}

if (!function_exists('gblog_admin_url')) {
    /**
     * 后台页面 URL（站点根绝对）。
     *
     * 后台页面物理上按功能分在 admin/content|system|account/ 子目录，
     * 但页面内相对链接（action="sites.php"）会以「当前 URL 所在目录」为基准解析，
     * 移入子目录后会打错。统一用本函数从站点根拼出目标地址，与文件深度无关。
     *
     * @param string $rel 形如 'sites.php?tab=x' 或 'content/comments.php'；不带 admin/ 前缀。
     */
    function gblog_admin_url(string $rel): string
    {
        return gblog_web_base() . 'admin/' . ltrim($rel, '/');
    }
}

/* ------------------------------------------------------------------ *
 * 开关 / 形态 / 前缀
 * ------------------------------------------------------------------ */

if (!function_exists('gblog_permalink_enabled')) {
    /** 应急回退开关：关掉时全站出链回到原始 ?do= 形式。 */
    function gblog_permalink_enabled(): bool
    {
        static $v = null;
        if ($v !== null) return $v;
        if (!function_exists('get_setting')) return $v = false;
        $raw = (string)get_setting('permalink_enabled', '');
        if ($raw === '') {
            // 旧版本只有 permalink_style（query/path/id/date/colid）：
            // query = 关闭，其余旧形态视为已启用，升级后不会突然变回 ?do=。
            $old = (string)get_setting('permalink_style', 'query');
            return $v = ($old !== '' && $old !== 'query');
        }
        return $v = in_array($raw, ['1', 'on', 'yes', 'true'], true);
    }
}

if (!function_exists('gblog_permalink_style')) {
    /** s1 / s2 / s3；旧值或非法值一律回退 s3。 */
    function gblog_permalink_style(): string
    {
        static $v = null;
        if ($v === null) {
            $raw = (string)(function_exists('get_setting') ? get_setting('permalink_style', 's3') : 's3');
            $v = in_array($raw, ['s1', 's2', 's3'], true) ? $raw : 's3';
        }
        return $v;
    }
}

if (!function_exists('gblog_type_prefix')) {
    /** 栏目 type → URL 前缀（禁按 slug 硬编码）。 */
    function gblog_type_prefix(string $type): string
    {
        $map = ['article' => 'post', 'song' => 'music', 'software' => 'soft',
                'image' => 'image', 'novel' => 'novel'];
        return $map[$type] ?? 'post';
    }
}

if (!function_exists('gblog_type_prefix_map')) {
    /** 兼容旧调用保留：URL 前缀 → 栏目 type。内容页前缀已改由 sites.slug 决定，此表不再参与归属校验。 */
    function gblog_type_prefix_map(): array
    {
        return ['post' => 'article', 'music' => 'song', 'soft' => 'software',
                'image' => 'image', 'novel' => 'novel'];
    }
}

if (!function_exists('gblog_site_slug_is_url_safe')) {
    /**
     * 栏目 slug 能否直接用作内容页 URL 前缀。
     *
     * rewrite 的内容页通用规则只认 [a-zA-Z0-9_-]+，且排在
     * column-N / category-N / tag-N / chapter-N / novel-N-N 等专用规则之后。
     * 中文 slug 会生成 rewrite 无法命中的坏链；占用专用规则名的 slug 会被
     * 抢先解析成栏目页 / 章节页。这两种都回退成 type 前缀，保证链接始终可用。
     */
    function gblog_site_slug_is_url_safe(string $slug): bool
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $slug)) return false;
        static $reserved = ['column', 'category', 'tag', 'chapter',
                            'about', 'links', 'archive', 'search', 'songs', 'sitemap', 'robots',
                            'index', 'admin', 'usr', 'core', 'app', 'install', 'gblog_backups'];
        $lower = strtolower($slug);
        if (in_array($lower, $reserved, true)) return false;
        // 纯数字 slug 会与老链接兜底规则 ^([0-9]+)\.html$（→ 内容页）冲突
        if (preg_match('/^[0-9]+$/', $lower)) return false;
        // novel-2-101.html 会被章节页专用规则抢走（章节规则排在通用内容规则之前）
        if (preg_match('/^novel-[0-9]/', $lower)) return false;
        return true;
    }
}

if (!function_exists('gblog_unique_site_slug')) {
    /**
     * 生成不与其它栏目冲突的 slug（排除自身 $excludeId）。
     * 用于「新建栏目时 slug 留空 → 按类型前缀（post/soft/music/image/novel）自动生成」，
     * 同类型建多个时自动追加序号（post-2、post-3…），避免唯一键冲突。
     */
    function gblog_unique_site_slug(string $slug, int $excludeId = 0): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '-', strtolower(trim($slug)));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        if ($slug === '') {
            $slug = 'post';
        }
        $pdo  = db();
        $base = $slug;
        $i    = 2;
        while (true) {
            $st = $pdo->prepare('SELECT id FROM sites WHERE slug = ? AND id <> ? LIMIT 1');
            $st->execute([$slug, $excludeId]);
            if ($st->fetchColumn() === false) {
                $st->closeCursor();
                break;
            }
            $st->closeCursor();
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}

if (!function_exists('gblog_compact_date')) {
    /** 发布日期 8 位 Ymd（s2 形态用，无连字符）。 */
    function gblog_compact_date($ts): string
    {
        if (!$ts) return '';
        $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
        return $t ? date('Ymd', $t) : '';
    }
}

if (!function_exists('gblog_parse_content_raw_id')) {
    /**
     * 解析 rewrite 传来的数字串：s2 是「8 位发布日期 + 内容 id」，s1 是纯 id。
     * 仅当长度 > 8 且前 8 位是合法日期时才剥离，避免把超长纯 id 误切。
     */
    function gblog_parse_content_raw_id($raw): int
    {
        $raw = preg_replace('/[^0-9]/', '', (string)$raw);
        if ($raw === '') return 0;
        if (strlen($raw) > 8) {
            $y = (int)substr($raw, 0, 4);
            $m = (int)substr($raw, 4, 2);
            $d = (int)substr($raw, 6, 2);
            if ($y >= 2000 && $y <= 2100 && checkdate($m, $d, $y)) {
                return (int)substr($raw, 8);
            }
        }
        return (int)$raw;
    }
}

/* ------------------------------------------------------------------ *
 * slug / id 互查（带请求内静态缓存，避免 N+1 查询）
 * ------------------------------------------------------------------ */

if (!function_exists('gblog_site_row')) {
    /** 按 id 或 slug 取栏目精简信息；只认启用栏目。 */
    function gblog_site_row(int $id, string $slug): array
    {
        static $cache = [];
        $key = $id > 0 ? 'i' . $id : 's' . $slug;
        if ($key === 's') return [];
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = [];
            try {
                if ($id > 0) {
                    $r = db()->prepare('SELECT id, slug, type FROM sites WHERE id = ? AND enabled = 1 LIMIT 1');
                    $r->execute([$id]);
                } else {
                    $r = db()->prepare('SELECT id, slug, type FROM sites WHERE slug = ? AND enabled = 1 LIMIT 1');
                    $r->execute([$slug]);
                }
                $row = $r->fetch();
                if ($row) $cache[$key] = ['id' => (int)$row['id'], 'slug' => (string)$row['slug'], 'type' => (string)$row['type']];
            } catch (Throwable $e) {
            }
        }
        return $cache[$key];
    }
}

if (!function_exists('gblog_first_site_id_by_type')) {
    /** 某 type 下第一个启用栏目占用缩写顶级页（/post.html 等）。 */
    function gblog_first_site_id_by_type(string $type): int
    {
        static $cache = [];
        if ($type === '') return 0;
        if (!array_key_exists($type, $cache)) {
            $cache[$type] = 0;
            try {
                $r = db()->prepare('SELECT id FROM sites WHERE type = ? AND enabled = 1 ORDER BY id ASC LIMIT 1');
                $r->execute([$type]);
                $cache[$type] = (int)($r->fetchColumn() ?? 0);
            } catch (Throwable $e) {
            }
        }
        return $cache[$type];
    }
}

if (!function_exists('gblog_site_id_by_slug')) {
    function gblog_site_id_by_slug(string $slug): int
    {
        $r = gblog_site_row(0, $slug);
        return $r ? (int)$r['id'] : 0;
    }
}

/** 按 id 补齐歌曲生成 URL 所需的字段。 */
if (!function_exists('gblog_song_fields_by_id')) {
    function gblog_song_fields_by_id(int $id): array
    {
        static $cache = [];
        if ($id <= 0) return [];
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = [];
            try {
                $st = db()->prepare(
                    'SELECT sg.id, sg.site_id, sg.created_at, s.type AS site_type, s.slug AS site_slug
                     FROM songs sg LEFT JOIN sites s ON s.id = sg.site_id
                     WHERE sg.id = ? LIMIT 1'
                );
                $st->execute([$id]);
                $r = $st->fetch();
                if ($r) $cache[$id] = $r;
            } catch (Throwable $e) {
            }
        }
        return $cache[$id];
    }
}

if (!function_exists('gblog_content_meta_by_id')) {
    /**
     * 按全局内容 id 反查栏目 type 与发布日期（posts / songs 共用 id 序列）。
     * 列表模板常常只带 id，不带 site_type / created_at：
     *   - 缺 type 时若默认 article，软件/图片/小说链接会错成 post-{id}.html；
     *   - s2 形态缺日期会退化成 /novel/200.html，与同页其它链接形态不一致。
     */
    function gblog_content_meta_by_id(int $id): array
    {
        static $cache = [];
        if ($id <= 0) return [];
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = [];
            try {
                $st = db()->prepare(
                    'SELECT p.created_at, s.type, s.slug FROM posts p LEFT JOIN sites s ON s.id = p.site_id WHERE p.id = ? LIMIT 1'
                );
                $st->execute([$id]);
                $r = $st->fetch();
                $fallback = 'article';
                if (!$r) {
                    $st = db()->prepare(
                        'SELECT sg.created_at, s.type, s.slug FROM songs sg LEFT JOIN sites s ON s.id = sg.site_id WHERE sg.id = ? LIMIT 1'
                    );
                    $st->execute([$id]);
                    $r = $st->fetch();
                    $fallback = 'song';
                }
                if ($r) {
                    $t = (string)($r['type'] ?? '');
                    $cache[$id] = [
                        'type'       => $t !== '' ? $t : $fallback,
                        'slug'       => (string)($r['slug'] ?? ''),
                        'created_at' => (string)($r['created_at'] ?? ''),
                    ];
                }
            } catch (Throwable $e) {
            }
        }
        return $cache[$id];
    }
}

if (!function_exists('gblog_content_type_by_id')) {
    /** 兼容包装：按内容 id 反查所属栏目 type。 */
    function gblog_content_type_by_id(int $id): string
    {
        return (string)(gblog_content_meta_by_id($id)['type'] ?? '');
    }
}

if (!function_exists('gblog_chapter_meta_by_id')) {
    /**
     * 按章节全局 id 反查「作品 id + 本书内第几章 + 发布日期（8 位 Ymd）」。
     *
     * 章节 URL 跟随伪静态形态（与内容页保持一致）：
     *   s1 / s3  → /novel-{作品id}-{第几章}.html  或  /novel/{作品id}-{第几章}.html
     *   s2       → /novel/{Ymd}-{作品id}-{第几章}.html（Ymd 取章节发布日期，与内容页 /post/Ymd+id.html 同款）
     * 序号是已发布章节按 sort_order,id 排序后的位置（从 1 开始），与 sort_order 的
     * 具体数值无关——后台可能用 10/20/30 排序。整本只扫描一次并填满静态缓存，
     * 列表页不会出现 N+1。
     *
     * 草稿 / 不存在的章节不参与章序：直接返回 no=0 并记负缓存，
     * 不会再为每个草稿重复扫一遍整本（长篇 9999 章时每次约 20ms）。
     */
    function gblog_chapter_meta_by_id(int $id): array
    {
        static $cache = [];
        static $books = [];
        if ($id <= 0) return [];
        if (array_key_exists($id, $cache)) return $cache[$id];
        $cache[$id] = [];
        try {
            $st = db()->prepare('SELECT novel_id, status, created_at FROM novel_chapters WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) return $cache[$id];
            $novelId = (int)$row['novel_id'];
            if ((string)$row['status'] !== 'published') {
                return $cache[$id] = ['novel_id' => $novelId, 'no' => 0];
            }
            if (!isset($books[$novelId])) {
                $books[$novelId] = true;
                $seq = db()->prepare(
                    "SELECT id, created_at FROM novel_chapters WHERE novel_id = ? AND status = 'published'
                     ORDER BY sort_order ASC, id ASC"
                );
                $seq->execute([$novelId]);
                // 逐行取，边取边记行号：不把整本 fetchAll 压进 PHP 数组
                $i = 0;
                while (($r = $seq->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $cid = (int)$r['id'];
                    $cache[$cid] = [
                        'novel_id' => $novelId,
                        'no'       => ++$i,
                        'date'     => gblog_compact_date($r['created_at'] ?? ''),
                    ];
                }
            }
            if (!array_key_exists($id, $cache)) {
                $cache[$id] = ['novel_id' => $novelId, 'no' => 0];
            }
        } catch (Throwable $e) {
        }
        return $cache[$id];
    }
}

if (!function_exists('gblog_chapter_id_by_position')) {
    /**
     * 按「作品 id + 本书内第几章」反查章节全局 id（仅已发布）。
     * 供 sitemap 等只持有 novel_id+no 时补出发布日期（s2 形态需要）。
     */
    function gblog_chapter_id_by_position(int $novelId, int $no): int
    {
        if ($novelId <= 0 || $no <= 0) return 0;
        try {
            $st = db()->prepare(
                "SELECT id FROM novel_chapters WHERE novel_id = ? AND status = 'published'
                 ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET ?"
            );
            $st->bindValue(1, $novelId, PDO::PARAM_INT);
            $st->bindValue(2, $no - 1, PDO::PARAM_INT);
            $st->execute();
            return (int)($st->fetchColumn() ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/* ------------------------------------------------------------------ *
 * URL 生成
 * ------------------------------------------------------------------ */

if (!function_exists('gblog_content_url')) {
    /**
     * 内容页 URL（文章 / 歌曲共用）。
     * $row 至少含 id；可含 site_type / site_id / created_at。
     */
    function gblog_content_url(array $row): string
    {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) return '';

        $type  = (string)($row['site_type'] ?? '');
        $slug  = (string)($row['site_slug'] ?? '');
        if (($type === '' || $slug === '') && !empty($row['site_id'])) {
            $r = gblog_site_row((int)$row['site_id'], '');
            if ($type === '') $type = (string)($r['type'] ?? '');
            if ($slug === '') $slug = (string)($r['slug'] ?? '');
        }
        $style = gblog_permalink_style();
        $date  = gblog_compact_date($row['created_at'] ?? '');

        // 只带 id 的列表行：一次反查同时补齐栏目 type / slug 与发布日期。
        // 缺 type 会退化成 post 前缀；s2 缺日期会退化成同页形态不一致的纯 id 链接。
        $meta = [];
        if ($type === '' || $slug === '' || ($style === 's2' && $date === '')) {
            $meta = gblog_content_meta_by_id($id);
        }
        if ($type === '') $type = (string)($meta['type'] ?? '');
        if ($slug === '') $slug = (string)($meta['slug'] ?? '');
        // 内容页前缀 = 所属栏目 slug（自建栏目用自己的 slug，如 ganwu/101.html）。
        // 内置栏目 slug 恰是 post/music/soft/image/novel，与旧 type 前缀一致，零变化。
        // slug 缺失、含中文、或占用 rewrite 专用规则名时回退 type 前缀，保证链接仍可用。
        $pfx = ($slug !== '' && gblog_site_slug_is_url_safe($slug)) ? $slug : gblog_type_prefix($type);

        if ($style === 's3') return '/' . $pfx . '-' . $id . '.html';
        if ($style === 's2') {
            if ($date === '') $date = gblog_compact_date($meta['created_at'] ?? '');
            // 数据库里确实没有发布日期时才退化成 s1 形态
            if ($date === '') return '/' . $pfx . '/' . $id . '.html';
            return '/' . $pfx . '/' . $date . $id . '.html';
        }
        return '/' . $pfx . '/' . $id . '.html';
    }
}

if (!function_exists('gblog_url')) {
    function gblog_url(string $do, array $params = []): string
    {
        if (!gblog_permalink_enabled()) {
            // 回退形态：只保留真正会被控制器读取的参数，不把 site_id / created_at
            // 等内部字段拼进 URL（既冗长又暴露数据）。
            $keep = array_flip(['id', 'q', 'cat', 'page', 'y', 'm', 'play', 'frag', 'type']);
            $out  = array_intersect_key($params, $keep);
            return 'index.php?do=' . rawurlencode($do) . (count($out) ? '&' . http_build_query($out) : '');
        }

        switch ($do) {
            case 'post': {
                // 文章按全局 id 定位（posts.slug 已移除）；
                // 不带站点信息时由 gblog_content_url() 按 id 反查真实栏目，
                // 切勿默认 article，否则软件/图片/小说都会生成 post-{id}.html。
                $url = gblog_content_url($params);
                return $url !== '' ? $url : 'index.php?do=post&id=' . (int)($params['id'] ?? 0);
            }

            case 'song': {
                $id  = (int)($params['id'] ?? 0);
                $row = $params;
                if ($id > 0) {
                    $extra = gblog_song_fields_by_id($id);
                    if ($extra) $row = array_merge($row, $extra);
                }
                if (empty($row['site_type'])) $row['site_type'] = 'song';
                $url = gblog_content_url($row);
                return $url !== '' ? $url : 'index.php?do=song&id=' . $id;
            }

            case 'site': {
                $id   = (int)($params['id'] ?? 0);
                $slug = (string)($params['slug'] ?? '');
                $row  = gblog_site_row($id, $slug);
                if (!$row) return 'index.php?do=site&slug=' . rawurlencode($slug);
                $id   = (int)$row['id'];
                $type = (string)$row['type'];
                $rowSlug = (string)($row['slug'] ?? '');
                // 栏目首页地址 = 栏目 slug（用户可控）：/ganwu.html。内置栏目 slug 与
                // type 前缀同名，自然得到 /post.html 等缩写顶级页。两种情况回退：
                //  a) slug 不安全（中文 / 保留名 / 纯数字）；
                //  b) slug 恰是某个 type 前缀，但本栏目不是该 type 第一个启用栏目——
                //     /{前缀}.html 的 rewrite 固定指向 type 第一个栏目，会产生错位。
                // 回退规则沿用旧约定：type 第一个栏目用缩写页，其它用 /column-{id}.html。
                if (gblog_site_slug_is_url_safe($rowSlug)) {
                    $pmap  = gblog_type_prefix_map(); // 前缀 => type
                    $pType = $pmap[strtolower($rowSlug)] ?? '';
                    if ($pType === '' || ($pType === $type && $id === gblog_first_site_id_by_type($type))) {
                        return '/' . $rowSlug . '.html';
                    }
                }
                return $id === gblog_first_site_id_by_type($type)
                    ? '/' . gblog_type_prefix($type) . '.html'
                    : '/column-' . $id . '.html';
            }

            case 'category': {
                // 分类按 id 定位（categories.slug 已移除）
                $id = (int)($params['id'] ?? 0);
                return $id > 0 ? '/category-' . $id . '.html' : 'index.php?do=category&id=' . $id;
            }

            case 'tag': {
                $id = (int)($params['id'] ?? 0);
                return $id > 0 ? '/tag-' . $id . '.html' : 'index.php?do=tag&id=' . $id;
            }

            case 'chapter': {
                $id = (int)($params['id'] ?? 0);
                if ($id <= 0 && empty($params['novel_id']) && empty($params['no'])) {
                    return 'index.php?do=chapter';
                }
                // 章节页 URL 跟随伪静态形态（与内容页保持一致）：
                //   s1  → /novel/{作品id}-{章序}.html（栏目目录式，与 /post/101.html 同款）
                //   s2  → /novel/{Ymd}-{作品id}-{章序}.html（Ymd 取章节发布日期，与 /post/Ymd+id.html 同款）
                //   s3  → /novel-{作品id}-{章序}.html（顶级栏目标识式，与 /post-101.html 同款）
                // 旧 /chapter-{全局id}.html 仅作兜底 / 兼容。
                $style   = gblog_permalink_style();
                $novelId = (int)($params['novel_id'] ?? 0);
                $no      = (int)($params['no'] ?? 0);
                $date    = (string)($params['date'] ?? '');
                // 有 id 时优先按 id 反查：保证拿到发布日期（s2 形态需要）。
                if ($id > 0) {
                    $meta    = gblog_chapter_meta_by_id($id);
                    $novelId = (int)($meta['novel_id'] ?? $novelId);
                    $no      = (int)($meta['no'] ?? $no);
                    $date    = (string)($meta['date'] ?? $date);
                } elseif ($style === 's2' && $novelId > 0 && $no > 0 && $date === '') {
                    // sitemap 等只给 novel_id+no 时，反查章节 id 取发布日期
                    $cid  = gblog_chapter_id_by_position($novelId, $no);
                    $meta = $cid > 0 ? gblog_chapter_meta_by_id($cid) : [];
                    $date = (string)($meta['date'] ?? '');
                }
                $pfx = gblog_type_prefix('novel');
                if ($novelId > 0 && $no > 0) {
                    $identity = $novelId . '-' . $no;
                    if ($style === 's3') {
                        return '/' . $pfx . '-' . $identity . '.html';
                    }
                    if ($style === 's2') {
                        // 缺日期（异常数据）退回 s1 形态，避免同页链接形态不一致
                        if ($date === '') return '/' . $pfx . '/' . $identity . '.html';
                        return '/' . $pfx . '/' . $date . '-' . $identity . '.html';
                    }
                    return '/' . $pfx . '/' . $identity . '.html';
                }
                // 草稿或数据缺失时退回按章节全局 id 的旧形态，避免生成坏链
                return '/chapter-' . $id . '.html';
            }

            case 'songs':
            case 'archive':
            case 'search':
            case 'about':
            case 'links':
            case 'sitemap':
                return '/' . $do . '.html';

            case 'home':
                return '/';

            default:
                // 登录 / 注册 / 下载 / 点赞 / AJAX 等接口一律保持 ?do=，不参与伪静态。
                return 'index.php?do=' . rawurlencode($do) . (count($params) ? '&' . http_build_query($params) : '');
        }
    }
}

if (!function_exists('gblog_post_url')) {
    /** 文章页 URL：按全局 id 定位（posts.slug 已移除），$siteSlug 参数仅为兼容旧签名保留。 */
    function gblog_post_url($post, string $siteSlug = ''): string
    {
        $row = (array)$post;
        if ($siteSlug !== '' && empty($row['site_type'])) {
            $r = gblog_site_row(0, $siteSlug);
            if ($r) $row['site_type'] = (string)$r['type'];
        }
        return gblog_url('post', $row);
    }
}

if (!function_exists('gblog_pretty_base')) {
    /**
     * 把控制器里现成的 'index.php?do=X&slug=Y' 分页基址转换为当前形态。
     * 非 'index.php?do=' 开头的基址原样返回；cat/q 等附加参数保留为查询串。
     */
    function gblog_pretty_base(string $base): string
    {
        // 关闭伪静态时基址本来就是 index.php?do=...，原样返回，避免参数被重复追加。
        if (!gblog_permalink_enabled() || strpos($base, 'index.php?do=') !== 0) {
            return $base;
        }
        parse_str(substr($base, strlen('index.php?')), $q);
        $do = $q['do'] ?? '';
        unset($q['do'], $q['page']);
        // 栏目仍以 slug 定位，必须从附加参数中剔除，避免 gblog_url 处理后再重复拼接
        $consumed = ['slug', 'id', 'site', 'site_id', 'created_at', 'site_type', 'type'];
        $extra = [];
        foreach ($q as $k => $v) {
            if (!in_array($k, $consumed, true)) $extra[$k] = $v;
        }
        $url = gblog_url($do, $q);
        if ($extra) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($extra);
        }
        return $url;
    }
}

/* ------------------------------------------------------------------ *
 * 输出层统一改写：覆盖模板里剩余的硬编码 index.php?do= 出链 / 表单 action
 * ------------------------------------------------------------------ */

if (!function_exists('gblog_rewrite_href_dos')) {
    /** 只改写页面类路由；AJAX 接口、二进制下载、用户中心等一概不碰。 */
    function gblog_rewrite_href_dos(): array
    {
        return ['site', 'post', 'category', 'tag', 'search', 'archive',
                'about', 'links', 'sitemap', 'home', 'songs', 'song', 'chapter', 'content'];
    }
}

if (!function_exists('gblog_rootify_relative_urls')) {
    /**
     * 把页面里剩余的「相对」静态资源 / 接口地址改成站点根绝对。
     *
     * 背景：s1/s2 的 /post/101.html 会被浏览器当作目录，usr/themes/.../style.css
     * 会解析成 /post/usr/... 而 404（整页掉样式）；fetch('index.php?do=like')、
     * action="index.php?do=login" 同理会打错地址。
     * 只处理以 usr/(themes|uploads|plugins)/ 或 index.php 开头的相对地址；
     * 已经是 /xxx、http(s)://、data:、#、? 的一律不动。
     */
    function gblog_rootify_relative_urls(string $html): string
    {
        $base = gblog_web_base();
        if ($base === '') return $html;

        // 1) HTML 属性
        $html = preg_replace_callback(
            '~\b(href|src|action|poster|data-src|data-url|data-href)=(["\'])(usr/(?:themes|uploads|plugins)/[^"\']*|index\.php[^"\']*)\2~i',
            function (array $m) use ($base): string {
                return $m[1] . '=' . $m[2] . $base . $m[3] . $m[2];
            },
            $html
        ) ?? $html;

        // 2) 内联 JS / JSON 字符串：fetch('index.php?do=...')、window.location.href='index.php'
        $html = preg_replace_callback(
            '~(["\'])(usr/(?:themes|uploads|plugins)/[^"\']*|index\.php[^"\']*)\1~',
            function (array $m) use ($base): string {
                return $m[1] . $base . $m[2] . $m[1];
            },
            $html
        ) ?? $html;

        // 3) 内联 CSS：url(usr/...) / url('usr/...')
        $html = preg_replace_callback(
            '~url\((["\']?)(usr/(?:themes|uploads|plugins)/[^)"\']+)\1\)~i',
            function (array $m) use ($base): string {
                return 'url(' . $m[1] . $base . $m[2] . $m[1] . ')';
            },
            $html
        ) ?? $html;

        return $html;
    }
}

if (!function_exists('gblog_rewrite_hrefs')) {
    function gblog_rewrite_hrefs(string $html): string
    {
        // 顺序要紧：先把内容链接变成漂亮 URL，再把剩余的相对地址根绝对化。
        if (gblog_permalink_enabled()) {
            $dos = gblog_rewrite_href_dos();
            $html = preg_replace_callback(
                '~(href|action)=(["\'])index\.php\?do=([A-Za-z0-9_]+)([^"\']*)\2~i',
                function (array $m) use ($dos): string {
                    $attr  = strtolower($m[1]);
                    $quote = $m[2];
                    $do    = strtolower($m[3]);
                    if (!in_array($do, $dos, true)) return $m[0];

                    $rest = html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $frag = '';
                    if (($p = strpos($rest, '#')) !== false) {
                        $frag = substr($rest, $p);
                        $rest = substr($rest, 0, $p);
                    }
                    parse_str(ltrim($rest, '&'), $q);
                    // 后台草稿预览必须保持 ?do= 原样：改成漂亮 URL 会丢 preview 参数
                    if (isset($q['preview'])) return $m[0];

                    $consumed = ['id', 'site', 'site_id', 'created_at', 'site_type', 'type',
                                 'novel', 'novel_id', 'no'];
                    $extra = [];
                    foreach ($q as $k => $v) {
                        if (!in_array($k, $consumed, true)) $extra[$k] = $v;
                    }
                    $url = gblog_url($do, $q);
                    if ($url === '' || strpos($url, 'index.php?do=') === 0) return $m[0];
                    if ($extra) {
                        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($extra);
                    }
                    return $attr . '=' . $quote . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $frag . $quote;
                },
                $html
            ) ?? $html;
        }
        // 即便关闭伪静态也执行：用户仍可能从收藏夹访问 /post/101.html 这类多级路径 URL
        return gblog_rootify_relative_urls($html);
    }
}

if (!function_exists('gblog_maybe_start_href_rewrite')) {
    /**
     * 在入口按当前 do 决定是否挂输出缓冲改写。
     * 只挂 HTML 页面型路由，避免缓冲 JSON / XML / 文件下载等输出。
     * 关闭伪静态时也挂：仍需把相对静态资源根绝对化，以免旧链接页面掉样式。
     */
    function gblog_maybe_start_href_rewrite(string $do): void
    {
        $htmlDos = ['', 'site', 'post', 'category', 'tag', 'search', 'archive',
                    'about', 'links', 'home', 'songs', 'song', 'chapter', 'content',
                    'login', 'register', 'forgot', 'reset', 'profile'];
        if (!in_array($do, $htmlDos, true)) return;
        ob_start('gblog_rewrite_hrefs');
    }
}

/* ------------------------------------------------------------------ *
 * SEO：站点源 / 绝对地址 / canonical / 非规范 URL 301
 * ------------------------------------------------------------------ */

if (!function_exists('gblog_site_origin')) {
    /**
     * 站点源（scheme://host[:port]，无尾斜杠）。
     * 优先用后台「站点地址」；未填时按当前请求推断，保证新装站点
     * sitemap / canonical 也能输出合法绝对 URL。
     */
    function gblog_site_origin(): string
    {
        static $origin = null;
        if ($origin !== null) return $origin;
        $configured = trim((string)(function_exists('get_setting') ? get_setting('site_url', '') : ''));
        if ($configured !== '') {
            $p = parse_url($configured);
            if (is_array($p) && !empty($p['scheme']) && !empty($p['host'])
                && in_array(strtolower((string)$p['scheme']), ['http', 'https'], true)) {
                $port = isset($p['port']) ? ':' . (int)$p['port'] : '';
                return $origin = strtolower((string)$p['scheme']) . '://' . (string)$p['host'] . $port;
            }
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        // 只允许域名 / IPv6 / 端口字符，防 Host 头注入
        $host = (string)preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
        if ($host === '') $host = 'localhost';
        return $origin = ($https ? 'https://' : 'http://') . $host;
    }
}

if (!function_exists('gblog_absolute_url')) {
    /** 把出链层生成的根绝对 / 相对 index.php 地址补成完整 URL。 */
    function gblog_absolute_url(string $path): string
    {
        if ($path === '') return gblog_site_origin() . gblog_web_base();
        if (preg_match('#^https?://#i', $path)) return $path;
        $origin = gblog_site_origin();
        $base   = gblog_web_base();
        if ($path === '/') return $origin . $base;
        if ($path[0] === '/') {
            if ($base !== '/' && strpos($path, $base) === 0) return $origin . $path;
            return $origin . $base . ltrim($path, '/');
        }
        return $origin . $base . $path;
    }
}

if (!function_exists('gblog_xml_escape')) {
    /** XML 文本转义（sitemap 的 <loc> 里 & 必须写成 &amp;）。 */
    function gblog_xml_escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gblog_canonical_path')) {
    /**
     * 当前 HTML 页面的规范路径（不含域名、不含分页 / 搜索 / 锚点等杂参）。
     * 返回空串表示该页不该输出 canonical（登录、用户中心、接口等）。
     */
    function gblog_canonical_path(): string
    {
        $do  = (string)($_GET['do'] ?? '');
        $id  = (int)($_GET['id'] ?? 0);
        $d   = [];
        switch ($do) {
            case '':
            case 'home':     $d = ['home' => []]; break;
            case 'post':
                // 文章按全局 id 定位（posts.slug 已移除）
                $d = ['post' => ['id' => $id]];
                break;
            case 'song':     $d = ['song' => ['id' => $id]]; break;
            case 'chapter':  $d = ['chapter' => ['id' => $id]]; break;
            case 'site':
                if ($id <= 0 && ($_GET['type'] ?? '') !== '') {
                    $id = gblog_first_site_id_by_type((string)$_GET['type']);
                }
                if ($id <= 0 && ($_GET['slug'] ?? '') !== '') {
                    $id = gblog_site_id_by_slug((string)$_GET['slug']);
                }
                $d = $id > 0 ? ['site' => ['id' => $id]] : ['home' => []];
                break;
            case 'category': $d = ['category' => ['id' => $id]]; break;
            case 'tag':      $d = ['tag' => ['id' => $id]]; break;
            case 'about': case 'links': case 'archive': case 'search':
            case 'songs': case 'sitemap':
                $d = [$do => []]; break;
            default: return '';
        }
        $key   = (string)array_key_first($d);
        $path  = gblog_url($key, $d[$key]);
        if ($path === '') return '';
        // 分页页保留页码（Google 建议分页 canonical 指向自身），其余参数一律丢弃
        $paged = ['', 'home', 'site', 'category', 'tag', 'archive', 'songs'];
        $page  = (int)($_GET['page'] ?? 0);
        if ($page > 1 && in_array($do, $paged, true)) {
            $path .= (strpos($path, '?') === false ? '?' : '&') . 'page=' . $page;
        }
        return $path;
    }
}

if (!function_exists('gblog_canonical_url')) {
    /** 当前页面 canonical 的绝对 URL；不该输出时返回空串。 */
    function gblog_canonical_url(): string
    {
        if (http_response_code() === 404) return '';
        $path = gblog_canonical_path();
        return $path === '' ? '' : gblog_absolute_url($path);
    }
}

if (!function_exists('gblog_should_noindex')) {
    /** 登录 / 注册 / 用户私有页 / 站内搜索结果 / 404 不该进索引。 */
    function gblog_should_noindex(): bool
    {
        if (http_response_code() === 404) return true;
        if (!empty($_GET['preview'])) return true;   // 后台草稿预览页
        $do = (string)($_GET['do'] ?? '');
        return in_array($do, ['login', 'register', 'forgot', 'reset', 'profile',
                             'like', 'download', 'search'], true);
    }
}

if (!function_exists('gblog_redirect_noncanonical')) {
    /**
     * 重复 URL 规范化：前导零章序 / 内容 id → 301；章节三种形态互转（s1/s2/s3 分隔与日期）→ 301 到当前形态规范地址；
     * 旧 chapter-N.html → 301 到新章节 URL。只处理 GET 且漂亮 .html 页面，JSON / 下载 / 表单提交一律不碰。
     */
    function gblog_redirect_noncanonical(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;
        if (!gblog_permalink_enabled()) return;
        $uri   = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path  = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        $query = (string)(parse_url($uri, PHP_URL_QUERY) ?? '');
        if ($path === '' || !preg_match('#\.html$#', $path)) return;
        $b = preg_quote(gblog_web_base(), '#');
        $target = '';

        if (preg_match('#^' . $b . 'novel/([0-9]+)-([0-9]+)-([0-9]+)\.html$#', $path, $m)
            || preg_match('#^' . $b . 'novel-([0-9]+)-([0-9]+)-([0-9]+)\.html$#', $path, $m)) {
            // s2 形态：带发布日期 Ymd-novel-no（兼容误写成“-”分隔）；归一前导零 + 形态切换。
            $novel = (int)$m[2];
            $no    = (int)$m[3];
            if ($novel > 0 && $no > 0) {
                $canon   = gblog_url('chapter', ['novel_id' => $novel, 'no' => $no]);
                $normReq = '/' . ltrim(preg_replace('#^' . $b . '#', '', $path), '/');
                if ($normReq !== $canon) {
                    $target = $canon;
                }
            }
        } elseif (preg_match('#^' . $b . 'novel-([0-9]+)-([0-9]+)\.html$#', $path, $m)
            || preg_match('#^' . $b . 'novel/([0-9]+)-([0-9]+)\.html$#', $path, $m)) {
            // s1 / s3：只有 novel-no 两段；归一前导零 + 形态切换（“-”与“/”）→ 当前形态规范地址。
            $novel = (int)$m[1];
            $no    = (int)$m[2];
            if ($novel > 0 && $no > 0) {
                $canon   = gblog_url('chapter', ['novel_id' => $novel, 'no' => $no]);
                $normReq = '/' . ltrim(preg_replace('#^' . $b . '#', '', $path), '/');
                if ($normReq !== $canon) {
                    $target = $canon;
                }
            }
        } elseif (preg_match('#^' . $b . 'chapter-([0-9]+)\.html$#', $path, $m)) {
            $cid  = (int)$m[1];
            $meta = gblog_chapter_meta_by_id($cid);
            if ((int)($meta['no'] ?? 0) > 0) {
                $target = gblog_url('chapter', [
                    'id' => $cid, 'novel_id' => (int)$meta['novel_id'], 'no' => (int)$meta['no'],
                ]);
            }
        } elseif (preg_match('#^' . $b . '([a-zA-Z0-9_-]+)-([0-9]+)\.html$#', $path, $m)) {
            // 前缀为任意栏目 slug（自建栏目如 ganwu）。刻意不做「前缀归属校验」：
            // 内容 id 全局唯一，前缀只作可读性；栏目改 slug 后旧链接必须仍能访问，
            // 重复 URL 由 canonical 指向新前缀，用 301 强跳会打断外链与收藏夹。
            // 这里只归一前导零 id，不改前缀、不改形态。
            $n = (string)(int)$m[2];
            if ($n !== $m[2]) $target = '/' . $m[1] . '-' . $n . '.html';
        } elseif (preg_match('#^' . $b . '([a-zA-Z0-9_-]+)/([0-9]+)\.html$#', $path, $m)) {
            // s1 / s2 共用：s2 的 8 位日期+id 同样是纯数字串，保持原样只归一前导零。
            $n = (string)(int)$m[2];
            if ($n !== $m[2]) $target = '/' . $m[1] . '/' . $n . '.html';
        }

        if ($target === '') return;
        $location = gblog_root_absolute($target);
        if ($query !== '') $location .= (strpos($location, '?') === false ? '?' : '&') . $query;
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $location);
        header('Cache-Control: public, max-age=86400');
        exit;
    }
}
