<?php
if (!function_exists('atlas_lang')) {
function atlas_lang(): string {
    static $lang=null;
    if ($lang!==null) return $lang;
    $allowed=['it','en'];
    $q=strtolower((string)($_GET['lang']??''));
    if (in_array($q,$allowed,true)) {
        $lang=$q;
        if (!headers_sent()) setcookie('ATLASLANG',$lang,['expires'=>time()+31536000,'path'=>'/atlas_install','secure'=>true,'httponly'=>false,'samesite'=>'Lax']);
        return $lang;
    }
    $c=strtolower((string)($_COOKIE['ATLASLANG']??''));
    if (in_array($c,$allowed,true)) return $lang=$c;
    $accept=strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??''));
    return $lang=(preg_match('/(^|[,;\s])it(?:-|[,;\s]|$)/',$accept)?'it':'en');
}
function atlas_t(string $key, ?string $fallback=null): string {
    static $t=[
      'it'=>[
        'brand_subtitle'=>'ATLAS Installation System','tagline'=>'Deployment software, validazione e operazioni sui siti',
        'menu'=>'Menu','close_menu'=>'Chiudi menu','operations'=>'Operazioni','architectures'=>'Architetture','infosys'=>'InfoSys','releases'=>'Release','sites'=>'Siti','targets'=>'Target','tasks'=>'Task','help'=>'Aiuto',
        'home'=>'Home','user_registration'=>'Registrazione utente','request_install'=>'Richiedi installazione','pin_release'=>'Fissa una release','email_subscriptions'=>'Sottoscrizioni email','show_requests'=>'Mostra richieste','install_summary'=>'Riepilogo installazioni','tag_matrix'=>'Matrice tag','site_map'=>'Mappa siti','usage_charts'=>'Grafici utilizzo','server_config'=>'Configurazione server','local_users'=>'Utenti locali','change_password'=>'Cambia password','logout'=>'Esci','local_login'=>'Login locale',
        'define_arch'=>'Definisci architettura','update_arch'=>'Modifica architettura','delete_arch'=>'Rimuovi architettura','define_infosys'=>'Definisci InfoSys','update_infosys'=>'Modifica InfoSys','delete_infosys'=>'Rimuovi InfoSys','infosys_params'=>'Parametri InfoSys','define_release'=>'Definisci release','update_release'=>'Modifica release','release_matrix'=>'Matrice release','critical_releases'=>'Release critiche','release_params'=>'Parametri release','release_subscriptions'=>'Sottoscrizioni release','define_site'=>'Definisci sito','update_site'=>'Modifica sito','delete_site'=>'Rimuovi sito','site_params'=>'Parametri sito','define_target'=>'Definisci target','update_target'=>'Modifica target','delete_target'=>'Rimuovi target','define_task'=>'Definisci task','update_task'=>'Modifica task','delete_task'=>'Rimuovi task','documentation'=>'Documentazione','language'=>'Lingua',
        'public_session'=>'Sessione pubblica','no_authenticated_user'=>'nessuna utenza autenticata','certificate'=>'Certificato','role'=>'Ruolo','not_assigned'=>'non assegnato','local_user'=>'Utente locale','email'=>'Email','current_user_details'=>'Dettagli utente corrente','identity_unavailable'=>'Dettagli identità temporaneamente non disponibili.','authenticated_by'=>'Autenticazione',
        'authentication_required'=>'È necessario autenticarsi con un certificato autorizzato oppure con un’utenza locale.','certificate_required'=>'Questa funzione richiede un certificato client valido.','master_required'=>'Questa funzione richiede il ruolo master.','unauthorized'=>'Accesso non autorizzato','unauthorized_default'=>'Non disponi delle autorizzazioni necessarie per accedere a questa pagina o funzione.','contact_admin'=>'Contatta l’amministratore se ritieni che i privilegi debbano essere modificati.','unauth_howto'=>'Per accedere effettua il login con un’utenza locale abilitata oppure presenta un certificato client valido associato a un ruolo adeguato.','go_login'=>'Vai al login locale',
        'app_error'=>'Errore applicativo','app_error_msg'=>'Si è verificato un errore interno. Riprova o contatta l’amministratore.','local_login_unavailable'=>'Login locale non disponibile','local_login_unavailable_msg'=>'Il database di autenticazione locale non è inizializzato o non è raggiungibile. Contatta l’amministratore. Dettagli diagnostici sono disponibili nei log Kubernetes.',
        'records_per_page'=>'Record per pagina','all'=>'Tutti','previous'=>'Precedente','next'=>'Successiva','page'=>'Pagina','of'=>'di','records'=>'record','details'=>'Dettagli','hide_details'=>'Nascondi dettagli'
      ],
      'en'=>[
        'brand_subtitle'=>'ATLAS Installation System','tagline'=>'Software deployment, validation and site operations',
        'menu'=>'Menu','close_menu'=>'Close menu','operations'=>'Operations','architectures'=>'Architectures','infosys'=>'InfoSys','releases'=>'Releases','sites'=>'Sites','targets'=>'Targets','tasks'=>'Tasks','help'=>'Help',
        'home'=>'Home','user_registration'=>'User registration','request_install'=>'Request installation','pin_release'=>'Pin a release','email_subscriptions'=>'Email subscriptions','show_requests'=>'Show requests','install_summary'=>'Installation summary','tag_matrix'=>'Tag matrix','site_map'=>'Site map','usage_charts'=>'Usage charts','server_config'=>'Server configuration','local_users'=>'Local users','change_password'=>'Change password','logout'=>'Log out','local_login'=>'Local login',
        'define_arch'=>'Define architecture','update_arch'=>'Update architecture','delete_arch'=>'Remove architecture','define_infosys'=>'Define InfoSys','update_infosys'=>'Update InfoSys','delete_infosys'=>'Remove InfoSys','infosys_params'=>'InfoSys parameters','define_release'=>'Define release','update_release'=>'Update release','release_matrix'=>'Release matrix','critical_releases'=>'Critical releases','release_params'=>'Release parameters','release_subscriptions'=>'Release subscriptions','define_site'=>'Define site','update_site'=>'Update site','delete_site'=>'Remove site','site_params'=>'Site parameters','define_target'=>'Define target','update_target'=>'Update target','delete_target'=>'Remove target','define_task'=>'Define task','update_task'=>'Update task','delete_task'=>'Remove task','documentation'=>'Documentation','language'=>'Language',
        'public_session'=>'Public session','no_authenticated_user'=>'no authenticated user','certificate'=>'Certificate','role'=>'Role','not_assigned'=>'not assigned','local_user'=>'Local user','email'=>'Email','current_user_details'=>'Current user details','identity_unavailable'=>'Identity details are temporarily unavailable.','authenticated_by'=>'Authentication',
        'authentication_required'=>'You must authenticate with an authorized client certificate or a local account.','certificate_required'=>'This function requires a valid client certificate.','master_required'=>'This function requires the master role.','unauthorized'=>'Access denied','unauthorized_default'=>'You do not have the required authorization to access this page or function.','contact_admin'=>'Contact the administrator if you believe your privileges should be changed.','unauth_howto'=>'To continue, sign in with an enabled local account or present a valid client certificate associated with a suitable role.','go_login'=>'Go to local login',
        'app_error'=>'Application error','app_error_msg'=>'An internal error occurred. Try again or contact the administrator.','local_login_unavailable'=>'Local login unavailable','local_login_unavailable_msg'=>'The local authentication database is not initialized or cannot be reached. Contact the administrator. Diagnostic details are available in the Kubernetes logs.',
        'records_per_page'=>'Rows per page','all'=>'All','previous'=>'Previous','next'=>'Next','page'=>'Page','of'=>'of','records'=>'records','details'=>'Details','hide_details'=>'Hide details'
      ]
    ];
    $l=atlas_lang(); return $t[$l][$key]??$fallback??$key;
}
function atlas_lang_url(string $lang): string {
    $uri=(string)($_SERVER['REQUEST_URI']??'/atlas_install/');
    $p=parse_url($uri); $q=[]; parse_str((string)($p['query']??''),$q); $q['lang']=$lang;
    return ($p['path']??'/atlas_install/').'?'.http_build_query($q);
}
function atlas_language_selector_html(): string {
    $l=atlas_lang();
    $uri=(string)($_SERVER['REQUEST_URI']??'/atlas_install/');
    $p=parse_url($uri); $q=[]; parse_str((string)($p['query']??''),$q); unset($q['lang']);
    $ret=($p['path']??'/atlas_install/').($q?'?'.http_build_query($q):'');
    $mk=static fn(string $lang): string => '/atlas_install/lang.php?lang='.rawurlencode($lang).'&return='.rawurlencode($ret);
    return '<div class="atlas-language"><span>'.htmlspecialchars(atlas_t('language'),ENT_QUOTES,'UTF-8').'</span><a href="'.htmlspecialchars($mk('it'),ENT_QUOTES,'UTF-8').'" class="'.($l==='it'?'active':'').'">IT</a><a href="'.htmlspecialchars($mk('en'),ENT_QUOTES,'UTF-8').'" class="'.($l==='en'?'active':'').'">EN</a></div>';
}
}
