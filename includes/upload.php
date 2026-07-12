<?php
/** Safe image upload helpers — extension derived from MIME, never client filename. */

const IMAGE_MIME_EXT = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

function image_ext_from_mime(string $mime): ?string
{
    return IMAGE_MIME_EXT[$mime] ?? null;
}

function validate_image_upload(array $file, int $max_bytes = 5242880): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > $max_bytes) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    return image_ext_from_mime($mime);
}

/** Save uploaded image to admin/uploads/{subdir}/; returns web URL or false. */
function save_uploaded_image(array $file, string $subdir, string $prefix = 'img_'): string|false
{
    $ext = validate_image_upload($file);
    if (!$ext) {
        return false;
    }

    $uploadDir = dirname(__DIR__) . '/admin/uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $name = uniqid($prefix, true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
        return false;
    }

    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir    = dirname($script);
        if (basename($dir) === 'admin')    $dir = dirname($dir);
        if (basename($dir) === 'includes') $dir = dirname($dir);
        $base = rtrim($dir, '/') === '' ? '' : rtrim($dir, '/');
    }

    return $base . '/admin/uploads/' . trim($subdir, '/') . '/' . $name;
}

function save_uploaded_image_from_input(string $input_name, string $subdir, string $prefix = 'img_'): string|false
{
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
    }
    return save_uploaded_image($_FILES[$input_name], $subdir, $prefix);
}
