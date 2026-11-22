<?php
/**
 * 钩子（Action / Filter）注册入口；钩子名只增不删，完整清单见《Gblog 开发笔记》。
 * 接管标题区后置 $GLOBALS['gblog_detail_title_taken']=true，默认标题零件让位。
 */

if (!isset($GLOBALS['gblog_hooks'])) {
    $GLOBALS['gblog_hooks'] = ['actions' => [], 'filters' => []];
}

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['gblog_hooks']['actions'][$hook][$priority][] = $callback;
}

function do_action(string $hook, ...$args): void
{
    $all = $GLOBALS['gblog_hooks']['actions'][$hook] ?? [];
    if (!$all) return;
    ksort($all);
    foreach ($all as $group) {
        foreach ($group as $callback) {
            try {
                $callback(...$args);
            } catch (Throwable $e) {
            }
        }
    }
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['gblog_hooks']['filters'][$hook][$priority][] = $callback;
}

function apply_filters(string $hook, $value, ...$args)
{
    $all = $GLOBALS['gblog_hooks']['filters'][$hook] ?? [];
    if (!$all) return $value;
    ksort($all);
    foreach ($all as $group) {
        foreach ($group as $callback) {
            try {
                $value = $callback($value, ...$args);
            } catch (Throwable $e) {
            }
        }
    }
    return $value;
}

function has_hook(string $hook, string $kind = 'actions'): bool
{
    return !empty($GLOBALS['gblog_hooks'][$kind][$hook]);
}
