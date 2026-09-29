<?php
require_once dirname(__DIR__).'/chart_local.php';
function criticalmap_plot($tier_level=NULL,$site_type=NULL,$sites=NULL,$width=700,$height=420){
  $query="SELECT site.name,site.tier_level,site.cs,COUNT(DISTINCT release_stat.ref) FROM site JOIN release_stat ON release_stat.sitefk=site.ref JOIN release_data ON release_stat.name=release_data.name WHERE release_data.critical=1 AND site.facilityfk=(SELECT ref FROM facility WHERE name='Panda') AND release_stat.status='installed'";
  if(!is_null($sites))$query.=" AND site.ref IN (".db_int_list($sites,0).")";
  if(!is_null($tier_level))$query.=" AND site.tier_level=".db_int($tier_level,0,99); else $query.=" AND site.tier_level>0";
  if(!is_null($site_type))$query.=" AND site.activity_typefk=(SELECT ref FROM site_activity_type WHERE name=".db_quote($site_type,'ro').")";
  $query.=" GROUP BY site.name,site.tier_level,site.cs ORDER BY 4 DESC";
  $r=db_query($query,'ro');$d=[];while($x=mysqli_fetch_row($r))$d[]=[$x[0].' / '.$x[2],(float)$x[3]];
  echo '<div id="criticalchart_div">'.atlas_chart_bars($d,'Critical releases installed',$width,$height).'</div>';
}
?>
