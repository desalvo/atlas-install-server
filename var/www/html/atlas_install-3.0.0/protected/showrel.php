<?php

  require("db.php");

  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $result = db_query("SELECT user.ref, role.description FROM user,role WHERE name=" . db_quote($sslusername,'ro') . " AND dn=" . db_quote($ssluserdetails,'ro') . " AND user.rolefk=role.ref");
  $row = mysqli_fetch_row($result);
  if (!$row) {
    $role="";
  } else {
    $adminfk=$row[0];
    $role=$row[1];
  }

  # Query header
  $q_hdr  = ("SELECT rd.ref
                    ,rd.name
                    ,(select ra.description from release_arch ra where ra.ref=rd.archfk) as arch
                    ,(select ra.description from release_arch ra where ra.ref=rd.sw_archfk) as sw_arch
                    ,(select t.name from task t where t.ref=rd.default_validate_taskfk) as validate_task
                    ,(select t.name from task t where t.ref=rd.default_install_taskfk) as install_task
                    ,(select t.name from task t where t.ref=rd.default_test_taskfk) as test_task
                    ,(select t.name from task t where t.ref=rd.default_uninstall_taskfk) as uninstall_task
                    ,(select t.name from task t where t.ref=rd.default_cleanup_taskfk) as cleanup_task
                    ,(select t.name from task t where t.ref=rd.default_publish_tag_taskfk) as publish_tag_task
                    ,(select t.name from task t where t.ref=rd.default_remove_tag_taskfk) as remove_tag_task
                    ,installer_version
                    ,install_tools_version
                    ,sw_name
                    ,sw_revision
                    ,pacman_version
                    ,pacman_platform
                    ,sw_versionarea
                    ,sw_atlas_fix_64
                    ,sw_atlas_compiler
                    ,kvpost
                    ,tag
                    ,rd.obsolete
                    ,rd.requires
                    ,rd.kit_cache
                    ,rd.package
                    ,(select reldata.sw_name from release_data reldata where reldata.name=rd.requires and rd.requires <> '') as required_swname
                    ,rd.sw_physicalpath
                    ,rd.sw_logicalpath
                    ,rd.sw_diskspace
                    ,rd.dbrelease
                    ,rd.autoinstall
                    ,rt.category");
  $q_body = (" FROM release_data rd,release_type rt
              WHERE     rd.typefk=rt.ref");

  # Fetch the records
  $query  = ($q_hdr . $q_body);
  if (isset($_GET['rel'])) $query .= " AND rd.name LIKE " . db_quote($_GET['rel'],'ro');
  # Select obsolete/production releases
  # excluding internal (typefk=1) and nightly (typefk=3) releases
  if (isset($_GET['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_GET['obsolete'],0,1) . " AND rd.typefk <> 1 AND rd.typefk <> 3";
  if (isset($_GET['autoinstall'])) $query .= " AND rd.autoinstall=" . db_int($_GET['autoinstall'],0,1);
  if (isset($_GET['base'])) $query .= " AND rd.requires IS NULL";
  if (isset($_GET['patch'])) $query .= " AND rd.requires IS NOT NULL";
  $query .= (" ORDER BY rd.name DESC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_array($result) ) {
    for ($i=0; $i<33; $i++) {
      echo ($row[$i]);
      if ($i<32) { echo(','); }
    }
    print "\n";
  }
?>
