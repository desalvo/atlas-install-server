<?php

  require_once("print_utils.php");

  $filter_release = "WHERE release_stat.sitefk=site.ref AND release_stat.userfk=user.ref AND release_stat.name=release_data.name";
  $order_release  = "ORDER BY release_stat.name, site.name";
  $query_release  = "SELECT %s FROM release_stat, release_data, site, user";

  function ljsfinfo_readDB($select=NULL,$body=NULL,$filter=''
                          ,$groupby='',$orderby='',$tables=NULL
                          ,$extquery=NULL,$extkey=NULL
                          ,$cache_timeout=7200,$showtokens=False
                          ,$genparser=False,$noout=False
                          ,$quiet=False,$debug=False,$quote=True) {
    global $cache;
    $rc = array(200,"OK");
    if ($genparser) $showtokens=True;

    $fielddata = array();
    if ($tables != NULL) $fielddata = getFieldData($tables,$cache_timeout,$debug);
    if (count($fielddata) > 0) {
      foreach (array_keys($fielddata) as $field) {
        if ($fielddata[$field]['query']) {
          $select .= sprintf(", (%s) AS %s" , $fielddata[$field]['query'], $field);
        }
      }
    }
    if (strlen($body) == 0) {
      $body = sprintf($query_release, $select);
    } else {
      $body = sprintf($body, $select);
    }

    $query = sprintf("%s %s %s %s", $body, $filter, $groupby, $orderby);
    if ($debug) echo $query."\n";
    if ($cache && $cache_timeout > 0) {
      $report = $cache->get(md5("report".$query));
      $report_fields = $cache->get(md5("report_fields".$query));
    }

    if (!$report || !$report_fields) {
      $result = db_query_rest($query);
      $report = array();
      $report_fields = array();
      $numfields = mysqli_num_fields($result);
      for ($i=0; $i<$numfields; $i++) {
        $field_name = mysqli_fetch_field_direct($result,$i)->name;
        array_push($report_fields,$field_name);
      }
      while ($row = mysqli_fetch_row($result)) {
        array_push($report, $row);
      }
      if ($cache && $cache_timeout > 0) {
        $cache->set(md5("report".$query),$report,MEMCACHE_COMPRESSED,$cache_timeout);
        $cache->set(md5("report_fields".$query),$report_fields,MEMCACHE_COMPRESSED,$cache_timeout);
      }
    } else {
      if ($debug) echo "Using cached data\n";
      $numfields = count($report_fields);
    }

    #print_mysql_table(NULL,$report,$report_fields,True);

    foreach ($report as $row) {
      $indx = 0;
      $field_values = array();
      $extkey_value = NULL;
      foreach ($row as $field) {
        $fname = $report_fields[$indx];
        if ($field == NULL) {
          if (count($fielddata) > 0 && array_key_exists($fname,$fielddata) && $fielddata[$fname]["null"] == 0) {
            $field='%UNDEFINED_'.strtoupper($report_fields[$indx]);
          } else {
            $field='';
          }
        }
        $fregexp = $fielddata[$fname]['fregexp'];
        $fformat = $fielddata[$fname]['format'];
        array_push($field_values,array(0,$fname,$field,$fregexp,$fformat));
        if ($extquery != NULL && $extkey != NULL and $fname == $extkey) $extkey_value = $field;
        $indx++;
      }

      $outdata = array();
      if (!$quiet) { $sep = '|'; } else { $sep = ','; }

      # Extensions
      if ($extkey_value) {
        $extq = sprintf($extquery, $extkey_value);
        if ($debug) echo $extq."\n";
        $res_ext = db_query_rest($extq);
        while ($row_ext = mysqli_fetch_row($res_ext)) {
          if ($row_ext[1] == 1) {
            if ($row_ext[4]) {
              $field=$row_ext[4];
            } else {
              if ($row_ext[5] == 1) {
                if ($row_ext[6]) { $field=$row_ext[6]; } else { $field = ''; }
              } else {
                if ($row_ext[6]) { $field=$row_ext[6]; } else { $field='%UNDEFINED_'.strtoupper($row_ext[3]); }
              }
            }
            array_push($field_values,array(0,$row_ext[2],$field,$row_ext[8],$row_ext[7]));
          }
        }
      }

      # Token replacements
      foreach ($field_values as $field) {
        if ($fielddata && array_key_exists($field[1],$fielddata)) {
          $token = sprintf("@%s@", $fielddata[$field[1]]['token']);
          for ($indx=0; $indx < count($field_values); $indx++) {
            if (is_string($field_values[$indx][2]))
              $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],preg_replace('/'.$token.'/',$field[2],$field_values[$indx][2]),$field_values[$indx][3],$field_values[$indx][4]);
            if (is_string($field_values[$indx][4]))
              $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],$field_values[$indx][2],$field_values[$indx][3],preg_replace('/'.$token.'/',$field[2],$field_values[$indx][4]));
          }
        }
      }

      # Regexps
      for ($indx=0; $indx < count($field_values); $indx++) {
        if (is_string($field_values[$indx][2]) and $field_values[$indx][3]) {
          list($p,$r) = split('\^',$field_values[$indx][3]);
          $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],preg_replace('/'.$p.'/',$r,$field_values[$indx][2]),$field_values[$indx][3],$field_values[$indx][4]);
        }
      }

      # Formats
      for ($indx=0; $indx < count($field_values); $indx++) {
        if ($field_values[$indx][4] and $field_values[$indx][2]) {
          if (strtolower($field_values[$indx][2]) == "no") {
            $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],'',$field_values[$indx][3],$field_values[$indx][4]);
          } else {
            try {
              $formatstr = sprintf($field_values[$indx][4], $field_values[$indx][2]);
              $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],$formatstr,$field_values[$indx][3],$field_values[$indx][4]);
            } catch (Exception $e) {
              $field_values[$indx]=array($field_values[$indx][0],$field_values[$indx][1],$field_values[$indx][4],$field_values[$indx][3],$field_values[$indx][4]);
            }
          }
        }
      }

      $indx = 0;
      $tokenData = array();
      foreach ($field_values as $field) {
        if ($showtokens) {
          if (array_key_exists($field[1],$fielddata) && $fielddata[$field[1]]['show_conf'] == 1) {
            array_push($tokenData,array(sprintf("@%s@",$fielddata[$field[1]]['token']),sprintf("%s",$field[2])));
            if ($genparser) {
              $format = sprintf('s#@%s@#%%s#g', $fielddata[$field[1]]['token']);
            } else {
              $format = sprintf('"%s=%%s"',$fielddata[$field[1]]['token']);
            }
          } else {
            $format = NULL;
          }
        } else {
          if ($quote) { $format = ('"%s"'); } else { $format = ('%s'); }
        }
        if ($format) array_push($outdata, sprintf($format, $field[2]));
        $indx++;
      }
      if ($genparser) {
        if (!$noout) {
          foreach ($outdata as $str) echo $str."\n";
        }
      } else {
        if ($quiet) {
          if (!$noout) echo implode($outdata,$sep)."\n";
        } else {
          if (!$noout) echo implode($outdata,$sep).$sep."\n";
        }
      }
    }

    return $rc;
  }

  function getFieldData($tables=NULL,$cache_timeout=7200,$debug=False) {
    $fielddata = array();
    foreach ($tables as $table) {
      $query = "SELECT field_name,field_query,show_conf,format,fregexp,token,field_nullfk FROM field_descriptions WHERE table_name=".db_quote($table,'ro');
      $result = db_query_rest($query);
      if ($result) {
        while ($row = mysqli_fetch_row($result)) {
          if (strpos($table,".") > 0) { $fieldname = sprintf("%s.%s", $table, $row[0]); } else { $fieldname = $row[0]; }
          $fielddata[$fieldname] = array("query" => $row[1], "show_conf" => $row[2], "format" => $row[3], "fregexp" => $row[4], "token" => $row[5], "null" => $row[6]);
        }
      }
    }
    return $fielddata;
  }

?>
