<HTML>
<HEAD>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = critical_tbl.getElementsByTagName("tr");   
  for(i = 0; i < rows.length; i++){
    rows[i].style.backgroundColor='#EFEFEF';
  }
  if (form.sitename.value=='') {
    sitename_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (form.rel.value=='') {
    rel_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (!retval) {
    alert('Please fill all the highlighted fields');
  }

  return retval;
}

function pageReload() {
  criticality.action='critical.php';
  criticality.submit();
}

//-->
</script>

<?php

  require("db.php");
  require("config.php");
  require("user_info.php");
  require("combo.php");
  require ("$LJSFi_PATH/access_log.php");

  require('criticalmap_chart.php'); // Critical releases map
  $get_opts = array();
  if (isset($_REQUEST['tier_level'])) {
    $tier_level = db_int($_REQUEST['tier_level'],0,99);
    array_push($get_opts,"tier_level=".$tier_level);
  } else {
    $tier_level = NULL;
  }
  if (isset($_REQUEST['site_type'])) {
    $site_type = $_REQUEST['site_type'];
  } else {
    $site_type = NULL;
  }

  // LJSF sites
  if (isset($_REQUEST["debug"])) echo "Querying sites:<BR>";
  $query = "SELECT ref, cs"
         .  " FROM site"
         . " WHERE status = 1";
  $result = db_query($query);
  $ljsf_sites = array();
  $ljsf_sites_by_id = array();
  while ($row = mysqli_fetch_row($result)) {
    if (!array_key_exists($row[1],$ljsf_sites)) $ljsf_sites[$row[1]] = $row[0];
    $ljsf_sites_by_id[$row[0]] = $row[1];
  }

  // AGIS sites
  if (isset($_REQUEST["debug"])) echo "Querying AGIS sites:<BR>";
  $query = "SELECT name"
         .  " FROM ljsf_infosys.panda_resource"
         . " WHERE statusfk = (SELECT ref FROM ljsf_infosys.status where name='online')";
  if (isset($_REQUEST["tier_level"])) $query .= " AND tier_level = ".db_int($_REQUEST["tier_level"],0,99);
  if (isset($_REQUEST["site_type"])) {
    $values = split(",",$_REQUEST["site_type"]);
    $val_array = array();
    foreach ($values as $val) array_push($val_array, db_quote($val,'ro'));
    $st_opt = implode($val_array,",");
  } else {
    $st_opt = "'analysis','production'";
  }
  $query .= " AND typefk IN (SELECT ref FROM ljsf_infosys.resource_type WHERE name IN (".$st_opt."))";
  $result = db_query($query);
  $all_sites = array();
  while ($row = mysqli_fetch_row($result)) {
    if (array_key_exists($row[0],$ljsf_sites)) array_push($all_sites, $ljsf_sites[$row[0]]);
  }
  $num_sites = count($all_sites);

  if (!is_null($tier_level)) {
    if (isset($_REQUEST["debug"])) echo "Creating map<BR>";
    criticalmap_plot($tier_level, $site_type, $all_sites, 600,500);
  }
?>

<TITLE>Release criticality flags management for the <?php echo $LJSFi_VO; ?> Software Installation System</TITLE>
<script language="JavaScript" src="../js/calendarmysql.js"></script>
<?php require("../css/page_header.php"); page_header(".."); ?>
<link rel="stylesheet" href="../css/jquery-ui.css" />
<script src="../js/jquery-ui.js"></script>
</HEAD>
<BODY>

<script type="text/javascript" src="../js/wz_tooltip.js"></script>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/req_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content1">
      <div id="content">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Software Critical Releases</h3>

<?php
  // Username
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  if (!isset($_POST["user"]) || $_POST["user"] == "") $_POST["user"] = $sslusername;
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Unknown user. Please <A HREF='user.php'>register to LJSFi</A> first.</B></FONT>");
  } elseif ($user_info[0]['enabled'] == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Your user is disabled. Please contact the LJSFi admin.</B></FONT>");
  } else {
    $user_data = $user_info[0];
    $priv_critical = $user_data['priv_critical'];
    if ($priv_critical == 1) {
?>
<form method="post" name="criticality" action="critical.php" onsubmit="return checkform(this);">
<TABLE id='critical_tbl' border="1" rules="groups" summary="Manage criticality flag" class="greentable">
<COLGROUP width="130"></COLGROUP>
<COLGROUP width="470"></COLGROUP>
<?php
      // Release name combo box
      echo ("<TR id='rel_tr'><TD><EM>Release&nbsp;Name</EM></TD><TD>");
      // echo ("<select multiple size=5 name='relref[]' size='1'>");
    }
    $rel=array();
    $query = "SELECT ref, tag, obsolete"
           . "  FROM release_data"
           . " WHERE name <> 'ALL'";
    $result = db_query($query);
    $patterns = array('/^VO-atlas-offline/','/^VO-atlas-production/','/^VO-atlas-/');
    $replacements = array('AtlasOffline','AtlasProduction','');
    while ($row = mysqli_fetch_array($result)) {
      $swname = preg_replace($patterns,$replacements,$row[1]);
      $rel[$row[0]] = $swname;
    }
    asort($rel);
    if ($priv_critical == 1) {
      $relref = array();
      $relname = array();
?>
<div class="ui-widget">
<input id="relref" name="relref" size="50"/>
<script>
$(function() {
var availableReleases = [
<?php
      $relsel = NULL;
      foreach ($rel as $rref => $rname) {
        array_push($relref,$rref);
        array_push($relname,$rname);
        echo '"'.$rname.'",';
        if (isset($_POST["relref"]) && $_POST["relref"] == $rname) {
          $relsel = $rref;
        }
      }
      //if (isset($_POST["relref"][0])) { $defsel = $rel[$_POST["relref"][0]]; } else { $defsel = NULL; }
?>
];
$( "#relref" ).autocomplete({
   source: availableReleases
});
});
</script>
<?php
      //combo_box($relref,NULL,$defsel,$relname,false);
      //echo '</select></TD></TR>';

      // Tier Type combo box
      echo ("<TR id='site_tr'><TD><EM>Site&nbsp;Type</EM></TD><TD>");
      echo ("<select name='sitetype' size='1'>");
      $query = "SELECT ref, description"
             . "  FROM site_type";
      $result = db_query($query);
      $sitetypefk=array();
      $sitetype=array();
      while ($row = mysqli_fetch_array($result)) {
        array_push($sitetypefk,$row[0]);
        array_push($sitetype,$row[1]);
      }
      combo_box($sitetypefk,NULL,1,$sitetype);
      echo '</select></TD></TR>';

      // Validity dates
      $valfromdef = date("Y-m-d");
      echo "<TR id='valfrom_tr'><TD><EM>Validity&nbsp;from</EM></TD><TD>";
      echo '<input name="cvalfrom" value="'.$valfromdef.'"><a href="javascript:calfrom.popup();">';
      echo '<img src="img/cal.gif" width="16" height="16" border="0" alt="Select date"></a>';
      echo '</TD></TR>';
      echo "<TR id='valto_tr'><TD><EM>Validity&nbsp;to</EM></TD><TD>";
      echo '<input name="cvalto"><a href="javascript:calto.popup();">';
      echo '<img src="img/cal.gif" width="16" height="16" border="0" alt="Select date"></a>';
      echo '</TD></TR>';
  
      // Description
      echo "<TR id='descr_tr'><TD><EM>Description</EM></TD><TD>";
      echo '<input name="cdesc">';
      echo '</TD></TR>';

      if (isset($_POST['add'])) {
        # Get the user id
        $userfk = $user_data['ref'];
        # Add the criticality flag
        #if ($userfk >= 0 && isset($_POST["relref"]) && count($_POST["relref"]) > 0
        if ($userfk >= 0 && isset($relsel) && isset($_POST["sitetype"]) && $_POST["sitetype"] != '') {
          $criticalquery = "SELECT ref"
                       . "  FROM release_data"
                       . " WHERE ref=".$relsel
                       . "   AND critical=0";
          $res = db_query($criticalquery);
          $numrows = mysqli_num_rows($res);
          echo '<TR><TD>&nbsp;</TD><TD nowrap>';
          if ($numrows > 0) {
            $date = date("Y-m-d H:i:s");
            $query = "UPDATE release_data"
                   . "   SET critical=1, critical_site_typefk=".db_int($_POST["sitetype"],1)
                         .", critical_userfk=".$userfk.", critical_date='".$date."'";
            if (isset($_POST['cvalfrom']) && $_POST['cvalfrom'] != '') {
              $query .= ", critical_validity_from='".$_POST['cvalfrom']."'";
            } else {
              $query .= ", critical_validity_from=NULL";
            }
            if (isset($_POST['cvalto']) && $_POST['cvalto'] != '') {
              $query .= ", critical_validity_to='".$_POST['cvalto']."'";
            } else {
              $query .= ", critical_validity_to=NULL";
            }
            if (isset($_POST['cdesc']) && $_POST['cdesc'] != '') {
              $query .= ", critical_description='".$_POST['cdesc']."'";
            } else {
              $query .= ", critical_description=NULL";
            }
            $query .= " WHERE ref=".$relsel;
            $res = db_query($query);
            echo '<FONT COLOR="red"><B>You have successfully flagged release</FONT> <FONT COLOR="blue">'.$rel[$relsel].'</FONT> <FONT color="red">as critical</B></FONT>';

            # Send the notification email
            $subj='[CRITICALITY ADD] Criticality flag added for release '.$rel[$relsel];
            $body='A criticality flag for release '.$rel[$relsel] 
                 .' has been added by '.$_POST["user"]." <".$user_data['email'].">\n";
            $from = "From: LJSFi agent <".$LJSFi_email.">";
            $user_info = get_user_info("subscribers",NULL,NULL,True);
            $emails = array();
            foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
            $to = implode(",",$emails);
            if ($to != "") {
              mail($to, $subj, $body, $from);
              // Update the mail log
              $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                          . "(SELECT ref FROM mail_type WHERE name='critical'),"
                          . "'" . $to . "',"
                          . count($emails) . ","
                          . "'" . date("Y-m-d H:i:s") . "')";
              $result = db_query($maillogqry);
            }

          } else {
            echo '<FONT COLOR=red>Release '.$rel[$relsel].' is already flagged as critical<FONT>';
          }
          echo '</TD></TR>';
        }
      }
?>
</TABLE>
<input type="submit" name="add" value="Add">
</form>

<script language="JavaScript">
var calfrom = new calendarmysql(document.forms['criticality'].elements['cvalfrom']);
calfrom.year_scroll = true;
calfrom.time_comp = false;
var calto = new calendarmysql(document.forms['criticality'].elements['cvalto']);
calto.year_scroll = true;
calto.time_comp = false;
</script>

<form method="post" name="updatecriticality" action="critical.php">
<?php } ?>
<TABLE border="1" rules="groups" summary="Remove criticality flags">
<h4>Critical releases</h4>
<?php
    if ($priv_critical == 0) {
      echo "<B>You cannot edit release criticality flags.<BR>";
      echo "Please update your <A HREF='user.php'>user profile</A> and ask for additional privileges<BR>if you need to update</B>"; 
    }
?>
<COLGROUP width="900"></COLGROUP>
<TR><TD colspan="2">
<?php
    if ($priv_critical == 1) {
      if (isset($_POST['remove'])) {
        # Check for the user id
        $userfk = $user_data['ref'];
        # Remove the criticality flags
        if ($userfk >= 0) {
          while (list ($key,$val) = @each ($_POST['cflags'])) {
            $val = db_int($val,1);
            $criticalquery = "SELECT ref"
                           . "  FROM release_data"
                           . " WHERE ref=".$val
                           . "   AND critical=1";
            $res = db_query($criticalquery);
            $numrows = mysqli_num_rows($res);
            echo '<CENTER>';
            if ($numrows>0) {
              $date = date("Y-m-d H:i:s");
              $query = "UPDATE release_data"
                     . "   SET critical=0, critical_userfk=".db_int($userfk,0).", critical_date=".db_quote($date,'rw')
                     . " WHERE ref=".db_int($val,1);
              $res = db_query($query);
              echo '<FONT COLOR="red">You have successfully removed the criticality flag for release</FONT><BR><FONT COLOR="blue"><B>'.$rel[$val]."</FONT><B>";
              # Send the notification email
              $subj='[CRITICALITY REMOVE] Criticality flag removed for release '.$rel[$val];
              $body='The criticality flag for release '.$rel[$val]
                   .' has been removed by '.$_POST["user"]." <".$user_data['email'].">\n";
              $from = "From: LJSFi agent <".$LJSFi_email.">";
              $user_info = get_user_info("subscribers",NULL,NULL,True);
              $emails = array();
              foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
              $to = implode(",",$emails);
              if ($to != "") {
                mail($to, $subj, $body, $from);
                // Update the mail log
                $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                            . "(SELECT ref FROM mail_type WHERE name='critical'),"
                            . "'" . $to . "',"
                            . count($emails) . ","
                            . "'" . date("Y-m-d H:i:s") . "')";
                $result = db_query($maillogqry);
              }

            } else {
              echo 'Release '.$rel[$val].' is not set as critical';
            }
            echo '</CENTER></TD></TR>';
            echo '<TR><TD colspan="2"><HR>';
          }
        }
      }
    }

    // Current criticality flags
    //$colors = array('#DFFFDF','#C2C2C2');
    #$colors = array('whitetable','graytable');
    $colors = array('redline','orangeline','yellowline','grayline','lightgreenline','greenline');

    // Release statistics
    if (isset($_REQUEST["debug"])) echo "Querying release stats<BR>";
    $query = "SELECT release_stat.name, COUNT(DISTINCT(site.cs)), GROUP_CONCAT(DISTINCT site.ref)"
           .  " FROM release_stat, site"
           . " WHERE release_stat.sitefk = site.ref"
           .   " AND (release_stat.status = 'installed' OR release_stat.status LIKE 'validate%')"
           .   " AND site.ref in (".implode($all_sites,",").")"
           . " GROUP BY release_stat.name";
    $result = db_query($query);
    $all_rels = array();
    if (isset($_REQUEST['show_missing'])) $missing_resources = array();
    while ($row = mysqli_fetch_row($result)) {
      $all_rels[$row[0]] = intval($row[1]);
      $installed_sites = split(",",$row[2]);
      foreach ($all_sites as $siteref) {
        if (isset($_REQUEST['show_missing'])) {
          if (!in_array($siteref, $installed_sites)) {
            if (!array_key_exists($row[0],$missing_resources)) $missing_resources[$row[0]] = array();
            array_push($missing_resources[$row[0]],'<A HREF="../list.php?resource='.$ljsf_sites_by_id[$siteref].'">'.$ljsf_sites_by_id[$siteref].'</A>');
          }
        }
      }
    }

    // Releases
    if (isset($_REQUEST["debug"])) echo "Querying releases<BR>";
    $query = "SELECT rd.ref, rd.name, st.description"
           .      ", DATE_FORMAT(rd.critical_validity_from,'%Y-%m-%d')"
           .      ", DATE_FORMAT(rd.critical_validity_to,'%Y-%m-%d')"
           .      ", u.name, u.email, rd.critical_date"
           .      ", rd.critical_validity_to, u.name, u.email, rd.critical_date"
           .      ", rd.critical_description"
           .      ", rd.critical_description"
           . "  FROM release_data rd, user u, site_type st"
           . " WHERE rd.critical_userfk = u.ref"
           .   " AND rd.critical = 1"
           .   " AND rd.critical_site_typefk = st.ref"
           .   " AND (rd.critical_validity_from IS NULL OR rd.critical_validity_from < NOW())"
           .   " AND (rd.critical_validity_to IS NULL OR rd.critical_validity_to >= NOW())";
    if (isset($_REQUEST["age"]) && $_REQUEST["age"] != "") $query .= " AND rd.critical_validity_from > DATE_SUB(NOW(), INTERVAL ".db_int($_REQUEST["age"],0,36500)." DAY)";
    $query .= " ORDER BY rd.critical_date DESC";
    $result = db_query($query);
    if (isset($_REQUEST["debug"])) echo "Done<P>\n";
    $indx   = 0;
?>
<TABLE border="0" rules="groups" summary="Criticality flags list">
<COLGROUP width="60"></COLGROUP>
<COLGROUP width="350"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<?php if (isset($_REQUEST['show_missing'])) { ?>
<COLGROUP width="200"></COLGROUP>
<?php } ?>
<?php if ($priv_critical == 1) { ?>
<COLGROUP width="100"></COLGROUP>
<?php } ?>
<TH align="left"><FONT COLOR="red">Num</FONT></TH>
<TH align="left"><FONT COLOR="red">Release</FONT></TH>
<TH align="left"><FONT COLOR="red">Site&nbsp;Type</FONT></TH>
<TH align="center"><FONT COLOR="red">From&nbsp;date</FONT></TH>
<TH align="center"><FONT COLOR="red">To&nbsp;date</FONT></TH>
<TH align="center"><FONT COLOR="red">Set&nbsp;by</FONT></TH>
<TH align="center"><FONT COLOR="red">Date</FONT></TH>
<TH align="left"><FONT COLOR="red">Description</FONT></TH>
<TH align="left"><FONT COLOR="red">Completion</FONT></TH>
<?php if (isset($_REQUEST['show_missing'])) { ?>
<TH align="left"><FONT COLOR="red">Missing resources</FONT></TH>
<?php } ?>
<?php if ($priv_critical == 1) { ?>
<TH align="center">Remove</TH>
<?php } ?>
<TBODY>
<?php
    while ($row = mysqli_fetch_array($result)) {
      if (array_key_exists($row[1],$all_rels)) { $installed = $all_rels[$row[1]]; } else { $installed = 0; }
      $compl_perc = 100 * $installed/$num_sites;
      $color_code = intval($compl_perc/20);
      if (isset($row[3])) { $fromdate = $row[3]; } else { $fromdate = "-"; }
      if (isset($row[4])) { $todate   = $row[4]; } else { $todate   = "-"; }
      echo '<TR class="'.$colors[$color_code].'"><TD>'.($indx+1).'</TD>';
      if (count($get_opts) > 0) { $get_options = implode($get_opts,"&")."&"; } else { $get_options = ""; }
      echo '<TD><A HREF="../list.php?'.$get_options.'rel='.$row[1].'">'.$rel[$row[0]].'</A></TD>';
      echo '<TD>'.$row[2].'</TD>';
      echo '<TD>'.$fromdate.'</TD>';
      echo '<TD>'.$todate.'</TD>';
      echo '<TD><A HREF="mailto:'.$row[6].'">'.$row[5].'</A></TD>';
      echo '<TD>'.$row[7].'</TD>';
      echo '<TD>'.$row[8].'</TD>';
      echo '<TD>'.$installed."/".$num_sites.' ['.sprintf("%2.1f",$compl_perc).'%] </TD>';
      if (isset($_REQUEST['show_missing'])) {
        echo '<TD>'.implode("<BR>",$missing_resources[$row[1]]).'</TD>';
      }
      if ($priv_critical == 1) {
        echo '<TD><CENTER>';
        echo '<input type=checkbox name=cflags[] value="'.$row[0].'">';
        echo '</CENTER></TD>';
      }
      echo '</TR>';
      $indx += 1;
    }
    echo '</TABLE>';
?>

</TD></TR></TABLE>
<?php if ($priv_critical == 1) { ?>
<input type="submit" name="remove" value="Remove">
</form>
<?php
    }
    if (!isset($_REQUEST["nostatus"]) && !is_null($tier_level)) {
      echo "<h4>Site view (ATLAS Tiers)</h4>\n";
      echo "<h5>Left click to zoom in, right click to zoom out</h5>\n";
      echo "<div id='criticalchart_div' style='width: 900px; height: 500px;'></div>\n";
    }
  }
?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
