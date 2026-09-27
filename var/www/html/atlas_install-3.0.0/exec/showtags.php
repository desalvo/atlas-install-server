<?php

  require("db.php");

  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = preg_replace('/\/CN=[0-9]+/','',getenv("SSL_CLIENT_S_DN"));
  $sslvalid = getenv("SSL_CLIENT_V_REMAIN");
  if ($ssluserdetails == "" || $sslvalid == 0) {
    print "INSTALL SERVER> Bad credentials\n";
    exit;
  }
  $ssluserdetails = ereg_replace("/CN=proxy", "", $ssluserdetails);
  
  $result = db_query("SELECT user.ref, role.description FROM user,role WHERE name=" . db_quote($sslusername,'ro') . " AND dn=" . db_quote($ssluserdetails,'ro') . " AND user.rolefk=role.ref");

  $row = mysqli_fetch_row($result);
  if (!$row) {
    $role="";
  } else {
    $adminfk=$row[0];
    $role=$row[1];
  }
  if ($role == "") {
    print "INSTALL SERVER> DN: ".$ssluserdetails."\n";
    print "INSTALL SERVER> CN: ".$sslusername."\n";
    print "INSTALL SERVER> role: ".$role."\n";
    print "INSTALL SERVER> Unknown user. Please register to the installation system first.\n";
    exit;
  }
  if (isset($_REQUEST["verbose"])) {
    print "User identity: ".$ssluserdetails."\n";
    print "User role:     ".$role."\n";
  }

  # Query header
  $q_hdr  = "SELECT DISTINCT(rd.tag),rd.name";
  $q_ce   = "(SELECT site.cs FROM site WHERE site.cename=s.cename ORDER BY site.ref DESC LIMIT 1)";
  $q_site = "(SELECT site.name FROM site WHERE site.name=s.name ORDER BY site.ref DESC LIMIT 1)";
  $q_body =  " FROM release_data rd,release_stat r,site s,grid g"
           ." WHERE rd.name=r.name AND r.sitefk=s.ref AND s.gridfk=g.ref AND rd.tag <> ''";

  # Fetch the records
  if (isset($_REQUEST['rel'])) $q_body .= " AND rd.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  if (isset($_REQUEST['cename'])) {
    $_REQUEST["showcs"] = "y";
    $q_body .= " AND s.cename=" . db_quote($_REQUEST['cename'],'ro');
  }
  if (isset($_REQUEST['showcs'])) $q_hdr  .= "," . $q_ce;
  if (isset($_REQUEST['showsite'])) $q_hdr  .= "," . $q_site;
  if (isset($_REQUEST['sitename']))  $q_body .= " AND s.name=" . db_quote($_REQUEST['sitename'],'ro');
  if (isset($_REQUEST['grid']))  $q_body .= " AND g.name=" . db_quote($_REQUEST['grid'],'ro');
  if (isset($_REQUEST['status']))  {
    $q_body .= " AND s.status=" . db_quote($_REQUEST['status'],'ro');
  } else {
    $q_body .= " AND r.status='installed'";
  }
  $query  = $q_hdr . $q_body;
  // SQL query disclosure disabled
  # Select obsolete/production releases
  # excluding internal (typefk=1) and nightly (typefk=3) releases
  if (isset($_REQUEST['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_REQUEST['obsolete'],0,1) . " AND rd.typefk <> 1 AND rd.typefk <> 3";
  $query .= (" ORDER BY rd.tag ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_row($result) ) {
    if (isset($_REQUEST['showrel'])) echo $row[0].",";
    echo $row[0];
    if (isset($_REQUEST['showcs']) or isset($_REQUEST['showsite'])) echo ",".$row[2];
    print "\n";
  }
?>
