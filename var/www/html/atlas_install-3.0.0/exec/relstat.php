<?php

  require("db.php");

  # Query header
  # Exclude internal (typefk=1) releases
  $q_hdr  = "SELECT DISTINCT(rd.name),r.status";
  $q_body =  " FROM release_data rd,release_stat r,site s";
  if (isset($_REQUEST['relarch'])) $q_body .= ",release_arch ra";
  $q_body .= " WHERE rd.name=r.name AND r.sitefk=s.ref AND rd.typefk <> 1";
  if (isset($_REQUEST['relarch'])) $q_body .= " AND rd.archfk=ra.ref";

  # Fetch the records
  $query  = ($q_hdr . $q_body);
  if (isset($_REQUEST['rel']))      $query .= " AND rd.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  if (isset($_REQUEST['cename']))   $query .= " AND s.cename=" . db_quote($_REQUEST['cename'],'ro');
  if (isset($_REQUEST['sitename'])) $query .= " AND s.name=" . db_quote($_REQUEST['sitename'],'ro');
  if (isset($_REQUEST['status']))   $query .= " AND r.status=" . db_quote($_REQUEST['status'],'ro');
  if (isset($_REQUEST['swarea']))   $query .= " AND s.swarea=" . db_quote($_REQUEST['swarea'],'ro');
  if (isset($_REQUEST['relarch']))  $query .= " AND ra.description LIKE " . db_like_contains($_REQUEST['relarch'],'ro');
  # Select obsolete/production releases
  # Exclude nightly (typefk=3) releases
  if (isset($_REQUEST['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_REQUEST['obsolete'],0,1) . " AND rd.typefk <> 3";
  $query .= (" ORDER BY rd.tag ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_row($result) ) {
    printf ("%-20s : [%s]\n", $row[0],$row[1]);
  }
  // SQL query disclosure disabled
?>
