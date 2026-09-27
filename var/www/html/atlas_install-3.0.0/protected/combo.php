<?php

function combo_box ($values="",$def_choice="-",$selection="",$descr="",$has_header=true){
    $has_selection = false;
    if ($descr == "") {
      $descr=$values;
    }
    if ($has_header) {
      echo '<option value="" ';
      if ($selection == "") echo ' selected';
      echo '>' . $def_choice . '</option>';
    }
    for ($i=0;$i<count($values);$i++) {
      echo '<option value="',$values[$i],'"';
      if ($selection == $values[$i]) {
        echo 'selected';
        $has_selection = true;
      }
      if (strpos($descr[$i],"@")) {
        $descr_data = explode("@", $descr[$i]);
        if (strpos($descr_data[1],"/")) {
          $descr_data = explode("/", $descr_data[1]);
          $descr_val  = $descr_data[0];
        } else {
          $descr_val  = $descr_data[1];
        }
      } else {
        $descr_val  = $descr[$i];
      }
      echo '>',$descr_val,'</option>';
      echo "\n";
    }
    return $has_selection;
}
?>
