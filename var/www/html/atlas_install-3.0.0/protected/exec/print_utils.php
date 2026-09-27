<?php

  function print_mysql_table ($result=NULL, $data=NULL, $fields=NULL, $quiet=False) {
    if ($data != NULL) {
      $numrows = count($data);
    } else {
      $numrows = mysqli_num_rows($result);
    }
    if ($numrows > 0) {
      if ($quiet) {
        if ($data != NULL) {
          foreach ( $data as $row ) {
            $tablerow = array();
            foreach ($row as $rowdata) {
              if ($rowdata == NULL) {
                array_push($tablerow,"None");
              } else {
                array_push($tablerow,$rowdata);
              }
            }
            echo implode(", ",$tablerow)."\n";
          }
        } else {
          while ( $row = mysqli_fetch_row($result) ) {
            $tablerow = array();
            foreach ($row as $rowdata) {
              if ($rowdata == NULL) {
                array_push($tablerow,"None");
              } else {
                array_push($tablerow,$rowdata);
              }
            }
            echo implode(", ",$tablerow)."\n";
          }
        }
      } else {
        $table = array();
        $tps = array();
        if ($fields != NULL) {
          $numfields = count($fields);
        } else {
          $fields = array();
          $numfields = mysqli_num_fields($result);
          for ($i=0; $i<$numfields; $i++) {
            $field_name = mysqli_fetch_field_direct($result,$i)->name;
            array_push($fields,$field_name);
          }
        }
        for ($i=0; $i<$numfields; $i++) {
          array_push($tps,strlen($field_name));
        }
        if ($data != NULL) {
          foreach ($data as $row) {
            array_push($table,$row);
            for ($i=0; $i<$numfields; $i++) {
              if (strlen($row[$i]) > $tps[$i]) $tps[$i] = strlen($row[$i]);
            }
          }
        } else {
          while ( $row = mysqli_fetch_row($result) ) {
            array_push($table,$row);
            for ($i=0; $i<$numfields; $i++) {
              if (strlen($row[$i]) > $tps[$i]) $tps[$i] = strlen($row[$i]);
            }
          }
        }
        $format = "|";
        $hline = "+";
        for ($i=0; $i<$numfields; $i++) {
          $format .= " %".$tps[$i]."s |";
          $hline .= "-";
          for ($j=0; $j<$tps[$i]; $j++) {
            $hline .= "-";
          }
          $hline .= "-+";
        }
        $format .= "\n";
        $hline .= "\n";
        echo $hline;
        vprintf ($format, $fields);
        echo $hline;
        foreach ($table as $row) {
          $tablerow = array();
          foreach ($row as $data) {
            if ($data == NULL) {
              array_push($tablerow,"None");
            } else {
              array_push($tablerow,$data);
            }
          }
          vprintf ($format, $tablerow);
        }
        echo $hline;
        if ($numrows > 1) {
          echo "$numrows records selected\n";
        } elseif ($numrows == 1) {
          echo "$numrows record selected\n";
        }
      }
    }
    return array(200,"OK");
  }
?>
