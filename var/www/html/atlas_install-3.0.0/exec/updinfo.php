<?php

  require("db.php");

  function set_site_info() {
    if (!isset($_POST['cename']) || trim((string)$_POST['cename']) === '') return 0;
    $cename = trim((string)$_POST['cename']);
    $stmt = db_prepare_execute('SELECT ref FROM site WHERE cename=? ORDER BY ref DESC LIMIT 1', [$cename], 's', 'ro');
    $row = $stmt->get_result()->fetch_row();
    if (!$row) return 0;

    $allowedStrings = ['sitename'=>'name','cs'=>'cs','swarea'=>'swarea','fstype'=>'fstype','mountpoint'=>'mountpoint','osname'=>'osname','osrel'=>'osrelease','osver'=>'osversion'];
    $sets=[]; $params=[]; $types='';
    foreach ($allowedStrings as $input=>$column) {
      if (isset($_POST[$input]) && $_POST[$input] !== '') { $sets[]="$column=?"; $params[]=(string)$_POST[$input]; $types.='s'; }
    }
    foreach (['attr','available','quota'] as $input) {
      if (isset($_POST[$input]) && $_POST[$input] !== '') {
        if (!is_numeric($_POST[$input])) { http_response_code(400); exit("Invalid numeric field\n"); }
        $sets[]="$input=?"; $params[]=(float)$_POST[$input]; $types.='d';
      }
    }
    if (isset($_POST['capacity']) && $_POST['capacity'] !== '') {
      $raw=(string)$_POST['capacity'];
      if (str_ends_with($raw,'%')) {
        $pct=substr($raw,0,-1);
        if (!is_numeric($pct) || !isset($_POST['available']) || !is_numeric($_POST['available'])) { http_response_code(400); exit("Invalid capacity\n"); }
        $capacity=((float)$pct * (float)$_POST['available'])/100.0;
      } else {
        if (!is_numeric($raw)) { http_response_code(400); exit("Invalid capacity\n"); }
        $capacity=(float)$raw;
      }
      $sets[]='capacity=?'; $params[]=$capacity; $types.='d';
    }
    if (!$sets) return 0;
    $params[]=$cename; $types.='s';
    db_prepare_execute('UPDATE site SET '.implode(',',$sets).' WHERE cename=?', $params, $types, 'rw');
    echo "INSTALL SERVER> Site info updated successfully for CE ".$cename.".\n";
    return 0;
  }

  if (isset($_POST["cename"]) && $_POST["cename"] != "") set_site_info();
?>
