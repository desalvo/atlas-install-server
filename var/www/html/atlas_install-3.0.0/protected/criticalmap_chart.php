<?php

  function criticalmap_plot($tier_level=NULL, $site_type=NULL, $sites=NULL, $width=550, $height=400) {
    $query = "SELECT COUNT(DISTINCT release_data.ref)  FROM release_data WHERE release_data.critical = 1";
    $qry_res = db_query($query,"ro");
    if ($qry_res) {
      $row = mysqli_fetch_row($qry_res);
      $numrels = $row[0];
      $avg = intval($numrels/2);
    } else {
      return;
    }
    $query = "SET @@group_concat_max_len = 30000";
    db_query($query,"ro");
    $query = "SELECT site.name, site.tier_level, site.cs"
                . ", COUNT(DISTINCT release_data.ref)"
                . ", GROUP_CONCAT(DISTINCT release_data.ref ORDER BY release_data.ref)"
            . " FROM site, release_data"
           . " WHERE release_data.critical = 1"
             . " AND facilityfk=(SELECT ref FROM facility WHERE name='Panda')";
    if (!is_null($sites)) $query .= " AND site.ref IN (".db_int_list($sites,0).")";
    if (!is_null($tier_level)) {
      $query .= " AND site.tier_level = ".db_int($tier_level,0,99);
    } else {
      $query .= " AND site.tier_level > 0";
    }
    if (!is_null($site_type)) {
      $query .= " AND site.activity_typefk = (SELECT ref FROM site_activity_type WHERE name=".db_quote($site_type,'ro').")";
    } else {
      $query .= " AND site.tier_level > 0";
    }
    $query .= " GROUP BY site.name, site.cs";
    $query .= " ORDER BY site.name, site.cs";
    $qry_res = db_query($query,"ro");
    $num_releases = array();
    $max_rels = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      $num_releases[$row[2]] = array($row[0],$row[1],$row[3],$row[4]);
      if ($row[3] > $max_rels) $max_rels = $row[3];
    }
    $query = "SELECT site.name, site.tier_level, site.cs"
                . ", COUNT(DISTINCT release_stat.ref)"
                . ", GROUP_CONCAT(DISTINCT release_data.ref ORDER BY release_data.ref)"
            . " FROM site, release_stat, release_data"
           . " WHERE release_stat.name = release_data.name"
             . " AND release_stat.sitefk = site.ref"
             . " AND release_data.critical = 1"
             . " AND site.facilityfk=(SELECT ref FROM facility WHERE name='Panda')"
             . " AND release_stat.status = 'installed'";
    if (!is_null($sites)) $query .= " AND site.ref IN (".db_int_list($sites,0).")";
    if (!is_null($tier_level)) {
      $query .= " AND site.tier_level = ".db_int($tier_level,0,99);
    } else {
      $query .= " AND site.tier_level > 0";
    }
    if (!is_null($site_type)) {
      $query .= " AND site.activity_typefk = (SELECT ref FROM site_activity_type WHERE name=".db_quote($site_type,'ro').")";
    } else {
      $query .= " AND site.tier_level > 0";
    }
    $query .= " GROUP BY site.name, site.cs";
    $query .= " ORDER BY site.name, site.cs";
    $qry_res = db_query($query,"ro");
    $installed_releases = array();
    while ($row = mysqli_fetch_row($qry_res)) {
      $installed_releases[$row[2]] = array($row[0],$row[1],$row[3],$row[4]);
    }
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"treemap\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = google.visualization.arrayToDataTable([\n";
    echo "      ['Resource', 'Parent', 'Releases', 'Percentage'],\n";
    echo "      ['ATLAS Tiers', null, 0, 0],\n";
    echo "      ['Min value', 'ATLAS Tiers', 0, 0],\n";
    echo "      ['Max value', 'ATLAS Tiers', 0, $max_rels],\n";
    $indx = 0;
    $sites = array();
    foreach ($installed_releases as $res => $resdata) {
      if (!array_key_exists($resdata[0],$sites)) {
        if ($indx > 0) echo ",\n";
        printf ("      ['%s T%d', 'ATLAS Tiers', %d, %d]", $resdata[0], $resdata[1], 0, 0);
        $sites[$resdata[0]]=1;
        $indx++;
      }
      if ($indx > 0) echo ",\n";
      printf ("      ['%s', '%s T%d', %d, %d]", $res, $resdata[0], $resdata[1], $resdata[2], $num_releases[$res][2]-$resdata[2]);
      $indx++;
    }
    echo "\n    ]);\n";
    echo "    tree = new google.visualization.TreeMap(document.getElementById('criticalchart_div'));\n";
    echo "    var options = {\n";
    echo "       minColor: '#0f0',\n";
    echo "       midColor: '#ddd',\n";
    echo "       maxColor: '#f00',\n";
    echo "       showScale: true,\n";
    echo "       generateTooltip: showFullTooltip\n";
    echo "    }\n";
    echo "    tree.draw(data, options);\n";
    $server_url = $_SERVER['FULL_URL'] = 'http';
    if ($_SERVER['HTTPS']=='on') $_SERVER['FULL_URL'] .=  's';
    $_SERVER['FULL_URL'] .=  '://';
    $hn = $_SERVER['SERVER_NAME'];
    if($_SERVER['SERVER_PORT']!='80' && $_SERVER['SERVER_PORT']!='443') {
      $_SERVER['FULL_URL'] .=  $hn.':'.$_SERVER['SERVER_PORT'].$_SERVER['DOCUMENT_ROOT'];
    } else {
      $_SERVER['FULL_URL'] .=  $hn.$_SERVER['SCRIPT_NAME'];
    }
    $reslink=dirname($_SERVER['FULL_URL']) . "/../list.php?critical=1&resource=";
    $sitelink=dirname($_SERVER['FULL_URL']) . "/../list.php?critical=1&sitename=";
    $rellink=dirname($_SERVER['FULL_URL']) . "/../list.php?resource=%s&rel=%s";
    $query = "SELECT ref, name FROM release_data WHERE critical=1";
    $qry_res = db_query($query,"ro");
    $release_names = array();
    while ($row = mysqli_fetch_row($qry_res)) $release_names[$row[0]] = $row[1];
    $missing_releases = array();
    foreach ($num_releases as $resource_name => $resource_data) {
      $allrels = split(",",$resource_data[3]);
      if (array_key_exists($resource_name,$installed_releases)) {
        $instrels = split(",",$installed_releases[$resource_name][3]);
      } else {
        $instrels = array();
      }
      foreach ($allrels as $rel) {
        if (!in_array($rel, $instrels)) {
          if (!array_key_exists($resource_name,$missing_releases)) $missing_releases[$resource_name] = array();
          $relurl = sprintf($rellink,$resource_name,$release_names[$rel]);
          array_push($missing_releases[$resource_name],"<li><a href=\"".$relurl."\">".$release_names[$rel]."</a></li>");
        }
      }
    }
    echo "    function showFullTooltip(row, size, value) {\n";
    echo "      var missing_rels = [];\n";
    foreach ($missing_releases as $res => $rels) {
      $mrels = implode($rels,"");
      echo "      missing_rels['".$res."'] = '".$mrels."';\n";
    }
    echo "      reslabel = data.getValue(row,0);\n";
    echo "      if (data.getValue(row,1) != 'ATLAS Tiers') {\n";
    echo "          resname = reslabel;\n";
    echo "          var link = '<a href=\"".$reslink."' + data.getValue(row,0) + '\">Status of the releases in the resource</a>';\n";
    echo "      } else {\n";
    echo "          var strparts = reslabel.split(' ');\n";
    echo "          var resname = strparts[0];\n";
    echo "          var link = '<a href=\"".$sitelink."' + resname + '\">Status of the releases in the site</a>';\n";
    echo "      }\n";
    echo "      var ttip = '<div style=\"background:#fd9; padding:10px; border-style: none\">' +\n";
    echo "                 '<span><b>' + data.getValue(row,0) + '<br>' +\n";
    echo "                 '<span><b>Installed releases: ' +\n";
    echo "                 size + '</b><br>';\n";
    echo "      if (reslabel in missing_rels) {\n";
    echo "        ttip = ttip + 'Missing releases:<br>' + missing_rels[resname] + '<br>';\n";
    echo "      } else {\n";
    echo "        if (data.getValue(row,1) != 'ATLAS Tiers') ttip = ttip + 'No missing releases<br>';\n";
    echo "      }\n";
    echo "      ttip = ttip + link + '</span><br></div>';\n";
    echo "      return ttip;\n";
    echo "    }\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
