<?php
if (!function_exists('atlas_sidebar_icon')) {
function atlas_sidebar_icon(string $name): string {
  $paths=[
    'home'=>'<path d="M3 11.2 12 4l9 7.2"/><path d="M5.5 10.5V20h5v-5h3v5h5v-9.5"/>',
    'operations'=>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    'architectures'=>'<circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M7 6h10M6.3 7.7l4.4 8.4M17.7 7.7l-4.4 8.4"/>',
    'infosys'=>'<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
    'releases'=>'<path d="m12 3 8 4-8 4-8-4 8-4Z"/><path d="m4 12 8 4 8-4M4 17l8 4 8-4"/>',
    'sites'=>'<path d="M4 20h16M6 20v-5h4v5M14 20v-8h4v8M8 15V8h6v12"/><path d="M10 5h2v3"/>',
    'targets'=>'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="m14 10 6-6M17 4h3v3"/>',
    'tasks'=>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
  ];
  return '<svg class="atlas-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true">'.($paths[$name]??$paths['operations']).'</svg>';
}
}
function menubar($path=".") {
  $it=atlas_lang()==='it';
  $sections = [
    ['operations','operations',[
      ['user_registration','/protected/user.php'],['request_install','/protected/rai.php'],['pin_release','/protected/pin.php'],['email_subscriptions','/protected/subscribe.php'],['show_requests','/protected/req.php'],['install_summary','/list.php?summary=1'],['tag_matrix','/protected/tags.php'],['site_map','/mmap.php'],['usage_charts','/usage_plots.php'],['server_config','/protected/configuration.php']
    ]],
    ['architectures','architectures',[[ 'define_arch','/protected/archdef.php?mode=define'],['update_arch','/protected/archdef.php?mode=update'],['delete_arch','/protected/archdef.php?mode=delete'],[$it?'Matrice architetture':'Architecture matrix','/protected/archdef.php']]],
    ['infosys','infosys',[[$it?'Gestione InfoSys':'Manage InfoSys','/protected/isdef.php?mode=define'],[$it?'Matrice InfoSys':'InfoSys matrix','/protected/ispardef.php']]],
    ['releases','releases',[[ 'define_release','/protected/reldef.php?mode=define'],['update_release','/protected/reldef.php?mode=update'],['release_matrix','/protected/rel.php'],['critical_releases','/protected/critical.php'],['release_params','/protected/pardef.php'],['release_subscriptions','/protected/relsub.php']]],
    ['sites','sites',[[ 'define_site','/protected/sitedef.php?mode=define'],['update_site','/protected/sitedef.php?mode=update'],['delete_site','/protected/sitedef.php?mode=delete'],[$it?'Matrice siti':'Site matrix','/protected/sitepardef.php']]],
    ['targets','targets',[[$it?'Gestione target':'Manage targets','/protected/tgtdef.php?mode=define'],[$it?'Matrice target':'Target matrix','/protected/tgtdef.php']]],
    ['tasks','tasks',[[$it?'Task in esecuzione':'Running tasks','/jobs.php'],[$it?'Task pianificati':'Scheduled tasks','/protected/taskdef.php'],[$it?'Storico task':'Task history','/historical_reports.php']]],
  ];
  $current=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:'';
?>
<div id="atlas-mobile-menu-backdrop" class="atlas-mobile-menu-backdrop" hidden></div>
<nav id="menubar" class="atlas-sidebar" aria-label="<?php echo atlas_h(atlas_t('menu')); ?>">
  <a class="atlas-sidebar-home<?php echo preg_match('~/atlas_install/?(?:index\.php)?$~',$current)?' is-active':''; ?>" href="<?php echo $path; ?>/">
    <?php echo atlas_sidebar_icon('home'); ?><span><?php echo atlas_h(atlas_t('home')); ?></span>
  </a>
  <div class="atlas-sidebar-scroll">
  <?php foreach($sections as $section): $sectionId='atlas-sidebar-links-'.$section[0]; ?>
    <section class="atlas-sidebar-section" data-section="<?php echo atlas_h($section[0]); ?>">
      <button type="button" class="atlas-sidebar-heading" aria-expanded="true" aria-controls="<?php echo atlas_h($sectionId); ?>">
        <?php echo atlas_sidebar_icon($section[0]); ?><span><?php echo atlas_h(atlas_t($section[1])); ?></span><span class="atlas-sidebar-caret" aria-hidden="true">⌃</span>
      </button>
      <div id="<?php echo atlas_h($sectionId); ?>" class="atlas-sidebar-links">
        <?php foreach($section[2] as $item):
          $label=(str_contains((string)$item[0],' ') || str_contains((string)$item[0],'Matrice') || str_contains((string)$item[0],'Gestione') || str_contains((string)$item[0],'Task ') || str_contains((string)$item[0],'Running') || str_contains((string)$item[0],'Scheduled')) ? (string)$item[0] : atlas_t((string)$item[0]);
          $href=$path.$item[1];
          $linkPath=parse_url($item[1],PHP_URL_PATH)?:'';
          $active=$linkPath!=='' && str_ends_with($current,$linkPath);
        ?>
          <a class="<?php echo $active?'is-active':''; ?>" href="<?php echo atlas_h($href); ?>"><?php echo atlas_h($label); ?></a>
        <?php endforeach; ?>
        <?php if($section[0]==='operations' && atlas_has_role(['master'])): ?><a href="<?php echo $path; ?>/protected/local_users.php"><?php echo atlas_h(atlas_t('local_users')); ?></a><?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
</nav>
<script>
(function(){
  function initAtlasSidebar(){
    var bar=document.getElementById('menubar'),toggle=document.getElementById('atlas-sidebar-toggle'),backdrop=document.getElementById('atlas-mobile-menu-backdrop');
    if(!bar||!toggle||bar.dataset.ready==='1')return; bar.dataset.ready='1';
    function mobile(){
      if(!window.matchMedia)return window.innerWidth<=900;
      return window.matchMedia('(max-width:900px), (max-width:1180px) and (hover:none) and (pointer:coarse)').matches;
    }
    // null means the responsive mode has not been evaluated yet.  Do not
    // use a plain boolean here: iOS Safari emits resize events when its
    // browser chrome expands/collapses, especially in portrait mode.  Those
    // height-only resizes must not be mistaken for a desktop/mobile change.
    var mobileMode=null;
    function setSectionCollapsed(sec,collapsed){
      if(!sec)return;
      var btn=sec.querySelector('.atlas-sidebar-heading');
      sec.classList.toggle('is-collapsed',!!collapsed);
      if(btn)btn.setAttribute('aria-expanded',collapsed?'false':'true');
    }
    function collapseAllSections(){
      bar.querySelectorAll('.atlas-sidebar-section').forEach(function(sec){setSectionCollapsed(sec,true);});
    }
    function setOpen(open){
      var isMobile=mobile();
      open=!!open && isMobile;
      bar.classList.toggle('atlas-mobile-open',open);
      toggle.setAttribute('aria-expanded',open?'true':'false');
      if(isMobile) bar.setAttribute('aria-hidden',open?'false':'true'); else bar.removeAttribute('aria-hidden');
      if(backdrop){backdrop.hidden=!open;backdrop.classList.toggle('is-open',open);}
      document.documentElement.classList.toggle('atlas-menu-open',open);
      if(isMobile && !open) collapseAllSections();
    }
    function syncMode(){
      var nowMobile=mobile();
      var modeChanged=(mobileMode===null || nowMobile!==mobileMode);
      if(!modeChanged)return;
      if(nowMobile) collapseAllSections();
      else bar.querySelectorAll('.atlas-sidebar-section').forEach(function(sec){setSectionCollapsed(sec,false);});
      mobileMode=nowMobile;
      setOpen(false);
    }
    function resetAfterOrientationChange(){
      // Orientation changes are genuine layout transitions, unlike the
      // portrait Safari height-only resizes caused by the address/tool bars.
      var nowMobile=mobile();
      if(nowMobile){
        mobileMode=nowMobile;
        setOpen(false);
        collapseAllSections();
      }else{
        mobileMode=nowMobile;
        setOpen(false);
        bar.querySelectorAll('.atlas-sidebar-section').forEach(function(sec){setSectionCollapsed(sec,false);});
      }
    }
    toggle.addEventListener('click',function(e){e.preventDefault();setOpen(!bar.classList.contains('atlas-mobile-open'));});
    if(backdrop)backdrop.addEventListener('click',function(){setOpen(false);});
    bar.querySelectorAll('.atlas-sidebar-heading').forEach(function(btn){
      btn.addEventListener('click',function(e){
        e.preventDefault();
        var sec=btn.closest('.atlas-sidebar-section');
        setSectionCollapsed(sec,!sec.classList.contains('is-collapsed'));
      });
    });
    bar.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){if(mobile())setOpen(false);});});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')setOpen(false);});
    window.addEventListener('resize',syncMode);
    window.addEventListener('orientationchange',function(){window.setTimeout(resetAfterOrientationChange,0);});
    syncMode();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initAtlasSidebar);else initAtlasSidebar();
})();
</script>
<?php } ?>
