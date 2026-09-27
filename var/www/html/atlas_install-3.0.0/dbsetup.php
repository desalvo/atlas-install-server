<?php
  $dbconn       = NULL;

  // Connection function
  function db_conn($dest="rw") {
    require("config.php");
    global $dbconn;
    $dbconn = new mysqli($LJSFi_dbserv[$dest],$LJSFi_dbuser[$dest],$LJSFi_dbpass[$dest],$LJSFi_dbname);
    if (!$dbconn) {
      echo ( "<P>Cannot connect to db server ".$LJSFi_dbserv[$dest]."</P>");
      exit();
    }
  }

  // Error function
  function db_err($err) {
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
