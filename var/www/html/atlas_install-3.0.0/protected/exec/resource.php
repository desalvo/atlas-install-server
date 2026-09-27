<?php

  require_once("../config.php");
  require("../db.php");
  require('../user_info.php'); // User informations

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

  $dbdest = sprintf("%s:%s@%s/%s", $LJSFi_dbuser["rw"], $LJSFi_dbpass["rw"], $LJSFi_dbserv["rw"], 'ljsf_infosys');

  if ($role == "master") {
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($_REQUEST['debug']))         { $debug = True; } else { $debug = False; }
    if (isset($_REQUEST['quiet']))         { $quiet = True; } else { $quiet = False; }
    if (isset($_REQUEST['name']))          { $name = $_REQUEST['name']; }                   else { $name = NULL; }
    if (isset($_REQUEST['cache_timeout'])) { $cache_timeout = $_REQUEST['cache_timeout']; } else { $cache_timeout = 7200; }

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "get commands:\n";
            echo "   info <name> [cache-timeout] [debug]\n";
            echo "   list [cache-timeout] [debug] [master]\n";
            echo "   master <name> [cache-timeout] [debug]\n";
            echo "   parser <name> [cache-timeout] [debug]\n";
            echo "   slaves <name> [cache-timeout] [debug]\n";
            break;
          case 'info':
            $rc = get_resource_info($dbdest, $name, $cache_timeout, $quiet, $debug);
            break;
          case 'list':
            if (isset($_REQUEST['master'])) { $master = True; } else { $master = NULL; }
            $rc = get_resource_list($dbdest, $master, $cache_timeout, $quiet, $debug);
            break;
          case 'master':
            $rc = get_resource_master($dbdest, $name, $cache_timeout, $quiet, $debug);
            break;
          case 'parser':
            $rc = get_resource_parser($dbdest, $name, $cache_timeout, $quiet, $debug);
            break;
          case 'slaves':
            $rc = get_resource_slaves($dbdest, $name, $cache_timeout, $quiet, $debug);
            break;
        }
        break;
    }
  } else {
    $rc = array(500,"Insufficient privileges");
  }
  header('X-PHP-Response-Code: '.$rc[0], true, $rc[0]);
  if ($rc[0] != 200 && $rc[1] != NULL && $rc[1] != '-') {
    echo $rc[1]."\n";
  }

  function get_resource_info($dbdest, $name=NULL, $cache_timeout=7200, $quiet=False, $debug=False) {
    $report = array("ATLAS_SITENAME=%s","SITENAME=%s","RESOURCE=%s","TIER_LEVEL=%s","OSNAME=%s","OSVERSION=%s","OSRELEASE=%s","GRID=%s","SITETYPE=%s","IS_CVMFS=%s","IS_DEFAULT=%s","CORECOUNT=%s","LFCPATH=%s","LFCPRODPATH=%s","SEPATH=%s","SEPRODPATH=%s");
    $query = "SELECT DISTINCT(p.atlas_site), p.site, p.name, p.tier_level, ros.osname, ros.osversion, ros.osrelease, f.name AS flavor, rt.name AS rtype, p.is_cvmfs, p.is_default, p.corecount, p.lfcpath, p.lfcprodpath, p.sepath, p.seprodpath FROM panda_resource p JOIN resource_os ros ON p.osfk = ros.ref JOIN resource_flavor f ON p.flavorfk = f.ref JOIN resource_type rt ON p.typefk = rt.ref WHERE p.name = " . db_quote($name,$dbdest) . " GROUP BY site, name, flavor";
    $result = db_query_rest($query,$dbdest);
    if ($result) {
      $out = '';
      while ($row = mysqli_fetch_row($result)) {
        $indx = 0;
        foreach ($report as $field) {
          if ($indx > 0) $out .= "\n";
          $out .= sprintf($field,$row[$indx]);
          $indx = $indx +1;
        }
      }
      $rc=array(0,$out);
    } else {
      $rc=array(1,'');
    }

    return $rc;
  }

  function get_resource_list($dbdest, $master=NULL, $cache_timeout=7200, $quiet=False, $debug=False) {
    $query = "SELECT name FROM panda_resource";
    if ($master === True) $query .= " WHERE is_default=1";
    $result = db_query_rest($query,$dbdest);
    while ($row = mysqli_fetch_row($result)) {
      echo $row[0]."\n";
    }
    return array(200,"OK");
  }

  function get_resource_master($dbdest, $name=NULL, $cache_timeout=7200, $quiet=False, $debug=False) {
    global $cache;
    $master = NULL;
    if ($cache_timeout > 0) { $master = $cache->get("isgetresourcemaster-".$name); }

    if (!$master) {
      $site_query = "SELECT DISTINCT(site), typefk FROM panda_resource WHERE is_default=0 AND name=" . db_quote($name,$dbdest);
      $site_result = db_query_rest($site_query,$dbdest);
      if ($site_result) {
        $site_row = mysqli_fetch_row($site_result);
        $site = $site_row[0];
        $typefk = $site_row[1];
        $master_query = "SELECT DISTINCT(name) FROM panda_resource WHERE site=".db_quote($site,$dbdest)." AND typefk=".db_int($typefk,1)." AND is_default=1 ORDER BY name";
        $master_result = db_query_rest($master_query,$dbdest);
        while ($master_row = mysqli_fetch_row($master_result)) {
          if ($master) { $master .= "\n"; }
          $master .= $master_row[0];
        }
      }
      if (!$master) $master = "-"; 
      if ($cache_timeout > 0) {
        if ($debug) echo "Filling cache.\n";
        $cache->set("isgetresourcemaster-".$name, $master, MEMCACHE_COMPRESSED, $cache_timeout);
      }
    } else {
      if ($debug) echo "Using cached data\n";
    }
    return array(0,$master);
  }

  function get_resource_parser($dbdest, $name=NULL, $cache_timeout=7200, $quiet=False, $debug=False) {
    global $cache;
    $parser = NULL;
    if ($cache_timeout > 0) { $parser = $cache->get("isgetresourceparser-".$name); }

    if (!$parser) {
      $report = array("s#@INFOSYS_SITETYPE@#%s#g","s#@INFOSYS_LFCPATH@#%s#g","s#@INFOSYS_LFCPRODPATH@#%s#g","s#@INFOSYS_SEPATH@#%s#g","s#@INFOSYS_SEPRODPATH@#%s#g");
      $query = "SELECT rt.name AS rtype, p.lfcpath, p.lfcprodpath, p.sepath, p.seprodpath FROM panda_resource p JOIN resource_type rt ON p.typefk = rt.ref WHERE p.name = " . db_quote($name,$dbdest) . " GROUP BY rtype, lfcpath, lfcprodpath, sepath, seprodpath";
      $result = db_query_rest($query,$dbdest);
      if ($result) {
        $parser = '';
        while ($row = mysqli_fetch_row($result)) {
          $indx = 0;
          foreach ($report as $field) {
            if ($indx > 0) $parser .= "\n";
            $parser .= sprintf($field,$row[$indx]);
            $indx = $indx +1;
          }
        }
      }
      if ($cache_timeout > 0) {
        if ($debug) echo "Filling cache.\n";
        $cache->set("isgetresourceparser-".$name, $parser, MEMCACHE_COMPRESSED, $cache_timeout);
      }
    } else {
      if ($debug) echo "Using cached data.\n";
    }

    if ($parser) {
      $rc=array(0,$parser);
    } else {
      $rc=array(1,'');
    }

    return $rc;
  }

  function get_resource_slaves($dbdest, $name=NULL, $cache_timeout=7200, $quiet=False, $debug=False) {
    global $cache;
    $slaves = NULL;
    if ($cache_timeout > 0) { $slaves = $cache->get("isgetresourceslaves-".$name); }

    if (!$slaves) {
      $site_query = "SELECT DISTINCT(site), typefk FROM panda_resource WHERE is_default=1 AND name=" . db_quote($name,$dbdest);
      $site_result = db_query_rest($site_query,$dbdest);
      if ($site_result) {
        $site_row = mysqli_fetch_row($site_result);
        $site = $site_row[0];
        $typefk = $site_row[1];
        $slaves_query = "SELECT DISTINCT(name) FROM panda_resource WHERE site=".db_quote($site,$dbdest)." AND typefk=".db_int($typefk,1)." AND is_default=0 ORDER BY name";
        $slaves_result = db_query_rest($slaves_query,$dbdest);
        while ($slaves_row = mysqli_fetch_row($slaves_result)) {
          if ($slaves) { $slaves .= "\n"; }
          $slaves .= $slaves_row[0];
        }
      }
      if (!$slaves) $slaves = "-"; 
      if ($cache_timeout > 0) {
        if ($debug) echo "Filling cache\n";
        $cache->set("isgetresourceslaves-".$name, $slaves, MEMCACHE_COMPRESSED, $cache_timeout);
      }
    } else {
      if ($debug) echo "Using cached data\n";
    }
    return array(0,$slaves);
  }
?>
