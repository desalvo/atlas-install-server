# REST API LJSF 3 — guida italiana

## Scopo

Le API REST v1 affiancano, senza sostituire, gli endpoint legacy GET/POST/PUT già usati da LJSF 3. I client nuovi dovrebbero preferire `/atlas_install/api/v1/` perché usa URI a risorse, JSON, metodi HTTP semantici e status code standard.

Base URL:

```text
https://HOST/atlas_install/api/v1
```

## Autenticazione

Le letture di `releases`, `sites`, `tasks`, `architectures` e `targets` sono pubbliche come le corrispondenti viste dell'applicazione. Le modifiche richiedono autenticazione. `requests` richiede autenticazione anche in lettura. `users` e `local-users` richiedono ruolo `master`.

Sono supportati:

1. certificato client X.509 già usato dalla Web UI;
2. cookie di sessione locale della Web UI;
3. HTTP Basic con un utente locale, esclusivamente su HTTPS.

Esempio Basic:

```bash
curl -u admin:PASSWORD https://HOST/atlas_install/api/v1/me
```

Se l'utente locale ha TOTP attivo:

```bash
curl -u admin:PASSWORD -H 'X-ATLAS-TOTP: 123456' \
  https://HOST/atlas_install/api/v1/me
```

## Formato e codici HTTP

Le risposte normali sono JSON. Errori:

```json
{
  "error": {
    "status": 400,
    "code": "invalid_request",
    "message": "...",
    "request_id": "...",
    "details": {}
  }
}
```

Codici principali: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `409`, `415`, `500`.

## Risorse

- `/releases`
- `/sites`
- `/requests`
- `/tasks`
- `/architectures`
- `/targets`
- `/infosys`
- `/release-subscriptions`
- `/release-status`
- `/grids`
- `/facilities`
- `/site-types`
- `/request-statuses`
- `/request-types`
- `/users` — master
- `/local-users` — master
- `/me`
- `/health`

### Elenco e filtri

```bash
curl 'https://HOST/atlas_install/api/v1/releases?limit=100&offset=0&sort=name&order=ASC&q=24.1'
```

Qualunque campo esposto dalla risorsa può essere usato come filtro esatto, ad esempio:

```bash
curl 'https://HOST/atlas_install/api/v1/sites?tier_level=1&status=1'
```

La risposta contiene `data` e `meta` con `total`, `limit`, `offset` e `count`. `limit` è massimo 1000.

### Singola risorsa

```bash
curl https://HOST/atlas_install/api/v1/releases/123
```

### Creazione

```bash
curl -u admin:PASSWORD \
  -H 'Content-Type: application/json' \
  -d '{"name":"24.2.0","tag":"AtlasOffline_24_2_0","comments":"created via API"}' \
  https://HOST/atlas_install/api/v1/releases
```

Una creazione corretta risponde `201 Created` e restituisce anche `Location`.

### Modifica parziale

```bash
curl -u admin:PASSWORD -X PATCH \
  -H 'Content-Type: application/json' \
  -d '{"obsolete":1}' \
  https://HOST/atlas_install/api/v1/releases/123
```

`PUT` è accettato con la stessa semantica di aggiornamento dei campi forniti, per facilitare la migrazione dagli endpoint legacy.

### Eliminazione

```bash
curl -u admin:PASSWORD -X DELETE \
  https://HOST/atlas_install/api/v1/releases/123
```

Risposta corretta: `204 No Content`.

## Utenti locali

La password non viene mai restituita. Per creare un utente locale:

```bash
curl -u admin:ADMIN_PASSWORD \
  -H 'Content-Type: application/json' \
  -d '{"username":"operator","first_name":"Op","last_name":"User","email":"operator@example.org","role":"user","enabled":1,"password":"A-strong-password"}' \
  https://HOST/atlas_install/api/v1/local-users
```

Per cambiare password:

```bash
curl -u admin:ADMIN_PASSWORD -X PATCH \
  -H 'Content-Type: application/json' \
  -d '{"password":"Another-strong-password","must_change_password":0}' \
  https://HOST/atlas_install/api/v1/local-users/5
```

## Compatibilità

Gli endpoint legacy sotto `/exec`, `/protected/exec` e le pagine GET/POST esistenti non cambiano. La REST API è una superficie parallela e versionata. L'introduzione di v2 in futuro non modificherà il contratto di v1 senza un periodo di deprecazione documentato.
