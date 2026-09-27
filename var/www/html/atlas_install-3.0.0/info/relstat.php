<?php
  require("../db.php");

  # Query header
  # Exclude internal (typefk=1) releases
  $q_hdr  = "SELECT DISTINCT(rd.name),rd.sw_name,REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(rd.name,'_',1),'-',1),':',2),'.','_'),ra.description,ra.mode,s.name,r.status";
  $q_body =  " FROM release_data rd,release_stat r,site s,release_arch ra"
           ." WHERE rd.name=r.name AND r.sitefk=s.ref AND rd.archfk=ra.ref AND s.name <> ''"
             ." AND rd.typefk <> 1";

  # Fetch the records
  $query  = ($q_hdr . $q_body);
  if (isset($_REQUEST['rel']))      $query .= " AND rd.name LIKE " . db_quote($_REQUEST['rel'],'ro');
  if (isset($_REQUEST['swname']))   $query .= " AND rd.sw_name LIKE " . db_quote($_REQUEST['swname'],'ro');
  if (isset($_REQUEST['version']))  $query .= " AND rd.name LIKE " . db_quote('%'.$_REQUEST['version'].'%','ro');
  if (isset($_REQUEST['arch'])) {
      if (preg_match("/_opt$/",$_REQUEST['arch'])) $query .= " AND ra.description LIKE " . db_quote('%'.preg_replace('/_opt$/','',$_REQUEST['arch']),'ro') . " AND ra.mode='opt'";
      elseif (preg_match("/_dbg$/",$_REQUEST['arch'])) $query .= " AND ra.description LIKE " . db_quote('%'.preg_replace('/_dbg$/','',$_REQUEST['arch']),'ro') . " AND ra.mode='dbg'";
      else $query .= " AND ra.description LIKE " . db_quote('%'.$_REQUEST['arch'],'ro');
  }
  if (isset($_REQUEST['mode']))     $query .= " AND ra.mode=" . db_quote($_REQUEST['mode'],'ro');
  if (isset($_REQUEST['cename']))   $query .= " AND s.cename=" . db_quote($_REQUEST['cename'],'ro');
  if (isset($_REQUEST['sitename'])) $query .= " AND s.name=" . db_quote($_REQUEST['sitename'],'ro');
  if (isset($_REQUEST['status']))   $query .= " AND r.status=" . db_quote($_REQUEST['status'],'ro');
  if (isset($_REQUEST['swarea']))   $query .= " AND s.swarea=" . db_quote($_REQUEST['swarea'],'ro');
  # Select obsolete/production releases
  # Exclude nightly (typefk=3) releases
  if (isset($_REQUEST['obsolete'])) $query .= " AND rd.obsolete=" . db_int($_REQUEST['obsolete'],0,1) . " AND rd.typefk <> 3";
  $query .= (" ORDER BY rd.tag ASC");
  $result = db_query($query);
  while ( $row = mysqli_fetch_row($result) ) {
    if (isset($row[7])) {
      $relstatus = $row[7];
    } else {
      $relstatus = "unavailable";
    }
    printf ("%s_%s_%s_%s [%s]: %s : [%s]\n", $row[1],$row[2],preg_replace('/^_/','',$row[3]),$row[4],$row[0],$row[5],$row[6],$relstatus);
  }
  // SQL query disclosure disabled
?>
