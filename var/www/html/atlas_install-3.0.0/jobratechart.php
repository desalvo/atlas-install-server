<?php
  function jobrateplot($width=550, $height=400) {
    $query = "SELECT SUM(entries) AS jobs, max(UNIX_TIMESTAMP(DATE_FORMAT(date,'%Y-%m-%d')))"
            . " FROM job_history"
           . " WHERE mode='s' AND date >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 WEEK)"
           . " GROUP BY YEAR(date), MONTH(date), DAY(date)";
    $qry_res = db_query($query,"ro");
    echo "<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>\n";
    echo "<script type=\"text/javascript\">\n";
    echo "  google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});\n";
    echo "  google.setOnLoadCallback(drawChart);\n";
    echo "  function drawChart() {\n";
    echo "    var data = new google.visualization.DataTable();\n";
    echo "    data.addColumn('datetime', 'Date');\n";
    echo "    data.addColumn('number', 'Jobs');\n";
    echo "    data.addRows(".mysqli_num_rows($qry_res).");\n";
    $indx = 0;
    while ($row = mysqli_fetch_row($qry_res)) {
      echo "    var currdate = new Date();\n";
      echo "    currdate.setTime(".$row[1]."*1000);\n";
      echo "    data.setValue(".$indx.", 0, currdate);\n";
      echo "    data.setValue(".$indx.", 1, ".$row[0].");\n";
      $indx += 1;
    }
    echo "    var chart = new google.visualization.ColumnChart(document.getElementById('jobratechart_div'));\n";
    echo "    chart.draw(data, {width: ".$width.", height: ".$height.", isStacked: true, legend: { position: 'top', maxLines: 8 }, bar: { groupWidth: '95%' }, title: 'Installation Job Submission Rate'});\n";
    echo "  }\n";
    echo "</script>\n";
    return;
  }
?>
