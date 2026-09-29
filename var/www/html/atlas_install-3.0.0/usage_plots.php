<?php
require('db.php');     // database connect script.
require('config.php'); // Main configuration
require('jobchart.php');
?>

<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System Usage</TITLE>

<?php require("./css/page_header.php"); page_header("."); ?>
<?php
require("fschart.php");
require("gridchart.php");
require("gkchart.php");
require("mailchart.php");
require("reqchart.php");
?>
</HEAD>
<BODY>
  <div id="main">
    <div id="header">
<?php require("./css/main_header.php"); main_header($LJSFi_VO, "."); ?>
<?php require("./css/menubar.php"); menubar("."); ?>
<?php include "./css/list_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content1">
      <div id="content">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Installation System Usage</h3>
<?php
require_once __DIR__.'/chart_local.php';
foreach ([14,7] as $days) {
  $f=$cache_path."/LJSFi_jobs_{$days}.json";
  if (is_readable($f)) { $j=json_decode((string)file_get_contents($f),true); if(is_array($j)&&isset($j['data'])) echo atlas_chart_line(array_map(fn($r)=>[substr((string)$r[0],5),(float)$r[1]],$j['data']),$j['title']??("Jobs {$days} days"),600,300); }
  else { jobplot((string)$days,$LJSFi_VO." installation jobs in the last {$days} days"); }
}
?>
<?php fsplot(); ?>

<?php gridplot(); ?>
<?php gkplot(); ?>
<?php mailplot(); ?>
<?php reqplot(); ?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
