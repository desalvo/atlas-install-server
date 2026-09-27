<HTML>
<HEAD>

<?php
  require ("db.php");
  require ("config.php");
  require ("protected/user_info.php");
  require ("$LJSFi_PATH/access_log.php");
?>

<TITLE><?php echo $LJSFi_VO; ?> Installation DB viewer - Job View</TITLE>
<?php require("./css/page_header.php"); page_header("."); ?>
<link rel="stylesheet" href="css/tooltipster.css" />
<script type="text/javascript" src="js/jquery.tooltipster.min.js"></script>
<script>
    $(document).ready(function() {
        $('.tooltip').tooltipster({interactive: true});
    });
</script>
</HEAD>
<BODY>
<P>
  <div id="main">
    <div id="header">
<?php require("./css/main_header.php"); main_header($LJSFi_VO, "."); ?>
<?php require("./css/menubar.php"); menubar("."); ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content1">
      <div id="content">
        <!-- insert the page content here -->

<?php if (isset($_GET['showinfo'])) { ?>
<TABLE border="2" frame="hsides" rules="groups"
          summary="<?php echo $LJSFi_VO; ?> software deployment status.">
<COLGROUP align="left">
<THEAD valign="top">
<TR>
<TH>Job info
<?php } else { ?>
<h3><?php echo $LJSFi_VO; ?> software deployment job list</h3>
<TABLE border="2" frame="hsides" rules="groups"
          summary="<?php echo $LJSFi_VO; ?> software deployment status.">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="center">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<COLGROUP align="left">
<THEAD valign="top">
<TR>
<TH>Job type
<TH>Release
<TH>Destination
<TH>Job<BR>status
<TH>Validation<BR>status
<TH>Submission<BR>time
<TH>Retrieval<BR>time
<TH>Comments
<TH>User
<?php } ?>
<TBODY>

<?php
  $COLORS = array (1 => 'valPending'
                 , 2 => 'valOk'
                 , 3 => 'valFailed'
                 , 4 => 'valUnknown'
                  );
  $JOBLOCK = array (0 => "unlocked"
                  , 1 => "locked");
  $query  = ("SELECT jdl.type,release_stat.name,site.cs,job.status
                    ,validation.description
                    ,job.submission_time,job.retrieval_time
                    ,job.comments,user.name,job.validationfk
                    ,job.name,site.arch,job.id,job.reach_time
                    ,job.exit_code,job.status_reason
                    ,job.info
                    ,job.ref
                    ,user.dn
                    ,job.jdlfk
                    ,(SELECT dn FROM user WHERE ref=job.adminfk)
                    ,jdl.name,jdl.joblock
                    ,facility.name
                    ,CONCAT(job_output.name)
              FROM job,site,user,validation,jdl,release_stat,facility,job_output
              WHERE     job.sitefk=site.ref AND job.userfk=user.ref
                    AND job.validationfk=validation.ref
                    AND job.jdlfk=jdl.ref AND jdl.relfk=release_stat.ref
                    AND job.facilityfk=facility.ref AND job_output.jobfk=job.ref");
  if (isset($_GET['ce'])) $query .= " AND cename=" . db_quote($_GET['ce'],'ro');
  if (isset($_GET['relfk'])) $query .= " AND jdl.relfk=" . db_int($_GET['relfk']);
  if (isset($_GET['status'])) $query .= " AND validation.description=" . db_quote($_GET['status'],'ro');
  if (isset($_GET['user'])) $query .= " AND user.name LIKE " . db_quote('%'.$_GET['user'].'%','ro');
  if (isset($_GET['id'])) $query .= " AND job.ref=" . db_int($_GET['id']);
  if (isset($_GET['siteid'])) $query .= " AND site.ref=" . db_int($_GET['siteid']);
  $query = ($query . " ORDER BY job.submission_time DESC, job.name");
  // SQL query disclosure disabled
  $result = db_query($query,"ro");
  if (!$result) {
    echo ("<P>ERROR: " . mysqli_error() . "</P>");
    exit();
  } else {
    $num_rows = mysqli_num_rows($result);
    $job_stats = array ();
    while ( $row = mysqli_fetch_array($result) ) {
      if (isset($_GET['showinfo'])) {
        echo ("<TR>");
        $jobinfo = $row[16];
        $sslusername = getenv("SSL_CLIENT_S_DN_CN");
        $ssluserdetails = getenv("SSL_CLIENT_S_DN");
        if (isset($sslusername) && isset($ssluserdetails) && $ssluserdetails != "") {
          $user_info = get_user_info();
          if (count($user_info) > 0) {
            $priv_view = $user_info[0]['priv_view'];
            $enabled = $user_info[0]['enabled'];
          }
        } else {
          echo ("<FONT SIZE=+1 COLOR='red'><B>");
          echo ("No valid credentials found. Please use https and a valid certificate\n");
          echo ("</B></FONT>");
        }
        if (isset($priv_view)) {
          if ($enabled == 0) {
            echo ("<FONT SIZE=+1 COLOR='red'><B>");
            echo ("Your user is disabled. Please contact the LJSFi administrator.<BR>\n");
            echo ("</B></FONT>");
          } elseif ($priv_view == 0) {
            echo ("<FONT SIZE=+1 COLOR='red'><B>");
            echo ("You don't have the permissions to view the job info.<BR>\n");
            echo ("Please check your registration and <A HREF='protected/user.php'>ask for the view privilege</A>\n");
            echo ("</B></FONT>");
          } else {
            // Show the job info
            $data = nl2br(htmlentities($jobinfo));
            echo ("<TD bgcolor='#D3D3D3'><BR>\n");
            if ($data != '') {
              echo $data;
            } else {
              echo 'No info for this job';
            }
          }
        } elseif (isset($ssluserdetails) && $ssluserdetails != "") {
          echo ("Unknown user. Please <A HREF='user.php'>register to LJSFi</A>\n");
        }
        echo ("<BR></TD>\n");
      } else {
        if ($row[3] != "") { $jobstat = $row[3]; } else { $jobstat = "NULL"; }
        if (!isset($job_stats[$jobstat])) $job_stats[$jobstat] = 0;
        $job_stats[$jobstat] += 1;
        echo ("<TR class='" . $COLORS[$row[9]] . "'>\n");
        for ($i=0; $i<9; $i++) {
          echo ("<TD>\n");
          if ($i==0) {
            echo (' <A href="protected/jdl.php?id='.$row[19].'" class="tooltip" title="');
            echo ('JDL NAME: ' . $row[21] . '<BR>');
            echo ('JOB LOCK: ' . $JOBLOCK[$row[22]]);
            echo ('">');
          }
          if ($i==1) { echo (" <A href='protected/rel.php?name=".$row[1]."'>"); }
          if ($i==3) {
            echo (' <A href="protected/log.php?id='.$row[17].'" class="tooltip" title="');
            echo ('<A HREF=\'protected/log.php?id='.$row[17].'\'>View the full log</A><BR>');
            echo ('<A HREF=\'protected/log.php?mode=download&id='.$row[17].'\'>Download the full log</A>');
            echo ('">');
          }
          echo atlas_h($row[$i]);
          if ($i==0 || $i==1 || $i==3) echo ("</A>");
          if ($i==3) {
            $facility = $row[23];
            if ($facility == "WMS") {
              $joblink = "<A HREF='".$row[12] ."' TARGET='top'>" . $row[12] . "</A>";
            } else {
              #$joblink = "<A HREF='http://panda.cern.ch/server/pandamon/query?job=".$row[12] ."' TARGET='top'>Panda ID " . $row[12] . "</A>";
              $joblink = "<A HREF='http://bigpanda.cern.ch/job?pandaid=".$row[12] ."' TARGET='top'>Panda ID " . $row[12] . "</A>";
            }
            echo ('&nbsp;<A href="jobs.php?id='.$row[17].'&showinfo" class="tooltip" title="');
            echo ('JOB SUMMARY: <A HREF=\'jobs.php?id='.$row[17].'&showinfo\'>click here for the job summary</A><BR>');
            echo ('LJSFi JOB NAME: ' . $row[10] . '<BR>');
            echo ('JOB ARCH:       ' . $row[11] . '<BR>');
            echo ('JOB ID:         ' . $joblink . '<BR>');
            echo ('FACILITY:       ' . $row[23] . '<BR>');
            echo ('OUTPUT DATA:    ' . $row[24] . '<BR>');
            if ($facility != "Panda") echo ('REACH TIME:     ' . $row[13] . '<BR>');
            echo ('EXIT CODE:      ' . $row[14] . '<BR>');
            echo ('STATUS REASON:  ' . $row[15] . '<BR>');
            echo ('SUBMITTER DN:   ' . $row[18] . '<BR>');
            if (isset($row[20])) $reqdn = $row[20]; else $reqdn = $row[18];
            echo ('REQUESTER DN:   ' . $reqdn);
            echo ('">');
            echo ("<img src='img/info_sign_new.gif' width=15></A>");
          }
          echo ("\n</TD>\n");
        }
      }
      echo ("</TR><TBODY>");
    }
  }
?>
</TABLE>
<?php
  echo( "Number of records found: $num_rows<BR>" );
  asort($job_stats);
  foreach ($job_stats as $key => $value) {
      echo $key.": ".$value."<BR>\n";
  }
  echo( date("l, F dS Y, H:m:s") );
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
