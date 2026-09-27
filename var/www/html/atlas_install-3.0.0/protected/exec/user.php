<?php

  require("../db.php");
  require('../user_info.php'); // User informations

  date_default_timezone_set('Europe/Rome');

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

  if (isset($userref)) {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
      case 'GET':
        switch ($_REQUEST["mode"]) {
          case 'info':
            $enabled = array(0 => "false", 1 => "true");
            echo "UserName:   ".$user_info[0]['name']."\n";
            echo "DN:         ".$user_info[0]['dn']."\n";
            echo "Role:       ".$user_info[0]['role']."\n";
            echo "Expiration: ".$user_info[0]['valid_end']."\n";
            echo "Enabled:    ".$enabled[$user_info[0]['enabled']]."\n";
            break;
        }
        break;
    }
  } else {
    $rc = array(500,"Insufficient privileges for user ".$ident);
  }
  header('X-PHP-Response-Code: '.$rc[0], true, $rc[0]);
  if ($rc[0] != 200 && $rc[1] != NULL) {
    echo $rc[1]."\n";
  }

?>
