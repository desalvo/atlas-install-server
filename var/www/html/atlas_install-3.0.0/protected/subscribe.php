<HTML>
<HEAD>

<?php

  require("db.php");
  require("combo.php");
?>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = subscribe_tbl.getElementsByTagName("tr");   
  for(i = 0; i < rows.length; i++){
    rows[i].style.backgroundColor='#EFEFEF';
  }
  if (form.sitename.value=='') {
    sitename_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (form.user.value=='') {
    user_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (form.email.value=='') {
    email_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (!retval) {
    alert('Please fill all the highlighted fields');
  }

  return retval;
}

function pageReload() {
  subscribe.action='subscribe.php';
  subscribe.submit();
}

//-->
</script>

<TITLE>Notifications from the <?php echo $LJSFi_VO; ?> Software Installation System</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
</HEAD>
<BODY>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/rai_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3>Subscribe to notifications</h3>
<form method="post" name="subscribe" action="subscribe.php" onsubmit="return checkform(this);">
<TABLE id='subscribe_tbl' border="1" rules="groups" summary="Subscribe to notifications">
<COLGROUP width="130"></COLGROUP>
<COLGROUP width="470"></COLGROUP>
<?php
  // Site name combo box
  echo ("<TR id='sitename_tr' class='graytable'><TD><EM>Site Name</EM></TD><TD>");
  echo ("<select name='sitename' size='1' onchange=\"pageReload();\">");
  $sitename=array();
  $query = "SELECT DISTINCT site.name"
         . "  FROM site"
         . " WHERE site.name <> ''"
         . " ORDER BY site.name";
  $result = db_query($query);
  array_push($sitename, "ALL");
  while ($row = mysqli_fetch_array($result)) {
    array_push($sitename, $row[0]);
  }
  combo_box($sitename,"-- select one --",$_POST["sitename"]);
  echo '</select></TD></TR>';
  echo '<TR class="graytable"><TD></TD><TD>Choose "ALL" to subscribe to all notifications</TD></TR>';

  // Username
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  if (!$_POST["user"]) $_POST["user"] = $sslusername;
  echo '<TR id=\'user_tr\' class="graytable"><TD><EM>Your name</EM></TD><TD><input type="text" name="user" size=60 value="';
  echo $_POST["user"] . '"></TD>';

  // Email
  $res = db_query("SELECT email FROM user WHERE name=" . db_quote($_POST["user"],'rw') . " AND dn=" . db_quote($ssluserdetails,'rw'));
  $row = mysqli_fetch_row($res);
  if ($row) $_POST["email"]=$row[0];
  echo '<TR id=\'email_tr\' class="graytable"><TD><EM>Your e-mail</EM></TD><TD><input type="text" name="email" size=60 value="';
  echo $_POST["email"];
  echo '"></TD>';

  if (isset($_POST['subscribe'])) {
    # Check for the user id
    $userfk = -1;
    $userquery = "SELECT user.ref, user.name, user.dn"
               . "  FROM user"
               . " WHERE user.name=".db_quote($_POST["user"],'rw')
               . "   AND user.dn=".db_quote($ssluserdetails,'rw');
    $res = db_query($userquery);
    $row = mysqli_fetch_row($res);
    if ($row) {
      $userfk = $row[0];
    } else {
      $userins = "INSERT INTO user (name, dn, email)"
                        . " VALUES (".db_quote($_POST["user"],'rw')
                               . ", ".db_quote($ssluserdetails,'rw')
                               . ", ".db_quote($_POST["email"],'rw').")";
      $res = db_query($userins);
      $res = db_query($userquery);
      $row = mysqli_fetch_row($res);
      if ($row) $userfk=$row[0];
    }
    # Insert the subscription
    if ($userfk > 0 && isset($_POST["sitename"]) && $_POST["sitename"] != '') {
      if ($_POST["sitename"] == "ALL") {
        $sitename = '*';
      } else {
        $sitename = $_POST["sitename"];
      }
      $subscrquery = "SELECT ref "
                   . "  FROM subscription"
                   . " WHERE sitename=".db_quote($sitename,'rw')
                   . "   AND userfk=".$userfk;
      $res = db_query($subscrquery);
      $row = mysqli_fetch_row($res);
      echo '<TR><TD>&nbsp;</TD><TD>';
      if (!$row) {
        $query = "INSERT INTO subscription"
                    .   " SET sitename=".db_quote($sitename,'rw').", userfk=".db_int($userfk,1);
        $res = db_query($query);
        echo 'You are have successfully subscribed to '.$_POST["sitename"];
      } else {
        echo 'You are already subscribing to '.$_POST["sitename"];
      }
      echo '</TD></TR>';
    }
  }
?>
</TABLE>
<input type="submit" name="subscribe" value="Subscribe">
</form>

<p>
<h3>Current subscriptions</h3>
<form method="post" name="updatesubscription" action="subscribe.php">
<TABLE border="1" rules="groups" summary="Subscriptions">
<COLGROUP width="300"></COLGROUP>
<TR class="graytable"><TD colspan="2">
<?php
  if (isset($_POST['updateaction'])) {
    # Check for the user id
    $userfk = -1;
    $userquery = "SELECT user.ref, user.name, user.dn"
               . "  FROM user"
               . " WHERE user.name=".db_quote($_POST["user"],'rw')
               . "   AND user.dn=".db_quote($ssluserdetails,'rw');
    $res = db_query($userquery);
    $row = mysqli_fetch_row($res);
    if ($row) $userfk = $row[0];
    if ($userfk > 0) {
      # Remove the selected subscriptions
      while (list ($key,$val) = @each ($_POST['subscriptions'])) {
        $value = $val;
        if ($val == "ALL") $value = '*';
        $subscrquery = "SELECT ref "
                     . "  FROM subscription"
                     . " WHERE sitename=".db_quote($value,'rw')
                     . "   AND userfk=".$userfk;
        $res = db_query($subscrquery);
        $row = mysqli_fetch_row($res);
        echo '<CENTER>';
        if ($row) {
          $query = "DELETE FROM subscription"
                      . " WHERE ref=".db_int($row[0],1);
          $res = db_query($query);
          echo 'You are have successfully removed the subscription to '.$val;
        } else {
          echo 'You are not subscribing to '.$val;
        }
        echo '</CENTER></TD></TR>';
        echo '<TR><TD colspan="2"><HR>';
      }

      # Load the subscription map
      $subscrquery = "SELECT ref, sitename, immediate, summary FROM subscription WHERE userfk=".db_int($userfk,1);
      $res = db_query($subscrquery);
      $subflags = array();
      while ($row = mysqli_fetch_row($res)) {
        $subflags[$row[1]] = array("ref" => $row[0], "immediate" => $row[2], "summary" => $row[3]);
      }

      # Manage the immediate flags
      $newflags = array();
      foreach ($subflags as $site => $flags) {
        $newflags[$site] = array("immediate" => 0, "summary" => 0);
      }
      while (list ($key,$val) = @each ($_POST['immediate'])) {
        $value = $val;
        if ($val == "ALL") $value = '*';
        $newflags[$value]["immediate"] = 1;
      }
      while (list ($key,$val) = @each ($_POST['summary'])) {
        $value = $val;
        if ($val == "ALL") $value = '*';
        $newflags[$value]["summary"] = 1;
      }
      foreach ($subflags as $site => $flags) {
        $query_flags = array();
        if ($flags["immediate"] != $newflags[$site]["immediate"]) array_push($query_flags, "immediate=".$newflags[$site]["immediate"]);
        if ($flags["summary"] != $newflags[$site]["summary"]) array_push($query_flags, "summary=".$newflags[$site]["summary"]);
        if ($query_flags) {
          $query = "UPDATE subscription SET ".join(",",$query_flags)
                      . " WHERE ref=".db_int($flags["ref"],1);
          $res = db_query($query);
        }
      }
    }
  }
?>
<TABLE border="0" rules="groups" summary="Subscriptions list">
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="100"></COLGROUP>
<TH align="left">Site</TH>
<TH align="center">Immediate</TH>
<TH align="center">Summary</TH>
<TH align="center">Remove</TH>
<TBODY>
<?php
  // Current subscriptions
  $colors = array('#DFFFDF','#DFDFFF');
  $query = "SELECT DISTINCT(sitename),immediate,summary"
         . "  FROM user, subscription"
         . " WHERE subscription.userfk = user.ref"
         . "   AND user.dn = ".db_quote($ssluserdetails,'ro')
         . "   AND user.name = ".db_quote($_POST['user'],'ro')
         . " ORDER BY sitename";
  $result = db_query($query);
  $indx   = 0;
  while ($row = mysqli_fetch_array($result)) {
    echo '<TR><TD bgcolor="'.$colors[$indx%2].'">';
    if ($row[0]=="*") {
      $value = "ALL";
    } else {
      $value = $row[0];
    }
    echo $value;
    echo '</TD><TD bgcolor="'.$colors[$indx%2].'"><CENTER>';
    if ($row[1] > 0) { $opt = " checked"; } else { $opt = ""; }
    echo '<input type=checkbox name=immediate[] value="'.$value.'"'.$opt.'>';
    echo '</CENTER>';
    echo '</TD><TD bgcolor="'.$colors[$indx%2].'"><CENTER>';
    if ($row[2] > 0) { $opt = " checked"; } else { $opt = ""; }
    echo '<input type=checkbox name=summary[] value="'.$value.'"'.$opt.'>';
    echo '</CENTER>';
    echo '</TD><TD bgcolor="'.$colors[$indx%2].'"><CENTER>';
    echo '<input type=checkbox name=subscriptions[] value="'.$value.'">';
    echo '</CENTER></TD></TR>';
    $indx += 1;
  }
  echo '</TABLE>';
?>

</TD></TR></TABLE>
<input type="submit" name="updateaction" value="Update">
</form>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
