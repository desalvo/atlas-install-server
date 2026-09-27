<?php
  require('config.php'); // Main configuration
  require('db.php');     // database connect script.
  require("jobchart.php");
  require("historical_reports.php");
  if (!file_exists($cache_path)) {
    if (!mkdir($cache_path)) {
      echo ("Cannot create $cache_path");
    }
  }
  if (file_exists($cache_path)) {
    jobplot("14",$LJSFi_VO." installation jobs in the last 14 days",$cache_path . "/LJSFi_jobs_14.html");
    jobplot("7",$LJSFi_VO." installation jobs in the last 7 days",$cache_path . "/LJSFi_jobs_7.html");
  }
  historical_reports();
?>
