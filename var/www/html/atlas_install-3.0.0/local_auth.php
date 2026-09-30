<?php
/** Local authentication, TOTP and session support. */

define('ATLAS_LOCAL_COOKIE', 'ATLASLOCAL');

function atlas_auth_log(string $event, array $ctx = []): void {
    $safe = [];
    foreach ($ctx as $k => $v) {
        if (preg_match('/pass|secret|token|totp|key/i', (string)$k)) continue;
        if (is_scalar($v) || $v === null) $safe[$k] = $v;
    }
    $safe['remote'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if(function_exists('atlas_app_log')) atlas_app_log($event,$safe);
    else error_log('[ATLAS_APP] ' . json_encode(array_merge(['event'=>$event],$safe), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
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
function atlas_totp_uri(string $username, string $secret): string {
    $issuer=(string)atlas_env('ATLAS_VO','ATLAS').' Installation System';
    return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($username).'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
}
function atlas_totp_qr_data_uri(string $uri): string {
    $bin='/usr/bin/qrencode';
    if(!is_executable($bin) || !function_exists('proc_open')) return '';
    $spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $proc=@proc_open([$bin,'-t','PNG','-o','-','-s','6','-m','2',$uri],$spec,$pipes);
    if(!is_resource($proc)) return '';
    fclose($pipes[0]); $png=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]); $rc=proc_close($proc);
    if($rc!==0 || !is_string($png) || $png===''){ if(function_exists('atlas_app_log')) atlas_app_log('totp_qr_failed',['exit_code'=>$rc,'error'=>substr((string)$err,0,200)]); return ''; }
    return 'data:image/png;base64,'.base64_encode($png);
}

function atlas_totp_code(string $secret, ?int $slice=null): string {
    $slice=$slice??intdiv(time(),30); $key=atlas_base32_decode($secret); $bin=pack('N2',($slice>>32)&0xffffffff,$slice&0xffffffff); $h=hash_hmac('sha1',$bin,$key,true); $o=ord($h[19])&0xf; $v=((ord($h[$o])&0x7f)<<24)|(ord($h[$o+1])<<16)|(ord($h[$o+2])<<8)|ord($h[$o+3]); return str_pad((string)($v%1000000),6,'0',STR_PAD_LEFT);
}
function atlas_totp_verify(string $secret,string $code): bool { $code=preg_replace('/\D/','',$code); if(strlen($code)!==6)return false; $s=intdiv(time(),30); for($i=-1;$i<=1;$i++) if(hash_equals(atlas_totp_code($secret,$s+$i),$code)) return true; return false; }

function atlas_auth_setting(string $name,string $default=''): string { atlas_local_auth_schema(); $db=atlas_local_db(); $st=$db->prepare('SELECT value FROM atlas_auth_setting WHERE name=?'); $st->bind_param('s',$name); $st->execute(); $r=$st->get_result()->fetch_assoc(); return $r?(string)$r['value']:$default; }
function atlas_set_auth_setting(string $name,string $value): void { atlas_local_auth_schema(); $db=atlas_local_db(); $st=$db->prepare('INSERT INTO atlas_auth_setting(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)'); $st->bind_param('ss',$name,$value); $st->execute(); }

function atlas_dn_split_unescaped(string $value, string $delimiter): array {
    $parts=[]; $buf=''; $escaped=false; $quoted=false; $len=strlen($value);
    for($i=0;$i<$len;$i++){
        $ch=$value[$i];
        if($escaped){ $buf.=$ch; $escaped=false; continue; }
        if($ch==='\\'){ $buf.=$ch; $escaped=true; continue; }
        if($ch==='"'){ $buf.=$ch; $quoted=!$quoted; continue; }
        if(!$quoted && $ch===$delimiter){ $parts[]=$buf; $buf=''; continue; }
        $buf.=$ch;
    }
    $parts[]=$buf;
    return $parts;
}
function atlas_dn_unescape_value(string $value): string {
    $value=trim($value);
    if(strlen($value)>=2 && $value[0]==='"' && $value[strlen($value)-1]==='"') $value=substr($value,1,-1);
    $out=''; $len=strlen($value);
    for($i=0;$i<$len;$i++){
        if($value[$i]!=='\\'){ $out.=$value[$i]; continue; }
        if($i+2<$len && ctype_xdigit($value[$i+1].$value[$i+2])){ $out.=chr(hexdec($value[$i+1].$value[$i+2])); $i+=2; continue; }
        if($i+1<$len){ $out.=$value[++$i]; continue; }
        $out.='\\';
    }
    $out=preg_replace('/\s+/u',' ',trim($out));
    return is_string($out)?$out:'';
}
function atlas_dn_attribute_name(string $name): string {
    $name=strtoupper(trim($name));
    $aliases=[
      'E'=>'EMAILADDRESS', 'EMAIL'=>'EMAILADDRESS', 'EMAILADDRESS'=>'EMAILADDRESS', '1.2.840.113549.1.9.1'=>'EMAILADDRESS',
      'S'=>'ST', 'STATEORPROVINCENAME'=>'ST', '2.5.4.8'=>'ST',
      'COMMONNAME'=>'CN', '2.5.4.3'=>'CN', 'COUNTRYNAME'=>'C', '2.5.4.6'=>'C',
      'ORGANIZATIONNAME'=>'O', '2.5.4.10'=>'O', 'ORGANIZATIONALUNITNAME'=>'OU', '2.5.4.11'=>'OU',
      'LOCALITYNAME'=>'L', '2.5.4.7'=>'L', 'DOMAINCOMPONENT'=>'DC', '0.9.2342.19200300.100.1.25'=>'DC',
      'SERIALNUMBER'=>'SERIALNUMBER', '2.5.4.5'=>'SERIALNUMBER', 'USERID'=>'UID', '0.9.2342.19200300.100.1.1'=>'UID'
    ];
    return $aliases[$name]??$name;
}
function atlas_dn_parse_rdn(string $rdn): ?array {
    $avas=[];
    foreach(atlas_dn_split_unescaped($rdn,'+') as $ava){
        $escaped=false; $quoted=false; $eq=-1; $len=strlen($ava);
        for($i=0;$i<$len;$i++){
            $ch=$ava[$i];
            if($escaped){$escaped=false;continue;}
            if($ch==='\\'){$escaped=true;continue;}
            if($ch==='"'){$quoted=!$quoted;continue;}
            if(!$quoted && $ch==='='){$eq=$i;break;}
        }
        if($eq<=0) return null;
        $type=atlas_dn_attribute_name(substr($ava,0,$eq));
        if($type==='') return null;
        $value=atlas_dn_unescape_value(substr($ava,$eq+1));
        $fold=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
        $avas[]=['type'=>$type,'value'=>$fold];
    }
    usort($avas,static fn($a,$b)=>strcmp($a['type']."\0".$a['value'],$b['type']."\0".$b['value']));
    return $avas;
}
function atlas_dn_components(string $dn, bool $stripProxy=false): ?array {
    $dn=trim($dn); if($dn==='') return null;
    $slash=str_starts_with($dn,'/');
    $raw=$slash?atlas_dn_split_unescaped(substr($dn,1),'/'):atlas_dn_split_unescaped($dn,',');
    $rdns=[];
    foreach($raw as $part){ if(trim($part)==='') continue; $parsed=atlas_dn_parse_rdn($part); if($parsed===null)return null; $rdns[]=$parsed; }
    if(!$rdns)return null;
    // OpenSSL's slash notation is root-to-leaf; RFC2253/RFC4514 is leaf-to-root.
    if($slash) $rdns=array_reverse($rdns);
    if($stripProxy){
        while($rdns){
            $rdn=$rdns[0];
            if(count($rdn)!==1 || $rdn[0]['type']!=='CN') break;
            $v=$rdn[0]['value'];
            if($v!=='proxy' && !preg_match('/^[0-9]+$/D',$v)) break;
            array_shift($rdns);
        }
    }
    return $rdns?:null;
}
function atlas_canonicalize_dn(string $dn, bool $stripProxy=false): string {
    $parts=atlas_dn_components($dn,$stripProxy); if($parts===null)return '';
    return (string)json_encode($parts,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
function atlas_normalize_subject_dn(string $dn): string {
    $dn=trim($dn); if($dn==='')return '';
    if(str_starts_with($dn,'/')){
        // Preserve historical slash representation while removing Grid proxy suffix RDNs.
        do { $old=$dn; $dn=preg_replace('~/CN=(?:proxy|[0-9]+)$~i','',$dn); } while($dn!==$old);
        return trim((string)$dn);
    }
    // In RFC notation proxy RDNs are leaf-most and therefore appear first.
    do { $old=$dn; $dn=preg_replace('/^\s*CN\s*=\s*(?:proxy|[0-9]+)\s*,\s*/i','',$dn); } while($dn!==$old);
    return trim((string)$dn);
}
function atlas_normalize_issuer_dn(string $dn): string { return preg_replace('/\s+/',' ',trim($dn)); }
function atlas_ca_binding_status(string $storedDn, string $storedName, array $issuer): string {
    $storedDn=atlas_normalize_issuer_dn($storedDn); $storedName=trim($storedName);
    if($storedDn!==''){
        $storedCanon=atlas_canonicalize_dn($storedDn); $presentedCanon=(string)($issuer['ca_dn_canonical']??atlas_canonicalize_dn((string)($issuer['ca_dn']??'')));
        return ($storedCanon!=='' && $presentedCanon!=='' ? hash_equals($storedCanon,$presentedCanon) : hash_equals($storedDn,(string)($issuer['ca_dn']??'')))?'match':'mismatch';
    }
    if($storedName!=='') return strcasecmp($storedName,(string)($issuer['ca_name']??''))===0?'match':'mismatch';
    return 'legacy_unbound';
}
function atlas_cert_issuer_info(): array {
    $dn=atlas_normalize_issuer_dn((string)($_SERVER['SSL_CLIENT_I_DN']??getenv('SSL_CLIENT_I_DN')?:''));
    return [
      'ca_dn'=>$dn,
      'ca_dn_canonical'=>atlas_canonicalize_dn($dn),
      'ca_name'=>trim((string)($_SERVER['SSL_CLIENT_I_DN_CN']??getenv('SSL_CLIENT_I_DN_CN')?:'')),
      'serial'=>trim((string)($_SERVER['SSL_CLIENT_M_SERIAL']??getenv('SSL_CLIENT_M_SERIAL')?:'')),
      'valid_end'=>trim((string)($_SERVER['SSL_CLIENT_V_END']??getenv('SSL_CLIENT_V_END')?:'')),
    ];
}
function atlas_cert_identity(): ?array {
    $verify=(string)($_SERVER['SSL_CLIENT_VERIFY']??getenv('SSL_CLIENT_VERIFY')?:'');
    $dn=(string)($_SERVER['SSL_CLIENT_S_DN']??getenv('SSL_CLIENT_S_DN')?:'');
    if($verify!=='SUCCESS'||$dn==='') return null;
    $presentedDn=atlas_normalize_subject_dn($dn); $canonicalDn=atlas_canonicalize_dn($presentedDn,true); $issuer=atlas_cert_issuer_info();
    $name=(string)($_SERVER['SSL_CLIENT_S_DN_CN']??getenv('SSL_CLIENT_S_DN_CN')?:$presentedDn);
    $email=(string)($_SERVER['SSL_CLIENT_S_DN_Email']??getenv('SSL_CLIENT_S_DN_Email')?:'');
    $role=''; $enabled=0; $known=false; $caStatus='unknown'; $reason='unknown_dn'; $legacyRef=null; $storedCaDn=''; $storedCaName=''; $storedDn=''; $matchMethod='none';
    try {
        $db=atlas_local_db(); $row=null;
        // Fast path: preserve the traditional exact lookup when the representation already matches.
        $st=$db->prepare('SELECT u.ref,u.name,u.email,u.dn,u.rolefk,u.enabled,u.valid_start,u.valid_end,u.ca_dn,u.ca_name,r.description role FROM user u LEFT JOIN role r ON ABS(u.rolefk)=r.ref WHERE u.dn=? ORDER BY u.enabled DESC,u.ref DESC LIMIT 1');
        if(!$st) throw new RuntimeException('certificate identity prepare failed: '.$db->error);
        $st->bind_param('s',$presentedDn); $st->execute(); $row=$st->get_result()->fetch_assoc();
        if($row){ $matchMethod='exact'; }
        elseif($canonicalDn!==''){
            // Historical databases contain both OpenSSL slash and RFC2253 forms. Compare canonical
            // forms in PHP so no destructive DN migration or schema change is required.
            $q=$db->query("SELECT u.ref,u.name,u.email,u.dn,u.rolefk,u.enabled,u.valid_start,u.valid_end,u.ca_dn,u.ca_name,r.description role FROM user u LEFT JOIN role r ON ABS(u.rolefk)=r.ref WHERE u.dn IS NOT NULL AND u.dn<>'' AND u.dn NOT LIKE 'LOCAL:%' ORDER BY u.enabled DESC,u.ref DESC");
            if(!$q) throw new RuntimeException('certificate canonical lookup failed: '.$db->error);
            $matches=[];
            while($candidate=$q->fetch_assoc()) if(hash_equals($canonicalDn,atlas_canonicalize_dn((string)$candidate['dn'],true))) $matches[]=$candidate;
            if($matches){ $row=$matches[0]; $matchMethod='canonical'; if(count($matches)>1) atlas_auth_log('certificate_dn_ambiguous',['presented_dn'=>$presentedDn,'matches'=>count($matches),'selected_ref'=>(int)$row['ref']]); }
        }
        if($row){
            $known=true; $legacyRef=(int)$row['ref']; $storedDn=(string)$row['dn']; $name=(string)$row['name']; $email=(string)($row['email']??$email); $enabled=(int)$row['enabled'];
            $storedCaDn=atlas_normalize_issuer_dn((string)($row['ca_dn']??'')); $storedCaName=trim((string)($row['ca_name']??''));
            $caStatus=atlas_ca_binding_status($storedCaDn,$storedCaName,$issuer);
            $now=time(); $vs=!empty($row['valid_start'])?strtotime((string)$row['valid_start']):null; $ve=!empty($row['valid_end'])?strtotime((string)$row['valid_end']):null;
            $valid=($vs===null||$vs<=$now)&&($ve===null||$ve>$now); $approved=((int)$row['rolefk'])>0;
            if(!$enabled) $reason='disabled'; elseif(!$valid) $reason='expired'; elseif($caStatus==='mismatch') $reason='ca_mismatch'; elseif(!$approved) $reason='pending_approval'; elseif(empty($row['role'])) $reason='no_role'; else {$role=(string)$row['role'];$reason='role_assigned';}
            atlas_auth_log('certificate_dn_matched',['legacy_ref'=>$legacyRef,'match_method'=>$matchMethod,'ca_status'=>$caStatus]);
        } else atlas_auth_log('certificate_dn_unknown',['presented_dn'=>$presentedDn]);
    } catch(Throwable $e){ atlas_auth_log('certificate_role_lookup_failed',['error'=>$e->getMessage()]); }
    // For a known certificate user expose the exact historical DB DN. This is intentionally not
    // the canonical form: legacy pages still use literal user.dn comparisons.
    $effectiveDn=$known?$storedDn:$presentedDn;
    return ['source'=>'certificate','username'=>$name,'name'=>$name,'first_name'=>'','last_name'=>'','email'=>$email,'role'=>$role,'enabled'=>$enabled,'dn'=>$effectiveDn,'presented_dn'=>$presentedDn,'canonical_dn'=>$canonicalDn,'dn_match_method'=>$matchMethod,'must_change_password'=>0,
      'legacy_ref'=>$legacyRef,'known_user'=>$known,'ca_status'=>$caStatus,'role_reason'=>$reason,'ca_dn'=>$issuer['ca_dn'],'ca_name'=>$issuer['ca_name'],'stored_ca_dn'=>$storedCaDn,'stored_ca_name'=>$storedCaName,'cert_serial'=>$issuer['serial'],'cert_valid_end'=>$issuer['valid_end']];
}
function atlas_sync_local_legacy_user(array $identity): ?int {
    if (($identity['source'] ?? '') !== 'local') return null;
    static $synced=[]; $username=trim((string)($identity['username']??'')); if($username==='')return null; if(isset($synced[$username]))return $synced[$username];
    try {
        $db=atlas_local_db(); $dn='LOCAL:'.$username; $email=(string)($identity['email']??''); $role=(string)($identity['role']??'user'); if(!in_array($role,['user','admin','master'],true))$role='user'; $priv=$role==='user'?0:1;
        $st=$db->prepare('SELECT u.ref,u.name,u.email,u.priv_view,u.priv_insert,u.priv_update,u.priv_pin,u.priv_relsub,u.priv_critical,u.enabled,r.description role,(u.valid_end IS NULL OR u.valid_end>NOW()) valid_now FROM user u LEFT JOIN role r ON ABS(u.rolefk)=r.ref WHERE u.dn=? ORDER BY u.ref DESC LIMIT 1');
        if(!$st)throw new RuntimeException('legacy user lookup prepare failed: '.$db->error); $st->bind_param('s',$dn);$st->execute();$row=$st->get_result()->fetch_assoc();
        if($row){
            $ref=(int)$row['ref']; $matches=((string)$row['name']===$username&&(string)($row['email']??'')===$email&&(string)($row['role']??'')===$role&&(int)$row['enabled']===1&&(int)$row['valid_now']===1);
            foreach(['priv_view','priv_insert','priv_update','priv_pin','priv_relsub','priv_critical'] as $pk)$matches=$matches&&(int)$row[$pk]===$priv;
            if(!$matches){$st=$db->prepare("UPDATE user SET name=?,email=?,rolefk=(SELECT ref FROM role WHERE description=? LIMIT 1),priv_view=?,priv_insert=?,priv_update=?,priv_pin=?,priv_relsub=?,priv_critical=?,enabled=1,deleted_at=NULL,deleted_by=NULL,valid_start=COALESCE(valid_start,NOW()),valid_end=DATE_ADD(NOW(), INTERVAL 20 YEAR) WHERE ref=?");if(!$st)throw new RuntimeException('legacy user update prepare failed: '.$db->error);$st->bind_param('sssiiiiiii',$username,$email,$role,$priv,$priv,$priv,$priv,$priv,$priv,$ref);if(!$st->execute())throw new RuntimeException('legacy user update failed: '.$st->error);}
        } else {
            $st=$db->prepare("INSERT INTO user(name,dn,email,priv_view,priv_insert,priv_update,priv_pin,priv_relsub,priv_critical,rolefk,valid_start,valid_end,enabled) VALUES(?,?,?,?,?,?,?,?,?,(SELECT ref FROM role WHERE description=? LIMIT 1),NOW(),DATE_ADD(NOW(), INTERVAL 20 YEAR),1)");if(!$st)throw new RuntimeException('legacy user insert prepare failed: '.$db->error);$st->bind_param('sssiiiiiis',$username,$dn,$email,$priv,$priv,$priv,$priv,$priv,$priv,$role);if(!$st->execute())throw new RuntimeException('legacy user insert failed: '.$st->error);$ref=(int)$db->insert_id;
        }
        $synced[$username]=$ref; atlas_auth_log('local_legacy_identity_synced',['username'=>$username,'role'=>$role,'legacy_ref'=>$ref]); return $ref;
    } catch(Throwable $e){ atlas_auth_log('local_legacy_identity_sync_failed',['username'=>$username,'error'=>$e->getMessage()]); return null; }
}

function atlas_local_identity(): ?array {
    $token=(string)($_COOKIE[ATLAS_LOCAL_COOKIE]??''); if($token==='') return null;
    try { atlas_local_auth_schema(); $db=atlas_local_db(); $hash=hash('sha256',$token); $st=$db->prepare("SELECT s.id session_id,u.* FROM atlas_local_session s JOIN atlas_local_user u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>NOW() AND u.enabled=1 LIMIT 1"); if(!$st) throw new RuntimeException('local session prepare failed: '.$db->error); $st->bind_param('s',$hash); if(!$st->execute()) throw new RuntimeException('local session lookup failed: '.$st->error); $r=$st->get_result()->fetch_assoc(); if(!$r)return null; $sid=(int)$r['session_id']; $db->query('UPDATE atlas_local_session SET last_seen_at=NOW() WHERE id='.$sid); $id=['source'=>'local','id'=>(int)$r['id'],'username'=>(string)$r['username'],'name'=>trim((string)$r['first_name'].' '.(string)$r['last_name'])?: (string)$r['username'],'first_name'=>(string)$r['first_name'],'last_name'=>(string)$r['last_name'],'email'=>(string)$r['email'],'role'=>(string)$r['role'],'enabled'=>(int)$r['enabled'],'dn'=>'','must_change_password'=>(int)$r['must_change_password']]; $id['legacy_ref']=atlas_sync_local_legacy_user($id); return $id; } catch(Throwable $e){ atlas_auth_log('local_session_lookup_failed',['error'=>$e->getMessage()]); return null; }
}
function atlas_current_identity(): ?array { static $cached=false,$id=null; if($cached)return $id; $cached=true; $id=atlas_local_identity(); if($id)return $id; return $id=atlas_cert_identity(); }
function atlas_is_authenticated(): bool { $i=atlas_current_identity(); return $i!==null && (int)($i['enabled']??1)===1; }
function atlas_has_role(array $roles): bool { $i=atlas_current_identity(); return $i!==null && in_array((string)($i['role']??''),$roles,true); }

function atlas_create_local_session(int $uid): void {
    atlas_local_auth_schema(); $db=atlas_local_db();
    $ur=$db->query('SELECT * FROM atlas_local_user WHERE id='.(int)$uid.' LIMIT 1');
    $ui=$ur?$ur->fetch_assoc():null;
    if($ui){$tmp=['source'=>'local','id'=>(int)$ui['id'],'username'=>(string)$ui['username'],'name'=>trim((string)$ui['first_name'].' '.(string)$ui['last_name'])?: (string)$ui['username'],'first_name'=>(string)$ui['first_name'],'last_name'=>(string)$ui['last_name'],'email'=>(string)$ui['email'],'role'=>(string)$ui['role'],'enabled'=>(int)$ui['enabled'],'dn'=>'','must_change_password'=>(int)$ui['must_change_password']]; $tmp['legacy_ref']=atlas_sync_local_legacy_user($tmp);}
    $hours=max(1,min(168,(int)atlas_auth_setting('session_hours','8'))); $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $expires=date('Y-m-d H:i:s',time()+$hours*3600); $ip=(string)($_SERVER['REMOTE_ADDR']??''); $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255); $st=$db->prepare('INSERT INTO atlas_local_session(user_id,token_hash,expires_at,remote_addr,user_agent) VALUES(?,?,?,?,?)'); $st->bind_param('issss',$uid,$hash,$expires,$ip,$ua); $st->execute(); setcookie(ATLAS_LOCAL_COOKIE,$token,['expires'=>time()+$hours*3600,'path'=>'/atlas_install','secure'=>true,'httponly'=>true,'samesite'=>'Lax']); atlas_auth_log('local_login_success',['user_id'=>$uid,'session_hours'=>$hours]); }
function atlas_logout_local(): void { $token=(string)($_COOKIE[ATLAS_LOCAL_COOKIE]??''); if($token!==''){try{$db=atlas_local_db();$h=hash('sha256',$token);$st=$db->prepare('DELETE FROM atlas_local_session WHERE token_hash=?');$st->bind_param('s',$h);$st->execute();}catch(Throwable $e){}} setcookie(ATLAS_LOCAL_COOKIE,'',['expires'=>1,'path'=>'/atlas_install','secure'=>true,'httponly'=>true,'samesite'=>'Lax']); }
function atlas_user_totps(int $uid,bool $enabledOnly=false): array { atlas_local_auth_schema(); $db=atlas_local_db(); $q='SELECT id,label,secret_enc,enabled,created_at FROM atlas_local_totp WHERE user_id=?'.($enabledOnly?' AND enabled=1':'').' ORDER BY id'; $st=$db->prepare($q);$st->bind_param('i',$uid);$st->execute();return $st->get_result()->fetch_all(MYSQLI_ASSOC); }
function atlas_verify_user_totp(int $uid,string $code): bool { foreach(atlas_user_totps($uid,true) as $t){$s=atlas_secret_decrypt((string)$t['secret_enc']);if($s!==''&&atlas_totp_verify($s,$code))return true;} return false; }
function atlas_local_user_by_username(string $username): ?array { atlas_local_auth_schema();$db=atlas_local_db();$st=$db->prepare('SELECT * FROM atlas_local_user WHERE username=? LIMIT 1');$st->bind_param('s',$username);$st->execute();$r=$st->get_result()->fetch_assoc();return $r?:null; }

function atlas_require_master(): void { if(!atlas_has_role(['master'])) atlas_access_denied(atlas_t('master_required')); }
?>