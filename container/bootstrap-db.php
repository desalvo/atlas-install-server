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
function connect_db(string $db=''): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $m=mysqli_init(); if(!$m) throw new RuntimeException('mysqli_init failed');
    $flags=0;
    if(boolenv('ATLAS_DB_SSL',false)) {
        $verify=boolenv('ATLAS_DB_SSL_VERIFY',false); $ca=trim(envfile('ATLAS_DB_SSL_CA',''));
        if(defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) @$m->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT,$verify);
        @mysqli_ssl_set($m,null,null,$ca!==''?$ca:null,null,null); $flags|=MYSQLI_CLIENT_SSL;
    }
    @$m->real_connect(envfile('ATLAS_DB_RW_HOST','127.0.0.1'),envfile('ATLAS_DB_RW_USER',''),envfile('ATLAS_DB_RW_PASSWORD',''),$db,3306,null,$flags);
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

$dbname=envfile('ATLAS_DB_NAME','atlas_install_panda');
if(!preg_match('/^[A-Za-z0-9_]+$/D',$dbname)) throw new RuntimeException('unsafe ATLAS_DB_NAME');
$root='/var/www/html/atlas_install';
$schema="$root/conf/sql/create_install_db.sql.template";
$auth='/opt/atlas/local-auth-schema.sql';

try {
    $server=connect_db('');
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
    $db=connect_db($dbname);
    $required=['atlas_local_user','atlas_local_totp','atlas_local_session','atlas_auth_setting']; $missing=[];
    foreach($required as $t) {
        $st=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1');
        $st->bind_param('ss',$dbname,$t); $st->execute(); if(!$st->get_result()->fetch_row()) $missing[]=$t;
    }
    if($missing) {
        logmsg('local authentication tables missing: '.implode(',',$missing).'; initializing');
        $sql=@file_get_contents($auth); if($sql===false) throw new RuntimeException("cannot read $auth");
        run_multi($db,$sql,'local authentication schema initialization');
        logmsg('local authentication schema initialized');
    }
    exit(0);
} catch(Throwable $e) {
    logmsg('WARNING: '.$e->getMessage());
    exit(1);
}
