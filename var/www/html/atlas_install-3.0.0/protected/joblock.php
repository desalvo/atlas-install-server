<?php

  require("db.php");
  require('user_info.php'); // User informations

  // Check the user's credentials
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    $role="";
  } else {
    $userref = $user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
  }

  if ($role == "master") {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
      case 'GET':
        $lock_status = array(0 => "unlocked", 1 => "locked");
        # Query header
        $q_hdr  = "SELECT jdl.name AS jdlname, jdl.joblock AS joblock, user.name AS username";
        $q_body =  " FROM jdl, user"
                . " WHERE jdl.userfk=user.ref";

        # Fetch the records
        if (isset($_REQUEST['jdlname'])) {
          $q_body .= " AND jdl.name=".db_quote($_REQUEST['jdlname'],'rw');
        }
        $query  = ($q_hdr . $q_body);
        $result = db_query($query);
        $numrows = mysqli_num_rows($result);
        if ($numrows > 0) {
          while ( $row = mysqli_fetch_assoc($result) ) {
            printf ("%s,%s,%s\n", $row["jdlname"],$lock_status[$row["joblock"]],$row["username"]);
          }
        } else {
          echo $_REQUEST['jdlname'].",unlocked,undefined\n";
        }
        // SQL query disclosure disabled
        break;
      case 'PUT':
        if (!isset($_REQUEST["jdlname"]) && !isset($_REQUEST["jobid"])) {
          echo "No jdl name or job id specified\n";
        } else {
          if (isset($_REQUEST["lock"])) { $lockmode = 1; } else { $lockmode = 0; }
          $is_locked = 0;
          if ($lockmode == 1) {
            if (isset($_REQUEST["jobid"])) {
              $query = "SELECT joblock FROM jdl, job WHERE job.jdlfk=jdl.ref AND job.id=".db_quote($_REQUEST["jobid"],'rw')." AND joblock=1";
            } elseif (isset($_REQUEST["jdlname"])) {
              $query = "SELECT joblock FROM jdl WHERE name=".db_quote($_REQUEST["jdlname"],'rw')." AND joblock=1";
            }
            $result = db_query($query);
            $numrows = mysqli_num_rows($result);
            if ($numrows > 0) { $is_locked = True; }
          }
          if ($lockmode == 1 && $is_locked) {
            echo "1\n";
          } else {
            if (isset($_REQUEST["jobid"])) {
              $query  = "UPDATE jdl SET joblock=".$lockmode." WHERE ref=(SELECT jdlfk FROM job WHERE id=".db_quote($_REQUEST["jobid"],'rw').")";
            } else {
              $query  = "UPDATE jdl SET joblock=".$lockmode." WHERE name=".db_quote($_REQUEST["jdlname"],'rw');
            }
            $result = db_query($query);
            echo "0\n";
          }
        }
        break;
    }
  } else {
    echo "Insufficient privileges\n";
  }
?>
