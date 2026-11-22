<?php
/* 友链与广告 —— 友情链接、链接分类、广告位。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function get_ads(): array
{
    return db()->query("SELECT * FROM ads ORDER BY id ASC")->fetchAll();
}

function save_ad(string $adKey, string $code, int $enabled): void
{
    $stmt = db()->prepare("UPDATE ads SET code = ?, enabled = ? WHERE ad_key = ?");
    $stmt->execute([$code !== '' ? $code : null, $enabled, $adKey]);
}

function render_ad(string $adKey): string
{
    $stmt = db()->prepare("SELECT code, enabled FROM ads WHERE ad_key = ? LIMIT 1");
    $stmt->execute([$adKey]);
    $ad = $stmt->fetch();
    if (!$ad || (int)$ad['enabled'] !== 1 || empty($ad['code'])) {
        return '';
    }
    return '<div class="ad-widget">' . $ad['code'] . '</div>';
}

function get_links(): array
{
    return db()->query("SELECT l.*, COALESCE(c.name, '未分类') AS category_name FROM links l LEFT JOIN link_categories c ON c.id = l.category_id ORDER BY c.sort_order ASC, c.id ASC, l.sort_order ASC, l.id ASC")->fetchAll();
}

function get_link_categories(): array
{
    return db()->query('SELECT c.*, COUNT(l.id) AS link_count FROM link_categories c LEFT JOIN links l ON l.category_id = c.id GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC')->fetchAll();
}

function get_link_groups(): array
{
    $groups = get_link_categories();
    foreach ($groups as &$group) $group['links'] = [];
    unset($group);
    $index = [];
    foreach ($groups as $i => $group) $index[(int)$group['id']] = $i;
    foreach (get_links() as $link) {
        $categoryId = (int)($link['category_id'] ?? 0);
        if (isset($index[$categoryId])) $groups[$index[$categoryId]]['links'][] = $link;
    }
    return array_values($groups);
}

function save_link_category(string $name, int $sort, int $id = 0): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('分类名称不能为空且不能超过100个字符。');
    $pdo = db();
    if ($id > 0) {
        $pdo->prepare('UPDATE link_categories SET name = ?, sort_order = ? WHERE id = ?')->execute([$name, $sort, $id]);
    } else {
        $pdo->prepare('INSERT INTO link_categories (name, sort_order) VALUES (?, ?)')->execute([$name, $sort]);
    }
}

function delete_link_category(int $id): bool
{
    if ($id <= 1) return false;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE links SET category_id = 1 WHERE category_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM link_categories WHERE id = ?')->execute([$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function save_link(string $name, string $url, string $desc, int $sort, int $id = 0, string $icon = '', int $categoryId = 1): void
{
    $pdo = db();
    $check = $pdo->prepare('SELECT id FROM link_categories WHERE id = ?');
    $check->execute([$categoryId]);
    if (!$check->fetchColumn()) $categoryId = 1;
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE links SET category_id=?, name=?, url=?, description=?, sort_order=?, icon_url=? WHERE id=?');
        $stmt->execute([$categoryId, $name, $url, $desc ?: null, $sort, $icon ?: null, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO links (category_id, name, url, description, sort_order, icon_url) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$categoryId, $name, $url, $desc ?: null, $sort, $icon ?: null]);
    }
}

function delete_link(int $id): void
{
    db()->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
}
