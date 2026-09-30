<?php
require_once __DIR__.'/../var/www/html/atlas_install-3.0.0/local_auth.php';

function check(bool $ok, string $message): void {
    if(!$ok){ fwrite(STDERR,"FAIL: $message\n"); exit(1); }
}

$slash='/DC=org/DC=terena/DC=tcs/C=IT/O=Istituto Nazionale di Fisica Nucleare/CN=Alessandro De Salvo desalvo@infn.it';
$rfc='CN=Alessandro De Salvo desalvo@infn.it,O=Istituto Nazionale di Fisica Nucleare,C=IT,DC=tcs,DC=terena,DC=org';
check(atlas_canonicalize_dn($slash,true)!=='','slash DN must canonicalize');
check(hash_equals(atlas_canonicalize_dn($slash,true),atlas_canonicalize_dn($rfc,true)),'OpenSSL slash and RFC2253 forms must be equivalent');

$escapedSlash='/C=IT/O=Example Corp/CN=Doe\\/John';
$escapedRfc='CN=Doe/John,O=Example Corp,C=IT';
check(hash_equals(atlas_canonicalize_dn($escapedSlash),atlas_canonicalize_dn($escapedRfc)),'escaped slash value must compare by decoded value');

$commaA='/C=IT/O=Example/CN=Doe, John';
$commaB='CN=Doe\\, John,O=Example,C=IT';
check(hash_equals(atlas_canonicalize_dn($commaA),atlas_canonicalize_dn($commaB)),'escaped RFC comma must preserve the value');

$proxy=$slash.'/CN=123456/CN=proxy';
check(hash_equals(atlas_canonicalize_dn($slash,true),atlas_canonicalize_dn($proxy,true)),'Grid proxy CN suffixes must be ignored');
$proxyRfc='CN=proxy,CN=123456,'.$rfc;
check(hash_equals(atlas_canonicalize_dn($rfc,true),atlas_canonicalize_dn($proxyRfc,true)),'RFC proxy CN prefixes must be ignored');

$issuerSlash='/C=NL/O=GEANT Vereniging/CN=GEANT TCS Authentication RSA CA 5';
$issuerRfc='CN=GEANT TCS Authentication RSA CA 5,O=GEANT Vereniging,C=NL';
$issuer=['ca_dn'=>$issuerRfc,'ca_dn_canonical'=>atlas_canonicalize_dn($issuerRfc),'ca_name'=>'GEANT TCS Authentication RSA CA 5'];
check(atlas_ca_binding_status($issuerSlash,'',$issuer)==='match','equivalent CA DNs must match');
check(atlas_ca_binding_status('/C=NL/O=Other/CN=Other CA','',$issuer)==='mismatch','different CA DN must mismatch');
check(atlas_ca_binding_status('','',$issuer)==='legacy_unbound','record without CA metadata must stay legacy_unbound');
check(atlas_ca_binding_status('','GEANT TCS Authentication RSA CA 5',$issuer)==='match','legacy CA-name binding must still match');

check(atlas_normalize_subject_dn($proxy)===$slash,'legacy slash DN export must remove proxy suffixes without rewriting the base DN');
check(atlas_normalize_subject_dn($proxyRfc)===$rfc,'RFC DN export must remove proxy prefixes without rewriting the base DN');

echo "DN canonicalization tests: PASS\n";
