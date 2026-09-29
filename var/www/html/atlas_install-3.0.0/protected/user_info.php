<?php

  require_once("db.php");
  function get_user_info($mode='select',$id=NULL,$role=NULL,$valid=NULL,$filter=NULL,$limit=15,$offset=0,$ident=NULL) {
    if ($mode === 'select' && $id === NULL && $filter === NULL && $ident === NULL) {
      $ai = atlas_current_identity();
      if ($ai && ($ai['source'] ?? '') === 'local') {
        $rp=in_array((string)$ai['role'],['admin','master'],true)?1:0; return [[ 'ref'=>(int)($ai['id'] ?? 0), 'name'=>(string)$ai['username'], 'dn'=>'LOCAL:'.(string)$ai['username'], 'email'=>(string)$ai['email'], 'rolefk'=>(string)$ai['role']==='master'?3:((string)$ai['role']==='admin'?2:1), 'role'=>(string)$ai['role'], 'priv_view'=>$rp, 'priv_insert'=>$rp, 'priv_update'=>$rp, 'priv_pin'=>$rp, 'priv_relsub'=>$rp, 'priv_critical'=>$rp, 'valid_start'=>'2000-01-01 00:00:00', 'valid_end'=>'2099-12-31 23:59:59', 'enabled'=>(int)$ai['enabled'] ]];
      }
    }
    //$ssluserdetails = getenv("SSL_CLIENT_S_DN");
    $ssluserdetails = preg_replace('/\/CN=proxy/','',getenv("SSL_CLIENT_S_DN"));
    $ssluserdetails = preg_replace('/\/CN=[0-9]+/','',$ssluserdetails);
    $ssluserdetails = preg_replace('/\/CN=[0-9]+/','',$ssluserdetails);
    $sslusername = getenv("SSL_CLIENT_S_DN_CN");
    $where = array();
    if ($mode == 'count') {
      $userquery = "SELECT COUNT(u.ref) AS counts FROM user u, role r";
    } elseif ($mode == 'emails') {
      $userquery = "SELECT DISTINCT(u.email) AS email"
                   ." FROM user u, role r";
      array_push($where,"email IS NOT NULL");
      array_push($where,"email <> ''");
    } elseif ($mode == 'subscribers') {
      $userquery = "SELECT DISTINCT(u.email) AS email FROM subscription s, user u, role r";
      array_push($where,"s.userfk=u.ref");
      array_push($where,"(s.sitename=".db_quote($filter,'ro')." OR s.sitename='*')");
      array_push($where,"email IS NOT NULL");
      array_push($where,"email <> ''");
      array_push($where,"immediate = 1");
    } elseif ($mode == 'digest') {
      $userquery = "SELECT DISTINCT(u.email) AS email FROM subscription s, user u, role r";
      array_push($where,"s.userfk=u.ref");
      array_push($where,"(s.sitename=".db_quote($filter,'ro')." OR s.sitename='*')");
      array_push($where,"email IS NOT NULL");
      array_push($where,"email <> ''");
      array_push($where,"summary = 1");
    } else {
      $userquery = "SELECT u.ref"
                        .",u.name"
                        .",u.dn"
                        .",u.email"
                        .",u.rolefk"
                        .",r.description as role"
                        .",u.priv_view"
                        .",u.priv_insert"
                        .",u.priv_update"
                        .",u.priv_pin"
                        .",u.priv_relsub"
                        .",u.priv_critical"
                        .",u.valid_start"
                        .",u.valid_end"
                        .",u.enabled"
                   ." FROM user u, role r";
    }
    array_push($where,"ABS(u.rolefk)=r.ref");
    if ($mode != 'subscribers' and $mode != 'digest') {
      if (isset($id)) {
        array_push($where, "u.ref=".db_int($id,1));
      } else {
        if ($filter) {
          array_push($where, "u.name LIKE ".db_quote($filter,'ro'));
        } elseif ($mode == 'select') {
          array_push($where, "u.dn=" . db_quote($ssluserdetails,'ro'));
          if (isset($_POST['user'])) {
            array_push($where, "u.name=" . db_quote($_POST["user"],'ro'));
          } elseif ($ident != NULL) {
            array_push($where, "u.name=" . db_quote($ident,'ro'));
          } elseif (isset($sslusername)) {
            array_push($where, "u.name=" . db_quote($sslusername,'ro'));
          }
        }
      }
    }
    if (isset($role)) array_push($where, "r.description=".db_quote($role,'ro'));
    if ($valid) {
      $now = date('Y-m-d H:i:s');
      array_push($where, "u.enabled=1");
      array_push($where, "u.valid_start <= ".db_quote($now,'ro'));
      array_push($where, "u.valid_end > ".db_quote($now,'ro'));
    }
    if ($where)  $userquery .= " WHERE ".join(" AND ",$where);
    if ($mode != 'count' && $limit) $userquery .= " LIMIT ".db_int($offset,0).",".db_int($limit,1,100000);
    #print ($userquery);
    $res = db_query($userquery);
    $user_info = array();
    while ($row = mysqli_fetch_assoc($res)) {
      array_push($user_info,$row);
    }
    return $user_info;
  }

  function add_user ($name,$email,$dn,$role
                    ,$priv_view=False,$priv_insert=False,$priv_update=False,$priv_pin=False, $priv_relsub=False
                    ,$priv_critical=False,$valid_start=NULL, $valid_end=NULL) {
    // Insert the new user
    $now = date('Y-m-d H:i:s');
    if (isset($name) && isset($email) && isset($dn)) {
      $query = "INSERT INTO user SET name=".db_quote($name,'rw')
                                  .",email=".db_quote($email,'rw')
                                  .",dn=".db_quote($dn,'rw')
                                  .",rolefk=(SELECT -ref FROM role WHERE description=".db_quote($role,'rw').")";
      if ($priv_view)     $query .= ",priv_view=-1";     else $query .= ",priv_view=0";
      if ($priv_insert)   $query .= ",priv_insert=-1";   else $query .= ",priv_insert=0";
      if ($priv_update)   $query .= ",priv_update=-1";   else $query .= ",priv_update=0";
      if ($priv_pin)      $query .= ",priv_pin=-1";      else $query .= ",priv_pin=0";
      if ($priv_relsub)   $query .= ",priv_relsub=-1";   else $query .= ",priv_relsub=0";
      if ($priv_critical) $query .= ",priv_critical=-1"; else $query .= ",priv_critical=0";
      if ($valid_start)   $query .= ",valid_start=".db_quote($valid_start,'rw'); else $query .= ",valid_start=".db_quote($now,'rw');
      if ($valid_end)     $query .= ",valid_end=".db_quote($valid_end,'rw');
      error_log("[USER_ADD] ".$query."\n");
      $res = db_query($query);
      error_log("[USER_ADD] User added successfully\n");
    }
  }

  function update_user ($id=NULL,$name=NULL,$email=NULL,$dn=NULL,$rolefk=NULL
                       ,$priv_view=NULL,$priv_insert=NULL,$priv_update=NULL,$priv_pin=NULL,$priv_relsub=NULL
                       ,$priv_critical=NULL,$valid_start=NULL, $valid_end=NULL,$enabled=NULL) {
    if (isset($id)) {
      $query_list = array();
      if ($name != NULL)          array_push($query_list,"name=".db_quote($name,'rw'));
      if ($email != NULL)         array_push($query_list,"email=".db_quote($email,'rw'));
      if ($dn != NULL)            array_push($query_list,"dn=".db_quote($dn,'rw'));
      if ($rolefk != NULL)        array_push($query_list,"rolefk=".db_int($rolefk,-2147483648,2147483647));
      if ($priv_view != NULL)     array_push($query_list,"priv_view=".db_int($priv_view,-1,1));
      if ($priv_insert != NULL)   array_push($query_list,"priv_insert=".db_int($priv_insert,-1,1));
      if ($priv_update != NULL)   array_push($query_list,"priv_update=".db_int($priv_update,-1,1));
      if ($priv_pin != NULL)      array_push($query_list,"priv_pin=".db_int($priv_pin,-1,1));
      if ($priv_relsub != NULL)   array_push($query_list,"priv_relsub=".db_int($priv_relsub,-1,1));
      if ($priv_critical != NULL) array_push($query_list,"priv_critical=".db_int($priv_critical,-1,1));
      if ($valid_start != NULL)   array_push($query_list,"valid_start=".db_quote($valid_start,'rw'));
      if ($valid_end != NULL)   array_push($query_list,"valid_end=".db_quote($valid_end,'rw'));
      if ($enabled != NULL)     array_push($query_list,"enabled=".db_int($enabled,0,1));
      if (count($query_list) > 0) {
        $query = "UPDATE user SET ".implode(",",$query_list)." WHERE ref=".db_int($id,1);
        error_log("[USER_UPDATE] ".$query."\n");
        $res = db_query($query);
        error_log("[USER_UPDATE] User data updated successfully\n");
      } else {
        echo "No data to update<BR>";
      }
    }
  }

  function get_role_id($rolename) {
    $rolequery = "SELECT ref FROM role WHERE description=".db_quote($rolename,'ro');
    $roleres = db_query($rolequery);
    $rolerow = mysqli_fetch_row($roleres);
    if ($rolerow) return $rolerow[0];
    return 0;
  }
?>
