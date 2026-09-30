<?php
function main_header($voname, $path=".") {
  $ident = atlas_current_identity();
  $lang = atlas_lang();
  $displayName = '';
  if ($ident) $displayName = (string)($ident['username'] ?? $ident['name'] ?? '');
  $uri=(string)($_SERVER['REQUEST_URI']??'/atlas_install/');
  $p=parse_url($uri); $q=[]; parse_str((string)($p['query']??''),$q); unset($q['lang']);
  $ret=($p['path']??'/atlas_install/').($q?'?'.http_build_query($q):'');
  $langUrl=static fn(string $l): string => '/atlas_install/lang.php?lang='.rawurlencode($l).'&return='.rawurlencode($ret);
?>
<header id="atlas-topbar" class="atlas-topbar">
  <div class="atlas-topbar-brand">
    <button type="button" id="atlas-sidebar-toggle" class="atlas-sidebar-toggle" aria-controls="menubar" aria-expanded="false" aria-label="<?php echo atlas_h(atlas_t('menu')); ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <a class="atlas-brand" href="<?php echo $path; ?>/" aria-label="LJSF 3 home">
      <img class="atlas-brand-logo" src="<?php echo $path; ?>/img/ljsf3-logo.png" alt="LJSF 3 - ATLAS Installation System">
    </a>
  </div>
  <div class="atlas-topbar-sky" aria-hidden="true"></div>
  <div class="atlas-header-actions">
    <div class="atlas-language-control" aria-label="<?php echo atlas_h(atlas_t('language')); ?>">
      <span class="atlas-language-flag" aria-hidden="true"><?php echo $lang==='it'?'🇮🇹':'🇬🇧'; ?></span>
      <span class="atlas-language-code"><?php echo strtoupper($lang); ?></span>
      <span class="atlas-language-chevron">⌄</span>
      <div class="atlas-language-menu">
        <a href="<?php echo atlas_h($langUrl('it')); ?>"<?php echo $lang==='it'?' class="active"':''; ?>>🇮🇹 IT</a>
        <a href="<?php echo atlas_h($langUrl('en')); ?>"<?php echo $lang==='en'?' class="active"':''; ?>>🇬🇧 EN</a>
      </div>
    </div>
    <a class="atlas-topbar-icon" href="<?php echo $path; ?>/documentation.php" aria-label="<?php echo atlas_h(atlas_t('help')); ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.9 9.1a2.45 2.45 0 0 1 4.75.9c0 1.9-2.65 2.15-2.65 4M12 17.5h.01"/></svg>
    </a>
    <a class="atlas-topbar-icon atlas-user-button" href="<?php echo $ident ? $path.'/protected/user_info.php' : $path.'/auth/login.php'; ?>" aria-label="<?php echo atlas_h($ident ? ($displayName?:atlas_t('current_user_details')) : atlas_t('local_login')); ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.8 20c.9-4 3.1-6 7.2-6s6.3 2 7.2 6"/></svg>
    </a>
  </div>
</header>
<?php } ?>
