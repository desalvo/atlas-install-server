<?php
function main_header($voname, $path=".") {
  $ident = atlas_current_identity();
  $authOk = (bool)$ident;
?>
      <div id="logo" class="atlas-hero">
        <a class="atlas-brand" href="<?php echo $path; ?>/" aria-label="LJSF 3 home">
          <img class="atlas-brand-logo" src="<?php echo $path; ?>/img/ljsf3-logo.png" alt="LJSF 3 - ATLAS Installation System">
        </a>
        <div id="logo_text" class="atlas-brand-copy">
          <h2><?php echo atlas_h(atlas_t('tagline')); ?></h2>
        </div>
        <div class="atlas-header-actions">
          <?php echo atlas_language_selector_html(); ?>
          <div class="atlas-auth-state <?php echo $authOk ? 'is-authenticated' : 'is-public'; ?>"><span class="atlas-auth-dot"></span><?php if ($ident): ?><?php echo atlas_h(($ident['source'] === 'local' ? atlas_t('local_user').': ' : atlas_t('certificate').': ') . ($ident['username'] ?? $ident['name'] ?? '') . ' · ' . (($ident['role'] ?? '') ?: atlas_t('not_assigned'))); ?><?php else: ?><?php echo atlas_h(atlas_t('public_session')); ?><?php endif; ?></div>
        </div>
      </div>
<?php } ?>
