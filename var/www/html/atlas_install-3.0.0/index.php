<?php
require('dbsetup.php');     // database connect script.
require('config.php');      // Main configuration
?>

<HTML>
<HEAD>
<?php

require('reqchart.php');     // Request chart
reqplot(500,300);

require('relchart.php');     // Release chart
relplot(600,500);

require('jobratechart.php'); // Job Rate chart
jobrateplot(600,500);

require('jobstatchart.php'); // Job Status chart
jobstatplot(600,500);

require('jobrunchart.php');  // Job Running chart
jobrunplot(600,500);

?>
<TITLE><?php echo $LJSFi_VO; ?> Installation System</TITLE>

<?php require("./css/page_header.php"); page_header("."); ?>
<link rel="stylesheet" href="css/jquery-ui.css" />
<script src="js/jquery-ui.js"></script>

<script type="text/javascript">
function openpop(url) {
    newWin = window.open(url,'Details','scrollbars=no,resizable=yes, width=300,height=300,status=no,location=no,toolbar=no');
}
function closeWin() {
    self.close();
}
function checkform(form) {
  var retval=true;
  var getstring="";
  if (form.sitename.value != "") {
    if (getstring == "") getstring += "?"; else getstring += "&";
    getstring += "sitename="+form.sitename.value;
  }
  if (form.rel.value != "") {
    if (getstring == "") getstring += "?"; else getstring += "&";
    getstring += "rel="+form.rel.value;
  }
  if (form.resource.value != "") {
    if (getstring == "") getstring += "?"; else getstring += "&";
    getstring += "resource="+form.resource.value;
  }
  if (form.fstype.value != "") {
    if (getstring == "") getstring += "?"; else getstring += "&";
    getstring += "fstype="+form.fstype.value;
  }
  if (getstring != "") form.action += getstring;
  return retval;
}
</script>
</HEAD>
<BODY>
  <div id="main">
    <div id="header">
<?php require("./css/main_header.php"); main_header($LJSFi_VO, "."); ?>
<?php require("./css/menubar.php"); menubar("."); ?>
<?php include "./css/index_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content">
      <div class="sidebar">
        <!-- insert your sidebar items here -->
        <h3><img src="css/LJSFi2_logo.png" width="200"></h3>
        <h5>Help</h5>
        <p>Select an item from the top menu or use the search facility to select the records.
        <p>Type on the input boxes to see hints about the values.
        <ul><li><a href="https://atlas-install.roma1.infn.it/twiki/bin/view/Main/LJSFiDocumentation">LJSFi Documentation</a></li></ul>
      </div>
      <div id="content">
        <!-- insert the page content here -->
        <h1>Installation Status Search</h1>
        <form method="post" name="select" action="list.php" onsubmit="return checkform(this);">
          <TABLE id='select_tbl' border="1" rules="groups">
            <COLGROUP width="200"></COLGROUP>
<tr><td class="selection">Release</td><td>
<div class="ui-widget">
<input id="rel" />
<script>
$(function() {
var availableReleases = [
<?php
  $qry_res = db_query("SELECT ref,name as value FROM release_data WHERE typefk > 1 ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    echo '"'.$row[1].'",';
  }
?>
];
$( "#rel" ).autocomplete({
   source: availableReleases
});
});
</script>
</div>
</td></tr>
<tr><td class="selection">Grid name</td><td>
<?php
  echo ('<select name="gridname">');
  $rowsel='';
  echo ('<option value="" selected>- any -</option>');
  $qry_res = db_query("SELECT name as value FROM grid ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    if ($row[0] == $rowsel) {
      echo ('<option value="'.$row[0].'" selected>'.$row[0].'</option>');
    } else {
      echo ('<option value="'.$row[0].'">'.$row[0].'</option>');
    }
  }
  echo ('</select>');
?>
</td></tr>
<tr><td class="selection">Site name</td><td>
<div class="ui-widget">
<input id="sitename" size="40"/>
<script>
$(function() {
var availableSitenames = [
<?php
  $qry_res = db_query("SELECT DISTINCT(name) as value FROM site WHERE name <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    echo '"'.$row[0].'",';
  }
?>
];
$( "#sitename" ).autocomplete({
   source: availableSitenames
});
});
</script>
</div>
</td></tr>
<tr><td class="selection">Site arch</td><td>
<?php
  echo ('<select name="arch">');
  $rowsel='';
  echo ('<option value="" selected>- any -</option>');
  $qry_res = db_query("SELECT DISTINCT(arch) as value FROM site WHERE arch <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    if ($row[0] == $rowsel) {
      echo ('<option value="'.$row[0].'" selected>'.$row[0].'</option>');
    } else {
      echo ('<option value="'.$row[0].'">'.$row[0].'</option>');
    }
  }
  echo ('</select>');
?>
</td></tr>
<tr><td class="selection">Resource</td><td>
<div class="ui-widget">
<input id="resource" size="40"/>
<script>
$(function() {
var availableResources = [
<?php
  $qry_res = db_query("SELECT DISTINCT(cename) as value FROM site WHERE cename <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    echo '"'.$row[0].'",';
  }
?>
];
$( "#resource" ).autocomplete({
   source: availableResources
});
});
</script>
</div>
</td></tr>
<tr><td class="selection">Filesystem Type</td><td>
<?php
  echo ('<select name="fstype">');
  $rowsel='';
  echo ('<option value="" selected>- any -</option>');
  $qry_res = db_query("SELECT DISTINCT(fstype) as value FROM site WHERE cename <> '' AND fstype NOT IN ('','-') ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    if ($row[0] == $rowsel) {
      echo ('<option value="'.$row[0].'" selected>'.$row[0].'</option>');
    } else {
      echo ('<option value="'.$row[0].'">'.$row[0].'</option>');
    }
  }
  echo ('</select>');
?>
</td></tr>
<tr><td class="selection">OS type</td><td>
<?php
  echo ('<select name="ostype">');
  $rowsel='';
  echo ('<option value="" selected>- any -</option>');
  $qry_res = db_query("SELECT DISTINCT(CONCAT(osname, ' ', osversion, ' ', osrelease)) as value FROM site WHERE osrelease NOT IN (' ','-','0','UNKNOWN') ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    if ($row[0] == $rowsel) {
      echo ('<option value="'.$row[0].'" selected>'.$row[0].'</option>');
    } else {
      echo ('<option value="'.$row[0].'">'.$row[0].'</option>');
    }
  }
  echo ('</select>');
?>
</td></tr>
<tr><td class="selection">User</td><td>
<?php
  echo ('<select name="user">');
  $rowsel='';
  echo ('<option value="">- any -</option>');
  $qry_res = db_query("SELECT DISTINCT(name) as value FROM user WHERE name <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    if ($row[0] == $rowsel) {
      echo ('<option value="'.$row[0].'" selected>'.$row[0].'</option>');
    } else {
      echo ('<option value="'.$row[0].'">'.$row[0].'</option>');
    }
  }
  echo ('</select>');
?>
</td></tr>
</TABLE>
<P>

<input type="submit" name="submit" value="Search">
<input type="reset" name="reset" value="Reset">
</form>
<P>
<HR>
<P>
<img>
<?php
  $plot1 = $cache_path . "/LJSFi_jobs_14.html";
  $plot2 = $cache_path . "/LJSFi_jobs_7.html";
  if (file_exists($plot1)) include($plot1);
  if (file_exists($plot2)) include($plot2);
?>
</img>
<div id="reqchart_div" style="float: left"></div>
<div id="relchart_div" style="float: left"></div>
<div id="jobratechart_div" style="float: left"></div>
<div id="jobstatchart_div" style="float: left"></div>
<div id="jobrunchart_div" style="float: left"></div>
</CENTER>
</TD></TR>
<TR><TD height="30" background="img/bar2.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
</TABLE>

      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
      <p>SERVICE NAME: <?php echo gethostname(); ?></p>
    </div>
  </div>
</BODY>
</HTML>
