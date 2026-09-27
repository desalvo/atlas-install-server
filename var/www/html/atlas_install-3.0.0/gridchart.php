<?php
  function gridplot() {
    $query = "SELECT COUNT(*) AS entries"
                . ", grid.name AS gridname"
            . " FROM site, grid"
           . " WHERE last_activity >= DATE_SUB(NOW(),INTERVAL 2 MONTH)"
           .   " AND fstype NOT IN ('','-')"
           .   " AND site.gridfk=grid.ref"
           .   " AND status=1"
           .   " AND cs <> cename"
           .   " AND cs <> 'unassigned'"
           .   " AND cs NOT LIKE '%_Install'"
           . " GROUP BY gridname"
           . " ORDER BY entries";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('string', 'Grid Name');\n";
    echo "    data.addColumn('number', 'Resources');\n";
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    data.setValue(".$indx.", 0, '".$row[1]."');\n";
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.PieChart(document.getElementById('gridchart_div'));\n";
    echo "    chart.draw(data, {width: 550, height: 400, title: 'Grid Resources (active sites)'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
