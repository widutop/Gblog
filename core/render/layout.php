<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }

function gblog_layout_catalog(): array
{
    $c = [
        'aggregate' => [
            'sections'      => '分区聚合（现有首页）',
            'stream'        => '混排信息流',
            'software_list' => '软件专题',
        ],
        'home' => [
            'article_list' => '通用列表',
            'novel_list'   => '小说列表',
            'music_list'   => '音乐列表',
            'image_list'   => '图片列表',
        ],
        'list' => [
            'article_category'  => '通用分类',
            'software_category' => '软件分类',
            'novel_category'    => '小说分类',
            'music_category'    => '音乐分类',
            'image_category'    => '图片分类',
        ],
        'detail' => [
            'article_content'  => '文章内容',
            'software_content' => '软件内容',
            'song_content'     => '歌曲内容',
            'image_content'    => '图片内容',
            'chapter_content'  => '内容章节',
        ],
    ];
    if (function_exists('apply_filters')) {
        $extra = apply_filters('layout_catalog', $c);
        if (is_array($extra)) {
            foreach ($c as $role => $items) {
                if (!isset($extra[$role]) || !is_array($extra[$role])) continue;
                foreach ($extra[$role] as $id => $name) {
                    if (!is_string($id) || !is_string($name)) continue;
                    if (!preg_match('/^[a-zA-Z0-9_.-]{1,60}$/', $id) || strpos($id, '..') !== false) continue;
                    $c[$role][$id] = $name;
                }
            }
        }
    }
    foreach (gblog_layout_scan_custom() as $role => $items) {
        foreach ($items as $id => $name) {
            $c[$role][$id] = $name;
        }
    }
    return $c;
}

function gblog_layout_recommended(): array
{
    return [
        'article'  => ['aggregate' => 'sections', 'home' => 'article_list',  'list' => 'article_category',  'detail' => 'article_content'],
        'software' => ['aggregate' => 'sections', 'home' => 'article_list',   'list' => 'software_category', 'detail' => 'software_content'],
        'song'     => ['aggregate' => 'sections', 'home' => 'music_list',    'list' => 'music_category',    'detail' => 'song_content'],
        'image'    => ['aggregate' => 'sections', 'home' => 'image_list',    'list' => 'image_category',    'detail' => 'image_content'],
        'novel'    => ['aggregate' => 'sections', 'home' => 'novel_list',    'list' => 'novel_category',    'detail' => 'chapter_content'],
    ];
}

function gblog_layout_legacy_id(string $role, string $slug, string $id): string
{
    if ($role === 'detail' && $id === 'article') {
        $bySlug = ['soft' => 'software_content', 'image' => 'image_content'];
        return $bySlug[$slug] ?? 'article_content';
    }
    if ($role === 'detail' && $id === 'novel') return 'chapter_content';
    if ($role === 'list' && $id === 'card' && $slug === 'soft') return 'software_category';
    $map = [
        'home'   => ['card' => 'article_list', 'song' => 'music_list', 'masonry' => 'image_list', 'grid' => 'novel_list'],
        'list'   => ['card' => 'article_category', 'song' => 'music_category', 'masonry' => 'image_category', 'grid' => 'novel_category'],
        'detail' => ['song' => 'song_content'],
    ];
    return $map[$role][$id] ?? $id;
}

// 全新安装不会写入 layout:* 设置；此时按栏目 type 套用内置推荐版式，
// 否则歌曲列表等专用模板永远选不到（页面显示「该栏目暂无内容」）。禁按 slug 硬编码。
function gblog_layout_default(string $role, string $slug): string
{
    if ($slug === '' || $slug === '_home' || !in_array($role, ['home', 'list', 'detail'], true)) return '';
    if (!function_exists('db')) return '';
    static $types = [];
    if (!array_key_exists($slug, $types)) {
        $types[$slug] = '';
        try {
            $st = db()->prepare('SELECT type FROM sites WHERE slug = ? LIMIT 1');
            $st->execute([$slug]);
            $types[$slug] = (string)$st->fetchColumn();
        } catch (Throwable $e) {}
    }
    $rec = gblog_layout_recommended()[$types[$slug]] ?? [];
    return (string)($rec[$role] ?? '');
}

function gblog_layout_selected(string $role, string $slug): string
{
    if (!function_exists('get_setting')) return '';
    $id = (string)get_setting('layout:' . $role . ':' . $slug, '');
    if ($id === '') $id = gblog_layout_default($role, $slug);
    if ($id === '') return '';
    $catalog = gblog_layout_catalog();
    if (isset($catalog[$role][$id])) return $id;
    $id = gblog_layout_legacy_id($role, $slug, $id);
    return isset($catalog[$role][$id]) ? $id : '';
}

function gblog_layout_builtin_map(): array
{
    return [
        'aggregate' => ['sections' => 'index', 'stream' => 'index-stream', 'software_list' => 'index-software'],
        'home'      => ['article_list' => 'home', 'novel_list' => 'home-novel', 'music_list' => 'home-music', 'image_list' => 'home-image'],
        'list'      => ['article_category' => 'list', 'software_category' => 'list-software', 'novel_category' => 'list-novel', 'music_category' => 'list-music', 'image_category' => 'list-image'],
        'detail'    => ['article_content' => 'single', 'software_content' => 'single-software', 'song_content' => 'single-song', 'image_content' => 'single-image', 'chapter_content' => 'single-novel'],
    ];
}

function gblog_layout_template_name(string $role, string $id): string
{
    $map = gblog_layout_builtin_map();
    if (isset($map[$role][$id])) return $map[$role][$id];
    $prefix = ['aggregate' => 'index', 'home' => 'home', 'list' => 'list', 'detail' => 'single'][$role] ?? $role;
    return ($id === $prefix || $id === $prefix . '_default') ? $prefix : $prefix . '-' . $id;
}

function gblog_page_views(): array
{
    return [
        'page'    => '通用单页（关于等）',
        '404'     => '404 页面',
        'links'   => '友情链接页',
        'archive' => '文章归档页',
        'tag'     => '标签页',
        'search'  => '搜索页',
    ];
}

function gblog_layout_role_names(): array
{
    return ['aggregate' => '栏目模板', 'home' => '列表页', 'list' => '分类页', 'detail' => '内容页'];
}

// 注解取值：整行取到后剥掉块注释尾巴（`*/`），名字中间的 `/` 必须保留（如「详情页·小说/章节」）。
function gblog_tpl_meta_trim(string $v): string
{
    return (string)preg_replace('#\s*(?:\*/|\*|/)\s*$#', '', trim($v));
}

// 模板元数据：Template Name + 可用页面视图。视图来源 = 文件头 View: 声明，缺声明按文件名推断（search.php / search-xxx.php → search）。
// 扫描走 gblog_tpl_index()（递归、按文件名识别），所以模板放在 template/ 下任何分类子目录都能被后台认到；parts/ 里的零件不参与。
function gblog_scan_template_meta(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    if (!defined('THEMES_DIR') || !function_exists('gblog_tpl_index')) return $cache;
    $views = array_map('strval', array_keys(gblog_page_views()));
    foreach (gblog_tpl_index() as $base => $hit) {
        if (!empty($hit['part'])) continue;
        $src  = @file_get_contents($hit['abs'], false, null, 0, 2048);
        $src  = is_string($src) ? $src : '';
        $name = preg_match('/Template\s+Name:\s*([^\r\n]+)/', $src, $m) ? gblog_tpl_meta_trim($m[1]) : '';
        $decl = preg_match('/View:\s*([^\r\n]+)/', $src, $m) ? strtolower(gblog_tpl_meta_trim($m[1])) : '';
        $ctx  = preg_match('/Context:\s*([^\r\n]+)/', $src, $m) ? gblog_tpl_meta_trim($m[1]) : '';
        $used = [];
        $from = '';
        if ($decl !== '') {
            foreach (preg_split('/[\s,]+/', $decl) as $v) {
                if (in_array($v, $views, true)) $used[] = $v;
            }
            $from = $used ? 'view' : 'invalid';
        } else {
            foreach ($views as $v) {
                if ($base === $v || strpos($base, $v . '-') === 0) {
                    $used = [$v];
                    $from = 'file';
                    break;
                }
            }
        }
        $cache[$base] = [
            'name'  => $name !== '' ? $name : $base,
            'views' => array_values(array_unique($used)),
            'from'  => $from,
            'ctx'   => $ctx,
            'theme' => $hit['theme'],
            'dir'   => gblog_tpl_dir_label($base),
        ];
    }
    return $cache;
}

// 文件名(无 .php) => 模板名（Template Name 注解优先，缺省用文件名）。
function gblog_scan_templates(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (gblog_scan_template_meta() as $base => $meta) {
        $cache[$base] = $meta['name'];
    }
    return $cache;
}

// 某页面视图的可选模板：只收声明或命名匹配该视图的，结构模板与页架不会混进来。
function gblog_page_templates_for(string $view): array
{
    $out = [];
    foreach (gblog_scan_template_meta() as $base => $meta) {
        if (in_array($view, $meta['views'], true)) $out[$base] = $meta['name'];
    }
    return $out;
}

function gblog_page_template_matches(string $base, string $view): bool
{
    $meta = gblog_scan_template_meta();
    return isset($meta[$base]) && in_array($view, $meta[$base]['views'], true);
}

function gblog_layout_scan_custom(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache   = [];
    $mapped  = [];
    foreach (gblog_layout_builtin_map() as $items) {
        foreach ($items as $tpl) $mapped[$tpl] = true;
    }
    $prefixes = ['aggregate' => 'index', 'home' => 'home', 'list' => 'list', 'detail' => 'single'];
    foreach (gblog_scan_templates() as $base => $label) {
        if (isset($mapped[$base])) continue;
        foreach ($prefixes as $role => $prefix) {
            if (strpos($base, $prefix . '-') !== 0) continue;
            $id = substr($base, strlen($prefix) + 1);
            if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $id)) continue;
            $cache[$role][$id] = $label;
        }
    }
    return $cache;
}

function gblog_page_template_selected(string $view): string
{
    if (!isset(gblog_page_views()[$view]) || !function_exists('get_setting')) return '';
    $id = (string)get_setting('page_template:' . $view, '');
    if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $id)) return '';
    return gblog_page_template_matches($id, $view) ? $id : '';
}

function gblog_page_template_file(string $view): ?string
{
    $id = gblog_page_template_selected($view);
    if ($id === '') return null;
    $file = function_exists('gblog_tpl_path') ? gblog_tpl_path($id) : '';
    return ($file !== '' && is_file($file)) ? $file : null;
}

function gblog_layout(string $role, string $slug, array $site = []): ?string
{
    $catalog = gblog_layout_catalog();
    if (!isset($catalog[$role])) return null;
    $id = gblog_layout_selected($role, $slug);
    if ($id === '' || !isset($catalog[$role][$id])) return null;
    if (strpos($id, '..') !== false || !preg_match('/^[a-zA-Z0-9_.-]{1,60}$/', $id)) return null;

    $name = gblog_layout_template_name($role, $id);
    if ($role === 'aggregate' && $slug !== '_home' && in_array($name, ['index', 'index-software'], true)) {
        $name = 'site-aggregate';
    }
    $file = function_exists('gblog_tpl_path') ? gblog_tpl_path($name) : '';
    if ($file === '') $file = null;
    if (function_exists('apply_filters')) {
        $file = apply_filters('layout_file', $file, $role, $id, $slug);
    }
    return (is_string($file) && is_file($file)) ? $file : null;
}

// 后台手填容错：接受「版式 ID」「模板文件名」「Template Name 显示名」「下拉显示名」四种写法，统一解析成版式 ID。
// 目录无关：把文件放进分类子目录、改子目录名都不影响——这里只认文件名与文件头声明。
function gblog_layout_id_from_input(string $role, string $input): string
{
    $input = trim($input);
    if ($input === '') return '';
    $catalog = gblog_layout_catalog();
    if (isset($catalog[$role][$input])) return $input;           // ① 版式 ID 原样

    $map    = gblog_layout_builtin_map()[$role] ?? [];
    $prefix = ['aggregate' => 'index', 'home' => 'home', 'list' => 'list', 'detail' => 'single'][$role] ?? $role;

    // 文件名 → 版式 ID：内置映射反查（list-software → software_category），再按「前缀-后缀」规则兜底自定义版式
    $toId = function (string $base) use ($role, $catalog, $map, $prefix): string {
        foreach ($map as $id => $tpl) {
            if ($tpl === $base && isset($catalog[$role][$id])) return $id;
        }
        if (strpos($base, $prefix . '-') === 0) {
            $id = substr($base, strlen($prefix) + 1);
            if (isset($catalog[$role][$id])) return $id;
        }
        if ($base === $prefix) {
            foreach ($map as $id => $tpl) {
                if ($tpl === $base && isset($catalog[$role][$id])) return $id;
            }
        }
        return '';
    };

    $name = $input;
    if (substr($name, -4) === '.php') $name = substr($name, 0, -4);
    if (strpos($name, '/') !== false || strpos($name, '\\') !== false) $name = (string)basename(str_replace('\\', '/', $name));
    $hit = $toId($name);
    if ($hit !== '') return $hit;                                 // ② 文件名

    foreach (gblog_scan_template_meta() as $base => $meta) {      // ③ 模板文件头 Template Name
        if ($meta['name'] !== $input) continue;
        $hit = $toId($base);
        if ($hit !== '') return $hit;
        break;
    }
    foreach ($catalog[$role] as $id => $label) {                  // ④ 下拉显示名
        if ($label === $input) return $id;
    }
    return $input;                                                // 交给调用方判定失败
}

// 页面模板手填容错：接受模板 ID / 文件名 / Template Name 显示名。
function gblog_page_template_from_input(string $input): string
{
    $input = trim($input);
    if ($input === '') return '';
    $scan = gblog_scan_templates();
    $id   = $input;
    if (substr($id, -4) === '.php') $id = substr($id, 0, -4);
    if (strpos($id, '/') !== false) $id = (string)basename($id);
    if (isset($scan[$id])) return $id;
    foreach ($scan as $base => $label) {
        if ($label === $input) return $base;
    }
    return $id;
}

function gblog_layout_attr(): string
{
    $ctx    = function_exists('page_context') ? page_context() : [];
    $isHome = !empty($ctx['is_home']);
    $slug   = $isHome ? '_home' : (string)($ctx['site']['slug'] ?? 'post');
    $role   = ($isHome || (string)($ctx['home_mode'] ?? 'default') === 'aggregate')
        ? 'aggregate' : (string)($ctx['view'] ?? 'page');

    if (!$isHome && (string)($_GET['do'] ?? '') === 'category') {
        // 分类按 id 定位（categories.slug 已移除）：反查所属栏目，版式键仍是栏目 slug
        $catId = (int)($_GET['id'] ?? 0);
        if ($catId > 0 && function_exists('db')) {
            try {
                $st = db()->prepare('SELECT s.slug FROM categories c JOIN sites s ON s.id = c.site_id WHERE c.id = ? LIMIT 1');
                $st->execute([$catId]);
                $v = $st->fetchColumn();
                if (is_string($v) && $v !== '') $slug = $v;
            } catch (Throwable $e) {
            }
        }
    }

    if (!in_array($role, ['aggregate', 'home', 'list', 'detail'], true)) return '';
    $id = gblog_layout_selected($role, $slug);
    return ($id !== '' && strpos($id, '..') === false) ? $id : 'default';
}
