<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    $conn = db_conn('ro');
    $ok = $conn->query('SELECT 1');
    if (!$ok) throw new RuntimeException('query failed');
    if (filter_var(atlas_env('ATLAS_DB_AUTO_INIT','1'), FILTER_VALIDATE_BOOL)) {
        $required=['atlas_local_user','atlas_local_totp','atlas_local_session','atlas_auth_setting'];
        $missing=[];
        foreach($required as $table){
            $e=$conn->real_escape_string($table);
            $r=$conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$e."' LIMIT 1");
            if(!$r || !$r->fetch_row()) $missing[]=$table;
        }
        if($missing) throw new RuntimeException('local auth schema missing: '.implode(',',$missing));
    }
    echo json_encode(['status' => 'ready'], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    error_log('[ATLAS_APP] '.json_encode(['event'=>'readiness_failed','reason'=>$e->getMessage(),'request_id'=>atlas_request_id()],JSON_UNESCAPED_SLASHES));
    http_response_code(503);
    echo json_encode(['status' => 'not-ready'], JSON_UNESCAPED_SLASHES) . "\n";
}
