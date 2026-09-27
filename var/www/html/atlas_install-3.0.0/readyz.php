<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    $conn = db_conn('ro');
    $ok = $conn->query('SELECT 1');
    if (!$ok) throw new RuntimeException('query failed');
    echo json_encode(['status' => 'ready'], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'not-ready'], JSON_UNESCAPED_SLASHES) . "\n";
}
