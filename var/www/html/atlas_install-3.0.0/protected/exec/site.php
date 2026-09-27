<?php

  require("../db.php");
  require('../user_info.php'); // User informations
  require('ljsfinfo.php');     // Info tools

  date_default_timezone_set('UTC');

  // Ident
  if (isset($_REQUEST["ident"])) { $ident = $_REQUEST["ident"]; } else { $ident = NULL; }

  // Check the user's credentials
  $user_info = get_user_info('select',NULL,NULL,True,NULL,15,0,$ident);
  if (count($user_info) == 0) {
    $role="";
  } else {
    $userref = $user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
  }

  $rc = array(200,"OK");

  if ($role == "master") {
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($_REQUEST['debug']))         { $debug = True; }                               else { $debug = False; }
    if (isset($_REQUEST['quiet']))         { $quiet = True; }                               else { $quiet = False; }
    if (isset($_REQUEST['cache_timeout'])) { $cache_timeout = $_REQUEST['cache_timeout']; } else { $cache_timeout = 7200; }

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "get commands:\n";
            echo "   info [cache-timeout] [debug] [genparser] [name]\n";
            echo "   list [cache-timeout] [debug] [disabled] [grid] [name]\n";
            break;
          case 'info':
            if (isset($_REQUEST['genparser']))     { $genparser = True; }                           else { $genparser = False; }
            if (isset($_REQUEST['name']))          { $name = $_REQUEST['name']; }                   else { $name = NULL; }
            $rc = get_site_info($name, $cache_timeout, $genparser, $quiet, $debug);
            break;
          case 'list':
            if (isset($_REQUEST['name']))          { $name = $_REQUEST['name']; }                   else { $name = NULL; }
            if (isset($_REQUEST['disabled']))      { $disabled = True; }                            else { $disabled = NULL; }
            if (isset($_REQUEST['grid']))          { $grid = $_REQUEST['grid']; }                   else { $grid = NULL; }
            $rc = get_site_list($name, $cache_timeout, $disabled, $grid, $quiet, $debug);
            break;
        }
        break;
    }
  } else {
    $rc = array(500,"Insufficient privileges");
  }
  header('X-PHP-Response-Code: '.$rc[0], true, $rc[0]);
  if ($rc[0] != 200 && $rc[1] != NULL) {
    echo $rc[1]."\n";
  }

  function get_site_info($name=NULL, $cache_timeout=7200, $genparser=False, $quiet=False, $debug=False) {
    # All tables
    $all_tables = array("site", "site_ext");

    # Aggregated tables
    $tables = $all_tables;
    foreach ($tables as $table) {
      $query = "SELECT name,field1,table2,field2 FROM field_relations WHERE table1=".db_quote($table,'ro');
      $result = db_query_rest($query);
      if ($result) {
        while ( $row = mysqli_fetch_row($result) ) {
          $foreignConstraint = sprintf("%s.%s=%s.ref", $table, $row[1], $row[2]);
          $fieldName = sprintf("%s.%s", $row[2], $row[3]);
          $aggregatedTable = sprintf("%s.%s", $table, $row[2]);
          $selectionFields .= sprintf(",(SELECT %s FROM %s WHERE %s) AS '%s.%s'", $fieldName, $row[2], $foreignConstraint, $aggregatedTable, $row[0]);
          array_push($all_tables, $aggregatedTable);
        }
      }
    }

    # Complete the query
    if ($name) {
      if (strpos($name,'%') === False) {
        $q_filter = "WHERE s.cs = ".db_quote($name,'ro');
      } else {
        $q_filter = "WHERE s.cs LIKE ".db_quote($name,'ro');
      }
    } else {
      $q_filter = '';
    }

    $selectionFields = "s.*,GROUP_CONCAT((SELECT field_name FROM field_descriptions WHERE ref=se.fieldfk),',',se.value) AS site_ext";
    $q_body = "SELECT %s FROM site s LEFT OUTER JOIN site_ext se ON s.ref=se.sitefk";
    $q_order   = "ORDER BY s.cename DESC";
    $q_group   = "GROUP BY se.sitefk";
    $q_ext     = "SELECT site.cs AS cs, show_conf, field_name, token, site_ext.value AS value, field_nullfk, field_default, format, fregexp FROM site JOIN field_descriptions LEFT OUTER JOIN site_ext ON field_descriptions.ref=site_ext.fieldfk AND site_ext.sitefk = site.ref WHERE table_name = 'site_ext' AND cs='%s'";
    $q_ext_key = 'cs';
    $rc = ljsfinfo_readDB($selectionFields, $q_body, $q_filter, $q_group, $q_order, $all_tables, $q_ext, $q_ext_key, $cache_timeout, True, $genparser, False, $quiet, $debug);
    return $rc;
  }

  function get_site_list($name=NULL, $cache_timeout=7200, $disabled=NULL, $grid=NULL, $quiet=False, $debug=False) {
    global $cache;
    $query = sprintf("SELECT s.cs FROM site s");
    $query_criteria = array();
    if ($disabled) array_push($query_criteria,"s.status=0");
    if ($name)     array_push($query_criteria,"s.name LIKE ".db_quote($name,'ro'));
    if ($grid) {
      $query .= ", grid g";
      array_push($query_criteria,"s.gridfk=g.ref AND g.name=".db_quote($grid,'ro'));
    }
    if ($query_criteria) $query .= " WHERE " . implode(" AND ",$query_criteria);
    $query .= " ORDER BY cs";
    if ($debug) echo $query."\n";

    $site_list = NULL;
    if ($cache && $cache_timeout > 0) {
      $site_list = $cache->get(md5("get_site_list".$query));
    }

    if (!$site_list) {
      $site_list = array();
      $result = db_query_rest($query);
      while ($row = mysqli_fetch_row($result)) {
        array_push($site_list, $row[0]);
      }
      if ($cache && $cache_timeout > 0) {
        $cache->set(md5("get_site_list".$query),$site_list,MEMCACHE_COMPRESSED,$cache_timeout);
      }
    } else {
      if ($debug) echo "Using cached data\n";
    }

    foreach ($site_list as $site) {
      printf ("\"%s\"\n", $site);
    }
    return array(200,"OK");
  }
?>
