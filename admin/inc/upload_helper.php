<?php
// 上传：扩展名白名单 + 真实 MIME 校验 + 大小限制 + 随机命名。
require_once __DIR__ . '/../../usr/data/config.php';

function handle_upload(array $file, string $type): array
{
    if (!isset($file['tmp_name']) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'error' => '未接收到文件'];
    }

    if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
        return ['ok' => false, 'url' => '', 'error' => '文件超过 ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB 限制'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($type === 'audio' && !in_array($ext, ALLOWED_AUDIO_EXT, true)) {
        return ['ok' => false, 'url' => '', 'error' => '仅允许音频文件：' . implode('/', ALLOWED_AUDIO_EXT)];
    }
    if ($type === 'image' && !in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        return ['ok' => false, 'url' => '', 'error' => '仅允许图片文件：' . implode('/', ALLOWED_IMAGE_EXT)];
    }

    $mime = detect_mime($file['tmp_name']);
    if ($type === 'audio' && strpos($mime, 'audio/') !== 0) {
        return ['ok' => false, 'url' => '', 'error' => '文件内容不是音频（MIME: ' . $mime . '）'];
    }
    if ($type === 'image' && strpos($mime, 'image/') !== 0) {
        return ['ok' => false, 'url' => '', 'error' => '文件内容不是图片（MIME: ' . $mime . '）'];
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
