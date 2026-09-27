<?php
  function jobplot($interval,$title,$file=NULL) {
    $COLORS = array("baseline" => "FFFFFF"
                   ,"failed"   => "FF0000"
                   ,"ok"       => "00FF00"
                   ,"pending"  => "0000FF"
                   ,"other"    => "333333");
    $LEGENDS = array("baseline" => ""
                    ,"failed"   => "Failed"
                    ,"ok"       => "Done"
                    ,"pending"  => "Pending"
                    ,"other"    => "Other");
    $query = "SELECT DATE_FORMAT(submission_time,'%Y-%m-%d') AS date"
                . ", DATE_FORMAT(submission_time,'%b') AS month"
                . ", DATE_FORMAT(submission_time,'%d') AS day"
                . ", validation.description AS valstatus"
                . ", count(*) AS entries"
            . " FROM job, validation"
           . " WHERE DATE_SUB(CURDATE(),INTERVAL ${interval} DAY) <= submission_time"
             . " AND job.validationfk = validation.ref"
           . " GROUP BY date, valstatus ORDER BY submission_time";
    $qry_res = db_query($query,"ro");
    $months = array();
    $days   = array();
    $submitted_jobs = array();
    $index = 0;
    $maxjobs = 0;
    $categories = array("baseline");
    $step = intval($interval)/4;
    if ($step < 1) $step = 1;
    while ($row = mysqli_fetch_row($qry_res)) {
      if (!isset($submitted_jobs[$row[0]])) {
        if ($index % $step == 0) {
          array_push($months,$row[1]);
          array_push($days,$row[2]);
        }
        $submitted_jobs[$row[0]] = array();
        $jobs = 0;
        $index++;
      }
      $jobs += $row[4];
      $submitted_jobs[$row[0]][$row[3]] = $row[4];
      if (!in_array($row[3],$categories)) array_push($categories,$row[3]);
      if ($jobs > $maxjobs) $maxjobs = $jobs;
    }
    $jobdata = array();
    $jobstack = array();
    foreach ($submitted_jobs as $jobdate => $jobinfo) {
      foreach ($categories as $category) {
        if (!isset($jobstack[$jobdate])) $jobstack[$jobdate] = 0;
        if (!isset($jobinfo[$category])) $jobval = $jobstack[$jobdate]; else $jobval = $jobinfo[$category] + $jobstack[$jobdate];
        if (!isset($jobdata[$category])) $jobdata[$category] = array();
        array_push($jobdata[$category],$jobval);
        if (isset($jobinfo[$category])) $jobstack[$jobdate] += $jobinfo[$category];
      }
    }
    $chartdata  = array();
    $chartcolor = array();
    foreach ($jobdata as $jobkey => $jobval) {
      if (isset($COLORS[$jobkey])) $color = $COLORS[$jobkey]; else $color = $COLORS["other"];
      array_push($chartcolor,$color);
      array_push($chartdata,implode($jobval,","));
    }
    $chartfill = array();
    $chartlegend = array();
    $indx = 0;
    foreach ($categories as $category) {
      if ($category != "baseline") {
        if (isset($COLORS[$category])) $color = $COLORS[$category]; else $color = $COLORS["other"];
        array_push($chartfill,"b,".$color.",".($indx-1).",".$indx.",0");
      }
      if (isset($LEGENDS[$category])) $legend = $LEGENDS[$category]; else $legend = $LEGENDS["other"];
      array_push($chartlegend, $legend);
      $indx++;
    }
    $chart="http://chart.apis.google.com/chart?cht=lc&chs=400x200&chtt=".ereg_replace(" ","+",$title)."&chm=".implode($chartfill,"|")."&chco=".implode($chartcolor,",")."&chd=t:".implode($chartdata,"|")."&chxt=x,y,x&chxl=0:|".implode($months,"|")."|2:|".implode($days,"|")."&chds=0,".$maxjobs."&chxr=1,0,".$maxjobs."&chdl=".implode($chartlegend,"|");
    if ($file) {
      $fh = fopen($file, 'w') or die("Cannot open $file");
      fwrite($fh, "<img src=\"".$chart."\">");
      fclose($fh);
    } else {
      echo "<img src=\"".$chart."\">";
    }
    return;
  }
?>
