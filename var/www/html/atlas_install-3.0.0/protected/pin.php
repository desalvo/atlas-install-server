<HTML>
<HEAD>

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  var rows = pin_tbl.getElementsByTagName("tr");   
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
  pin.action='pin.php';
  pin.submit();
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

<TITLE>Pin management for the <?php echo $LJSFi_VO; ?> Software Installation System</TITLE>
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
        <h3>Add or remove pins to installed releases</h3>
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
  } elseif ($user_info[0]['priv_pin'] == 0) {
    echo ("<FONT COLOR='RED' SIZE=+2><B>Insufficient privileges to pin releases. Please <A HREF='user.php'>update your registration</A>.</B></FONT>");
  } else {
    $user_data = $user_info[0];
?>
<form method="post" name="pin" action="pin.php" onsubmit="return checkform(this);">
<TABLE id='pin_tbl' border="1" rules="groups" summary="Add pins">
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
    echo '<TR class="graytable"><TD></TD><TD>Select a site name to list all the installed releases</TD></TR>';

    // Release name combo box
    if (isset($_POST["sitename"]) && $_POST["sitename"] != "") {
      echo ("<TR id='rel_tr' class='graytable'><TD><EM>Release Name</EM></TD><TD>");
      echo ("<select name='rel' size='1'>");
      $rel=array();
      $query = "SELECT DISTINCT(release_stat.name)"
             . "  FROM release_stat, site"
             . " WHERE release_stat.sitefk=site.ref"
             . "   AND site.name=".db_quote($_POST["sitename"],'rw')
             . "   AND release_stat.status='installed'"
             . " ORDER BY release_stat.name";
      $result = db_query($query);
      while ($row = mysqli_fetch_array($result)) {
        array_push($rel, $row[0]);
      }
      combo_box($rel,"-- select one --",$_POST["rel"]);
      echo '</select></TD></TR>';
    }

  
    if (isset($_POST['add'])) {
      # Get the user id
      $userfk = $user_data['ref'];
      # Add the pin
      if ($userfk >= 0 && isset($_POST["sitename"]) && $_POST["sitename"] != ''
                       && isset($_POST["rel"]) && $_POST["rel"] != '') {
        $pinquery = "SELECT release_stat.ref"
                  . "  FROM release_stat, site"
                  . " WHERE release_stat.sitefk=site.ref"
                  . "   AND site.name=".db_quote($_POST["sitename"],'rw')
                  . "   AND release_stat.name=".db_quote($_POST["rel"],'rw')
                  . "   AND pin=0";
        $res = db_query($pinquery);
        $numrows = mysqli_num_rows($res);
        echo '<TR><TD>&nbsp;</TD><TD>';
        if ($numrows > 0) {
          $date = date("Y-m-d H:i:s");
          $query = "UPDATE release_stat"
                 . "   SET pin=1, pinuserfk=".db_int($userfk,1).", pindate=".db_quote($date,'rw')
                 . " WHERE release_stat.name=".db_quote($_POST["rel"],'rw')
                 . "   AND sitefk in ("
                 . "SELECT ref"
                 . "  FROM site"
                 . " WHERE site.name=".db_quote($_POST["sitename"],'rw')
                 . ")";
          $res = db_query($query);
          echo 'You have successfully pinned release '.$_POST["rel"].' in '.$_POST["sitename"];

          # Send the notification email
          $subj='[PIN ADD] Pin added for release '.$_POST["rel"].' in site '.$_POST["sitename"];
          $body='A pin for release '.$_POST["rel"].' in site '.$_POST["sitename"]
               .' has been added by '.$_POST["user"]." <".$user_data['email'].">\n";
          $from = "From: LJSFi agent <".$LJSFi_email.">";
          $user_info = get_user_info("subscribers",NULL,NULL,True,$_POST["sitename"]);
          $emails = array();
          foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
          $to = implode(",",$emails);
          if ($to != "") {
            mail($to, $subj, $body, $from);
            // Update the mail log
            $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                        . "(SELECT ref FROM mail_type WHERE name='pin'),"
                        . "'" . $to . "',"
                        . count($emails) . ","
                        . "'" . date("Y-m-d H:i:s") . "')";
            $result = db_query($maillogqry);
          }

        } else {
          echo 'Release '.$_POST["rel"].' is already pinned in '.$_POST["sitename"];
        }
        echo '</TD></TR>';
      }
    }
?>
</TABLE>
<input type="submit" name="add" value="Add">
</form>

<?php if (isset($_POST["sitename"]) && $_POST["sitename"] != "") { ?>
<form method="post" name="updatepin" action="pin.php">
<TABLE border="1" rules="groups" summary="Remove pins">
<P><h3>Pins in <?php echo $_POST["sitename"]; ?></h3>
<COLGROUP width="500"></COLGROUP>
<TR class="graytable"><TD colspan="2">
<?php
    if (isset($_POST['remove'])) {
      # Check for the user id
      $userfk = $user_data['ref'];
      # Remove the pins
      if ($userfk >= 0) {
        while (list ($key,$val) = @each ($_POST['pins'])) {
          $pinquery = "SELECT release_stat.ref"
                    . "  FROM release_stat, site"
                    . " WHERE release_stat.sitefk=site.ref"
                    . "   AND site.name=".db_quote($_POST["sitename"],'rw')
                    . "   AND release_stat.name=".db_quote($val,'rw')
                    . "   AND pin=1";
          $res = db_query($pinquery);
          $numrows = mysqli_num_rows($res);
          echo '<CENTER>';
          if ($numrows>0) {
            $date = date("Y-m-d H:i:s");
            $query = "UPDATE release_stat"
                   . "   SET pin=0, pinuserfk=".db_int($userfk,1).", pindate=".db_quote($date,'rw')
                   . " WHERE release_stat.name=".db_quote($val,'rw')
                   . "   AND sitefk in ("
                   . "SELECT ref"
                   . "  FROM site"
                   . " WHERE site.name=".db_quote($_POST["sitename"],'rw')
                   . ")";
            $res = db_query($query);
            echo 'You have successfully removed the pin for release '.$val.' in '.$_POST["sitename"];
            # Send the notification email
            $subj='[PIN REMOVE] Pin removed for release '.$val.' in site '.$_POST["sitename"];
            $body='The pin for release '.$val.' in site '.$_POST["sitename"]
                 .' has been removed by '.$_POST["user"]." <".$user_data['email'].">\n";
            $from = "From: LJSFi agent <".$LJSFi_email.">";
            $user_info = get_user_info("subscribers",NULL,NULL,True,$_POST["sitename"]);
            $emails = array();
            foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
            $to = implode(",",$emails);
            if ($to != "") {
              mail($to, $subj, $body, $from);
              // Update the mail log
              $maillogqry = "INSERT INTO mail_log (typefk,address,copies,date) VALUES ("
                          . "(SELECT ref FROM mail_type WHERE name='pin'),"
                          . "'" . $to . "',"
                          . count($emails) . ","
                          . "'" . date("Y-m-d H:i:s") . "')";
              $result = db_query($maillogqry);
            }
          } else {
            echo 'Release '.$val.' is not pinned in '.$_POST["sitename"];
          }
          echo '</CENTER></TD></TR>';
          echo '<TR><TD colspan="2"><HR>';
        }
      }
    }
?>
<TABLE border="0" rules="groups" summary="Pin list">
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="200"></COLGROUP>
<COLGROUP width="150"></COLGROUP>
<COLGROUP width="100"></COLGROUP>
<TH align="left">Release</TH>
<TH align="left">Pinned by</TH>
<TH align="left">Date</TH>
<TH align="center">Remove</TH>
<TBODY>
<?php
    // Current pins
    $colors = array('#DFFFDF','#DFDFFF');
    $query = "SELECT DISTINCT(release_stat.name), user.name, release_stat.pindate"
           . "  FROM release_stat, site, user"
           . " WHERE release_stat.sitefk = site.ref"
           . "   AND release_stat.pinuserfk = user.ref"
           . "   AND site.name = ".db_quote($_POST["sitename"],'ro')
           . "   AND release_stat.pin = 1"
           . " ORDER BY release_stat.name";
    $result = db_query($query);
    $indx   = 0;
    while ($row = mysqli_fetch_array($result)) {
      echo '<TR><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[0];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[1];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'">';
      echo $row[2];
      echo '</TD><TD bgcolor="'.$colors[$indx%2].'"><CENTER>';
      echo '<input type=checkbox name=pins[] value="'.$row[0].'">';
      echo '</CENTER></TD></TR>';
      $indx += 1;
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
