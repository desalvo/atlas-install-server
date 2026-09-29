<?php
require_once __DIR__.'/chart_local.php';
function atlas_job_summary($interval): array {$r=db_query("SELECT DATE_FORMAT(submission_time,'%Y-%m-%d'),COUNT(*) FROM job WHERE DATE_SUB(CURDATE(),INTERVAL ".intval($interval)." DAY)<=submission_time GROUP BY DATE_FORMAT(submission_time,'%Y-%m-%d') ORDER BY submission_time",'ro');$d=[];while($x=mysqli_fetch_row($r))$d[]=[$x[0],(int)$x[1]];return $d;}
function jobplot($interval,$title,$file=NULL){$d=atlas_job_summary($interval);if($file){$json=preg_replace('/\.html$/','.json',$file);file_put_contents($json,json_encode(['title'=>$title,'data'=>$d],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return;}echo atlas_chart_line(array_map(fn($r)=>[substr($r[0],5),$r[1]],$d),$title,600,300);}
?>
