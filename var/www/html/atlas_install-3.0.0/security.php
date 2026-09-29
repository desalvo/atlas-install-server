<?php
/**
 * Security/bootstrap helpers for the RHEL 10 / PHP 8.3 migration.
 */
declare(strict_types=0);

function atlas_env(string $name, ?string $default = null): ?string {
    $value = getenv($name);
    if ($value !== false && $value !== '') return $value;
    static $fileEnv = null;
    if ($fileEnv === null) {
        $envFile = getenv('ATLAS_ENV_FILE') ?: '/etc/atlas-install/atlas-install.env';
        $fileEnv = [];
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $raw] = explode('=', $line, 2);
                $key = trim($key);
                $raw = trim($raw);
                if (!preg_match('/^[A-Z0-9_]+$/', $key)) continue;
                if (strlen($raw) >= 2 && $raw[0] === '"' && $raw[strlen($raw)-1] === '"') {
                    $raw = substr($raw, 1, -1);
                    $raw = preg_replace_callback('/\\\\([\\\"$`])/', static fn($m) => $m[1], $raw);
                }
                $fileEnv[$key] = $raw;
            }
        }
    }
    return isset($fileEnv[$name]) && $fileEnv[$name] !== '' ? (string)$fileEnv[$name] : $default;
}

function atlas_db_bool(string $name, bool $default = false): bool {
    $raw = atlas_env($name, $default ? '1' : '0');
    return filter_var((string)$raw, FILTER_VALIDATE_BOOL);
}

/**
 * Open a MariaDB/MySQL connection using the common ATLAS TLS policy.
 * ATLAS_DB_SSL=1 enables TLS. ATLAS_DB_SSL_VERIFY=1 enables server certificate
 * verification; ATLAS_DB_SSL_CA may point to a CA bundle inside the container.
 */
function atlas_mysqli_connect(string $host, string $user, string $password, string $db, int $port = 3306): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    if (!$conn) throw new RuntimeException('mysqli_init failed');

    $flags = 0;
    if (atlas_db_bool('ATLAS_DB_SSL', false)) {
        $verify = atlas_db_bool('ATLAS_DB_SSL_VERIFY', false);
        $ca = trim((string)atlas_env('ATLAS_DB_SSL_CA', ''));
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $verify);
        }
        @mysqli_ssl_set($conn, null, null, $ca !== '' ? $ca : null, null, null);
        $flags |= MYSQLI_CLIENT_SSL;
    }

    @$conn->real_connect($host, $user, $password, $db, $port, null, $flags);
    return $conn;
}

function atlas_h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function atlas_is_cli(): bool {
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function atlas_require_client_certificate(): void {
    if (atlas_is_cli()) {
        return;
    }
    $verify = (string)($_SERVER['SSL_CLIENT_VERIFY'] ?? getenv('SSL_CLIENT_VERIFY') ?: '');
    $dn = (string)($_SERVER['SSL_CLIENT_S_DN'] ?? getenv('SSL_CLIENT_S_DN') ?: '');
    $remain = (int)($_SERVER['SSL_CLIENT_V_REMAIN'] ?? getenv('SSL_CLIENT_V_REMAIN') ?: 0);
    if ($verify !== 'SUCCESS' || $dn === '' || $remain <= 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "A valid client certificate is required.\n";
        exit;
    }
}

function atlas_security_headers(): void {
    if (atlas_is_cli() || headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self' https:; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
}

function atlas_reject_control_chars(mixed $value): mixed {
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = atlas_reject_control_chars($v);
        }
        return $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    if (strlen($value) > 1048576 || preg_match('/[\\x00\\r]/', $value)) {
        http_response_code(400);
        exit('Invalid request data');
    }
    return $value;
}

function atlas_validate_request(): void {
    $_GET = atlas_reject_control_chars($_GET);
    $_POST = atlas_reject_control_chars($_POST);
    $_REQUEST = atlas_reject_control_chars($_REQUEST);
}

function atlas_same_origin_guard(): void {
    if (atlas_is_cli()) return;
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) return;
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return; // preserve non-browser API/agent clients
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if (!hash_equals($expected, rtrim($origin, '/'))) {
        http_response_code(403);
        exit('Cross-origin state-changing request rejected');
    }
}

atlas_validate_request();
atlas_security_headers();
atlas_same_origin_guard();

function atlas_require_sensitive_request_certificate(): void {
    if (atlas_is_cli()) return;
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if (preg_match('~^/atlas_install/protected(?:/|$)~', $path)) {
        atlas_require_client_certificate();
    }
}
atlas_require_sensitive_request_certificate();
