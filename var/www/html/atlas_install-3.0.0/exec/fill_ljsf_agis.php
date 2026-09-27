<?php

  require("db.php");
  include("JSON.php");

  function populate_brokerage($mode='') {
      $agis_tags_json = file_get_contents("http://atlas-agis-api.cern.ch/jsoncache/list_presource_sw.schedconf.json");
      $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
      $tag_list = array_keys($json->decode($agis_tags_json));
      print $tag_list[0];
      #$res   = db_query("INSERT INTO release_data","brokerage");
      #while ($row = mysqli_fetch_array($res)) {
      #  $num_critical[$row[0]] = $row[1];
      #}
      #array_push($query_filters, "site.name <> ''");
      #$res   = db_query($query);
      #while ($row = mysqli_fetch_array($res)) {
      #  if (in_array($row[0],$site_list)) {
      #    $val = intval(100*$row[1]/$num_critical[$row[0]]);
      #    if ($val > 100) { $val = 100; }
      #    print $d." ".$row[0]." ".$row[1]."/".$num_critical[$row[0]]." ".$status_code[$val]." ".$linkpre.$link.$row[0].$linkpost."\n";
      #  }
      #}
  }

  populate_brokerage();
?>
