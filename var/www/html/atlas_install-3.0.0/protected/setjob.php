<?php

  require("guid.php");
  require("db.php");

  function set_job_data() {
    // Configuration data
    require("config.php");

    // Current date/time
    $date = date("Y-m-d H:i:s");

    // Generic info
    if (isset($_POST["cs"]) && $_POST["cs"] != "") $cs = $_POST["cs"];
    if (isset($_POST["jobname"]) && $_POST["jobname"] != "") $jobname = addslashes($_POST["jobname"]);
    if (isset($_POST["status"]) && $_POST["status"] != "") $status = addslashes($_POST["status"]);
    if (isset($_POST["rel"]) && $_POST["rel"] != "") $relname = $_POST["rel"];
    if (isset($_POST["validation"]) && $_POST["validation"] != "") {
      $validation = addslashes($_POST["validation"]);
    } else {
      $validation = "pending";
    }

    // Process the site
    if (isset($cs)) {
      $res = db_query("SELECT ref FROM site WHERE cs=" . db_quote($cs,'rw'));
      $row = mysqli_fetch_row($res);
      if ($row) {
        $siteref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> ".$cs." has id ".$siteref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Unknown resource ".$cs."\n";
      }
    }

    // Process the user
    if (isset($_POST["user"]) && $_POST["user"] != "") {
      $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) {
        $res = db_query("INSERT INTO user SET name=" . db_quote($_POST["user"],'rw') . ", dn=" . db_quote(getenv("SSL_CLIENT_S_DN"),'rw') . ", email=" . db_quote($_POST["email"],'rw'));
        $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
        $row = mysqli_fetch_row($res);
      }
      $userref = $row[0];
      if (!isset($_POST["quiet"])) echo "INSTALL SERVER> User ".$_POST["user"]." has id ".$userref."\n";
    }

    // Process the release
    if (isset($relname) && isset($siteref)) {
      $res = db_query("SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'rw') . " AND sitefk=" . db_int($siteref,1));
      $row = mysqli_fetch_row($res);
      if ($row) {
        $relref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Release ".$relname." has id ".$relref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Unknown release ".$relname." in site\n";
      }
    }

    // Process the validation code
    if (isset($validation)) {
      $res = db_query("SELECT ref FROM validation WHERE description=" . db_quote($validation,'rw'));
      $row = mysqli_fetch_row($res);
      if ($row) {
        $validationref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Validation code ".$validation." has id ".$validationref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Unknown validation code ".$validation."\n";
      }
    }

    // Generate a Job ID if none is supplied
    if (isset($_POST["jobid"]) && $_POST["jobid"] != "") {
      $jobid = $_POST["jobid"];
    } else {
      $jobid = guid();
    }

    // Process the jdl
    if (isset($_POST["jdlname"]) && $_POST["jdlname"] != "") {
      $res = db_query("SELECT ref FROM jdl WHERE name=" . db_quote($_POST["jdlname"],'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row && isset($_POST["jdltype"]) && isset($userref) && isset($relref)) {
        $query = "INSERT INTO jdl SET name=" . db_quote($_POST["jdlname"],'rw')
                                 . ", type=" . db_quote($_POST["jdltype"],'rw')
                                 . ", userfk=" . db_int($userref,1)
                                 . ", relfk=" . db_int($relref,1);
        if (isset($_POST["jdldata"]) && $_POST["jdldata"] != "")
          $query .= ", content=" . db_quote($_POST["jdldata"],'rw');
        $res = db_query($query);
        $res = db_query("SELECT ref FROM jdl WHERE name=" . db_quote($_POST["jdlname"],'rw'));
        $row = mysqli_fetch_row($res);
      }
      if ($row) {
        $jdlref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> JDL ".$_POST["jdlname"]." has id ".$jdlref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot find JDL ".$_POST["jdlname"]."\n";
      }
    }


    // Handle the job data
    $res = db_query("SELECT ref FROM job WHERE id=" . db_quote($jobid,'rw'));
    $row = mysqli_fetch_row($res);
    if (!$row) {
      // Insert the job data
      if (   isset($relref) && isset($userref) && isset($jdlref)
          && isset($siteref) && isset($jobname) && isset($status)) {
        $query =  "INSERT INTO job SET id=" . db_quote($jobid,'rw')
                              . ", userfk=" . db_int($userref,1)
                              . ", name="  . db_quote($jobname,'rw')
                              . ", jdlfk="  . db_int($jdlref,1)
                              . ", status=" . db_quote($status,'rw')
                              . ", submission_time=" . db_quote($date,'rw')
                              . ", reach_time=" . db_quote($date,'rw')
                              . ", sitefk=" . db_int($siteref,1);
        if (isset($_POST["reqid"]) && $_POST["reqid"] != "")
          $query .= ", requestfk=" . db_quote($_POST["reqid"],'rw');
        if (isset($_POST["jobinfo"]) && $_POST["jobinfo"] != "")
          $query .= ", info=" . db_quote($_POST["info"],'rw');
        if (isset($validationref)) $query .= ", validationfk=" . db_int($validationref,1);
        if (isset($_POST["statreason"]) && $_POST["statreason"] != "")
          $query .= ", status_reason=" . db_quote($_POST["statreason"],'rw');
        $res = db_query($query);
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> job id ".$jobid." INSERT successful\n";
      } else {
        $jobid = NULL;
        if (!isset($_POST["quiet"]) && !isset($relref))  echo "INSTALL SERVER> No release data\n";
        if (!isset($_POST["quiet"]) && !isset($userref)) echo "INSTALL SERVER> No user data\n";
        if (!isset($_POST["quiet"]) && !isset($siteref)) echo "INSTALL SERVER> No site data\n";
        if (!isset($_POST["quiet"]) && !isset($jobname)) echo "INSTALL SERVER> No job name\n";
        if (!isset($_POST["quiet"]) && !isset($jdlref))  echo "INSTALL SERVER> No JDL data\n";
        if (!isset($_POST["quiet"]) && !isset($status))  echo "INSTALL SERVER> No job status\n";
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot insert data\n";
      }
    } else {
      // Update the job data
      if (isset($status) && isset($validationref)) {
        // Build the update query
        $query =  "UPDATE job SET status=" . db_quote($status,'rw')
                     . ", retrieval_time=" . db_quote($date,'rw')
                       . ", validationfk=" . db_int($validationref,1);
        if (isset($_POST["statreason"]) && $_POST["statreason"] != "")
          $query .= ", status_reason=" . db_quote($_POST["statreason"],'rw');
        if (isset($_POST["jobexit"]) && $_POST["jobexit"] != "")
          $query .= ", exit_code=" . db_int($_POST["jobexit"],-2147483648,2147483647);
        if (isset($_POST["jobinfo"]) && $_POST["jobinfo"] != "")
          $query .= ", info=" . db_quote(trim($_POST["jobinfo"]),'rw');

        // Handle the logfiles
        if (isset($_FILES['logfile']['name'])) {
          $logid = guid();
          $logfile         = $_FILES['logfile']['name'];
          $fpath_container = date('Y-m-d');
          $fpath           = $fpath_container."/".$logid."/";
          $fname           = $upload_path.$fpath.$logfile;
          if (!file_exists($upload_path.$fpath_container)) {
            if (!mkdir($upload_path.$fpath_container, 0777)) {
              if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot create ".$upload_path.$fpath_container;
              die();
            }
          }
          if (!file_exists($upload_path.$fpath)) {
            if (!mkdir($upload_path.$fpath, 0777)) {
              if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot create ".$upload_path.$fpath;
              die();
            }
          }
          if (!move_uploaded_file($_FILES['logfile']['tmp_name'],$fname)) {
            if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Error while saving the logfile";
            die();
          }
          $query  .= ", logfile=".db_quote($fpath.$logfile,'rw');
        }

        // Complete the update query
        $query .= " WHERE id=" . db_quote($jobid,'rw');
        $res = db_query($query);
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> job id ".$jobid." UPDATE successful\n";
      } else {
        $jobid = NULL;
        if (!isset($_POST["quiet"]) && !isset($status))        echo "INSTALL SERVER> No job status\n";
        if (!isset($_POST["quiet"]) && !isset($validationref)) echo "INSTALL SERVER> No job validation\n";
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot update data\n";
      }
    }
    return $jobid;
  }

  // Main interface
  $jobid = set_job_data();
  if ($jobid) {
    if (!isset($_POST["quiet"])) {
      echo "INSTALL SERVER> job id ".$jobid." updated successfully";
    } else {
      echo $jobid;
    }
  } else {
    if (!isset($_POST["quiet"])) echo "INSTALL SERVER> job update failed";
  }
?>
