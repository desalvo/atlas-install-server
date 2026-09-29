<?php
require_once __DIR__.'/../config.php';
$it=atlas_lang()==='it';
try {
    atlas_local_auth_schema();
} catch (Throwable $e) {
    atlas_auth_log('local_login_unavailable', ['error'=>$e->getMessage()]);
    atlas_render_message_page(atlas_t('local_login_unavailable'), atlas_t('local_login_unavailable_msg'), 'error');
}
if (atlas_is_authenticated()) { header('Location: /atlas_install/'); exit; }
$error=''; $stage='password'; $pending=null;
if(session_status()!==PHP_SESSION_ACTIVE){session_name('ATLASLOGIN');session_start(['cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException($it?'Richiesta non valida.':'Invalid request.');
  $action=(string)($_POST['action']??'password');
  if($action==='password'){
    $u=atlas_local_user_by_username(trim((string)($_POST['username']??'')));
    if(!$u||!(int)$u['enabled']||!password_verify((string)($_POST['password']??''),(string)$u['password_hash'])){atlas_auth_log('local_login_failed',['username'=>trim((string)($_POST['username']??'')),'reason'=>'invalid_credentials']); usleep(250000); throw new RuntimeException($it?'Credenziali non valide.':'Invalid credentials.');}
    $_SESSION['pending_uid']=(int)$u['id']; $_SESSION['pending_user']=(string)$u['username'];
    $totps=atlas_user_totps((int)$u['id'],true);
    if(!$totps){$secret=atlas_totp_generate_secret();$_SESSION['enroll_secret']=$secret;$stage='enroll';} else $stage='totp';
  } elseif($action==='totp'){
    $uid=(int)($_SESSION['pending_uid']??0); if(!$uid||!atlas_verify_user_totp($uid,(string)($_POST['code']??''))){atlas_auth_log('local_login_failed',['user_id'=>$uid,'reason'=>'invalid_totp']);throw new RuntimeException($it?'Codice TOTP non valido.':'Invalid TOTP code.');}
    atlas_create_local_session($uid); unset($_SESSION['pending_uid'],$_SESSION['pending_user'],$_SESSION['enroll_secret']); header('Location: /atlas_install/'); exit;
  } elseif($action==='enroll'){
    $uid=(int)($_SESSION['pending_uid']??0);$secret=(string)($_SESSION['enroll_secret']??''); if(!$uid||$secret===''||!atlas_totp_verify($secret,(string)($_POST['code']??'')))throw new RuntimeException($it?'Codice TOTP non valido.':'Invalid TOTP code.');
    $enc=atlas_secret_encrypt($secret);$label='Primary authenticator';$db=atlas_local_db();$st=$db->prepare('INSERT INTO atlas_local_totp(user_id,label,secret_enc,enabled) VALUES(?,?,?,1)');$st->bind_param('iss',$uid,$label,$enc);$st->execute();atlas_auth_log('totp_enrolled',['user_id'=>$uid]);$userRow=$db->query('SELECT must_change_password FROM atlas_local_user WHERE id='.(int)$uid)->fetch_assoc(); atlas_create_local_session($uid);unset($_SESSION['pending_uid'],$_SESSION['pending_user'],$_SESSION['enroll_secret']);header('Location: '.((int)($userRow['must_change_password']??1)===1?'/atlas_install/auth/change_password.php':'/atlas_install/'));exit;
  }
 }catch(Throwable $e){atlas_auth_log('local_login_error',['stage'=>$stage,'error'=>$e->getMessage()]); $error=$e->getMessage(); if(isset($_SESSION['pending_uid'])){$stage=isset($_SESSION['enroll_secret'])?'enroll':'totp';}}
}
$secret=(string)($_SESSION['enroll_secret']??''); $user=(string)($_SESSION['pending_user']??''); $issuer=rawurlencode((string)atlas_env('ATLAS_VO','ATLAS').' Installation System'); $uri=$secret!==''?'otpauth://totp/'.$issuer.':'.rawurlencode($user).'?secret='.rawurlencode($secret).'&issuer='.$issuer.'&digits=6&period=30':'';
?><!doctype html><html lang="<?=atlas_h(atlas_lang())?>"><head><title><?=atlas_h(atlas_t('local_login'))?></title><?php require __DIR__.'/../css/page_header.php'; page_header('..'); ?></head><body><div id="main"><div id="header"><?php require __DIR__.'/../css/main_header.php'; main_header($LJSFi_VO,'..'); require __DIR__.'/../css/menubar.php'; menubar('..'); ?></div><div id="site_content"><div id="content" style="width:100%;float:none"><h1><?=atlas_h(atlas_t('local_login'))?></h1><?php if($error):?><div class="atlas-alert error"><?=atlas_h($error)?></div><?php endif;?>
<?php if($stage==='password'):?><form method="post" class="atlas-form atlas-auth-form"><input type="hidden" name="csrf" value="<?=atlas_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="password"><div class="atlas-form-row"><label><?php echo $it?'Nome utente':'Username'; ?></label><input name="username" autocomplete="username" required></div><div class="atlas-form-row"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div><div class="atlas-actions"><button><?php echo $it?'Continua':'Continue'; ?></button></div></form>
<?php elseif($stage==='totp'):?><form method="post" class="atlas-form atlas-auth-form"><input type="hidden" name="csrf" value="<?=atlas_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="totp"><p><?php echo $it?'Inserisci il codice TOTP per':'Enter the TOTP code for'; ?> <strong><?=atlas_h($user)?></strong>.</p><div class="atlas-form-row"><label><?php echo $it?'Codice TOTP':'TOTP code'; ?></label><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div><div class="atlas-actions"><button><?php echo $it?'Accedi':'Sign in'; ?></button></div></form>
<?php else:?><div class="atlas-alert warning"><?php echo $it?'Nessun TOTP è configurato. Registra ora il primo autenticatore; la sessione verrà creata solo dopo la verifica del codice.':'No TOTP is configured. Enroll the first authenticator now; the session will be created only after code verification.'; ?></div><p><strong>Secret:</strong> <span class="atlas-code"><?=atlas_h($secret)?></span></p><p class="atlas-code atlas-break"><?=atlas_h($uri)?></p><form method="post" class="atlas-form atlas-auth-form"><input type="hidden" name="csrf" value="<?=atlas_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="enroll"><div class="atlas-form-row"><label><?php echo $it?'Primo codice TOTP':'First TOTP code'; ?></label><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div><div class="atlas-actions"><button><?php echo $it?'Attiva e accedi':'Activate and sign in'; ?></button></div></form><?php endif;?></div></div><div id="footer"><p>ATLAS Installation System</p></div></div></body></html>