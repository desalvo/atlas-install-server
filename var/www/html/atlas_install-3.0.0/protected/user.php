<?php

  require("db.php");
  require("config.php");
  require("combo.php");
  require("user_info.php");
?>
<?php if (!isset($_REQUEST["quiet"])) { ?>
<HTML>
<HEAD>

<?php require("../css/page_header.php"); page_header(".."); ?>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = usermanage_tbl.getElementsByTagName("tr");   
  for(i = 0; i < rows.length; i++){
    rows[i].style.backgroundColor='#AAFFAA';
  }
  if (form.user.value=='') {
    user_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.email.value=='') {
    email_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (form.role.value=='') {
    role_tr.style.backgroundColor='#AAAAAA';
    retval=false;
  }
  if (!retval) {
    alert('Please fill all the highlighted fields');
  }

  return retval;
}

//-->
</script>

<TITLE>LJSFi user management</TITLE>
</HEAD>

<BODY>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/req_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Software Installation User Management</h3>
<?php } ?>
<?php
  function send_approval_request($name, $id) {
    global $LJSFi_email, $LJSFi_VO;
    # Send the approval request email
    $subj='['.$LJSFi_VO.' LJSFi USER CHANGE REQUEST] Request of membership change for user '.$name;
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
    $link=dirname($_SERVER['FULL_URL']) . "/user.php?action=approve&id=" . $id;
    $body='The user '.$name." has requested one or more changes.\n"
         ."Follow this link to approve or deny the profile\n"
         .$link;
    $from = "From: ".$LJSFi_VO." LJSFi agent <".$LJSFi_email.">";
    $admin_list = get_user_info("emails",NULL,"master",True,"%");
    $email_list = array();
    foreach ($admin_list as $admin_info) { array_push($email_list,$admin_info["email"]); }
    $to = implode(",",$email_list);
    if ($to != "") {
      mail($to, $subj, $body, $from);
      // Update the mail log
      $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                  . "(SELECT ref FROM mail_type WHERE name='user'),"
                  . "'" . $to . "',"
                  . count($email_list) . ","
                  . "'" . date("Y-m-d H:i:s") . "')";
      $result = db_query($maillogqry);
    }
  }

  function send_notification($adminemail, $adminname, $useremail, $name, $id, $notifydata) {
    global $LJSFi_email,$LJSFi_VO;
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
    // Send the notification email to the admins
    $subj='['.$LJSFi_VO.' LJSFi USER CHANGE] Notification of membership change for user '.$name;
    $link=dirname($_SERVER['FULL_URL']) . "/user.php?action=approve&id=" . $id;
    $body='The membership of user '.$name." <".$useremail."> has been modified by "
         .$adminname." <".$adminemail."> with:\n\n".$notifydata."\n\n"
         ."Follow this link to further info\n"
         .$link;
    $from = "From: ".$LJSFi_VO." LJSFi agent <".$LJSFi_email.">";
    $admin_list = get_user_info("emails",NULL,"master",True,"%");
    $email_list = array();
    foreach ($admin_list as $admin_info) { array_push($email_list,$admin_info["email"]); }
    $to = implode(",",$email_list);
    if ($to != "") {
      mail(join(",",$to), $subj, $body, $from);
      // Update the mail log
      $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                  . "(SELECT ref FROM mail_type WHERE name='user'),"
                  . "'" . $to . "',"
                  . count($email_list) . ","
                  . "'" . date("Y-m-d H:i:s") . "')";
      $result = db_query($maillogqry);
    }
    // Send the notification email to the user
    $subj='['.$LJSFi_VO.' LJSFi USER CHANGE] Notification of membership change for user '.$name;
    $link=dirname($_SERVER['FULL_URL']) . "/user.php";
    $body='The membership of user '.$name." <".$useremail."> has been modified by "
         .$adminname." <".$adminemail."> with:\n\n".$notifydata."\n"
         ."Please follow this link for further changes\n"
         .$link;
    mail($useremail, $subj, $body, $from);
    // Update the mail log
    $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                . "(SELECT ref FROM mail_type WHERE name='user'),"
                . "'" . $to . "',"
                . "1,"
                . "'" . date("Y-m-d H:i:s") . "')";
    $result = db_query($maillogqry);
  }

  function show_admin_toolbar() {
    echo ('<FORM method="post" name="ljsfadmin" action="user.php">');
    echo ("\n");
    echo '<table id="toolbar_tbl" border="0" bgcolor="#ffffff" width="100%" cellpadding="1" rules="groups" class="adminbar">';
    echo '<COLGROUP width="100"></COLGROUP>';
    echo '<COLGROUP width="100"></COLGROUP>';
    echo '<tr><td align="center">';
    echo ('<button type="submit" name="action" value="list" style="background-color:transparent;height=:50" alt="List users"><img src="../img/listing.jpg" border="0"></button>');
    echo '</td><td align="center">';
    echo ('<button type="submit" name="action" value="search" style="background-color:transparent;height=:50" alt="Search users"><img src="../img/search.jpg" border="0"></button>');
    echo '</td><td>';
    echo '</td></tr>';
    echo '<tr><td class="parTextSmall">';
    echo 'List users';
    echo '</td><td class="parTextSmall">';
    echo 'Search users';
    echo '</td><td>';
    echo '</td></tr>';
    echo '</table>';
    echo ("\n");
  }

  ///////////////////////
  // MAIN PROGRAM
  ///////////////////////

  // Control flags
  $skip=0;
  // Status map
  $status_map = array("0" => "disabled", "1" => "enabled");
  // Unified identity source. Local sessions are not treated as certificates;
  // legacy variables are populated only for compatibility with the historic UI.
  $current_identity = atlas_current_identity();
  $auth_source = (string)($current_identity['source'] ?? 'none');
  $actor_role = (string)($current_identity['role'] ?? '');
  $actor_enabled = (int)($current_identity['enabled'] ?? 0) === 1;
  $actor_is_master = $actor_enabled && $actor_role === 'master';
  $actor_legacy_ref = (int)($current_identity['legacy_ref'] ?? 0);
  $sslusername = (string)($current_identity['username'] ?? '');
  $ssluserdetails = $auth_source === 'local' ? 'LOCAL:'.$sslusername : (string)($current_identity['dn'] ?? getenv("SSL_CLIENT_S_DN") ?: '');
  $ssluseremail = (string)($current_identity['email'] ?? getenv("SSL_CLIENT_S_DN_Email") ?: '');
  $sslcaname = $auth_source === 'certificate' ? (string)($current_identity['ca_name'] ?? '') : '';
  $sslca_dn = $auth_source === 'certificate' ? (string)($current_identity['ca_dn'] ?? '') : '';
  if (!isset($_POST["user"])) $_POST["user"] = $sslusername;
  if (!isset($_POST["email"]) || ((string)$_POST["email"] === '' && $ssluseremail !== '')) $_POST["email"] = $ssluseremail;
  $sslcertexpire = $auth_source === 'certificate' ? (string)($current_identity['cert_valid_end'] ?? getenv("SSL_CLIENT_V_END") ?: '') : '';
  if ($sslcertexpire !== '') $valid_expire=date('Y-m-d H:i:s',strtotime($sslcertexpire)); else $valid_expire=NULL;
  if (   !isset($sslusername)    || (isset($sslusername) && $sslusername == "")
      || !isset($ssluserdetails) || (isset($ssluserdetails) && $ssluserdetails == "")) {
      echo atlas_h(atlas_t('authentication_required'))."\n";
  } else {
    // Handle the data changes
    if (isset($_POST['submit'])) {
      $now = date('Y-m-d H:i:s');
      // Request summary, only in queit mode
      if (isset($_REQUEST['quiet'])) {
        echo "Request summary:\n";
        echo "User DN:        ".$ssluserdetails."\n";
        echo "User name:      ".$_POST["user"]."\n";
        echo "User email:     ".$_POST["email"]."\n";
        echo "Start validity: ".$now."\n";
        echo "End validity:   ".$valid_expire."\n";
      }
      // User request
      if (  !isset($_POST["user"])  || (isset($_POST["user"])  && $_POST["user"] == '')
         || !isset($_POST["email"]) || (isset($_POST["email"]) && $_POST["email"] == '')
         || !isset($_POST["role"])  || (isset($_POST["role"])  && $_POST["role"] == '')) {
        echo "Missing parameters. Cannot update user data\n";
      } else {
        // Get the current the user info
        $user_info = get_user_info();
        if (count($user_info) == 0) {
          // This is a new user, let's register it
          add_user($_POST["user"], $_POST["email"], $ssluserdetails,$_POST["role"]
                  ,isset($_POST["view"]),isset($_POST["insert"]),isset($_POST["update"])
                  ,isset($_POST["pin"]),isset($_POST["relsub"]),isset($_POST["critical"])
                  ,$now,$valid_expire,$auth_source==='certificate'?$sslca_dn:NULL,$auth_source==='certificate'?$sslcaname:NULL);
          $user_info = get_user_info();
          if (count($user_info) == 1) {
            send_approval_request($_POST["user"],$user_info[0]['ref']);
            echo "Your request has been submitted to the sysadmins for approval.";
            if (isset($_REQUEST['quiet'])) echo "\n"; else echo "<BR>";
          } else {
            echo "Unable to send the request. Please contact the administrators.";
            if (isset($_REQUEST['quiet'])) echo "\n"; else echo "<BR>";
          }
        } else {
          // This is an existing user, let's evaluate the requests
          $user_data = $user_info[0];
          // Prevent the disabled users (enabled=0) to access any function
          if ($user_data['enabled'] == 1) {
            // Update the user data
            // If we are approved masters we do not need any further approval, so set it all directly
            if ($user_data["role"] == "master" && $user_data["rolefk"]>0) $sep=""; else $sep="-";
            $needsapproval = False;
            if (isset($_POST["role"])   && $_POST["role"]   != "" && $_POST["role"]   != $user_data["role"]) {
              $upd_role = $sep.get_role_id($_POST["role"]);
              if ($sep == "-") $needsapproval = True;
            } else {
              $upd_role=NULL;
            }
            if (isset($_POST["email"])  && $_POST["email"]  != "" && $_POST["email"]  != $user_data["email"]) {
              $upd_email = $_POST["email"];
            } else {
              $upd_email=NULL;
            }
            // View privilege
            if (isset($_POST["view"])) $priv_view = 1; else $priv_view = 0;
            if ($priv_view != abs($user_data["priv_view"])) {
              $upd_view = $sep.$priv_view;
              if ($sep == "-" && $priv_view != 0) $needsapproval = True;
            }
            // Insert privilege
            if (isset($_POST["insert"])) $priv_insert = 1; else $priv_insert = 0;
            if ($priv_insert != abs($user_data["priv_insert"])) {
              $upd_insert = $sep.$priv_insert;
              if ($sep == "-" && $priv_insert != 0) $needsapproval = True;
            }
            // Update privilege
            if (isset($_POST["update"])) $priv_update = 1; else $priv_update = 0;
            if ($priv_update != abs($user_data["priv_update"])) {
              $upd_update = $sep.$priv_update;
              if ($sep == "-" && $priv_update != 0) $needsapproval = True;
            }
            // Pin privilege
            if (isset($_POST["pin"])) $priv_pin = 1; else $priv_pin = 0;
            if ($priv_pin != abs($user_data["priv_pin"])) {
              $upd_pin = $sep.$priv_pin;
              if ($sep == "-" && $priv_pin != 0) $needsapproval = True;
            }
            // Release subscription privilege
            if (isset($_POST["relsub"])) $priv_relsub = 1; else $priv_relsub = 0;
            if ($priv_relsub != abs($user_data["priv_relsub"])) {
              $upd_relsub = $sep.$priv_relsub;
              if ($sep == "-" && $priv_relsub != 0) $needsapproval = True;
            }
            // Release criticality set privilege
            if (isset($_POST["critical"])) $priv_critical = 1; else $priv_critical = 0;
            if ($priv_critical != abs($user_data["priv_critical"])) {
              $upd_critical = $sep.$priv_critical;
              if ($sep == "-" && $priv_critical != 0) $needsapproval = True;
            }
            // Start validity
            if (!$user_data["valid_start"]) {
              $upd_valid_start = $now;
            } else {
              $upd_valid_start = NULL;
            }
            // End validity
            if (isset($_POST["valid_end"]) && $_POST["valid_end"] != "" && $_POST["valid_end"] != $user_data["valid_end"]) {
              // This can be changed by the admins only
              $upd_valid_end = $_POST["valid_end"];
            } elseif ((!$user_data["valid_end"] && isset($valid_expire))
                   || (isset($valid_expire) && $user_data["valid_end"] && strtotime($valid_expire) > strtotime($user_data["valid_end"]))) {
              // This can be changed by any user
              $upd_valid_end = $valid_expire;
            } else {
              $upd_valid_end = NULL;
            }
            update_user($user_data['ref'], NULL, $upd_email, NULL, $upd_role
                       ,$upd_view, $upd_insert, $upd_update, $upd_pin, $upd_relsub
                       ,$upd_critical, $upd_valid_start, $upd_valid_end, NULL
                       ,$auth_source==='certificate'?$sslca_dn:NULL,$auth_source==='certificate'?$sslcaname:NULL);
            if ($needsapproval) {
              send_approval_request($_POST["user"],$user_data['ref']);
              echo "Your request has been submitted to the LJSFi administrators for approval.";
              if (isset($_REQUEST['quiet'])) echo "\n"; else echo "<BR>";
            } else {
              echo "Your request has been approved or no parameter has changed.";
              if (isset($_REQUEST['quiet'])) echo "\n"; else echo "<BR>";
            }
          } else {
            echo "Your user has been disabled. Please contact the LJSFi admins for further information.";
            if (isset($_REQUEST['quiet'])) echo "\n"; else echo "<BR>";
            $skip=1;
          }
        }
      }
    } elseif (isset($_POST['delete_user']) && isset($_POST['id'])) {
      $actor=$current_identity; $actor_ref=$actor_legacy_ref;
      if(!$actor_is_master) atlas_access_denied(atlas_t('master_required'));
      $target=(int)$_POST['id'];
      if($target<=0) {
        echo '<div class="atlas-alert error">'.atlas_h(atlas_lang()==='it'?'Identificatore utente non valido.':'Invalid user identifier.').'</div>';
      } elseif($target===$actor_ref) {
        echo '<div class="atlas-alert warning">'.atlas_h(atlas_lang()==='it'?'Non puoi cancellare l’utenza con cui sei attualmente autenticato.':'You cannot delete the account currently used for authentication.').'</div>';
      } else {
        soft_delete_user($target,$actor_ref?:NULL);
        echo '<div class="atlas-alert success">'.atlas_h(atlas_lang()==='it'?'Utente disabilitato e marcato come cancellato. I riferimenti storici sono stati preservati.':'User disabled and marked as deleted. Historical references were preserved.').'</div>';
      }
    } elseif (isset($_POST['validate']) && isset($_POST['aid'])) {
      // Authorization always comes from the unified current identity.
      // The hidden legacy ref is retained only for historical attribution and is
      // never trusted to decide whether the caller is a master.
      $admin_info = $actor_legacy_ref > 0 ? get_user_info('select',$actor_legacy_ref) : array();
      if ($actor_is_master) {
        $adminname  = (string)($current_identity['name'] ?? $current_identity['username'] ?? '');
        $adminemail = (string)($current_identity['email'] ?? '');
        if (count($admin_info) > 0) {
          if ($adminname === '') $adminname = (string)$admin_info[0]['name'];
          if ($adminemail === '') $adminemail = (string)$admin_info[0]['email'];
        }
        if (!isset($_POST["id"]) || (isset($_POST["id"]) && $_POST["id"] == "")) {
          echo "<FONT COLOR=red><B>Cannot find any user to validate</B></FONT><BR>";
        } else {
          $query_arr = array();
          $actions = "";
          $user_info = get_user_info('select',$_REQUEST["id"]);
          if (count($user_info) > 0) {
            $username       = $user_info[0]['name'];
            $useremail      = $user_info[0]['email'];
            $rolefk         = $user_info[0]['rolefk'];
            $role           = $user_info[0]['role'];
            $priv_view      = $user_info[0]['priv_view'];
            $priv_insert    = $user_info[0]['priv_insert'];
            $priv_update    = $user_info[0]['priv_update'];
            $priv_pin       = $user_info[0]['priv_pin'];
            $priv_relsub    = $user_info[0]['priv_relsub'];
            $priv_critical  = $user_info[0]['priv_critical'];
            $valid_start    = $user_info[0]['valid_start'];
            $valid_end      = $user_info[0]['valid_end'];
            $userenabled    = $user_info[0]['enabled'];
          }
          $upd_email = NULL;
          if (isset($_POST["email"])  && $_POST["email"]  != "" && $_POST["email"]  != $useremail) {
            $upd_email = $_POST["email"];
            $useremail = $_POST["email"];
            $actions .= "Email = ".$_POST["email"]."\n";
          }
          $upd_role = NULL;
          if (isset($_POST["role"])   && $_POST["role"]   != "") {
            $new_rolefk = get_role_id($_POST["role"]);
            if ($new_rolef != $rolefk) {
              $upd_role = $new_rolefk;
              $actions .= "Role = ".$_POST["role"]."\n";
            }
          }
          $upd_view = NULL;
          if (isset($_POST["view"])) $reqpriv_view = 1; else $reqpriv_view = 0;
          if ($priv_view != $reqpriv_view) {
            $upd_view = $reqpriv_view;
            $actions .= "View protected info = ".$status_map[$reqpriv_view]."\n";
          }
          $upd_insert = NULL;
          if (isset($_POST["insert"])) $reqpriv_insert = 1; else $reqpriv_insert = 0;
          if ($priv_insert != $reqpriv_insert) {
            $upd_insert = $reqpriv_insert;
            $actions .= "Post installation requests = ".$status_map[$reqpriv_insert]."\n";
          }
          $upd_update = NULL;
          if (isset($_POST["update"])) $reqpriv_update = 1; else $reqpriv_update = 0;
          if ($priv_update != $reqpriv_update) {
            $upd_update = $reqpriv_update;
            $actions .= "Restart installation tasks = ".$status_map[$reqpriv_update]."\n";
          }
          $upd_pin = NULL;
          if (isset($_POST["pin"])) $reqpriv_pin = 1; else $reqpriv_pin = 0;
          if ($priv_pin != $reqpriv_pin) {
            $upd_pin = $reqpriv_pin;
            $actions .= "Pin installed releases = ".$status_map[$reqpriv_pin]."\n";
          }
          $upd_relsub = NULL;
          if (isset($_POST["relsub"])) $reqpriv_relsub = 1; else $reqpriv_relsub = 0;
          if ($priv_relsub != $reqpriv_relsub) {
            $upd_relsub = $reqpriv_relsub;
            $actions .= "Subscribe releases = ".$status_map[$reqpriv_relsub]."\n";
          }
          $upd_critical = NULL;
          if (isset($_POST["critical"])) $reqpriv_critical = 1; else $reqpriv_critical = 0;
          if ($priv_critical != $reqpriv_critical) {
            $upd_critical = $reqpriv_critical;
            $actions .= "Set criticality of releases = ".$status_map[$reqpriv_critical]."\n";
          }
          $upd_valid_start = NULL;
          if (isset($_POST["valid_start"]) && $valid_start != $_POST["valid_start"]) {
            $upd_valid_start = $_POST["valid_start"];
            $actions .= "Membership start = ".$_POST["valid_start"]."\n";
          }
          $upd_valid_end = NULL;
          if (isset($_POST["valid_end"]) && $valid_end != $_POST["valid_end"]) {
            $upd_valid_end = $_POST["valid_end"];
            $actions .= "Membership end = ".$_POST["valid_end"]."\n";
          }
          $upd_enabled = NULL;
          if (isset($_POST["uenabled"]) && $userenabled != $_POST["uenabled"]) {
            $upd_enabled = $_POST["uenabled"];
            $actions .= "User status = ".$status_map[$_POST["uenabled"]]."\n";
          }
          if ($actions != "") {
            update_user($user_info[0]['ref'], NULL, $upd_email, NULL, $upd_role
                       ,$upd_view, $upd_insert, $upd_update, $upd_pin, $upd_relsub
                       ,$upd_critical, $upd_valid_start, $upd_valid_end, $upd_enabled);
            send_notification($adminemail, $adminname, $useremail, $username, $_POST["id"], $actions);
            $_REQUEST['action'] = "approve";
            echo "<FONT SIZE=+1 COLOR=green><B>You have successfully validated the user profile</B></FONT><BR>";
          } else {
            echo "<FONT COLOR=RED><B>No changes to validate</B></FONT><BR>";
          }
        }
      } else {
        echo "<FONT SIZE=+1 COLOR=RED><B>You have no privileges to validate user profile or your user has been disabled</B></FONT><BR>";
      }
    }

    // User info
    $user_info = get_user_info();
    if (count($user_info) > 0) {
      $_POST["email"] = $user_info[0]['email'];
      $rolefk         = $user_info[0]['rolefk'];
      $role           = $user_info[0]['role'];
      $priv_view      = $user_info[0]['priv_view'];
      $priv_insert    = $user_info[0]['priv_insert'];
      $priv_update    = $user_info[0]['priv_update'];
      $priv_pin       = $user_info[0]['priv_pin'];
      $priv_relsub    = $user_info[0]['priv_relsub'];
      $priv_critical  = $user_info[0]['priv_critical'];
      $valid_start    = $user_info[0]['valid_start'];
      $valid_end      = $user_info[0]['valid_end'];
      $uenabled       = $user_info[0]['enabled'];
    }

    // Only allow enabled users to access the page
    if (!isset($uenabled) || (isset($uenabled) && $uenabled == 1)) {
      // Check if we are trying to approve a user and if we are entitled to do that
      // Only masters can approve other members
      if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve") {  
        if ($actor_is_master) {
          $canapprove = True;
          $adminref = $actor_legacy_ref;
          if (isset($_REQUEST["id"]) && $_REQUEST["id"] != "") {
            $user_info = get_user_info('select',$_REQUEST["id"]);
            if (count($user_info) > 0) {
              $userid         = $user_info[0]['ref'];
              $_POST["user"]  = $user_info[0]['name'];
              $_POST["email"] = $user_info[0]['email'];
              $rolefk         = $user_info[0]['rolefk'];
              $role           = $user_info[0]['role'];
              $priv_view      = $user_info[0]['priv_view'];
              $priv_insert    = $user_info[0]['priv_insert'];
              $priv_update    = $user_info[0]['priv_update'];
              $priv_pin       = $user_info[0]['priv_pin'];
              $priv_relsub    = $user_info[0]['priv_relsub'];
              $priv_critical  = $user_info[0]['priv_critical'];
              $valid_start    = $user_info[0]['valid_start'];
              $valid_end      = $user_info[0]['valid_end'];
              $uenabled       = $user_info[0]['enabled'];
            }
          } else {
            echo "No user id specified<BR>";
          }
        } else {
          echo "<FONT SIZE=+1 COLOR=RED><B>You have no privileges to approve user profiles</B></FONT>";
          $canapprove = False;
        }
      }

      // Exit if we are in quiet mode
      if (isset($_REQUEST['quiet'])) exit();

      // Display the form, if we are entitled
      if (!isset($_REQUEST['action']) || (isset($_REQUEST['action']) && ($_REQUEST['action'] == "approve" && $canapprove))) {

        // Header
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve") {
          echo ('<FONT SIZE=+1 COLOR=GREEN><B>Please validate the user profile</B></FONT>');
        } elseif (isset($rolefk)) {
          echo ('Please modify your membership parameters');
        } else {
          echo ('Please register to LJSFi');
        }

        // Admin toolbar
        if ($actor_is_master || (isset($canapprove) && $canapprove)) show_admin_toolbar();

        // Start the form
        echo '<form method="post" name="manageuser" action="user.php" onsubmit="return checkform(this);">';
        echo '<TABLE id="usermanage_tbl" border="1" rules="groups" summary="User Management interface" class="whitetable">';
        echo '<P>';
        echo '</FONT></CENTER></EM></CAPTION>';
        echo '<COLGROUP width="350" span=2></COLGROUP>';

        // Username
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve") {  
          echo '<TR id="user_tr"><TD class="selection">User name</TD><TD>'.$_POST["user"].'</TD></TR>';
        } else {
          echo '<TR id="user_tr"><TD class="selection">Your name</TD><TD><input type="hidden" name="user" size=60 value="';
          echo $_POST["user"] . '">' . $_POST["user"] . '</TD></TR>';
        }
 
        // Email
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve") {  
          echo '<TR id="email_tr"><TD class="selection">User e-mail</TD><TD><input type="text" name="email" size=60 value="'.$_POST["email"].'"></TD></TR>';
        } else {
          echo '<TR id="email_tr"><TD class="selection">Your e-mail</TD><TD><input type="text" name="email" size=60 value="';
          echo $_POST["email"] . '"></TD></TR>';
        }

        // X.509 issuer binding (read-only). Local accounts deliberately have no CA.
        if ($auth_source === 'certificate') {
          echo '<TR><TD class="selection">'.atlas_h(atlas_t('certificate_authority')).'</TD><TD>'.atlas_h($sslcaname!==''?$sslcaname:$sslca_dn).'</TD></TR>';
          if($sslca_dn!=='') echo '<TR><TD class="selection">'.atlas_h(atlas_t('ca_dn')).'</TD><TD><span class="atlas-code">'.atlas_h($sslca_dn).'</span></TD></TR>';
        }

        // Role
        $res = db_query("SELECT ref,description FROM role ORDER BY ref");
        $role_list = array();
        while ($row = mysqli_fetch_array($res)) {
          // combo_box expects a dense zero-based array. Do not derive array
          // indexes from DB refs: deleted/migrated role rows may leave gaps.
          $role_list[] = $row[1];
        }
        if (!isset($rolefk) || !isset($role) || $role === '') $role = $role_list[0] ?? '';
        echo ("<TR id='role_tr'><TD class='selection'>Role</TD><TD><select name='role' size='1'>");
        combo_box($role_list,"-- select one --",$role);
        echo '</select>';
        if ($rolefk < 0) echo "[NOT YET APPROVED]";
        echo '</TD></TR>';

        // View privilege
        echo '<TR id="vp_tr"><TD class="selection">View protected info</TD><TD><input type="checkbox" name="view" value="1"';
        if (abs($priv_view) == 1) echo " checked";
        echo '>';
        if ($priv_view < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Insert privilege
        echo '<TR id="ip_tr"><TD class="selection">Post installation requests</TD><TD><input type="checkbox" name="insert" value="1"';
        if (abs($priv_insert) == 1) echo " checked";
        echo '>';
        if ($priv_insert < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Update privilege
        echo '<TR id="up_tr"><TD class="selection">Restart installation tasks</TD><TD><input type="checkbox" name="update" value="1"';
        if (abs($priv_update) == 1) echo " checked";
        echo '>';
        if ($priv_update < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Pin privilege
        echo '<TR id="up_tr"><TD class="selection">Pin installed releases</TD><TD><input type="checkbox" name="pin" value="1"';
        if (abs($priv_pin) == 1) echo " checked";
        echo '>';
        if ($priv_pin < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Release subscription privilege
        echo '<TR id="up_tr"><TD class="selection">Subscribe releases</TD><TD><input type="checkbox" name="relsub" value="1"';
        if (abs($priv_relsub) == 1) echo " checked";
        echo '>';
        if ($priv_relsub < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Release criticality set privilege
        echo '<TR id="up_tr"><TD class="selection">Set criticality of releases</TD><TD><input type="checkbox" name="critical" value="1"';
        if (abs($priv_critical) == 1) echo " checked";
        echo '>';
        if ($priv_critical < 0) echo "[NOT YET APPROVED]";
        echo "</TD></TR>\n";

        // Validities
        $now = date('Y-m-d H:i:s');
        echo '<TR id="vs_tr"><TD class="selection">Membership start</TD><TD>';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) echo '<input type="text" name="valid_start" value="';
        if (isset($valid_start)) echo $valid_start; else echo $now;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) echo '">';
        echo "</TD></TR>\n";
        echo '<TR id="ve_tr"><TD class="selection">Membership end</TD><TD>';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) echo '<input type="text" name="valid_end" value="';
        if (isset($valid_end)) {
          echo $valid_end;
          if (!isset($_REQUEST['action']) && isset($valid_expire) && strtotime($valid_expire) > strtotime($valid_end)) {
          echo " <FONT COLOR='RED'><B><BR>PLEASE HIT SUBMIT TO EXTEND YOUR MEMBERSHIP TO ".$valid_expire."</B></FONT>";
          }
        } elseif (!isset($_REQUEST['action']) && isset($valid_expire)) {
          echo $valid_expire;
        }
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) echo '">';
        echo "</TD></TR>\n";

        // User status
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) {
          echo '<TR id="us_tr"><TD class="selection">User status</TD><TD><select name="uenabled" size="1">';
          $user_status_list = array(0 => "disabled", "1" => "enabled");
          foreach ($user_status_list as $us_id => $us_val) {
            echo "<option value='".$us_id."'";
            if ($uenabled == $us_id) echo " selected";
            echo ">".$us_val."</option>";
          }
          echo '</select>';
          echo "</TD></TR>\n";
        }

        // Close the table
        echo '</TABLE>';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "approve" && $canapprove) {
          echo '<input type="hidden" name="id" value="'.$userid.'">';
          echo '<input type="hidden" name="aid" value="'.$actor_legacy_ref.'">';
          echo '<input type="submit" name="validate" value="Validate">';
          $actorNow=atlas_current_identity();
          if(($actorNow['role']??'')==='master' && (int)$userid !== (int)($actorNow['legacy_ref']??0) && !str_starts_with((string)($user_info[0]['dn']??''),'LOCAL:')) {
            $confirm=atlas_lang()==='it'?'Cancellare questa utenza? I riferimenti storici saranno preservati.':'Delete this user? Historical references will be preserved.';
            $label=atlas_lang()==='it'?'Cancella utente (preserva storico)':'Delete user (preserve history)';
            echo '<button type="submit" name="delete_user" value="1" class="atlas-danger-button" onclick="return confirm(\''.atlas_h($confirm).'\')">'.atlas_h($label).'</button>';
          }
        } else {
          echo '<input type="submit" name="submit" value="Submit">';
          echo '<input type="reset" value="Reset">';
        }
        echo '</form>';
      } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == "list" && $actor_is_master) {
        // Admin toolbar
        if ($actor_is_master) show_admin_toolbar();

        echo '<TABLE id="userlist_tbl" border="1" rules="groups" summary="User List">';
        echo '<COLGROUP width="250"></COLGROUP>';
        echo '<COLGROUP width="250"></COLGROUP>';
        echo '<COLGROUP width="250"></COLGROUP>';
        echo '<COLGROUP width="100"></COLGROUP>';
        echo '<COLGROUP width="100"></COLGROUP>';
        echo '<COLGROUP width="150"></COLGROUP>';
        echo '<TH class="tablehead">User Name</TH>';
        echo '<TH class="tablehead">Email</TH>';
        echo '<TH class="tablehead">DN</TH>';
        echo '<TH class="tablehead">Status</TH>';
        echo '<TH class="tablehead">Enabled</TH>';
        echo '<TH class="tablehead">Valid until</TH>';
        if (isset($_REQUEST["filter"]) && $_REQUEST["filter"] != "") $filter = $_REQUEST["filter"]; else $filter = NULL;
        $user_info = get_user_info($mode='count',NULL,NULL,NULL,$filter);
        $numrows = $user_info[0]['counts'];
        echo $numrows." record(s) found<BR>";
        // Paginate every 15 users
        $pagesize = 15;
        $pagenum  = 1;
        if (isset($_REQUEST["page"]) && $_REQUEST["page"] > 0) $pagenum = $_REQUEST["page"];
        $maxpages = floor($numrows / $pagesize) + 1;
        if ($pagenum > $maxpages) $pagenum = $maxpages;
        $offset   = ($pagenum - 1) * $pagesize;
        $rowtype = 1;
        $user_info = get_user_info('list',NULL,NULL,NULL,$filter,$pagesize,$offset);
        foreach ($user_info as $user_data) {
          if ($rowtype == 1) $rowtype = 2; else $rowtype = 1;
          echo '<TR class="userlist'.$rowtype.'"><TD><A HREF="user.php?action=approve&id='.$user_data['ref'].'">'.$user_data['name'].'</A></TD>';
          echo '<TD><A HREF="mailto:'.$user_data['email'].'">'.$user_data['email'].'</A></TD>'."\n";
          echo '<TD>'.$user_data['dn'].'</TD>'."\n";
          if (!empty($user_data['deleted_at'])) {
            echo '<TD align="center"><FONT COLOR="#666666"><B>DELETED</B></FONT></TD>'."\n";
          } elseif ($user_data['enabled'] == 1 && strtotime($user_data['valid_end']) > time()) {
            echo '<TD align="center"><FONT COLOR="GREEN"><B>ACTIVE</B></FONT></TD>'."\n";
          } else {
            echo '<TD align="center"><FONT COLOR="RED"><B>EXPIRED</B></FONT></TD>'."\n";
          }
          echo '<TD align="center">'.$status_map[$user_data['enabled']].'</TD>'."\n";
          echo '<TD align="center">'.$user_data['valid_end'].'</TD></TR>'."\n";
        }
        echo "</TABLE>\n";
        // Show the page navigator
        $minpage = $pagenum - 5;
        $maxpage = $pagenum + 5;
        if ($minpage < 1) $minpage = 1;
        if ($maxpage > $maxpages) $maxpage = $maxpages;
        if ($pagenum != 1) {
          echo '<A HREF="user.php?action=list&page=1';
          if ($filter) echo "&filter=".$filter;
          echo '">';
        }
        echo '[first]';
        if ($pagenum != 1) echo '</A>';
        echo ' - ';
        for ($i=$minpage; $i<=$maxpage; $i++) {
          if ($i != $pagenum) {
            echo '<A HREF="user.php?action=list&page='.$i;
            if ($filter) echo "&filter=".$filter;
            echo '">';
          }
          echo '['.$i.'] ';
          if ($i != $pagenum) echo "</A>";
        }
        if ($pagenum != $maxpages) {
          echo ' - <A HREF="user.php?action=list&page='.$maxpages;
          if ($filter) echo "&filter=".$filter;
          echo '">';
        }
        echo '[last]';
        if ($pagenum != $maxpages) echo '</A>';
      } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == "search" && $actor_is_master) {
        // Admin toolbar
        if ($actor_is_master) show_admin_toolbar();

        echo '<form method="post" name="searchuser" action="user.php">';
        echo '<TABLE id="usersearch_tbl" border="1" rules="groups" summary="User Search">';
        echo '<COLGROUP width="200"></COLGROUP>';
        echo '<COLGROUP width="400"></COLGROUP>';
        echo '<TR class="graytable"><TD class="parText"><EM>User Name</EM><BR>(use % as wildcard)</TD><TD><INPUT TYPE="text" size="60" NAME="filter"></TD></TD></TR>';
        echo "</TABLE>\n";
        echo '<input type="submit" name="submit" value="Search">';
        echo '</form>';
      }
    } else {
      if ($skip == 0) echo "Your user has been disabled. Please contact the LJSFi admins for further information.<BR>";
    }
  }
?>
<?php if (!isset($_REQUEST["quiet"])) { ?>

      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
<?php } ?>
