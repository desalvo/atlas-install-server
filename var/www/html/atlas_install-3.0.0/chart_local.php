<?php
function atlas_chart_escape($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');}
function atlas_chart_box(string $title,string $body,int $w=600,int $h=320): string {return '<div class="atlas-local-chart"><h3>'.atlas_chart_escape($title).'</h3><svg role="img" aria-label="'.atlas_chart_escape($title).'" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="xMidYMid meet">'.$body.'</svg></div>';}
function atlas_chart_line(array $points,string $title,int $w=600,int $h=300): string {
  if(!$points)return atlas_chart_box($title,'<text x="20" y="50">No data</text>',$w,$h);
  $pad=42;$vals=array_map(fn($p)=>(float)$p[1],$points);$max=max(1,max($vals));$n=count($points);$coords=[];
  foreach($points as $i=>$p){$x=$pad+($n>1?$i*($w-2*$pad)/($n-1):0);$y=$h-$pad-((float)$p[1]/$max)*($h-2*$pad);$coords[]=round($x,1).','.round($y,1);} 
  $b='<line x1="'.$pad.'" y1="'.($h-$pad).'" x2="'.($w-$pad).'" y2="'.($h-$pad).'" class="axis"/><line x1="'.$pad.'" y1="'.$pad.'" x2="'.$pad.'" y2="'.($h-$pad).'" class="axis"/><polyline fill="none" points="'.implode(' ',$coords).'" class="series"/>';
  foreach($points as $i=>$p){if($i%max(1,(int)ceil($n/6))!==0&&$i!==$n-1)continue;$x=$pad+($n>1?$i*($w-2*$pad)/($n-1):0);$b.='<text x="'.round($x,1).'" y="'.($h-12).'" text-anchor="middle">'.atlas_chart_escape($p[0]).'</text>';}
  return atlas_chart_box($title,$b,$w,$h);
}
function atlas_chart_bars(array $rows,string $title,int $w=600,int $h=320): string {
  if(!$rows)return atlas_chart_box($title,'<text x="20" y="50">No data</text>',$w,$h);$pad=46;$max=max(1,max(array_map(fn($r)=>(float)$r[1],$rows)));$n=count($rows);$slot=($w-2*$pad)/max(1,$n);$bw=max(4,$slot*.66);$b='';
  foreach($rows as $i=>$r){$bh=((float)$r[1]/$max)*($h-2*$pad);$x=$pad+$i*$slot+($slot-$bw)/2;$y=$h-$pad-$bh;$b.='<rect x="'.round($x,1).'" y="'.round($y,1).'" width="'.round($bw,1).'" height="'.round($bh,1).'" class="bar"><title>'.atlas_chart_escape($r[0].': '.$r[1]).'</title></rect>';if($n<=12||$i%max(1,(int)ceil($n/8))===0)$b.='<text x="'.round($x+$bw/2,1).'" y="'.($h-12).'" text-anchor="middle">'.atlas_chart_escape($r[0]).'</text>';}
  return atlas_chart_box($title,$b,$w,$h);
}
function atlas_chart_pie(array $rows,string $title,int $w=560,int $h=320): string {
  if(!$rows)return atlas_chart_box($title,'<text x="20" y="50">No data</text>',$w,$h);$sum=array_sum(array_map(fn($r)=>(float)$r[1],$rows));if($sum<=0)$sum=1;$cx=145;$cy=155;$r=92;$a=-M_PI/2;$colors=['#155eef','#12a594','#e59a18','#c2414f','#667085','#7c3aed','#0891b2','#4f46e5'];$b='';
  foreach($rows as $i=>$row){$da=2*M_PI*((float)$row[1]/$sum);$a2=$a+$da;$x1=$cx+$r*cos($a);$y1=$cy+$r*sin($a);$x2=$cx+$r*cos($a2);$y2=$cy+$r*sin($a2);$large=$da>M_PI?1:0;$b.='<path d="M '.$cx.' '.$cy.' L '.round($x1,2).' '.round($y1,2).' A '.$r.' '.$r.' 0 '.$large.' 1 '.round($x2,2).' '.round($y2,2).' Z" fill="'.$colors[$i%count($colors)].'"><title>'.atlas_chart_escape($row[0].': '.$row[1]).'</title></path>';$ly=55+$i*25;$b.='<rect x="285" y="'.($ly-12).'" width="13" height="13" rx="3" fill="'.$colors[$i%count($colors)].'"/><text x="307" y="'.$ly.'">'.atlas_chart_escape($row[0]).' ('.atlas_chart_escape($row[1]).')</text>';$a=$a2;}
  return atlas_chart_box($title,$b,$w,$h);
}
function atlas_chart_result_rows($result): array {$out=[];while($r=mysqli_fetch_row($result))$out[]=$r;return $out;}
