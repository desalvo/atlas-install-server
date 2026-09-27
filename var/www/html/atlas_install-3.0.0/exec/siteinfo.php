<?php

  require("db.php");
  include("JSON.php");

  function get_site_info($mode='') {
    $status_code = array();
    $status_code[0] = "white";
    for ($i=1; $i<=50; $i++) $status_code[$i] = "red";
    for ($i=51; $i<=99; $i++) $status_code[$i] = "yellow";
    $status_code[100] = "green";
    if ($mode == 'showfs') {
      $query_fields = "DISTINCT(name), fstype";
      $query_filters = array();
      if (isset($_REQUEST["showcs"])) $query_fields = "DISTINCT(CONCAT(name,'[',cs,']')), fstype";
      array_push($query_filters, "name <> ''");
      array_push($query_filters, "fstype IS NOT NULL");
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") array_push($query_filters, "name=".db_quote($_REQUEST["sitename"],'ro'));
      if (isset($_REQUEST["cs"])) array_push($query_filters, "cs=".db_quote($_REQUEST["cs"],'ro'));
      if (isset($_REQUEST["fstype"]) && $_REQUEST["fstype"] != "") array_push($query_filters, "fstype=".db_quote($_REQUEST["fstype"],'ro'));
      if (isset($_REQUEST["active"])) array_push($query_filters, "last_activity >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
      $query = "SELECT ".$query_fields." FROM site ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" AND ",$query_filters);
      if (isset($_REQUEST["orderby"]) && $_REQUEST["orderby"] != "") {
        $query .= " ORDER BY ".db_order_by($_REQUEST["orderby"], ['name','fstype','osname','cs','cename'], 'name');
      } else {
        $query .= " ORDER BY name, fstype";
      }
      $res   = db_query($query);
      while ($row = mysqli_fetch_array($res)) {
        if (isset($_REQUEST["quiet"])) {
          print $row[1]."\n";
        } else {
          print $row[0].": ".$row[1]."\n";
        }
      }
    } elseif ($mode == 'showos') {
      $query_fields = "DISTINCT(name), osname";
      $query_filters = array();
      if (isset($_REQUEST["showcs"])) $query_fields = "DISTINCT(CONCAT(name,'[',cs,']')), osname";
      array_push($query_filters, "name <> ''");
      array_push($query_filters, "osname IS NOT NULL");
      array_push($query_filters, "osname <> ''");
      array_push($query_filters, "osname <> '-'");
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") array_push($query_filters, "name=".db_quote($_REQUEST["sitename"],'ro'));
      if (isset($_REQUEST["fstype"]) && $_REQUEST["fstype"] != "") array_push($query_filters, "fstype=".db_quote($_REQUEST["fstype"],'ro'));
      if (isset($_REQUEST["active"])) array_push($query_filters, "last_activity >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
      $query = "SELECT ".$query_fields." FROM site ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" AND ",$query_filters);
      if (isset($_REQUEST["orderby"]) && $_REQUEST["orderby"] != "") {
        $query .= " ORDER BY ".db_order_by($_REQUEST["orderby"], ['name','fstype','osname','cs','cename'], 'name');
      } else {
        $query .= " ORDER BY name, osname";
      }
      $res   = db_query($query);
      while ($row = mysqli_fetch_array($res)) {
        print $row[0].": ".$row[1]."\n";
      }
    } elseif ($mode == 'critical') {
      $site_list_json = file_get_contents("http://adc-ssb.cern.ch/SITE_EXCLUSION/ATLAS_sites.json");
      $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
      $site_list = array_keys($json->decode($site_list_json));
      $num_critical = array();
      $res   = db_query("SELECT site.name, COUNT(DISTINCT release_data.ref) FROM site, release_data WHERE release_data.critical = 1 AND (release_data.critical_site_typefk in (SELECT DISTINCT site_type.ref FROM site_type, site_attr, site_type_attr WHERE site_type_attr.attr=site_attr.attr AND site_type.ref=site_type_attr.type AND site.attr&site_attr.attr=site_attr.attr) OR release_data.critical_site_typefk = 1) AND site.name <> '' GROUP BY site.name");
      while ($row = mysqli_fetch_array($res)) {
        $num_critical[$row[0]] = $row[1];
      }
      $query_fields = "site.name, COUNT(DISTINCT release_data.name)";
      $query_filters = array();
      if (isset($_REQUEST["showcs"])) $query_fields = "CONCAT(site.name,'[',site.cs,']'), COUNT(DISTINCT release_data.name)";
      array_push($query_filters, "site.name <> ''");
      array_push($query_filters, "release_data.name = release_stat.name");
      array_push($query_filters, "release_data.critical = 1");
      array_push($query_filters, "release_stat.sitefk = site.ref");
      array_push($query_filters, "release_stat.status = 'installed'");
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") array_push($query_filters, "site.name=".db_quote($_REQUEST["sitename"],'ro'));
      if (isset($_REQUEST["active"])) array_push($query_filters, "site.last_activity >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
      $query = "SELECT ".$query_fields." FROM site, release_data, release_stat ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" AND ",$query_filters);
      $query .= " GROUP BY site.name";
      if (isset($_REQUEST["orderby"]) && $_REQUEST["orderby"] != "") {
        $query .= " ORDER BY ".db_order_by($_REQUEST["orderby"], ['name','fstype','osname','cs','cename'], 'name');
      } else {
        $query .= " ORDER BY site.name";
      }
      $link = 'https://'.$_SERVER['SERVER_NAME'].dirname(dirname($_SERVER['SCRIPT_NAME']))."/list.php?critical=1&sitename=";
      $d = date('Y-m-d H:i:s');
      if (isset($_REQUEST['html'])) {
        $linkpre = "<A HREF='";
        $linkpost = "'>status</A><BR>";
      } else {
        $linkpre = "";
        $linkpost = "";
      }
      $res   = db_query($query);
      while ($row = mysqli_fetch_array($res)) {
        if (in_array($row[0],$site_list)) {
          $val = intval(100*$row[1]/$num_critical[$row[0]]);
          if ($val > 100) { $val = 100; }
          print $d." ".$row[0]." ".$row[1]."/".$num_critical[$row[0]]." ".$status_code[$val]." ".$linkpre.$link.$row[0].$linkpost."\n";
        }
      }
    } elseif ($mode == 'getattr') {
      $query_fields = "site_attr.attr, site_attr.name";
      $query_filters = array();
      if (isset($_REQUEST["getattr"]) && $_REQUEST["getattr"] != "") {
        $attrs = explode(",",$_REQUEST["getattr"]);
        foreach ($attrs as $attr) {
          array_push($query_filters, "site_attr.name=".db_quote($attr,'ro'));
        }
      }
      $query = "SELECT ".$query_fields." FROM site_attr ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" OR ",$query_filters);
      $res   = db_query($query);
      $val = 0;
      while ($row = mysqli_fetch_array($res)) {
        $val = $val | $row[0];
      }
      print $val."\n";
    } elseif ($mode == 'showattr') {
      $query_fields = "site.name,site.attr, GROUP_CONCAT(DISTINCT site_attr.name)";
      $query_filters = array();
      array_push($query_filters, "site.attr & site_attr.attr = site_attr.attr");
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") {
          array_push($query_filters, "site.name=".db_quote($_REQUEST["sitename"],'ro'));
      }
      $query = "SELECT ".$query_fields." FROM site, site_attr ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" AND ",$query_filters);
      $query .= " GROUP BY site.attr";
      $res   = db_query($query);
      while ($row = mysqli_fetch_array($res)) {
        print $row[0]." ".$row[1]." ".$row[2]."\n";
      }
    } elseif ($mode == 'setattr') {
      $query_filters = array();
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") {
          array_push($query_filters, "site.name=".db_quote($_REQUEST["sitename"],'ro'));
      }
      $query = "UPDATE site SET attr=" . db_int($_REQUEST["setattr"],0) . " ";
      if (count($query_filters) > 0) $query .= "WHERE ".join(" AND ",$query_filters);
      $res   = db_query($query);
      echo "ATTR_SET: ".$_REQUEST["setattr"]."\n";
    } else {
      $query = "SELECT cename, name, cs, swarea, fstype, mountpoint, capacity, available, quota FROM site ";
      $query_fields = array();
      array_push($query_fields, "cename=".db_quote($_REQUEST["cename"],'ro'));
      if (isset($_REQUEST["sitename"]) && $_REQUEST["sitename"] != "") array_push($query_fields, "name=".db_quote($_REQUEST["sitename"],'ro'));
      if (isset($_REQUEST["cs"]) && $_REQUEST["cs"] != "") array_push($query_fields, "cs=".db_quote($_REQUEST["cs"],'ro'));
      if (isset($_REQUEST["swarea"]) && $_REQUEST["swarea"] != "") array_push($query_fields, "swarea=".db_quote($_REQUEST["swarea"],'ro'));
      if (isset($_REQUEST["fstype"]) && $_REQUEST["fstype"] != "") array_push($query_fields, "fstype=".db_quote($_REQUEST["fstype"],'ro'));
      if (isset($_REQUEST["mountpoint"]) && $_REQUEST["mountpoint"] != "") array_push($query_fields, "mountpoint=".db_quote($_REQUEST["mountpoint"],'ro'));
      if (isset($_REQUEST["capacity"]) && $_REQUEST["capacity"] != "") array_push($query_fields, "capacity=".db_int($_REQUEST["capacity"],0));
      if (isset($_REQUEST["available"]) && $_REQUEST["available"] != "") array_push($query_fields, "available=".db_int($_REQUEST["available"],0));
      if (isset($_REQUEST["quota"]) && $_REQUEST["quota"] != "") array_push($query_fields, "quota=".db_int($_REQUEST["quota"],0));
      $query .= "WHERE ".join(" AND ",$query_fields)." LIMIT 1";
      $res   = db_query($query);
      if ($res) {
        $row = mysqli_fetch_row($res);
        print join(',', $row)."\n";
      } else {
        echo ("INSTALL SERVER> Cannot list site info for CE ".$_REQUEST['cename'].".\n");
      }
    }
    return 0;
  }

  if (isset($_REQUEST["cename"]) && $_REQUEST["cename"] != "") get_site_info();
  if (isset($_REQUEST["showfs"])) get_site_info('showfs');
  if (isset($_REQUEST["showos"])) get_site_info('showos');
  if (isset($_REQUEST["getattr"])) get_site_info('getattr');
  if (isset($_REQUEST["showattr"])) get_site_info('showattr');
  if (isset($_REQUEST["setattr"])) get_site_info('setattr');
  if (isset($_REQUEST["critical"])) get_site_info('critical');
?>
