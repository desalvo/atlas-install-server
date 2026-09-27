<?php
require __DIR__ . '/../config.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo "DBNAME=".$LJSFi_dbname."\n";
echo "DBREADER=".$LJSFi_dbuser['ro']."\n";
echo "DBWRITER=".$LJSFi_dbuser['rw']."\n";
?>
