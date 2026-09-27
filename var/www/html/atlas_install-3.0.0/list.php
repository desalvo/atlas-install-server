<HTML>
<HEAD>
<?php
  require ("db.php");
  require ("config.php");
?>
<TITLE><?php echo $LJSFi_VO; ?> Installation DB viewer</TITLE>
<?php require("./css/page_header.php"); page_header("."); ?>
<link rel="stylesheet" href="css/tooltipster.css" />
<script type="text/javascript" src="js/jquery.tooltipster.min.js"></script>
<script>
    $(document).ready(function() {
        $('.tooltip').tooltipster({interactive: true});
    });
</script>
</HEAD>

<script type="text/javascript">
function openpop(url) {
    newWin = window.open(url,'Details','scrollbars=no,resizable=yes, width=300, height=300,status=no,location=no,toolbar=no');
}
function closeWin() {
    self.close();
}
function showHideElement(cb,id) {
    var elm = document.getElementById(id);
    elm.style.display = cb.checked? "inline":"none";
}

function enableDisableField(form,id) {
  var elm = document.getElementById(id);
  if (elm.disabled) elm.disabled=false; else elm.disabled=true;
}

var btnWhichButton;

function checkform(form) {
  var retval=true;
  if (btnWhichButton.value == 'delete') {
    answ = confirm('Do you really want to delete the selected records?');
    if (!answ) {
      retval=false
    }
  }
  return retval;
}

</script>

<BODY>

<script src="js/sorttable.js"></script>
<script type="text/javascript" src="js/selectall.js"></script>

<?php

  function printLJSFtags ($tags)
  {
    foreach ($_REQUEST as $key => $value) {
      if (in_array($key, $tags) && $key != "skip" and $value != "") {
        if (is_array($value)) {
          foreach ($value as $val) echo '<input type="hidden" name="'.$key.'[]" value="'.$val.'">';
        } else {
          echo '<input type="hidden" name="'.$key.'" value="'.$value.'">';
        }
        echo "\n";
      }
    }
  }

  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  if (!isset($ssluserdetails) || $ssluserdetails == "") $ssluserdetails = "guest";
  $result = db_query("SELECT user.ref, role.description, role.ref FROM user,role WHERE name=" . db_quote($sslusername,'ro') . " AND dn=" . db_quote($ssluserdetails,'ro') . " AND user.rolefk=role.ref","ro");
  $row = mysqli_fetch_row($result);
  if (!$row) {
    $role="";
  } else {
    $adminfk=$row[0];
    $role=$row[1];
    $roleid=$row[2];
  }

  $LJSFtags = array( "rel", "user", "sitename", "arch", "relarch", "cename", "resource", "status", "orderby", "orderdir");
  $lists    = array();
  if (isset($_REQUEST["summary"])) {
    $queries  = array(
                      ""
                     ,"SELECT DISTINCT(cs) FROM site ORDER BY cs"
                     ,"SELECT DISTINCT(fstype) FROM site ORDER BY fstype"
                     ,"SELECT DISTINCT(status) FROM release_stat ORDER BY status"
                     );
  } else {
    $queries  = array(
                      "SELECT DISTINCT(name) FROM release_stat WHERE name <> 'all' ORDER BY name"
                     ,"SELECT DISTINCT(name) FROM site ORDER BY name"
                     ,"SELECT DISTINCT(arch) FROM site ORDER BY arch"
                     ,"SELECT DISTINCT(fstype) FROM site ORDER BY fstype"
                     ,"SELECT DISTINCT(cename) FROM site ORDER BY cename"
                     ,"SELECT DISTINCT(status) FROM release_stat ORDER BY status"
                     ,""
                     ,""
                     ,"SELECT DISTINCT(LEFT(name, LOCATE('@',name)-1)) AS username FROM user ORDER BY username"
                     );
  }
  $status_map = array(0 => "disabled", 1 => "enabled");
  foreach ($queries as $query) {
    $list = array();
    if ($query != "") {
      $result = db_query($query,"ro");
      if (!$result) {
        db_err(mysqli_error());
      } else {
        while ($row = mysqli_fetch_array($result)) {
          array_push($list, $row[0]);
        }
      }
    }
    array_push($lists, $list);
  }


?>

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
        <h3><?php echo $LJSFi_VO; ?> software deployment status</h3>
<TABLE border="2" frame="hsides" rules="groups" summary="<?php echo $LJSFi_VO; ?> software deployment status." class="table1">
<?php if (!isset($_REQUEST["summary"])) { ?>
<?php if (isset($roleid) && $roleid > 1) echo '<COLGROUP align="center">'; ?>
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="left">
<?php } else {?>
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="center">
<?php } ?>
<?php
  require("combo.php");
  if (isset($_REQUEST["summary"])) {
    $COLUMNS = array (
                      'Entries<BR>'       => array('entries','entries')
                     ,'Resource<BR>'      => array('s.cs','cs')
                     ,'FS type<BR>'       => array('s.fstype','fstype')
                     ,'Status<BR>'        => array('rs.status','status')
                     ,'OS release<BR>'    => array('osdesc','osdesc')
                     ,'Rate [%]<BR>'      => array('entries','entries')
                     );
  } else {
    $COLUMNS = array (
                      'Release<BR>number' => array('rs.name','rel')
                     ,'Site name<BR>'     => array('s.name','sitename')
                     ,'Release arch<BR>'  => array('s.arch','arch')
                     ,'FS type<BR>'       => array('s.fstype','fstype')
                     ,'Resource<BR>'      => array('s.cename','cename')
                     ,'Status<BR>'        => array('rs.status','status')
                     ,'Comments<BR>'      => array('rs.comments','comments')
                     ,'Date<BR>'          => array('rs.date','date')
                     ,'Installer<BR>'     => array('u.name','user')
                     );
  }
  $indx = 0;
  echo ('<form method="get" action="">');
  if (isset($roleid) && $roleid > 1 && !isset($_REQUEST["summary"])) echo '<TH>Sel';
  echo ('<TH>Num');
  foreach($COLUMNS as $text=>$data) {
    $keyword = $data[0];
    $optname = $data[1];
    echo ('<TH>');
    if (count($lists[$indx]) > 0) {
      echo '<select name="',$optname,'" size="1">';
      combo_box ($lists[$indx]);
      echo '</select><br/>';
      echo '<input type="submit" value="Filter">';
      echo '<br/>';
    }
    $indx++;
    echo ("<A HREF='list.php?");
    $first = 0;
    foreach($_GET as $key=>$value) {
      if ($key != "orderby" && $key != "orderdir" && $value != "") {
        if ($first == 1) echo ("&");
        echo ($key . "=" . $value);
        $first = 1;
      }
    }
    if ($first == 1) echo ("&");
    echo ("orderby=" . $keyword);
    if (isset($_GET['orderdir']) && $_GET['orderdir'] == 'asc') {
      echo ("&orderdir=desc'>" . $text . "</A><BR><img width=30 src='img/b_up.png'>\n");
    } else {
      echo ("&orderdir=asc'>" . $text . "</A><BR><img width=30 src='img/b_down.png'>\n");
    }
  }
  if (isset($_REQUEST["summary"])) echo '<input type="hidden" name="summary" value="'.$_REQUEST["summary"].'">';
  echo '</form>';
?>
<TBODY>
<?php
  if (isset($roleid) && $roleid > 1 && !isset($_REQUEST["summary"])) {
    echo ('<TR><TD colspan="11">');
    if (isset($_GET)) {
      $getlist = array();
      foreach ($_GET as $key => $val) array_push($getlist,"${key}=${val}");
      if ($getlist) $getstring = "?".implode($getlist,"&");
    }
    echo ('<FORM method="post" name="ljsfadmin" action="list.php'.$getstring.'" onsubmit="return checkform(this);">');
    echo ("\n");
    printLJSFtags($LJSFtags);
    echo '<table id="toolbar_tbl" border="0" width="100%" cellpadding="1" rules="groups" class="adminbar">';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<COLGROUP width="80"></COLGROUP>';
    echo '<tr><td align="center">';
    echo ('<button type="submit" name="submit" value="delete" style="background-color:transparent;height:60" onclick="btnWhichButton=this"><img src="img/delete_icon_small.gif" border="0" width="40"></button>');
    echo '</td><td align="center">';
    echo ('<button type="submit" name="submit" value="setinstalled" style="background-color:transparent;height:60" onclick="btnWhichButton=this"><img src="img/installed_icon.gif" border="0" width="40"></button>');
    echo '</td><td align="center">';
    echo ('<button type="submit" name="submit" value="setfailed" style="background-color:transparent;height:60" onclick="btnWhichButton=this"><img src="img/failed_icon.gif" border="0" width="40"></button>');
    echo '</td><td align="center" valign="center">';
    echo ('<button type="submit" name="submit" value="setremoved" style="background-color:transparent;height:60" onclick="btnWhichButton=this"><img src="img/erased_icon.gif" border="0" width="60"></button>');
    echo '</td><td align="center" valign="center">';
    echo ('<button type="submit" name="submit" value="setaborted" style="background-color:transparent;height:60" onclick="btnWhichButton=this"><img src="img/aborted_icon.gif" border="0" width="40"></button>');
    echo '</td><td align="center" valign="bottom">';
    echo '<input type="checkbox" name="ljsfadmin_sa" value="all" onclick="SetAllCheckBoxes(\'ljsfadmin\', \'relsel[]\', \'ljsfadmin_sa\');">';
    echo '</td><td>';
    echo '</td></tr>';
    echo '<tr><td align="center">';
    echo 'Delete';
    echo '</td><td align="center">';
    echo 'Set Installed';
    echo '</td><td align="center">';
    echo 'Set Failed';
    echo '</td><td align="center">';
    echo 'Set Removed';
    echo '</td><td align="center">';
    echo 'Set Aborted';
    echo '</td><td align="center">';
    echo 'Select all';
    echo '</td><td>';
    echo '</td></tr>';
    echo '</table>';
    echo '</TD></TR>';
  }
?>
<?php
  $COLORS = array ('installed' => 'installedTask'
                 , 'failed' => 'failedTask'
                 , 'aborted' => 'abortedTask'
                 , 'pending' => 'pendingTask'
                 , 'removed' => 'removedTask'
                 , 'maxinstretry' => 'maxinstretryTask'
                 , 'maxrmretry' => 'maxrmretryTask'
                 , 'inconsistent' => 'inconsistentTask'
                 , 'other' => 'otherTask'
                 , 'installfail' => 'installfailTask'
                 , 'cancelled' => 'cancelledTask'
                 , 'closed' => 'closedTask'
                 , 'waiting' => 'waitingTask'
                  );

  // Perform the admin actions
  if (isset($_REQUEST['relsel']) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']==='POST')) atlas_require_client_certificate();
  if (isset($roleid) && isset($_REQUEST['relsel']) && $roleid > 1) {
    $_REQUEST['relsel'] = array_map(fn($v) => db_int($v,1), (array)$_REQUEST['relsel']);
    require("protected/config.php");
    require ("$LJSFi_PATH/access_log.php");
    $query_action = array();
    if ($_REQUEST["submit"] == "delete") {
      $jdl_list = array();
      $job_list = array();
      $log_list = array();
      $req_list = array();
      $query_prep_action = "SELECT ref FROM jdl WHERE relfk IN (".db_int_list($_REQUEST['relsel'],1).")";
      $result = db_query($query_prep_action);
      if (!$result) {
        echo ("<P>ERROR: Cannot get the jdl list. " . mysqli_error() . "</P>");
        exit();
      } else {
        while ($row = mysqli_fetch_array($result)) array_push($jdl_list,$row[0]);
      }
      if ($jdl_list) array_push($query_action, "DELETE FROM jdl WHERE ref IN (".join(',',$jdl_list).")");
      if ($jdl_list) {
        $query_prep_action = "SELECT ref, logfile FROM job WHERE jdlfk IN (".join(',',$jdl_list).")";
        $result = db_query($query_prep_action);
        if (!$result) {
          echo ("<P>ERROR: Cannot get the job list. " . mysqli_error() . "</P>");
          exit();
        } else {
          while ($row = mysqli_fetch_array($result)) {
            array_push($job_list,$row[0]);
            if (isset($row[1])) array_push($log_list,$upload_path.$row[1]);
          }
        }
      }
      if ($job_list) array_push($query_action, "DELETE FROM job WHERE ref IN (".join(',',$job_list).")");
      $query_prep_action = "SELECT id FROM request WHERE relfk IN (".db_int_list($_REQUEST['relsel'],1).")";
      $result = db_query($query_prep_action);
      if (!$result) {
        echo ("<P>ERROR: Cannot get the request list. " . mysqli_error() . "</P>");
        exit();
      } else {
        while ($row = mysqli_fetch_array($result)) array_push($req_list,"'".$row[0]."'");
      }
      if ($req_list) array_push($query_action, "DELETE FROM request WHERE id IN (".join(',',$req_list).")");
      array_push($query_action, "DELETE FROM release_stat WHERE ref IN (".db_int_list($_REQUEST['relsel'],1).")");
    } elseif ($_REQUEST["submit"] == "setinstalled") {
      foreach ($_REQUEST['relsel'] as $indx => $selection_raw) {
        $selection = db_int($selection_raw,1);
        if (isset($_REQUEST['comments'][$indx]) && $_REQUEST['comments'][$indx] != "")
          $comments = db_quote($_REQUEST['comments'][$indx]); else $comments="NULL";
        array_push($query_action, "UPDATE release_stat SET status='installed', comments=".$comments." WHERE ref = ".$selection);
      }
    } elseif ($_REQUEST["submit"] == "setfailed") {
      foreach ($_REQUEST['relsel'] as $indx => $selection_raw) {
        $selection = db_int($selection_raw,1);
        if (isset($_REQUEST['comments'][$indx]) && $_REQUEST['comments'][$indx] != "")
          $comments = db_quote($_REQUEST['comments'][$indx]); else $comments="NULL";
        array_push($query_action, "UPDATE release_stat SET status='failed', comments=".$comments." WHERE ref = ".$selection);
      }
    } elseif ($_REQUEST["submit"] == "setremoved") {
      foreach ($_REQUEST['relsel'] as $indx => $selection_raw) {
        $selection = db_int($selection_raw,1);
        if (isset($_REQUEST['comments'][$indx]) && $_REQUEST['comments'][$indx] != "")
          $comments = db_quote($_REQUEST['comments'][$indx]); else $comments="NULL";
        array_push($query_action, "UPDATE release_stat SET status='removed', comments=".$comments." WHERE ref = ".$selection);
      }
    } elseif ($_REQUEST["submit"] == "setaborted") {
      foreach ($_REQUEST['relsel'] as $indx => $selection_raw) {
        $selection = db_int($selection_raw,1);
        if (isset($_REQUEST['comments'][$indx]) && $_REQUEST['comments'][$indx] != "")
          $comments = db_quote($_REQUEST['comments'][$indx]); else $comments="NULL";
        array_push($query_action, "UPDATE release_stat SET status='aborted', comments=".$comments." WHERE ref = ".$selection);
      }
    }
  }
  if (isset($query_action)) {
    foreach ($query_action as $query) {
      //echo $query."<br>";
      $result = db_query($query,"rw",True);
      if (!$result) {
        echo ("<P>ERROR: " . mysqli_error() . "</P>");
        exit();
      }
    }
  }
  if (isset($log_list)) {
    foreach ($log_list as $logfile) {
      //echo "Removing ".$logfile."<br>";
      unlink($logfile);
    }
  }

  // Show the results
  if (isset($_REQUEST["summary"])) {
    $equery = ("SELECT sitefk, COUNT(DISTINCT(name)) FROM release_stat rs GROUP by sitefk");
    $query  = ("SELECT COUNT(DISTINCT(rs.name)) AS entries, s.cs, s.fstype, rs.status, CONCAT(s.osname,' ',s.osversion) AS osdesc, rs.sitefk
                  FROM release_stat rs, site s
                 WHERE rs.sitefk=s.ref AND rs.name <> 'ALL'");
  } else {
    $query  = ("SELECT rs.name,s.name,ra.description,s.fstype,s.cename
                      ,rs.status,rs.comments,rs.date,u.name
                      ,u.email,rs.ref
                      ,s.osname,s.osversion,s.cs,s.ref,s.status
                      ,(SELECT job.status FROM job, jdl WHERE job.jdlfk=jdl.ref AND jdl.relfk=rs.ref ORDER BY job.submission_time DESC LIMIT 1)
                      ,g.name
                FROM release_stat rs,release_data rd,release_arch ra,site s,user u,grid g
                WHERE rs.sitefk=s.ref AND rs.name=rd.name
                      AND rd.archfk=ra.ref AND rs.userfk=u.ref
                      AND s.gridfk = g.ref AND rs.name != 'ALL'");
  }
  if (isset($_REQUEST['rel']) && $_REQUEST['rel'] != '')
    $query=($query . " AND rs.name LIKE " . db_quote($_REQUEST['rel'],'ro'));
  if (isset($_REQUEST['user']) && $_REQUEST['user'] != '')
    $query=($query . " AND u.name LIKE " . db_quote('%'.$_REQUEST['user'].'%','ro'));
  if (isset($_REQUEST['gridname']) && $_REQUEST['gridname'] != '')
    $query=($query . " AND g.name = " . db_quote($_REQUEST['gridname'],'ro'));
  #  $query=($query . " AND s.gridfk = (SELECT ref FROM grid WHERE name='" . $_REQUEST['gridname'] . "')");
  if (isset($_REQUEST['sitename']) && $_REQUEST['sitename'] != '')
    $query=($query . " AND s.name LIKE " . db_quote($_REQUEST['sitename'],'ro'));
  if (isset($_REQUEST['arch']) && $_REQUEST['arch'] != '')
    $query=($query . " AND s.arch LIKE " . db_quote('%'.$_REQUEST['arch'].'%','ro'));
  if (isset($_REQUEST['relarch']) && $_REQUEST['relarch'] != '')
    $query=($query . " AND ra.description LIKE " . db_quote('%'.$_REQUEST['relarch'].'%','ro'));
  if (isset($_REQUEST['cename']) && $_REQUEST['cename'] != '')
    $query=($query . " AND s.cename LIKE " . db_quote('%'.$_REQUEST['cename'].'%','ro'));
  if (isset($_REQUEST['maxage']) && $_REQUEST['maxage'] != '')
    $query=($query . " AND rs.date >= DATE_SUB(CURRENT_DATE , INTERVAL " . db_int($_REQUEST['maxage'],0,87600) . " HOUR)");
  if (isset($_REQUEST['resource']) && $_REQUEST['resource'] != '') {
    if (strpos($_REQUEST['resource'],'%') !== false) {
      $query=($query . " AND s.cename LIKE " . db_quote($_REQUEST['resource'],'ro'));
    } else {
      $query=($query . " AND s.cename = " . db_quote($_REQUEST['resource'],'ro'));
    }
  }
  if (isset($_REQUEST['cs']) && $_REQUEST['cs'] != '')
    $query=($query . " AND s.cs LIKE " . db_quote('%'.$_REQUEST['cs'].'%','ro'));
  if (isset($_REQUEST['siteid']) && $_REQUEST['siteid'] > 0)
    $query=($query . " AND s.ref = " . db_int($_REQUEST['siteid'],1));
  if (isset($_REQUEST['fstype']) && $_REQUEST['fstype'] != '') {
    if (substr($_REQUEST['fstype'],0,1) == "!") {
      $query=($query . " AND s.fstype <> " . db_quote(substr($_REQUEST['fstype'],1),'ro'));
    } else {
      $query=($query . " AND s.fstype = " . db_quote($_REQUEST['fstype'],'ro'));
    }
  }
  if (isset($_REQUEST['status']) && $_REQUEST['status'] != '')
    $query=($query . " AND rs.status LIKE " . db_quote('%'.$_REQUEST['status'].'%','ro'));
  if (isset($_REQUEST['critical']))
    $query=($query . " AND rd.critical = 1");
  if (isset($_REQUEST['tier_level']) && $_REQUEST['tier_level'] != '')
    $query=($query . " AND s.tier_level = " . db_int($_REQUEST['tier_level'],0,99));
  if (isset($_REQUEST['sitetype']))
    $query=($query . " AND s.activity_typefk = (SELECT ref FROM site_activity_type WHERE name=".db_quote($_REQUEST['sitetype'],'ro').")");
  if (isset($_REQUEST['orphaned']))
    $query=($query . " AND (SELECT COUNT(*) FROM request WHERE request.relfk=rs.ref)=0");
  if (isset($_REQUEST['invalid']))
    $query=($query . " AND (SELECT job.status FROM job, jdl WHERE job.jdlfk=jdl.ref AND jdl.relfk=rs.ref ORDER BY job.submission_time DESC LIMIT 1) = 'failed' AND rs.status = 'installed'");
  if (isset($_REQUEST['ostype']) && $_REQUEST['ostype'] != '')
    $query=($query . " AND CONCAT(s.osname, ' ', s.osversion, ' ', s.osrelease) = " . db_quote($_REQUEST['ostype'],'ro'));
  if (isset($_REQUEST["summary"])) {
    $query .= " GROUP BY s.cs,rs.status ORDER BY ";
    if (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], ['rs.status','s.cs','rs.name','s.name','s.cename','rs.date'], true)) {
      $query .= $_REQUEST['orderby'];
    } else {
      $query .= " rs.status,s.cs";
    }
  } else {
    $query .= " ORDER BY ";
    if (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], ['rs.status','s.cs','rs.name','s.name','s.cename','rs.date'], true)) {
      $query .= $_REQUEST['orderby'];
    } else {
      $query .= " rs.name,rs.status,s.name,s.cename";
    }
  }
  if (isset($_REQUEST['orderdir']) && in_array(strtolower($_REQUEST['orderdir']), ['asc','desc'], true)) $query .= ' ' . strtoupper($_REQUEST['orderdir']);
  if (isset($_REQUEST['limit']) && $_REQUEST['limit'] != '') $query .= ' LIMIT ' . db_int($_REQUEST['limit'],1,10000);
  // SQL query disclosure disabled
  if (isset($_REQUEST["summary"])) {
    $eresult = db_query($equery,"ro");
    $tot_entries = array();
    while ( $row = mysqli_fetch_array($eresult) ) {
      $tot_entries[$row[0]] = $row[1];
    }
  }

  $result = db_query($query,"ro");
  if (!$result) {
    echo ("<P>ERROR: " . mysqli_error() . "</P>");
    exit();
  } else {
    $category = array();
    while ( $row = mysqli_fetch_array($result) ) {
      if (isset($_REQUEST["summary"])) {
        if (!isset($category[$row[3]])) $category[$row[3]] = 0;
        $category[$row[3]]++;
        if (!isset($COLORS[$row[3]])) {
          $color = $COLORS['other'];
        } else {
          $color = $COLORS[$row[3]];
        }
        echo ("<TR class='".$color."'>");
        for ($i=0; $i<5; $i++) {
          echo ("<TD nowrap>");
          if ($i == 0) echo ($category[$row[3]]."</TD>\n<TD>");
          if ($i == 1) { echo ("<A HREF=\"list.php?resource=" . $row[1] . "&status=".$row[3]."\">"); }
          echo ($row[$i]);
          if ($i == 1) echo ("</A>");
          echo ("</TD>\n");
        }
        echo ("<TD nowrap>");
        if (isset($tot_entries[$row[5]])) {
          $en   = $row[0];
          $te   = $tot_entries[$row[5]];
          $rate = 100*$en/$te;
        } else {
          $en   = $row[0];
          $te   = 0;
          $rate = 0;
        }
        printf ("[%d/%d] %.1f%%", $en,$te,$rate);
        echo ("</TD>\n");
      } else {
        if (!isset($category[$row[5]])) $category[$row[5]] = 0;
        $category[$row[5]]++;
        if (!isset($COLORS[$row[5]])) {
          $color = $COLORS['other'];
        } else {
          if ($row[5] == "installed" && $row[16] == "failed") {
            $color = $COLORS["installfail"];
          } else {
            $color = $COLORS[$row[5]];
          }
        }
        echo ("<TR class='".$color."'>");
        if (isset($roleid) && $roleid > 1) echo ('<TD><input type="checkbox" name="relsel[]" value="'.$row[10].'" onclick="enableDisableField(this,'.$row[10].');"></td>');
        for ($i=0; $i<9; $i++) {
          echo ("<TD>");
          if ($i == 0) echo ($category[$row[5]]."</TD>\n<TD>");
          if ($i == 4) {
            echo ('<A HREF="jobs.php?relfk=' . $row[10] . '" class="tooltip" title="');
            echo ('<A HREF=\'protected/sitedef.php?mode=update&sitesrc='.$row[14].'\' TARGET=top>' . $row[13] . '</A><BR>' . $row[11] . ' ' . $row[12] . '<BR>Status: ' . $status_map[$row[15]] . '<BR>Grid: ' . $row[17] . '<BR>Last job status: ' . $row[16] . '<BR><A HREF=\'http://atlas-agis.cern.ch/agis/panda_resource/detail/' . $row[13] . '/full/\' TARGET=\'_blank\'>AGIS info</A>');
            echo ('">');
          }
          if ($i == 0) { echo ("<A HREF=\"list.php?rel=" . $row[0] . "\">"); }
          if ($i == 1) { echo ("<A HREF=\"protected/req.php?site=" . $row[1] . "&rel=".$row[0]."\">"); }
  	if ($i == 6 && isset($roleid) && $roleid > 1) {
  	  echo ('<input type="text" id="'.$row[10].'" name="comments[]" value="');
          }
          if ($i == 8 && $row[10] != "") { echo ("<A HREF=\"mailto:" . $row[9] . "\">"); }
          echo ($row[$i]);
          if ($i == 0 || ($i == 8 && $row[10] != "")) echo ("</A>");
          if ($i == 6 && isset($roleid) && $roleid > 1) echo ('" disabled>');
          echo ("</TD>\n");
        }
      }
      echo ("</TR><TBODY>");
    }
  }
?>
</TABLE>

<?php if (isset($roleid) && $roleid > 1) { echo '</form>'; } ?>

</TD></TR></TABLE>

<?php
  if (isset($ssluserdetails) && $ssluserdetails != "") {
    echo ("You are logged in as ".$ssluserdetails);
    if (isset($role)) echo (" (".$role.")");
  echo ("<br>");
  }
  echo (date("l, F dS Y, H:m:s"));
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
