<?php
  require('db.php');     // database connect script.
  require('config.php'); // Main configuration
  require("protected/site-info.php");
  $squery = "SELECT ref FROM request_status WHERE description='accepted'";
  $res1  = db_query($squery,"ro");
  while ( $row = mysqli_fetch_row($res1) ) { $accepted = $row[0]; }
  $aquery = "SELECT ref FROM request_status WHERE description='autorun'";
  $res2  = db_query($aquery,"ro");
  while ( $row = mysqli_fetch_row($res2) ) { $autorun = $row[0]; }
  $vquery = "SELECT ref FROM validation WHERE description='pending'";
  $res3  = db_query($vquery,"ro");
  while ( $row = mysqli_fetch_row($res3) ) { $validation = $row[0]; }
  $query = "SELECT r.id, COUNT(j.ref) AS pending FROM request r LEFT JOIN job j ON r.id = j.requestfk AND j.validationfk = $validation WHERE r.statusfk=$accepted GROUP BY r.id";
  $res4  = db_query($query,"ro");
  while ( $row = mysqli_fetch_row($res4) ) {
    $reqid = $row[0];
    $numpending = intval($row[1]);
    if ($numpending == 0) {
      $updquery = "UPDATE request SET statusfk = $autorun WHERE id = '$reqid'";
      db_query($updquery, "rw");
      echo "<A HREF='http://atlas-install.roma1.infn.it/atlas_install/protected/req.php?id=$reqid'>$reqid</A><BR>\n";
    }
  }
?>
