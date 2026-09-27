<?php
  require('db.php');     // database connect script.
  require('config.php'); // Main configuration
  require("protected/site-info.php");
  echo "Using infosys $LJSFi_default_infosys\n";
  $query = "SELECT DISTINCT(cs) FROM site WHERE name IS NULL OR name = '' AND (cs LIKE '%:8443/%' OR cs LIKE '%:2119/%')";
  $res   = db_query($query,"ro");
  while ( $row = mysqli_fetch_row($res) ) {
    $cs = $row[0];
    echo "Checking site info of $cs\n";
    $siteinfo = get_cluster_info($cs,$LJSFi_default_infosys);
    if ($siteinfo) {
      echo "Restoring site name ".$siteinfo[0]." to resource ".$cs." (".$siteinfo[4].")\n";
      $updquery = "UPDATE site SET name='".$siteinfo[0]."', gridfk=(SELECT ref FROM grid WHERE name='".$siteinfo[4]."') WHERE cs='".$cs."'";
      db_query($updquery);
    } else {
      print "No site info for $cs\n";
    }
  }
?>
