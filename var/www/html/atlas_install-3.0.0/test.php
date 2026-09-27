<?php
  require('config.php'); // Main configuration
  require('db.php');     // database connect script.
  require("historical_reports.php");
  date_default_timezone_set('Europe/Rome');
  $start='2014-11-10 00:00:00';
  $date = strtotime($start);
  $interval = 3600;
  do {
    $from = date('Y-m-d H:i:s',$date);
    $to = date('Y-m-d H:i:s',$date+3600);
    echo $from." -> ".$to."\n";
    historical_reports($requests=false,$releases=false,$jobs=true,$from=$from,$to=$to);
    $date += $interval;
  } while ($date+$interval < time()-$interval);
?>
