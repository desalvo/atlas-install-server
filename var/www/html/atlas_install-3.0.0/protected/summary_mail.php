<?php

  require("config.php");
  require("db.php");
  require("user_info.php");
  function send_summary_mail($fromdate,$todate) {
    global $LJSFi_VO,$LJSFi_email;
    $logqry = "SELECT site.name"
             . " FROM log"
             . " JOIN request ON log.requestfk=request.id"
             . " JOIN site ON request.sitefk=site.ref"
             . " WHERE log.date >= '" . $fromdate . "' and log.date <= '". $todate . "'"
             . " GROUP BY site.name";
    $result = db_query($logqry);
    $sites = array();
    while ($siteentry=mysqli_fetch_row($result)) {
      if ($siteentry[0] != "") {
        array_push($sites,$siteentry[0]);
      }
    }
    foreach ($sites as $site) {
      $logsiteqry = "SELECT COUNT(*)"
                  . ",site.cs"
                  . ",log.action"
                  . ",log.description"
                  . ",group_concat(request.id)"
                  . ",log.url"
                  . ",log.date"
                  . " FROM log"
                  . " JOIN request ON log.requestfk=request.id"
                  . " JOIN site ON request.sitefk=site.ref"
                  . " WHERE log.date >= '" . $fromdate . "' and log.date <= '". $todate . "'"
                  . "  AND site.name='" . $site . "'"
                  . " GROUP BY site.cs, log.action, log.date, log.description";
      $result = db_query($logsiteqry);
      $numSiteEntries = mysqli_num_rows($result);
      $infoByAction = array();
      while ($logentry=mysqli_fetch_row($result)) {
        if (! isset($infoByAction[$logentry[2]])) $infoByAction[$logentry[2]] = array();
        array_push($infoByAction[$logentry[2]],array("cs" => $logentry[1], "descr" => $logentry[3], "req" => $logentry[4], "url" => $logentry[5], "date" => $logentry[6]));
      }
      $user_info = get_user_info("digest",NULL,NULL,True,$site);
      $emails = array();
      foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
      $to = implode(",",$emails);

      $actions = array_keys($infoByAction);
      $action_summary = array();
      foreach ($actions as $action) {
        array_push($action_summary, $action . " [" . count($infoByAction[$action]) . "]");
      }
      //echo "[MAILTO] ===> " . $site . " [" . $numSiteEntries . "] : " . join(" / ",$action_summary) . " ===> " . $to . "\n";

      $from_header = "From: LJSFi agent <".$LJSFi_email.">";
      $contents = "Summary of activities in site ".$site." [".$LJSFi_VO." VO] from ".$fromdate." to ".$todate."\n";
      $contents = $contents . "Number of actions in the site: ".$numSiteEntries."\n";
      $contents = $contents . join(" / ",$action_summary)."\n\n";
      foreach ($actions as $action) {
        $contents = $contents . strtoupper($action) . " [" . count($infoByAction[$action]) . "]:\n";
        foreach ($infoByAction[$action] as $loginfo) {
          $contents = $contents . "[" . $loginfo["date"] . "] "  . $loginfo["cs"] . "\n" . $loginfo["descr"] . "\n" . $loginfo["url"] . "\n";
        }
        $contents = $contents . "\n\n";
      }
      $subject  = "[SUMMARY] " . $site .  " activities from " . $fromdate . " to " . $todate;
   
      if($contents != "" && $to != "") {
        mail($to, $subject, $contents, $from_header);
        // Update the mail log
        $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                    . "(SELECT ref FROM mail_type WHERE name='summary'),"
                    . "'" . $to . "',"
                    . count($emails) . ","
                    . "'" . date("Y-m-d H:i:s") . "')";
        $result = db_query($maillogqry);
      //} else {
      //  print("Error, no mail submitted!");
      }
    }
  }

  $now = date("Y-m-d H:i:s");
  $interval = 21600;
  $fromdate = date("Y-m-d H:i:s", time() - $interval);
  send_summary_mail($fromdate,$now);
?>
