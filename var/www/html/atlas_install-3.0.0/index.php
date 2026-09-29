<?php
require('dbsetup.php');     // database connect script.
require('config.php');      // Main configuration
$it = atlas_lang()==='it';
?>

<HTML>
<HEAD>
<?php

require('reqchart.php');     // Request chart

require('relchart.php');     // Release chart

require('jobratechart.php'); // Job Rate chart

require('jobstatchart.php'); // Job Status chart

require('jobrunchart.php');  // Job Running chart

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
  return true;
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
        <h3><img src="img/ljsf3-logo.png" width="220"></h3>
        <h5><?php echo $it?'Aiuto':'Help'; ?></h5>
        <p><?php echo $it?'Seleziona una voce dal menu oppure usa la ricerca per filtrare i record.':'Select an item from the menu or use the search facility to filter records.'; ?>
        <p><?php echo $it?'Digita nei campi per visualizzare i suggerimenti disponibili.':'Type in the input boxes to see available suggestions.'; ?>
        <ul><li><a href="documentation.php"><?php echo atlas_h(atlas_t('documentation')); ?></a></li></ul>
      </div>
      <div id="content">
        <!-- insert the page content here -->
        <h1><?php echo $it?'Ricerca stato installazioni':'Installation status search'; ?></h1>
        <form method="get" name="select" action="list.php" onsubmit="return checkform(this);">
          <TABLE id='select_tbl' border="1" rules="groups">
            <COLGROUP width="220"></COLGROUP>
<tr><td class="selection"><?php echo $it?'Release':'Release'; ?></td><td>
<div class="ui-widget">
<input id="rel" name="rel" list="atlas-rel-list" data-atlas-datalist="atlas-rel-list" autocomplete="off" />
<datalist id="atlas-rel-list">
<?php
  $vals=[];
  $qry_res = db_query("SELECT ref,name as value FROM release_data WHERE typefk > 1 ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) { $vals[]=(string)$row[1]; echo '<option value="'.atlas_h((string)$row[1]).'">'; }
?>
</datalist>
<script>
$(function() {
var availableReleases = <?php echo json_encode($vals, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
if ($.fn && $.fn.autocomplete) { $( "#rel" ).autocomplete({ source: availableReleases }); }
});
</script>
</div>
</td></tr>
<tr><td class="selection"><?php echo $it?'Nome grid':'Grid name'; ?></td><td>
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
<tr><td class="selection"><?php echo $it?'Nome sito':'Site name'; ?></td><td>
<div class="ui-widget">
<input id="sitename" name="sitename" size="40" list="atlas-sitename-list" data-atlas-datalist="atlas-sitename-list" autocomplete="off" />
<datalist id="atlas-sitename-list">
<?php
  $vals=[];
  $qry_res = db_query("SELECT DISTINCT(name) as value FROM site WHERE name <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) { $vals[]=(string)$row[0]; echo '<option value="'.atlas_h((string)$row[0]).'">'; }
?>
</datalist>
<script>
$(function() {
var availableSitenames = <?php echo json_encode($vals, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
if ($.fn && $.fn.autocomplete) { $( "#sitename" ).autocomplete({ source: availableSitenames }); }
});
</script>
</div>
</td></tr>
<tr><td class="selection"><?php echo $it?'Architettura sito':'Site arch'; ?></td><td>
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
<tr><td class="selection"><?php echo $it?'Risorsa':'Resource'; ?></td><td>
<div class="ui-widget">
<input id="resource" name="resource" size="40" list="atlas-resource-list" data-atlas-datalist="atlas-resource-list" autocomplete="off" />
<datalist id="atlas-resource-list">
<?php
  $vals=[];
  $qry_res = db_query("SELECT DISTINCT(cename) as value FROM site WHERE cename <> '' ORDER BY value","ro");
  while ($row = mysqli_fetch_row($qry_res)) { $vals[]=(string)$row[0]; echo '<option value="'.atlas_h((string)$row[0]).'">'; }
?>
</datalist>
<script>
$(function() {
var availableResources = <?php echo json_encode($vals, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
if ($.fn && $.fn.autocomplete) { $( "#resource" ).autocomplete({ source: availableResources }); }
});
</script>
</div>
</td></tr>
<tr><td class="selection"><?php echo $it?'Tipo filesystem':'Filesystem Type'; ?></td><td>
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
<tr><td class="selection"><?php echo $it?'Tipo OS':'OS type'; ?></td><td>
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
<tr><td class="selection"><?php echo $it?'Utente':'User'; ?></td><td>
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

<input type="submit" name="submit" value="<?php echo $it?'Cerca':'Search'; ?>">
<input type="reset" name="reset" value="<?php echo $it?'Reimposta':'Reset'; ?>">
</form>
<P>
<HR>
<P>
<?php
try {
  require_once __DIR__.'/chart_local.php';
  foreach ([14,7] as $days) {
    $f=$cache_path."/LJSFi_jobs_{$days}.json";
    if (is_readable($f)) { $j=json_decode((string)file_get_contents($f),true); if(is_array($j)&&isset($j['data'])) echo atlas_chart_line(array_map(fn($r)=>[substr((string)$r[0],5),(float)$r[1]],$j['data']),$j['title']??("Jobs {$days} days"),600,300); }
    else { jobplot((string)$days,$LJSFi_VO." installation jobs in the last {$days} days"); }
  }
  reqplot(500,300);
  relplot(600,500);
  jobrateplot(600,500);
  jobstatplot(600,500);
  jobrunplot(600,500);
} catch (Throwable $e) {
  error_log('[ATLAS_APP] '.json_encode(['event'=>'home_chart_render_failed','request_id'=>atlas_request_id(),'message'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  echo '<div class="atlas-alert error">'.atlas_h(atlas_t('app_error_msg')).' <span class="atlas-code">'.atlas_h(atlas_request_id()).'</span></div>';
}
?>

      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer" class="atlas-home-footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it"><?php echo $it?'Contatta il team di installazione':'Contact the installation team'; ?></a></p>
      <p>SERVICE NAME: <?php echo gethostname(); ?></p>
    </div>
  </div>
</BODY>
</HTML>
