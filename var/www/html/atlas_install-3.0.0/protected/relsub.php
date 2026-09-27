<HTML>
<HEAD>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = relsub_tbl.getElementsByTagName("tr");   
  for(i = 0; i < rows.length; i++){
    rows[i].style.backgroundColor='#EFEFEF';
  }
  if (form.sitename.value=='') {
    sitename_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (form.rel.value=='') {
    rel_tr.style.backgroundColor='#FFFFAA';
    retval=false;
  }
  if (!retval) {
    alert('Please fill all the highlighted fields');
  }

  return retval;
}

function pageReload() {
  relsub.action='relsub.php';
  relsub.submit();
}

//-->
</script>

<?php

  require("db.php");
  require("config.php");
  require("user_info.php");
  require("combo.php");
  require ("$LJSFi_PATH/access_log.php");
?>

<TITLE>Release subscription management for the <?php echo $LJSFi_VO; ?> Software Installation System</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
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
        <h3>Add or remove release subscriptions to releases</h3>

<?php
  // Username
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  if (!isset($_POST["user"]) || $_POST["user"] == "") $_POST["user"] = $sslusername;
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Unknown user. Please <A HREF='user.php'>register to LJSFi</A> first.</B></FONT>");
  } elseif ($user_info[0]['enabled'] == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Your user is disabled. Please contact the LJSFi admin.</B></FONT>");
  } elseif ($user_info[0]['priv_relsub'] == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Insufficient privileges to subscribe releases. Please <A HREF='user.php'>update your registration</A>.</B></FONT>");
  } else {
    $user_data = $user_info[0];
?>
<form method="post" name="relsub" action="relsub.php" onsubmit="return checkform(this);">
<TABLE id='relsub_tbl' border="1" rules="groups" summary="Add release subscriptions">
<CAPTION><EM><CENTER><FONT SIZE=+2>
<P>
</FONT></CENTER></EM></CAPTION>
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
    while ($row = mysqli_fetch_array($result)) {
      array_push($sitename, $row[0]);
    }
    combo_box($sitename,"-- select one --",$_POST["sitename"]);
    echo '</select></TD></TR>';
    echo '<TR class="graytable"><TD></TD><TD>Select a site name to list all the release subscriptions</TD></TR>';

    // Pattern
    if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
      echo ("<TR id='pattern_tr' class='graytable'><TD><EM>Pattern</EM></TD><TD>");
      echo ('<input type="text" name="pattern" size=60></TD></TR>');
    }

  
    if (isset($_POST['add'])) {
      # Get the user id
      $userfk = $user_data['ref'];
      # Add the release subscriptions
      if ($userfk >= 0 && isset($_POST["sitename"]) && $_POST["sitename"] != ''
                       && isset($_POST["pattern"]) && $_POST["pattern"] != '') {
        $date = date("Y-m-d H:i:s");
        $query = "INSERT INTO release_subscription (sitename,userfk,pattern,date) VALUES"
               . " ('".$_POST["sitename"]."',".$userfk.",'".$_POST["pattern"]."','".$date."')";
        $res = db_query($query);
        echo 'Pattern '.$_POST['pattern'].' successfully added to site '.$_POST["sitename"];

        # Send the notification email
        $subj='[ADD SUBSCRIPTION] Pattern '.$_POST["pattern"].' subscribed to site '.$_POST["sitename"];
        $body='The release pattern '.$_POST["pattern"].' has been subscribed to site '.$_POST["sitename"]
             .' by '.$_POST["user"]." <".$user_data['email'].">\n";
        $from = "From: LJSFi agent <".$LJSFi_email.">";
        $user_info = get_user_info("subscribers",NULL,NULL,True,$_POST["sitename"]);
        $emails = array();
        foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
        $to = implode(",",$emails);
        if ($to != "") mail($to, $subj, $body, $from);
        echo '</TD></TR>';
      }
    }
?>
</TABLE>
<input type="submit" name="add" value="Add">
</form>

<?php if (isset($_POST["sitename"]) && $_POST["sitename"] != "") { ?>
<form method="post" name="updaterelsub" action="relsub.php">
<TABLE border="1" rules="groups" summary="Remove release subscriptions">
<CAPTION><EM><CENTER><FONT SIZE=+2>
Subscriptions in <?php echo $_POST["sitename"]; ?>
<P>
</FONT></CENTER></EM></CAPTION>
<COLGROUP width="600"></COLGROUP>
<TR class="graytable"><TD colspan="2">
<?php
    if (isset($_POST['remove'])) {
      # Check for the user id
      $userfk = $user_data['ref'];
      # Remove the subscriptions
      if ($userfk >= 0) {
        while (list ($key,$val) = @each ($_POST['subscriptions'])) {
          $relsubquery = "SELECT release_subscription.ref"
                       .  " FROM release_subscription"
                       . " WHERE ref IN (".db_int_list($_POST['subscriptions'],1).")"
                       .   " AND sitename=".db_quote($_POST['sitename'],'rw');
          $res = db_query($relsubquery);
          $numrows = mysqli_num_rows($res);
          echo '<CENTER>';
          if ($numrows>0) {
            $date = date("Y-m-d H:i:s");
            $query = "DELETE FROM release_subscription"
                   . " WHERE ref IN (".db_int_list($_POST['subscriptions'],1).")";
            $res = db_query($query);
            echo 'You have successfully the release subscription in site '.$_POST["sitename"];
            # Send the notification email
            $subj='[REMOVE SUBSCRIPTION] Release subscription removed from site '.$_POST["sitename"];
            $body=$numrows.' releases subscription(s) removed from site '.$_POST["sitename"]
                 .' by '.$_POST["user"]." <".$user_data['email'].">\n";
            $from = "From: LJSFi agent <".$LJSFi_email.">";
            $user_info = get_user_info("subscribers",NULL,NULL,True,$_POST["sitename"]);
            $emails = array();
            foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
            $to = implode(",",$emails);
            if ($to != "") mail($to, $subj, $body, $from);

          } else {
            echo 'No subscription matching in '.$_POST["sitename"];
          }
          echo '</CENTER></TD></TR>';
          echo '<TR><TD colspan="2"><HR>';
        }
      }
    }
?>
<TABLE border="0" rules="groups" summary="List of subscriptions">
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="100"></COLGROUP>
<TH align="center">Release pattern</TH>
<TH align="center">Set by</TH>
<TH align="center">Date</TH>
<TH align="center">Remove</TH>
<TBODY>
<?php
    // Current subscriptions
    $colors = array('#DFFFDF','#DFDFFF');
    $query = "SELECT rs.ref, rs.pattern, u.name, rs.date"
           . "  FROM release_subscription rs, user u"
           . " WHERE rs.userfk = u.ref"
           . "   AND rs.sitename = ".db_quote($_POST["sitename"],'ro')
           . " ORDER BY rs.pattern";
    $result = db_query($query);
    $indx   = 0;
    while ($row = mysqli_fetch_array($result)) {
      echo '<TR><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[1];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[2];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[3];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'"><CENTER>';
      echo '<input type=checkbox name=subscriptions[] value="'.$row[0].'">';
      echo '</CENTER></TD></TR>';
      $indx += 1;
    }
    if ($indx == 0) {
      echo '<TR><TD bgcolor="'.$colors[$indx%2].'" align="center">ALL RELEASES</TD>';
      echo '<TD bgcolor="'.$colors[$indx%2].'">&nbsp</TD>';
      echo '<TD bgcolor="'.$colors[$indx%2].'">&nbsp</TD>';
      echo '<TD bgcolor="'.$colors[$indx%2].'">&nbsp</TD>';
      echo '</TR>';
    }
    echo '</TABLE>';
?>

</TD></TR></TABLE>
<input type="hidden" name="sitename" value="<?php echo $_POST["sitename"]; ?>">
<input type="submit" name="remove" value="Remove">
</form>
<?php
  }
}
?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
