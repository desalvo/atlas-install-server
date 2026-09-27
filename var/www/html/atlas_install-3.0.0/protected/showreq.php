<?php

  require("db.php");

  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $result = db_query("SELECT user.ref, role.description FROM user,role WHERE name=" . db_quote($sslusername,'ro') . " AND dn=" . db_quote($ssluserdetails,'ro') . " AND user.rolefk=role.ref","ro");
  $row = mysqli_fetch_row($result);
  if (!$row) {
    $role="";
  } else {
    $adminfk=$row[0];
    $role=$row[1];
  }

  # Query body
  $q_body  = ("SELECT request.id
                     ,request.typefk
                     ,request_type.description
                     ,release_stat.name
                     ,site.cename
                     ,site.cs
                     ,request.statusfk
                     ,request_status.description
                     ,request.request_date
                     ,request.update_date
                     ,user.name
                     ,bdii.ns
                     ,bdii.lb
                     ,bdii.ld
                     ,bdii.wmproxy
                     ,bdii.myproxy
                     ,request.userfk
                     ,IF(request.adminfk IS NOT NULL,request.adminfk,request.userfk)");
  $q_from =   (" FROM request,release_stat,site,user,request_type,request_status,bdii");
  $q_where = (" WHERE request.sitefk=site.ref
                  AND request.userfk=user.ref
                  AND request.relfk=release_stat.ref
                  AND request.typefk=request_type.ref
                  AND request.statusfk=request_status.ref
                  AND request.bdiifk=bdii.ref");

  # Fetch the records
  if (isset($_REQUEST['id']))       $q_where .= " AND request.id=" . db_quote($_REQUEST['id'],'ro');
  if (isset($_REQUEST['statusid'])) $q_where .= " AND request.statusfk=" . db_int($_REQUEST['statusid']);
  if (isset($_REQUEST['status']))   $q_where .= " AND request_status.description=" . db_quote($_REQUEST['status'],'ro');
  if (isset($_REQUEST['cename']))   $q_where .= " AND site.cename=" . db_quote($_REQUEST['cename'],'ro');
  if (isset($_REQUEST['cs']))       $q_where .= " AND site.cs=" . db_quote($_REQUEST['cs'],'ro');
  if (isset($_REQUEST['rel']))      $q_where .= " AND release_stat.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  if (isset($_REQUEST['reqtype']))  $q_where .= " AND request_type.description LIKE " . db_quote($_REQUEST['reqtype'],'ro');
  if (isset($_REQUEST['since']))    $q_where .= " AND UNIX_TIMESTAMP(request.request_date) > " . db_int($_REQUEST['since']);
  if (isset($_REQUEST['age']))      $q_where .= " AND UNIX_TIMESTAMP(request.update_date) <= " . db_int($_REQUEST['age']);
  if (isset($_REQUEST['gridname'])) {
    $q_from .= ",grid";
    $q_where .= " AND site.gridfk=grid.ref AND grid.name=" . db_quote($_REQUEST['gridname'],'ro');
  }
  if (isset($_REQUEST['facility'])) {
    $q_from .= ",facility";
    $q_where .= " AND site.facilityfk=facility.ref AND facility.name=" . db_quote($_REQUEST['facility'],'ro');
  }
  if (isset($_REQUEST['sitetype'])) {
    $q_from .= ",site_activity_type";
    $q_where .= " AND site.activity_typefk=site_activity_type.ref AND site_activity_type.name=" . db_quote($_REQUEST['sitetype'],'ro');
  }
  $query  = ($q_body . $q_from . $q_where . " ORDER BY request.request_date DESC, site.cename");
  if (isset($_REQUEST['offset'])) { $offset = db_int($_REQUEST['offset'],0); } else { $offset = 0; }
  if (isset($_REQUEST['maxrecords'])) $query .= " LIMIT " . db_int($_REQUEST['maxrecords'],1,100000) . " OFFSET " . $offset;
  $result = db_query($query,"ro");
  while ( $row = mysqli_fetch_array($result) ) {
    for ($i=0; $i<18; $i++) {
      echo ($row[$i]);
      if ($i<17) { echo(','); }
    }
    print "\n";
  }
?>
