<?php
require_once __DIR__ . '/legacy_compat.php';
require_once __DIR__ . '/security.php';

# The LJSFi server version
$LJSFi_server_version = "3.0.0";

# Database settings. Secrets MUST come from the service environment.
$LJSFi_dbname = atlas_env('ATLAS_DB_NAME', 'atlas_install_panda');
$LJSFi_dbserv = array(
    'rw' => atlas_env('ATLAS_DB_RW_HOST', '127.0.0.1'),
    'ro' => atlas_env('ATLAS_DB_RO_HOST', atlas_env('ATLAS_DB_RW_HOST', '127.0.0.1')),
    'broker' => atlas_env('ATLAS_DB_BROKER_HOST', atlas_env('ATLAS_DB_RW_HOST', '127.0.0.1')),
);
$LJSFi_dbuser = array(
    'rw' => atlas_env('ATLAS_DB_RW_USER', ''),
    'ro' => atlas_env('ATLAS_DB_RO_USER', ''),
    'broker' => atlas_env('ATLAS_DB_BROKER_USER', atlas_env('ATLAS_DB_RW_USER', '')),
);
$LJSFi_dbpass = array(
    'rw' => atlas_env('ATLAS_DB_RW_PASSWORD', ''),
    'ro' => atlas_env('ATLAS_DB_RO_PASSWORD', ''),
    'broker' => atlas_env('ATLAS_DB_BROKER_PASSWORD', atlas_env('ATLAS_DB_RW_PASSWORD', '')),
);

$upload_path = rtrim(atlas_env('ATLAS_UPLOAD_PATH', '/var/lib/atlas-install/log'), '/') . '/';
$archive_path = rtrim(atlas_env('ATLAS_ARCHIVE_PATH', '/var/lib/atlas-install/logbackup'), '/') . '/';
$cache_path = rtrim(atlas_env('ATLAS_CACHE_PATH', '/var/cache/atlas-install'), '/') . '/';

$debug = filter_var(atlas_env('ATLAS_DEBUG', '0'), FILTER_VALIDATE_BOOL) ? 1 : 0;
$logprefix = '[LJSFi]';
$LJSFi_VO = atlas_env('ATLAS_VO', 'ATLAS');
$LJSFi_email = atlas_env('ATLAS_EMAIL', 'no-reply@localhost');
$LJSFi_contacts = atlas_env('ATLAS_CONTACTS', '');
$LJSFi_swinst_proto = 'atlassw';
$LJSFi_default_infosys = atlas_env('ATLAS_DEFAULT_INFOSYS', 'lcg-bdii.cern.ch');
$LJSFi_KML_CACHE = atlas_env('ATLAS_KML_CACHE', '/var/cache/atlas-install/install.kml');
$LJSFi_ACTIVITY_PERIOD = atlas_env('ATLAS_ACTIVITY_PERIOD', '3 DAY');
$LJSFi_PATH = atlas_env('ATLAS_APP_PATH', '/var/www/html/atlas_install');
$LJSFi_access_log = filter_var(atlas_env('ATLAS_ACCESS_LOG', '1'), FILTER_VALIDATE_BOOL);
?>
