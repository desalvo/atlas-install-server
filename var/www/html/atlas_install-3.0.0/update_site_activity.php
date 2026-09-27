<?php
  require('db.php');     // database connect script.
  require('config.php'); // Main configuration
  $query = "SELECT DISTINCT(sitefk),MAX(submission_time) FROM job WHERE submission_time >= DATE_SUB(NOW(),INTERVAL ".$LJSFi_ACTIVITY_PERIOD.") GROUP BY sitefk";
  $res   = db_query($query,"ro");
  while ( $row = mysqli_fetch_row($res) ) {
    $updquery = "UPDATE site SET last_activity='".$row[1]."' WHERE ref=".$row[0];
    db_query($updquery);
  }
?>
