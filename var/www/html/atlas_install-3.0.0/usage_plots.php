<?php
require('db.php');     // database connect script.
require('config.php'); // Main configuration
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
fsplot();
gridplot();
gkplot();
mailplot();
reqplot();
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
<img>
<?php
  $plot1 = $cache_path . "/LJSFi_jobs_14.html";
  $plot2 = $cache_path . "/LJSFi_jobs_7.html";
  if (file_exists($plot1)) include($plot1);
  if (file_exists($plot2)) include($plot2);
?>
</img>
<div id="fschart_div" style="float: left"></div>
<div id="fssitechart_div" style="float: left"></div>
<div id="gridchart_div" style="float: left"></div>
<div id="gkchart_div" style="float: left"></div>
<div id="mailchart_div" style="float: left"></div>
<div id="reqchart_div" style="float: left"></div>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
