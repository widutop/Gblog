<?php
function admin_sub_tabs(array $tabs, string $active, string $rightHtml = '', ?callable $href = null): void
{
    echo '<div class="card tab-card"><div class="tab-card-row">';
    echo '<div class="tab-bar">';
    foreach ($tabs as $key => $label) {
        $on  = ((string)$key === $active) ? ' on' : '';
        // 默认 ?tab=key；章节管理等页面可传闭包自定义链接（保留 novel_id / 筛选参数）
        $url = $href ? (string)$href((string)$key) : '?tab=' . rawurlencode((string)$key);
        echo '<a class="tab-btn' . $on . '" href="' . e($url) . '">'
           . (string)$label . '</a>';
    }
    echo '</div>';
    if ($rightHtml !== '') {
        echo '<div class="tab-card-right">' . $rightHtml . '</div>';
    }
    echo '</div></div>';
}

function admin_flash_html(string $error = '', string $success = ''): string
{
    $out = '';
    if ($error !== '') {
        $out .= '<span class="alert alert-error">' . e($error) . '</span>';
    }
    if ($success !== '') {
        $out .= '<span class="alert alert-success">' . e($success) . '</span>';
    }
    return $out;
}
