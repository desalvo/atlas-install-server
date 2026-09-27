<?php
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['status' => 'ok', 'service' => 'atlas-install'], JSON_UNESCAPED_SLASHES) . "\n";
