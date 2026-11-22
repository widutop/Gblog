<?php

if (!defined('GBLOG_ROOT')) exit;

function gblog_item_normalize(array $row): array
{
    $isSong = !empty($row['is_song']) || array_key_exists('audio_url', $row) || array_key_exists('artist', $row);
    if ($isSong) {
        $row['is_song'] = true;
        $row['url']       = (string)($row['url'] ?? '') ?: 'index.php?do=song&id=' . (int)$row['id'];
        $row['author']    = (string)($row['artist'] ?? '');
        $row['excerpt']   = (string)($row['note'] ?? '');
        $row['cover']     = (string)($row['cover_url'] ?? '');
        $row['view_count'] = (int)($row['play_count'] ?? 0);
        $row['like_count'] = 0;
        $row['type']      = 'song';
    } else {
        $row['url']  = (string)($row['url'] ?? '') ?: 'index.php?do=post&id=' . (int)($row['id'] ?? 0);
        $row['type'] = (string)($row['type'] ?? gblog_current_type() ?: 'article');
    }
    $row['author'] = (string)($row['author'] ?? '') ?: '匿名';
    return $row;
}

// 当前详情项：优先取 gblog_current_item，否则按 do 反查（含章节）
function gblog_current_item(): ?array
{
    if (!empty($GLOBALS['gblog_current_item']) && is_array($GLOBALS['gblog_current_item'])) {
        return gblog_item_normalize($GLOBALS['gblog_current_item']);
    }
    static $cached = null;
    if ($cached !== null) return $cached ?: null;
    $do = (string)($_GET['do'] ?? '');
    $row = null;
    try {
        if ($do === 'post' && ($_GET['id'] ?? '') !== '' && function_exists('get_post_by_id')) {
            $row = get_post_by_id((int)$_GET['id'], true);
        } elseif ($do === 'song' && ($_GET['id'] ?? '') !== '' && function_exists('db')) {
            $st = db()->prepare("SELECT * FROM songs WHERE id = ? AND site_id = ? AND status = 'published' LIMIT 1");
            $st->execute([(int)$_GET['id'], function_exists('gblog_music_site_id') ? gblog_music_site_id() : 0]);
            $row = $st->fetch() ?: null;
        } elseif ($do === 'chapter' && ($_GET['id'] ?? '') !== '' && function_exists('db')) {
            $st = db()->prepare('SELECT * FROM novel_chapters WHERE id = ? LIMIT 1');
            $st->execute([(int)$_GET['id']]);
            $ch = $st->fetch() ?: null;
            if ($ch) {
                $ch['url'] = 'index.php?do=chapter&id=' . (int)$ch['id'];
                $ch['type'] = 'novel';
                $row = $ch;
            }
        }
    } catch (Throwable $e) {
        $row = null;
    }
    $cached = $row ? gblog_item_normalize($row) : [];
    return $cached ?: null;
}

function get_item(): ?array
{
    return gblog_current_item();
}

function get_title(): string
{
    return (string)(gblog_current_item()['title'] ?? '');
}
function the_title(): void
{
    echo e(get_title());
}

// 正文富 HTML：唯一不转义的 the_*，调用方不要再套 e()
function get_content(): string
{
    return (string)(gblog_current_item()['content'] ?? '');
}
function the_content(): void
{
    echo get_content();
}

function get_excerpt(int $len = 120): string
{
    $it = gblog_current_item();
    $text = (string)($it['excerpt'] ?? '');
    if ($text === '' && isset($it['content'])) $text = (string)$it['content'];
    return excerpt($text, $len);
}
function the_excerpt(int $len = 120): void
{
    echo e(get_excerpt($len));
}

function get_url(): string
{
    return (string)(gblog_current_item()['url'] ?? '');
}
function the_url(): void
{
    echo e(get_url());
}

function get_cover(): string
{
    return (string)(gblog_current_item()['cover'] ?? '');
}
function the_cover(): void
{
    echo e(get_cover());
}

function get_author(): string
{
    return (string)(gblog_current_item()['author'] ?? '匿名');
}
function the_author(): void
{
    echo e(get_author());
}

function get_date(string $fmt = ''): string
{
    $it = gblog_current_item();
    $raw = (string)($it['created_at'] ?? '');
    if ($raw === '') return '';
    if ($fmt === '' && function_exists('fmt_date')) return fmt_date($raw);
    $ts = function_exists('gblog_parse_time') ? gblog_parse_time($raw) : strtotime($raw);
    return $ts > 0 ? date($fmt, $ts) : '';
}
function the_date(string $fmt = ''): void
{
    echo e(get_date($fmt));
}

function get_category_name(): string
{
    $it = gblog_current_item();
    if (isset($it['category_name'])) return (string)$it['category_name'];
    $cid = (int)($it['category_id'] ?? 0);
    if (!$cid || $cid === 0 || !function_exists('db')) return '未分类';
    try {
        $st = db()->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
        $st->execute([$cid]);
        return (string)($st->fetchColumn() ?: '未分类');
    } catch (Throwable $e) {
        return '未分类';
    }
}
function the_category(): void
{
    echo e(get_category_name());
}

function get_views(): int
{
    return (int)(gblog_current_item()['view_count'] ?? 0);
}
function the_views(): void
{
    echo (string)get_views();
}

function get_likes(): int
{
    return (int)(gblog_current_item()['like_count'] ?? 0);
}
function the_likes(): void
{
    echo (string)get_likes();
}

function get_download_info(): array
{
    $it = gblog_current_item();
    return [
        'level' => (string)($it['download_level'] ?? 'free'),
        'price' => (int)($it['download_price'] ?? 0),
        'url'   => (string)($it['download_url'] ?? ''),
        'size'  => (string)($it['file_size'] ?? ''),
        'count' => (int)($it['download_count'] ?? 0),
    ];
}

function the_download(): void
{
    $f = gblog_theme_part('template/parts/download-box.php');
    if ($f !== '') require $f;
}

function the_loop(): array
{
    $loop = $GLOBALS['gblog_loop'] ?? [];
    return is_array($loop) ? $loop : [];
}

function post_card(array $item): void
{
    $f = gblog_theme_part('template/post-card.php');
    if ($f === '') return;
    $__prev = $GLOBALS['gblog_current_item'] ?? null;
    $GLOBALS['gblog_current_item'] = $item;
    require $f;
    if ($__prev === null) unset($GLOBALS['gblog_current_item']);
    else $GLOBALS['gblog_current_item'] = $__prev;
}

