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
    if (isset($_REQUEST['quiet']))   { $quiet = True; } else { $quiet = False; }
    if (isset($_REQUEST['debug']))   { $debug = True; } else { $debug = False; }
    if (isset($_REQUEST['noquote'])) { $quote = False; } else { $quote = True; }

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "get commands:\n";
            echo "   dstat [name] [cache_timeout] [debug] [grid] [limit] [noquote] [olderthan] [quiet] [quote] [resource] [site] [status]\n";
            echo "   info [autoinstall] [autoinstall_target] [build] [cache_timeout] [cvmfs_available] [cvmfs_unavailable] [debug]\n";
            echo "        [genparser] [name] [noout] [noquote] [obsolete] [production] [quiet] [tag] [showtokens]\n";
            echo "   subscriptions <site> [cache_timeout] [debug] [noquote] [quiet]\n";
            echo "   tag  [cache_timeout] [debug] [name] [noquote] [quiet]\n";
            break;
          case 'info':
            $obsolete = NULL;
            $requires = NULL;
            $cvmfs    = NULL;
            if (isset($_REQUEST['cvmfs_available']))    { $cvmfs = 1; }
            if (isset($_REQUEST['obsolete']))           { $obsolete = 1; }
            if (isset($_REQUEST['production']))         { $obsolete = 0; }
            if (isset($_REQUEST['base']))               { $requires = 'NULL'; }
            if (isset($_REQUEST['patch']))              { $requires = 'NOT NULL'; }
            if (isset($_REQUEST['build']))              { $build = $_REQUEST['build']; }                           else { $build = NULL; }
            if (isset($_REQUEST['name']))               { $name = $_REQUEST['name']; }                             else { $name = NULL; }
            if (isset($_REQUEST['autoinstall']))        { $autoinstall = $_REQUEST['autoinstall']; }               else { $autoinstall = NULL; }
            if (isset($_REQUEST['autoinstall_target'])) { $autoinstall_target = $_REQUEST['autoinstall_target']; } else { $autoinstall_target = NULL; }
            if (isset($_REQUEST['tag']))                { $tag = $_REQUEST['tag']; }                               else { $tag = NULL; }
            if (isset($_REQUEST['showtokens']))         { $showtokens = True; }                                    else { $showtokens = False; }
            if (isset($_REQUEST['genparser']))          { $genparser = True; }                                     else { $genparser = False; }
            if (isset($_REQUEST['noout']))              { $noout = True; }                                         else { $noout = False; }
            if (isset($_REQUEST['cache_timeout']))      { $cache_timeout = $_REQUEST['cache_timeout']; }           else { $cache_timeout = 7200; }
            $rc = get_rel_info($name, $obsolete, $requires, $build, $autoinstall, $autoinstall_target, $cvmfs, $tag, $showtokens, $genparser, $noout, $cache_timeout, $quiet, $debug, $quote);
            break;
          case 'dstat':
            if (isset($_REQUEST['limit']))              { $limit = intval($_REQUEST['limit']); }                   else { $limit = 100; }
            if (isset($_REQUEST['name']))               { $name = $_REQUEST['name']; }                             else { $name = NULL; }
            if (isset($_REQUEST['resource']))           { $resource = $_REQUEST['resource']; }                     else { $resource = NULL; }
            if (isset($_REQUEST['site']))               { $site = $_REQUEST['site']; }                             else { $site = NULL; }
            if (isset($_REQUEST['status']))             { $status = $_REQUEST['status']; }                         else { $status = NULL; }
            if (isset($_REQUEST['olderthan']))          { $olderthan = $_REQUEST['olderthan']; }                   else { $olderthan = NULL; }
            if (isset($_REQUEST['grid']))               { $grid = $_REQUEST['grid']; }                             else { $grid = NULL; }
            if (isset($_REQUEST['quote']))              { $quote = True; }                                         else { $quote = False; }
            if (isset($_REQUEST['cache_timeout']))      { $cache_timeout = $_REQUEST['cache_timeout']; }           else { $cache_timeout = 7200; }
            $rc = get_rel_dstat($name, $resource, $site, $grid, $status, $olderthan, $limit, $cache_timeout, $quiet, $debug, $quote);
            break;
          case 'subscriptions':
            if (isset($_REQUEST['site']))               { $site = $_REQUEST['site']; }                             else { $site = NULL; }
            if (isset($_REQUEST['cache_timeout']))      { $cache_timeout = $_REQUEST['cache_timeout']; }           else { $cache_timeout = 7200; }
            $rc = get_rel_subscriptions($site, $cache_timeout, $quiet, $debug, $quote);
            break;
          case 'tag':
            if (isset($_REQUEST['name']))               { $name = $_REQUEST['name']; }                             else { $name = NULL; }
            if (isset($_REQUEST['cache_timeout']))      { $cache_timeout = $_REQUEST['cache_timeout']; }           else { $cache_timeout = 7200; }
            $rc = get_rel_tag($name, $cache_timeout, $quiet, $debug, $quote);
            break;
        }
        break;
      case 'PUT':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "set commands:\n";
            echo "   dstat <name> <resource> <status> [cache_timeout] [debug] [quiet]\n";
            break;
          case 'dstat':
            if (isset($_REQUEST['name']))               { $name = $_REQUEST['name']; }                             else { $name = NULL; }
            if (isset($_REQUEST['resource']))           { $resource = $_REQUEST['resource']; }                     else { $resource = NULL; }
            if (isset($_REQUEST['status']))             { $status = $_REQUEST['status']; }                         else { $status = NULL; }
            if (isset($_REQUEST['cache_timeout']))      { $cache_timeout = $_REQUEST['cache_timeout']; }           else { $cache_timeout = 7200; }
            $rc = set_rel_dstat($name, $resource, $status, $cache_timeout, $quiet, $debug);
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


  function get_rel_info($name=NULL, $obsolete=NULL, $requires=NULL, $build=NULL, $autoinstall=NULL
                       , $autoinstall_target=NULL, $cvmfs=NULL, $tag=NULL, $showtokens=False
                       , $genparser=False, $noout=False, $cache_timeout=7200
                       , $quiet=False, $debug=False, $quote=True) {
    # Selection fields
    $selectionFields = "release_data.*";

    # All tables
    $all_tables = array("release_data", "release_data_ext");

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
    if (strpos($name,'%') === False) {
      $q_filter = "WHERE release_data.name = ".db_quote($name,'ro');
    } else {
      $q_filter = "WHERE release_data.name LIKE ".db_quote($name,'ro');
    }
    if (!is_null($cvmfs))              $q_filter .= " AND release_data.cvmfs_available=".db_int($cvmfs,0,1);
    if (!is_null($obsolete))           $q_filter .= " AND release_data.obsolete=".db_int($obsolete,0,1)." AND release_data.typefk <> 1";
    if (!is_null($autoinstall))        $q_filter .= " AND release_data.autoinstall=".db_int($autoinstall,0);
    if (!is_null($autoinstall_target)) $q_filter .= " AND autoinstall_target.name=".db_quote($autoinstall_target,'ro');
    if (!is_null($requires)) {
      $requires_sql = strtoupper(trim((string)$requires));
      if (!in_array($requires_sql, ['NULL','NOT NULL'], true)) throw new InvalidArgumentException('Invalid requires filter');
      $q_filter .= " AND release_data.requires IS ".$requires_sql;
    }
    if (!is_null($build))              $q_filter .= " AND release_data.build=".db_quote($build,'ro');
    if (!is_null($tag))                $q_filter .= " AND release_data.tag = ".db_quote($tag,'ro');

    $q_body = "SELECT %s FROM release_data JOIN release_type ON release_data.typefk=release_type.ref JOIN autoinstall_target ON release_data.autoinstall=autoinstall_target.ref";
    $q_order   = "ORDER BY release_data.ref";
    $q_ext     = "SELECT release_data.name AS name, show_conf, field_name, token, release_data_ext.value AS value, field_nullfk, field_default, format, fregexp FROM release_data JOIN field_descriptions LEFT OUTER JOIN release_data_ext ON field_descriptions.ref=release_data_ext.fieldfk AND release_data.ref=release_data_ext.relfk WHERE table_name = 'release_data_ext' AND name='%s'";
    $q_ext_key = 'name';
    $rc = ljsfinfo_readDB($selectionFields, $q_body, $q_filter, '', $q_order, $all_tables, $q_ext, $q_ext_key, $cache_timeout, $showtokens, $genparser, $noout, $quiet, $debug, $quote);

    return $rc;
  }

  function get_rel_subscriptions($site=NULL, $cache_timeout=7200, $quiet=False, $debug=False, $quote=True) {
    if (!is_null($site)) {
      $q_body = "SELECT release_data.name FROM release_data, release_subscription";
      $q_filter = " WHERE release_data.name LIKE release_subscription.pattern AND release_subscription.sitename = ".db_quote($site,'ro');
      $query = $q_body . $q_filter;
      $result = db_query_rest($query);
      $indx = 0;
      while ( $row = mysqli_fetch_row($result) ) {
        echo $row[0]."\n";
        $indx = $indx + 1;
      }
      if ($indx == 0) echo "ALL\n";
      return array(200,"OK");
    } else {
      $rc = array(1,"");
    }
    return $rc;
  }

  function get_rel_tag($name=NULL, $cache_timeout=7200, $quiet=False, $debug=False, $quote=True) {
    # Selection fields
    $selectionFields = "release_data.tag";
    $q_body = "SELECT %s FROM release_data";
    $q_filter = "WHERE release_data.name=".db_quote($name,'ro');
    $rc = ljsfinfo_readDB($selectionFields, $q_body, $q_filter, '', '', NULL, NULL, NULL, $cache_timeout, False, False, False, $quiet, $debug, $quote);
    return $rc;
  }

  function get_rel_dstat($name=NULL, $resource=NULL, $site=NULL, $grid=NULL
                       , $status=NULL, $olderthan=NULL
                       , $limit=NULL, $cache_timeout=7200
                       , $quiet=False, $debug=False, $quote=False) {
    $query_body     = "SELECT rs.name, s.name, s.cs, rs.status";
    $query_tables   = " FROM release_stat rs, site s";
    $query_criteria = " WHERE rs.sitefk = s.ref";
    $query_orderby  = sprintf(" ORDER BY rs.name, s.name, s.cs, rs.status");
    if ($name) {
      if (strpos($name,'%') === False) {
        $query_criteria .= " AND rs.name = ".db_quote($name,'ro');
      } else {
        $query_criteria .= " AND rs.name LIKE ".db_quote($name,'ro');
      }
    }
    if ($resource)  $query_criteria .= " AND s.cs = ".db_quote($resource,'ro');
    if ($olderthan) $query_criteria .= " AND rs.date <= DATE_SUB(NOW(),INTERVAL ".db_int($olderthan,0)." SECOND)";
    if ($site)      $query_criteria .= " AND s.name = ".db_quote($site,'ro');
    if ($status) {
      if (strpos($status,'%') === False) {
        $query_criteria .= " AND rs.status = ".db_quote($status,'ro');
      } else {
        $query_criteria .= " AND rs.status LIKE ".db_quote($status,'ro');
      }
    }
    if ($grid) {
      $query_tables   .= ", grid g";
      $query_criteria .= " AND s.gridfk = g.ref AND g.name=".db_quote($grid,'ro');
    }
    $query = $query_body . $query_tables . $query_criteria . $query_orderby . $query_limit;
    if ($limit) $query .= " LIMIT ".db_int($limit,1,100000);
    if ($debug) echo $query."\n";
    $res = db_query_rest($query);
    while ($row = mysqli_fetch_row($res)) {
      if ($quote) {
        echo "\"".implode("\",\"",$row)."\"\n";
      } else {
        echo implode(",",$row)."\n";
      }
    }
    $rc = array(200,"OK");
    return $rc;
  }

  function set_rel_dstat($name, $resource, $status, $cache_timeout=7200, $quiet=False, $debug=False) {
    if ($name and $resource and $status) {
      $query = "UPDATE release_stat SET status=".db_quote($status,'rw')." WHERE name=".db_quote($name,'rw')." AND sitefk=(SELECT ref FROM site WHERE cs=".db_quote($resource,'rw').")";
      if ($debug) echo $query."\n";
      $rc = db_query_rest($query);
    } else {
      $rc = array(501,"No name, resource or status specified");
    }
    return $rc;
  }
?>
