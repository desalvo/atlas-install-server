<?php

  require("db.php");
  require("user_info.php");

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

  if (isset($_REQUEST['cs']) || isset($_REQUEST['cename']) || isset($_REQUEST['name']) || isset($_REQUEST['siteid'])) {
    // Query arrays
    $query_select = array("site.*"
                        , "GROUP_CONCAT((SELECT field_name FROM field_descriptions WHERE ref=site_ext.fieldfk),',',site_ext.value) AS site_ext");
    $query_from = array("site LEFT OUTER JOIN site_ext ON site.ref=site_ext.sitefk");
    $query_tables = array("site","site_ext");
    $query_where = array();

    // Field relations, aggregated tables
    $aggregated_tables = array();
    foreach ($query_tables as $table_name) {
      $qry_res = db_query("SELECT name,field1,table2,field2 FROM field_relations WHERE table1='".$table_name."'");
      while ($row = mysqli_fetch_row($qry_res)) {
        $foreign_constraint = $table_name.".".$row[1]."=".$row[2].".ref";
        $field = $row[2].".".$row[3];
        $aggregated_table = $table_name.".".$row[2];
        array_push($query_select,"(SELECT ".$field." FROM ".$row[2]." WHERE ".$foreign_constraint.") AS '".$aggregated_table.".".$row[0]."'");
        if (!in_array($aggregated_table,$aggregated_tables)) array_push($aggregated_tables,$aggregated_table);
      }
    }

    // Field descriptions
    $query_table_desc = array();
    // Main tables
    foreach ($query_tables as $table_name) {
      $qry_res = db_query("SELECT field_name,field_query,show_conf,format,fregexp,token FROM field_descriptions WHERE table_name='".$table_name."'");
      while ($row = mysqli_fetch_row($qry_res)) {
        if (isset($row[1])) array_push($query_select,"(".$row[1].") AS ".$row[0]);
        if (isset($row[3])) $format = $row[3]; else $format = NULL;
        if (isset($row[4])) $regexp = $row[4]; else $regexp = NULL;
        if (isset($row[5])) $token  = $row[5]; else $token  = NULL;
        $query_table_desc[$row[0]] = array( "show_conf" => $row[2], "format" => $format, "fregexp" => $regexp, "token" => $token);
      }
    }
    // Aggregated tables
    foreach ($aggregated_tables as $table_name) {
      $qry_res = db_query("SELECT field_name,field_query,show_conf,format,fregexp,token FROM field_descriptions WHERE table_name='".$table_name."'");
      while ($row = mysqli_fetch_row($qry_res)) {
        if (isset($row[3])) $format = $row[3]; else $format = NULL;
        if (isset($row[4])) $regexp = $row[4]; else $regexp = NULL;
        if (isset($row[5])) $token  = $row[5]; else $token  = NULL;
        $query_table_desc[$table_name.".".$row[0]] = array( "show_conf" => $row[2], "format" => $format, "fregexp" => $regexp, "token" => $token);
      }
    }

    // Build the query
    if (isset($_REQUEST['cs'])) array_push($query_where,"site.cs LIKE " . db_quote($_REQUEST['cs'],'ro'));
    if (isset($_REQUEST['name'])) array_push($query_where,"site.name LIKE " . db_quote($_REQUEST['name'],'ro'));
    if (isset($_REQUEST['cename'])) array_push($query_where,"site.cename LIKE " . db_quote($_REQUEST['cename'],'ro'));
    if (isset($_REQUEST['siteid'])) array_push($query_where,"site.ref = " . db_int($_REQUEST['siteid'],1));
    $query = "SELECT ".implode(",",$query_select);
    $query .= " FROM ".implode(",",$query_from);
    if (count($query_where) > 0) $query .= " WHERE ".implode(" AND ",$query_where);
    $query .= " GROUP BY site_ext.sitefk ORDER BY site.cename DESC";
    $qry_res = db_query($query);
    $is_error = False;
    while ($row = mysqli_fetch_row($qry_res)) {
      $columns = mysqli_num_fields($qry_res);
      if (isset($sitedef)) unset($sitedef);
      $siteval = array();
      $regexp  = array();
      for ($i=0; $i<$columns; $i++) {
        $field_name = mysqli_fetch_field_direct($qry_res,$i)->name;
        $value=$row[$i];
        if (isset($fields)) unset($fields);
        if ($field_name != "site_ext") {
          // Normal fields
          $fields = array($field_name => $value);
        } else {
          // Extensions
          $fields = array();
          $field_data = explode(',',$value);
          $num_fields = count($field_data);
          for ($j=0; $j<$num_fields; $j+=2) if ($field_data[$j+1] != '') $fields[$field_data[$j]] = $field_data[$j+1];
        }
        foreach ($fields as $field_name => $value) {
          if (isset($query_table_desc[$field_name]["show_conf"])) {
            if ($query_table_desc[$field_name]["show_conf"] == 1) {
              if (isset($query_table_desc[$field_name]["token"])) {
                $token = $query_table_desc[$field_name]["token"];
              } else {
                $token = $field_name;
              }
              if (isset($query_table_desc[$field_name]["format"]) && $value != "") {
                $siteval[$token] = sprintf($query_table_desc[$field_name]["format"],$value);
                if (isset($query_table_desc[$field_name]["fregexp"])) {
                  $parts = explode("/",$query_table_desc[$field_name]["fregexp"]);
                  if (count($parts) > 2) $regexp[$token] = array("from" => $parts[1], "to" => $parts[2]);
                }
              } else {
                $siteval[$token] = $value;
              }
            }
          } else {
            print "No conf found for ".$field_name."\n";
            $is_error = True;
          }
        }
        // Change the parameters to their values
        $sitedef = array();
        foreach ($siteval as $skey1 => $sval1) {
          $svalue = $sval1;
          foreach ($siteval as $skey2 => $sval2) $svalue = preg_replace("/@".$skey2."@/",$sval2,$svalue);
          if (isset($regexp[$skey1])) $svalue = ereg_replace($regexp[$skey1]["from"], $regexp[$skey1]["to"], $svalue);
          array_push($sitedef, $skey1."=".$svalue);
        }
      }
      if (count($sitedef) > 0 && !$is_error) {
        echo implode(",",$sitedef);
        print "\n";
      }
    }
  }
?>
