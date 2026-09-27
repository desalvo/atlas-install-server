<?php

  require("db.php");

  $obsolete = array(0 => "production", 1 => "obsolete");
  $autoinstall = array(0 => "autoinstall_off", 1 => "autoinstall_on");
  # Query header
  # Exclude internal (typefk=1) releases
  $q_hdr  = "SELECT rd.name,rd.obsolete,rd.autoinstall,rd.tag";
  $q_body =  " FROM release_data rd"
           ." WHERE rd.typefk <> 1";

  # Fetch the records
  if (isset($_REQUEST['showdeps'])) $q_hdr .= (",rd.requires,(SELECT tag FROM release_data WHERE name=rd.requires) AS reqtag");
  if (isset($_REQUEST['showtarget'])) $q_hdr .= (",rd.sw_name,(SELECT CONCAT(description,'_',mode) FROM release_arch WHERE ref=rd.archfk) AS arch");
  if (isset($_REQUEST['rel'])) {
    $q_rel = "";
    foreach (split(',',$_REQUEST['rel']) as $relname) {
      if ($q_rel == "") {
        $q_rel .= (" AND (rd.name LIKE '" . $relname . "'");
      } else {
        $q_rel .= (" OR rd.name LIKE '" . $relname . "'");
      }
    }
    if ($q_rel != "") $q_body .= $q_rel.")";
  }
  if (isset($_REQUEST['tag'])) {
    $q_tag = "";
    foreach (split(',',$_REQUEST['tag']) as $tagname) {
      if ($q_tag == "") {
        $q_tag .= (" AND (rd.tag LIKE '" . $tagname . "'");
      } else {
        $q_tag .= (" OR rd.tag LIKE '" . $tagname . "'");
      }
    }
    if ($q_tag != "") $q_body .= $q_tag.")";
  }
  $query  = ($q_hdr . $q_body);
  if (isset($_REQUEST['autoinstall'])) {
    if ($_REQUEST['autoinstall'] == 0) {
      $query .= (" AND rd.autoinstall = 0");
    } else {
      $query .= (" AND rd.autoinstall >= 1");
    }
  }
  if (isset($_REQUEST['cvmfs_available'])) {
    if ($_REQUEST['cvmfs_available'] == 0) {
      $query .= (" AND rd.cvmfs_available = 0");
    } else {
      $query .= (" AND rd.cvmfs_available >= 1");
    }
  }
  # Select obsolete/production releases
  # Exclude nightly (typefk=3) releases
  if (isset($_REQUEST['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_REQUEST['obsolete'],0,1) . " AND rd.typefk <> 3";
  $query .= (" ORDER BY rd.tag ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_assoc($result) ) {
    if ($row['autoinstall'] == 0) {
      $ai = $autoinstall[0];
    } else {
      $ai = $autoinstall[1];
    }
    if (!isset($_REQUEST['panda'])) {
        printf ("%-35s : %s,%s,%s", $row['name'],$obsolete[$row['obsolete']],$ai,$row['tag']);
    } else {
        printf ("%s", $row['tag']);
    }
    if (isset($_REQUEST['showdeps'])) printf (",%s,%s", $row['requires'], $row['reqtag']);
    if (isset($_REQUEST['showtarget'])) {
      $relname = preg_split('/:/',$row['name']);
      if (isset($relname[1])) {
        $rn = $relname[1];
      } else {
        $rn = $row['name'];
      }
      $rel = preg_split('/[-_]/', $rn);
      $arch = preg_replace('/^_/', '', $row['arch']);
      $target = sprintf ("%s_%s_%s",$row['sw_name'],preg_replace('/\./','_',$rel[0]),$arch);
      if (isset($_REQUEST['panda'])) {
        if (substr($arch,0,6) == "noarch") {
            $arch = "noarch";
        } else {
          $cpuarch = preg_replace('/(i686|x86_64)_(.*)/', '${1}', $arch);
          $platf   = preg_replace('/(i686|x86_64)_(.*)/', '${2}', $arch);
          $platf   = preg_replace('/_/', '-', $platf);
          $arch    = $cpuarch . "-" . $platf;
        }
        if (substr($row['sw_name'],0,4) == "http") {
          $swname = $row['name'];
        } else {
          $swname = $row['sw_name'];
        }
        printf (",%s,%s,%s", $swname, $rel[0], $arch);
        if (isset($_REQUEST["showrel"])) printf (",%s", $row['name']);
      } else {
        printf (",%s,%s", $target, $arch);
      }
    }
    print ("\n");
  }
  // SQL query disclosure disabled
?>
