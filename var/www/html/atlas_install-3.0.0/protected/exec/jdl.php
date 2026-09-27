<?php

  require("../db.php");
  require('../user_info.php'); // User informations
  require('print_utils.php');  // Print utilities

  date_default_timezone_set('UTC');

  // Ident
  if (isset($_REQUEST["ident"])) { $ident = $_REQUEST["ident"]; } else { $ident = NULL; }

  // Check the user's credentials
  $user_info = get_user_info('select',NULL,NULL,True,NULL,15,0,$ident);
  if (count($user_info) == 0) {
    $role="";
  } else {
    $userref = $user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
  }

  $rc = array(200,"OK");

  if ($role == "master") {
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($_REQUEST['quiet'])) { $quiet = True; } else { $quiet = False; }
    if (isset($_REQUEST['debug'])) { $debug = True; } else { $debug = False; }

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "get commands:\n";
            echo "   content <jobid>\n";
            echo "   name <jobid>\n";
            echo "   type <jobid>\n";
            break;
          case 'content':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_jdl_content($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'name':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_jdl_name($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'type':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_jdl_type($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
        }
        break;
    }
  } else {
    $rc = array(500,"Insufficient privileges");
  }
  header('X-PHP-Response-Code: '.$rc[0], true, $rc[0]);
  if ($rc[0] != 200 && $rc[1] != NULL) {
    echo $rc[1]."\n";
  }

  function get_jdl_content($jobid) {
    # Query header
    $query = "SELECT jdl.content FROM jdl JOIN job ON jdl.ref=job.jdlfk WHERE job.id=".db_quote($jobid,'ro');

    # Fetch the records
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_row($result) ) {
        if (!is_null($row[0])) { echo $row[0]."\n"; } else { echo "None\n"; }
      }
      $rc = array(200,"OK");
    } else {
      $rc = array(404,NULL);
    }
    return $rc;
  }

  function get_jdl_name($jobid) {
    # Query header
    $query = "SELECT DISTINCT(jdl.name) FROM jdl JOIN job ON jdl.ref=job.jdlfk WHERE job.id=".db_quote($jobid,'ro');

    # Fetch the records
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_row($result) ) { echo $row[0]."\n"; }
      $rc = array(200,"OK");
    } else {
      $rc = array(404,NULL);
    }
    return $rc;
  }

  function get_jdl_type($jobid) {
    # Query header
    $query = "SELECT jdl.type FROM jdl JOIN job ON jdl.ref=job.jdlfk WHERE job.id=".db_quote($jobid,'ro');

    # Fetch the records
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_row($result) ) {
        if (!is_null($row[0])) { echo $row[0]."\n"; } else { echo "None\n"; }
      }
      $rc = array(200,"OK");
    } else {
      $rc = array(404,NULL);
    }
    return $rc;
  }

?>
