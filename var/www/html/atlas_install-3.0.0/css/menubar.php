<?php
function menubar($path=".") {
?>
      <button type="button" id="atlas-mobile-menu-toggle" class="atlas-mobile-menu-toggle" aria-controls="menubar" aria-expanded="false">☰ Menu</button>
      <div id="atlas-mobile-menu-backdrop" class="atlas-mobile-menu-backdrop" hidden></div>
      <div id="menubar">
        <div class="atlas-mobile-menu-title"><span>Menu</span><button type="button" id="atlas-mobile-menu-close" aria-label="Chiudi menu">×</button></div>
        <ul id="menu" class="dropdown dropdown-horizontal">
          <li><a href="#" class="dir">Operazioni</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>" title="Home page">Home</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/user.php" title="User registration">Registrazione utente</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/rai.php" title="Request an installation">Richiedi installazione</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/pin.php" title="Pin an installed release">Fissa una release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/subscribe.php" title="Subscribe to email notifications">Sottoscrizioni email</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/req.php" title="Show the installation requests">Mostra richieste</A></li>
              <li><A HREF="<?php echo $path; ?>/list.php?summary=1" title="Show the installation summary">Riepilogo installazioni</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tags.php" title="Show the tags matrix">Matrice tag</A><li>
              <li><A HREF="<?php echo $path; ?>/mmap.php" title="Site map">Mappa siti</A><li>
              <li><A HREF="<?php echo $path; ?>/usage_plots.php" title="Usage plots">Grafici utilizzo</A><li>
              <li><A HREF="<?php echo $path; ?>/protected/configuration.php" title="Server configuration">Configurazione server</A></li>
              <?php if (atlas_has_role(['master'])): ?><li><A HREF="<?php echo $path; ?>/protected/local_users.php" title="Local users">Utenti locali</A></li><?php endif; ?>
              <?php if (atlas_current_identity() && (atlas_current_identity()['source'] ?? '') === 'local'): ?><li><A HREF="<?php echo $path; ?>/auth/change_password.php">Cambia password</A></li><li><A HREF="<?php echo $path; ?>/auth/logout.php">Esci</A></li><?php else: ?><li><A HREF="<?php echo $path; ?>/auth/login.php">Login locale</A></li><?php endif; ?>
            </ul>
          </li>
          <li><a href="#" class="dir">Architetture</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=define" title="Define a new architecture">Definisci architettura</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=update" title="Update an architecture definition">Modifica architettura</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=delete" title="Remove an architecture definition">Rimuovi architettura</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">InfoSys</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=define" title="Define a new InfoSys">Definisci InfoSys</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=update" title="Update an InfoSys definition">Modifica InfoSys</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=delete" title="Remove an InfoSys">Rimuovi InfoSys</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/ispardef.php" title="InfoSys parameters management">Parametri InfoSys</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Release</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/reldef.php?mode=define" title="Define a new release">Definisci release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/reldef.php?mode=update" title="Update a release definition">Modifica release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/rel.php" title="Show the release matrix">Matrice release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php" title="Manage the release criticality">Critical Releases - All</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php?tier_level=1&show_missing" title="Manage the release criticality [T1]">Critical Releases - T1</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php?tier_level=2&show_missing" title="Manage the release criticality [T2]">Critical Releases - T2</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/pardef.php" title="Release parameters management">Parametri release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/relsub.php" title="Release subscriptions">Sottoscrizioni release</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Siti</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=define" title="Define a new site">Definisci sito</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=update" title="Update a site definition">Modifica sito</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=delete" title="Remove a site">Rimuovi sito</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitepardef.php" title="Site parameters management">Parametri sito</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Target</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=define" title="Define a new target">Definisci target</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=update" title="Update a target definition">Modifica target</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=delete" title="Remove a target definition">Rimuovi target</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Task</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=define" title="Define a new task">Definisci task</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=update" title="Update a task definition">Modifica task</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=delete" title="Remove a task definition">Rimuovi task</A></li>
            </ul>
          </li>
          <li><a href="#" id="trigger" class="dir">Aiuto</a>
          </li>
        </ul></div>
<script>
(function(){
  function mobile(){ return window.matchMedia('(max-width: 760px)').matches; }
  var bar=document.getElementById('menubar'), toggle=document.getElementById('atlas-mobile-menu-toggle'), close=document.getElementById('atlas-mobile-menu-close'), backdrop=document.getElementById('atlas-mobile-menu-backdrop');
  if(!bar||!toggle) return;
  function setOpen(open){
    bar.classList.toggle('atlas-mobile-open',open);
    toggle.setAttribute('aria-expanded',open?'true':'false');
    if(backdrop){backdrop.hidden=!open; backdrop.classList.toggle('is-open',open);}
    document.documentElement.classList.toggle('atlas-menu-open',open);
  }
  toggle.addEventListener('click',function(){setOpen(!bar.classList.contains('atlas-mobile-open'));});
  if(close) close.addEventListener('click',function(){setOpen(false);});
  if(backdrop) backdrop.addEventListener('click',function(){setOpen(false);});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')setOpen(false);});
  bar.querySelectorAll('#menu > li > a.dir').forEach(function(a){
    a.addEventListener('click',function(e){
      if(!mobile()) return;
      var submenu=a.parentElement.querySelector(':scope > ul');
      if(!submenu) return;
      e.preventDefault();
      a.parentElement.classList.toggle('atlas-mobile-section-open');
    });
  });
  bar.querySelectorAll('#menu ul a').forEach(function(a){a.addEventListener('click',function(){if(mobile())setOpen(false);});});
  window.addEventListener('resize',function(){if(!mobile())setOpen(false);});
})();
</script>
<?php } ?>
