<?php

  require("db.php");
  require("guid.php");
  require("config.php");
  require("user_info.php");
?>
<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation Logfile Access</TITLE>
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
    <div id="site_content1">
      <div id="content">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Software Installation JDL viewer</h3>

<TABLE id='jdl_tbl' border="1" cellspacing="0" rules="groups" width="100%" summary="<?php echo $LJSFi_VO; ?> Installation JDL viewer">
<TR><TD width="20">&nbsp&nbsp</TD><TD class="graytable">
<?php
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    $role="";
  } else {
    $adminfk=$user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
    $priv_view=$user_info[0]['priv_view'];
  }

  if (!isset($priv_view)) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>Unknown user. Please <A HREF='user.php'>register to LJSFi.</A></B></FONT>\n");
  } elseif ($enabled == 0) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>Your user is disabled. Please contact the LJSFi administrator.</B></FONT>\n");
  } elseif ($priv_view != 1) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>");
    echo ("You don't have permissions to view job definitions.<BR>\n");
    echo ("Please check your registration and <A HREF='user.php'>ask for the view privilege</A>\n");
    echo ("</B></FONT>");
  } else {
  # Get the ID
    if (isset($_REQUEST['id']) && $_REQUEST['id'] != '') $jid=db_int($_REQUEST['id'],1);

    # Fetch the record
    if (isset($jid)) {
      $query  = ("SELECT jdl.content FROM jdl WHERE ref=".db_int($jid,1));
      $result = db_query($query);
      $row = mysqli_fetch_row($result);
      if ($row) {
        echo nl2br(htmlentities($row[0]));
      } else {
        echo ('<center>No jdl found</center>');
      }
    } else {
      echo ("<center>No ID specified</center>");
    }
  }
?>
</TD><TD width="20">&nbsp&nbsp</TD></TR>
</TABLE>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
