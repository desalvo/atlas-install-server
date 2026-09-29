<?php
require_once __DIR__.'/i18n.php';
$lang=strtolower((string)($_GET['lang']??''));
if(!in_array($lang,['it','en'],true)) $lang='en';
setcookie('ATLASLANG',$lang,['expires'=>time()+31536000,'path'=>'/atlas_install','secure'=>true,'httponly'=>false,'samesite'=>'Lax']);
$ret=(string)($_GET['return']??'/atlas_install/');
if(!str_starts_with($ret,'/atlas_install') || str_contains($ret,"\r") || str_contains($ret,"\n")) $ret='/atlas_install/';
header('Location: '.$ret, true, 303);
exit;
?>
