<?php

  require("db.php");
  require("guid.php");
  require("config.php");
  require("user_info.php");
  require ("$LJSFi_PATH/access_log.php");
  function logPageURL($hn) {
      $pageURL = 'http';
      if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
      $pageURL .= "://".$hn.$_SERVER["REQUEST_URI"];
      return $pageURL;
   }
?>
<?php if (!isset($_REQUEST['mode'])) { ?>
<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation Logfile Access</TITLE>
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
    <div id="site_content">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Installation Logfile Access</h3>

<TABLE id='log_tbl' border="1" cellspacing="0" rules="groups" width="100%" summary="<?php echo $LJSFi_VO; ?> Installation Logfile Access">
<TR><TD width="20">&nbsp&nbsp</TD><TD class="graytable">
<?php } ?>
<?php
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    $role="";
  } else {
    $adminfk=$user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
    $priv_view=$user_info[0]['priv_view'];
  }

  if (!isset($priv_view)) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>Unknown user. Please <A HREF='user.php'>register to LJSFi.</A></B></FONT>\n");
  } elseif ($enabled == 0) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>Your user is disabled. Please contact the LJSFi administrator.</B></FONT>\n");
  } elseif ($priv_view != 1) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>");
    echo ("You don't have permissions to view logs.<BR>\n");
    echo ("Please check your registration and <A HREF='user.php'>ask for the view privilege</A>\n");
    echo ("</B></FONT>");
  } else {
    // Start the form
    # Get the ID
    if (isset($_POST['id']) && $_POST['id'] != '') $jid=db_int($_POST['id'],1);
    if (isset($_GET['id'])  && $_GET['id'] != '')  $jid=db_int($_GET['id'],1);

    if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'upload') {
      if (isset($_FILES['file']['name']) && isset($_POST['jobid'])) {
        # Check the job id
        $query   = "SELECT job.ref, job.logfile, job.logfragments FROM job WHERE id=".db_quote($_POST['jobid'],'rw');
        $checkid = db_query($query);
        $numrows = mysqli_num_rows($checkid);
        if ($numrows > 0) {
          # OK, this is an existing job, let's go ahead
          $row             = mysqli_fetch_row($checkid);
          $logfile         = $_FILES['file']['name'];
          if (isset($row[1]) && $row[1] != "") {
            # This logfile is already existing
            $path_list = preg_split("/\//",$row[1]);
            $fpath_container = $path_list[0];
            $guid            = $path_list[1];
            $logfile_old     = $path_list[2];
            $logfragments    = $row[2];
          } else {
            # New logfile
            $fpath_container = date('Y-m-d');
            $guid            = guid();
            $logfragments    = 0;
          }
          if (!isset($_REQUEST["fragment"]) || (isset($_REQUEST["fragment"]) && $_REQUEST["fragment"] > $logfragments)) {
            # Now build the file names and create any missing path
            $fpath = $fpath_container."/".$guid."/";
            $fname = $upload_path.$fpath.$logfile;
            if (isset($logfile_old)) $fname_old = $upload_path.$fpath.$logfile_old;
            if (!file_exists($upload_path.$fpath_container))
              mkdir($upload_path.$fpath_container, 0777);
            if (!file_exists($upload_path.$fpath))
              mkdir($upload_path.$fpath, 0777);

            # Make a backup copy of the old logfile, if exists and has the same name as the new one
            if (isset($fname_old) && file_exists($fname_old) && $fname == $fname_old) {
              if (file_exists("${fname_old}.bak")) unlink("${fname_old}.bak");
              rename($fname_old,"${fname_old}.bak");
            }
            # Move the uploaded file to the logfile destination
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    echo ("INSTALLSERVER> - file too large (limit of ".ini_get('upload_max_filesize').")\n");
                    break;
                case UPLOAD_ERR_PARTIAL:
                    echo ("INSTALLSERVER> - file upload was not completed\n");
                    break;
                case UPLOAD_ERR_NO_FILE:
                    echo ("INSTALLSERVER> - zero-length file uploaded\n");
                    break;
                default:
                    echo ("INSTALLSERVER> - internal error ".$_FILES['file']['error']."\n");
                    break;
            }
            if (!move_uploaded_file($_FILES['file']['tmp_name'],$fname)) {
              echo ("INSTALLSERVER> Error while saving the logfile ".$_FILES['file']['tmp_name']." to ".$fname);
              # Restore the backup copy of the old logfile, if exists
              if (isset($fname_old) && file_exists("${fname_old}.bak")) {
                if (file_exists($fname_old)) unlink($fname_old);
                rename("${fname_old}.bak",$fname_old);
              }
              # Exit with error
              die();
            }
            # Merge the logfiles, if required
            if (isset($_REQUEST["fragment"]) && intval($_REQUEST["fragment"]) > $row[2]) {
              $files = array();
              if (file_exists("${fname_old}.bak")) {
                array_push($files,"${fname_old}.bak");
              } elseif (file_exists("$fname_old") && $fname_old != $fname) {
                array_push($files, $fname_old);
              }
              if (count($files) > 0) {
                rename($fname,"${fname}.new");
                $gzoutfile = gzopen($fname, "w9");
                if (file_exists("${fname}.new")) array_push($files, "${fname}.new");
                foreach ($files as $infile) {
                  $gzinfile = gzopen($infile, "r");
                  if ($gzinfile) {
                    while (!gzeof($gzinfile)) {
                      $chunk = gzread($gzinfile,4096);
                      gzwrite($gzoutfile, $chunk);
                    }
                    gzclose($gzinfile);
                    unlink($infile);
                  } else {
                    echo ("INSTALLSERVER> Error reading ${infile}");
                  }
                }
              }
              gzclose($gzoutfile);
              $logfragments = intval($_REQUEST["fragment"]);
            }
            # Remove the old logfiles, if exist
            if (isset($fname_old)) {
              if (file_exists("${fname_old}.bak")) unlink("${fname_old}.bak");
              if (file_exists($fname_old) && $fname != $fname_old) unlink($fname_old);
            }
            # Update the database
            $hostname = gethostname();
            if (!isset($hostname) || $hostname == "") { $hostname = "localhost"; }
            $query  = "SELECT ref FROM log_host WHERE name='".$hostname."'";
            $result = db_query($query);
            $row = mysqli_fetch_row($result);
            if (!$row) {
              $query  = "INSERT INTO log_host SET name='".$hostname."'";
              db_query($query,"rw",True);
              $query  = "SELECT ref FROM log_host WHERE name='".$hostname."'";
              $result = db_query($query);
              $row = mysqli_fetch_row($result);
            }
            if ($row) {
              $loghostfk = $row[0];
              $query  = "UPDATE job SET loghostfk=".$loghostfk.", logfile=".db_quote($fpath.$logfile,'rw').", logfragments=".db_int($logfragments,0)." WHERE id=".db_quote($_POST['jobid'],'rw');
              $result = db_query($query);
              # Print the result
              if(isset($result)) echo $fpath.$logfile;
            }
          } else {
            echo ("INSTALLSERVER> Last fragment saved is ${logfragments}, ignoring request");
          }
        }
      }
    } elseif (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'logreq') {
      # List the logfiles explicitly requested by users
      $query  = "SELECT job.ref,job.logfragments,job.id"
               . " FROM job"
              . " WHERE logfile IS NOT NULL"
                . " AND retrieval_time IS NULL"
                . " AND validationfk=(SELECT ref FROM validation WHERE description='pending')";
      if (isset($jid)) $query .= " AND ref=".db_int($jid,1);
      if (isset($_POST['jobid']) && $_POST['jobid'] != "") $query .= " AND id=".db_quote($_POST['jobid'],'ro');
      $result = db_query($query);
      $first  = True;
      while ($row = mysqli_fetch_row($result)) {
        if (!$first) echo "\n";
        echo "$row[0],$row[1],$row[2]";
        $first = False;
      }
    } else {
      # Fetch the record
      if (isset($jid)) {
        $query  = ("SELECT job.logfile, log_host.name FROM job JOIN log_host ON job.loghostfk=log_host.ref WHERE job.ref=".$jid);
        $result = db_query($query);
        $row = mysqli_fetch_row($result);
        $logfile = $row[0];
        $loghost = $row[1];
        $hostname = gethostname();
        if ($loghost != "localhost" && $loghost != $hostname) {
            $logurl = logPageURL($loghost);
            $redir = "location: ".$logurl."?id=".$jid;
            header($redir);
            exit;
        }
        $fname = $upload_path.$logfile;
        if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'download') {
          header("Content-Type: text/plain");
          header("Content-Disposition: attachment; filename=\"".ereg_replace(".gz",".txt",basename($logfile))."\"");
          //header("Content-Length: " . filesize($file_path));
        }
        if ($logfile != '' && file_exists($fname)) {
          $gzfile = gzopen($fname, "r");
          while (!gzeof($gzfile)) {
            $chunk = gzread($gzfile,4096);
            if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'download') {
              echo $chunk;
            } else {
              echo nl2br(htmlentities($chunk));
            }
          }
          gzclose($gzfile);
        } else {
          # List the logfiles explicitly requested by users
          $query  = "SELECT job.ref,job.logfile"
                   . " FROM job"
                  . " WHERE retrieval_time IS NULL"
                    . " AND validationfk=(SELECT ref FROM validation WHERE description='pending')"
                    . " AND ref=".db_int($jid,1);
          $logres = db_query($query);
          $numrows = mysqli_num_rows($logres);
          if ($numrows == 1) {
            $logrow = mysqli_fetch_row($logres);
            if (isset($logrow[1])) {
              if (!isset($_REQUEST['mode'])) echo ('<center>A partial logfile request has been already sent to the system. Please check again in a few minutes</center>');
            } else {
              $fpath_container = date('Y-m-d');
              $guid            = guid();
              $fpath           = $fpath_container."/".$guid."/";
              $logfile         = "install.log.gz";
              $logquery  = "UPDATE job SET logfile=".db_quote($fpath.$logfile,'rw')." WHERE ref=".db_int($jid,1);
              $logupdres = db_query($logquery);
              if (!isset($_REQUEST['mode'])) echo ('<center>A partial logfile request has been sent to the system. Please check back in a few minutes</center>');
            }
          } else {
            if (!isset($_REQUEST['mode'])) echo ('<center>No logfile available for this job</center>');
          }
        }
      } else {
        if (!isset($_REQUEST['mode'])) echo ("<center>No ID specified</center>");
      }
    }
  }
?>
<?php if (!isset($_REQUEST['mode'])) { ?>
</TD><TD width="20">&nbsp&nbsp</TD></TR>
<TR><TD colspan="3" height="30">&nbsp</TD></TR>
</TABLE>
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
