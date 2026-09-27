<?php
function main_header($voname, $path=".") {
  $certOk = (string)($_SERVER['SSL_CLIENT_VERIFY'] ?? getenv('SSL_CLIENT_VERIFY') ?: '') === 'SUCCESS';
?>
      <div id="logo" class="atlas-hero">
        <div class="atlas-brandmark" aria-hidden="true">A</div>
        <div id="logo_text">
          <h1><a><?php echo htmlspecialchars((string)$voname, ENT_QUOTES, 'UTF-8'); ?> <span class="logo_colour">Installation System</span></a></h1>
          <h2>Software deployment, validation and site operations</h2>
        </div>
        <div class="atlas-auth-state <?php echo $certOk ? 'is-authenticated' : 'is-public'; ?>">
          <span class="atlas-auth-dot"></span>
          <?php echo $certOk ? 'Client certificate verified' : 'Public session'; ?>
        </div>
      </div>
<?php } ?>
