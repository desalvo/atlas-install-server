<?php
  function historical_reports($requests=true, $releases=true, $jobs=true, $from=NULL, $to=NULL) {
    if ($requests) {
      # Number of requests
      $query = "SELECT COUNT(*) AS entries, NOW()"
              . " FROM request"
             . " WHERE statusfk = (SELECT ref FROM request_status WHERE description = 'autorun')";
      $qry_res = db_query($query,"ro");
      $row = mysqli_fetch_row($qry_res);
      $insquery = "INSERT INTO request_history (entries, date) VALUES (".$row[0].",'".$row[1]."')";
      db_query($insquery,"rw");
    }

    # Release status
    if ($releases) {
      if ($to) {
        $relquery = "SELECT COUNT(*) AS entries, status, '".$to."' FROM release_stat";
      } else {
        $relquery = "SELECT COUNT(*) AS entries, status, UTC_TIMESTAMP() FROM release_stat";
      }
      if (!$from) {
        $query = "SELECT MAX(date) FROM release_stat_history";
        $qry_res = db_query($query,"ro");
        $row = mysqli_fetch_row($qry_res);
        $numrows = mysqli_num_rows($qry_res);
        if ($row[0]) {
          $relquery .= " WHERE date >= '" . $row[0] . "'";
        } else {
          $relquery .= " WHERE date >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 MINUTE)";
        }
      } else {
        $relquery .= " WHERE date >= '" . $from . "'";
      }
      if ($to) {
        $relquery .= " AND date < '" . $to . "'";
      }
      $relquery .= " GROUP BY status ORDER BY status";
      $qry_res = db_query($relquery,"ro");
      while ($row = mysqli_fetch_row($qry_res)) {
        $insquery = "INSERT INTO release_stat_history (entries, status, date) VALUES (".$row[0].",'".$row[1]."','".$row[2]."')";
        db_query($insquery,"rw");
      }
    }

    # Job status
    if ($jobs) {
      if ($to) {
        $jobsubquery = "SELECT COUNT(*) AS entries, 's' AS mode, status, exit_code, validationfk, '".$to."' FROM job";
        $jobretquery = "SELECT COUNT(*) AS entries, 'r' AS mode, status, exit_code, validationfk, '".$to."' FROM job";
        $jobpenquery = "SELECT COUNT(*) AS entries, 'p' AS mode, status, exit_code, validationfk, '".$to."' FROM job";
      } else {
        $jobsubquery = "SELECT COUNT(*) AS entries, 's' AS mode, status, exit_code, validationfk, NOW() FROM job";
        $jobretquery = "SELECT COUNT(*) AS entries, 'r' AS mode, status, exit_code, validationfk, NOW() FROM job";
        $jobpenquery = "SELECT COUNT(*) AS entries, 'p' AS mode, status, exit_code, validationfk, NOW() FROM job";
      }
      $jobpenquery .= " WHERE validationfk = (SELECT ref FROM validation WHERE description='pending')";
      if (!$from) {
        $query = "SELECT MAX(date) FROM job_history";
        $qry_res = db_query($query,"ro");
        $row = mysqli_fetch_row($qry_res);
        $numrows = mysqli_num_rows($qry_res);
        if ($row[0]) {
          $jobsubquery .= " WHERE submission_time > '" . $row[0] . "'";
          $jobretquery .= " WHERE retrieval_time > '" . $row[0] . "'";
        } else {
          $jobsubquery .= " WHERE submission_time > DATE_SUB(NOW(),INTERVAL 30 MINUTE)";
          $jobretquery .= " WHERE retrieval_time > DATE_SUB(NOW(),INTERVAL 30 MINUTE)";
        }
      } else {
        $jobsubquery .= " WHERE submission_time > '" . $from . "'";
        $jobretquery .= " WHERE retrieval_time > '" . $from . "'";
      }
      if ($to) {
        $jobsubquery .= " AND submission_time <= '" . $to . "'";
        $jobretquery .= " AND retrieval_time <= '" . $to . "'";
      }
      if (!$to) {
        $jobquery = $jobsubquery." UNION ".$jobretquery." UNION ".$jobpenquery." GROUP BY mode, status, exit_code, validationfk ORDER BY mode, status, exit_code, validationfk";
      } else {
        $jobquery = $jobsubquery." UNION ".$jobretquery." GROUP BY mode, status, exit_code, validationfk ORDER BY mode, status, exit_code, validationfk";
      }
      $qry_res = db_query($jobquery,"ro");
      while ($row = mysqli_fetch_row($qry_res)) {
        if ($row[2] != NULL) { $status = $row[2]; } else { $status = 'unknown'; }
        if ($row[3] != NULL) { $exit_code = $row[3]; } else { $exit_code = "NULL"; }
        $insquery = "INSERT INTO job_history (entries, mode, status, exit_code, validationfk, date) VALUES (".$row[0].",'".$row[1]."','".$status."',".$exit_code.",".$row[4].",'".$row[5]."')";
        db_query($insquery,"rw");
      }
    }

    return;
  }
?>
