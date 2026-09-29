<?php
require('config.php'); require('db.php'); require('jobchart.php'); require('historical_reports.php');
if(!is_dir($cache_path) && !mkdir($cache_path,0770,true)) { fwrite(STDERR,"Cannot create $cache_path\n"); exit(1); }
jobplot('14',$LJSFi_VO.' installation jobs in the last 14 days',$cache_path.'/LJSFi_jobs_14.html');
jobplot('7',$LJSFi_VO.' installation jobs in the last 7 days',$cache_path.'/LJSFi_jobs_7.html');
historical_reports();
fwrite(STDERR,"[atlas-maintenance] plot summaries refreshed; no external chart service used\n");
?>
