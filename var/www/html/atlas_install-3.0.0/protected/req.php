<?php
 if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
<?php require("config.php"); atlas_app_log('req_enter',['method'=>(string)($_SERVER['REQUEST_METHOD']??'GET')]); ?>
<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation Requests</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  if (form.reqstat.value=='') {
    alert ('Please select a status to update the record');
    retval=false;
  }
  return retval;
}
//-->
</script>
</HEAD>
<BODY>
<P>
<?php } ?>
<?php
  require("db.php");
  require("combo.php");
  require("config.php");
  require("user_info.php");
  require (__DIR__."/../access_log.php");
?>
<?php if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
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
        <h3><?php echo $LJSFi_VO; ?> Software Installation Request list</h3>

<TABLE border="2" frame="hsides" rules="groups" summary="<?php echo $LJSFi_VO; ?> software deployment status." class="table3">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<THEAD valign="top">
<TR>
<TH>Request ID
<TH>Type
<TH>Release
<TH>Resource name
<TH>Status
<TH>Request Time
<TH>Update Time
<TH>Requested by
<TH>Requester comments
<TH>Operator comments
<TH>Assigned to
<TBODY>
<?php } ?>
<?php
  $COLORS = array (1  => 'reqNotAssigned'
                 , 2  => 'reqAccepted'
                 , 3  => 'reqRejected'
                 , 4  => 'reqCanceled'
                 , 5  => 'reqStopped'
                 , 6  => 'reqDone'
                 , 7  => 'reqAutorun'
                 , 8  => 'reqAutoabort'
                 , 9  => 'reqAborting'
                 , 10 => 'reqIgnore'
                  );

  $sslusername = (string)getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = (string)getenv("SSL_CLIENT_S_DN");
  $identity = atlas_current_identity();
  $adminfk = NULL;
  $role = '';
  $priv_update = 0;
  // Reuse a legacy user row when it exists, but never mutate the legacy user
  // table merely because req.php was opened with GET.
  if ($sslusername !== '' && $ssluserdetails !== '') {
    $qryuser = "SELECT user.ref, role.description, user.priv_update FROM user,role WHERE name=" . db_quote($sslusername,'ro') . " AND dn=" . db_quote($ssluserdetails,'ro') . " AND ABS(user.rolefk)=role.ref";
    $result = db_query($qryuser,"ro");
    $legacyRow = mysqli_fetch_row($result);
    if ($legacyRow) {
      $adminfk = $legacyRow[0];
      $role = (string)$legacyRow[1];
      $priv_update = (int)$legacyRow[2];
    }
  }
  if (($identity['source'] ?? '') === 'local') {
    $role = (string)($identity['role'] ?? '');
    if (in_array($role, array('admin','master'), true)) $priv_update = 1;
  }

  $reqstat = array();
  atlas_app_log('req_load_reference_data');
  $result = db_query("SELECT description FROM request_status","ro");
  while ( $row = mysqli_fetch_array($result) ) {
    array_push($reqstat,$row[0]);
  }

  $reqtypefk = array();
  $reqtype   = array();
  $result = db_query("SELECT ref, description FROM request_type","ro");
  while ( $row = mysqli_fetch_array($result) ) {
    array_push($reqtypefk,$row[0]);
    array_push($reqtype,$row[1]);
  }

  // Check if we need to update the status
  if (isset($_POST["id"]) && $_POST["id"] != "") {
    // A local account is authoritative for Web authorization. Create its legacy
    // user mapping lazily only when a state-changing legacy operation actually
    // needs an adminfk; never do this as a side effect of GET.
    if ($adminfk === NULL && ($identity['source'] ?? '') === 'local' && in_array($role, array('admin','master'), true)) {
      $legacyName = (string)($identity['username'] ?? '');
      $legacyDn = 'LOCAL:'.$legacyName;
      $legacyEmail = (string)($identity['email'] ?? '');
      if ($legacyName !== '') {
        $ins = "INSERT INTO user (name,dn,email,rolefk,enabled,valid_start,valid_end) VALUES ("
             .db_quote($legacyName,'rw').",".db_quote($legacyDn,'rw').",".db_quote($legacyEmail,'rw').","
             ."(SELECT ref FROM role WHERE description=".db_quote($role,'rw')." LIMIT 1),1,NOW(),DATE_ADD(NOW(), INTERVAL 20 YEAR))";
        db_query($ins);
        $resLegacy = db_query("SELECT ref FROM user WHERE name=".db_quote($legacyName,'ro')." AND dn=".db_quote($legacyDn,'ro')." ORDER BY ref DESC LIMIT 1",'ro');
        $mapRow = mysqli_fetch_row($resLegacy);
        if ($mapRow) $adminfk = (int)$mapRow[0];
      }
    }
    $rowdp = NULL;
    if ($_POST["reqstat"] == "autorun") {
      // Check for concurrent requests on the same exp soft area
      $query = "SELECT site.swarea, release_data.sw_physicalpath, release_data.sw_versionarea"
              . " FROM request, site, release_stat, release_data"
             . " WHERE site.ref=request.sitefk"
               . " AND release_stat.ref=request.relfk"
               . " AND release_stat.name=release_data.name"
               . " AND request.id=".db_quote($_POST["id"],'rw');
      $resrd = db_query($query,"ro");
      $rowrd = mysqli_fetch_row($resrd);
      $swarea = $rowrd[0];
      $sw_physicalpath = $rowrd[1];
      $sw_versionarea = $rowrd[2];
      if (isset($swarea) and $swarea != "" and $swarea != "cvmfs2") {
        $query = "SELECT id, request_date"
                . " FROM request, release_stat, release_data, site"
               . " WHERE release_stat.ref = request.relfk"
                 . " AND site.ref = release_stat.sitefk"
                 . " AND swarea='".$swarea."'"
                 . " AND release_stat.name = release_data.name"
                 . " AND sw_physicalpath='".$sw_physicalpath."'"
                 . " AND request.statusfk NOT IN (3,4,5,6,10)"
                 . " AND request.typefk NOT IN (3,7,8)"
                 . " AND request.id <> ".db_quote($_POST["id"],'rw');
        if (isset($sw_versionarea)) $query = $query . " AND sw_versionarea='" . $sw_versionarea . "'";
        $resdp = db_query($query,"ro");
        $rowdp = mysqli_fetch_row($resdp);
      }
    }

    if (!$row && !$rowdp) {
      // Current date/time
      $date = date("Y-m-d H:i:s");
      $result = db_query("SELECT ref FROM request_status WHERE description=" . db_quote($_POST["reqstat"],'ro'),'ro');
      $row = mysqli_fetch_row($result);
      $reqstatfk = $row[0];
      $updateqry = "UPDATE request SET statusfk=" . $reqstatfk;
      if ($_POST["reqstat"]=="not assigned") {
        $updateqry = $updateqry . ", adminfk=NULL";
      } else {
        $updateqry = $updateqry . ($adminfk === NULL ? ", adminfk=NULL" : ", adminfk=" . (int)$adminfk);
      }
      $updateqry = $updateqry . ", update_date='" . $date . "'";
      $updateqry .= ", admin_comments=" . db_quote($_POST["admincomm"],'rw');
      if (isset($_POST["reqtypefk"]) && $_POST["reqtypefk"] != "") $updateqry .= ", typefk=".db_int($_POST["reqtypefk"],1);
      if (isset($_POST["reqtype"]) && $_POST["reqtype"] != "") $updateqry .= ", typefk=(SELECT ref FROM request_type WHERE description=".db_quote($_POST["reqtype"],'rw').")";
      if (isset($_POST["forcerun"])) { $updateqry .= ", force_run=1"; } else { $updateqry .= ", force_run=0"; }
      $updateqry .= " WHERE id=" . db_quote($_POST["id"],'rw');
      $id = $_POST["id"];
      $_POST["id"] = "";
      db_query($updateqry);

      # Send the notification email
      $infoqry = "SELECT rt.field, rl.name, s.cename, s.name, r.admin_comments"
               . "  FROM request_type rt, request r, release_stat rl, site s"
               . " WHERE r.relfk=rl.ref"
               . "   AND r.sitefk=s.ref"
               . "   AND r.typefk=rt.ref"
               . "   AND r.id='".$id."'";
      $result = db_query($infoqry,"ro");
      $info=mysqli_fetch_row($result);
      $taskqry = "SELECT task.description, task.name"
               . "  FROM release_data, task"
               . " WHERE release_data.name='".$info[1]."'"
               . "   AND task.ref=release_data.".$info[0];
      $taskres = db_query($taskqry,"ro");
      $taskdata=mysqli_fetch_row($taskres);
      $subj='['.strtoupper($_POST["reqstat"]).'] '
           .$taskdata[1].' of '.$info[1].' at '.$info[2]
           .' ('.$info[3].')';
      $_SERVER['FULL_URL'] = 'http';
      if($_SERVER['HTTPS']=='on') {
        $_SERVER['FULL_URL'] .=  's';
      }
      $_SERVER['FULL_URL'] .=  '://';
      $hn = $_SERVER['SERVER_NAME'];
      if($_SERVER['SERVER_PORT']!='80' && $_SERVER['SERVER_PORT']!='443') {
        $_SERVER['FULL_URL'] .=  $hn.':'.$_SERVER['SERVER_PORT'].$_SERVER['DOCUMENT_ROOT'];
      } else {
        $_SERVER['FULL_URL'] .=  $hn.$_SERVER['SCRIPT_NAME'];
      }
      $link=dirname($_SERVER['FULL_URL']) . "/req.php?id=" . $id;
      $body='The status of request ID '.$id."\n"
           .'('.$taskdata[0].' of release '.$info[1]
           .' on CE '.$info[2].', site name '.$info[3].')'."\n"
           .'has been changed to "'.$_POST["reqstat"].'" '
           .'by "'.$sslusername.'".'."\n"
           .'Admin comments are "'.$info[4].'".'."\n"
           .'See '.$link.' for details.';
      $from = "From: LJSFi agent <".$LJSFi_email.">";
      $user_info = get_user_info("subscribers",NULL,NULL,True,$info[3]);
      $emails = array();
      foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
      $to = implode(",",$emails);
      if ($to != "") {
        mail($to, $subj, $body, $from);
        // Update the mail log
        $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                    . "(SELECT ref FROM mail_type WHERE name='immediate'),"
                    . "'" . $to . "',"
                    . count($emails) . ","
                    . "'" . date("Y-m-d H:i:s") . "')";
        $result = db_query($maillogqry);
      }

      // Insert a record in the log table
      $logqry = "INSERT INTO log (requestfk,action,description,userfk,comments,url,date) VALUES ("
               . "'" . $id . "',"
               . "'" . $_POST["reqstat"] . "',"
               . "'" . $taskdata[0] . " of release " . $info[1] . " on resource " . $info[2] . ", site name ". $info[3] . "',"
               . $adminfk.","
               . "'" . $info[4] . "',"
               . "'" . $link . "',"
               . "'" . date("Y-m-d H:i:s") . "')";
      $result = db_query($logqry);
      if (isset($HTTP_REFERER)) header("Location: $HTTP_REFERER");
    }
  }

  if (!isset($_POST['ws']) or $_POST['ws'] == '') {
  # Query header
  $q_hdr  = ("SELECT request.id
                    ,request_type.description
                    ,release_stat.name
                    ,site.cename
                    ,request_status.description
                    ,request.request_date
                    ,request.update_date
                    ,user.name
                    ,request.user_comments
                    ,request.admin_comments
                    ,request.statusfk
                    ,request.adminfk
                    ,user.email
                    ,site.cs
                    ,request.relfk
                    ,request.typefk");
  $q_body = (" FROM request,release_stat,site,user,request_type,request_status
              WHERE     request.sitefk=site.ref
                    AND request.userfk=user.ref
                    AND request.relfk=release_stat.ref
                    AND request.typefk=request_type.ref
                    AND request.statusfk=request_status.ref");
  if (isset($_GET['id']))     $q_body .= " AND request.id=" . db_quote($_GET['id'],'ro');
  if (isset($_GET['status'])) $q_body .= " AND request.statusfk=" . db_int($_GET['status']);
  if (isset($_GET['rel']))    $q_body .= " AND release_stat.name LIKE " . db_quote($_GET['rel'],'ro');
  if (isset($_GET['ce']))   $q_body .= " AND site.cename LIKE " . db_quote($_GET['ce'],'ro');
  if (isset($_GET['site']))   $q_body .= " AND site.name LIKE " . db_quote($_GET['site'],'ro');

  # Get the number of records
  $limit  = 20;
  $offset = 0;
  $query = ("SELECT count(*) " . $q_body);
  atlas_app_log('req_count_query');
  $result = db_query($query,"ro");
  $row = mysqli_fetch_row($result);
  $records = $row ? (int)$row[0] : 0;
  $maxpage = intval(($records-1)/$limit);
  $pagerange = 20;
  $page = 0;

  if (isset($_GET['step'])) $limit = db_int($_GET['step'],1,500);

  # Check the requested page
  if (isset($_GET['page'])) {
    $page = db_int($_GET['page'],1)-1;
    if ($page < 0) $page = 0;
    if ($page > $maxpage) $page = $maxpage;
    $offset = $page*$limit;
  }

  # Build the current GET string
  $getstr = "";
  foreach($_GET as $key=>$value) {
    if ($key != "page") {
      if ($getstr != "") $getstr = $getstr . "&";
      $getstr = $getstr . $key;
      if ($value != "") $getstr = $getstr . "=" . $value;
    }
  }

  # Fetch the given page
  $query  = ($q_hdr . $q_body);
  $query .= (" ORDER BY request.request_date DESC, site.cename");
  $query .= (" LIMIT " . db_int($offset,0) . "," . db_int($limit,1,500));
  // SQL query disclosure disabled
  atlas_app_log('req_data_query',['records'=>$records,'limit'=>$limit,'offset'=>$offset]);
  $result = db_query($query,"ro");
  while ( $row = mysqli_fetch_array($result) ) {
    if (   ($role == "admin" && ($row[11] == $adminfk || $row[11] == NULL)) || $role == "master"
        || (isset($priv_update) && $priv_update == 1)) {
      echo '<form method="post" name="' . $row[0] . '" action="" onsubmit="return checkform(this);">';
    }
    $rowColor=$COLORS[(int)$row[10]] ?? 'otherTask'; echo ("<TR class='" . $rowColor . "'>");
    for ($i=0; $i<10; $i++) {
      echo ("<TD>");
      if ($i == 0) {
        echo ('<A HREF="../jobs.php?relfk='.$row[14].'">'.$row[0].'</A>');
        #if (($role == "admin" && ($row[11] == $adminfk || $row[11] == NULL)) || $role == "master") {
          echo ("<input type='hidden' name='id' value='" . $row[0] . "'>");
          echo ("<input type='hidden' name='forcerun' value='1'>");
        #}
      } elseif ($i == 1) {
        if ($role == "master") {
          echo ("<select name='reqtypefk' size='1'>");
          for ($j=0; $j < count($reqtypefk); $j++) {
            echo ('<option value="'.$reqtypefk[$j].'" ');
            if ($row[15] == $reqtypefk[$j]) echo ('selected');
            echo ('>'.$reqtype[$j].'</option>');
          }
          echo '</select>';
        } else {
          echo ($row[$i]);
        }
      } elseif ($i == 4) {
        if (($role == "admin" && ($row[11] == $adminfk || $row[11] == NULL)) || $role == "master") {
          echo ("<select name='reqstat' size='1'>");
          combo_box($reqstat,"-- select one --",$row[$i]);
          echo '</select>';
        } else {
          echo ($row[$i]);
        }
      } elseif ($i == 9) {
        if (($role == "admin" && ($row[11] == $adminfk || $row[11] == NULL)) || $role == "master") {
          $val = htmlentities($row[9]);
          print "<input type='text' name='admincomm' value=\"" . $val . "\">";
        } else {
          echo ($row[$i]);
        }
      } else {
        if ($i == 3) echo ("<A HREF='site.php?cs=" . $row[13] . "'>");
        if ($i == 7) echo ("<A HREF='mailto:" . $row[12] . "'>");
        echo ($row[$i]);
        if ($i == 3 || $i == 7) echo ("</A>");
      }
      echo ("</TD>");
    }
    if ($row[11] != "") {
      $resqry = "SELECT user.name,user.email FROM user WHERE ref=" . $row[11];
      $resadm = db_query($resqry,"ro");
      $rowadm = mysqli_fetch_row($resadm);
      echo ("<TD>");
      if($rowadm) echo ("<A HREF='mailto:" . atlas_h((string)$rowadm[1]) . "'>" . atlas_h((string)$rowadm[0]) . "</A>"); else echo '-'; echo '</TD>';
    } else {
      echo ("<TD>-</TD>");
    }
    if (($role == "admin" && ($row[11] == $adminfk || $row[11] == NULL)) || $role == "master") {
      echo '<TD><input type="submit" value="Update"></TD></form>';
    } else {
      if (isset($priv_update) && $priv_update == 1) {
        echo '<TD>';
        echo '<input type="hidden" name="reqstat" value="autorun">';
        echo '<input type="hidden" name="admincomm" value="Restarting the task">';
        echo '<input type="submit" value="Restart">';
        echo '</TD></form>';
      }
    }
    echo ("</TR>");
  }
  echo ("</TABLE><P>");

  # Show a message for non-privileged users
  if (isset($priv_update) && $priv_update != 1) {
    echo '<FONT COLOR=RED><B>';
    echo 'You don\'t have permissions to restart the tasks. ';
    echo 'Please go <A HREF="user.php">here</A> and ask for the restart privilege if you need it.';
    echo '</B></FONT><BR>';
  }

  # Show the legend for moving around the pages
  $min = $page-$pagerange;
  if ($min < 1) $min = 1;
  $max = $page+$pagerange;
  if ($max > ($maxpage+1)) $max = $maxpage+1;
  $pagestr = "<STRONG>Page number <";
  for ($i=$min; $i<=$max; $i++) {
     if ($i > $min) $pagestr =  ($pagestr . " ");
     if ($i == $page+1) {
       $pagestr =  ($pagestr . $i);
     } else {
       $pagestr =  ($pagestr . " <A HREF='?");
       if ($getstr != "") $pagestr = ($pagestr . $getstr . "&");
       $pagestr =  ($pagestr . "page=" . $i . "'>" . $i . "</A>");
     }
  }
  $pagestr = $pagestr . "></STRONG>";
  echo ('<TABLE border="0" frame="hsides" rules="groups"');
  echo ('<TR>' . $pagestr . '</TR></TABLE>');
  echo '<P>';
  echo( date("l, F dS Y, H:m:s") );
  if (isset($rowdp) && isset($rowdp[0])) { echo "<P><FONT SIZE=+1 COLOR=RED><B>A <A HREF='req.php?id=".$rowdp[0]."'>concurrent installation</A> is still active, cannot change the parameters</B></FONT>"; }
  }
?>
<?php if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
<?php } ?>
