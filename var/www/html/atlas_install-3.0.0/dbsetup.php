<?php
  $dbconn       = NULL;

  // Connection function
  function db_conn($dest="rw") {
    require("config.php");
    global $dbconn;
    $dbconn = atlas_mysqli_connect($LJSFi_dbserv[$dest],$LJSFi_dbuser[$dest],$LJSFi_dbpass[$dest],$LJSFi_dbname,3306);
    if (!$dbconn) {
      error_log("[ATLAS_APP] ".json_encode(["event"=>"db_connect_failed","dest"=>$dest,"host"=>$LJSFi_dbserv[$dest],"error"=>$dbconn ? $dbconn->connect_error : "init failed","request_id"=>atlas_request_id()]));
      echo ( "<P>Cannot connect to db server ".$LJSFi_dbserv[$dest]."</P>");
      exit();
    }
  }

  // Error function
  function db_err($err) {
    error_log("[ATLAS_APP] ".json_encode(["event"=>"db_error","error"=>(string)$err,"request_id"=>atlas_request_id(),"uri"=>(string)($_SERVER["REQUEST_URI"]??"cli")]));
    echo ("<P>ERROR: " . $err . "</P>");
    exit();
  }

  // Query handler
  function db_query($query,$dest="rw") {
    global $dbconn;
    if (!isset($dbconn)) db_conn($dest);
    $result = mysqli_query($dbconn,$query);
    if (!$result) {
      db_err(mysqli_error($dbconn));
    } else {
      return $result;
    }
  }
?>
