<?php
function menubar($path=".") {
  $sections = [
    ['operations','operations',[
      ['home','/'],['user_registration','/protected/user.php'],['request_install','/protected/rai.php'],['pin_release','/protected/pin.php'],['email_subscriptions','/protected/subscribe.php'],['show_requests','/protected/req.php'],['install_summary','/list.php?summary=1'],['tag_matrix','/protected/tags.php'],['site_map','/mmap.php'],['usage_charts','/usage_plots.php'],['server_config','/protected/configuration.php']
    ]],
    ['architectures','architectures',[[ 'define_arch','/protected/archdef.php?mode=define'],['update_arch','/protected/archdef.php?mode=update'],['delete_arch','/protected/archdef.php?mode=delete']]],
    ['infosys','infosys',[[ 'define_infosys','/protected/isdef.php?mode=define'],['update_infosys','/protected/isdef.php?mode=update'],['delete_infosys','/protected/isdef.php?mode=delete'],['infosys_params','/protected/ispardef.php']]],
    ['releases','releases',[[ 'define_release','/protected/reldef.php?mode=define'],['update_release','/protected/reldef.php?mode=update'],['release_matrix','/protected/rel.php'],['critical_releases','/protected/critical.php'],['release_params','/protected/pardef.php'],['release_subscriptions','/protected/relsub.php']]],
    ['sites','sites',[[ 'define_site','/protected/sitedef.php?mode=define'],['update_site','/protected/sitedef.php?mode=update'],['delete_site','/protected/sitedef.php?mode=delete'],['site_params','/protected/sitepardef.php']]],
    ['targets','targets',[[ 'define_target','/protected/tgtdef.php?mode=define'],['update_target','/protected/tgtdef.php?mode=update'],['delete_target','/protected/tgtdef.php?mode=delete']]],
    ['tasks','tasks',[[ 'define_task','/protected/taskdef.php?mode=define'],['update_task','/protected/taskdef.php?mode=update'],['delete_task','/protected/taskdef.php?mode=delete']]],
  ];
?>
<button type="button" id="atlas-mobile-menu-toggle" class="atlas-mobile-menu-toggle" aria-controls="menubar" aria-expanded="false" onclick="if(window.atlasToggleMobileMenu){return window.atlasToggleMobileMenu(event);}var b=document.getElementById('menubar'),d=document.getElementById('atlas-mobile-menu-backdrop');if(b){var o=!b.classList.contains('atlas-mobile-open');b.classList.toggle('atlas-mobile-open',o);this.setAttribute('aria-expanded',o?'true':'false');if(d){d.hidden=!o;d.classList.toggle('is-open',o);}document.documentElement.classList.toggle('atlas-menu-open',o);}return false">☰ <?php echo atlas_h(atlas_t('menu')); ?></button>
<div id="atlas-mobile-menu-backdrop" class="atlas-mobile-menu-backdrop" hidden></div>
<nav id="menubar" aria-label="<?php echo atlas_h(atlas_t('menu')); ?>">
  <div class="atlas-mobile-menu-title"><a class="atlas-menu-brand" href="<?php echo $path; ?>/"><img src="<?php echo $path; ?>/img/ljsf3-logo.png" alt="LJSF 3"></a><button type="button" id="atlas-mobile-menu-close" aria-label="<?php echo atlas_h(atlas_t('close_menu')); ?>">×</button></div>
  <ul id="menu" class="dropdown dropdown-horizontal">
    <li class="atlas-menu-logo"><a href="<?php echo $path; ?>/" aria-label="LJSF 3"><img src="<?php echo $path; ?>/img/ljsf3-icon.png" alt=""></a></li>
    <?php foreach($sections as $section): ?>
      <li class="atlas-menu-section"><a href="#" class="dir"><?php echo atlas_h(atlas_t($section[1])); ?></a>
        <ul>
          <?php foreach($section[2] as $item): ?><li><a href="<?php echo $path.$item[1]; ?>"><?php echo atlas_h(atlas_t($item[0])); ?></a></li><?php endforeach; ?>
          <?php if($section[0]==='operations' && atlas_has_role(['master'])): ?><li><a href="<?php echo $path; ?>/protected/local_users.php"><?php echo atlas_h(atlas_t('local_users')); ?></a></li><?php endif; ?>
          <?php if($section[0]==='operations'): ?>
            <?php if(atlas_current_identity() && (atlas_current_identity()['source']??'')==='local'): ?>
              <li><a href="<?php echo $path; ?>/auth/change_password.php"><?php echo atlas_h(atlas_t('change_password')); ?></a></li>
              <li><a href="<?php echo $path; ?>/auth/logout.php"><?php echo atlas_h(atlas_t('logout')); ?></a></li>
            <?php else: ?><li><a href="<?php echo $path; ?>/auth/login.php"><?php echo atlas_h(atlas_t('local_login')); ?></a></li><?php endif; ?>
          <?php endif; ?>
        </ul>
      </li>
    <?php endforeach; ?>
    <li class="atlas-menu-single"><a href="<?php echo $path; ?>/documentation.php"><?php echo atlas_h(atlas_t('documentation')); ?></a></li>
    <li class="atlas-menu-single"><a href="#" id="trigger"><?php echo atlas_h(atlas_t('help')); ?></a></li>
  </ul>
</nav>
<script>
(function(){
  function bindAtlasMenuFallback(){
    var bar=document.getElementById('menubar'), toggle=document.getElementById('atlas-mobile-menu-toggle'), close=document.getElementById('atlas-mobile-menu-close'), backdrop=document.getElementById('atlas-mobile-menu-backdrop');
    if(!bar||!toggle) return;
    function mobile(){return window.matchMedia?window.matchMedia('(max-width:760px)').matches:window.innerWidth<=760;}
    function setOpen(open){bar.classList.toggle('atlas-mobile-open',!!open);toggle.setAttribute('aria-expanded',open?'true':'false');if(backdrop){backdrop.hidden=!open;backdrop.classList.toggle('is-open',!!open);}document.documentElement.classList.toggle('atlas-menu-open',!!open);}
    if(!window.atlasSetMobileMenuOpen) window.atlasSetMobileMenuOpen=setOpen;
    if(!window.atlasToggleMobileMenu) window.atlasToggleMobileMenu=function(ev){if(ev){ev.preventDefault();ev.stopPropagation();}setOpen(!bar.classList.contains('atlas-mobile-open'));return false;};
    if(toggle.dataset.atlasFallbackBound!=='1'){toggle.dataset.atlasFallbackBound='1';toggle.addEventListener('click',window.atlasToggleMobileMenu,false);}
    if(close&&close.dataset.atlasFallbackBound!=='1'){close.dataset.atlasFallbackBound='1';close.addEventListener('click',function(e){e.preventDefault();setOpen(false);},false);}
    if(backdrop&&backdrop.dataset.atlasFallbackBound!=='1'){backdrop.dataset.atlasFallbackBound='1';backdrop.addEventListener('click',function(){setOpen(false);},false);}
    bar.querySelectorAll('#menu > li > a.dir').forEach(function(a){if(a.dataset.atlasFallbackBound==='1')return;a.dataset.atlasFallbackBound='1';a.addEventListener('click',function(e){if(!mobile())return;var li=a.parentElement,sub=li&&li.querySelector(':scope > ul');if(!sub)return;e.preventDefault();bar.querySelectorAll('#menu > li.atlas-mobile-section-open').forEach(function(x){if(x!==li)x.classList.remove('atlas-mobile-section-open');});li.classList.toggle('atlas-mobile-section-open');},false);});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bindAtlasMenuFallback,false);else bindAtlasMenuFallback();
})();
</script>

<?php } ?>
