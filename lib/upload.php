<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function random_file_name(string $originalName, string $suffix = ''): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'bin';
    }
    $base = bin2hex(random_bytes(16));
    $suffix = $suffix ? ('_' . $suffix) : '';
    return $base . $suffix . '.' . $ext;
}

function is_image_upload(string $tmpPath): bool
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    if (!$mime) {
        return false;
    }
    return in_array($mime, [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ], true);
}

function upload_image(string $fieldName, string $subdir): string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Thiếu tệp tải lên hoặc tệp không hợp lệ: ' . $fieldName);
    }
    if ((int)$_FILES[$fieldName]['size'] > UPLOAD_IMAGE_MAX_BYTES) {
        throw new RuntimeException('Ảnh vượt quá dung lượng cho phép');
    }

    $tmp = $_FILES[$fieldName]['tmp_name'];
    if (!is_uploaded_file($tmp) || !is_image_upload($tmp)) {
        throw new RuntimeException('Tệp ảnh không hợp lệ');
    }

    $uploadRoot = __DIR__ . '/../uploads';
    $destDir = $uploadRoot . '/' . trim($subdir, '/\\');
    ensure_dir($destDir);

    $name = random_file_name((string)$_FILES[$fieldName]['name']);
    $destPath = $destDir . '/' . $name;

    if (!move_uploaded_file($tmp, $destPath)) {
        throw new RuntimeException('Không thể lưu ảnh đã tải lên');
    }

    // Return relative path for DB usage (so it works from web root).
    return 'uploads/' . trim($subdir, '/\\') . '/' . $name;
}

function is_pdf_upload(string $tmpPath, ?string $originalName = null): bool
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);

    // Validate by checking PDF header first (most reliable).
    $header = '';
    $fh = @fopen($tmpPath, 'rb');
    if ($fh) {
        $header = (string)fread($fh, 5); // e.g. "%PDF-"
        fclose($fh);
    }
    if (strncmp($header, '%PDF', 4) === 0) {
        return true;
    }

    // Fallback: sometimes MIME differs by environment.
    if (is_string($mime) && $mime !== '' && stripos($mime, 'pdf') !== false) {
        return true;
    }

    // Fallback: use extension from original filename if provided.
    if (is_string($originalName) && $originalName !== '') {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return true;
        }
    }

    return false;
}

function upload_pdf(string $fieldName, string $subdir): string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Thiếu tệp tải lên hoặc tệp không hợp lệ: ' . $fieldName);
    }
    if ((int)$_FILES[$fieldName]['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Tệp vượt quá dung lượng cho phép');
    }

    $tmp = $_FILES[$fieldName]['tmp_name'];
    $originalName = (string)($_FILES[$fieldName]['name'] ?? '');
    if (!is_uploaded_file($tmp) || !is_pdf_upload($tmp, $originalName)) {
        throw new RuntimeException('Tệp PDF không hợp lệ');
    }

    $uploadRoot = __DIR__ . '/../uploads';
    $destDir = $uploadRoot . '/' . trim($subdir, '/\\');
    ensure_dir($destDir);

    $name = random_file_name((string)$_FILES[$fieldName]['name']);
    $destPath = $destDir . '/' . $name;

    if (!move_uploaded_file($tmp, $destPath)) {
        throw new RuntimeException('Không thể lưu tệp đã tải lên');
    }

    return 'uploads/' . trim($subdir, '/\\') . '/' . $name;
}

function upload_images_multi(string $fieldName, string $subdir): array
{
    if (!isset($_FILES[$fieldName])) {
        return [];
    }
    if (!is_array($_FILES[$fieldName]['name'])) {
        return [];
    }

    $uploadPaths = [];
    $count = count($_FILES[$fieldName]['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($_FILES[$fieldName]['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ((int)$_FILES[$fieldName]['size'][$i] > UPLOAD_IMAGE_MAX_BYTES) {
            continue;
        }

        $tmp = $_FILES[$fieldName]['tmp_name'][$i];
        if (!is_uploaded_file($tmp) || !is_image_upload($tmp)) {
            continue;
        }

        $uploadRoot = __DIR__ . '/../uploads';
        $destDir = $uploadRoot . '/' . trim($subdir, '/\\');
        ensure_dir($destDir);

        $name = random_file_name((string)$_FILES[$fieldName]['name'][$i], (string)$i);
        $destPath = $destDir . '/' . $name;
        if (!move_uploaded_file($tmp, $destPath)) {
            continue;
        }

        $uploadPaths[] = 'uploads/' . trim($subdir, '/\\') . '/' . $name;
    }

    return $uploadPaths;
}

