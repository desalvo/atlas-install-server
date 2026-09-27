<?php
  function jobstatplot($width=650, $height=400) {
    $query = "SELECT DISTINCT(v.description) AS valstatus"
            . " FROM job_history jh, validation v"
           . " WHERE jh.date >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 WEEK)"
             . " AND jh.mode='r'"
             . " AND jh.validationfk=v.ref"
           . " ORDER BY valstatus DESC";
    $qry_res = db_query($query,"ro");
    $status_array = array();
    while ($row = mysqli_fetch_row($qry_res)) {
      array_push($status_array,$row[0]);
    }
    $query = "SELECT SUM(jh.entries) AS jobs"
                . ", v.description AS valstatus"
                . ", UNIX_TIMESTAMP(DATE_FORMAT(date,'%Y-%m-%d')) AS gdate"
            . " FROM job_history jh, validation v"
           . " WHERE jh.mode='r'"
             . " AND jh.date >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 WEEK)"
             . " AND jh.validationfk=v.ref"
           . " GROUP BY gdate, valstatus"
           . " ORDER BY gdate, valstatus";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawRelChart);\n";
    echo "  function drawRelChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('datetime', 'Date');\n";
    foreach ($status_array as $status) {
      echo "    data.addColumn('number', '".$status."');\n";
    }
    $indx = 0;
    $values = array();
    while ($row = mysqli_fetch_row($qry_res)) {
      if (!array_key_exists($row[2],$values)) {
        $values[$row[2]] = array();
        foreach ($status_array as $status) {
          $values[$row[2]][$status] = 0;
          $values[$row[2]]['indx'] = $indx;
        }
        $indx += 1;
      }
      $values[$row[2]][$row[1]] = $row[0];
    }
    echo "    data.addRows(".$indx.");\n";
    foreach ($values as $valdate => $val) {
      echo "    var currdate = new Date();\n";
      echo "    currdate.setTime(".$valdate."*1000);\n";
      echo "    data.setValue(".$val['indx'].", 0, currdate);\n";
      $indx = 1;
      foreach ($status_array as $status) {
        echo "    data.setValue(".$val['indx'].", ".$indx.", ".$val[$status].");\n";
        $indx += 1;
      }
    }
    echo "    var chart = new google.visualization.ColumnChart(document.getElementById('jobstatchart_div'));\n";
    echo "    chart.draw(data, {width: ".$width.", height: ".$height.", isStacked: true, legend: { position: 'top', maxLines: 8 }, bar: { groupWidth: '95%' }, title: 'Installation Job Status'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
