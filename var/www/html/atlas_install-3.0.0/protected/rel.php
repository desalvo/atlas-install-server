<?php
 if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
<?php require("config.php"); ?>
<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation Release Matrix</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  if (form.relstat.value=='') {
    alert ('Please select a status to update the record');
    retval=false;
  }
  return retval;
}
//-->
</script>
</HEAD>
<BODY>
<?php } ?>
<?php
  require("db.php");
  require("combo.php");
  require("user_info.php");

  # Get the user role
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    $role="";
  } else {
    $adminfk=$user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
  }

?>
<?php if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/req_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content2">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Software Installation Release Matrix</h3>

<form method="post" name="rs_form" action="">
<h4>
Search release (use % as wildcard)&nbsp;
<input type='text' name='name'>
<input type="submit" value="Search">
</form>
</h4>

<TABLE border="2" frame="hsides" rules="groups"
          summary="<?php echo $LJSFi_VO; ?> Software release matrix." class="table2">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<THEAD valign="top">
<TR>
<TH>Num
<TH>Release name
<TH>Arch
<TH>Build type
<TH>Software<BR>name
<TH>CVMFS<BR>status
<TH>Status
<TH>Type
<TH>Requires
<TH>DB release
<TH>Size
<?php if ($role == "admin" || $role == "master") { ?>
<BR>(physical):(logical):(temp)
<?php } ?>
<BR>[GB]
<TH>Autoinstall
<TH>Installation<BR>phys path
<TH>Installation<BR>log path
<TH>Tag
<TBODY>
<?php } ?>
<?php
  $COLORS     = array ( 0 => 'prod_noauto'
                     ,  1 => 'obs_noauto'
                     , 10 => 'prod_allauto'
                     , 11 => 'obs_allauto'
                     , 20 => 'prod_auto1'
                     , 21 => 'obs_auto1'
                     , 30 => 'prod_auto2'
                     , 31 => 'obs_auto2'
                     , 40 => 'prod_auto3'
                     , 41 => 'obs_auto3'
                     , 50 => 'prod_auto4'
                     , 51 => 'obs_auto4'
                      );
  $ISOBSOLETE = array (0 => 'production'
                     , 1 => 'obsolete'
                      );
  $ISINSTALLED = array (0 => 'not installed'
                      , 1 => 'installed'
                       );

  $ISENABLED = array();
  $result = db_query("SELECT ref,description FROM autoinstall_target ORDER BY ref");
  while ($row = mysqli_fetch_array($result)) {
    $ISENABLED[$row[0]] = $row[1];
  }

  $reltypes = array();
  $result  = db_query("SELECT ref, description FROM release_type");
  while ( $row = mysqli_fetch_array($result) ) {
    $reltypes[$row[0]] = $row[1];
  }

  $relnames = array();
  $result  = db_query("SELECT distinct(name) FROM release_data WHERE name <> 'ALL'");
  while ( $row = mysqli_fetch_array($result) ) {
    array_push($relnames,$row[0]);
  }

  $relarch = array();
  $result = db_query("SELECT description FROM release_arch");
  while ( $row = mysqli_fetch_array($result) ) {
    array_push($relarch,$row[0]);
  }

  // Check if we need to update the status
  if (isset($_REQUEST["id"]) && $_REQUEST["id"] != "" && ($role == "admin" || $role == "master")) {
    // Check for request integrity
    if ((!isset($_REQUEST['cvmfs_available']) || $_REQUEST['cvmfs_available']=='') ||
        (!isset($_REQUEST['obsolete'])        || $_REQUEST['obsolete']=='') ||
        (!isset($_REQUEST['reltype'])         || $_REQUEST['reltype']=='') ||
        (!isset($_REQUEST['relreq'])          || $_REQUEST['relreq']=='') ||
        (!isset($_REQUEST['autoinstall'])     || $_REQUEST['autoinstall']=='')) {
      echo 'One of the needed fields have not been provided.';
    } else {
      $updateqry  = "UPDATE release_data ";
      $updateqry .=    "SET cvmfs_available=" . db_int($_REQUEST['cvmfs_available'],0,1);
      $updateqry .=      ", obsolete="        . db_int($_REQUEST['obsolete'],0,1);
      $updateqry .=      ", typefk="          . db_int($_REQUEST['reltype'],1);
      $updateqry .=      ", autoinstall="     . db_int($_REQUEST['autoinstall'],0,1);
      if (isset($_REQUEST['diskspace']) && $_REQUEST['diskspace'] != '')
          $updateqry .=  ", sw_diskspace=" . db_quote($_REQUEST['diskspace'],'rw');
      if ($_REQUEST["relreq"]=="NULL") {
        $updateqry .=    ", requires=NULL";
      } else {
        $updateqry .=    ", requires=" . db_quote($_REQUEST['relreq'],'rw');
      }
      $updateqry .= " WHERE ref=" . db_int($_POST["id"],1);
      db_query($updateqry);

      # Send the notification email
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
      $link=dirname($_SERVER['FULL_URL']) . "/rel.php?name=" . $_REQUEST["name"];
      $subj='[RELEASE CHANGE]'
           .' Release '.$_REQUEST["name"].' status changed';
      $body='The status of the release '.$_REQUEST["name"]
           .' has been modified by "'.$sslusername.'".'."\n"
           .'See '.$link.' for details.';
      $from = "From: LJSFi agent <".$LJSFi_email.">";
      $emailqry = "SELECT u.email FROM subscription s, user u"
                . " WHERE s.userfk=u.ref"
                . "   AND s.release=1";
      $result = db_query($emailqry);
      $to = "";
      while ($email = mysqli_fetch_array($result)) {
        if ($to != "") {
          $to .= ','.$email[0];
        } else {
          $to  = $email[0];
        }
      }
      mail($to, $subj, $body, $from);
      header("Location: $HTTP_REFERER");
    }
  }

  if (!isset($_POST['ws']) or $_POST['ws'] == '') {
  # Query header
  $q_hdr  = ("SELECT release_data.ref
                    ,release_data.name
                    ,release_arch.description
                    ,release_data.build
                    ,release_data.sw_name
                    ,release_data.cvmfs_available
                    ,release_data.obsolete
                    ,release_data.typefk
                    ,release_data.requires as relreq
                    ,release_data.dbrelease
                    ,release_data.sw_diskspace
                    ,release_data.autoinstall
                    ,CONCAT(release_data.sw_physicalpath,'/',release_data.sw_versionarea)
                    ,CONCAT(release_data.sw_logicalpath,'/',IF (release_data.requires IS NULL,release_data.name,release_data.requires))
                    ,release_data.tag
                    ,release_data.archfk
                    ,release_type.category
                    ,release_data.kit_cache
                    ,(SELECT rd.sw_name FROM release_data rd WHERE rd.name=relreq)");
  $q_body = (" FROM release_data,release_arch,release_type
              WHERE     release_data.typefk=release_type.ref
                    AND release_data.archfk=release_arch.ref
                    AND release_data.typefk > 1");
  if (isset($_REQUEST['name']))     $q_body .= " AND release_data.name LIKE " . db_quote($_REQUEST['name'],'ro');
  if (isset($_REQUEST['obsolete'])) $q_body .= " AND release_data.obsolete=" . db_int($_REQUEST['obsolete'],0,1);
  if (isset($_REQUEST['swname'])) $q_body .= " AND release_data.sw_name LIKE " . db_quote($_REQUEST['swname'],'ro');
  if (isset($_REQUEST['cvmfs_available'])) $q_body .= " AND release_data.cvmfs_available = " . db_int($_REQUEST['cvmfs_available'],0,1);
  if (isset($_REQUEST['exclude'])) {
    foreach ($_REQUEST['exclude'] as $name) {
      $q_body .= (" AND release_data.name NOT LIKE " . db_quote($name,'ro'));
    }
  }

  # Get the number of records
  $limit  = 30;
  if (isset($_REQUEST['limit'])) $limit = db_int($_REQUEST['limit'],1,500);
  $offset = 0;
  $query = ("SELECT count(*) " . $q_body);
  $result = db_query($query);
  $row = mysqli_fetch_row($result);
  $records = $row[0];
  $maxpage = intval(($records-1)/$limit);
  $pagerange = $limit;
  $page = 0;

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
  $query .= (" ORDER BY release_data.obsolete, release_data.autoinstall DESC, release_data.name ASC");
  $query .= (" LIMIT " . db_int($offset,0) . "," . db_int($limit,1,500));
  // SQL query disclosure disabled
  $result = db_query($query);
  $rowindx = $offset;
  while ( $row = mysqli_fetch_array($result) ) {
    $rowindx++;
    if (($role == "admin" && ($row[12] == $adminfk || $row[12] == NULL)) || $role == "master") {
      echo '<form method="post" name="' . $row[0] . '" action="" onsubmit="return checkform(this);">';
    }
    $color = $row[6]+$row[11]*10;
    echo ("<TR class='" . $COLORS[$color] . "'>");
    echo "\n";
    echo ("<TD>" . $rowindx  . "</TD>");
    for ($i=1; $i<15; $i++) {
      echo ("<TD>");
      echo "\n";
      if ($i == 1) {
        if ($role == "admin" || $role == "master") {
          echo ("<input type='hidden' name='id' value='" . $row[0] . "'>\n");
          echo ("<input type='hidden' name='name' value='" . $row[1] . "'>\n");
        }
        $arch = preg_replace('/^_/','', $row[2]);
        preg_match('/([^:]*):([^:]*)/', $row[1], $pkgparts);
        if (isset($pkgparts[2])) { $pkgname = $pkgparts[2]; } else { $pkgname = $row[1]; }
        $logpath_parts = preg_split('/\//',$row[13]);
        $physpath_parts = preg_split('/\//',$row[12]);
        $lpath = $logpath_parts[count($logpath_parts)-1];
        $ppath = $physpath_parts[count($physpath_parts)-2];
        if ($ppath != "releases") {
            $lpath = preg_replace("/(:[^:]+)$/", "/latest", $lpath);
            $ppath .= "/".$physpath_parts[count($physpath_parts)-1];
        }
        echo ('<A HREF="'.$LJSFi_swinst_proto.'://'.$row[4].'/'.$pkgname.'/'.$arch.'/?install='.$pkgname.'&project-opt=opt&logical='.$lpath.'&physical='.$ppath);
        $insttype_parts = preg_split('/:/',$row[16]);
        echo ('&project-type='.$insttype_parts[0]);
        for ($indx=1; $indx<count($insttype_parts); $indx++) {
            if (isset($insttype_parts[$indx])) {
                echo ('&'.$insttype_parts[$indx]);
            }
        }
        if (isset($row[18])) echo ('&require-prj='.$row[18]);
        if (isset($row[9])) echo ('&dbrelease='.$row[9]);
        if (isset($row[17])) echo ('&kit-cache='.$row[17]);
        echo ('">');
        echo ($row[1]);
        echo ('</A>');
        echo "\n";
      } elseif ($i == 5) {
        if ($role == "admin" || $role == "master") {
          echo ("<select name='cvmfs_available' size='1'>");
          echo "\n";
          for ($j=0; $j < count($ISINSTALLED); $j++) {
            echo ('<option value="'.$j.'" ');
            if ($row[$i] == $j) echo ('selected');
            echo ('>'.$ISINSTALLED[$j].'</option>');
            echo "\n";
          }
          echo '</select>';
          echo "\n";
        } else {
          echo ($ISINSTALLED[$row[$i]]);
          echo "\n";
        }
      } elseif ($i == 6) {
        if ($role == "admin" || $role == "master") {
          echo ("<select name='obsolete' size='1'>");
          echo "\n";
          for ($j=0; $j < count($ISOBSOLETE); $j++) {
            echo ('<option value="'.$j.'" ');
            if ($row[$i] == $j) echo ('selected');
            echo ('>'.$ISOBSOLETE[$j].'</option>');
            echo "\n";
          }
          echo '</select>';
          echo "\n";
        } else {
          echo ($ISOBSOLETE[$row[$i]]);
          echo "\n";
        }
      } elseif ($i == 7) {
        if ($role == "admin" || $role == "master") {
          echo ("<select name='reltype' size='1'>");
          echo "\n";
          foreach ($reltypes as $indx => $type) {
            echo ('<option value="'.$indx.'" ');
            if ($row[$i] == $indx) echo ('selected');
            echo ('>'.$type.'</option>');
            echo "\n";
          }
          echo '</select>';
          echo "\n";
        } else {
          echo ($reltypes[$row[$i]]);
          echo "\n";
        }
      } elseif ($i == 8) {
        if ($role == "admin" || $role == "master") {
          echo ("<select name='relreq' size='1'>");
          echo "\n";
          echo ('<option value="NULL" ');
          if (!isset($row[$i])) echo ('selected');
          echo ('>none</option>');
          echo "\n";
          foreach ($relnames as $name) {
            echo ('<option value="'.$name.'" ');
            if (isset($row[$i]) && $row[$i] == $name) echo ('selected');
            echo ('>'.$name.'</option>');
            echo "\n";
          }
          echo '</select>';
          echo "\n";
        } else {
          if (isset($relnames[$row[$i]])) echo ($relnames[$row[$i]]);
          echo "\n";
        }
      } elseif ($i == 10) {
        if ($role == "admin" || $role == "master") {
          echo ("<input name='diskspace' size='25' value='".$row[$i]."'>");
          echo "\n";
        } else {
          list ($phys, $logic, $temp) = split (':', $row[$i]);
          $val = sprintf('%3.1f', $phys/1048576);
          echo ($val);
          echo "\n";
        }
      } elseif ($i == 11) {
        if ($role == "admin" || $role == "master") {
          echo ("<select name='autoinstall' size='1'>");
          echo "\n";
          foreach ($ISENABLED as $indx => $name) {
            echo ('<option value="'.$indx.'" ');
            if ($row[11] == $indx) echo ('selected');
            echo ('>'.$ISENABLED[$indx].'</option>');
            echo "\n";
          }
          echo '</select>';
          echo "\n";
        } else {
          echo ($ISENABLED[$row[$i]]);
          echo "\n";
        }
      } elseif ($i == 13) {
        $parts = split('-', $row[$i]);
        if (count($parts)>1) $plist = array_pop($parts);
        echo (join('-',$parts));
      } else {
        echo ($row[$i]);
        echo "\n";
      }
      echo ("</TD>");
      echo "\n";
    }
    if ($role == "admin" || $role == "master") {
      echo '<TD><input type="submit" value="Update"></TD></form>';
    }
    echo ("</TR><TBODY>");
  }
  echo ("</TABLE><P>");

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
