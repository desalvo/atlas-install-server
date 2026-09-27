<?php
  function reqplot($width=550, $height=400) {
    $query = "SELECT entries, UNIX_TIMESTAMP(date)"
            . " FROM request_history"
           . " WHERE date >= DATE_SUB(NOW(),INTERVAL 2 WEEK)";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('datetime', 'Date');\n";
    echo "    data.addColumn('number', 'Requests');\n";
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    var currdate = new Date();\n";
      echo "    currdate.setTime(".$row[1]."*1000);\n";
      echo "    data.setValue(".$indx.", 0, currdate);\n";
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.AreaChart(document.getElementById('reqchart_div'));\n";
    echo "    chart.draw(data, {width: ".$width.", height: ".$height.", title: 'Installation Requests'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
