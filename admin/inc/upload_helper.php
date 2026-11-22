<?php
// 上传：扩展名白名单 + 真实 MIME 校验 + 大小限制 + 随机命名。
require_once __DIR__ . '/../../usr/data/config.php';

// 软件包单独设上限；老站 config.php 没有该常量时回退到通用上限，避免升级后必须改配置。
function gb_upload_max_size(string $type): int
{
    if ($type === 'file') {
        return defined('MAX_FILE_UPLOAD_SIZE') ? (int)MAX_FILE_UPLOAD_SIZE : (int)MAX_UPLOAD_SIZE;
    }
    return (int)MAX_UPLOAD_SIZE;
}

function gb_upload_allowed_ext(string $type): array
{
    if ($type === 'file') {
        return defined('ALLOWED_FILE_EXT') ? (array)ALLOWED_FILE_EXT
            : ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'apk', 'exe', 'msi', 'dmg',
               'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    }
    if ($type === 'audio') return (array)ALLOWED_AUDIO_EXT;
    if ($type === 'image') return (array)ALLOWED_IMAGE_EXT;
    return [];
}

function gblog_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function handle_upload(array $file, string $type): array
{
    if (!isset($file['tmp_name']) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'error' => '未接收到文件'];
    }

    $maxSize = gb_upload_max_size($type);
    if ((int)$file['size'] > $maxSize) {
        return ['ok' => false, 'url' => '', 'error' => '文件超过 ' . gblog_format_bytes($maxSize) . ' 限制'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = gb_upload_allowed_ext($type);
    if ($allowed && !in_array($ext, $allowed, true)) {
        $label = $type === 'file' ? '软件包' : ($type === 'audio' ? '音频文件' : '图片文件');
        return ['ok' => false, 'url' => '', 'error' => '仅允许' . $label . '：' . implode('/', $allowed)];
    }

    $mime = detect_mime($file['tmp_name']);
    if ($type === 'audio' && strpos($mime, 'audio/') !== 0) {
        return ['ok' => false, 'url' => '', 'error' => '文件内容不是音频（MIME: ' . $mime . '）'];
    }
    if ($type === 'image' && strpos($mime, 'image/') !== 0) {
        return ['ok' => false, 'url' => '', 'error' => '文件内容不是图片（MIME: ' . $mime . '）'];
    }
    // 软件包只接受二进制/文档类 MIME，避免把 .php/.html 改名成 .zip 后下发。
    // 无法探测 MIME 的主机（finfo 缺失）仍靠扩展名白名单把关。
    if ($type === 'file' && $mime !== '' && strpos($mime, 'application/') !== 0) {
        return ['ok' => false, 'url' => '', 'error' => '文件内容不是可下载软件包（MIME: ' . $mime . '）'];
    }

    $sub = date('Ym');
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'url' => '', 'error' => '无法创建上传目录，请检查 uploads 目录权限'];
    }

    $name = date('d_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'url' => '', 'error' => '文件保存失败，请检查 uploads 目录权限'];
    }

    return ['ok' => true, 'url' => UPLOAD_URL . '/' . $sub . '/' . $name, 'error' => ''];
}

function detect_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime ?: '';
    }
    if (function_exists('mime_content_type')) {
        return mime_content_type($path) ?: '';
    }
    return '';
}
