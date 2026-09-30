<?php
 if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
<HTML>
<HEAD>
<?php
  require("db.php");
  require("config.php");
?>
<TITLE><?php echo $LJSFi_VO; ?> Installation Tag Matrix</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
</HEAD>
<BODY>
<?php } ?>
<?php
  $identity = atlas_current_identity();
  $identityRef = (int)($identity['legacy_ref'] ?? 0);
  $role = '';
  if ($identityRef > 0) {
    $result = db_query("SELECT user.ref, role.description FROM user,role WHERE user.ref=" . db_int($identityRef,1) . " AND ABS(user.rolefk)=role.ref","ro");
    $row = mysqli_fetch_row($result);
    if ($row) { $adminfk=(int)$row[0]; $role=(string)$row[1]; }
  }
?>
<?php if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
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
        <h3><?php echo $LJSFi_VO; ?> Software Installation Tags Matrix</h3>
<TABLE border="2" frame="hsides" rules="groups" summary="<?php echo $LJSFi_VO; ?> Tag matrix." class="table1">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<TR>
<TH>Site name
<TH>Resource name
<TH>Tags
<TBODY>
<?php } ?>
<?php
  # Define the colors
  $COLORS     = array ( 0 => '#33FFFF'
                     ,  1 => '#FFFF33'
                      );

  # Prepare the arrays
  $sitetags = array();

  # Query header
  $q_hdr  = "SELECT s.name, s.cename, rd.tag, r.name";
  $q_body =  " FROM release_data rd,release_stat r,site s"
           ." WHERE s.ref=r.sitefk"
             ." AND rd.name=r.name"
             ." AND rd.tag <> ''"
             ." AND r.status='installed'"
             ." AND s.name <> ''";

  # Fetch the release tag records
  $query  = ($q_hdr . $q_body);
  $query .= "ORDER BY s.name, s.cename,r.name";
  if (isset($_GET['rel'])) $query .= " AND rd.name LIKE " . db_quote($_GET['rel'],'ro');
  $result = db_query($query);
  while ( $row = mysqli_fetch_array($result) ) {
    if (!array_key_exists($row[0],$sitetags)) {
      $sitetags[$row[0]] = array();
    }
    if (!array_key_exists($row[1],$sitetags[$row[0]])) $sitetags[$row[0]][$row[1]] = array();
    $sitetags[$row[0]][$row[1]][$row[2]] = $row[3];
  }

  # Query header
  $q_hdr  = "SELECT s.name, s.cename, s.tags";
  $q_body =  " FROM site s"
           ." WHERE  s.tags <> ''";

  # Fetch the site tag records
  $query  = ($q_hdr . $q_body);
  $query .= "ORDER BY s.cename";
  $result = db_query($query);
  while ( $row = mysqli_fetch_array($result) ) {
    if (array_key_exists($row[0],$sitetags)) {
      if (array_key_exists($row[1],$sitetags[$row[0]])) {
        foreach (split(",",$row[2]) as $key => $val) {
          $sitetags[$row[0]][$row[1]][$val] = $val;
        }
      }
    }
  }

  $color = 0;
  foreach ($sitetags as $sitename => $cedata) {
    foreach ($cedata as $ce => $reldata) {
      if ($color == 0) { $color = 1; } else { $color = 0; }
      echo ("<TR>"); 
      echo ("<TD nowrap bgcolor=" . $COLORS[$color] . ">".$sitename."</TD>");
      echo ("<TD nowrap bgcolor=" . $COLORS[$color] . ">".$ce."</TD>");
      echo ("<TD nowrap bgcolor=" . $COLORS[$color] . ">");
      asort($reldata);
      foreach ($reldata as $reltag => $relname) {
        echo ($reltag.'<BR>');
      }
      echo ('</TD>');
      echo ('</TR>');
    }
  }
?>
<?php if (!isset($_POST['ws']) or $_POST['ws'] == '') { ?>
</TABLE></CENTER></TD></TR></TABLE>
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
