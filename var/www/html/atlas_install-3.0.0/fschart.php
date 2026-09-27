<?php
  function fsplot() {
    $query = "SELECT COUNT(*) AS entries"
                . ", fstype"
                . ", grid.name AS gridname"
            . " FROM site, grid"
           . " WHERE last_activity >= DATE_SUB(NOW(),INTERVAL 2 MONTH)"
           .   " AND fstype NOT IN ('','-')"
           .   " AND status=1"
           .   " AND cs <> cename"
           .   " AND cs <> 'unassigned'"
           .   " AND cs NOT LIKE '%_Install'"
           .   " AND site.gridfk = grid.ref"
           . " GROUP BY fstype, gridname"
           . " ORDER BY entries DESC";
    $sitequery = "SELECT COUNT(DISTINCT site.name) AS entries"
                    . ", fstype"
                    . ", grid.name AS gridname"
                . " FROM site, grid"
               . " WHERE last_activity >= DATE_SUB(NOW(),INTERVAL 2 MONTH)"
               .   " AND fstype NOT IN ('','-')"
               .   " AND status=1"
               .   " AND cs <> cename"
               .   " AND cs <> 'unassigned'"
               .   " AND cs NOT LIKE '%_Install'"
               .   " AND site.gridfk = grid.ref"
               . " GROUP BY fstype, gridname"
               . " ORDER BY entries DESC";
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('string', 'FileSystem Type');\n";
    echo "    data.addColumn('number', 'Resources');\n";
    $qry_res = db_query($query,"ro");
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    data.setValue(".$indx.", 0, '".$row[1]." (".$row[2]."): ".$row[0]."');\n";
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.PieChart(document.getElementById('fschart_div'));\n";
    echo "    chart.draw(data, {width: 550, height: 400, title: 'FileSystem Types (active resources)'});\n";
    echo "\n";
    echo "    var sitedata = new google.visualization.DataTable();\n";
    echo "    sitedata.addColumn('string', 'FileSystem Type');\n";
    echo "    sitedata.addColumn('number', 'Resources');\n";
    $qry_res = db_query($sitequery,"ro");
    echo "    sitedata.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    sitedata.setValue(".$indx.", 0, '".$row[1]." (".$row[2]."): ".$row[0]."');\n";
      echo "    sitedata.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var sitechart = new google.visualization.PieChart(document.getElementById('fssitechart_div'));\n";
    echo "    sitechart.draw(sitedata, {width: 550, height: 400, title: 'FileSystem Types (active sites)'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
