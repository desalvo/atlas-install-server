<?php
declare(strict_types=1);

function logmsg(string $message): void { fwrite(STDERR, "[atlas-db-bootstrap] $message\n"); }
function envfile(string $name, string $default=''): string {
    static $cfg=null;
    if (($v=getenv($name)) !== false && $v !== '') return (string)$v;
    if ($cfg===null) {
        $cfg=[]; $path=getenv('ATLAS_ENV_FILE') ?: '/var/lib/atlas-install/config/atlas-install.env';
        foreach (@file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line=trim($line); if($line===''||$line[0]==='#'||!str_contains($line,'=')) continue;
            [$k,$v]=explode('=',$line,2); $k=trim($k); $v=trim($v);
            if(strlen($v)>=2 && $v[0]==='"' && $v[strlen($v)-1]==='"') $v=stripcslashes(substr($v,1,-1));
            $cfg[$k]=$v;
        }
    }
    return isset($cfg[$name]) && $cfg[$name]!=='' ? (string)$cfg[$name] : $default;
}
function boolenv(string $name, bool $default=false): bool {
    return filter_var(envfile($name,$default?'1':'0'), FILTER_VALIDATE_BOOL);
}
function connect_db(string $db='', bool $bootstrap=false): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $m=mysqli_init(); if(!$m) throw new RuntimeException('mysqli_init failed');
    $flags=0;
    if(boolenv('ATLAS_DB_SSL',false)) {
        $verify=boolenv('ATLAS_DB_SSL_VERIFY',false); $ca=trim(envfile('ATLAS_DB_SSL_CA',''));
        if(defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) @$m->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT,$verify);
        @mysqli_ssl_set($m,null,null,$ca!==''?$ca:null,null,null); $flags|=MYSQLI_CLIENT_SSL;
    }
    
    $user=$bootstrap ? envfile('ATLAS_DB_BOOTSTRAP_USER',envfile('ATLAS_DB_RW_USER','')) : envfile('ATLAS_DB_RW_USER','');
    $pass=$bootstrap ? envfile('ATLAS_DB_BOOTSTRAP_PASSWORD',envfile('ATLAS_DB_RW_PASSWORD','')) : envfile('ATLAS_DB_RW_PASSWORD','');
    @$m->real_connect(envfile('ATLAS_DB_RW_HOST','127.0.0.1'),$user,$pass,$db,3306,null,$flags);
    if($m->connect_errno) throw new RuntimeException('DB connect failed '.$m->connect_errno.': '.$m->connect_error);
    return $m;
}
function run_multi(mysqli $db,string $sql,string $label): void {
    if(!@$db->multi_query($sql)) throw new RuntimeException("$label failed: ".$db->error);
    do {
        if($r=$db->store_result()) $r->free();
        if(!$db->more_results()) break;
        if(!@$db->next_result()) throw new RuntimeException("$label failed: ".$db->error);
    } while(true);
}


function table_exists(mysqli $db,string $schema,string $table): bool {
    $st=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1');
    $st->bind_param('ss',$schema,$table); $st->execute(); return (bool)$st->get_result()->fetch_row();
}
function column_exists(mysqli $db,string $schema,string $table,string $column): bool {
    $st=$db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $st->bind_param('sss',$schema,$table,$column); $st->execute(); return (bool)$st->get_result()->fetch_row();
}
function index_exists(mysqli $db,string $schema,string $table,string $index): bool {
    $st=$db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
    $st->bind_param('sss',$schema,$table,$index); $st->execute(); return (bool)$st->get_result()->fetch_row();
}
function ensure_column(mysqli $db,string $schema,string $table,string $column,string $ddl): void {
    if(!table_exists($db,$schema,$table)) return;
    if(column_exists($db,$schema,$table,$column)) return;
    logmsg("adding missing column $table.$column");
    if(!@$db->query("ALTER TABLE `".$db->real_escape_string($table)."` ADD COLUMN ".$ddl)) throw new RuntimeException("add column $table.$column failed: ".$db->error);
}
function ensure_index(mysqli $db,string $schema,string $table,string $index,string $columns): void {
    if(!table_exists($db,$schema,$table)) return;
    if(index_exists($db,$schema,$table,$index)) return;
    logmsg("adding missing index $table.$index");
    $sql="ALTER TABLE `".$db->real_escape_string($table)."` ADD INDEX `".$db->real_escape_string($index)."` (".$columns.")";
    if(!@$db->query($sql)) throw new RuntimeException("add index $table.$index failed: ".$db->error);
}
function migrate_schema(mysqli $db,string $schema): void {
    // Migration ledger is deliberately independent from the historical schema_version table.
    if(!@$db->query("CREATE TABLE IF NOT EXISTS atlas_schema_migration (name varchar(128) NOT NULL, applied_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) throw new RuntimeException('migration ledger create failed: '.$db->error);
    // Objects introduced/required by LJSF 3. These ALTERs are safe after restoring an older dump.
    ensure_column($db,$schema,'request','force_run','`force_run` int(11) NOT NULL DEFAULT 0 AFTER `statusfk`');
    ensure_index($db,$schema,'request','request_sitefk_indx','`sitefk`');
    ensure_index($db,$schema,'request','request_userfk_indx','`userfk`');
    ensure_index($db,$schema,'request','request_typefk_indx','`typefk`');
    ensure_index($db,$schema,'request','request_adminfk_indx','`adminfk`');
    ensure_index($db,$schema,'request','request_request_date_indx','`request_date`');
    ensure_index($db,$schema,'request','request_status_date_indx','`statusfk`,`request_date`');
    // r32: bind X.509 identities to their issuing CA and preserve historical users on deletion.
    ensure_column($db,$schema,'user','ca_dn','`ca_dn` varchar(255) DEFAULT NULL AFTER `dn`');
    ensure_column($db,$schema,'user','ca_name','`ca_name` varchar(255) DEFAULT NULL AFTER `ca_dn`');
    ensure_column($db,$schema,'user','deleted_at','`deleted_at` datetime DEFAULT NULL AFTER `enabled`');
    ensure_column($db,$schema,'user','deleted_by','`deleted_by` int(11) DEFAULT NULL AFTER `deleted_at`');
    ensure_index($db,$schema,'user','user_dn_indx','`dn`');
    ensure_index($db,$schema,'user','user_ca_dn_indx','`ca_dn`');
    ensure_index($db,$schema,'user','user_deleted_at_indx','`deleted_at`');
    @$db->query("INSERT IGNORE INTO atlas_schema_migration(name) VALUES ('r29-request-indexes-and-force-run')");
    @$db->query("INSERT IGNORE INTO atlas_schema_migration(name) VALUES ('r32-user-ca-and-soft-delete')");
}

$dbname=envfile('ATLAS_DB_NAME','atlas_install_panda');
if(!preg_match('/^[A-Za-z0-9_]+$/D',$dbname)) throw new RuntimeException('unsafe ATLAS_DB_NAME');
$root='/var/www/html/atlas_install';
$schema="$root/conf/sql/create_install_db.sql.template";
$auth='/opt/atlas/local-auth-schema.sql';

try {
    $server=connect_db('', true);
    logmsg('using database bootstrap account '.envfile('ATLAS_DB_BOOTSTRAP_USER',envfile('ATLAS_DB_RW_USER','')).' for schema checks');
    $q=$server->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $q->bind_param('s',$dbname); $q->execute(); $exists=(bool)$q->get_result()->fetch_row();
    if(!$exists) {
        logmsg("database $dbname is missing; initializing default application schema");
        $sql=@file_get_contents($schema); if($sql===false) throw new RuntimeException("cannot read $schema");
        $sql=str_replace('$DBNAME',$dbname,$sql);
        // Account provisioning belongs to Kubernetes/MariaDB operator, not application bootstrap.
        $sql=preg_replace('/^CREATE USER .*$/mi','',$sql);
        $sql=preg_replace('/^GRANT .*$/mi','',$sql);
        $sql=preg_replace('/^FLUSH PRIVILEGES;.*$/mi','',$sql);
        // Remaining placeholders only occur in removed account-management lines; reject unexpected ones.
        if(preg_match('/@[A-Z0-9_]+@|\$DB[A-Z_]+/',$sql,$m)) throw new RuntimeException('unresolved SQL placeholder '.$m[0]);
        run_multi($server,$sql,'application schema initialization');
        logmsg("default application schema initialized in $dbname");
    }
    $db=connect_db($dbname, true);
    $required=['atlas_local_user','atlas_local_totp','atlas_local_session','atlas_auth_setting']; $missing=[];
    foreach($required as $t) {
        if(!table_exists($db,$dbname,$t)) $missing[]=$t;
    }
    if($missing) {
        logmsg('local authentication tables missing: '.implode(',',$missing).'; initializing');
        $sql=@file_get_contents($auth); if($sql===false) throw new RuntimeException("cannot read $auth");
        run_multi($db,$sql,'local authentication schema initialization');
        logmsg('local authentication schema initialized');
    }
    migrate_schema($db,$dbname);
    logmsg('application schema migration check completed');
    exit(0);
} catch(Throwable $e) {
    logmsg('WARNING: '.$e->getMessage());
    exit(1);
}
