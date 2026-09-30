# LJSF 3 REST API
## Riferimento tecnico completo v1

![](assets/ljsf3-logo.png)

| Dato | Valore |
| --- | --- |
| Prodotto | LJSF 3 - ATLAS Installation System |
| Versione applicazione | 3.0.0 |
| Revisione pacchetto | r30 |
| API version | v1 |
| Base path | `/atlas_install/api/v1` |
| Formato | JSON / HTTPS |

> Documento normativo per la superficie REST API v1 inclusa nel pacchetto r30. Gli endpoint legacy restano disponibili ma non fanno parte del contratto REST.

<div class="pagebreak"></div>

## 1. Identità del servizio

- **Product:** LJSF 3 - ATLAS Installation System
- **Application version:** 3.0.0
- **Package revision:** r30
- **Base path:** `/atlas_install/api/v1`
- **Media type:** `application/json; charset=UTF-8`
- **Response cache:** `Cache-Control: no-store`
- **API version header:** `X-API-Version: v1`
- **Request correlation:** `X-Request-ID`

![REST request lifecycle](assets/api.png)

*Figura: ciclo di vita di una richiesta REST.*

## 2. Modello di autenticazione

Le API riutilizzano il modello di identità dell’applicazione. L’identità viene risolta nell’ordine seguente: sessione locale Web già valida; certificato client X.509 valido; come fallback specifico REST, HTTP Basic con account locale. Se un account locale dispone di almeno un TOTP attivo, l’accesso Basic richiede anche l’header `X-ATLAS-TOTP`.

![Authentication model](assets/auth.png)

| Meccanismo | Trasporto | Requisiti | Note |
| --- | --- | --- | --- |
| Certificato X.509 | TLS client certificate | Il TLS deve arrivare ad Apache; `SSL_CLIENT_VERIFY=SUCCESS`; il DN deve essere mappato nella tabella `user`. | Adatto ad automazione con certificati personali/robot. |
| Sessione locale | Cookie Web sicuro | Sessione locale non scaduta e account abilitato. | Utile a chiamate effettuate dal browser già autenticato. |
| HTTP Basic | `Authorization: Basic ...` | Account locale abilitato, password corretta, `must_change_password=0`. | Usare esclusivamente su HTTPS. |
| Basic + TOTP | Basic + `X-ATLAS-TOTP: 123456` | Obbligatorio se l’account dispone di TOTP attivi. | Il codice è 6 cifre; la finestra TOTP accetta lo slice corrente +/- 1. |

### 2.1 Esempi di autenticazione

```bash
# Basic senza TOTP
curl --fail-with-body -u operator:'PASSWORD' \
  https://atlas-install.example.org/atlas_install/api/v1/me

# Basic con TOTP
curl --fail-with-body -u operator:'PASSWORD' \
  -H 'X-ATLAS-TOTP: 123456' \
  https://atlas-install.example.org/atlas_install/api/v1/me

# X.509
curl --fail-with-body --cert client.pem --key client.key \
  https://atlas-install.example.org/atlas_install/api/v1/me
```

### 2.2 Errori di autenticazione

- `401 authentication_required`: nessuna identità valida. La risposta include `WWW-Authenticate: Basic realm="LJSF 3 REST API"`.
- `401 totp_required`: account Basic valido ma manca/è errato il TOTP.
- `403 password_change_required`: l’account locale deve cambiare password tramite Web UI prima di usare l’API.
- `403 forbidden`: autenticazione valida ma ruolo insufficiente.

## 3. Convenzioni HTTP e JSON

| Elemento | Comportamento |
| --- | --- |
| GET collection | `200` with `{data:[...], meta:{resource,total,limit,offset,count}}` |
| GET item | `200` with `{data:{...}}`; `404` if not found |
| POST collection | Requires JSON body; `201 Created`; `Location` header; response contains created id and location |
| PATCH item | Partial update; `200` with the updated resource |
| PUT item | Currently has the same supplied-field partial-update semantics as PATCH; it is not a full replacement |
| DELETE item | `204 No Content`; `404` if missing; `409` on database conflicts |
| OPTIONS | Returns supported method names; router advertises GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS |
| HEAD | Accepted on read routes; web server may suppress the body as required by HTTP |
| Content-Type | POST/PUT/PATCH with a non-empty body require `application/json`; otherwise `415` |
| Unknown JSON field | `400 unknown_field`; immutable keys are also rejected on update |
| Arrays/objects in fields | Rejected with `400 invalid_field`; resource fields are scalar |
| Caching | All API responses send `Cache-Control: no-store` |

### 3.1 Busta di errore

```json
{
  "error": {
    "status": 400,
    "code": "invalid_request",
    "message": "...",
    "request_id": "7c3f2a9b...",
    "details": {}
  }
}
```

### 3.2 Codici di ritorno

| HTTP | Nome | Significato |
| --- | --- | --- |
| 200 | OK | Lettura o aggiornamento riuscito. |
| 201 | Created | Creazione riuscita; usare `Location`. |
| 204 | No Content | Eliminazione riuscita. |
| 400 | Bad Request | ID/JSON/campo/query non valido. |
| 401 | Unauthorized | Autenticazione assente o TOTP richiesto. |
| 403 | Forbidden | Ruolo insufficiente o cambio password richiesto. |
| 404 | Not Found | Route, risorsa o record inesistente. |
| 405 | Method Not Allowed | Metodo non supportato dalla route. |
| 409 | Conflict | Conflitto/vincolo DB durante write/delete. |
| 415 | Unsupported Media Type | Body non JSON. |
| 500 | Internal Server Error | Errore interno; correlare con `request_id` nei log. |


## 4. Collezioni, filtri, ricerca, paginazione e ordinamento

Ogni campo esposto dalla risorsa può essere passato come query parameter per un confronto esatto (`field=value`). Il parametro `q` effettua una ricerca `LIKE %q%` esclusivamente sui campi elencati come *search fields*. `limit` ha default 200 e massimo 1000; `offset` non può essere negativo. `sort` deve essere uno dei campi esposti, altrimenti viene usata la chiave primaria; `order` accetta `ASC` o `DESC`, altrimenti `ASC`.

```bash
curl 'https://atlas-install.example.org/atlas_install/api/v1/releases?limit=100&offset=0&sort=name&order=DESC&q=24.3'
```

## 5. Matrice delle risorse

| Resource | DB table | Key | GET | Write | `q` fields |
| --- | --- | --- | --- | --- | --- |
| releases | release_data | ref | pubblico | utente autenticato | name, tag, package, comments |
| sites | site | ref | pubblico | utente autenticato | cs, cename, name, atlas_name, alias, arch |
| requests | request | id | utente autenticato | utente autenticato | id, user_comments, admin_comments |
| tasks | task | ref | pubblico | utente autenticato | name, description, target |
| architectures | release_arch | ref | pubblico | utente autenticato | platform_type, os_type, gcc_ver, mode, description |
| targets | autoinstall_target | ref | pubblico | utente autenticato | name, description |
| infosys | bdii | ref | pubblico | utente autenticato | hostname, ns, lb, ld, wmproxy, myproxy |
| release-subscriptions | release_subscription | ref | utente autenticato | utente autenticato | sitename, pattern, comment |
| release-status | release_stat | ref | pubblico | utente autenticato | name, tag, status, comments |
| grids | grid | ref | pubblico | utente autenticato | name, description |
| facilities | facility | ref | pubblico | utente autenticato | name, description |
| site-types | site_type | ref | pubblico | utente autenticato | name, description |
| request-statuses | request_status | ref | pubblico | ruolo master | description |
| request-types | request_type | ref | pubblico | ruolo master | field, description, comment |
| users | user | ref | ruolo master | ruolo master | name, dn, email |
| local-users | atlas_local_user | id | ruolo master | ruolo master | username, first_name, last_name, email, role |


## 6. Endpoint speciali

### `GET /`
Restituisce nome API, versione, URL documentazione e elenco risorse.

### `GET /health`
Health dell’API, non equivalente alla readiness applicativa completa. Risposta: `{"status":"ok","api":"v1","request_id":"..."}`.

### `GET /me`
Richiede autenticazione e restituisce l’identità effettiva. `password_hash` viene sempre rimosso.

### `GET /openapi`
Restituisce un documento OpenAPI 3.1 sintetico con path e metodi; il presente manuale è il riferimento più dettagliato per campi, autenticazione e semantica.

![API request/response screenshot](assets/api-example.png)

## 7. Riferimento completo delle risorse

### 7.1 `releases`

**Tabella DB:** `release_data`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, tag, package, comments`

**Metodi:**

- `GET /releases`
- `GET /releases/{id}`
- `POST /releases`
- `PATCH /releases/{id}`
- `PUT /releases/{id}`
- `DELETE /releases/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(50) | NO | yes | Nome canonico o nome visualizzato. |
| build | varchar(15) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| typefk | int(11) | NO | yes | Riferimento al tipo. |
| archfk | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sw_archfk | int(11) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| userfk | int(11) | NO | yes | Riferimento all’utente. |
| obsolete | int(11) | YES | yes | Flag di release obsoleta. |
| autoinstall | int(11) | YES | yes | Flag per auto-installazione. |
| requires | varchar(30) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| dbrelease | varchar(10) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| installer_version | varchar(10) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| install_tools_version | varchar(10) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sw_name | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sw_revision | varchar(20) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sw_physicalpath | varchar(255) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sw_logicalpath | varchar(255) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| tag | varchar(100) | NO | yes | Tag software/release. |
| package | varchar(255) | NO | yes | Pacchetto o descrittore del software. |
| comments | varchar(255) | YES | yes | Commenti operativi. |
| date | datetime | NO | yes | Data/ora associata al record. |
| cvmfs_available | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| critical | int(11) | NO | yes | Flag di release critica. |
| critical_validity_from | datetime | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| critical_validity_to | datetime | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| critical_description | varchar(25) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/releases?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/releases/123
```

### 7.2 `sites`

**Tabella DB:** `site`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `cs, cename, name, atlas_name, alias, arch`

**Metodi:**

- `GET /sites`
- `GET /sites/{id}`
- `POST /sites`
- `PATCH /sites/{id}`
- `PUT /sites/{id}`
- `DELETE /sites/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| cs | varchar(128) | NO | yes | Computer service/identificatore legacy. |
| cename | varchar(50) | NO | yes | Nome CE legacy. |
| name | varchar(30) | NO | yes | Nome canonico o nome visualizzato. |
| atlas_name | varchar(30) | NO | yes | Nome ATLAS del sito. |
| tier_level | int(11) | NO | yes | Livello/tier del sito. |
| gridfk | int(11) | NO | yes | Riferimento alla grid. |
| facilityfk | int(11) | YES | yes | Riferimento alla facility. |
| activity_typefk | int(11) | YES | yes | Riferimento al tipo attività. |
| osname | varchar(128) | NO | yes | Nome OS. |
| osrelease | varchar(128) | NO | yes | Release OS. |
| osversion | varchar(128) | NO | yes | Versione OS. |
| arch | varchar(20) | NO | yes | Architettura riportata dal sito. |
| tags | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| alias | varchar(128) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| swarea | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| fstype | varchar(15) | YES | yes | Tipo filesystem. |
| mountpoint | varchar(255) | YES | yes | Mount point. |
| capacity | int(11) | YES | yes | Capacità. |
| available | int(11) | YES | yes | Spazio disponibile. |
| quota | int(11) | YES | yes | Quota. |
| status | int(11) | YES | yes | Stato applicativo. |
| attr | int(10) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| host_status | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| resource_status | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| last_activity | timestamp | YES | yes | Ultima attività registrata. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/sites?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/sites/123
```

### 7.3 `requests`

**Tabella DB:** `request`  
**Chiave:** `id` (string)  
**Lettura:** utente autenticato  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `id, user_comments, admin_comments`

**Metodi:**

- `GET /requests`
- `GET /requests/{id}`
- `POST /requests`
- `PATCH /requests/{id}`
- `PUT /requests/{id}`
- `DELETE /requests/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| id | varchar(128) | NO | no | Identificatore della risorsa. |
| bdiifk | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| sitefk | int(11) | NO | yes | Riferimento al sito. |
| relfk | int(11) | NO | yes | Riferimento alla release. |
| typefk | int(11) | NO | yes | Riferimento al tipo. |
| userfk | int(11) | NO | yes | Riferimento all’utente. |
| adminfk | int(11) | YES | yes | Riferimento all’amministratore/operatore assegnato. |
| statusfk | int(11) | NO | yes | Riferimento allo stato della richiesta. |
| force_run | int(11) | NO | yes | Flag per forzare l’esecuzione. |
| request_date | datetime | YES | yes | Data/ora della richiesta. |
| update_date | datetime | YES | yes | Data/ora dell’ultimo aggiornamento. |
| user_comments | varchar(255) | YES | yes | Commenti inseriti dall’utente. |
| admin_comments | varchar(255) | YES | yes | Commenti amministrativi. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/requests?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/requests/REQ-EXAMPLE-001
```

### 7.4 `tasks`

**Tabella DB:** `task`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, description, target`

**Metodi:**

- `GET /tasks`
- `GET /tasks/{id}`
- `POST /tasks`
- `PATCH /tasks/{id}`
- `PUT /tasks/{id}`
- `DELETE /tasks/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(50) | NO | yes | Nome canonico o nome visualizzato. |
| description | varchar(255) | YES | yes | Descrizione testuale. |
| target | varchar(20) | YES | yes | Target associato al task. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/tasks?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/tasks/123
```

### 7.5 `architectures`

**Tabella DB:** `release_arch`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `platform_type, os_type, gcc_ver, mode, description`

**Metodi:**

- `GET /architectures`
- `GET /architectures/{id}`
- `POST /architectures`
- `PATCH /architectures/{id}`
- `PUT /architectures/{id}`
- `DELETE /architectures/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| platform_type | varchar(16) | NO | yes | Tipo di piattaforma. |
| os_type | varchar(128) | NO | yes | Sistema operativo. |
| gcc_ver | varchar(128) | NO | yes | Versione/toolchain GCC. |
| mode | varchar(10) | NO | yes | Modalità di build. |
| description | varchar(255) | NO | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/architectures?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/architectures/123
```

### 7.6 `targets`

**Tabella DB:** `autoinstall_target`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, description`

**Metodi:**

- `GET /targets`
- `GET /targets/{id}`
- `POST /targets`
- `PATCH /targets/{id}`
- `PUT /targets/{id}`
- `DELETE /targets/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(15) | NO | yes | Nome canonico o nome visualizzato. |
| description | varchar(255) | YES | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/targets?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/targets/123
```

### 7.7 `infosys`

**Tabella DB:** `bdii`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `hostname, ns, lb, ld, wmproxy, myproxy`

**Metodi:**

- `GET /infosys`
- `GET /infosys/{id}`
- `POST /infosys`
- `PATCH /infosys/{id}`
- `PUT /infosys/{id}`
- `DELETE /infosys/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| facilityfk | int(11) | NO | yes | Riferimento alla facility. |
| hostname | varchar(128) | NO | yes | Nome host. |
| port | int(11) | NO | yes | Porta TCP. |
| ns | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| lb | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| ld | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| wmproxy | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| myproxy | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| preferred | int(11) | NO | yes | Flag di preferenza. |
| enabled | int(11) | NO | yes | Flag di abilitazione (0/1). |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/infosys?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/infosys/123
```

### 7.8 `release-subscriptions`

**Tabella DB:** `release_subscription`  
**Chiave:** `ref` (integer)  
**Lettura:** utente autenticato  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `sitename, pattern, comment`

**Metodi:**

- `GET /release-subscriptions`
- `GET /release-subscriptions/{id}`
- `POST /release-subscriptions`
- `PATCH /release-subscriptions/{id}`
- `PUT /release-subscriptions/{id}`
- `DELETE /release-subscriptions/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| sitename | varchar(50) | NO | yes | Nome sito. |
| userfk | int(11) | NO | yes | Riferimento all’utente. |
| pattern | varchar(50) | NO | yes | Pattern di sottoscrizione. |
| date | datetime | NO | yes | Data/ora associata al record. |
| comment | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/release-subscriptions?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/release-subscriptions/123
```

### 7.9 `release-status`

**Tabella DB:** `release_stat`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, tag, status, comments`

**Metodi:**

- `GET /release-status`
- `GET /release-status/{id}`
- `POST /release-status`
- `PATCH /release-status/{id}`
- `PUT /release-status/{id}`
- `DELETE /release-status/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| sitefk | int(11) | NO | yes | Riferimento al sito. |
| name | varchar(50) | NO | yes | Nome canonico o nome visualizzato. |
| userfk | int(11) | NO | yes | Riferimento all’utente. |
| pin | int(11) | YES | yes | Flag pin. |
| pinuserfk | int(11) | YES | yes | Utente che ha effettuato il pin. |
| pindate | datetime | YES | yes | Data pin. |
| tag | varchar(100) | NO | yes | Tag software/release. |
| status | varchar(100) | NO | yes | Stato applicativo. |
| comments | varchar(255) | YES | yes | Commenti operativi. |
| date | datetime | NO | yes | Data/ora associata al record. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/release-status?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/release-status/123
```

### 7.10 `grids`

**Tabella DB:** `grid`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, description`

**Metodi:**

- `GET /grids`
- `GET /grids/{id}`
- `POST /grids`
- `PATCH /grids/{id}`
- `PUT /grids/{id}`
- `DELETE /grids/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(10) | NO | yes | Nome canonico o nome visualizzato. |
| description | varchar(255) | YES | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/grids?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/grids/123
```

### 7.11 `facilities`

**Tabella DB:** `facility`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, description`

**Metodi:**

- `GET /facilities`
- `GET /facilities/{id}`
- `POST /facilities`
- `PATCH /facilities/{id}`
- `PUT /facilities/{id}`
- `DELETE /facilities/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(10) | NO | yes | Nome canonico o nome visualizzato. |
| description | varchar(255) | YES | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/facilities?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/facilities/123
```

### 7.12 `site-types`

**Tabella DB:** `site_type`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** utente autenticato  
**Campi di ricerca `q`:** `name, description`

**Metodi:**

- `GET /site-types`
- `GET /site-types/{id}`
- `POST /site-types`
- `PATCH /site-types/{id}`
- `PUT /site-types/{id}`
- `DELETE /site-types/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(50) | NO | yes | Nome canonico o nome visualizzato. |
| description | varchar(255) | NO | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/site-types?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/site-types/123
```

### 7.13 `request-statuses`

**Tabella DB:** `request_status`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** ruolo master  
**Campi di ricerca `q`:** `description`

**Metodi:**

- `GET /request-statuses`
- `GET /request-statuses/{id}`
- `POST /request-statuses`
- `PATCH /request-statuses/{id}`
- `PUT /request-statuses/{id}`
- `DELETE /request-statuses/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| description | varchar(128) | YES | yes | Descrizione testuale. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/request-statuses?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/request-statuses/123
```

### 7.14 `request-types`

**Tabella DB:** `request_type`  
**Chiave:** `ref` (integer)  
**Lettura:** pubblico  
**Scrittura:** ruolo master  
**Campi di ricerca `q`:** `field, description, comment`

**Metodi:**

- `GET /request-types`
- `GET /request-types/{id}`
- `POST /request-types`
- `PATCH /request-types/{id}`
- `PUT /request-types/{id}`
- `DELETE /request-types/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| level | int(11) | NO | yes | Livello/precedenza. |
| field | varchar(100) | NO | yes | Nome del campo applicativo. |
| description | varchar(128) | YES | yes | Descrizione testuale. |
| comment | varchar(255) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/request-types?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/request-types/123
```

### 7.15 `users`

**Tabella DB:** `user`  
**Chiave:** `ref` (integer)  
**Lettura:** ruolo master  
**Scrittura:** ruolo master  
**Campi di ricerca `q`:** `name, dn, email`

**Metodi:**

- `GET /users`
- `GET /users/{id}`
- `POST /users`
- `PATCH /users/{id}`
- `PUT /users/{id}`
- `DELETE /users/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| ref | int(11) | NO | no | Identificatore numerico della risorsa. |
| name | varchar(50) | NO | yes | Nome canonico o nome visualizzato. |
| dn | varchar(200) | YES | yes | Distinguished Name X.509. |
| email | varchar(150) | YES | yes | Indirizzo email associato. |
| priv_view | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| priv_insert | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| priv_update | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| priv_pin | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| priv_relsub | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| priv_critical | int(11) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| rolefk | int(11) | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| valid_start | datetime | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| valid_end | datetime | YES | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| enabled | int(11) | NO | yes | Flag di abilitazione (0/1). |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/users?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/users/123
```

### 7.16 `local-users`

**Tabella DB:** `atlas_local_user`  
**Chiave:** `id` (integer)  
**Lettura:** ruolo master  
**Scrittura:** ruolo master  
**Campi di ricerca `q`:** `username, first_name, last_name, email, role`

**Metodi:**

- `GET /local-users`
- `GET /local-users/{id}`
- `POST /local-users`
- `PATCH /local-users/{id}`
- `PUT /local-users/{id}`
- `DELETE /local-users/{id}`

**Campi esposti:**

| Field | DB type | Nullable | Scrivibile | Descrizione |
| --- | --- | --- | --- | --- |
| id | BIGINT | NO | no | Identificatore della risorsa. |
| username | VARCHAR(64) | NO | yes | Nome account locale. |
| first_name | VARCHAR(100) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| last_name | VARCHAR(100) | NO | yes | Campo canonico dello schema applicativo; il significato operativo dipende dalla risorsa. |
| email | VARCHAR(254) | NO | yes | Indirizzo email associato. |
| role | VARCHAR(32) | NO | yes | Ruolo applicativo locale: user, admin o master. |
| enabled | TINYINT(1) | NO | yes | Flag di abilitazione (0/1). |
| must_change_password | TINYINT(1) | NO | yes | Se 1, l’account deve cambiare password prima dell’uso API. |
| created_at | DATETIME | NO | yes | Data/ora di creazione. |
| updated_at | DATETIME | NO | yes | Data/ora di ultimo aggiornamento. |


**Esempi:**

```bash
curl https://atlas-install.example.org/atlas_install/api/v1/local-users?limit=20&offset=0
curl https://atlas-install.example.org/atlas_install/api/v1/local-users/123
```

`password` è un campo di input speciale: viene accettato in POST/PATCH/PUT, richiede almeno 8 caratteri, viene trasformato in `password_hash` e non viene mai restituito.

## 8. Esempi completi

### 8.1 Creazione e modifica di un utente locale
```bash
curl -u admin:'ADMIN_PASSWORD' -H 'Content-Type: application/json' \
  -d '{"username":"operator","first_name":"API","last_name":"Operator","email":"operator@example.org","role":"user","enabled":1,"password":"StrongPass-2026"}' \
  https://atlas-install.example.org/atlas_install/api/v1/local-users

curl -u admin:'ADMIN_PASSWORD' -X PATCH -H 'Content-Type: application/json' \
  -d '{"role":"admin","must_change_password":0}' \
  https://atlas-install.example.org/atlas_install/api/v1/local-users/5
```
### 8.2 Python
```python
import requests

base = 'https://atlas-install.example.org/atlas_install/api/v1'
r = requests.get(f'{base}/releases', params={'q':'24.3','limit':50}, timeout=30)
r.raise_for_status()
for release in r.json()['data']:
    print(release['ref'], release['name'], release['tag'])
```
### 8.3 Gestione robusta degli errori
```python
import requests

r = requests.get('https://atlas-install.example.org/atlas_install/api/v1/requests', timeout=30)
if r.status_code >= 400:
    payload = r.json()
    err = payload.get('error', {})
    print('HTTP', r.status_code, err.get('code'), err.get('message'), 'request_id=', err.get('request_id'))
```

## 9. Sicurezza, compatibilità e limiti correnti

- Usare sempre HTTPS; Basic invia credenziali riutilizzabili a ogni richiesta.
- Le password/hash locali non vengono restituite.
- Le operazioni di scrittura usano query parametrizzate e whitelist dei campi esposti.
- Non esistono attualmente ETag/If-Match né un header `Idempotency-Key`: i client devono evitare retry ciechi di POST.
- Non esistono endpoint bulk: ogni richiesta modifica una singola risorsa.
- `PUT` è intenzionalmente patch-like nella v1; non assumere semantica di sostituzione completa.
- Il limite massimo di pagina è 1000; per dataset più grandi iterare `offset`.
- Le risorse con dipendenze DB possono restituire 409 su DELETE/UPDATE per vincoli o conflitti.
- Gli endpoint legacy restano separati; non dedurre il contratto REST dalla sintassi degli endpoint legacy.

## 10. Checklist client di produzione

- [ ] Verificare il certificato TLS del server.
- [ ] Impostare timeout espliciti lato client.
- [ ] Registrare e propagare `X-Request-ID` quando possibile.
- [ ] Gestire 401, 403, 409, 415 e 5xx in modo distinto.
- [ ] Non registrare password, Basic Authorization o TOTP.
- [ ] Usare paginazione e limiti ragionevoli.
- [ ] Prima di DELETE, verificare dipendenze e gestire 409.
- [ ] Per automazione privilegiata usare account dedicati e minimo ruolo necessario.
