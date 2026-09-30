<?php

  require("db.php");

  $identity = atlas_current_identity();
  $identityRef = (int)($identity['legacy_ref'] ?? 0);
  $role = '';
  if ($identityRef > 0) {
    $result = db_query("SELECT user.ref, role.description FROM user,role WHERE user.ref=" . db_int($identityRef,1) . " AND ABS(user.rolefk)=role.ref","ro");
    $row = mysqli_fetch_row($result);
    if ($row) { $adminfk=(int)$row[0]; $role=(string)$row[1]; }
  }

  # Query header
  $q_hdr  = "SELECT DISTINCT(rd.tag),rd.name,(SELECT site.cs FROM site WHERE site.cename=s.cename ORDER BY site.ref DESC LIMIT 1)";
  $q_body =  " FROM release_data rd,release_stat r,site s"
           ." WHERE rd.name=r.name AND r.sitefk=s.ref AND rd.tag <> '' AND r.status='installed'";

  # Fetch the records
  $query  = ($q_hdr . $q_body);
  if (isset($_REQUEST['rel'])) $query .= " AND rd.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  if (isset($_REQUEST['cename']))  $query .= " AND s.cename=" . db_quote($_REQUEST['cename'],'ro');
  # Select obsolete/production releases
  # excluding internal (typefk=1) and nightly (typefk=3) releases
  if (isset($_REQUEST['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_REQUEST['obsolete'],0,1) . " AND rd.typefk <> 1 AND rd.typefk <> 3";
  $query .= (" ORDER BY rd.tag ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_row($result) ) {
    if (isset($_REQUEST['showrel'])) echo $row[0].",";
    echo $row[0];
    if (isset($_REQUEST['showcs'])) echo ",".$row[2];
    print "\n";
  }
?>
