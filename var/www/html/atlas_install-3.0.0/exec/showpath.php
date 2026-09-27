<?php

  require("db.php");

  # Query header
  $q_hdr  = "SELECT rd.name, CONCAT(rd.sw_physicalpath,'/',rd.sw_versionarea)";
  $q_body =  " FROM release_data rd"
           ." WHERE rd.sw_physicalpath <> ''";

  # Fetch the records
  $query  = ($q_hdr . $q_body);
  if (isset($_REQUEST['rel'])) $query .= " AND rd.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  $query .= (" ORDER BY rd.name ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_row($result) ) {
    if (isset($_REQUEST['showrel'])) echo $row[0].",";
    echo $row[1];
    print "\n";
  }
?>
