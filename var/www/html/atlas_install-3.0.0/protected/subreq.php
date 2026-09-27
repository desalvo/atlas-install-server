<?php

  require("guid.php");
  require("config.php");
  require("db.php");
  require("user_info.php");
  function send_mail($link,$guid) {
    global $LJSFi_VO,$LJSFi_email;
    $infoqry = "SELECT rt.description, rl.name, s.cename, s.name, r.admin_comments"
             . "  FROM request_type rt, request r, release_stat rl, site s"
             . " WHERE r.relfk=rl.ref"
             . "   AND r.sitefk=s.ref"
             . "   AND r.typefk=rt.ref"
             . "   AND r.id=".db_quote($guid,'ro');
    $result = db_query($infoqry);
    $info=mysqli_fetch_row($result);
    if (!isset($info[3])) { $info[3]=""; }
    $user_info = get_user_info("subscribers",NULL,NULL,True,$info[3]);
    $emails = array();
    foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
    $to = implode(",",$emails);

    $from_header = "From: LJSFi agent <".$LJSFi_email.">";
    $contents = "Dear ".$LJSFi_VO." software manager,\n";
    $contents = $contents . "a request for task type ".$info[0]." has been submitted by\n";
    $contents = $contents . $_POST["user"] . " <" . $_POST["email"] . ">\n";
    $contents = $contents . "on " . date("l, F dS Y, H:m:s") . "\n";
    $contents = $contents . "The request concerns the release '". $_POST["rel"] . "'";
    if ($_POST["rel"] == 'other') $contents = $contents . " (" . $_POST["relnew"] . ").";
    $contents = $contents . "\n";
    $contents = $contents . "on the CE " . $_POST["cename"] . ", indentified by\n";
    $contents = $contents . $_POST["cs"] . "\n";
    $contents = $contents . "Additional comments are reported below:\n------------\n" . $_POST["comments"] . "\n------------\n";
    $contents = $contents . "Additional info is available from the URL below:\n\n" . $link;
    $contents = $contents . "\n\nPlease process this request as soon as possible.\nBest regards.";
    $subject  = "[REQUEST] Installation request for release " . $_POST["rel"] . " at " . $_POST["cename"];
 
    if($contents != "" && $to != "") {
      mail($to, $subject, $contents, $from_header);
      if (isset($HTTP_REFERER)) header("Location: $HTTP_REFERER");
      // Update the mail log
      $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                  . "(SELECT ref FROM mail_type WHERE name='immediate'),"
                  . db_quote($to,'rw') . ","
                  . count($emails) . ","
                  . db_quote(date("Y-m-d H:i:s"),'rw') . ")";
      $result = db_query($maillogqry);
    }
    // Insert a record in the log table
    $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
    $row = mysqli_fetch_row($res);
    $userref = $row[0];
    $logqry = "INSERT INTO log (requestfk,action,description,userfk,comments,url,date) VALUES ("
             . "'" . $guid . "',"
             . "'request',"
             . "'" . $info[0] . " on resource " . $_POST["cs"] . "',"
             . $userref . ","
             . "'" . $_POST["comments"]."',"
             . "'" . $link . "',"
             . db_quote(date("Y-m-d H:i:s"),'rw') . ")";
    $result = db_query($logqry,"rw",TRUE);
  }

  function check_pin() {
    $pinval=0;
    if (isset($_POST["cename"]) && isset($_POST["rel"])) {
      $qry = "SELECT release_stat.pin "
              ."FROM release_stat, site "
             ."WHERE release_stat.sitefk = site.ref "
               ."AND site.cename = ".db_quote($_POST["cename"],'ro')." "
               ."AND release_stat.name=".db_quote($_POST["rel"],'ro')
               ."AND release_stat.status<>'removed'";
      $res = db_query($qry);
      $row = mysqli_fetch_row($res);
      if ($row) $pinval = $row[0];
    }
    return $pinval;
  }

  function check_requirements() {
    // Process the requirements
    $requirementsok = 1;   // By default the requirements are satisfied
    if (!isset($_POST["nodeps"]) && isset($_POST["rel"])) {
      $qry = "SELECT rd.requires FROM release_data rd WHERE rd.name=".db_quote($_POST["rel"],'ro')." LIMIT 1";
      $res = db_query($qry);
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
                    . " WHERE release_stat.name = ".db_quote($requires,'ro')
                    .   " AND release_stat.sitefk = site.ref"
                    .   " AND site.name = ".db_quote($_POST["sitename"],'ro');
            $resreq = db_query($qryreq);
            while ($row = mysqli_fetch_array($resreq)) {
              if (isset($row[0]) && strtolower($row[0]) == "installed") $requirementsok = 1;
            }
          }
        }
      }
    }
    return $requirementsok;
  }

  function check_required() {
    // Process the requirements
    $requiredby = "";   // By default the release is not required by anybody
    if (isset($_POST["rel"])) {
      $qry = "SELECT rd.name FROM release_data rd WHERE rd.requires LIKE ".db_quote('%'.$_POST["rel"],'ro');
      $res = db_query($qry);
      while ($row = mysqli_fetch_array($res)) {
        if (isset($row[0]) && $row[0] != "") {
          $relnum = $row[0];
          if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
            $qryreq = "SELECT release_stat.status FROM release_stat, site"
                    . " WHERE release_stat.name = ".db_quote($relnum,'ro')
                    .   " AND release_stat.sitefk = site.ref"
                    .   " AND (site.name = ".db_quote($_POST["sitename"],'ro');
            if (isset($_POST["cs"]) && $_POST["cs"] != "") {
              $qryreq .= " OR site.cs = ".db_quote($_POST["cs"],'ro');
            }
            $qryreq .= ")";
            $resreq = db_query($qryreq);
            $isfound = 0;
            while ($row = mysqli_fetch_array($resreq)) {
              if (isset($row[0]) && strtolower($row[0]) != "removed" && strtolower($row[0]) != "aborted") $isfound=1;
            }
            if ($isfound == 1) {
              if ($requiredby != "") $requiredby .= ", ";
              $requiredby .= $relnum;
            }
          } else {
            if ($requiredby != "") $requiredby .= ", ";
            $requiredby .= $relnum;
          }
        }
      }
    }
    return $requiredby;
  }

  function insert_data() {
    // Globals
    global $LJSFi_VO,$LJSFi_email;
    // Current date/time
    $date = date("Y-m-d H:i:s");

    // Site info
    if (isset($_POST["atlassitename"])) $atlassitename = $_POST["atlassitename"];
    if (isset($_POST["sitename"]))      $sitename = $_POST["sitename"];
    if (isset($_POST["osname"]))        $osname   = $_POST["osname"];
    if (isset($_POST["osver"]))         $osver    = $_POST["osver"];
    if (isset($_POST["osrel"]))         $osrel    = $_POST["osrel"];
    if (isset($_POST["cs"]))            $cs       = $_POST["cs"];
    if (isset($_POST["cename"]))        $cename   = $_POST["cename"];
    if (isset($_POST["arch"]))          $arch     = $_POST["arch"];
    if (isset($_POST["dsstatus"]))      $dsstatus = $_POST["dsstatus"];
    if (isset($_POST["gridname"])) {
      $gridname = $_POST["gridname"];
      $res = db_query("SELECT ref FROM grid WHERE name=" . db_quote($gridname,'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) db_query("INSERT INTO grid SET name=" . db_quote($gridname,'rw'),"rw",TRUE);
    }
    if (isset($_POST["facility"])) {
      $facility = $_POST["facility"];
      $res = db_query("SELECT ref FROM facility WHERE name=" . db_quote($facility,'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) db_query("INSERT INTO facility SET name=" . db_quote($facility,'rw'),"rw",TRUE);
    }
    if (isset($_POST["sitetype"])) {
      $sitetype = $_POST["sitetype"];
      $res = db_query("SELECT ref FROM site_activity_type WHERE name=" . db_quote($sitetype,'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) db_query("INSERT INTO site_activity_type SET name=" . db_quote($sitetype,'rw'),"rw",TRUE);
    }

    if (isset($cs)) {
      // Process the site
      $res = db_query("SELECT ref, cs, cename, name, osname, osversion, osrelease, arch, swarea, status, fstype FROM site WHERE cs=" . db_quote($cs,'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) {
        $siteqry  = "INSERT INTO site SET";
        $siteqry .= "  cs="         . db_quote($cs,'rw');
        $siteqry .= ", cename="     . db_quote($cename ?? '','rw');
        $siteqry .= ", name="       . db_quote($sitename ?? '','rw');
        $siteqry .= ", atlas_name=" . db_quote($atlassitename ?? '','rw');
        $siteqry .= ", gridfk=(SELECT ref FROM grid WHERE name=" . db_quote($gridname ?? '','rw') . ")";
        $siteqry .= ", facilityfk=(SELECT ref FROM facility WHERE name=" . db_quote($facility ?? '','rw') . ")";
        $siteqry .= ", activity_typefk=(SELECT ref FROM site_activity_type WHERE name=" . db_quote($sitetype ?? '','rw') . ")";
        $siteqry .= ", osname="    . db_quote($osname ?? '','rw');
        $siteqry .= ", osversion=" . db_quote($osver ?? '','rw');
        $siteqry .= ", osrelease=" . db_quote($osrel ?? '','rw');
        if (isset($arch) && $arch != "" && $arch != "-") $siteqry .= ", arch=".db_quote($arch,'rw');
        $siteenabled = 1;
        if (isset($dsstatus) && $dsstatus != "") {
          if (intval($dsstatus) == 0) {
            $siteenabled = 0;
            // Send a notification email
            $user_info = get_user_info("subscribers",NULL,NULL,True,$sitename);
            $emails = array();
            foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
            $to = implode(",",$emails);
            $from_header = "From: LJSFi agent <".$LJSFi_email.">";
            $contents = "Dear ".$LJSFi_VO." " . $sitename . " subscriber,\n";
            $contents = $contents . "the new resource ".$cs."\n";
            $contents = $contents . "of the site " . $sitename . " has been added to the system and set to disabled.\n";
            $contents = $contents . "\n\nPlease report any issue to the support list.\nBest regards.";
            $subject  = "[SITE STATUS CHANGE] Site status change for " . $sitename;

            if($contents != "" && $to != "") {
              mail($to, $subject, $contents, $from_header);
              if (isset($HTTP_REFERER)) header("Location: $HTTP_REFERER");
            }
          } else {
            $siteenabled = 1;
          }
        }
        $siteqry .= ", status=" . $siteenabled;
        $res = db_query($siteqry);
        $res = db_query("SELECT ref, cs, cename, name, osname, osversion, osrelease, arch, swarea, status, fstype FROM site WHERE cs=" . db_quote($cs,'rw'));
        $row = mysqli_fetch_row($res);
      } else {
        // Update the site info, if needed
        $upd_array = array();
        if (isset($cename) && $cename != "" && $cename != "-" && $row[2] != $cename) array_push($upd_array, "cename=".db_quote($cename,'rw'));
        if (isset($name)   && $name   != "" && $name   != "-" && $row[3] != $name)   array_push($upd_array, "name=".db_quote($name,'rw'));
        if (isset($osname) && $osname != "" && $osname != "-" && $row[4] != $osname) array_push($upd_array, "osname=".db_quote($osname,'rw'));
        if (isset($osver)  && $osver  != "" && $osver  != "-" && $row[5] != $osver)  array_push($upd_array, "osversion=".db_quote($osver,'rw'));
        if (isset($osrel)  && $osrel  != "" && $osrel  != "-" && $row[6] != $osrel)  array_push($upd_array, "osrelease=".db_quote($osrel,'rw'));
        if (isset($arch)   && $arch   != "" && $arch   != "-" && $row[7] != $arch)   array_push($upd_array, "arch=".db_quote($arch,'rw'));
        if ($upd_array) {
          $upd_query = "UPDATE site SET ".join(",",$upd_array)." WHERE ref=".db_int($row[0],0);
          $res = db_query($upd_query,"rw",TRUE);
        }
      }
      $siteref     = $row[0];
      $swarea      = $row[8];
      $siteenabled = $row[9];
      $fstype      = $row[10];
  
      if ($siteenabled == 1 or ($siteenabled == 0 and isset($_POST["interactive"]))) {
        // Process the user
        $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
        $row = mysqli_fetch_row($res);
        if (!$row) {
          $res = db_query("INSERT INTO user SET name=" . db_quote($_POST["user"],'rw') . ", dn=" . db_quote(getenv("SSL_CLIENT_S_DN"),'rw') . ", email=" . db_quote($_POST["email"],'rw'),"rw",TRUE);
          $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
          $row = mysqli_fetch_row($res);
        }
        $userref = $row[0];
  
        // Process the release data
        $query = "SELECT sw_physicalpath, sw_versionarea FROM release_data WHERE name=".db_quote($_POST["rel"],'ro');
        $res = db_query($query);
        $row = mysqli_fetch_row($res);
        if ($row) {
          $physpath = $row[0];
          $versarea = $row[1];
        } else {
          $physpath = "none";
          $versarea = "none";
        }

        // Process the release
        if ($_POST["rel"] == "other") {
          $relname = $_POST["relnew"];
        } else {
          $relname = $_POST["rel"];
        }
        $res = db_query("SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'rw') . " AND sitefk=" . db_int($siteref,0));
        $row = mysqli_fetch_row($res);
        if (!$row) {
          $res = db_query("INSERT INTO release_stat SET name=" . db_quote($relname,'rw') . ", sitefk=" . db_int($siteref,0) . ", userfk=" . db_int($userref,0) . ", status='pending', date=" . db_quote($date,'rw'),"rw",TRUE);
          $res = db_query("SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'rw') . " AND sitefk=" . db_int($siteref,0));
          $row = mysqli_fetch_row($res);
        }
        $relref = $row[0];
  
        // Process the bdii
        $res = db_query("SELECT ref FROM bdii WHERE hostname=" . db_quote($_POST["bdii"],'ro'),'ro');
        $row = mysqli_fetch_row($res);
        $bdiiref = $row[0];

        // Process the type
        $res = db_query("SELECT ref FROM request_type WHERE description=" . db_quote($_POST["reqtype"],'ro'),'ro');
        $row = mysqli_fetch_row($res);
        $typeref = $row[0];

        // Transaction ID
        $guid = guid();

        // Process the status
        if (isset($_POST["autoinstall"]) && $_POST["autoinstall"] == "yes") {
            $statusfk = 7;  // autorun
        } else {
            $statusfk = 1;  // not assigned
        }
        if (isset($_POST["status"]) && $_POST["status"] != "") {
          $res = db_query("SELECT ref FROM request_status WHERE description=" . db_quote($_POST["status"],'ro'),'ro');
          $row = mysqli_fetch_row($res);
          if ($row) {
            $statusfk = $row[0];
          }
        }

        if (!isset($_POST['multi']) ||
           (isset($_POST['multi']) && $_POST['multi']=='n')) {
          // Check for duplicate requests on the same CE
          $query = "SELECT id, request_date FROM request WHERE sitefk=" . db_int($siteref,0);
          $query = $query . " AND typefk=" . db_int($typeref,0);
          $query = $query . " AND relfk=" . db_int($relref,0);
          $query = $query . " AND statusfk NOT IN (4,10)";
          $resdp = db_query($query);
          $row   = mysqli_fetch_row($resdp);
          if (!$row and isset($swarea) and $fstype != "cvmfs" and $_POST["reqtype"] != "remove-tag" and $_POST["reqtype"] != "publish-tag") {
            // Check for concurrent requests on the same exp soft area
            $query = "SELECT id, request_date"
                    . " FROM request, release_stat, release_data, site"
                   . " WHERE release_stat.ref = request.relfk"
                     . " AND site.ref = release_stat.sitefk"
                     . " AND swarea=" . db_quote($swarea,'ro')
                     . " AND release_stat.name = release_data.name"
                     . " AND sw_physicalpath=" . db_quote($physpath,'ro')
                     . " AND request.statusfk NOT IN (3,4,5,6,10)"
                     . " AND request.typefk <> 3";
            if (isset($versarea)) $query = $query . " AND sw_versionarea=" . db_quote($versarea,'ro');
            $rescr = db_query($query);
            $row   = mysqli_fetch_row($rescr);
          }
        } else {
          unset($row);
        }

        if (!isset($row) or !$row) {
          // Insert the data
          $query =  "INSERT INTO request SET id=" . db_quote($guid,'rw') . ", bdiifk=" . db_int($bdiiref,0);
          $query = $query . ", sitefk=" . db_int($siteref,0) . ", relfk=" . db_int($relref,0);
          $query = $query . ", typefk=" . db_int($typeref,0) . ", userfk=" . db_int($userref,0);
          $query = $query . ", statusfk=" . db_int($statusfk,0) . ", request_date=" . db_quote($date,'rw');
          $query = $query . ", user_comments=" . db_quote($_POST["comments"] ?? '','rw');
          if (isset($_POST["forcerun"])) $query = $query . ", force_run=1";
          $res   = db_query($query,"rw",TRUE);
        } else {
          $guid  = "-" . $row[0]; 
        }
      } else {
        $guid = "+" . $siteref;
      }
    } else {
      // No cs specified, error
      $guid  = "-"; 
    }
    return $guid;
  }
  if (!isset($_POST['quiet'])) {
?>
<HTML>
<HEAD>
<TITLE>Request An Install for the <?php echo $LJSFi_VO; ?> software</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
</HEAD>
<BODY>
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
        <h3><?php echo $LJSFi_VO; ?> Software Installation Request</h3>

<?php
  }
  $_POST["validated"] = "no";
  $pin = check_pin();
  $requirementsok = check_requirements();
  $reqby          = check_required();
  if ($requirementsok == 1) {
    if ($reqby == "" || ($reqby != "" && $_POST['reqtype'] != 'removal')) {
      if ($pin == 0 || ($pin != 0 && $_POST['reqtype'] != 'removal' && $_POST['reqtype'] != 'cleanup')) {
        $guid = insert_data();
        if ($guid == "-") {
          if (!isset($_POST['quiet'])) echo ("<H2><FONT COLOR=red>Wrong parameters</FONT></H2>");
        } elseif ($guid[0] == "-") {
          $guidlen = strlen($guid);
          $guid    = substr($guid,1,$guidlen-1);
          if (!isset($_POST['quiet'])) {
            echo ("<H2><FONT COLOR=red>A previous or concurrent request on the same software area is active, ");
            echo ("please retry later.");
            echo ("<BR>Please follow ");
            echo ("<A HREF='req.php?id=" . $guid . "'>this link</A> ");
            echo ("for further info or to restart the tasks.</FONT></H2>");
          }
        } elseif ($guid[0] == "+") {
          if (!isset($_POST['quiet'])) {
            $cs = "UNKNOWN";
            if (isset($_POST["cs"])) $cs = $_POST["cs"];
            $guidlen = strlen($guid);
            $siteref = substr($guid,1,$guidlen-1);
            echo ("<H2><FONT COLOR=red>The resource <A HREF='sitedef.php?mode=update&sitesrc=".$siteref."'>".$cs."</A> is disabled.</FONT></H2>");
          }
        } else {
          if (!isset($_POST['quiet'])) {
            echo ("Request submitted. You'll be contacted back in case of problems.");
            echo ("<H2><FONT COLOR=red>Your request ID is: " . $guid);
            echo ("<BR>You may check the status of your request by following ");
            echo ("<A HREF='req.php?id=" . $guid . "'>this link</A>.</FONT></H2>");
          } else {
            if (!isset($_POST['noout'])) {
              echo "INSTALL SERVER> request id ".$guid." submitted\n";
            } else {
              echo $guid;
            }
          }
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
          $link=dirname($_SERVER['FULL_URL']) . "/req.php?id=" . $guid;
          send_mail($link,$guid);
        }
      } else {
        if (!isset($_POST['quiet'])) echo ("You cannot remove a pinned release.");
      }
    } else {
      if (!isset($_POST['quiet'])) echo ("This release is required by ".$reqby.". Please remove the dependencies first.");
    }
  } else {
    if (!isset($_POST['quiet'])) echo ("The selected software requires a release which is not installed in the site.");
  }
  if (!isset($_POST['quiet'])) {
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
<?php } ?>
