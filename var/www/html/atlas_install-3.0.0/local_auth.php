<?php
/** Local authentication, TOTP and session support. */

define('ATLAS_LOCAL_COOKIE', 'ATLASLOCAL');

function atlas_auth_log(string $event, array $ctx = []): void {
    $safe = [];
    foreach ($ctx as $k => $v) {
        if (preg_match('/pass|secret|token|totp|key/i', (string)$k)) continue;
        if (is_scalar($v) || $v === null) $safe[$k] = $v;
    }
    $safe['event'] = $event;
    $safe['request_id'] = atlas_request_id();
    $safe['uri'] = (string)($_SERVER['REQUEST_URI'] ?? 'cli');
    $safe['remote'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    error_log('[ATLAS_APP] ' . json_encode($safe, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

function atlas_local_db(): mysqli {
    static $db = null;
    if ($db instanceof mysqli && @$db->ping()) return $db;
    $db = atlas_mysqli_connect(
        (string)atlas_env('ATLAS_DB_RW_HOST','127.0.0.1'),
        (string)atlas_env('ATLAS_DB_RW_USER',''),
        (string)atlas_env('ATLAS_DB_RW_PASSWORD',''),
        (string)atlas_env('ATLAS_DB_NAME','atlas_install_panda'), 3306
    );
    if ($db->connect_errno) throw new RuntimeException('Local authentication database unavailable');
    @$db->set_charset('utf8mb4');
    return $db;
}


function atlas_local_auth_install_schema(mysqli $db, array $missing = []): void {
    $statements = [
        'atlas_local_user' => "CREATE TABLE IF NOT EXISTS atlas_local_user (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(64) NOT NULL, first_name VARCHAR(100) NOT NULL DEFAULT '', last_name VARCHAR(100) NOT NULL DEFAULT '', email VARCHAR(254) NOT NULL DEFAULT '', role VARCHAR(32) NOT NULL DEFAULT 'user', enabled TINYINT(1) NOT NULL DEFAULT 1, password_hash VARCHAR(255) NOT NULL, must_change_password TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_atlas_local_username(username)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atlas_auth_setting' => "CREATE TABLE IF NOT EXISTS atlas_auth_setting (name VARCHAR(64) NOT NULL, value VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atlas_local_totp' => "CREATE TABLE IF NOT EXISTS atlas_local_totp (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, label VARCHAR(100) NOT NULL DEFAULT 'Authenticator', secret_enc TEXT NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY ix_atlas_totp_user(user_id), CONSTRAINT fk_atlas_totp_user FOREIGN KEY(user_id) REFERENCES atlas_local_user(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atlas_local_session' => "CREATE TABLE IF NOT EXISTS atlas_local_session (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, remote_addr VARCHAR(64) NOT NULL DEFAULT '', user_agent VARCHAR(255) NOT NULL DEFAULT '', PRIMARY KEY(id), UNIQUE KEY uq_atlas_session_token(token_hash), KEY ix_atlas_session_user(user_id), KEY ix_atlas_session_expires(expires_at), CONSTRAINT fk_atlas_session_user FOREIGN KEY(user_id) REFERENCES atlas_local_user(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    // Parent tables first so foreign keys are valid even when several tables are absent.
    foreach (['atlas_local_user','atlas_auth_setting','atlas_local_totp','atlas_local_session'] as $table) {
        if ($missing && !in_array($table, $missing, true)) continue;
        if (!$db->query($statements[$table])) {
            atlas_auth_log('local_auth_schema_create_error', ['table'=>$table, 'db_errno'=>$db->errno, 'db_error'=>$db->error]);
            throw new RuntimeException('Unable to initialize local authentication table '.$table.': '.$db->error);
        }
    }
}

function atlas_local_auth_schema(): void {
    static $done = false;
    if ($done) return;
    $db = atlas_local_db();
    $required = ['atlas_local_user','atlas_local_totp','atlas_local_session','atlas_auth_setting'];
    $missing = [];
    foreach ($required as $table) {
        $escaped = $db->real_escape_string($table);
        $r = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$escaped."' LIMIT 1");
        if (!$r || !$r->fetch_row()) $missing[] = $table;
    }
    if ($missing) {
        atlas_auth_log('local_auth_schema_missing', ['missing_tables'=>implode(',', $missing), 'action'=>'auto_initialize']);
        atlas_local_auth_install_schema($db, $missing);
        $stillMissing = [];
        foreach ($required as $table) {
            $escaped = $db->real_escape_string($table);
            $r = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$escaped."' LIMIT 1");
            if (!$r || !$r->fetch_row()) $stillMissing[] = $table;
        }
        if ($stillMissing) {
            atlas_auth_log('local_auth_schema_auto_init_failed', ['missing_tables'=>implode(',', $stillMissing), 'db_error'=>$db->error]);
            throw new RuntimeException('Local authentication schema could not be initialized automatically (missing: '.implode(', ', $stillMissing).').');
        }
        atlas_auth_log('local_auth_schema_auto_initialized', ['tables'=>implode(',', $missing)]);
    }

    $defaultHours=max(1,min(168,(int)atlas_env('ATLAS_SESSION_HOURS','8')));
    $db->query("INSERT IGNORE INTO atlas_auth_setting(name,value) VALUES ('session_hours','".$defaultHours."')");
    $pw = (string)atlas_env('ATLAS_LOCAL_ADMIN_PASSWORD','password');
    $fp = hash('sha256', $pw);
    $u = $db->query("SELECT id FROM atlas_local_user WHERE username='admin' LIMIT 1");
    $adminRow = $u ? $u->fetch_assoc() : null;
    if (!$adminRow) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $force = hash_equals($pw, 'password') ? 1 : 0;
        $st=$db->prepare("INSERT INTO atlas_local_user(username,first_name,last_name,email,role,enabled,password_hash,must_change_password) VALUES ('admin','Administrator','','','master',1,?,?)");
        $st->bind_param('si',$hash,$force); $st->execute();
        $st=$db->prepare("INSERT INTO atlas_auth_setting(name,value) VALUES ('admin_bootstrap_fingerprint',?) ON DUPLICATE KEY UPDATE value=VALUES(value)"); $st->bind_param('s',$fp); $st->execute();
        atlas_auth_log('local_admin_created',['force_password_change'=>$force]);
    } else {
        $r=$db->query("SELECT value FROM atlas_auth_setting WHERE name='admin_bootstrap_fingerprint' LIMIT 1"); $rr=$r?$r->fetch_assoc():null; $old=$rr?(string)$rr['value']:'';
        if ($old === '') {
            $st=$db->prepare("INSERT INTO atlas_auth_setting(name,value) VALUES ('admin_bootstrap_fingerprint',?) ON DUPLICATE KEY UPDATE value=VALUES(value)"); $st->bind_param('s',$fp); $st->execute();
        } elseif (!hash_equals($old,$fp)) {
            $hash=password_hash($pw,PASSWORD_DEFAULT); $force=hash_equals($pw,'password')?1:0; $aid=(int)$adminRow['id'];
            $st=$db->prepare("UPDATE atlas_local_user SET password_hash=?,must_change_password=?,enabled=1,role='master' WHERE id=?"); $st->bind_param('sii',$hash,$force,$aid); $st->execute();
            $db->query('DELETE FROM atlas_local_session WHERE user_id='.$aid);
            $st=$db->prepare("UPDATE atlas_auth_setting SET value=? WHERE name='admin_bootstrap_fingerprint'"); $st->bind_param('s',$fp); $st->execute();
            atlas_auth_log('local_admin_password_applied_from_configuration',['force_password_change'=>$force]);
        }
    }
    $done = true;
}

function atlas_auth_key(): string {
    $path = (string)atlas_env('ATLAS_LOCAL_AUTH_KEY_FILE','/var/lib/atlas-install/config/local-auth.key');
    $raw = @file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Local authentication encryption key unavailable');
    $raw=trim($raw);
    $bin = ctype_xdigit($raw) && strlen($raw)>=64 ? hex2bin(substr($raw,0,64)) : hash('sha256',$raw,true);
    if ($bin===false || strlen($bin)<32) throw new RuntimeException('Invalid local authentication encryption key');
    return substr($bin,0,32);
}
function atlas_secret_encrypt(string $plain): string {
    $iv=random_bytes(12); $tag='';
    $ct=openssl_encrypt($plain,'aes-256-gcm',atlas_auth_key(),OPENSSL_RAW_DATA,$iv,$tag,'atlas-local-totp');
    if ($ct===false) throw new RuntimeException('Unable to encrypt TOTP secret');
    return base64_encode($iv.$tag.$ct);
}
function atlas_secret_decrypt(string $enc): string {
    $raw=base64_decode($enc,true); if($raw===false||strlen($raw)<29) return '';
    $iv=substr($raw,0,12); $tag=substr($raw,12,16); $ct=substr($raw,28);
    $pt=openssl_decrypt($ct,'aes-256-gcm',atlas_auth_key(),OPENSSL_RAW_DATA,$iv,$tag,'atlas-local-totp');
    return $pt===false?'':$pt;
}
function atlas_base32_encode(string $data): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
    foreach(str_split($data) as $c) $bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
    $out=''; foreach(str_split($bits,5) as $chunk){$chunk=str_pad($chunk,5,'0');$out.=$alphabet[bindec($chunk)];} return $out;
}
function atlas_base32_decode(string $s): string {
    $s=strtoupper(preg_replace('/[^A-Z2-7]/','',$s)); $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
    foreach(str_split($s) as $c){$p=strpos($alphabet,$c); if($p===false)return ''; $bits.=str_pad(decbin($p),5,'0',STR_PAD_LEFT);} $out='';
    foreach(str_split($bits,8) as $b){if(strlen($b)===8)$out.=chr(bindec($b));} return $out;
}
function atlas_totp_generate_secret(): string { return atlas_base32_encode(random_bytes(20)); }
function atlas_totp_code(string $secret, ?int $slice=null): string {
    $slice=$slice??intdiv(time(),30); $key=atlas_base32_decode($secret); $bin=pack('N2',($slice>>32)&0xffffffff,$slice&0xffffffff); $h=hash_hmac('sha1',$bin,$key,true); $o=ord($h[19])&0xf; $v=((ord($h[$o])&0x7f)<<24)|(ord($h[$o+1])<<16)|(ord($h[$o+2])<<8)|ord($h[$o+3]); return str_pad((string)($v%1000000),6,'0',STR_PAD_LEFT);
}
function atlas_totp_verify(string $secret,string $code): bool { $code=preg_replace('/\D/','',$code); if(strlen($code)!==6)return false; $s=intdiv(time(),30); for($i=-1;$i<=1;$i++) if(hash_equals(atlas_totp_code($secret,$s+$i),$code)) return true; return false; }

function atlas_auth_setting(string $name,string $default=''): string { atlas_local_auth_schema(); $db=atlas_local_db(); $st=$db->prepare('SELECT value FROM atlas_auth_setting WHERE name=?'); $st->bind_param('s',$name); $st->execute(); $r=$st->get_result()->fetch_assoc(); return $r?(string)$r['value']:$default; }
function atlas_set_auth_setting(string $name,string $value): void { atlas_local_auth_schema(); $db=atlas_local_db(); $st=$db->prepare('INSERT INTO atlas_auth_setting(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)'); $st->bind_param('ss',$name,$value); $st->execute(); }

function atlas_cert_identity(): ?array {
    $verify=(string)($_SERVER['SSL_CLIENT_VERIFY']??getenv('SSL_CLIENT_VERIFY')?:''); $dn=(string)($_SERVER['SSL_CLIENT_S_DN']??getenv('SSL_CLIENT_S_DN')?:'');
    if($verify!=='SUCCESS'||$dn==='') return null;
    $norm=preg_replace('/\/CN=proxy/','',$dn); $norm=preg_replace('/\/CN=[0-9]+/','',$norm); $norm=preg_replace('/\/CN=[0-9]+/','',$norm);
    $name=(string)($_SERVER['SSL_CLIENT_S_DN_CN']??getenv('SSL_CLIENT_S_DN_CN')?:$norm); $role=''; $email=''; $enabled=0;
    try { $db=atlas_local_db(); $st=$db->prepare('SELECT u.name,u.email,r.description role,u.enabled FROM user u JOIN role r ON ABS(u.rolefk)=r.ref WHERE u.dn=? ORDER BY u.enabled DESC,u.ref DESC LIMIT 1'); $st->bind_param('s',$norm); $st->execute(); $row=$st->get_result()->fetch_assoc(); if($row){$name=(string)$row['name'];$email=(string)($row['email']??'');$role=(string)$row['role'];$enabled=(int)$row['enabled'];} } catch(Throwable $e){ atlas_auth_log('certificate_role_lookup_failed',['error'=>$e->getMessage()]); }
    return ['source'=>'certificate','username'=>$name,'name'=>$name,'first_name'=>'','last_name'=>'','email'=>$email,'role'=>$role,'enabled'=>$enabled,'dn'=>$norm,'must_change_password'=>0];
}
function atlas_local_identity(): ?array {
    $token=(string)($_COOKIE[ATLAS_LOCAL_COOKIE]??''); if($token==='') return null;
    try { atlas_local_auth_schema(); $db=atlas_local_db(); $hash=hash('sha256',$token); $st=$db->prepare("SELECT s.id session_id,u.* FROM atlas_local_session s JOIN atlas_local_user u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>NOW() AND u.enabled=1 LIMIT 1"); $st->bind_param('s',$hash); $st->execute(); $r=$st->get_result()->fetch_assoc(); if(!$r)return null; $sid=(int)$r['session_id']; $db->query('UPDATE atlas_local_session SET last_seen_at=NOW() WHERE id='.$sid); return ['source'=>'local','id'=>(int)$r['id'],'username'=>(string)$r['username'],'name'=>trim((string)$r['first_name'].' '.(string)$r['last_name'])?: (string)$r['username'],'first_name'=>(string)$r['first_name'],'last_name'=>(string)$r['last_name'],'email'=>(string)$r['email'],'role'=>(string)$r['role'],'enabled'=>(int)$r['enabled'],'dn'=>'','must_change_password'=>(int)$r['must_change_password']]; } catch(Throwable $e){ atlas_auth_log('local_session_lookup_failed',['error'=>$e->getMessage()]); return null; }
}
function atlas_current_identity(): ?array { static $cached=false,$id=null; if($cached)return $id; $cached=true; $id=atlas_local_identity(); if($id)return $id; return $id=atlas_cert_identity(); }
function atlas_is_authenticated(): bool { $i=atlas_current_identity(); return $i!==null && (int)($i['enabled']??1)===1; }
function atlas_has_role(array $roles): bool { $i=atlas_current_identity(); return $i!==null && in_array((string)($i['role']??''),$roles,true); }

function atlas_create_local_session(int $uid): void {
    atlas_local_auth_schema(); $db=atlas_local_db(); $hours=max(1,min(168,(int)atlas_auth_setting('session_hours','8'))); $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $expires=date('Y-m-d H:i:s',time()+$hours*3600); $ip=(string)($_SERVER['REMOTE_ADDR']??''); $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255); $st=$db->prepare('INSERT INTO atlas_local_session(user_id,token_hash,expires_at,remote_addr,user_agent) VALUES(?,?,?,?,?)'); $st->bind_param('issss',$uid,$hash,$expires,$ip,$ua); $st->execute(); setcookie(ATLAS_LOCAL_COOKIE,$token,['expires'=>time()+$hours*3600,'path'=>'/atlas_install','secure'=>true,'httponly'=>true,'samesite'=>'Lax']); atlas_auth_log('local_login_success',['user_id'=>$uid,'session_hours'=>$hours]); }
function atlas_logout_local(): void { $token=(string)($_COOKIE[ATLAS_LOCAL_COOKIE]??''); if($token!==''){try{$db=atlas_local_db();$h=hash('sha256',$token);$st=$db->prepare('DELETE FROM atlas_local_session WHERE token_hash=?');$st->bind_param('s',$h);$st->execute();}catch(Throwable $e){}} setcookie(ATLAS_LOCAL_COOKIE,'',['expires'=>1,'path'=>'/atlas_install','secure'=>true,'httponly'=>true,'samesite'=>'Lax']); }
function atlas_user_totps(int $uid,bool $enabledOnly=false): array { atlas_local_auth_schema(); $db=atlas_local_db(); $q='SELECT id,label,secret_enc,enabled,created_at FROM atlas_local_totp WHERE user_id=?'.($enabledOnly?' AND enabled=1':'').' ORDER BY id'; $st=$db->prepare($q);$st->bind_param('i',$uid);$st->execute();return $st->get_result()->fetch_all(MYSQLI_ASSOC); }
function atlas_verify_user_totp(int $uid,string $code): bool { foreach(atlas_user_totps($uid,true) as $t){$s=atlas_secret_decrypt((string)$t['secret_enc']);if($s!==''&&atlas_totp_verify($s,$code))return true;} return false; }
function atlas_local_user_by_username(string $username): ?array { atlas_local_auth_schema();$db=atlas_local_db();$st=$db->prepare('SELECT * FROM atlas_local_user WHERE username=? LIMIT 1');$st->bind_param('s',$username);$st->execute();$r=$st->get_result()->fetch_assoc();return $r?:null; }

function atlas_require_master(): void { if(!atlas_has_role(['master'])) atlas_access_denied(atlas_t('master_required')); }
?>