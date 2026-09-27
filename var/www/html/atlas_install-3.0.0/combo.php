<?php
function combo_box ($values="",$def_choice="-",$selection=""){
    echo '<option value="" ';
    if ($selection == "") echo ' selected';
    echo '>' . $def_choice . '</option>';
    for ($i=0;$i<count($values);$i++) {
      echo '<option value="',$values[$i],'"';
      if ($selection == $values[$i]) echo 'selected';
      echo '>',$values[$i],'</option>';
      echo "\n";
    }
}
?>
