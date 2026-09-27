<?php
require_once __DIR__ . '/../config.php';
atlas_require_client_certificate();

$name = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
if ($name === '' || $name === '.' || $name === '..') {
    http_response_code(400);
    exit('Invalid file');
}
$roots = [$upload_path, $archive_path];
$resolved = null;
foreach ($roots as $base) {
    $baseReal = realpath($base);
    $candidate = realpath(rtrim($base, '/') . '/' . $name);
    if ($baseReal !== false && $candidate !== false && str_starts_with($candidate, $baseReal . DIRECTORY_SEPARATOR) && is_file($candidate) && is_readable($candidate)) {
        $resolved = $candidate;
        break;
    }
}
if ($resolved === null) {
    http_response_code(404);
    exit('File not found');
}
header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
header('Content-Length: ' . (string)filesize($resolved));
readfile($resolved);
?>
