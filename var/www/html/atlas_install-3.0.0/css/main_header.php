<?php
function main_header($voname, $path=".") {
  $ident = atlas_current_identity();
  $certOk = $ident && ($ident['source'] ?? '') === 'certificate';
  $authOk = (bool)$ident;
?>
      <div id="logo" class="atlas-hero">
        <div class="atlas-brandmark" aria-hidden="true">A</div>
        <div id="logo_text">
          <h1><a><?php echo htmlspecialchars((string)$voname, ENT_QUOTES, 'UTF-8'); ?> <span class="logo_colour">Installation System</span></a></h1>
          <h2>Software deployment, validation and site operations</h2>
        </div>
        <div class="atlas-auth-state <?php echo $authOk ? 'is-authenticated' : 'is-public'; ?>"><span class="atlas-auth-dot"></span><?php if ($ident): ?><?php echo atlas_h(($ident['source'] === 'local' ? 'Local: ' : 'Certificate: ') . ($ident['username'] ?? $ident['name'] ?? '') . ' · ' . ($ident['role'] ?: 'no role')); ?><?php else: ?>Public session<?php endif; ?></div>
      </div>
<?php } ?>
