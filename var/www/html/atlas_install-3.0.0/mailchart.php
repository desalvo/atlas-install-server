<?php
  function mailplot() {
    $query = "SELECT COUNT(*) AS entries"
                . ", mail_type.name AS mailtype"
            . " FROM mail_log, mail_type"
           . " WHERE date >= DATE_SUB(NOW(),INTERVAL 1 MONTH)"
           .   " AND mail_log.typefk=mail_type.ref"
           . " GROUP BY mailtype"
           . " ORDER BY mailtype";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('string', 'Mail Type');\n";
    echo "    data.addColumn('number', 'Number');\n";
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    data.setValue(".$indx.", 0, '".$row[1]."');\n";
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.PieChart(document.getElementById('mailchart_div'));\n";
    echo "    chart.draw(data, {width: 550, height: 400, title: 'Mail Types'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
