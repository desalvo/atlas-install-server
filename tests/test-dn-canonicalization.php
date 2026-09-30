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



$now=strtotime('2026-09-30 12:00:00');
$baseRow=['name'=>'Test','email'=>'','enabled'=>1,'valid_start'=>null,'valid_end'=>null,'ca_dn'=>'','ca_name'=>'','role'=>'user'];
$pending=array_merge($baseRow,['ref'=>20,'dn'=>$rfc,'rolefk'=>-1,'_atlas_exact_dn'=>1]);
$approved=array_merge($baseRow,['ref'=>10,'dn'=>$slash,'rolefk'=>1,'_atlas_exact_dn'=>0]);
$selected=atlas_select_cert_candidate([$pending,$approved],$issuer,$now);
check((int)($selected['ref']??0)===10,'among CA-unbound duplicate DNs an enabled/current approved role must win over a newer pending row');
check((string)($selected['_atlas_selected_meta']['ca_status']??'')==='legacy_unbound','selected legacy duplicate must preserve legacy_unbound CA status');

$boundMismatch=array_merge($approved,['ref'=>30,'ca_dn'=>'/C=NL/O=Other/CN=Other CA']);
$selected=atlas_select_cert_candidate([$approved,$boundMismatch],$issuer,$now);
check((int)($selected['ref']??0)===30,'a CA-unbound duplicate must not bypass an existing CA-bound record');
check((string)($selected['_atlas_selected_meta']['ca_status']??'')==='mismatch','mismatching bound record must remain a mismatch instead of falling back to an unbound duplicate');

$boundMatch=array_merge($approved,['ref'=>40,'ca_dn'=>$issuerSlash]);
$selected=atlas_select_cert_candidate([$boundMismatch,$boundMatch],$issuer,$now);
check((int)($selected['ref']??0)===40,'matching CA-bound duplicate must win over a mismatching bound record');

$zeroDates=array_merge($approved,['ref'=>50,'valid_start'=>'0000-00-00 00:00:00','valid_end'=>'0000-00-00 00:00:00']);
$meta=atlas_cert_candidate_meta($zeroDates,$issuer,$now);
check(!empty($meta['valid']),'legacy zero dates must be treated as unbounded validity, like NULL historical values');

echo "DN canonicalization tests: PASS\n";
