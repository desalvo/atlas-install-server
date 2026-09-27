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
            echo "   count [resource] [user] [validation]\n";
            echo "   exit-code <jobid>\n";
            echo "   id <jdlname|jobname>\n";
            echo "   list [user] [validation] [quiet]\n";
            echo "   lock <jdlname> [age] [locked] [unlocked]\n";
            echo "   name <jobid>\n";
            echo "   output <jobid>\n";
            echo "   release <jobid>\n";
            echo "   request <jobid>\n";
            echo "   resource <jobid>\n";
            echo "   stale-lock [age] [quiet]\n";
            echo "   status [count] [grid] [jobid] [jobname] [last] [release] [request] [resource] [select] [validation] [quiet]\n";
            echo "   target <jobid>\n";
            break;
          case 'count':
            if (isset($_REQUEST['resource']))   { $resource = $_REQUEST['resource']; }     else { $resource = NULL; }
            if (isset($_REQUEST['user']))       { $user = $_REQUEST['user']; }             else { $user = NULL; }
            if (isset($_REQUEST['validation'])) { $validation = $_REQUEST['validation']; } else { $validation = NULL; }
            $rc = get_job_count($user, $validation, $resource, $quiet);
            break;
          case 'exit-code':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_job_exit_code($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'id':
            if (isset($_REQUEST['jdlname']))  { $jdlname = $_REQUEST['jdlname']; } else { $jdlname = NULL; }
            if (isset($_REQUEST['jobname']))  { $jobname = $_REQUEST['jobname']; } else { $jobname = NULL; }
            $rc = get_job_id($jdlname, $jobname);
            break;
          case 'list':
            if (isset($_REQUEST['validation'])) { $validation = $_REQUEST['validation']; } else { $validation = NULL; }
            if (isset($_REQUEST['user']))       { $user = $_REQUEST['user']; }             else { $user = NULL; }
            $rc = get_job_list($user, $validation, $quiet);
            break;
          case 'lock':
            if (isset($_REQUEST['jdlname']))  { $jdlname = $_REQUEST['jdlname']; } else { $jdlname = NULL; }
            if (isset($_REQUEST['age']))      { $age = $_REQUEST['age']; }         else { $age = NULL; }
            $lockstat = NULL;
            if (isset($_REQUEST['locked']))   $lockstat = 1;
            if (isset($_REQUEST['unlocked'])) $lockstat = 0;
            $rc = get_job_lock($jdlname, $age, $lockstat, $debug);
            break;
          case 'name':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_job_name($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'output':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_job_output($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'release':
            if (isset($_REQUEST['jobid'])) { $jobid = $_REQUEST['jobid']; } else { $jobid = NULL; }
            $rc = get_job_release($jobid);
            break;
          case 'request':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_job_request($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'resource':
            if (isset($_REQUEST["jobid"])) {
              $rc = get_job_resource($_REQUEST["jobid"]);
            } else {
              $rc = array(404,"No job id specified");
            }
            break;
          case 'stale-lock':
            if (isset($_REQUEST['age'])) { $age = $_REQUEST['age']; } else { $age = NULL; }
            $rc = get_stale_lock($age, $debug);
            break;
          case 'status':
            $select = NULL;
            if (isset($_REQUEST['count']))      { $select = "count(*)"; }
            if (isset($_REQUEST['select']))     { $select = db_identifier($_REQUEST['select']); }
            if (isset($_REQUEST['grid']))       { $grid = $_REQUEST['grid']; }             else { $grid = NULL; }
            if (isset($_REQUEST['jobid']))      { $jobid = $_REQUEST['jobid']; }           else { $jobid = NULL; }
            if (isset($_REQUEST['jobname']))    { $jobname = $_REQUEST['jobname']; }       else { $jobname = NULL; }
            if (isset($_REQUEST['last']))       { $last = $_REQUEST['last']; }             else { $last = NULL; }
            if (isset($_REQUEST['release']))    { $release = $_REQUEST['release']; }       else { $release = NULL; }
            if (isset($_REQUEST['request']))    { $request = $_REQUEST['request']; }       else { $request = NULL; }
            if (isset($_REQUEST['validation'])) { $validation = $_REQUEST['validation']; } else { $validation = NULL; }
            if (isset($_REQUEST['resource']))   { $resource = $_REQUEST['resource']; }     else { $resource = NULL; }
            $rc = get_job_status($select, $grid, $jobid, $jobname, $last, $release, $request, $validation, $resource, $quiet);
            break;
          case 'target':
            if (isset($_REQUEST['jobid']))      { $jobid = $_REQUEST['jobid']; }           else { $jobid = NULL; }
            $rc = get_job_target($jobid);
            break;
        }
        break;
      case 'PUT':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "set commands:\n";
            echo "   lock <jdlname|jobid>\n";
            break;
          case 'lock':
            if (!isset($_REQUEST["jdlname"]) && !isset($_REQUEST["jobid"])) {
              $rc = array(500,"No jdl name or job id specified");
            } else {
              if (isset($_REQUEST['jdlname'])) { $jdlname = $_REQUEST['jdlname']; } else { $jdlname = NULL; }
              if (isset($_REQUEST['jobid']))   { $jobid = $_REQUEST['jobid']; }     else { $jobid = NULL; }
              $lock = 1;
              $rc = set_job_lock($jdlname, $jobid, $lock, $_REQUEST["ident"]);
            }
            break;
        }
        break;
      case 'DELETE':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "del commands:\n";
            echo "   lock <jdlname|jobid>\n";
            break;
          case 'lock':
            if (!isset($_REQUEST["jdlname"]) && !isset($_REQUEST["jobid"])) {
              $rc = array(500,"No jdl name or job id specified");
            } else {
              if (isset($_REQUEST['jdlname'])) { $jdlname = $_REQUEST['jdlname']; } else { $jdlname = NULL; }
              if (isset($_REQUEST['jobid']))   { $jobid = $_REQUEST['jobid']; }     else { $jobid = NULL; }
              $lock = 0;
              $rc = set_job_lock($jdlname, $jobid, $lock, $_REQUEST["ident"]);
            }
            break;
        }
        break;
    }
  } else {
    $rc = array(500,"Insufficient privileges - role: $role - ident: $ident - ssluserdetails: $ssluserdetails");
  }
  header('X-PHP-Response-Code: '.$rc[0], true, $rc[0]);
  if ($rc[0] != 200 && $rc[1] != NULL) {
    echo $rc[1]."\n";
  }

  function get_job_id($jdlname=NULL,$jobname=NULL) {
    # Query header
    $q_hdr  = "SELECT job.id";
    $q_body =  " FROM job";

    if (!is_null($jdlname) or !is_null($jobname)) {
      # Fetch the records
      if (!is_null($jdlname)) {
        $q_body .= " JOIN jdl ON job.jdlfk=jdl.ref";
        $q_where = " WHERE jdl.name=" . db_quote($jdlname,'ro');
      } else {
        $q_where = " WHERE job.name=" . db_quote($jobname,'ro');
      }
      $query  = ($q_hdr . $q_body . $q_where);
      $result = db_query_rest($query);
    } else {
      $numrows = 0;
    }
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

  function get_job_lock($jdlname=NULL,$age=NULL,$lockstat=NULL,$debug=False) {
    $lock_status = array(0 => "unlocked", 1 => "locked");
    # Query header
    $q_hdr  = "SELECT jdl.name AS jdlname, jdl.joblock AS joblock, user.name AS username";
    $q_body =  " FROM jdl, user"
            . " WHERE jdl.userfk=user.ref";

    # Fetch the records
    if (!is_null($jdlname))  $q_body .= " AND jdl.name=".db_quote($jdlname,'ro');
    if (!is_null($age))      $q_body .= " AND jdl.joblock_date > (UTC_TIMESTAMP()-".db_int($age,0).")";
    if (!is_null($lockstat)) $q_body .= " AND jdl.joblock=".db_int($lockstat,0,1);
    $query  = ($q_hdr . $q_body);
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_assoc($result) ) {
        printf ("%s,%s,%s\n", $row["jdlname"],$lock_status[$row["joblock"]],$row["username"]);
      }
    } else {
      if (!$jdlname) $jdlname="unknown";
      printf ("%s,unlocked,undefined\n", $jdlname);
    }
    return array(200,"OK");
  }

  function set_job_lock($jdlname=NULL, $jobid=NULL, $lock=1, $ident=NULL) {
    $is_locked = 0;
    $query = "SELECT ref FROM user WHERE name=".db_quote($ident,'rw');
    $result = db_query_rest($query);
    if ($result) {
      $numrows = mysqli_num_rows($result);
      if ($numrows == 0) return array(404,"Invalid ident");
      $row = mysqli_fetch_row($result);
      $identfk = $row[0];
    } else {
      return array(505,"Cannot check ident");
    }
    if ($lock == 1) {
      if (!is_null($jobid)) {
        $query = "SELECT joblock, user.name FROM jdl, job, user WHERE job.jdlfk=jdl.ref AND job.id=".db_quote($jobid,'rw')." AND joblock=1 AND jdl.userfk=user.ref";
      } elseif (!is_null($jdlname)) {
        $query = "SELECT joblock, user.name FROM jdl, user WHERE jdl.name=".db_quote($jdlname,'rw')." AND joblock=1 AND jdl.userfk=user.ref";
      }
      $result = db_query_rest($query);
      $numrows = mysqli_num_rows($result);
      if ($numrows > 0) {
        $is_locked = True;
        $row = mysqli_fetch_row($result);
        $lock_user_name = $row[1];
      }
    }
    if ($lock == 1 && $is_locked) {
      return array(404,"JDL already locked by user " . $lock_user_name);
    } else {
      if (!is_null($jobid)) {
        $query  = "UPDATE jdl SET joblock=".db_int($lock,0,1).", joblock_date=UTC_TIMESTAMP(), userfk=".db_int($identfk,1)." WHERE ref=(SELECT jdlfk FROM job WHERE id=".db_quote($jobid,'rw').")";
      } else {
        $query  = "UPDATE jdl SET joblock=".db_int($lock,0,1).", joblock_date=UTC_TIMESTAMP(), userfk=".db_int($identfk,1)." WHERE name=".db_quote($jdlname,'rw');
      }
      $result = db_query_rest($query);
      if ($result) { return array(200,"OK"); } else { return array(500,"DB error"); }
    }
  }

  function get_job_count($user=NULL, $validation=NULL, $resource=NULL, $quiet=False) {
    # Query header
    $q_hdr    = "SELECT COUNT(*) AS entries";
    $q_tables =  " FROM job, user, validation";
    $q_filter =  " WHERE job.userfk=user.ref"
              . " AND job.validationfk=validation.ref";

    if (!is_null($resource)) {
      $q_tables .= ", site";
      $q_filter .= " AND job.sitefk=site.ref AND site.cs=" . db_quote($resource,'ro');
    }
    if (!is_null($user))       $q_filter .= " AND user.name=".db_quote($user,'ro');
    if (!is_null($validation)) $q_filter .= " AND validation.description=".db_quote($validation,'ro');

    # Fetch the records
    $query  = ($q_hdr . $q_tables . $q_filter);
    $result = db_query_rest($query);
    if ($result) {
      if (is_null($quiet)) {
        $rc = print_mysql_table($result,NULL,NULL,$quiet);
      } else {
        $row = mysqli_fetch_row($result);
        if ($row) echo $row[0]."\n";
        $rc = array(200,"OK");
      }
    } else {
      $rc = array(500,"DB Error");
    }
    return $rc;
  }

  function get_job_exit_code($jobid) {
    # Query header
    $query = "SELECT job.exit_code FROM job WHERE id=".db_quote($jobid,'ro');

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

  function get_job_list($user=NULL, $validation=NULL,$quiet=False) {
    # Query header
    $q_hdr  = "SELECT job.name AS job_name"
                 . ", job.status AS job_status"
                 . ", validation.description AS val_status"
                 . ", job.submission_time AS submission_time"
                 . ", user.name AS user_name";
    $q_body =  " FROM job, user, validation"
            . " WHERE job.userfk=user.ref"
              . " AND job.validationfk=validation.ref";

    if (!is_null($validation)) $q_body .= " AND validation.description=".db_quote($validation,'ro');
    if (!is_null($user))       $q_body .= " AND user.name=".db_quote($user,'ro');

    # Fetch the records
    $query  = ($q_hdr . $q_body);
    $result = db_query_rest($query);
    if ($result) {
      $rc = print_mysql_table($result,NULL,NULL,$quiet);
    } else {
      $rc = array(500,"DB Error");
    }
    return $rc;
  }

  function get_job_name($jobid) {
    # Query header
    $query = "SELECT DISTINCT(name) FROM job WHERE id=".db_quote($jobid,'ro');

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

  function get_job_output($jobid) {
    # Query header
    $query = "SELECT job.id, job.name, job.logfile, job_output.name FROM job JOIN job_output ON job_output.jobfk=job.ref WHERE id=".db_quote($jobid,'ro');

    # Fetch the records
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_row($result) ) {
          if (!is_null($row[2])) { $jlf = $row[2]; } else { $jlf = 'None'; }
          if (!is_null($row[3])) { $jon = $row[3]; } else { $jon = 'None'; }
          echo $row[0].", ".$row[1].", ".$jlf.", ".$jon."\n";
      }
      $rc = array(200,"OK");
    } else {
      $rc = array(404,NULL);
    }
    return $rc;
  }

  function get_job_release($jobid) {
    if (is_null($jobid)) return array(404,NULL);

    # Query header
    $query = "SELECT release_stat.name FROM release_stat JOIN jdl ON jdl.relfk=release_stat.ref JOIN job ON job.jdlfk=jdl.ref WHERE job.id=".db_quote($jobid,'ro');

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

  function get_job_request($jobid) {
    # Query header
    $query = "SELECT job.requestfk FROM job WHERE id=".db_quote($jobid,'ro');

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

  function get_job_resource($jobid) {
    # Query header
    $query = "SELECT site.cs FROM job JOIN site ON job.sitefk=site.ref WHERE id=".db_quote($jobid,'ro');

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

  function get_job_status($select=NULL, $grid=NULL, $jobid=NULL, $jobname=NULL, $last=NULL, $release=NULL, $request=NULL, $validation=NULL, $resource=NULL, $quiet=False) {
    $rc = array(200,"OK");

    if ($jobid == NULL && $jobname == NULL && $release == NULL && $request == NULL) {
      return array(500,"Please specify a jobid, jobname, release or request id");
    }

    # Query header
    if (is_null($select)) {
      $fields  = "job.id AS job_id"
             . ", user.name AS user_name"
             . ", jdl.name AS jdl_name"
             . ", job.name AS job_name"
             . ", job.submission_time AS submission_time"
             . ", job.reach_time AS reach_time"
             . ", job.retrieval_time AS retrieval_time"
             . ", job.status AS status"
             . ", job.status_reason AS status_reason"
             . ", job.exit_code AS exit_code";
    } else {
      $fields = $select;
    }
    $tables  = "job, jdl, user";
    $q_hdr   = "SELECT ".$fields;
    $q_table =  " FROM ".$tables;
    $q_crit  =  " WHERE job.jdlfk=jdl.ref AND job.userfk=user.ref";
    $q_ord   =  " ORDER BY job.submission_time";

    if (!is_null($grid))   {
      $q_table .= ", site, grid";
      $q_crit  .= " AND job.sitefk=site.ref AND site.gridfk=grid.ref";
      $q_crit  .= " AND grid.name=".db_quote($grid,'ro');
    }
    if (!is_null($jobid))   $q_crit .= " AND job.id=".db_quote($jobid,'ro');
    if (!is_null($jobname)) $q_crit .= " AND job.name=".db_quote($jobname,'ro');
    if (!is_null($last))    $q_ord  .= " DESC LIMIT ".db_int($last,1,10000);
    if (!is_null($release))   {
      $q_table .= ", release_stat";
      $q_crit  .= " AND jdl.relfk=release_stat.ref";
      $q_crit  .= " AND release_stat.name=".db_quote($release,'ro');
    }
    if (!is_null($request)) $q_crit  .= " AND job.requestfk=".db_quote($request,'ro');
    if (!is_null($resource))   {
      $q_table .= ", site";
      $q_crit  .= " AND job.sitefk=site.ref";
      $q_crit  .= " AND site.cs=".db_quote($resource,'ro');
    }
    if (!is_null($validation)) $q_crit  .= " AND job.validationfk=(SELECT ref FROM validation WHERE description=".db_quote($validation,'ro').")";

    # Fetch the records
    $query  = ($q_hdr . $q_table . $q_crit . $q_ord);
    $result = db_query_rest($query);
    if ($result) {
      $rc = print_mysql_table($result,NULL,NULL,$quiet);
    } else {
      $rc = array(500,"DB Error");
    }
    return $rc;
  }

  function get_stale_lock($age=NULL,$debug=False) {
    # Query header
    $q1_body = "SELECT DISTINCT(j.name) AS jdlname"
              . " FROM request r, release_stat rs, jdl j, job jb"
             . " WHERE jb.jdlfk=j.ref AND r.relfk=j.relfk AND r.relfk=rs.ref"
               . " AND statusfk=(SELECT ref FROM request_status WHERE description='autorun')"
               . " AND joblock=1"
               . " AND jb.id NOT IN (SELECT id FROM job WHERE validationfk=(SELECT ref FROM validation WHERE description='pending'))";
    $q2_body = "SELECT DISTINCT(jdl.name) AS jdlname"
              . " FROM jdl"
             . " WHERE (SELECT COUNT(*) FROM job WHERE jdlfk=jdl.ref)=0"
               . " AND joblock=1";
    $q3_body = "SELECT DISTINCT(jdl.name) AS jdlname"
              . " FROM jdl, job"
             . " WHERE jdl.ref=job.jdlfk"
               . " AND (SELECT COUNT(*) FROM request WHERE id=job.requestfk) = 0"
               . " AND joblock=1";

    if (!is_null($age)) {
      $q1_body .= " AND (UTC_TIMESTAMP() - joblock_date) > ".db_int($age,0);
      $q2_body .= " AND (UTC_TIMESTAMP() - joblock_date) > ".db_int($age,0);
      $q3_body .= " AND (UTC_TIMESTAMP() - joblock_date) > ".db_int($age,0);
    }


    # Fetch the records
    $query = $q1_body." UNION ".$q2_body." UNION ".$q3_body;
    $result = db_query_rest($query);
    $numrows = mysqli_num_rows($result);
    if ($numrows > 0) {
      while ( $row = mysqli_fetch_assoc($result) ) {
        echo $row["jdlname"]."\n";
      }
    }
    return array(200,"OK");
  }

  function get_job_target($jobid) {
    if (is_null($jobid)) return array(404,NULL);

    # Query header
    $query = "SELECT target FROM job LEFT JOIN jdl ON job.jdlfk=jdl.ref JOIN task ON jdl.type=task.name WHERE job.id=".db_quote($jobid,'ro');

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
?>
