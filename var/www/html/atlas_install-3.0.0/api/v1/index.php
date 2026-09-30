<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-API-Version: v1');

function api_json(mixed $payload, int $status=200, array $headers=[]): never {
    http_response_code($status);
    foreach ($headers as $k=>$v) header($k.': '.$v);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    exit;
}
function api_error(int $status, string $code, string $message, array $details=[]): never {
    api_json(['error'=>['status'=>$status,'code'=>$code,'message'=>$message,'request_id'=>atlas_request_id(),'details'=>(object)$details]], $status);
}
function api_body(): array {
    $raw=file_get_contents('php://input');
    if ($raw===false || trim($raw)==='') return [];
    $ct=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    if (!str_contains($ct,'application/json')) api_error(415,'unsupported_media_type','Use Content-Type: application/json');
    $data=json_decode($raw,true);
    if (!is_array($data) || json_last_error()!==JSON_ERROR_NONE) api_error(400,'invalid_json','Request body must contain a JSON object');
    return $data;
}
function api_basic_identity(): ?array {
    $auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
    if (!str_starts_with($auth,'Basic ')) return null;
    $decoded=base64_decode(substr($auth,6),true);
    if ($decoded===false || !str_contains($decoded,':')) return null;
    [$username,$password]=explode(':',$decoded,2);
    if ($username==='' || $password==='') return null;
    try {
        $u=atlas_local_user_by_username($username);
        if (!$u || !(int)$u['enabled'] || !password_verify($password,(string)$u['password_hash'])) {
            atlas_auth_log('api_basic_login_failed',['username'=>$username]);
            return null;
        }
        if ((int)($u['must_change_password']??0)===1) api_error(403,'password_change_required','The local account must change its password before API access');
        $totps=atlas_user_totps((int)$u['id'],true);
        if ($totps) {
            $code=trim((string)($_SERVER['HTTP_X_ATLAS_TOTP']??''));
            if ($code==='' || !atlas_verify_user_totp((int)$u['id'],$code)) api_error(401,'totp_required','A valid X-ATLAS-TOTP header is required');
        }
        return ['source'=>'basic','id'=>(int)$u['id'],'username'=>(string)$u['username'],'name'=>trim((string)$u['first_name'].' '.(string)$u['last_name']) ?: (string)$u['username'],'email'=>(string)$u['email'],'role'=>(string)$u['role'],'enabled'=>(int)$u['enabled'],'dn'=>''];
    } catch(Throwable $e) {
        atlas_auth_log('api_basic_auth_error',['username'=>$username,'error'=>$e->getMessage()]);
        return null;
    }
}
function api_identity(): ?array {
    $i=atlas_current_identity();
    if ($i && (int)($i['enabled']??1)===1) return $i;
    return api_basic_identity();
}
function api_require_auth(bool $master=false): array {
    $i=api_identity();
    if (!$i) {
        header('WWW-Authenticate: Basic realm="LJSF 3 REST API", charset="UTF-8"');
        api_error(401,'authentication_required','Authenticate with a client certificate, local session, or HTTP Basic local account');
    }
    if ($master && (string)($i['role']??'')!=='master') api_error(403,'forbidden','The master role is required for this resource');
    return $i;
}
function api_resources(): array {
    return [
      'releases'=>['table'=>'release_data','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','build','typefk','archfk','sw_archfk','userfk','obsolete','autoinstall','requires','dbrelease','installer_version','install_tools_version','sw_name','sw_revision','sw_physicalpath','sw_logicalpath','tag','package','comments','date','cvmfs_available','critical','critical_validity_from','critical_validity_to','critical_description'],'search'=>['name','tag','package','comments']],
      'sites'=>['table'=>'site','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','cs','cename','name','atlas_name','tier_level','gridfk','facilityfk','activity_typefk','osname','osrelease','osversion','arch','tags','alias','swarea','fstype','mountpoint','capacity','available','quota','status','attr','host_status','resource_status','last_activity'],'search'=>['cs','cename','name','atlas_name','alias','arch']],
      'requests'=>['table'=>'request','key'=>'id','key_type'=>'s','read'=>'auth','write'=>'auth','fields'=>['id','bdiifk','sitefk','relfk','typefk','userfk','adminfk','statusfk','force_run','request_date','update_date','user_comments','admin_comments'],'search'=>['id','user_comments','admin_comments']],
      'tasks'=>['table'=>'task','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','description','target'],'search'=>['name','description','target']],
      'architectures'=>['table'=>'release_arch','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','platform_type','os_type','gcc_ver','mode','description'],'search'=>['platform_type','os_type','gcc_ver','mode','description']],
      'targets'=>['table'=>'autoinstall_target','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','description'],'search'=>['name','description']],
      'infosys'=>['table'=>'bdii','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','facilityfk','hostname','port','ns','lb','ld','wmproxy','myproxy','preferred','enabled'],'search'=>['hostname','ns','lb','ld','wmproxy','myproxy']],
      'release-subscriptions'=>['table'=>'release_subscription','key'=>'ref','key_type'=>'i','read'=>'auth','write'=>'auth','fields'=>['ref','sitename','userfk','pattern','date','comment'],'search'=>['sitename','pattern','comment']],
      'release-status'=>['table'=>'release_stat','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','sitefk','name','userfk','pin','pinuserfk','pindate','tag','status','comments','date'],'search'=>['name','tag','status','comments']],
      'grids'=>['table'=>'grid','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','description'],'search'=>['name','description']],
      'facilities'=>['table'=>'facility','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','description'],'search'=>['name','description']],
      'site-types'=>['table'=>'site_type','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'auth','fields'=>['ref','name','description'],'search'=>['name','description']],
      'request-statuses'=>['table'=>'request_status','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'master','fields'=>['ref','description'],'search'=>['description']],
      'request-types'=>['table'=>'request_type','key'=>'ref','key_type'=>'i','read'=>'public','write'=>'master','fields'=>['ref','level','field','description','comment'],'search'=>['field','description','comment']],
      'users'=>['table'=>'user','key'=>'ref','key_type'=>'i','read'=>'master','write'=>'master','fields'=>['ref','name','dn','ca_dn','ca_name','email','priv_view','priv_insert','priv_update','priv_pin','priv_relsub','priv_critical','rolefk','valid_start','valid_end','enabled','deleted_at','deleted_by'],'search'=>['name','dn','email']],
      'local-users'=>['table'=>'atlas_local_user','key'=>'id','key_type'=>'i','read'=>'master','write'=>'master','fields'=>['id','username','first_name','last_name','email','role','enabled','must_change_password','created_at','updated_at'],'search'=>['username','first_name','last_name','email','role'],'local'=>true],
    ];
}
function api_authz(array $cfg, string $method): void {
    $mode=in_array($method,['GET','HEAD','OPTIONS'],true)?$cfg['read']:$cfg['write'];
    if ($mode==='public') return;
    api_require_auth($mode==='master');
}
function api_qi(string $s): string { return '`'.str_replace('`','``',$s).'`'; }
function api_bind(mysqli_stmt $st, string $types, array &$vals): void { if($vals) $st->bind_param($types,...$vals); }
function api_cast_key(string $raw, string $type): int|string {
    if ($type==='i') { if(!preg_match('/^\d+$/D',$raw)) api_error(400,'invalid_id','Resource id must be an integer'); return (int)$raw; }
    if ($raw==='' || strlen($raw)>128) api_error(400,'invalid_id','Invalid resource id'); return $raw;
}
function api_list(string $name,array $cfg): never {
    $db=db_conn('ro'); $fields=$cfg['fields']; $where=[];$types='';$vals=[];
    foreach($fields as $f){ if(isset($_GET[$f]) && $_GET[$f]!==''){ $where[]=api_qi($f).'=?'; $types.='s'; $vals[]=(string)$_GET[$f]; }}
    $q=trim((string)($_GET['q']??'')); if($q!=='') { $ors=[]; foreach($cfg['search'] as $f)$ors[]=api_qi($f).' LIKE ?'; if($ors){$where[]='('.implode(' OR ',$ors).')';foreach($ors as $_){$types.='s';$vals[]='%'.$q.'%';}} }
    $limit=max(1,min(1000,(int)($_GET['limit']??200))); $offset=max(0,(int)($_GET['offset']??0));
    $sort=(string)($_GET['sort']??$cfg['key']); if(!in_array($sort,$fields,true))$sort=$cfg['key']; $order=strtoupper((string)($_GET['order']??'ASC')); if(!in_array($order,['ASC','DESC'],true))$order='ASC';
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $countSql='SELECT COUNT(*) c FROM '.api_qi($cfg['table']).$whereSql; $st=$db->prepare($countSql); api_bind($st,$types,$vals);$st->execute();$total=(int)$st->get_result()->fetch_assoc()['c'];
    $sql='SELECT '.implode(',',array_map('api_qi',$fields)).' FROM '.api_qi($cfg['table']).$whereSql.' ORDER BY '.api_qi($sort).' '.$order.' LIMIT ? OFFSET ?';
    $vals2=$vals;$types2=$types.'ii';$vals2[]=$limit;$vals2[]=$offset;$st=$db->prepare($sql);api_bind($st,$types2,$vals2);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    api_json(['data'=>$items,'meta'=>['resource'=>$name,'total'=>$total,'limit'=>$limit,'offset'=>$offset,'count'=>count($items)]]);
}
function api_get_one(string $name,array $cfg,string $rawId): never {
    $id=api_cast_key($rawId,$cfg['key_type']);$db=db_conn('ro');$sql='SELECT '.implode(',',array_map('api_qi',$cfg['fields'])).' FROM '.api_qi($cfg['table']).' WHERE '.api_qi($cfg['key']).'=? LIMIT 1';$st=$db->prepare($sql);$types=$cfg['key_type'];$vals=[$id];api_bind($st,$types,$vals);$st->execute();$row=$st->get_result()->fetch_assoc();if(!$row)api_error(404,'not_found',ucfirst($name).' resource not found');api_json(['data'=>$row]);
}
function api_write_fields(array $cfg,array $body,bool $creating): array {
    $allowed=array_flip($cfg['fields']); if(!$creating || $cfg['key_type']==='i') unset($allowed[$cfg['key']]);
    $out=[]; foreach($body as $k=>$v){ if($k==='password' && !empty($cfg['local']))continue; if(!isset($allowed[$k])) api_error(400,'unknown_field','Unknown or immutable field: '.$k); if(is_array($v)||is_object($v))api_error(400,'invalid_field','Scalar value required for '.$k);$out[$k]=$v; }
    if(!empty($cfg['local']) && array_key_exists('password',$body)){ $p=(string)$body['password']; if(strlen($p)<8)api_error(400,'weak_password','Local-user passwords must contain at least 8 characters');$out['password_hash']=password_hash($p,PASSWORD_DEFAULT); }
    if($creating && !$out)api_error(400,'empty_body','At least one writable field is required'); return $out;
}
function api_create(string $name,array $cfg): never {
    $body=api_body();$fields=api_write_fields($cfg,$body,true);$db=db_conn('rw');$cols=array_keys($fields);$vals=array_values($fields);$sql='INSERT INTO '.api_qi($cfg['table']).' ('.implode(',',array_map('api_qi',$cols)).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';$st=$db->prepare($sql);if(!$st)api_error(400,'invalid_resource',$db->error);$types=str_repeat('s',count($vals));api_bind($st,$types,$vals);if(!$st->execute())api_error(409,'write_failed',$st->error);$id=$cfg['key_type']==='i'?(int)$db->insert_id:(string)($body[$cfg['key']]??'');if($id==='' && isset($body[$cfg['key']]))$id=(string)$body[$cfg['key']];$loc='/atlas_install/api/v1/'.$name.'/'.rawurlencode((string)$id);api_json(['data'=>['id'=>$id],'meta'=>['location'=>$loc]],201,['Location'=>$loc]);
}
function api_update(string $name,array $cfg,string $rawId): never {
    $id=api_cast_key($rawId,$cfg['key_type']);$body=api_body();$fields=api_write_fields($cfg,$body,false);if(!$fields)api_error(400,'empty_body','At least one writable field is required');$db=db_conn('rw');$sets=[];$vals=[];foreach($fields as $k=>$v){$sets[]=api_qi($k).'=?';$vals[]=$v;}$vals[]=$id;$types=str_repeat('s',count($fields)).$cfg['key_type'];$sql='UPDATE '.api_qi($cfg['table']).' SET '.implode(',',$sets).' WHERE '.api_qi($cfg['key']).'=?';$st=$db->prepare($sql);if(!$st)api_error(400,'invalid_resource',$db->error);api_bind($st,$types,$vals);if(!$st->execute())api_error(409,'write_failed',$st->error);if($st->affected_rows===0){$check=$db->prepare('SELECT 1 FROM '.api_qi($cfg['table']).' WHERE '.api_qi($cfg['key']).'=?');$v=[$id];api_bind($check,$cfg['key_type'],$v);$check->execute();if(!$check->get_result()->fetch_row())api_error(404,'not_found',ucfirst($name).' resource not found');}api_get_one($name,$cfg,(string)$id);
}
function api_delete(string $name,array $cfg,string $rawId): never {
    $id=api_cast_key($rawId,$cfg['key_type']);$db=db_conn('rw');
    // Legacy users are historical identities referenced by requests, logs and
    // release records. DELETE therefore means soft-delete for this resource.
    if($name==='users'){
        $sql='UPDATE user SET enabled=0,valid_end=NOW(),priv_view=0,priv_insert=0,priv_update=0,priv_pin=0,priv_relsub=0,priv_critical=0,deleted_at=NOW() WHERE ref=?';
        $st=$db->prepare($sql);$vals=[$id];api_bind($st,$cfg['key_type'],$vals);if(!$st->execute())api_error(409,'delete_failed',$st->error);if($st->affected_rows===0)api_error(404,'not_found','Users resource not found');http_response_code(204);exit;
    }
    $sql='DELETE FROM '.api_qi($cfg['table']).' WHERE '.api_qi($cfg['key']).'=?';$st=$db->prepare($sql);$vals=[$id];api_bind($st,$cfg['key_type'],$vals);if(!$st->execute())api_error(409,'delete_failed',$st->error);if($st->affected_rows===0)api_error(404,'not_found',ucfirst($name).' resource not found');http_response_code(204);exit;
}

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if($method==='OPTIONS'){header('Allow: GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS');api_json(['api'=>'v1','methods'=>['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']]);}
$path=trim((string)($_GET['__path']??($_SERVER['PATH_INFO']??'')),'/'); if($path===''){ $uri=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??''); $prefix='/atlas_install/api/v1'; if(str_starts_with($uri,$prefix)) $path=trim(substr($uri,strlen($prefix)),'/'); }
if($path==='' || $path==='index.php') api_json(['name'=>'LJSF 3 REST API','version'=>'v1','documentation'=>'/atlas_install/documentation.php?section=api','resources'=>array_keys(api_resources())]);
if($path==='openapi') {
    $paths=[]; foreach(api_resources() as $rn=>$rc){
        $paths['/atlas_install/api/v1/'.$rn]=['get'=>['summary'=>'List '.$rn],'post'=>['summary'=>'Create '.$rn]];
        $paths['/atlas_install/api/v1/'.$rn.'/{id}']=['get'=>['summary'=>'Get '.$rn.' item'],'patch'=>['summary'=>'Update '.$rn.' item'],'put'=>['summary'=>'Update '.$rn.' item'],'delete'=>['summary'=>'Delete '.$rn.' item'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>$rc['key_type']==='i'?'integer':'string']]]];
    }
    api_json(['openapi'=>'3.1.0','info'=>['title'=>'LJSF 3 REST API','version'=>'1.0'],'servers'=>[['url'=>'/atlas_install/api/v1']],'paths'=>$paths]);
}
if($path==='health') api_json(['status'=>'ok','api'=>'v1','request_id'=>atlas_request_id()]);
if($path==='me'){ $i=api_require_auth(false); unset($i['password_hash']); api_json(['data'=>$i]); }
$parts=explode('/',$path);$resource=$parts[0]??'';$id=$parts[1]??null;if(count($parts)>2)api_error(404,'route_not_found','Unknown API route');$resources=api_resources();if(!isset($resources[$resource]))api_error(404,'resource_not_found','Unknown API resource');$cfg=$resources[$resource];api_authz($cfg,$method);
try {
    if(($method==='GET'||$method==='HEAD') && $id===null) api_list($resource,$cfg);
    if(($method==='GET'||$method==='HEAD') && $id!==null) api_get_one($resource,$cfg,$id);
    if($method==='POST' && $id===null) api_create($resource,$cfg);
    if(in_array($method,['PUT','PATCH'],true) && $id!==null) api_update($resource,$cfg,$id);
    if($method==='DELETE' && $id!==null) api_delete($resource,$cfg,$id);
    api_error(405,'method_not_allowed','Method not allowed for this route');
} catch(mysqli_sql_exception $e) { api_error(409,'database_conflict',$e->getMessage()); }
  catch(InvalidArgumentException $e) { api_error(400,'invalid_request',$e->getMessage()); }
  catch(Throwable $e) { error_log('[ATLAS_APP] '.json_encode(['event'=>'api_exception','request_id'=>atlas_request_id(),'message'=>$e->getMessage(),'route'=>$path],JSON_UNESCAPED_SLASHES)); api_error(500,'internal_error','Internal API error'); }
