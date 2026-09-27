<HTML>
<HEAD>

<?php

  require("combo.php");
  require("db.php");
  require("config.php");
?>

<TITLE>Site Info Viewer</TITLE>

<link rel="STYLESHEET" type="text/css" href="../css/ljsf.css">
<link rel="shortcut icon" href="../img/favicon.ico">
<script type="text/javascript" src="../js/simpletreemenu.js"></script>
<link rel="stylesheet" type="text/css" href="../css/simpletree.css" />

<style>
TR {background-color: #CCCCCC;}
TD {width: 150;}
</style>

</HEAD>
<BODY>
<P>
<TABLE border="2" frame="hsides" rules="groups"
          summary="<?php echo $LJSFi_VO; ?> Installation: site view">
<CAPTION><EM><FONT SIZE=+2><?php echo $LJSFi_VO; ?> Software Installation: site view</FONT></EM></CAPTION>
<COLGROUP align="left">
<COLGROUP align="left">
<THEAD valign="top">
<TBODY>

<?php
  $COLORS = array (1 => '#DDDDDD'
                 , 2 => '#00FF00'
                 , 3 => '#FF0000'
                 , 4 => '#FF00FF'
                 , 5 => '#AADD88'
                  );
  $DBFIELDS = array ('cename'   => 'cename'
                   , 'sitename' => 'name'
                   , 'cs'       => 'cs');

  $qry = "SELECT name,cename,cs,osname,osrelease,osversion,arch,swarea FROM site";
  $first = true;
  foreach ($_GET as $key => $val) {
    if ($first) {
      $qry .= " WHERE ";
      $first = false;
    } else {
      $qry .= " AND ";
    }
    if (isset($DBFIELDS[$key])) $qry .= $DBFIELDS[$key] . "='" . $val . "'"; 
  }
  $result = db_query($qry);
  $numrows = mysqli_num_rows($result);
  if ($numrows == 1) {
    $row = mysqli_fetch_row($result);
    echo '<TR><TD nowrap><EM>Site Name</EM></TD><TD nowrap>'                . $row[0] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>Resource/CE FQDN</EM></TD><TD nowrap>'         . $row[1] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>Resource name</EM></TD><TD nowrap>'            . $row[2] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>OS Name</EM></TD><TD nowrap>'                  . $row[3] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>OS Release</EM></TD><TD nowrap>'               . $row[4] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>OS Version</EM></TD><TD nowrap>'               . $row[5] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>Install Arch</EM></TD><TD nowrap>'             . $row[6] . '</TD></TR>';
    echo '<TR><TD nowrap><EM>Experiment Software Area</EM></TD><TD nowrap>' . $row[7] . '</TD></TR>';
  } else {
    echo '<form name="site" method="get" action="">';
    echo '<TR><TD><EM>Please select a CE FQDN</EM></TD><TD>';
    $cenames = array();
    while ($row = mysqli_fetch_array($result)) {
      array_push($cenames, $row[1]);
    }
    echo '<select name="',cename,'" size="1" onchange="document.site.submit();">';
    combo_box ($cenames);
    echo '</select></TD>';
  }
?>
</TABLE>
<?php
  echo( date("l, F dS Y, H:m:s") );
?>
<P>
<A HREF="mailto:Alessandro.DeSalvo@roma1.infn.it">For comments or informations please drop me a mail (Alessandro.DeSalvo@roma1.infn.it)</A>
</BODY>
</HTML>
