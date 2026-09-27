<?php

  require("../db.php");
  require('../user_info.php'); // User informations

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
    if (isset($_REQUEST['debug'])) { $debug = 1; }    else { $debug = 0; }

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "   forcerun <id>\n";
            echo "get commands:\n";
            echo "   list [id] [statusid] [status] [cename] [cs] [jobs] [activejobs] [lastjobstat] [release] [reqtype] [since] [age] [request_age] [grid] [facility] [sitetype] [maxrecords] [offset]\n";
            echo "   type <id>\n";
            break;
          case 'list':
            if (isset($_REQUEST['id']))          { $id = $_REQUEST['id']; }                   else { $id = NULL; }
            if (isset($_REQUEST['statusid']))    { $statusid = $_REQUEST['statusid']; }       else { $statusid = NULL; }
            if (isset($_REQUEST['status']))      { $status = $_REQUEST['status']; }           else { $status = NULL; }
            if (isset($_REQUEST['cename']))      { $cename = $_REQUEST['cename']; }           else { $cename = NULL; }
            if (isset($_REQUEST['cs']))          { $cs = $_REQUEST['cs']; }                   else { $cs = NULL; }
            if (isset($_REQUEST['release']))     { $release = $_REQUEST['release']; }         else { $release = NULL; }
            if (isset($_REQUEST['reqtype']))     { $reqtype = $_REQUEST['reqtype']; }         else { $reqtype = NULL; }
            if (isset($_REQUEST['since']))       { $since = $_REQUEST['since']; }             else { $since = NULL; }
            if (isset($_REQUEST['age']))         { $age = $_REQUEST['age']; }                 else { $age = NULL; }
            if (isset($_REQUEST['request_age'])) { $request_age = $_REQUEST['request_age']; } else { $request_age = NULL; }
            if (isset($_REQUEST['jobs']))        { $jobs = $_REQUEST['jobs']; }               else { $jobs = NULL; }
            if (isset($_REQUEST['activejobs']))  { $activejobs = $_REQUEST['activejobs']; }   else { $activejobs = NULL; }
            if (isset($_REQUEST['lastjobstat'])) { $lastjobstat = $_REQUEST['lastjobstat']; } else { $lastjobstat = NULL; }
            if (isset($_REQUEST['grid']))        { $grid = $_REQUEST['grid']; }               else { $grid = NULL; }
            if (isset($_REQUEST['facility']))    { $facility = $_REQUEST['facility']; }       else { $facility = NULL; }
            if (isset($_REQUEST['sitetype']))    { $sitetype = $_REQUEST['sitetype']; }       else { $sitetype = NULL; }
            if (isset($_REQUEST['maxrecords']))  { $maxrecords = $_REQUEST['maxrecords']; }   else { $maxrecords = NULL; }
            if (isset($_REQUEST['offset']))      { $offset = $_REQUEST['offset']; }           else { $offset = 0; }
            $rc = get_req_list($id, $statusid, $status, $cename, $cs
                             , $release, $reqtype, $since, $age, $request_age
                             , $jobs, $activejobs, $lastjobstat, $grid, $facility
                             , $sitetype, $maxrecords, $offset, $debug);
            break;
          case 'forcerun':
            if (isset($_REQUEST['id']))          { $id = $_REQUEST['id']; }                   else { $id = NULL; }
            $rc = get_req_forcerun($id, $debug);
            break;
          case 'type':
            if (isset($_REQUEST['id']))          { $id = $_REQUEST['id']; }                   else { $id = NULL; }
            $rc = get_req_type($id, $debug);
            break;
        }
        break;
      case 'PUT':
        switch ($_REQUEST["mode"]) {
          case 'help':
            echo "set commands:\n";
            echo "   status <id> <status> [comments] [debug] [reqtype]\n";
            break;
          case 'status':
            if (isset($_REQUEST['debug']))       { $debug = 1; }                              else { $debug = 0; }
            if (isset($_REQUEST['comments']))    { $comments = $_REQUEST['comments']; }       else { $comments = NULL; }
            if (isset($_REQUEST['reqtype']))     { $creqtype = $_REQUEST['reqtype']; }        else { $reqtype = NULL; }
            if (isset($_REQUEST['id']))          { $id = $_REQUEST['id']; }                   else { $id = NULL; }
            if (isset($_REQUEST['status']))      { $status = $_REQUEST['status']; }           else { $status = NULL; }
            $cache_timeout = 86400;
            $rc = set_req_status($id, $status, $comments, $reqtype, $userref, $cache_timeout, $debug);
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


  function get_req_list($id=NULL, $statusid=NULL, $status=NULL
                      , $cename=NULL, $cs=NULL, $rel=NULL
                      , $reqtype=NULL, $since=NULL, $age=NULL
                      , $request_age=NULL, $jobs=NULL, $activejobs=NULL
                      , $lastjobstat=NULL, $gridname=NULL, $facility=NULL
                      , $sitetype=NULL, $maxrecords=NULL, $offset=0
                      , $debug) {
    # Query body
    $q_body  = "SELECT request.id
                       ,request.typefk
                       ,request_type.description
                       ,release_stat.name
                       ,site.cename
                       ,site.cs
                       ,request.statusfk
                       ,request_status.description
                       ,request.request_date
                       ,request.update_date
                       ,user.name
                       ,bdii.ns
                       ,bdii.lb
                       ,bdii.ld
                       ,bdii.wmproxy
                       ,bdii.myproxy
                       ,request.userfk
                       ,IF(request.adminfk IS NOT NULL,request.adminfk,request.userfk)";
    $q_from =   " FROM request,release_stat,site,user,request_type,request_status,bdii";
    $q_where = " WHERE request.sitefk=site.ref
                   AND request.userfk=user.ref
                   AND request.relfk=release_stat.ref
                   AND request.typefk=request_type.ref
                   AND request.statusfk=request_status.ref
                   AND request.bdiifk=bdii.ref";

    # Fetch the records
    if ($id != NULL)          $q_where .= " AND request.id=" . db_quote($id,'ro');
    if ($statusid != NULL)    $q_where .= " AND request.statusfk=" . db_int($statusid);
    if ($status != NULL)      $q_where .= " AND request_status.description=" . db_quote($status,'ro');
    if ($cename != NULL)      $q_where .= " AND site.cename=" . db_quote($cename,'ro');
    if ($cs != NULL)          $q_where .= " AND site.cs=" . db_quote($cs,'ro');
    if ($rel != NULL)         $q_where .= " AND release_stat.name LIKE " . db_quote($rel,'ro');
    if ($reqtype != NULL)     $q_where .= " AND request_type.description LIKE " . db_quote($reqtype,'ro');
    if ($since != NULL)       $q_where .= " AND UNIX_TIMESTAMP(request.request_date) > " . db_int($since);
    if ($age != NULL)         $q_where .= (" AND UNIX_TIMESTAMP(request.update_date) <= '" . (time() - db_int($age,0)) . "'");
    if ($request_age != NULL) $q_where .= (" AND UNIX_TIMESTAMP(request.request_date) <= '" . (time() - db_int($request_age,0)) . "'");
    if ($jobs != NULL)        $q_where .= " AND (SELECT COUNT(*) FROM job WHERE requestfk=request.id) = " . db_int($jobs,0);
    if ($activejobs != NULL)  $q_where .= " AND (SELECT COUNT(*) FROM job WHERE requestfk=request.id AND validationfk=(SELECT ref FROM validation WHERE description='pending')) = " . db_int($activejobs,0);
    if ($lastjobstat != NULL) $q_where .= (" AND (SELECT COUNT(*) FROM job WHERE requestfk=request.id AND validationfk=(SELECT ref FROM validation WHERE description=" . db_quote($lastjobstat,'ro') . ") ORDER BY submission_time DESC LIMIT 1) > 0");
    if ($gridname != NULL) {
      $q_from .= ",grid";
      $q_where .= " AND site.gridfk=grid.ref AND grid.name=" . db_quote($gridname,'ro');
    }
    if ($facility != NULL) {
      $q_from .= ",facility";
      $q_where .= " AND site.facilityfk=facility.ref AND facility.name=" . db_quote($facility,'ro');
    }
    if ($sitetype != NULL) {
      $q_from .= ",site_activity_type";
      $q_where .= " AND site.activity_typefk=site_activity_type.ref AND site_activity_type.name=" . db_quote($sitetype,'ro');
    }
    $query  = ($q_body . $q_from . $q_where . " ORDER BY request.request_date DESC, site.cename");
    if ($maxrecords != NULL) $query .= " LIMIT " . db_int($maxrecords,1,100000) . " OFFSET " . db_int($offset,0);
    if ($debug > 0) echo $query."\n";
    $result = db_query_rest($query);
    $numfields = mysqli_num_fields($result);
    while ( $row = mysqli_fetch_row($result) ) {
      echo implode(",",$row)."\n";
    }
    return array(200,"OK");
  }

  function get_req_forcerun($id=NULL, $debug) {
    if (!is_null($id)) {
      $query  = "SELECT force_run FROM request WHERE id=".db_quote($id,'ro');
      $result = db_query_rest($query);
      $row = mysqli_fetch_row($result);
      echo $row[0]."\n";
      return array(200,"OK");
    } else {
      return array(401,"No request id specified");
    }
  }

  function get_req_type($id=NULL, $debug) {
    if (!is_null($id)) {
      $query  = "SELECT rt.description FROM request r, request_type rt WHERE r.typefk=rt.ref AND r.id=".db_quote($id,'ro');
      $result = db_query_rest($query);
      $row = mysqli_fetch_row($result);
      echo $row[0]."\n";
      return array(200,"OK");
    } else {
      return array(401,"No request id specified");
    }
  }

  function set_req_status($id, $status, $comments, $reqtype, $userref, $cache_timeout, $debug) {
    global $cache;
    $rowdp = NULL;
    if ($status == "autorun") {
      // Check for concurrent requests on the same exp soft area
      $query = "SELECT site.swarea, release_data.sw_physicalpath, release_data.sw_versionarea"
              . " FROM request, site, release_stat, release_data"
             . " WHERE site.ref=request.sitefk"
               . " AND release_stat.ref=request.relfk"
               . " AND release_stat.name=release_data.name"
               . " AND request.id=".db_quote($id,'ro');
      $resrd = db_query_rest($query);
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
                 . " AND request.id <> ".db_quote($id,'ro');
        if (isset($sw_versionarea)) $query = $query . " AND sw_versionarea=" . db_quote($sw_versionarea,'ro');
        $resdp = db_query_rest($query);
        $rowdp = mysqli_fetch_row($resdp);
      }
    }

    if (!$rowdp) {
      // Current date/time
      $date = date("Y-m-d H:i:s");
      if ($cache_timeout > 0) { $statusfk = $cache->get("request_status_".$status); } else { $statusfk = NULL; }
      if ($statusfk === False) {
        $statusqry = "SELECT ref FROM request_status WHERE description=" . db_quote($status,'rw');
        if ($debug) echo $statusqry."\n";
        $result = db_query_rest($statusqry);
        $row = mysqli_fetch_row($result);
        $statusfk = $row[0];
        $cache->set("request_status_".$status, $statusfk, MEMCACHE_COMPRESSED, $cache_timeout);
      }
      $updateqry = "UPDATE request SET statusfk=" . db_int($statusfk,1);
      if ($status == "not assigned") {
        $updateqry .= ", adminfk=NULL";
      } else {
        $updateqry .= ", adminfk=" . db_int($userref,1);
      }
      $updateqry .= ", update_date=" . db_quote($date,'rw');
      if ($comments && $comments != "") $updateqry .= ", admin_comments=" . db_quote($comments,'rw');
      if ($reqtype && $reqtype != "")   $updateqry .= ", typefk=(SELECT ref FROM request_type WHERE description=".db_quote($reqtype,'rw').")";
      $updateqry .= " WHERE id=" . db_quote($id,'rw');
      if ($debug) echo $updateqry."\n";
      $rc = db_query_rest($updateqry);

      // Task info
      $infoqry  = "SELECT rt.field, rl.name, s.cename, s.name, r.admin_comments"
                . "  FROM request_type rt, request r, release_stat rl, site s"
                . " WHERE r.relfk=rl.ref"
                . "   AND r.sitefk=s.ref"
                . "   AND r.typefk=rt.ref"
                . "   AND r.id=".db_quote($id,'ro');
      $result   = db_query_rest($infoqry);
      $info     = mysqli_fetch_row($result);
      $taskqry  = "SELECT task.description, task.name"
                . "  FROM release_data, task"
                . " WHERE release_data.name='".$info[1]."'"
                . "   AND task.ref=release_data.".$info[0];
      $taskres  = db_query_rest($taskqry);
      $taskdata = mysqli_fetch_row($taskres);
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

      // Insert a record in the log table
      $logqry   = "INSERT INTO log (requestfk,action,description,userfk,comments,url,date) VALUES ("
                . db_quote($id,'rw') . ","
                . "'" . $status . "',"
                . "'" . $taskdata[0] . " of release " . $info[1] . " on resource " . $info[2] . ", site name ". $info[3] . "',"
                . db_int($userref,1).","
                . "'" . $info[4] . "',"
                . "'" . $link . "',"
                . "'" . date("Y-m-d H:i:s") . "')";
      if ($debug) echo $logqry."\n";
      db_query_rest($logqry);
    } else {
      return array(501,sprintf("Another request id %s is active on the same installation area", $rowdp[0]));
    }
    return array(200,"OK");
  }
?>
