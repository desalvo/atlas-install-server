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
                     . "   SET critical=0, critical_userfk=".$userfk.", critical_date='".$date."'"
                     . " WHERE ref=".$val;
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
<?php if ($priv_critical == 1) { ?>
<TH align="center">Remove</TH>
<?php } ?>
<TBODY>
<?php
    // Current criticality flags
    //$colors = array('#DFFFDF','#C2C2C2');
    $colors = array('whitetable','graytable');
    $query = "SELECT rd.ref, rd.name, st.description"
           .      ", DATE_FORMAT(rd.critical_validity_from,'%Y-%m-%d')"
           .      ", DATE_FORMAT(rd.critical_validity_to,'%Y-%m-%d')"
           .      ", u.name, u.email, rd.critical_date"
           .      ", rd.critical_validity_to, u.name, u.email, rd.critical_date"
           .      ", rd.critical_description"
           . "  FROM release_data rd, user u, site_type st"
           . " WHERE rd.critical_userfk = u.ref"
           .   " AND rd.critical = 1"
           .   " AND rd.critical_site_typefk = st.ref"
           . " ORDER BY rd.critical_date DESC";
    $result = db_query($query);
    $indx   = 0;
    while ($row = mysqli_fetch_array($result)) {
      if (isset($row[3])) { $fromdate = $row[3]; } else { $fromdate = "-"; }
      if (isset($row[4])) { $todate   = $row[4]; } else { $todate   = "-"; }
      echo '<TR class="'.$colors[$indx%2].'"><TD>'.($indx+1).'</TD>';
      echo '<TD><A HREF="../list.php?rel='.$row[1].'">'.$rel[$row[0]].'</A></TD>';
      echo '<TD>'.$row[2].'</TD>';
      echo '<TD>'.$fromdate.'</TD>';
      echo '<TD>'.$todate.'</TD>';
      echo '<TD><A HREF="mailto:'.$row[6].'">'.$row[5].'</A></TD>';
      echo '<TD>'.$row[7].'</TD>';
      echo '<TD>'.$row[8].'</TD>';
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
    if (!isset($_REQUEST["nostatus"])) {
      echo "<h4>Site view (ATLAS Tiers)</h4>\n";
      $colors = array();
      $colors[0] = "white";
      for ($i=1; $i<=50; $i++) $colors[$i] = "red";
      for ($i=51; $i<=70; $i++) $colors[$i] = "orange";
      for ($i=71; $i<=90; $i++) $colors[$i] = "yellow";
      for ($i=91; $i<=99; $i++) $colors[$i] = "palegreen";
      $colors[100] = "lime";
      $num_critical = array();
      $rel_critical = array();
      $res   = db_query("SELECT site.name, COUNT(DISTINCT release_data.ref), GROUP_CONCAT(DISTINCT release_data.ref ORDER BY release_data.ref) FROM site, release_data WHERE release_data.critical = 1 AND (release_data.critical_site_typefk in (SELECT DISTINCT site_type.ref FROM site_type, site_attr, site_type_attr WHERE site_type_attr.attr=site_attr.attr AND site_type.ref=site_type_attr.type AND site.attr&site_attr.attr=site_attr.attr) OR release_data.critical_site_typefk = 1) AND site.name <> '' AND site.attr > 0 GROUP BY site.name");
      while ($row = mysqli_fetch_array($res)) {
        $num_critical[$row[0]] = $row[1];
        $rel_critical[$row[0]] = explode(',',$row[2]);
      }
      $query_fields = "site.name, COUNT(DISTINCT release_data.name), GROUP_CONCAT(DISTINCT release_data.ref ORDER BY release_data.ref)";
      $query_filters = array();
      array_push($query_filters, "site.name <> ''");
      array_push($query_filters, "site.attr > 0");
      array_push($query_filters, "release_data.name = release_stat.name");
      array_push($query_filters, "release_data.critical = 1");
      array_push($query_filters, "release_stat.sitefk = site.ref");
      array_push($query_filters, "release_stat.status = 'installed'");
      array_push($query_filters, "site.last_activity >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
      $query  = "SELECT ".$query_fields." FROM site, release_data, release_stat ";
      $query .= "WHERE ".join(" AND ",$query_filters);
      $query .= " GROUP BY site.name ORDER BY site.name";
      // SQL query disclosure disabled
      $res   = db_query($query);
      echo "<TABLE id='critical_status_tbl' border='1' rules='groups' summary='Site Status'>\n";
      echo "<COLGROUP width='300'></COLGROUP>\n";
      echo "<COLGROUP width='300'></COLGROUP>\n";
      echo "<COLGROUP width='300'></COLGROUP>\n";
      $cols = 0;
      while ($row = mysqli_fetch_array($res)) {
        $rel_missing = array_diff($rel_critical[$row[0]],explode(',',$row[2]));
        $val = intval(100*$row[1]/$num_critical[$row[0]]);
        if ($val > 100) { $val = 100; }
        if ($cols % 3 == 0) { echo "<TR HEIGHT=30>"; }
        print "<TD BGCOLOR='".$colors[$val]."' ALIGN='CENTER'><A HREF='../list.php?critical=1&sitename=".$row[0]."' TARGET='_blank'";
        print ' onmouseover="Tip(\'';
        foreach ($rel_missing as $relref) {
          print '<A HREF=../list.php?rel='.$relref.' TARGET=_blank>'.$rel[$relref].'</A><BR>';
        }
        print '\', WIDTH, 400, TITLE, \'Missing releases\', SHADOW, true, FADEIN, 300, FADEOUT';
        print ', 300, STICKY, 1, CLOSEBTN, true, CLICKCLOSE, true)" onmouseout="UnTip()">';
        print $row[0].": ".$row[1]."/".$num_critical[$row[0]]."</A></TD>\n";
        if (($cols+1) % 3 == 0) { echo "</TR>"; }
        $cols += 1;
      }
      if ($cols % 3 != 0) { echo "</TR>"; }
      echo "</TABLE>\n";
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
