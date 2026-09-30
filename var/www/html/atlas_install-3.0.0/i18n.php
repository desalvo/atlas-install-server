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
        'public_session'=>'Sessione pubblica','no_authenticated_user'=>'nessuna utenza autenticata','certificate'=>'Certificato','role'=>'Ruolo','not_assigned'=>'non assegnato','local_user'=>'Utente locale','email'=>'Email','current_user_details'=>'Dettagli utente corrente','identity_unavailable'=>'Dettagli identità temporaneamente non disponibili.','authenticated_by'=>'Autenticazione','certificate_authority'=>'Autorità di certificazione (CA)','ca_dn'=>'DN della CA','known_user'=>'Utente conosciuto dall’applicazione','identity_binding_status'=>'Stato identità X.509','identity_dn_unknown'=>'DN sconosciuto','identity_dn_ca_match'=>'DN conosciuto · CA corrispondente','identity_dn_ca_mismatch'=>'DN conosciuto · CA differente','identity_dn_legacy_ca'=>'DN conosciuto · record legacy senza CA','yes'=>'sì','no'=>'no','not_available'=>'non disponibile','ca_mismatch_title'=>'CA diversa da quella registrata.','ca_mismatch_explanation'=>'Il DN personale è conosciuto, ma il certificato è stato emesso da una CA diversa da quella associata all’utenza. Per sicurezza il ruolo non viene assegnato.','registered_ca'=>'CA registrata','ca_legacy_unbound'=>'Questa utenza proviene da un record legacy che non contiene ancora la CA. Il ruolo continua a essere riconosciuto per compatibilità; un aggiornamento esplicito dell’utenza può associare la CA corrente.','why_no_role'=>'Motivo del ruolo non assegnato','role_reason_disabled'=>'l’utenza registrata è disabilitata.','role_reason_expired'=>'la validità dell’utenza registrata è scaduta.','role_reason_ca_mismatch'=>'la CA del certificato presentato non coincide con la CA registrata per questo DN.','role_reason_pending'=>'la registrazione o il ruolo sono ancora in attesa di approvazione.','role_reason_none'=>'all’utenza conosciuta non è associato un ruolo attivo.',
        'authentication_required'=>'È necessario autenticarsi con un certificato autorizzato oppure con un’utenza locale.','certificate_required'=>'Questa funzione richiede un certificato client valido.','master_required'=>'Questa funzione richiede il ruolo master.','unauthorized'=>'Accesso non autorizzato','unauthorized_default'=>'Non disponi delle autorizzazioni necessarie per accedere a questa pagina o funzione.','contact_admin'=>'Contatta l’amministratore se ritieni che i privilegi debbano essere modificati.','unauth_howto'=>'Per accedere effettua il login con un’utenza locale abilitata oppure presenta un certificato client valido associato a un ruolo adeguato.','go_login'=>'Vai al login locale',
        'app_error'=>'Errore applicativo','app_error_msg'=>'Si è verificato un errore interno. Riprova o contatta l’amministratore.','local_login_unavailable'=>'Login locale non disponibile','local_login_unavailable_msg'=>'Il database di autenticazione locale non è inizializzato o non è raggiungibile. Contatta l’amministratore. Dettagli diagnostici sono disponibili nei log Kubernetes.',
        'records_per_page'=>'Record per pagina','select'=>'Seleziona','save'=>'Salva','delete'=>'Elimina','update'=>'Aggiorna','reset'=>'Reimposta','search'=>'Cerca','status'=>'Stato','description'=>'Descrizione','name'=>'Nome','date'=>'Data','comments'=>'Commenti','request_id'=>'ID richiesta','type'=>'Tipo','resource_name'=>'Nome risorsa','request_time'=>'Data richiesta','update_time'=>'Data aggiornamento','requested_by'=>'Richiesto da','requester_comments'=>'Commenti richiedente','operator_comments'=>'Commenti operatore','assigned_to'=>'Assegnato a','all'=>'Tutti','previous'=>'Precedente','next'=>'Successiva','page'=>'Pagina','of'=>'di','records'=>'record','details'=>'Dettagli','hide_details'=>'Nascondi dettagli'
      ],
      'en'=>[
        'brand_subtitle'=>'ATLAS Installation System','tagline'=>'Software deployment, validation and site operations',
        'menu'=>'Menu','close_menu'=>'Close menu','operations'=>'Operations','architectures'=>'Architectures','infosys'=>'InfoSys','releases'=>'Releases','sites'=>'Sites','targets'=>'Targets','tasks'=>'Tasks','help'=>'Help',
        'home'=>'Home','user_registration'=>'User registration','request_install'=>'Request installation','pin_release'=>'Pin a release','email_subscriptions'=>'Email subscriptions','show_requests'=>'Show requests','install_summary'=>'Installation summary','tag_matrix'=>'Tag matrix','site_map'=>'Site map','usage_charts'=>'Usage charts','server_config'=>'Server configuration','local_users'=>'Local users','change_password'=>'Change password','logout'=>'Log out','local_login'=>'Local login',
        'define_arch'=>'Define architecture','update_arch'=>'Update architecture','delete_arch'=>'Remove architecture','define_infosys'=>'Define InfoSys','update_infosys'=>'Update InfoSys','delete_infosys'=>'Remove InfoSys','infosys_params'=>'InfoSys parameters','define_release'=>'Define release','update_release'=>'Update release','release_matrix'=>'Release matrix','critical_releases'=>'Critical releases','release_params'=>'Release parameters','release_subscriptions'=>'Release subscriptions','define_site'=>'Define site','update_site'=>'Update site','delete_site'=>'Remove site','site_params'=>'Site parameters','define_target'=>'Define target','update_target'=>'Update target','delete_target'=>'Remove target','define_task'=>'Define task','update_task'=>'Update task','delete_task'=>'Remove task','documentation'=>'Documentation','language'=>'Language',
        'public_session'=>'Public session','no_authenticated_user'=>'no authenticated user','certificate'=>'Certificate','role'=>'Role','not_assigned'=>'not assigned','local_user'=>'Local user','email'=>'Email','current_user_details'=>'Current user details','identity_unavailable'=>'Identity details are temporarily unavailable.','authenticated_by'=>'Authentication','certificate_authority'=>'Certificate authority (CA)','ca_dn'=>'CA DN','known_user'=>'User known to the application','identity_binding_status'=>'X.509 identity status','identity_dn_unknown'=>'unknown DN','identity_dn_ca_match'=>'known DN · matching CA','identity_dn_ca_mismatch'=>'known DN · different CA','identity_dn_legacy_ca'=>'known DN · legacy record without CA','yes'=>'yes','no'=>'no','not_available'=>'not available','ca_mismatch_title'=>'Different CA from the registered one.','ca_mismatch_explanation'=>'The personal DN is known, but the presented certificate was issued by a different CA from the one bound to the account. For security, no role is assigned.','registered_ca'=>'Registered CA','ca_legacy_unbound'=>'This account comes from a legacy record that does not yet contain CA information. The role remains recognized for compatibility; explicitly updating the account can bind the current CA.','why_no_role'=>'Why no role is assigned','role_reason_disabled'=>'the registered account is disabled.','role_reason_expired'=>'the registered account validity has expired.','role_reason_ca_mismatch'=>'the CA of the presented certificate does not match the CA registered for this DN.','role_reason_pending'=>'the registration or role is still awaiting approval.','role_reason_none'=>'no active role is associated with the known account.',
        'authentication_required'=>'You must authenticate with an authorized client certificate or a local account.','certificate_required'=>'This function requires a valid client certificate.','master_required'=>'This function requires the master role.','unauthorized'=>'Access denied','unauthorized_default'=>'You do not have the required authorization to access this page or function.','contact_admin'=>'Contact the administrator if you believe your privileges should be changed.','unauth_howto'=>'To continue, sign in with an enabled local account or present a valid client certificate associated with a suitable role.','go_login'=>'Go to local login',
        'app_error'=>'Application error','app_error_msg'=>'An internal error occurred. Try again or contact the administrator.','local_login_unavailable'=>'Local login unavailable','local_login_unavailable_msg'=>'The local authentication database is not initialized or cannot be reached. Contact the administrator. Diagnostic details are available in the Kubernetes logs.',
        'records_per_page'=>'Rows per page','select'=>'Select','save'=>'Save','delete'=>'Delete','update'=>'Update','reset'=>'Reset','search'=>'Search','status'=>'Status','description'=>'Description','name'=>'Name','date'=>'Date','comments'=>'Comments','request_id'=>'Request ID','type'=>'Type','resource_name'=>'Resource name','request_time'=>'Request time','update_time'=>'Update time','requested_by'=>'Requested by','requester_comments'=>'Requester comments','operator_comments'=>'Operator comments','assigned_to'=>'Assigned to','all'=>'All','previous'=>'Previous','next'=>'Next','page'=>'Page','of'=>'of','records'=>'records','details'=>'Details','hide_details'=>'Hide details'
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
function atlas_legacy_translation_map(string $lang): array {
    $it = [
      'Contact the installation team'=>'Contatta il team di installazione',
      'Sessions'=>'Sessioni','New user'=>'Nuovo utente','Local session duration (hours)'=>'Durata sessione locale (ore)',
      'initial password (>=12)'=>'password iniziale (>=12)','enabled'=>'abilitato','active'=>'attivo','disabled'=>'disabilitato',
      'Save data'=>'Salva dati','Create'=>'Crea','Password'=>'Password','Username'=>'Nome utente','First name'=>'Nome','Last name'=>'Cognome',
      'Current user details'=>'Dettagli utente corrente','Search, filters and sorting'=>'Ricerca, filtri e ordinamento',
      'Sort by'=>'Ordina per','Direction'=>'Direzione','Apply'=>'Applica','All'=>'Tutti',
      'Set Installed'=>'Imposta installato','Set Failed'=>'Imposta fallito','Set Removed'=>'Imposta rimosso','Set Aborted'=>'Imposta annullato','Select all'=>'Seleziona tutto',
      'Installation status search'=>'Ricerca stato installazioni','Grid name'=>'Nome grid','Site name'=>'Nome sito','Site arch'=>'Architettura sito','Filesystem Type'=>'Tipo filesystem','OS type'=>'Tipo OS',
      'Software Installation Request list'=>'Elenco richieste di installazione software',
      'Please select a status to update the record'=>'Seleziona uno stato per aggiornare il record',
      'Please select the architecture to update'=>'Seleziona l’architettura da modificare',
      'Please select the architecture to remove'=>'Seleziona l’architettura da rimuovere',
      'Please select the definition source'=>'Seleziona la definizione sorgente',
      'New architecture definition'=>'Nuova definizione architettura',
      'Architecture update'=>'Modifica architettura',
      'Architecture removal'=>'Rimozione architettura',
      'Architecture removed successfully'=>'Architettura rimossa correttamente',
      'Architecture definition failed'=>'Definizione architettura non riuscita',
      'No architecture description specified'=>'Descrizione architettura non specificata',
      'You don\'t have enough privileges to manage the architectures'=>'Non disponi dei privilegi necessari per gestire le architetture',
      'Please select the task to update'=>'Seleziona il task da modificare',
      'Please select the task to remove'=>'Seleziona il task da rimuovere',
      'New task definition'=>'Nuova definizione task',
      'Task update'=>'Modifica task',
      'Task removal'=>'Rimozione task',
      'Task removed successfully'=>'Task rimosso correttamente',
      'Task definition failed'=>'Definizione task non riuscita',
      'Please select the target to update'=>'Seleziona il target da modificare',
      'Please select the target to remove'=>'Seleziona il target da rimuovere',
      'New target definition'=>'Nuova definizione target',
      'Target update'=>'Modifica target',
      'Target removal'=>'Rimozione target',
      'Please select the site to update'=>'Seleziona il sito da modificare',
      'Please select the site to remove'=>'Seleziona il sito da rimuovere',
      'New site definition'=>'Nuova definizione sito',
      'Site update'=>'Modifica sito',
      'Site removal'=>'Rimozione sito',
      'Please select the release to update'=>'Seleziona la release da modificare',
      'Please select the release to remove'=>'Seleziona la release da rimuovere',
      'New release definition'=>'Nuova definizione release',
      'Release update'=>'Modifica release',
      'Release removal'=>'Rimozione release',
      'Request ID'=>'ID richiesta','Type'=>'Tipo','Resource name'=>'Nome risorsa','Status'=>'Stato',
      'Request Time'=>'Data richiesta','Update Time'=>'Data aggiornamento','Requested by'=>'Richiesto da',
      'Requester comments'=>'Commenti richiedente','Operator comments'=>'Commenti operatore','Assigned to'=>'Assegnato a',
      'Select'=>'Seleziona','Save'=>'Salva','Delete'=>'Elimina','Update'=>'Aggiorna','Reset'=>'Reimposta',
      'Description'=>'Descrizione','Name'=>'Nome','Comments'=>'Commenti','Date'=>'Data',
      'Page number'=>'Pagina','No valid credentials found. Please use https and a valid certificate'=>'Credenziali non valide. Usa HTTPS e un certificato valido',
      'No info for this job'=>'Nessuna informazione disponibile per questo job',
      'Request an installation'=>'Richiedi un’installazione','Pin an installed release'=>'Fissa una release installata',
      'Subscribe to email notifications'=>'Sottoscrivi notifiche email','Show the installation requests'=>'Mostra richieste di installazione',
      'Show the tags matrix'=>'Mostra matrice tag','Define a new architecture'=>'Definisci una nuova architettura',
      'Update an architecture definition'=>'Modifica una definizione architettura','Remove an architecture definition'=>'Rimuovi una definizione architettura',
      'Define a new InfoSys'=>'Definisci un nuovo InfoSys','Update an InfoSys definition'=>'Modifica una definizione InfoSys','Remove an InfoSys'=>'Rimuovi un InfoSys',
      'InfoSys parameters management'=>'Gestione parametri InfoSys','Define a new release'=>'Definisci una nuova release','Update a release definition'=>'Modifica una definizione release',
      'Show the release matrix'=>'Mostra matrice release','Release parameters management'=>'Gestione parametri release','Define a new site'=>'Definisci un nuovo sito',
      'Update a site definition'=>'Modifica una definizione sito','Remove a site'=>'Rimuovi un sito','Site parameters management'=>'Gestione parametri sito',
      'Define a new target'=>'Definisci un nuovo target','Update a target definition'=>'Modifica una definizione target','Remove a target definition'=>'Rimuovi una definizione target',
      'Define a new task'=>'Definisci un nuovo task','Update a task definition'=>'Modifica una definizione task','Remove a task definition'=>'Rimuovi una definizione task'
    ];
    if ($lang==='it') return $it;
    // The legacy UI source language is English. Explicitly reverse the Italian strings
    // emitted by first-party pages so a forced English session never leaks Italian UI.
    return array_flip($it);
}
function atlas_translate_legacy_html(string $html): string {
    if ($html==='' || stripos($html,'<html')===false) return $html;
    $map=atlas_legacy_translation_map(atlas_lang());
    // Translate visible text nodes only. Never rewrite attributes/form values,
    // because many legacy POST handlers intentionally compare submit values
    // such as Save/Select/Delete. Script/style blocks are also left untouched.
    $blocks=preg_split('~(<(?:script|style)\b[^>]*>.*?</(?:script|style)>)~is',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
    if($blocks===false) return $html;
    foreach($blocks as $bi=>$block){
        if(preg_match('~^<(?:script|style)\b~i',$block)) continue;
        $parts=preg_split('~(<[^>]+>)~s',$block,-1,PREG_SPLIT_DELIM_CAPTURE);
        if($parts===false) continue;
        foreach($parts as $pi=>$part){ if($part!=='' && $part[0] !== '<') $parts[$pi]=strtr($part,$map); }
        $blocks[$bi]=implode('',$parts);
    }
    return implode('',$blocks);
}

}
