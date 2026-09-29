<?php
  require_once("config.php");
  require_once("db.php");
  function curPageURL() {
      $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
      $https = $forwardedProto === 'https' || strtolower((string)($_SERVER['HTTPS'] ?? '')) === 'on' || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
      $host = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0] ?? '');
      if ($host === '') $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
      return ($https ? 'https' : 'http').'://'.$host.(string)($_SERVER['REQUEST_URI'] ?? '/');
   }

   if (isset($LJSFi_access_log) and $LJSFi_access_log) {
       $dn = getenv("SSL_CLIENT_S_DN");
       if (!isset($dn) || $dn == "") {
           $userfk = -1;
       } else {
           $result = db_query_rest("SELECT ref FROM user WHERE dn=".db_quote($dn,'ro')." ORDER BY valid_end DESC, ref ASC",'ro');
           $row = $result ? mysqli_fetch_row($result) : false;
           if (!$row) {
               $userfk = -2;
           } else {
               $userfk = $row[0];
           }
       }
       $sourceip = (string)($_SERVER["REMOTE_ADDR"] ?? "");
       $result = db_query_rest("SELECT ref FROM access_ip WHERE ip=".db_quote($sourceip,'rw'));
       $sourceiprow = $result ? mysqli_fetch_row($result) : false;
       if (!$sourceiprow) {
           db_query_rest("INSERT INTO access_ip (ip) VALUES (".db_quote($sourceip,'rw').")");
           $result = db_query_rest("SELECT ref FROM access_ip WHERE ip=".db_quote($sourceip,'rw'));
           $sourceiprow = $result ? mysqli_fetch_row($result) : false;
       }
       $destip = (string)($_SERVER["SERVER_ADDR"] ?? "");
       $result = db_query_rest("SELECT ref FROM access_ip WHERE ip=".db_quote($destip,'rw'));
       $destiprow = $result ? mysqli_fetch_row($result) : false;
       if (!$destiprow) {
           db_query_rest("INSERT INTO access_ip (ip) VALUES (".db_quote($destip,'rw').")");
           $result = db_query_rest("SELECT ref FROM access_ip WHERE ip=".db_quote($destip,'rw'));
           $destiprow = $result ? mysqli_fetch_row($result) : false;
       }
       if ($sourceiprow and $destiprow) {
           $sourceipfk = $sourceiprow[0];
           $destipfk = $destiprow[0];
           $URL=curPageURL();
           $result = db_query_rest("SELECT ref FROM access_url WHERE url=".db_quote($URL,'rw'));
           $row = $result ? mysqli_fetch_row($result) : false;
           if (!$row) {
               db_query_rest("INSERT INTO access_url (url) VALUES (".db_quote($URL,'rw').")");
               $result = db_query_rest("SELECT ref FROM access_url WHERE url=".db_quote($URL,'rw'));
               $row = $result ? mysqli_fetch_row($result) : false;
           }
           if ($row) {
               $URLref = $row[0];
               date_default_timezone_set("GMT");
               $now = date("Y-m-d H:i:s");
               if (isset($_SERVER["QUERY_STRING"])) {
                   $qrystr = db_quote($_SERVER["QUERY_STRING"],'rw');
               } else {
                   $qrystr = "NULL";
               }
               $query = "INSERT INTO access_log (urlfk,userfk,sourceipfk,destipfk,query,date) VALUES ("
                      . db_int($URLref,0) . ","
                      . db_int($userfk,-2) . ","
                      . db_int($sourceipfk,0) . ","
                      . db_int($destipfk,0) . ","
                      . $qrystr . ","
                      . db_quote($now,'rw') . ")";
               db_query_rest($query);
           }
       }
   }
?>
