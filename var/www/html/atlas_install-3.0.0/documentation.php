<?php
require_once __DIR__.'/config.php';
$it = atlas_lang()==='it';
$lang = $it ? 'it' : 'en';
$fragment = __DIR__.'/docs/LJSF3-Manual.'.$lang.'.fragment.html';
?>
<!doctype html>
<html lang="<?php echo atlas_h($lang); ?>">
<head>
<title><?php echo $it?'Documentazione LJSF 3':'LJSF 3 Documentation'; ?></title>
<?php require __DIR__.'/css/page_header.php'; page_header('.'); ?>
<style>
.atlas-doc-toolbar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin:1rem 0 1.5rem;padding:1rem;border:1px solid #d8e3ef;border-radius:12px;background:#f5f9fd}.atlas-doc-toolbar a{display:inline-block;padding:.65rem .85rem;border-radius:9px;background:#0f66d8;color:#fff;text-decoration:none;font-weight:700}.atlas-doc-toolbar a.secondary{background:#18324f}.atlas-docs{max-width:none;width:100%}.atlas-docs img{max-width:100%;height:auto;border-radius:10px}.atlas-docs table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.92rem;table-layout:auto}.atlas-docs th,.atlas-docs td{border:1px solid #d8e3ef;padding:.55rem .65rem;vertical-align:top;overflow-wrap:anywhere}.atlas-docs th{background:#0e4f91;color:#fff;text-align:left}.atlas-docs tr:nth-child(even) td{background:#f7f9fc}.atlas-docs pre{overflow:auto;padding:1rem;border-radius:10px;background:#081a2b;color:#e6f2ff;white-space:pre-wrap}.atlas-docs code{overflow-wrap:anywhere}.atlas-docs nav#TOC{background:#f5f9fd;border:1px solid #d8e3ef;border-radius:12px;padding:1rem 1.25rem}.atlas-docs h1,.atlas-docs h2,.atlas-docs h3{scroll-margin-top:1rem}@media(max-width:760px){.atlas-docs table{display:block;overflow-x:auto;font-size:.82rem}.atlas-doc-toolbar a{width:100%;text-align:center}.atlas-docs pre{font-size:.78rem}}
</style>
</head>
<body>
<div id="main">
  <div id="header"><?php require __DIR__.'/css/main_header.php'; main_header($LJSFi_VO,'.'); require __DIR__.'/css/menubar.php'; menubar('.'); ?></div>
  <div id="site_content">
    <main id="content" style="width:100%;float:none" class="atlas-docs">
      <div class="atlas-doc-toolbar">
        <strong><?php echo $it?'Manuale completo':'Complete manual'; ?>:</strong>
        <a href="docs/LJSF3-Manual.<?php echo $lang; ?>.pdf">PDF</a>
        <a class="secondary" href="docs/LJSF3-Manual.<?php echo $lang; ?>.html">HTML</a>
        <a href="docs/LJSF3-REST-API.<?php echo $lang; ?>.pdf">REST API PDF</a>
        <a class="secondary" href="docs/LJSF3-REST-API.<?php echo $lang; ?>.html">REST API HTML</a>
        <a class="secondary" href="/atlas_install/api/v1/openapi">OpenAPI 3.1</a>
      </div>
      <?php
        if (is_readable($fragment)) {
            readfile($fragment);
        } else {
            echo '<div class="atlas-alert error">'.atlas_h($it?'Documentazione non disponibile.':'Documentation unavailable.').'</div>';
        }
      ?>
    </main>
  </div>
  <?php echo atlas_identity_details_html(); ?>
  <div id="footer"><p>LJSF 3 · ATLAS Installation System · 3.0.0-r30</p></div>
</div>
</body>
</html>
