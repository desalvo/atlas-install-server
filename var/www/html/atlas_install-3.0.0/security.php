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
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return; // preserve non-browser API/agent clients
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if (!hash_equals($expected, rtrim($origin, '/'))) {
        error_log('[ATLAS_APP] same_origin_rejected uri=' . (string)($_SERVER['REQUEST_URI'] ?? '') . ' origin=' . $origin);
        http_response_code(403);
        exit('Cross-origin state-changing request rejected');
    }
}

atlas_validate_request();
atlas_security_headers();
atlas_same_origin_guard();




function atlas_request_id(): string {
    static $id = null; if ($id !== null) return $id;
    $incoming = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $id = preg_match('/^[A-Za-z0-9._:-]{6,128}$/D',$incoming) ? $incoming : bin2hex(random_bytes(8));
    if (!atlas_is_cli() && !headers_sent()) header('X-Request-ID: '.$id);
    return $id;
}

ini_set('log_errors','1');
ini_set('display_errors', atlas_db_bool('ATLAS_DEBUG',false) ? '1':'0');
set_error_handler(function($severity,$message,$file,$line){
    if (!(error_reporting() & $severity)) return false;
    error_log('[ATLAS_APP] '.json_encode(['event'=>'php_error','request_id'=>atlas_request_id(),'severity'=>$severity,'message'=>$message,'file'=>basename($file),'line'=>$line,'uri'=>(string)($_SERVER['REQUEST_URI']??'cli')],JSON_UNESCAPED_SLASHES));
    return false;
});
set_exception_handler(function(Throwable $e){
    error_log('[ATLAS_APP] '.json_encode(['event'=>'uncaught_exception','request_id'=>atlas_request_id(),'type'=>get_class($e),'message'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'uri'=>(string)($_SERVER['REQUEST_URI']??'cli')],JSON_UNESCAPED_SLASHES));
    if (!atlas_is_cli()) { http_response_code(500); atlas_render_message_page('Errore applicativo','Si è verificato un errore interno. Riprova o contatta l’amministratore.','error'); }
});

require_once __DIR__ . '/local_auth.php';

function atlas_identity_summary_html(): string {
    $i=atlas_current_identity();
    if(!$i) return '<strong>Sessione pubblica</strong> · nessuna utenza autenticata';
    if(($i['source']??'')==='certificate') return '<strong>Certificato:</strong> '.atlas_h($i['name']??''). ' · <strong>DN:</strong> <span class="atlas-code">'.atlas_h($i['dn']??'').'</span> · <strong>Ruolo:</strong> '.atlas_h($i['role']?:'non assegnato');
    return '<strong>Utente locale:</strong> '.atlas_h($i['username']??'').' · '.atlas_h($i['name']??'').' · <strong>Email:</strong> '.atlas_h($i['email']??'').' · <strong>Ruolo:</strong> '.atlas_h($i['role']??'');
}
function atlas_render_message_page(string $title,string $message,string $kind='warning'): never {
    if(!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $vo=atlas_env('ATLAS_VO','ATLAS'); $i=atlas_current_identity();
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.atlas_h($title).'</title><link rel="stylesheet" href="/atlas_install/css/ljsf.css"><link rel="stylesheet" href="/atlas_install/css/modern.css"></head><body><div id="main"><div id="header"><div id="logo" class="atlas-hero"><div class="atlas-brandmark">A</div><div id="logo_text"><h1><a>'.atlas_h($vo).' <span class="logo_colour">Installation System</span></a></h1><h2>Software deployment, validation and site operations</h2></div></div><div id="menubar"><ul id="menu" class="dropdown dropdown-horizontal"><li><a href="/atlas_install/">Home</a></li><li><a href="/atlas_install/auth/login.php">Login locale</a></li></ul></div></div><div id="site_content"><div id="content" style="width:100%;float:none"><h1>'.atlas_h($title).'</h1><div class="atlas-alert '.atlas_h($kind).'">'.atlas_h($message).'</div>';
    if(!$i) echo '<p>Non disponi delle autorizzazioni necessarie. Se ritieni di dover accedere a questa funzione, contatta l’amministratore.</p>';
    echo '</div></div><div class="atlas-identity-footer">'.atlas_identity_summary_html().'</div><div id="footer"><p>ATLAS Installation System · request '.atlas_h(atlas_request_id()).'</p></div></div></body></html>';
    exit;
}
function atlas_access_denied(string $detail='Non disponi delle autorizzazioni necessarie per accedere a questa pagina o funzione.'): never {
    http_response_code(403); $i=atlas_current_identity(); atlas_auth_log('access_denied',['auth_source'=>$i['source']??'none','username'=>$i['username']??'','role'=>$i['role']??'']);
    atlas_render_message_page('Accesso non autorizzato',$detail.' Contatta l’amministratore se ritieni che i privilegi debbano essere modificati.','warning');
}
function atlas_require_client_certificate(): void { if(atlas_is_cli())return; $i=atlas_cert_identity(); if(!$i)atlas_access_denied('Questa funzione richiede un certificato client valido.'); }
function atlas_require_authenticated(): void { if(atlas_is_cli())return; if(!atlas_is_authenticated())atlas_access_denied('È necessario autenticarsi con un certificato autorizzato oppure con un’utenza locale.'); $i=atlas_current_identity(); if(($i['source']??'')==='local' && !empty($i['must_change_password']) && !str_contains((string)($_SERVER['REQUEST_URI']??''),'/auth/change_password.php')) { header('Location: /atlas_install/auth/change_password.php'); exit; } }
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
    if(stripos($body,'atlas-identity-footer')===false){$footer='<div class="atlas-identity-footer">'.atlas_identity_summary_html().'</div>'; if(stripos($body,'</body>')!==false)$body=preg_replace('~</body>~i',$footer.'</body>',$body,1);else$body.=$footer;}
    echo $body;
}
if(!atlas_is_cli()) { atlas_request_id(); ob_start(); register_shutdown_function('atlas_append_identity_footer'); atlas_export_legacy_identity(); }
atlas_require_sensitive_request_certificate();
