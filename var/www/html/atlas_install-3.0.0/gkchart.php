<?php
  function gkplot() {
    $query = "SELECT COUNT(*) AS entries"
                . ", cs REGEXP '.*2119/jobmanager.*' AS lcgce"
                . ", cs REGEXP '.*8443/cream.*' AS creamce"
                . ", grid.name AS gridname"
            . " FROM site, grid"
           . " WHERE last_activity >= DATE_SUB(NOW(),INTERVAL 2 MONTH)"
           .   " AND status=1"
           .   " AND fstype NOT IN ('','-')"
           .   " AND cs <> cename"
           .   " AND cs <> 'unassigned'"
           .   " AND cs NOT LIKE '%_Install'"
           .   " AND site.gridfk=grid.ref"
           . " GROUP BY lcgce, creamce, gridname"
           . " ORDER BY entries DESC";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('string', 'Gatekeeper Type');\n";
    echo "    data.addColumn('number', 'Resources');\n";
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      if ($row[1] == 0 && $row[2] == 0) {
        echo "    data.setValue(".$indx.", 0, 'Other');\n";
      } elseif ($row[1] == 1 && $row[2] == 0) {
        if ($row[3] == 'OSG') {
          echo "    data.setValue(".$indx.", 0, 'OSG CE');\n";
        } else {
          echo "    data.setValue(".$indx.", 0, 'LCG CE');\n";
        }
      } else {
        echo "    data.setValue(".$indx.", 0, 'CREAM CE');\n";
      }
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.PieChart(document.getElementById('gkchart_div'));\n";
    echo "    chart.draw(data, {width: 550, height: 400, title: 'Gatekeeper Types (active sites)'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
