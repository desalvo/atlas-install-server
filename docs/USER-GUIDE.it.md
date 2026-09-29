# LJSF 3 — Guida utente

LJSF 3 (ATLAS Installation System) gestisce release software, siti, architetture, InfoSys, target, task e richieste di installazione.

## Accesso
Le aree di consultazione pubbliche non richiedono autenticazione. Le funzioni protette richiedono un certificato client valido associato a un ruolo oppure un account locale. L'account locale iniziale è `admin`, ruolo `master`; la password iniziale è definita dal bootstrap e può richiedere cambio al primo accesso. TOTP è supportato per gli account locali.

## Navigazione
Su desktop il menu è orizzontale. Su mobile è un drawer verticale a destra, chiuso per default, con una voce per riga e sezioni espandibili. La lingua viene scelta da `Accept-Language` (`it` => italiano, altrimenti inglese) e può essere cambiata dal selettore in alto a destra.

## Tabelle
Le tabelle HTML con più di 200 record sono paginate nel browser. Sono disponibili 50, 100, 200, 500, 1000 o Tutti. La paginazione non modifica API, script o query machine-oriented. Su mobile le tabelle dati larghe sono rese come record compatti collassabili: i campi principali restano visibili e i dettagli secondari si espandono verticalmente, evitando lo scroll laterale ove possibile.

## Grafici e report
I grafici sono renderizzati localmente in SVG/HTML al momento della richiesta. Nessun servizio esterno viene usato per il rendering. I CronJob di manutenzione producono soltanto summary/cache storiche.

## Autorizzazioni
Le pagine con accesso negato mantengono header e menu e mostrano il link al login locale e l'indicazione sull'eventuale certificato client richiesto.
