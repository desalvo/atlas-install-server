<HTML>
<HEAD>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = rai_tbl.getElementsByTagName("tr");   
  for(i = 0; i < rows.length; i++){
    rows[i].style.backgroundColor='#AAFFAA';
  }
  if (form.bdii.value=='') {
    bdii_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.cename.value=='') {
    cename_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.cs.value=='') {
    cs_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.rel.value=='') {
    rel_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.rel.value=='other') {
    if (form.relnew.value=='') {
      relnew_tr.style.backgroundColor='#AAAAAA';
      retval=false;
    }
  }
  if (form.user.value=='') {
    user_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.email.value=='') {
    email_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.reqtype.value=='') {
    reqtype_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (!retval) {
    alert('Please fill all the highlighted fields');
  }

  if (retval) {
    if (form.reltype.value=='production' && form.reqtype.value=='removal') {
      answ = confirm('WARNING: You are requesting the removal of a production release.\n Do you really want to continue?');
      if (!answ) {
        retval=false
      }
    }
    if (form.pin.value=='pinned' && (form.reqtype.value=='removal' || form.reqtype.value=='cleanup')) {
      alert('You cannot remove a pinned release');
      retval=false;
    }
    if (form.requiredby.value!='') {
      if (form.reqtype.value=='removal') {
        alert('Release '+form.rel.value+' is required by '+form.requiredby.value+'. Please remove the dependencies first.');
        retval=false;
      } else if (form.reqtype.value=='cleanup') {
        answ = confirm('WARNING: Removing this release will break dependencies.\n Do you really want to continue?');
        if (!answ) {
          retval=false;
        }
      }
    }
    if (form.requirementsok.value=='0') {
      alert('The selected software requires release '+form.requires.value+' which is not available in the selected site. Please install '+form.requires.value+' first');
      retval=false
    }
    if (form.pin.value=='not available' && form.reqtype.value=='removal') {
      alert('This release is not available in the selected site\nor it\'s already removed.');
      retval=false
    }
  }
  return retval;
}

function pageReload() {
  rai.action='rai.php';
  rai.submit();
}

function checkRel() {
  if (rai.rel.value=='other') {
    relnew_tr.style.display='table-row';
  } else {
    relnew_tr.style.display='none';
    rai.relnew.value='';
    rai.action='rai.php';
    rai.submit();
  }
}

//-->
</script>

<?php

  require("guid.php");
  require("db.php");
  require("config.php");
  require("combo.php");
  require("site-info.php");
  require("user_info.php");
  require ("$LJSFi_PATH/access_log.php");
?>
<TITLE>Request An Install for the <?php echo $LJSFi_VO; ?> software</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
</HEAD>

<BODY>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/rai_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> software Installation/Maintenance request</h3>

<?php
  function get_facility($bdii) {
    global $LJSFi_default_infosys,$LJSFi_VO;
    if (!isset($bdii)) { $bdii = $LJSFi_default_infosys; }
    $res = db_query("SELECT f.name FROM bdii b, facility f WHERE b.hostname='".$bdii."' AND b.facilityfk = f.ref","ro");
    $row = mysqli_fetch_array($res);
    if ($row) {
      return $row[0];
    }
    return NULL;
  }

  function get_ce($bdii) {
    global $LJSFi_default_infosys,$LJSFi_VO;
    if (!isset($bdii)) { $bdii = $LJSFi_default_infosys; }
    $ce = array();
    $res = db_query("SELECT b.ref, b.port, f.name FROM bdii b, facility f WHERE b.hostname='".$bdii."' AND b.facilityfk = f.ref","ro");
    $row = mysqli_fetch_array($res);
    if ($row) {
      $bdiiport = $row[1];
      $facility = strtoupper($row[2]);
    } else {
      $bdiiport = 2170;
      $facility = "WMS";
    }
    if ($facility == "PANDA") {
      $res = db_query("SELECT DISTINCT(name) FROM panda_resource p",$bdii.":".$bdiiport);
      while ($row = mysqli_fetch_array($res)) { array_push($ce, $row[0]); }
    } else {
      $ds=ldap_connect($bdii,$bdiiport);
      $r=ldap_bind($ds);
      $filter='(&(objectclass=gluece)(glueceaccesscontrolbaserule=*'.strtolower($LJSFi_VO).'*))';
      $items=array('glueceinfohostname');
      $sr=ldap_search($ds, "o=grid", $filter, $items);
      $info = ldap_get_entries($ds, $sr);
      for ($i=0; $i<$info["count"]; $i++) {
        for ($k=0; $k<count($info[$i]["glueceinfohostname"]); $k++) {
          if (isset($info[$i]["glueceinfohostname"][$k])) $glueceinfohostname = $info[$i]["glueceinfohostname"][$k];
          if ($glueceinfohostname != "") array_push($ce, $glueceinfohostname);
        }
      }
      ldap_close($ds);
    }
    $cename = array_unique($ce);
    sort($cename);
    return $cename;
  }

  function update_cs($ce,$bdii) {
    global $LJSFi_default_infosys,$LJSFi_VO;
    if (!isset($bdii)) { $bdii = $LJSFi_default_infosys; }
    $cs = array();
    $res = db_query("SELECT b.ref, b.port, f.name FROM bdii b, facility f WHERE b.hostname='".$bdii."' AND b.facilityfk = f.ref","ro");
    $row = mysqli_fetch_array($res);
    if ($row) {
      $bdiiport = $row[1];
      $facility = $row[2];
    } else {
      $bdiiport = 2170;
      $facility = "WMS";
    }
    $ds=ldap_connect($bdii,$bdiiport);
    $r=ldap_bind($ds);
    $filter='(&(objectclass=gluece)(glueceaccesscontrolbaserule=*'.strtolower($LJSFi_VO).'*)(glueceinfohostname=' . $ce . '))';
    $items=array('glueceuniqueid');
    $sr=ldap_search($ds, "o=grid", $filter, $items);
    $info = ldap_get_entries($ds, $sr);
    for ($i=0; $i<$info["count"]; $i++) {
      for ($k=0; $k<count($info[$i]["glueceuniqueid"]); $k++) {
        $glueceuniqueid = $info[$i]["glueceuniqueid"][$k];
        if ($glueceuniqueid != "") array_push($cs, $glueceuniqueid);
      }
    }
    ldap_close($ds);
    return $cs;
  }
?>

<?php
  // Defaults
  if (!isset($_POST['hideobsolete'])) { $_POST['hideobsolete'] = 'no'; }

  // User info
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  if (!isset($_POST["user"])) $_POST["user"] = $sslusername;
  $rolefk=0;
  $user_info = get_user_info();
  if (count($user_info) > 0) {
    $enabled=$user_info[0]["enabled"];
    $_POST["email"]=$user_info[0]["email"];
    $rolefk = $user_info[0]["rolefk"];
    $priv_insert = $user_info[0]["priv_insert"];
  }

  if (!isset($priv_insert)) {
    echo ("<FONT COLOR='RED' SIZE=+1><B>Unknown user. Please <A HREF='user.php'>register to LJSFi</A></B></FONT>\n");
  } elseif ($enabled == 0) {
    echo ("<FONT COLOR='RED' SIZE=+1><B>Your user is disabled. Please contact the LJSFi admin.</B></FONT>\n");
  } elseif ($priv_insert != 1) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>");
    echo ("You don't have permissions to insert requests.<BR>\n");
    echo ("Please check your registration and <A HREF='user.php'>ask for the insert privilege</A>\n");
    echo ("</B></FONT>");
  } else {
    // Start the form
    echo '<form method="post" name="rai" action="subreq.php" onsubmit="return checkform(this);">';
    echo '<TABLE id="rai_tbl" border="1" rules="groups" summary="Request An Install interface">';
    echo '<P>';
    echo '</FONT></CENTER></EM></CAPTION>';
    echo '<COLGROUP width="130" span=2></COLGROUP>';

    // BDII/Infosys combo box
    $res = db_query("SELECT b.ref, b.hostname, b.port, b.preferred, f.name FROM bdii b, facility f WHERE b.enabled=1 AND b.facilityfk = f.ref","ro");
    $bdii_list = array();
    $bdii_facility = array();
    $bdii_pref = array();
    while ($row = mysqli_fetch_array($res)) {
      if ($row[3] == 1) array_push($bdii_pref, $row[1]);
      array_push($bdii_list, $row[1]);
      $bdii_facility[$row[1]] = $row[4];
    }
    if (count($bdii_pref) == 0) array_push($bdii_pref, $bdii_list[1]);
    sort($bdii_pref);
    if (!isset($_POST["bdii"])) $_POST["bdii"] = $bdii_pref[0];
    echo ("<TR id='bdii_tr' class='greentable'><TD><EM>InfoSys</EM></TD><TD><select name='bdii' size='1' onchange=\"pageReload();\">");
    combo_box($bdii_list,"-- select one --",$_POST["bdii"]);
    echo '</select></TD>';

    if (strtoupper($bdii_facility[$_POST["bdii"]]) == "PANDA") {
      // Panda Resource combo box
      echo ("<TR id='cename_tr' class='greentable'><TD><EM>Panda&nbsp;Resource</EM></TD><TD>");
      echo ("<select name='cename' size='1' onchange=\"pageReload();\">");
      $cename = get_ce($_POST["bdii"]);
      if (!isset($_POST["cename"])) $_POST["cename"] = "-";
      $has_selection = combo_box($cename,"-- select one --",$_POST["cename"]);
      echo '</select></TD>';
      $resinfo = array();
      if (isset($_POST["cename"]) && $_POST["cename"] != "" && $has_selection)
        $resinfo = get_resource_info($_POST["cename"],$_POST["bdii"]);
      if ($has_selection && isset($resinfo[0])) {
        echo ("<TR class='greentable'><TD><EM>Site Name</EM></TD><TD>" . $resinfo[0] . "</TD>\n");
        echo ("<input type='hidden' name='cs' value='" . $_POST["cename"] . "'>\n");
        echo ("<input type='hidden' name='sitename' value='" . $resinfo[0] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>OS type</EM></TD><TD>" . $resinfo[1] . "</TD>\n");
        echo ("<input type='hidden' name='osname' value='" . $resinfo[1] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>OS version</EM></TD><TD>" . $resinfo[2] . "</TD>\n");
        echo ("<input type='hidden' name='osver' value='" . $resinfo[2] . "'>");
        echo ("<TR class='greentable'><TD><EM>OS release</EM></TD><TD>" . $resinfo[3] . "</TD>\n");
        echo ("<input type='hidden' name='osrel' value='" . $resinfo[3] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>Site Type</EM></TD><TD>" . $resinfo[4] . "</TD>\n");
        echo ("<input type='hidden' name='sitetype' value='" . $resinfo[4] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>Grid name</EM></TD><TD>" . $resinfo[5] . "</TD>\n");
        echo ("<input type='hidden' name='gridname' value='" . $resinfo[5] . "'>\n");
        $facility = get_facility($_POST["bdii"]);
        echo ("<TR class='greentable'><TD><EM>Facility</EM></TD><TD>" . $facility . "</TD>\n");
        echo ("<input type='hidden' name='facility' value='" . $facility . "'>\n");
      }
    } else {
      // CE FQDN combo box
      echo ("<TR id='cename_tr' class='greentable'><TD><EM>CE FQDN</EM></TD><TD>");
      echo ("<select name='cename' size='1' onchange=\"pageReload();\">");
      $cename = get_ce($_POST["bdii"]);
      if (!isset($_POST["cename"])) $_POST["cename"] = "-";
      combo_box($cename,"-- select one --",$_POST["cename"]);
      echo '</select></TD>';

      // Resource (CS) combo box
      $cs = update_cs($_POST["cename"],$_POST["bdii"]);
      echo ("<TR id='cs_tr' class='greentable'><TD><EM>Resource</EM></TD><TD>");
      echo ("<select name='cs' size='1' onchange=\"pageReload();\">");
      if (!isset($_POST["cs"])) $_POST["cs"] = "-";
      $has_selection = combo_box($cs,"-- select one --",$_POST["cs"]);
      echo '</select></TD>';
      $siteinfo = array();
      if (isset($_POST["cs"]) && $_POST["cs"] != "" && $has_selection)
        $siteinfo = get_cluster_info($_POST["cs"],$_POST["bdii"]);
      if ($has_selection && isset($siteinfo[0])) {
        echo ("<TR class='greentable'><TD><EM>Site Name</EM></TD><TD>" . $siteinfo[0] . "</TD>\n");
        echo ("<input type='hidden' name='sitename' value='" . $siteinfo[0] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>OS type</EM></TD><TD>" . $siteinfo[1] . "</TD>\n");
        echo ("<input type='hidden' name='osname' value='" . $siteinfo[1] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>OS version</EM></TD><TD>" . $siteinfo[2] . "</TD>\n");
        echo ("<input type='hidden' name='osver' value='" . $siteinfo[2] . "'>");
        echo ("<TR class='greentable'><TD><EM>OS release</EM></TD><TD>" . $siteinfo[3] . "</TD>\n");
        echo ("<input type='hidden' name='osrel' value='" . $siteinfo[3] . "'>\n");
        echo ("<TR class='greentable'><TD><EM>Grid name</EM></TD><TD>" . $siteinfo[4] . "</TD>\n");
        echo ("<input type='hidden' name='gridname' value='" . $siteinfo[4] . "'>\n");
      }
    }

    // Release combo box
    $obsolete=array('production','obsolete');
    echo ("<TR id='rel_tr' class='greentable'><TD><EM>Release</EM></TD><TD>");
    $qry = "SELECT rd.name,rt.description, rd.obsolete, ra.platform_type"
                 .",ra.os_type,ra.gcc_ver, ra.mode as opt "
                 .",rd.sw_name as sw_name "
            ."FROM release_data rd, release_type rt, release_arch ra "
           ."WHERE rd.typefk = rt.ref AND rd.archfk = ra.ref AND rd.name != 'ALL' AND rd.tag != 'TEST'";
    if ($rolefk < 3) $qry .= " AND rt.ref > 2";
    if ($_POST['hideobsolete'] == 'yes') { $qry .= " AND rd.obsolete <> 1"; }
    $qry .= " ORDER BY rd.name ASC";
    $res = db_query($qry,"ro");
    $list     = array();
    $listdesc = array();
    $reltype  = "";
    while ($row = mysqli_fetch_array($res)) {
      array_push($list, $row[0]);
      array_push($listdesc, $row[0].' ('.basename($row[7]).', '.$row[3].', '.$row[4].', gcc '.$row[5].', '.$row[6].', '.$row[1].', '.$obsolete[$row[2]].')');
      if (isset($_POST["rel"]) && $row[0] == $_POST["rel"]) { $reltype = $obsolete[$row[2]]; }
    }
    echo ("<select name='rel' size='1' onchange=\"checkRel();\">");
    if (!isset($_POST["rel"])) $_POST["rel"] = "-";
    combo_box($list,"-- select one --",$_POST["rel"],$listdesc);
    echo '</select>';
    echo '<BR><input type="checkbox" name="hideobsolete" size=80 value="yes" onchange="pageReload();"';
    if ($_POST['hideobsolete'] == 'yes') { echo ' checked'; }
    echo '>Hide obsolete releases';
    echo '</TD>';
    $pinstatus = array('free','pinned','not available');
    $requirements = array('not available','ok');
    $pin = 2;
    $requires = "";
    $requiredby = "";
    if (   isset($_POST["cename"]) && $_POST["cename"] != ""
        && isset($_POST["rel"])    && $_POST["rel"] != "") {
      // Process the pins
      $qry = "SELECT release_stat.pin "
              ."FROM release_stat, site "
             ."WHERE release_stat.sitefk = site.ref "
               ."AND site.cename = ".db_quote($_POST["cename"],'ro')." "
               ."AND release_stat.name=".db_quote($_POST["rel"],'ro')
               ."AND release_stat.status<>'removed'";
      $res = db_query($qry,"ro");
      $row = mysqli_fetch_row($res);
      if ($row) $pin = $row[0];
      // Process the requirements
      $requirementsok = 1;   // By default the requirements are satisfied
      $qry = "SELECT rd.requires FROM release_data rd WHERE rd.name=".db_quote($_POST["rel"],'ro')." LIMIT 1";
      $res = db_query($qry,"ro");
      while ($row = mysqli_fetch_array($res)) {
        if (isset($row[0]) && $row[0] != "") {
          $requires_array=explode(",",$row[0]);
          if (count($requires_array)<2) {
            $requires = $row[0];
          } else {
            $requires = $requires_array[1];
          }
          $requirementsok = 0;
          if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
            $qryreq = "SELECT release_stat.status FROM release_stat, site"
                    . " WHERE release_stat.name = '".$requires."'"
                    .   " AND release_stat.sitefk = site.ref"
                    .   " AND site.name = ".db_quote($_POST["sitename"],'ro');
            $resreq = db_query($qryreq,"ro");
            while ($row = mysqli_fetch_array($resreq)) {
              if (isset($row[0]) && strtolower($row[0]) == "installed") $requirementsok = 1;
            }
          }
        }
      }
      $qry = "SELECT rd.name FROM release_data rd WHERE rd.requires LIKE ".db_quote('%'.$_POST["rel"],'ro');
      $res = db_query($qry,"ro");
      while ($row = mysqli_fetch_array($res)) {
        if (isset($row[0]) && $row[0] != "") {
          $relnum=$row[0];
          if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
            $qryreq = "SELECT DISTINCT(release_stat.status) FROM release_stat, site"
                    . " WHERE release_stat.name = '".$relnum."'"
                    .   " AND release_stat.sitefk = site.ref"
                    .   " AND (site.name = ".db_quote($_POST["sitename"],'ro');
            if (isset($_POST["cs"]) && $_POST["cs"] != "") {
              $qryreq .= " OR site.cs = '".$_POST["cs"]."'";
            }
            $qryreq .= ")";
            $resreq = db_query($qryreq,"ro");
            while ($row = mysqli_fetch_array($resreq)) {
              if (isset($row[0]) && strtolower($row[0]) != "removed" && strtolower($row[0]) != "aborted") {
                if ($requiredby != "") $requiredby .= ", ";
                $requiredby .= $relnum;
              }
            }
          }
        }
      }
    }
    echo ("<TR class='greentable'><TD><EM>Release Status</EM></TD><TD>" . $pinstatus[$pin] . "</TD>\n");
    echo ("<input type='hidden' name='pin' value='" . $pinstatus[$pin] . "'>\n");
    echo ("<input type='hidden' name='requires' value='" . $requires . "'>\n");
    echo ("<input type='hidden' name='requiredby' value='" . $requiredby . "'>\n");
    echo ("<input type='hidden' name='interactive' value='1'>\n");
    echo ("<input type='hidden' name='forcerun' value='1'>\n");
    if ($requires != "") {
      echo ("<TR class='greentable'><TD><EM>Requires</EM></TD><TD>".$requires." (".$requirements[$requirementsok].")</TD>\n");
    }
    if ($requiredby != "") {
      echo ("<TR class='greentable'><TD><EM>Required by</EM></TD><TD>".$requiredby."</TD>\n");
    }
    echo ("<input type='hidden' name='requirementsok' value='" . $requirementsok . "'>\n");
    if ($reltype != '') {
      echo ("<TR class='greentable'><TD><EM>Release Type</EM></TD><TD>" . $reltype . "</TD>\n");
      echo ("<input type='hidden' name='reltype' value='" . $reltype . "'>\n");
    }
    echo '<TR id=\'relnew_tr\' style="display:none" class="greentable"><TD><EM>Requested release</EM></TD>';
    echo '<TD><input type="text" name="relnew" value="' . $_POST["relnew"] . '"></TD>';
    echo '<br/>';
  
    // Request type combo box
    echo ("<TR id='reqtype_tr' class='greentable'><TD><EM>Request type</EM></TD><TD>");
    $res = db_query("SELECT description,comment,level FROM request_type","ro");
    $list = array();
    $listdesc = array();
    while ($row = mysqli_fetch_array($res)) {
      if ($rolefk>=$row[2]) {
        array_push($list, $row[0]);
        array_push($listdesc, $row[0].' ('.$row[1].')');
      }
    }
    echo ("<select name='reqtype' size='1'>");
    combo_box($list,"-- select one --",$_POST["reqtype"],$listdesc);
    echo '</select></TD>';
  
    // Username
    echo '<TR id=\'user_tr\' class="greentable"><TD><EM>Your name</EM></TD><TD><input type="text" name="user" size=60 value="';
    echo $_POST["user"] . '"></TD>';
  
    // Email
    echo '<TR id=\'email_tr\' class="greentable"><TD><EM>Your e-mail</EM></TD><TD><input type="text" name="email" size=60 value="';
    echo $_POST["email"];
    echo '"></TD>';
  
    // Comments
    if (!$_POST["comments"]) $_POST["comments"]="No comments";
    echo '<TR class="greentable"><TD id=\'comm_tr\'><EM>Comments</EM></TD><TD><input type="text" name="comments" size=80 value="';
    echo $_POST["comments"];
    echo '"></TD>';

    echo '<TR class="greentable"><TD id="ai_tr"><EM>Autoinstall</EM></TD><TD><input type="checkbox" name="autoinstall" size=80 value="yes" checked></TD>';
    echo '</TABLE>';
    echo '<input type="submit" value="Submit">';
    echo '<input type="reset" value="Reset">';
    echo '</form>';
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
