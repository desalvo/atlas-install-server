<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user_info.php';
atlas_require_authenticated();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ATLASCFG');
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

$currentUsers = get_user_info('select', null, null, true, null, 5, 0, null);
$currentUser = $currentUsers[0] ?? null;
if (!$currentUser || (int)($currentUser['enabled'] ?? 0) !== 1 || (string)($currentUser['role'] ?? '') !== 'master') {
    atlas_access_denied(atlas_lang()==='it'?'La configurazione server richiede un ruolo master abilitato.':'Server configuration requires an enabled master role.');
}

if (empty($_SESSION['atlas_cfg_csrf'])) {
    $_SESSION['atlas_cfg_csrf'] = bin2hex(random_bytes(32));
}

$envFile = getenv('ATLAS_ENV_FILE') ?: '/etc/atlas-install/atlas-install.env';

function cfg_parse_env_file(string $path): array {
    $out = [];
    if (!is_readable($path)) return $out;
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if (!preg_match('/^[A-Z0-9_]+$/D', $k)) continue;
        $v = trim($v);
        if (strlen($v) >= 2 && $v[0] === '"' && $v[strlen($v)-1] === '"') {
            $v = substr($v, 1, -1);
            $v = preg_replace_callback('/\\\\([\\"$`])/', static fn($m) => $m[1], $v);
        }
        $out[$k] = $v;
    }
    return $out;
}

function cfg_quote(string $value): string {
    if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
        throw new InvalidArgumentException('Invalid control character in configuration value');
    }
    return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', '`' => '\\`']) . '"';
}

function cfg_validate_host(string $value, string $label): string {
    $value = trim($value);
    if ($value === '' || strlen($value) > 253 || !preg_match('/^[A-Za-z0-9_.:-]+$/D', $value)) {
        throw new InvalidArgumentException("Invalid {$label}");
    }
    return $value;
}

function cfg_validate_db_ident(string $value, string $label): string {
    $value = trim($value);
    if ($value === '' || strlen($value) > 64 || !preg_match('/^[A-Za-z0-9_$.-]+$/D', $value)) {
        throw new InvalidArgumentException("Invalid {$label}");
    }
    return $value;
}

function cfg_write_env_file(string $path, array $values): void {
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Configuration directory is not writable by the web service');
    }
    $ordered = [
        'ATLAS_PUBLIC_HOSTNAME','ATLAS_DB_NAME',
        'ATLAS_DB_RW_HOST','ATLAS_DB_RW_USER','ATLAS_DB_RW_PASSWORD',
        'ATLAS_DB_RO_HOST','ATLAS_DB_RO_USER','ATLAS_DB_RO_PASSWORD',
        'ATLAS_DB_BROKER_HOST','ATLAS_DB_BROKER_USER','ATLAS_DB_BROKER_PASSWORD',
        'ATLAS_DB_SSL','ATLAS_DB_SSL_VERIFY','ATLAS_DB_SSL_CA',
        'ATLAS_DB_GRANT_HOST','ATLAS_HOST_CERT_SOURCE','ATLAS_HOST_KEY_SOURCE',
        'ATLAS_HOST_CERT','ATLAS_HOST_KEY','ATLAS_UPLOAD_PATH','ATLAS_ARCHIVE_PATH',
        'ATLAS_CACHE_PATH','ATLAS_KML_CACHE','ATLAS_DEBUG','ATLAS_MAX_LOG_DIRS',
        'ATLAS_VO','ATLAS_EMAIL','ATLAS_CONTACTS','ATLAS_DEFAULT_INFOSYS',
        'ATLAS_ACTIVITY_PERIOD','ATLAS_ACCESS_LOG'
    ];
    $body = "# Managed by ATLAS Installation System. Contains secrets.\n";
    foreach ($ordered as $k) {
        if (array_key_exists($k, $values)) $body .= $k . '=' . cfg_quote((string)$values[$k]) . "\n";
    }
    foreach ($values as $k => $v) {
        if (!in_array($k, $ordered, true) && preg_match('/^[A-Z0-9_]+$/D', (string)$k)) {
            $body .= $k . '=' . cfg_quote((string)$v) . "\n";
        }
    }
    $tmp = tempnam($dir, '.atlas-env-');
    if ($tmp === false) throw new RuntimeException('Unable to create temporary configuration file');
    try {
        if (file_put_contents($tmp, $body, LOCK_EX) === false) throw new RuntimeException('Unable to write temporary configuration file');
        @chmod($tmp, 0660);
        if (!rename($tmp, $path)) throw new RuntimeException('Unable to atomically replace configuration file');
        @chmod($path, 0660);
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}

function cfg_test_db(string $host, string $user, string $password, string $db): string {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = atlas_mysqli_connect($host, $user, $password, $db, 3306);
    if ($conn->connect_errno) return 'Connection failed: ' . $conn->connect_error;
    if (!$conn->set_charset('utf8mb4')) { $conn->close(); return 'Connected, but utf8mb4 could not be enabled.'; }
    $ok = $conn->query('SELECT 1');
    $conn->close();
    return $ok ? 'OK' : 'Connection established but test query failed.';
}

$stored = cfg_parse_env_file($envFile);
$defaults = [
    'ATLAS_PUBLIC_HOSTNAME' => atlas_env('ATLAS_PUBLIC_HOSTNAME', 'atlas-install-el10.apps.desalvo.eu'),
    'ATLAS_DB_NAME' => atlas_env('ATLAS_DB_NAME', 'atlas_install_panda'),
    'ATLAS_DB_RW_HOST' => atlas_env('ATLAS_DB_RW_HOST', '192.168.1.145'),
    'ATLAS_DB_RW_USER' => atlas_env('ATLAS_DB_RW_USER', 'atlas_rw'),
    'ATLAS_DB_RO_HOST' => atlas_env('ATLAS_DB_RO_HOST', '192.168.1.145'),
    'ATLAS_DB_RO_USER' => atlas_env('ATLAS_DB_RO_USER', 'atlas_ro'),
    'ATLAS_DB_BROKER_HOST' => atlas_env('ATLAS_DB_BROKER_HOST', '192.168.1.145'),
    'ATLAS_DB_BROKER_USER' => atlas_env('ATLAS_DB_BROKER_USER', atlas_env('ATLAS_DB_RW_USER', 'atlas_rw')),
    'ATLAS_VO' => atlas_env('ATLAS_VO', 'ATLAS'),
    'ATLAS_EMAIL' => atlas_env('ATLAS_EMAIL', 'no-reply@localhost'),
    'ATLAS_CONTACTS' => atlas_env('ATLAS_CONTACTS', ''),
    'ATLAS_DEFAULT_INFOSYS' => atlas_env('ATLAS_DEFAULT_INFOSYS', 'lcg-bdii.cern.ch'),
    'ATLAS_ACTIVITY_PERIOD' => atlas_env('ATLAS_ACTIVITY_PERIOD', '3 DAY'),
];
$current = array_merge($stored, $defaults);
$message = '';
$error = '';
$dbTest = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string)$_SESSION['atlas_cfg_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Invalid CSRF token');
        }
        $next = $stored;
        $next['ATLAS_PUBLIC_HOSTNAME'] = cfg_validate_host((string)($_POST['public_hostname'] ?? ''), 'public hostname');
        $next['ATLAS_DB_NAME'] = cfg_validate_db_ident((string)($_POST['db_name'] ?? ''), 'database name');
        $next['ATLAS_DB_RW_HOST'] = cfg_validate_host((string)($_POST['db_host'] ?? ''), 'database host');
        $next['ATLAS_DB_RO_HOST'] = $next['ATLAS_DB_RW_HOST'];
        $next['ATLAS_DB_BROKER_HOST'] = $next['ATLAS_DB_RW_HOST'];
        $next['ATLAS_DB_RW_USER'] = cfg_validate_db_ident((string)($_POST['rw_user'] ?? ''), 'RW database user');
        $next['ATLAS_DB_RO_USER'] = cfg_validate_db_ident((string)($_POST['ro_user'] ?? ''), 'RO database user');
        $next['ATLAS_DB_BROKER_USER'] = cfg_validate_db_ident((string)($_POST['broker_user'] ?? ''), 'broker database user');
        foreach ([['rw_password','ATLAS_DB_RW_PASSWORD'],['ro_password','ATLAS_DB_RO_PASSWORD'],['broker_password','ATLAS_DB_BROKER_PASSWORD']] as [$field,$key]) {
            $submitted = (string)($_POST[$field] ?? '');
            if ($submitted !== '') $next[$key] = $submitted;
            elseif (!isset($next[$key])) $next[$key] = '';
        }
        foreach ([
            'ATLAS_VO' => 'vo', 'ATLAS_EMAIL' => 'email', 'ATLAS_CONTACTS' => 'contacts',
            'ATLAS_DEFAULT_INFOSYS' => 'default_infosys', 'ATLAS_ACTIVITY_PERIOD' => 'activity_period'
        ] as $key => $field) {
            $value = trim((string)($_POST[$field] ?? ''));
            if (strlen($value) > 512 || preg_match('/[\x00\r\n]/', $value)) throw new InvalidArgumentException("Invalid {$field}");
            $next[$key] = $value;
        }
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'test') {
            $pw = (string)($next['ATLAS_DB_RO_PASSWORD'] ?? '');
            $dbTest = cfg_test_db($next['ATLAS_DB_RO_HOST'], $next['ATLAS_DB_RO_USER'], $pw, $next['ATLAS_DB_NAME']);
            $current = array_merge($next, $defaults);
        } else {
            cfg_write_env_file($envFile, $next);
            error_log('[ATLAS_CONFIG] configuration updated by DN=' . (string)($_SERVER['SSL_CLIENT_S_DN'] ?? '') . ' keys=' . implode(',', array_keys($next)));
            $_SESSION['atlas_cfg_flash'] = 'Configuration saved. Application settings are effective on new requests; TLS/hostname changes require a pod or Apache restart.';
            header('Location: configuration.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if (!empty($_SESSION['atlas_cfg_flash'])) {
    $message = (string)$_SESSION['atlas_cfg_flash'];
    unset($_SESSION['atlas_cfg_flash']);
}

$certPath = atlas_env('ATLAS_HOST_CERT', getenv('ATLAS_TLS_CERT_FILE') ?: '/run/secrets/tls/tls.crt');
$certInfo = 'Not available';
if ($certPath && is_readable($certPath) && function_exists('openssl_x509_parse')) {
    $parsed = @openssl_x509_parse((string)file_get_contents($certPath));
    if (is_array($parsed)) {
        $cn = (string)($parsed['subject']['CN'] ?? 'unknown');
        $until = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s T', (int)$parsed['validTo_time_t']) : 'unknown';
        $certInfo = $cn . ' — expires ' . $until;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<title>Server Configuration - <?php echo atlas_h($LJSFi_VO); ?> Installation System</title>
<?php require __DIR__ . '/../css/page_header.php'; page_header('..'); ?>
</head>
<body>
<div id="main">
  <div id="header">
    <?php require __DIR__ . '/../css/main_header.php'; main_header($LJSFi_VO, '..'); ?>
    <?php require __DIR__ . '/../css/menubar.php'; menubar('..'); ?>
  </div>
  <div id="site_content">
    <div id="content" style="width:100%; float:none;">
      <h1>Server configuration</h1>
      <p class="atlas-muted">Protected configuration console. Access is allowed to an authorized certificate or a local user with the master role.</p>

      <?php if ($message): ?><div class="atlas-alert success"><?php echo atlas_h($message); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="atlas-alert error"><?php echo atlas_h($error); ?></div><?php endif; ?>
      <?php if ($dbTest): ?><div class="atlas-alert <?php echo $dbTest === 'OK' ? 'success' : 'warning'; ?>">Database test: <?php echo atlas_h($dbTest); ?></div><?php endif; ?>

      <div class="atlas-grid" style="margin-bottom:22px;">
        <div class="atlas-card"><div class="atlas-muted">Authenticated as</div><div class="atlas-kpi" style="font-size:18px;"><?php echo atlas_h((string)$currentUser['name']); ?></div><div class="atlas-muted atlas-code"><?php echo atlas_h((string)$currentUser['dn']); ?></div></div>
        <div class="atlas-card"><div class="atlas-muted">Role</div><div class="atlas-kpi"><?php echo atlas_h((string)$currentUser['role']); ?></div><div class="atlas-muted">Configuration write access</div></div>
        <div class="atlas-card"><div class="atlas-muted">Host certificate</div><div style="font-weight:700; margin-top:8px;"><?php echo atlas_h($certInfo); ?></div><div class="atlas-muted atlas-code"><?php echo atlas_h((string)$certPath); ?></div></div>
      </div>

      <form method="post" class="atlas-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo atlas_h($_SESSION['atlas_cfg_csrf']); ?>">
        <h2>Endpoint</h2>
        <div class="atlas-form-row"><label for="public_hostname">Public hostname</label><div><input id="public_hostname" name="public_hostname" required value="<?php echo atlas_h((string)$current['ATLAS_PUBLIC_HOSTNAME']); ?>" style="width:100%"><small>With TLS passthrough this should match the hostname presented to the client.</small></div></div>

        <h2>Database</h2>
        <div class="atlas-form-row"><label for="db_host">Database server</label><div><input id="db_host" name="db_host" required value="<?php echo atlas_h((string)$current['ATLAS_DB_RW_HOST']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="db_name">Database name</label><div><input id="db_name" name="db_name" required value="<?php echo atlas_h((string)$current['ATLAS_DB_NAME']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="rw_user">RW user</label><div><input id="rw_user" name="rw_user" required value="<?php echo atlas_h((string)$current['ATLAS_DB_RW_USER']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="rw_password">RW password</label><div><input id="rw_password" name="rw_password" type="password" value="" style="width:100%"><small>Leave blank to keep the current password.</small></div></div>
        <div class="atlas-form-row"><label for="ro_user">RO user</label><div><input id="ro_user" name="ro_user" required value="<?php echo atlas_h((string)$current['ATLAS_DB_RO_USER']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="ro_password">RO password</label><div><input id="ro_password" name="ro_password" type="password" value="" style="width:100%"><small>Leave blank to keep the current password.</small></div></div>
        <div class="atlas-form-row"><label for="broker_user">Broker user</label><div><input id="broker_user" name="broker_user" required value="<?php echo atlas_h((string)$current['ATLAS_DB_BROKER_USER']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="broker_password">Broker password</label><div><input id="broker_password" name="broker_password" type="password" value="" style="width:100%"><small>Leave blank to keep the current password.</small></div></div>

        <h2>Application</h2>
        <div class="atlas-form-row"><label for="vo">VO name</label><div><input id="vo" name="vo" value="<?php echo atlas_h((string)$current['ATLAS_VO']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="email">Sender email</label><div><input id="email" name="email" value="<?php echo atlas_h((string)$current['ATLAS_EMAIL']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="contacts">Contacts</label><div><input id="contacts" name="contacts" value="<?php echo atlas_h((string)$current['ATLAS_CONTACTS']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="default_infosys">Default InfoSys</label><div><input id="default_infosys" name="default_infosys" value="<?php echo atlas_h((string)$current['ATLAS_DEFAULT_INFOSYS']); ?>" style="width:100%"></div></div>
        <div class="atlas-form-row"><label for="activity_period">Activity period</label><div><input id="activity_period" name="activity_period" value="<?php echo atlas_h((string)$current['ATLAS_ACTIVITY_PERIOD']); ?>" style="width:100%"></div></div>

        <div class="atlas-alert warning">TLS certificates and IGTF trust anchors are intentionally not uploadable from this page. In Kubernetes they are mounted as dedicated secrets/volumes so a web compromise cannot silently replace the server identity or trust roots.</div>
        <div class="atlas-actions">
          <button type="submit" name="action" value="test">Test database connection</button>
          <button type="submit" name="action" value="save">Save configuration</button>
        </div>
      </form>
    </div>
  </div>
  <div id="footer"><p>ATLAS Installation System · authenticated configuration console</p></div>
</div>
</body>
</html>
