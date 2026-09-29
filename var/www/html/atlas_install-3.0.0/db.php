<?php
require_once __DIR__ . '/config.php';

$dbconn = array('rw' => NULL, 'ro' => NULL, 'broker' => NULL);
$dbconn_dbname = array('rw' => NULL, 'ro' => NULL, 'broker' => NULL);
$cache = NULL;

if (class_exists('Memcache')) {
    try {
        $candidate = new Memcache();
        if (@$candidate->connect(atlas_env('ATLAS_MEMCACHE_HOST', '127.0.0.1'), (int)atlas_env('ATLAS_MEMCACHE_PORT', '11211'))) {
            $cache = $candidate;
        }
    } catch (Throwable $e) {
        error_log($logprefix . ' memcache unavailable: ' . $e->getMessage());
    }
}
if (!defined('MEMCACHE_COMPRESSED')) define('MEMCACHE_COMPRESSED', 0);

function db_conn($dest='rw') {
    require __DIR__ . '/config.php';
    global $dbconn, $dbconn_dbname, $logprefix;
    if (isset($dbconn[$dest]) && $dbconn[$dest] instanceof mysqli) return $dbconn[$dest];

    mysqli_report(MYSQLI_REPORT_OFF);
    if (array_key_exists($dest, $LJSFi_dbserv)) {
        if ($LJSFi_dbuser[$dest] === '') {
            throw new RuntimeException("Database credentials are not configured for '$dest'");
        }
        $conn = atlas_mysqli_connect($LJSFi_dbserv[$dest], $LJSFi_dbuser[$dest], $LJSFi_dbpass[$dest], $LJSFi_dbname, 3306);
        $dbname = $LJSFi_dbname;
    } else {
        if (!preg_match('/^([^:]+):([^@]+)@([^\\/]+)\\/([A-Za-z0-9_]+)(?::([0-9]+))?$/', $dest, $m)) {
            throw new InvalidArgumentException('Invalid database destination');
        }
        $conn = atlas_mysqli_connect($m[3], $m[1], $m[2], $m[4], isset($m[5]) ? (int)$m[5] : 3306);
        $dbname = $m[4];
    }
    if ($conn->connect_errno) {
        $msg=$logprefix . ' database connection failed for ' . $dest . ': ' . $conn->connect_error; if(function_exists('atlas_log_line')) atlas_log_line($msg); else error_log($msg);
        throw new RuntimeException('Database connection failed');
    }
    if (!$conn->set_charset('utf8mb4')) {
        error_log($logprefix . ' failed to set utf8mb4 for ' . $dest);
        throw new RuntimeException('Database character set initialization failed');
    }
    $dbconn[$dest] = $conn;
    $dbconn_dbname[$dest] = $dbname;
    return $conn;
}

function db_err($err, $query='') {
    global $logprefix;
    $msg=$logprefix . ' database error: ' . $err . ($query !== '' ? ' [query hash=' . hash('sha256',$query) . ']' : ''); if(function_exists('atlas_log_line')) atlas_log_line($msg); else error_log($msg);
    if (!atlas_is_cli()) {
        http_response_code(500);
        echo '<p>Database operation failed.</p>';
    } else {
        fwrite(STDERR, "Database operation failed.\n");
    }
    exit(1);
}

function db_query($query, $dest='rw', $commit=FALSE) {
    global $dbconn;
    try { $conn = db_conn($dest); }
    catch (Throwable $e) { db_err($e->getMessage()); }
    $result = $conn->query($query);
    if ($result === false) db_err($conn->error, $query);
    if ($commit) $conn->commit();
    return $result;
}

function db_query_rest($query, $dest='rw', $commit=FALSE) {
    global $dbconn, $logprefix;
    try { $conn = db_conn($dest); }
    catch (Throwable $e) { error_log($logprefix . ' ' . $e->getMessage()); return NULL; }
    $result = $conn->query($query);
    if ($result === false) {
        error_log($logprefix . ' database error: ' . $conn->error . ' [query hash=' . hash('sha256',$query) . ']');
        return NULL;
    }
    if ($commit) $conn->commit();
    return $result;
}

function db_escape(mixed $value, string $dest='rw'): string {
    $conn = db_conn($dest);
    return $conn->real_escape_string((string)$value);
}

function db_prepare_execute(string $sql, array $params = [], string $types = '', string $dest='rw'): mysqli_stmt {
    $conn = db_conn($dest);
    $stmt = $conn->prepare($sql);
    if (!$stmt) db_err($conn->error, $sql);
    if ($params) {
        if ($types === '') {
            $types = '';
            foreach ($params as $p) $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) db_err($stmt->error, $sql);
    return $stmt;
}

function db_quote(mixed $value, string $dest='rw'): string {
    return "'" . db_escape($value, $dest) . "'";
}
function db_int_list(mixed $values, int $min = 0, ?int $max = null): string {
    if (!is_array($values) || !$values) {
        throw new InvalidArgumentException('Expected non-empty integer list');
    }
    $out = [];
    foreach ($values as $value) $out[] = db_int($value, $min, $max);
    return implode(',', $out);
}
function db_identifier(mixed $value): string {
    $value = (string)$value;
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)) {
        throw new InvalidArgumentException('Invalid SQL identifier');
    }
    return '`' . $value . '`';
}
function db_like_contains(mixed $value, string $dest='rw'): string {
    return db_quote('%' . (string)$value . '%', $dest);
}
function db_order_by(mixed $value, array $allowed, string $default): string {
    $raw = trim((string)$value);
    if ($raw === '') return $default;
    if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(ASC|DESC))?$/iD', $raw, $m)) {
        return $default;
    }
    $key = strtolower($m[1]);
    $map = [];
    foreach ($allowed as $candidate) $map[strtolower((string)$candidate)] = (string)$candidate;
    if (!isset($map[$key])) return $default;
    $direction = isset($m[2]) ? ' '.strtoupper($m[2]) : '';
    return db_identifier($map[$key]).$direction;
}
function db_int(mixed $value, int $min = 0, ?int $max = null): int {
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new InvalidArgumentException('Expected integer request value');
    }
    $v=(int)$value;
    if ($v < $min || ($max !== null && $v > $max)) {
        throw new InvalidArgumentException('Integer request value out of range');
    }
    return $v;
}
?>
