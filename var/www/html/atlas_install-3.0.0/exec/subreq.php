<?php

  require("guid.php");
  require("db.php");
  require("config.php");
  function send_mail($link,$guid) {
    global $LJSFi_VO,$LJSFi_email;
    $infoqry = "SELECT rt.field, rl.name, s.cename, s.name, r.admin_comments"
             . "  FROM request_type rt, request r, release_stat rl, site s"
             . " WHERE r.relfk=rl.ref"
             . "   AND r.sitefk=s.ref"
             . "   AND r.typefk=rt.ref"
             . "   AND r.id='".$guid."'";
    $result = db_query($infoqry,"ro");
    $info=mysqli_fetch_row($result);
    if (!isset($info[3])) { $info[3]=""; }
    $emailqry = "SELECT u.email FROM subscription s, user u"
              . " WHERE s.userfk=u.ref"
              . "   AND (s.sitename='".$info[3]."' OR s.sitename='*')"
              . "   AND u.email IS NOT NULL)";
    $result = db_query($emailqry,"ro");
    $to = "";
    while ($email = mysqli_fetch_array($result)) {
      if ($to != "") {
        $to .= ','.$email[0];
      } else {
        $to  = $email[0];
      }
    }

    $from_header = "From: nobody <".$LJSFi_email.">";
    $contents = "Dear ".$LJSFi_VO." manager,\n";
    $contents = $contents . "a request for installation/manteinance has been submitted by\n";
    $contents = $contents . $_POST["user"] . " <" . $_POST["email"] . ">\n";
    $contents = $contents . "on " . date("l, F dS Y, H:m:s") . "\n";
    $contents = $contents . "The request concerns an operation on release '". $_POST["rel"] . "'";
    if ($_POST["rel"] == 'other') $contents = $contents . " (" . $_POST["relnew"] . ").";
    $contents = $contents . "\n";
    $contents = $contents . "on the CE " . $_POST["cename"] . ", indentified by\n";
    $contents = $contents . $_POST["cs"] . "\n";
    $contents = $contents . "Additional comments are reported below:\n------------\n" . $_POST["comments"] . "\n------------\n";
    $contents = $contents . "Additional info is available from the URL below:\n\n" . $link;
    $contents = $contents . "\n\nPlease process this request as soon as possible.\nBest regards.";
    $subject  = "Installation request for release " . $_POST["rel"] . " at " . $_POST["cename"];
 
    if($contents != "") {
      mail($to, $subject, $contents, $from_header);
      header("Location: $HTTP_REFERER");
    } else {
      print("Error, no mail submitted!");
    }
  }

  function check_pin() {
    $pinval=0;
    $qry = "SELECT release_stat.pin "
            ."FROM release_stat, site "
           ."WHERE release_stat.sitefk = site.ref "
             ."AND site.cename = ".db_quote($_POST["cename"],'ro')." "
             ."AND release_stat.name=".db_quote($_POST["rel"],'ro')
             ."AND release_stat.status<>'removed'";
    $res = db_query($qry,"ro");
    $row = mysqli_fetch_row($res);
    if ($row) $pinval = $row[0];
    return $pinval;
  }

  function check_requirements() {
    // Process the requirements
    $requirementsok = 1;   // By default the requirements are satisfied
    $qry = "SELECT rd.requires FROM release_data rd WHERE rd.name=".db_quote($_POST["rel"],'ro')." LIMIT 1";
    $res = db_query($qry,"ro");
    while ($row = mysqli_fetch_array($res)) {
      if (isset($row[0]) && $row[0] != "") {
        $requires = $row[0];
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
    return $requirementsok;
  }

  function check_required() {
    // Process the requirements
    $requiredby = "";   // By default the release is not required by anybody
    $qry = "SELECT rd.name FROM release_data rd WHERE rd.requires=".db_quote($_POST["rel"],'ro');
    $res = db_query($qry,"ro");
    while ($row = mysqli_fetch_array($res)) {
      if (isset($row[0]) && $row[0] != "") {
        $relnum = $row[0];
        if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
          $qryreq = "SELECT release_stat.status FROM release_stat, site"
                  . " WHERE release_stat.name = '".$relnum."'"
                  .   " AND release_stat.sitefk = site.ref"
                  .   " AND (site.name = ".db_quote($_POST["sitename"],'ro');
          if (isset($_POST["cs"]) && $_POST["cs"] != "") {
            $qryreq .= " OR site.cs = ".db_quote($_POST["cs"],'ro');
          }
          $qryreq .= ")";
          $resreq = db_query($qryreq,"ro");
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
    return $requiredby;
  }

  function set_site_info() {
    $sres = db_query("SELECT name,cs FROM site WHERE cename=" . db_quote($_POST['cename'],'ro') . " ORDER BY ref DESC LIMIT 1","ro");
    $srow = mysqli_fetch_row($sres);
    if ($srow) {
      if (!isset($_POST["sitename"])) $_POST["sitename"]=$srow[0];
      if (!isset($_POST["cs"])) $_POST["cs"]=$srow[1];
    }
    return 0;
  }

  function insert_data() {
    // Current date/time
    $date = date("Y-m-d H:i:s");

    // Process the site
    $res = db_query("SELECT ref FROM site WHERE cs=" . db_quote($_POST['cs'],'ro'),"ro");
    $row = mysqli_fetch_row($res);
    if (!$row) {
      print "INSTALL SERVER> Unknown site. Please contact the installation team (".$LJSFi_contacts.").\n";
      exit;
    }
    $siteref = $row[0];

    // Process the user
    $ssluserdetails = ereg_replace("/CN=proxy", "", getenv("SSL_CLIENT_S_DN"));
    $ssluserdetails = preg_replace('/\/CN=[0-9]+/','',$ssluserdetails);
    $res = db_query("SELECT ref,name,email FROM user WHERE dn=" . db_quote($ssluserdetails,'ro') . " ORDER BY ref DESC","ro");
    $row = mysqli_fetch_row($res);
    if (!$row) {
      print "INSTALL SERVER> DN: ".$ssluserdetails."\n";
      print "INSTALL SERVER> CN: ".$sslusername."\n";
      print "INSTALL SERVER> role: ".$role."\n";
      print "INSTALL SERVER> Unknown user. Please register to the installation system first.\n";
      exit;
    }
    $userref = $row[0];
    $_POST["user"] = $row[1];
    $_POST["email"] = $row[2];

    // Process the release
    if ($_POST["rel"] == "other") {
      $relname = $_POST["relnew"];
    } else {
      $relname = $_POST["rel"];
    }
    $res = db_query("SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'ro') . " AND sitefk=" . db_int($siteref,1),"ro");
    $row = mysqli_fetch_row($res);
    if (!$row) {
      $res = db_query("INSERT INTO release_stat SET name=" . db_quote($relname,'rw') . ", sitefk=" . db_int($siteref,1) . ", userfk=" . db_int($userref,1) . ", status='pending', date=" . db_quote($date,'rw'));
      $res = db_query("SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'rw') . " AND sitefk=" . db_int($siteref,1));
      $row = mysqli_fetch_row($res);
    }
    $relref = $row[0];

    // Process the bdii
    $res = db_query("SELECT ref FROM bdii WHERE hostname=" . db_quote($_POST["bdii"],'ro'),"ro");
    $row = mysqli_fetch_row($res);
    $bdiiref = $row[0];

    // Process the type
    $res = db_query("SELECT ref FROM request_type WHERE description=" . db_quote($_POST["reqtype"],'ro'),"ro");
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
        $res = db_query("SELECT ref FROM request_status WHERE description=" . db_quote($_POST["status"],'ro'),"ro");
        $row = mysqli_fetch_row($res);
        if ($row) {
            $statusfk = $row[0];
        }
    }

    if (!isset($_POST['multi']) ||
       (isset($_POST['multi']) && $_POST['multi']=='n')) {
        // Check for duplicate requests
        $query = "SELECT id, request_date FROM request WHERE sitefk=" . db_int($siteref,1);
        $query = $query . " AND typefk=" . db_int($typeref,1);
        $query = $query . " AND relfk=" . db_int($relref,1);
        $query = $query . " AND statusfk <> 4";
        $resdp = db_query($query,"ro");
        $row   = mysqli_fetch_row($resdp);
    } else {
        unset($row);
    }

    if (!$row) {
        // Insert the data
        $query =  "INSERT INTO request SET id=" . db_quote($guid,'rw') . ", bdiifk=" . db_int($bdiiref,1);
        $query = $query . ", sitefk=" . db_int($siteref,1) . ", relfk=" . db_int($relref,1);
        $query = $query . ", typefk=" . db_int($typeref,1) . ", userfk=" . db_int($userref,1);
        $query = $query . ", statusfk=" . db_int($statusfk,1) . ", request_date=" . db_quote($date,'rw');
        if (isset($_POST["comments"]))
          $query .= ", user_comments=" . db_quote($_POST["comments"],'rw');
        $res   = db_query($query);
    } else {
        $guid  = "-" . $row[0]; 
    }

    return $guid;
  }


  // Check the credentials
  $ssluserdetails = ereg_replace("/CN=proxy", "", getenv("SSL_CLIENT_S_DN"));
  $ssluserdetails = preg_replace('/\/CN=[0-9]+/','',$ssluserdetails);
  $sslvalid = getenv("SSL_CLIENT_V_REMAIN");
  if ($ssluserdetails == "" || $sslvalid == 0) {
    print "INSTALL SERVER> Bad credentials\n";
    exit;
  }
  $_POST["validated"] = "no";

  // Get the site name, if not yet available
  if (!isset($_POST['sitename']) || !isset($_POST['cs'])) {
    set_site_info();
    if (!isset($_POST['cs'])) {
      print "INSTALL SERVER> Cannot find resource name for CE '".$_POST['cename']."'\n";
      exit;
    }
  }

  // Default BDII
  if (!isset($_POST['bdii'])) $_POST['bdii'] = 'exp-bdii.cern.ch';

  // Check pins and requirements
  $pin            = check_pin();
  $requirementsok = check_requirements();
  $reqby          = check_required();
  if ($requirementsok == 1) {
    if ($reqby == "" || ($reqby != "" && $_POST['reqtype'] != 'removal' && $_POST['reqtype'] != 'cleanup')) {
      if ($pin == 0 || ($pin != 0 && $_POST['reqtype'] != 'removal' && $_POST['reqtype'] != 'cleanup')) {
        $guid = insert_data();
        $_SERVER['FULL_URL'] = 'http';
        if($_SERVER['HTTPS']=='on') {
          $_SERVER['FULL_URL'] .=  's';
        }
        $_SERVER['FULL_URL'] .=  '://';
        $ip = gethostbyname($_SERVER['HTTP_HOST']);
        $hn = gethostbyaddr($ip);
        if($_SERVER['SERVER_PORT']!='80' && $_SERVER['SERVER_PORT']!='443') {
          $_SERVER['FULL_URL'] .=  $hn.':'.$_SERVER['SERVER_PORT'].$_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'];
        } else {
          $_SERVER['FULL_URL'] .=  $hn.$_SERVER['SCRIPT_NAME'];
        }
        $_SERVER['FULL_URL'] = ereg_replace("/exec/","/protected/",$_SERVER['FULL_URL']);
        if ($guid[0] == "-") {
          $guidlen = strlen($guid);
          $guid    = substr($guid,1,$guidlen-1);
          $link=dirname($_SERVER['FULL_URL']) . "/req.php?id=" . $guid;
          echo ("INSTALL SERVER> Another request has been already submitted with the same parameters.\n");
          echo ("INSTALL SERVER> See " . $link . " for further details.\n");
        } else {
          echo "INSTALL SERVER> request id ".$guid." submitted\n";
          send_mail($link,$guid);
        }
      } else {
        echo ("INSTALL SERVER> You cannot remove a pinned release.\n");
      }
    } else {
      echo ("INSTALL SERVER> This release is required by ".$reqby.". Please remove the dependencies first.\n");
    }
  } else {
    echo ("INSTALL SERVER> The selected software requires a release which is not installed in the site.\n");
  }
?>
