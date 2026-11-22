<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (function_exists('do_action')) do_action('portal_top');
?>
<?php render_slot('post_card_list', [
    'posts'      => $homePosts ?? [],
    'slug'       => '',
    'page'       => $page ?? 1,
    'totalPages' => $homeTotalPages ?? 1,
    'gbTabItems' => $homeTabItems ?? [],
    'gbPageBase' => $homePageBase ?? 'index.php?',
    'gbMetaName' => 'site',
]); ?>
