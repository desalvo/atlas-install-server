<?php
/**
 * Security/bootstrap helpers for the RHEL 10 / PHP 8.3 migration.
 */
declare(strict_types=0);
require_once __DIR__ . '/i18n.php';

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
        error_log('[ATLAS_APP] request_validation_failed reason=control_chars_or_size uri=' . (string)($_SERVER['REQUEST_URI'] ?? ''));
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
    $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    // login.php and change_password.php use a per-session cryptographic CSRF token
    // and strict/Lax secure cookies. Do not reject these forms based on proxy-
    // dependent Origin reconstruction; their own CSRF validation is authoritative.
    if (preg_match('~/auth/(?:login|change_password)\.php/?$~', $requestPath)) return;
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return; // preserve non-browser API/agent clients
    $originParts = parse_url($origin);
    $originHost = strtolower((string)($originParts['host'] ?? ''));
    $originScheme = strtolower((string)($originParts['scheme'] ?? ''));
    $originPort = isset($originParts['port']) ? (int)$originParts['port'] : (($originScheme === 'https') ? 443 : 80);
    $requestHostRaw = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $forwardedHostRaw = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0] ?? '');
    $configuredHostRaw = trim((string)atlas_env('ATLAS_PUBLIC_HOSTNAME', ''));
    $normalizeHost = static function(string $raw): string {
        $raw = trim(strtolower($raw));
        if ($raw === '') return '';
        if ($raw[0] === '[') { // IPv6 host[:port]
            $end = strpos($raw, ']');
            return $end === false ? $raw : substr($raw, 1, $end - 1);
        }
        return preg_replace('/:\d+$/', '', $raw) ?? $raw;
    };
    $allowedHosts = array_values(array_unique(array_filter([
        $normalizeHost($requestHostRaw),
        $normalizeHost($forwardedHostRaw),
        $normalizeHost($configuredHostRaw),
    ])));
    $sameHost = $originHost !== '' && in_array($normalizeHost($originHost), $allowedHosts, true);

    // Some WebKit/privacy configurations omit or sanitize Origin on ordinary form POSTs.
    // In that case accept a same-host Referer. We still never trust an arbitrary proxy host:
    // it must match Host, X-Forwarded-Host, or the configured ATLAS public hostname.
    if (!$sameHost) {
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $refParts = parse_url($referer);
            $refHost = $normalizeHost((string)($refParts['host'] ?? ''));
            if ($refHost !== '' && in_array($refHost, $allowedHosts, true)) $sameHost = true;
        }
    }

    $standardOriginPort = ($originScheme === 'https' && $originPort === 443) || ($originScheme === 'http' && $originPort === 80);
    $explicitPublicPort = null;
    foreach ([$forwardedHostRaw, $requestHostRaw] as $candidateHost) {
        if (preg_match('/:(\d+)$/', $candidateHost, $hm)) { $explicitPublicPort = (int)$hm[1]; break; }
    }
    $samePort = $explicitPublicPort === null || $explicitPublicPort === $originPort || $standardOriginPort;
    if (!in_array($originScheme, ['http','https'], true) || !$sameHost || !$samePort) {
        atlas_app_log('same_origin_rejected',['origin'=>$origin,'host'=>$requestHostRaw,'forwarded_host'=>$forwardedHostRaw,'configured_host'=>$configuredHostRaw]);
        http_response_code(403);
        exit('Cross-origin state-changing request rejected');
    }
}

function atlas_request_id(): string {
    static $id = null; if ($id !== null) return $id;
    $incoming = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $id = preg_match('/^[A-Za-z0-9._:-]{6,128}$/D',$incoming) ? $incoming : bin2hex(random_bytes(8));
    if (!atlas_is_cli() && !headers_sent()) header('X-Request-ID: '.$id);
    return $id;
}

function atlas_log_line(string $message): void {
    $line = rtrim($message, "\r\n");
    // Always write to the persistent runtime log as the authoritative channel.
    // The container entrypoint tails this file to stderr, so PHP-FPM worker
    // descriptor handling cannot hide application errors from kubectl logs.
    $logFile = (string)atlas_env('ATLAS_APP_ERROR_LOG','/var/lib/atlas-install/log/php-application.log');
    $written = false;
    try {
        $n = @file_put_contents($logFile, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        $written = ($n !== false);
    } catch (Throwable $e) { $written = false; }
    // Also try the worker stderr for native/non-container deployments.
    try { @file_put_contents('php://stderr', $line.PHP_EOL, FILE_APPEND); } catch (Throwable $e) {}
    if (!$written) @error_log($line);
}

function atlas_app_log(string $event, array $context = []): void {
    foreach ($context as $k => $v) {
        if (preg_match('/pass|secret|token|totp|key/i', (string)$k)) unset($context[$k]);
    }
    $payload = array_merge(['event'=>$event,'request_id'=>atlas_request_id(),'uri'=>(string)($_SERVER['REQUEST_URI']??'cli')], $context);
    atlas_log_line('[ATLAS_APP] '.json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

function atlas_log_bootstrap(): void {
    ini_set('log_errors','1');
    @ini_set('error_log','/proc/self/fd/2');
    ini_set('display_errors', atlas_db_bool('ATLAS_DEBUG',false) ? '1':'0');
    set_error_handler(function($severity,$message,$file,$line){
        if (!(error_reporting() & $severity)) return false;
        atlas_app_log('php_error',['severity'=>$severity,'message'=>$message,'file'=>basename($file),'line'=>$line]);
        return false;
    });
    set_exception_handler(function(Throwable $e){
        atlas_app_log('uncaught_exception',['type'=>get_class($e),'message'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);
        if (atlas_is_cli()) return;
        http_response_code(500);
        $existing = ob_get_level() > 0 ? (string)ob_get_contents() : '';
        // If the normal page chrome is already in the output buffer, never render
        // a second header/menu. Append only the in-page error notice.
        if (stripos($existing,'id="menubar"') !== false || stripos($existing,"id='menubar'") !== false) {
            echo '<div class="atlas-alert error atlas-late-error"><strong>'.atlas_h(atlas_t('app_error')).'</strong><br>'.atlas_h(atlas_t('app_error_msg')).' <span class="atlas-code">'.atlas_h(atlas_request_id()).'</span></div>';
            return;
        }
        atlas_render_message_page(atlas_t('app_error'),atlas_t('app_error_msg'),'error');
    });
    register_shutdown_function(function(){
        $last=error_get_last();
        if($last && in_array((int)$last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR],true)) {
            atlas_app_log('fatal_shutdown',['severity'=>$last['type'],'message'=>$last['message'],'file'=>basename((string)$last['file']),'line'=>$last['line']]);
        }
        if(!atlas_is_cli() && http_response_code() >= 500) {
            atlas_app_log('http_5xx_completed',['status'=>http_response_code(),'last_error'=>$last ? (string)$last['message'] : 'none']);
        }
    });
}

// Logging must be active before request validation and origin checks so every
// rejected request is visible in kubectl logs.
atlas_log_bootstrap();
atlas_validate_request();
atlas_security_headers();
atlas_same_origin_guard();

require_once __DIR__ . '/local_auth.php';

function atlas_identity_summary_html(): string {
    $i=atlas_current_identity();
    if(!$i) return '<strong>'.atlas_h(atlas_t('public_session')).'</strong> · '.atlas_h(atlas_t('no_authenticated_user'));
    if(($i['source']??'')==='certificate') return '<strong>'.atlas_h(atlas_t('certificate')).':</strong> '.atlas_h($i['name']??''). ' · <strong>DN:</strong> <span class="atlas-code">'.atlas_h($i['dn']??'').'</span> · <strong>'.atlas_h(atlas_t('role')).':</strong> '.atlas_h($i['role']?:atlas_t('not_assigned'));
    return '<strong>'.atlas_h(atlas_t('local_user')).':</strong> '.atlas_h($i['username']??'').' · '.atlas_h($i['name']??'').' · <strong>'.atlas_h(atlas_t('email')).':</strong> '.atlas_h($i['email']??'').' · <strong>'.atlas_h(atlas_t('role')).':</strong> '.atlas_h($i['role']??'');
}
function atlas_identity_details_html(): string {
    return '<details class="atlas-identity-footer"><summary>'.atlas_h(atlas_t('current_user_details')).'</summary><div class="atlas-identity-body">'.atlas_identity_summary_html().'</div></details>';
}
function atlas_render_message_page(string $title,string $message,string $kind='warning'): never {
    if(!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $vo=atlas_env('ATLAS_VO','ATLAS'); $i=atlas_current_identity();
    require_once __DIR__.'/css/page_header.php';
    require_once __DIR__.'/css/main_header.php';
    require_once __DIR__.'/css/menubar.php';
    echo '<!doctype html><html lang="'.atlas_h(atlas_lang()).'"><head><title>'.atlas_h($title).'</title>';
    page_header('/atlas_install');
    echo '</head><body><div id="main"><div id="header">';
    main_header($vo,'/atlas_install');
    menubar('/atlas_install');
    echo '</div><div id="site_content"><div id="content" style="width:100%;float:none"><h1>'.atlas_h($title).'</h1><div class="atlas-alert '.atlas_h($kind).'">'.atlas_h($message).'</div>';
    if($kind==='warning' || str_contains(strtolower($title),'access') || str_contains(strtolower($title),'autoriz')) {
        echo '<div class="atlas-access-help"><p>'.atlas_h(atlas_t('unauth_howto')).'</p><p><a class="atlas-button" href="/atlas_install/auth/login.php">'.atlas_h(atlas_t('go_login')).'</a></p></div>';
    }
    echo '</div></div>'.atlas_identity_details_html().'<div id="footer"><p>LJSF 3 · ATLAS Installation System · request '.atlas_h(atlas_request_id()).'</p></div></div></body></html>';
    exit;
}
function atlas_access_denied(string $detail=''): never {
    http_response_code(403); $i=atlas_current_identity(); atlas_auth_log('access_denied',['auth_source'=>$i['source']??'none','username'=>$i['username']??'','role'=>$i['role']??'']);
    if($detail==='') $detail=atlas_t('unauthorized_default');
    atlas_render_message_page(atlas_t('unauthorized'),$detail.' '.atlas_t('contact_admin'),'warning');
}
function atlas_require_client_certificate(): void { if(atlas_is_cli())return; $i=atlas_cert_identity(); if(!$i)atlas_access_denied(atlas_t('certificate_required')); }
function atlas_require_authenticated(): void { if(atlas_is_cli())return; if(!atlas_is_authenticated())atlas_access_denied(); $i=atlas_current_identity(); if(($i['source']??'')==='local' && !empty($i['must_change_password']) && !str_contains((string)($_SERVER['REQUEST_URI']??''),'/auth/change_password.php')) { header('Location: /atlas_install/auth/change_password.php'); exit; } }
function atlas_require_sensitive_request_certificate(): void { if(atlas_is_cli())return; $path=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:''; if(preg_match('~^/atlas_install/protected(?:/|$)~',$path)) atlas_require_authenticated(); }
function atlas_export_legacy_identity(): void { if(atlas_is_cli())return; $i=atlas_current_identity(); if(!$i||($i['source']??'')!=='local')return; $dn='LOCAL:'.(string)$i['username']; putenv('SSL_CLIENT_S_DN='.$dn); putenv('SSL_CLIENT_S_DN_CN='.(string)$i['username']); $_SERVER['SSL_CLIENT_S_DN']=$dn; $_SERVER['SSL_CLIENT_S_DN_CN']=(string)$i['username']; }

function atlas_append_identity_footer(): void {
    if(atlas_is_cli() || ob_get_level() < 1) return;
    $ct=(string)(headers_list()?implode(';',headers_list()):'');
    $uri=(string)($_SERVER['REQUEST_URI']??'');
    if(stripos($ct,'application/json')!==false||stripos($ct,'text/plain')!==false||preg_match('~/(?:protected/)?exec/|/(?:healthz|readyz)\.php|chart\.php~i',$uri)) return;
    $body=ob_get_clean(); if($body===false)return;
    if(stripos($body,'<html')===false){ echo $body; return; }
    if(stripos($body,'name="viewport"')===false && stripos($body,"name='viewport'")===false) $body=preg_replace('~<head([^>]*)>~i','<head$1><meta name="viewport" content="width=device-width,initial-scale=1">',$body,1);
    if(stripos($body,'modern.css')===false) $body=preg_replace('~</head>~i','<link rel="stylesheet" href="/atlas_install/css/modern.css"></head>',$body,1);
    if(stripos($body,'atlas-identity-footer')===false){
        try {
            $footer=atlas_identity_details_html();
        } catch (Throwable $e) {
            atlas_app_log('identity_footer_failed',['message'=>$e->getMessage()]);
            $footer='<details class="atlas-identity-footer"><summary>'.atlas_h(atlas_t('current_user_details')).'</summary><div class="atlas-identity-body">'.atlas_h(atlas_t('identity_unavailable')).'</div></details>';
        }
        if(stripos($body,'</body>')!==false)$body=preg_replace('~</body>~i',$footer.'</body>',$body,1);else$body.=$footer;
    }
    echo $body;
}
if(!atlas_is_cli()) { atlas_request_id(); ob_start(); register_shutdown_function('atlas_append_identity_footer'); atlas_export_legacy_identity(); }
atlas_require_sensitive_request_certificate();
