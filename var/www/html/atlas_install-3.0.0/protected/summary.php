<HTML>
<?php

  require("db.php");
  require("config.php");
  require("user_info.php");
  require ("$LJSFi_PATH/access_log.php");
?>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation action summary</TITLE>
<link rel="STYLESHEET" type="text/css" href="../css/ljsf.css">
<link rel="shortcut icon" href="../img/favicon.ico">

<script language="JavaScript" type="text/javascript">
<!--
function checkform(form) {
  var retval=true;
  if (form.relstat.value=='') {
    alert ('Please select a status to update the record');
    retval=false;
  }
  return retval;
}
//-->
</script>
<script type="text/javascript" src="../js/simpletreemenu.js"></script>
<link rel="stylesheet" type="text/css" href="../css/simpletree.css" />
</HEAD>
<?php
  function show_summary($fromdate,$todate,$sitename='') {
    global $LJSFi_VO,$LJSFi_email;
    if ($sitename == '') {
      $logqry = "SELECT site.name"
               . " FROM log"
               . " JOIN request ON log.requestfk=request.id"
               . " JOIN site ON request.sitefk=site.ref"
               . " WHERE log.date >= " . db_quote($fromdate,'ro') . " and log.date <= ". db_quote($todate,'ro')
               . " GROUP BY site.name";
      $result = db_query($logqry);
      $sites = array();
      while ($siteentry=mysqli_fetch_row($result)) {
        if ($siteentry[0] != "") {
          array_push($sites,$siteentry[0]);
        }
      }
    } else {
      $sites = array($sitename);
    }
    foreach ($sites as $site) {
      $logsiteqry = "SELECT COUNT(*)"
                  . ",site.cs"
                  . ",GROUP_CONCAT(DISTINCT log.action ORDER BY log.date) AS actions"
                  . ",request.id"
                  . ",log.url"
                  . ",GROUP_CONCAT(DISTINCT log.date ORDER BY log.date) AS dates"
                  . ",release_data.name"
                  . " FROM log"
                  . " JOIN request ON log.requestfk=request.id"
                  . " JOIN site ON request.sitefk=site.ref"
                  . " JOIN job ON job.requestfk=request.id"
                  . " JOIN jdl ON jdl.ref=job.jdlfk"
                  . " JOIN release_stat ON release_stat.ref=jdl.relfk"
                  . " JOIN release_data ON release_data.name=release_stat.name"
                  . " WHERE log.date >= " . db_quote($fromdate,'ro') . " and log.date <= ". db_quote($todate,'ro')
                  . "  AND site.name=" . db_quote($site,'ro')
                  . " GROUP BY site.cs, request.id"
                  . " ORDER BY log.date,release_data.name";
      $result = db_query($logsiteqry);
      $numSiteEntries = mysqli_num_rows($result);
      $infoByAction = array();
      while ($logentry=mysqli_fetch_row($result)) {
        if (! isset($infoByAction[$logentry[2]])) $infoByAction[$logentry[2]] = array();
        array_push($infoByAction[$logentry[2]],array("cs" => $logentry[1], "req" => $logentry[3], "url" => $logentry[4], "date" => $logentry[5], "rel" => $logentry[6]));
      }
      $user_info = get_user_info("digest",NULL,NULL,True,$site);
      $emails = array();
      foreach ($user_info as $user_data) { array_push($emails,$user_data["email"]); }
      $to = implode(",",$emails);

      $actions = array_keys($infoByAction);
      $action_summary = array();
      foreach ($actions as $action) {
        array_push($action_summary, $action . " [" . count($infoByAction[$action]) . "]");
      }

      $contents = $site." from ".$fromdate." to ".$todate."<BR>";
      $contents = $contents . "Number of action sequences in the site: ".$numSiteEntries."<BR>";
      $contents = $contents . join(" / ",$action_summary)."<BR><BR>";
      foreach ($actions as $action) {
        $contents = $contents . "<FONT COLOR='red'><B>".strtoupper($action) . " [" . count($infoByAction[$action]) . "]</B></FONT>:<BR>";
        foreach ($infoByAction[$action] as $loginfo) {
          $contents = $contents . "[" . $loginfo["date"] . "] "  . $loginfo["cs"] . " <A HREF='" . $loginfo["url"] . "'>" . $loginfo["rel"] . "</A><BR>";
        }
        $contents = $contents . "<BR><BR>";
      }
      $subject  = "[" . $site .  "]";
   
      if($contents != "" && $to != "") {
        echo "<HR><BR><BR>";
        echo "<B><FONT COLOR='red' SIZE=+2>[".$site."]</FONT></B><BR>";
        echo "<FONT COLOR='green'>".$to."</FONT><BR>";
        echo $contents."<BR><BR>";
      }
    }
  }
?>

<BODY>

<TABLE id='frame_tbl' border="1" rules="groups" width="1850" summary="Summary table">
<COLGROUP width="200"></COLGROUP>
<COLGROUP></COLGROUP>
<TR><TD colspan="2" width="100%" height="30" background="../img/bar.gif" class="captionimg">
<CENTER>
<?php echo $LJSFi_VO; ?> installation summary
</CENTER>
</TD></TR>
<TR><TD height="50" background="../img/bar3.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
<TR><TD background="../img/bar3.gif" height="100%" valign="top">
<?php include ("sidebar.php"); ?>
</TD><TD valign="top">
<TABLE border="2" frame="hsides" rules="groups" summary="<?php echo $LJSFi_VO; ?> software installation summary.">
<COLGROUP align="left">
<COLGROUP align="left">
<TBODY>
<TR><TD>
<?php
  $interval = 21600;
  if (isset($_REQUEST["from"]) and $_REQUEST["from"] != "") {
    $fromdate = $_REQUEST["from"];
  } else {
    $fromdate = date("Y-m-d H:i:s", time() - $interval);
  }
  if (isset($_REQUEST["to"]) and $_REQUEST["to"] != "") {
    $todate = $_REQUEST["to"];
  } else {
    $todate = date("Y-m-d H:i:s");
  }
  if (isset($_REQUEST["sitename"]) and $_REQUEST["sitename"] != "") {
    $sitename = $_REQUEST["sitename"];
  } else {
    $sitename = "";
  }
  show_summary($fromdate,$todate,$sitename);
?>
</TD></TR></TABLE>
<P>
<A HREF="mailto:Alessandro.DeSalvo@roma1.infn.it">For comments or informations please drop me a mail (Alessandro.DeSalvo@roma1.infn.it)</A>
</BODY>
</HTML>
