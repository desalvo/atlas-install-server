<?php
require('dbsetup.php');
require('config.php');
$it = atlas_lang()==='it';

function atlas_dash_rows(string $sql): array {
  try {
    $r=db_query($sql,'ro'); $out=[];
    while($row=mysqli_fetch_assoc($r)) $out[]=$row;
    return $out;
  } catch(Throwable $e) {
    if(function_exists('atlas_app_log')) atlas_app_log('dashboard_query_failed',['message'=>$e->getMessage()]);
    return [];
  }
}
function atlas_dash_scalar(string $sql, int $fallback=0): int {
  $r=atlas_dash_rows($sql); if(!$r)return $fallback; $row=$r[0]; return (int)reset($row);
}
function atlas_dash_pct(int $value,int $max): int { return $max>0 ? max(3,(int)round($value*100/$max)) : 0; }
function atlas_dash_status_class(string $status): string {
  $s=strtolower(trim($status));
  if(preg_match('/installed|done|complete|success|finished|retrieved/',$s)) return 'ok';
  if(preg_match('/fail|error|abort|reject/',$s)) return 'fail';
  if(preg_match('/run|submit|accept|progress|queue|pend/',$s)) return 'run';
  return 'plan';
}
function atlas_dash_status_label(string $status,bool $it): string {
  $c=atlas_dash_status_class($status);
  if(!$it)return $c==='ok'?'Completed':($c==='fail'?'Failed':($c==='run'?'Running':'Planned'));
  return $c==='ok'?'Completata':($c==='fail'?'Fallito':($c==='run'?'In esecuzione':'Pianificata'));
}
function atlas_dash_icon(string $kind): string {
  $p=[
   'release'=>'<path d="m12 3 8 4-8 4-8-4 8-4Z"/><path d="m4 12 8 4 8-4M4 17l8 4 8-4"/>',
   'sites'=>'<rect x="9" y="3" width="6" height="5"/><rect x="3" y="16" width="5" height="5"/><rect x="10" y="16" width="5" height="5"/><rect x="17" y="16" width="4" height="5"/><path d="M12 8v4M5.5 16v-4h13v4"/>',
   'install'=>'<rect x="3" y="4" width="18" height="13" rx="1"/><path d="M8 21h8M12 17v4"/>',
   'task'=>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
   'plus'=>'<circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/>',
   'bars'=>'<path d="M5 20v-7M10 20V7M15 20v-11M20 20V4"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($p[$kind]??$p['task']).'</svg>';
}

$releaseCount=atlas_dash_scalar("SELECT COUNT(*) c FROM release_data WHERE typefk > 1");
$siteCount=atlas_dash_scalar("SELECT COUNT(*) c FROM site");
$installCount=atlas_dash_scalar("SELECT COUNT(*) c FROM release_stat");
$runningCount=atlas_dash_scalar("SELECT COUNT(*) c FROM job WHERE retrieval_time IS NULL AND LOWER(COALESCE(status,'')) NOT REGEXP 'finished|failed|done|retrieved|cancel|abort'");

$monthRows=atlas_dash_rows("SELECT DATE_FORMAT(date,'%Y-%m') ym, COUNT(*) c FROM release_stat WHERE date >= DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(date,'%Y-%m') ORDER BY ym");
$monthMap=[]; foreach($monthRows as $r)$monthMap[(string)$r['ym']]=(int)$r['c'];
$months=[]; $now=new DateTimeImmutable('first day of this month');
for($i=11;$i>=0;$i--){$d=$now->modify("-$i months");$key=$d->format('Y-m');$label=$it?['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'][(int)$d->format('n')-1]:$d->format('M');$months[]=[$label,$monthMap[$key]??0];}
$monthMax=max(1,...array_column($months,1));

$statusRows=atlas_dash_rows("SELECT LOWER(COALESCE(status,'')) status, COUNT(*) c FROM release_stat GROUP BY LOWER(COALESCE(status,''))");
$status=['ok'=>0,'run'=>0,'fail'=>0,'plan'=>0];
foreach($statusRows as $r){$c=atlas_dash_status_class((string)$r['status']);$status[$c]+=(int)$r['c'];}
$statusTotal=array_sum($status); if($statusTotal===0)$statusTotal=$installCount;
$p1=$statusTotal?round($status['ok']*100/$statusTotal,2):0; $p2=$statusTotal?round($status['run']*100/$statusTotal,2):0; $p3=$statusTotal?round($status['fail']*100/$statusTotal,2):0;

$topSites=atlas_dash_rows("SELECT s.name,COUNT(*) c FROM release_stat rs JOIN site s ON s.ref=rs.sitefk GROUP BY s.ref,s.name ORDER BY c DESC,s.name LIMIT 5");
$siteMax=1; foreach($topSites as $r)$siteMax=max($siteMax,(int)$r['c']);

$latestReq=atlas_dash_rows("SELECT r.id,s.name site,rs.name rel,COALESCE(st.description,'') status,r.request_date,COALESCE(u.name,'') requester FROM request r LEFT JOIN site s ON s.ref=r.sitefk LEFT JOIN release_stat rs ON rs.ref=r.relfk LEFT JOIN request_status st ON st.ref=r.statusfk LEFT JOIN user u ON u.ref=r.userfk ORDER BY r.request_date DESC LIMIT 5");
$latestJobs=atlas_dash_rows("SELECT id,name,COALESCE(status,'') status,submission_time,TIMESTAMPDIFF(SECOND,submission_time,COALESCE(retrieval_time,NOW())) duration FROM job ORDER BY submission_time DESC LIMIT 5");
$relUsage=atlas_dash_rows("SELECT name,COUNT(*) c FROM release_stat WHERE date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY name ORDER BY MAX(date) DESC LIMIT 6");
$relUsage=array_reverse($relUsage); $relMax=1; foreach($relUsage as $r)$relMax=max($relMax,(int)$r['c']);
$spaceSites=atlas_dash_rows("SELECT name,GREATEST(COALESCE(capacity,0)-COALESCE(available,0),0) used FROM site WHERE COALESCE(capacity,0)>0 ORDER BY used DESC LIMIT 5");
$spaceMax=1; foreach($spaceSites as $r)$spaceMax=max($spaceMax,(int)$r['used']);
?>
<!doctype html>
<html lang="<?php echo atlas_h(atlas_lang()); ?>">
<head>
<title><?php echo atlas_h($LJSFi_VO); ?> Installation System</title>
<?php require("./css/page_header.php"); page_header("."); ?>
</head>
<body class="atlas-dashboard-page">
<div id="main">
  <div id="header">
    <?php require("./css/main_header.php"); main_header($LJSFi_VO, "."); ?>
    <?php require("./css/menubar.php"); menubar("."); ?>
  </div>
  <div id="site_content">
    <main id="content" class="atlas-dashboard">
      <section class="atlas-dashboard-hero">
        <div class="atlas-dashboard-intro">
          <h1 class="atlas-dashboard-wordmark">LJSF <span>3</span></h1>
          <div class="atlas-dashboard-subtitle">ATLAS INSTALLATION SYSTEM</div>
          <div class="atlas-dashboard-description"><?php echo $it?'Sistema per la gestione del deployment, validazione e operazioni sui siti.':'System for software deployment, validation and site operations.'; ?></div>
        </div>
        <div class="atlas-dashboard-earth" role="img" aria-label="ATLAS global infrastructure"></div>
      </section>

      <section class="atlas-kpi-grid">
        <article class="atlas-kpi-card"><div class="atlas-kpi-icon blue"><?php echo atlas_dash_icon('release'); ?></div><div><div class="atlas-kpi-label">Release</div><div class="atlas-kpi-value"><?php echo $releaseCount; ?></div><div class="atlas-kpi-meta"><?php echo $it?'Totali disponibili':'Available'; ?></div><a class="atlas-kpi-link" href="protected/rel.php"><?php echo $it?'Visualizza release':'View releases'; ?></a></div></article>
        <article class="atlas-kpi-card"><div class="atlas-kpi-icon green"><?php echo atlas_dash_icon('sites'); ?></div><div><div class="atlas-kpi-label"><?php echo $it?'Siti':'Sites'; ?></div><div class="atlas-kpi-value"><?php echo $siteCount; ?></div><div class="atlas-kpi-meta"><?php echo $it?'Siti configurati':'Configured sites'; ?></div><a class="atlas-kpi-link" href="mmap.php"><?php echo $it?'Visualizza siti':'View sites'; ?></a></div></article>
        <article class="atlas-kpi-card"><div class="atlas-kpi-icon red"><?php echo atlas_dash_icon('install'); ?></div><div><div class="atlas-kpi-label"><?php echo $it?'Installazioni':'Installations'; ?></div><div class="atlas-kpi-value"><?php echo $installCount; ?></div><div class="atlas-kpi-meta"><?php echo $it?'Totali':'Total'; ?></div><a class="atlas-kpi-link" href="list.php"><?php echo $it?'Mostra richieste':'Show requests'; ?></a></div></article>
        <article class="atlas-kpi-card"><div class="atlas-kpi-icon amber"><?php echo atlas_dash_icon('task'); ?></div><div><div class="atlas-kpi-label">Task</div><div class="atlas-kpi-value"><?php echo $runningCount; ?></div><div class="atlas-kpi-meta"><?php echo $it?'In esecuzione':'Running'; ?></div><a class="atlas-kpi-link" href="jobs.php"><?php echo $it?'Mostra task':'Show tasks'; ?></a></div></article>
      </section>

      <section class="atlas-dashboard-row three">
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Installazioni per mese':'Installations by month'; ?></h2><div class="atlas-chart"><div class="atlas-chart-grid"></div><span class="atlas-chart-y y0">0</span><span class="atlas-chart-y y1"><?php echo (int)round($monthMax*.25); ?></span><span class="atlas-chart-y y2"><?php echo (int)round($monthMax*.5); ?></span><span class="atlas-chart-y y3"><?php echo (int)round($monthMax*.75); ?></span><span class="atlas-chart-y y4"><?php echo $monthMax; ?></span><span class="atlas-chart-legend"><i></i><?php echo $it?'Installazioni':'Installations'; ?></span><div class="atlas-chart-bars"><?php foreach($months as [$m,$v]): ?><div class="atlas-chart-bar-wrap"><div class="atlas-chart-bar" style="height:<?php echo atlas_dash_pct($v,$monthMax); ?>%" title="<?php echo atlas_h($m.': '.$v); ?>"></div><span class="atlas-chart-label"><?php echo atlas_h($m); ?></span></div><?php endforeach; ?></div></div></article>
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Stato installazioni':'Installation status'; ?></h2><div class="atlas-status-wrap"><div class="atlas-donut" style="--p1:<?php echo $p1; ?>%;--p2:<?php echo $p2; ?>%;--p3:<?php echo $p3; ?>%"><div class="atlas-donut-total"><?php echo $statusTotal; ?></div></div><div class="atlas-status-list"><div class="atlas-status-item"><i class="atlas-status-dot green"></i><span><?php echo $it?'Completate':'Completed'; ?></span><b><?php echo $status['ok']; ?></b></div><div class="atlas-status-item"><i class="atlas-status-dot blue"></i><span><?php echo $it?'In corso':'Running'; ?></span><b><?php echo $status['run']; ?></b></div><div class="atlas-status-item"><i class="atlas-status-dot red"></i><span><?php echo $it?'Fallite':'Failed'; ?></span><b><?php echo $status['fail']; ?></b></div><div class="atlas-status-item"><i class="atlas-status-dot gray"></i><span><?php echo $it?'Pianificate':'Planned'; ?></span><b><?php echo $status['plan']; ?></b></div></div></div></article>
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Top 5 siti per installazioni':'Top 5 sites by installations'; ?></h2><div class="atlas-horizontal-bars"><?php if(!$topSites): ?><div class="atlas-muted">—</div><?php endif; foreach($topSites as $r): ?><div class="atlas-hbar-row"><span><?php echo atlas_h((string)$r['name']); ?></span><div class="atlas-hbar-track"><div class="atlas-hbar-fill" style="width:<?php echo atlas_dash_pct((int)$r['c'],$siteMax); ?>%"></div></div><b class="atlas-hbar-value"><?php echo (int)$r['c']; ?></b></div><?php endforeach; ?></div></article>
      </section>

      <section class="atlas-dashboard-row two">
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Ultime richieste di installazione':'Latest installation requests'; ?></h2><table class="atlas-dashboard-table" data-no-pagination="1" data-no-mobile-collapse="1"><thead><tr><th>ID</th><th><?php echo $it?'Sito':'Site'; ?></th><th>Release</th><th><?php echo $it?'Stato':'Status'; ?></th><th><?php echo $it?'Creato il':'Created'; ?></th><th><?php echo $it?'Richiedente':'Requester'; ?></th></tr></thead><tbody><?php foreach($latestReq as $r): $st=(string)$r['status']; ?><tr><td><a class="atlas-table-link" href="protected/showreq.php?id=<?php echo rawurlencode((string)$r['id']); ?>"><?php echo atlas_h((string)$r['id']); ?></a></td><td><?php echo atlas_h((string)$r['site']); ?></td><td><?php echo atlas_h((string)$r['rel']); ?></td><td><span class="atlas-status-pill <?php echo atlas_dash_status_class($st); ?>"><?php echo atlas_h(atlas_dash_status_label($st,$it)); ?></span></td><td><?php echo atlas_h((string)$r['request_date']); ?></td><td><?php echo atlas_h((string)$r['requester']); ?></td></tr><?php endforeach; ?></tbody></table><a class="atlas-panel-link" href="protected/req.php"><?php echo $it?'Mostra tutte le richieste':'Show all requests'; ?></a></article>
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Ultimi task':'Latest tasks'; ?></h2><table class="atlas-dashboard-table" data-no-pagination="1" data-no-mobile-collapse="1"><thead><tr><th>ID</th><th><?php echo $it?'Nome':'Name'; ?></th><th><?php echo $it?'Stato':'Status'; ?></th><th><?php echo $it?'Inizio':'Start'; ?></th><th><?php echo $it?'Durata':'Duration'; ?></th></tr></thead><tbody><?php foreach($latestJobs as $r): $st=(string)$r['status'];$d=max(0,(int)$r['duration']);$dur=sprintf('%02d:%02d:%02d',intdiv($d,3600),intdiv($d%3600,60),$d%60); ?><tr><td><?php echo atlas_h((string)$r['id']); ?></td><td><?php echo atlas_h((string)$r['name']); ?></td><td><span class="atlas-status-pill <?php echo atlas_dash_status_class($st); ?>"><?php echo atlas_h(atlas_dash_status_label($st,$it)); ?></span></td><td><?php echo atlas_h((string)$r['submission_time']); ?></td><td><?php echo $dur; ?></td></tr><?php endforeach; ?></tbody></table><a class="atlas-panel-link" href="jobs.php"><?php echo $it?'Mostra tutti i task':'Show all tasks'; ?></a></article>
      </section>

      <section class="atlas-dashboard-row two">
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Utilizzo risorse':'Resource usage'; ?></h2><div class="atlas-resource-panel"><div><div class="atlas-resource-subtitle"><?php echo $it?'Installazioni per release (ultimi 6 mesi)':'Installations by release (last 6 months)'; ?></div><div class="atlas-mini-bars"><?php foreach($relUsage as $r): ?><div class="atlas-mini-bar-wrap"><div class="atlas-mini-bar" style="height:<?php echo atlas_dash_pct((int)$r['c'],$relMax); ?>%"></div><span class="atlas-mini-label"><?php echo atlas_h((string)$r['name']); ?></span></div><?php endforeach; ?></div></div><div><div class="atlas-resource-subtitle"><?php echo $it?'Spazio utilizzato per sito':'Space used by site'; ?></div><div class="atlas-horizontal-bars"><?php foreach($spaceSites as $r): ?><div class="atlas-hbar-row"><span><?php echo atlas_h((string)$r['name']); ?></span><div class="atlas-hbar-track"><div class="atlas-hbar-fill" style="width:<?php echo atlas_dash_pct((int)$r['used'],$spaceMax); ?>%"></div></div><b class="atlas-hbar-value"><?php echo (int)$r['used']; ?></b></div><?php endforeach; ?></div></div></div></article>
        <article class="atlas-dashboard-panel"><h2 class="atlas-dashboard-panel-title"><?php echo $it?'Link rapidi':'Quick links'; ?></h2><div class="atlas-quick-grid"><a class="atlas-quick-card" href="protected/rai.php"><span class="atlas-quick-icon green"><?php echo atlas_dash_icon('plus'); ?></span><span><div class="atlas-quick-title"><?php echo $it?'Nuova installazione':'New installation'; ?></div><div class="atlas-quick-desc"><?php echo $it?'Crea una nuova richiesta':'Create a new request'; ?></div></span></a><a class="atlas-quick-card" href="protected/rel.php"><span class="atlas-quick-icon blue"><?php echo atlas_dash_icon('release'); ?></span><span><div class="atlas-quick-title"><?php echo $it?'Gestione release':'Release management'; ?></div><div class="atlas-quick-desc"><?php echo $it?'Definisci e modifica release':'Define and update releases'; ?></div></span></a><a class="atlas-quick-card" href="protected/sitedef.php?mode=define"><span class="atlas-quick-icon cyan"><?php echo atlas_dash_icon('sites'); ?></span><span><div class="atlas-quick-title"><?php echo $it?'Gestione siti':'Site management'; ?></div><div class="atlas-quick-desc"><?php echo $it?'Configura i siti':'Configure sites'; ?></div></span></a><a class="atlas-quick-card" href="usage_plots.php"><span class="atlas-quick-icon blue"><?php echo atlas_dash_icon('bars'); ?></span><span><div class="atlas-quick-title"><?php echo $it?'Grafici e report':'Charts and reports'; ?></div><div class="atlas-quick-desc"><?php echo $it?'Visualizza statistiche':'View statistics'; ?></div></span></a></div></article>
      </section>
    </main>
  </div>
</div>
</body>
</html>
