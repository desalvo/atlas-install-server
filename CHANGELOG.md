## 3.0.0 r35

- Fixed mobile navigation on phones in both portrait and landscape: a closed drawer is now fully hidden/non-interactive and touch-landscape viewports above 900 CSS px use the mobile shell.
- The drawer is forcibly closed on load, resize and orientation changes; opened navigation remains scrollable and preserves all menu entries.
- Fixed X.509 role resolution when historical databases contain multiple equivalent DN rows. Candidate selection now prefers CA-matching, enabled, currently valid and approved records instead of blindly selecting the highest `ref`.
- Preserved CA anti-bypass semantics: a CA-unbound duplicate can never override an existing CA-bound record; a matching bound record is preferred over a mismatching one.
- Legacy `0000-00-00 00:00:00` validity values are treated as unbounded, matching historical NULL semantics.
- Added regression coverage for mobile drawer state, touch-landscape breakpoints, duplicate DN selection and CA binding precedence.

## 3.0.0 r34

- Rebuilt the common Web application shell to match the documented `ui-overview.png` mock-up: 74 px Earth top bar, 248 px dark navigation sidebar, active Home treatment, grouped navigation, compact footer, language/help/user controls and the same LJSF 3 branding.
- Replaced the legacy home search page with the documented operational dashboard while keeping the historical endpoints available from the sidebar. Dashboard KPIs, charts, recent requests/tasks and resource summaries are populated from the live ATLAS database.
- Reused exact visual crops from the documentation mock-up for the top-bar and dashboard Earth imagery so deployed UI and documentation share the same source artwork.
- Reworked the identity footer into the documented bottom status bar while preserving the collapsible certificate/local-user details and all r33 X.509/CA diagnostics.
- Added responsive drawer behaviour for the new sidebar and kept the compact-table/mobile adaptations from prior revisions.
- Updated static CI with explicit r34 UI-parity regression checks and retained the complete r33 DN canonicalization suite.

## 3.0.0 r33

- Added robust X.509 DN canonicalization across OpenSSL slash and RFC2253/RFC4514 comma-separated representations, including RDN-order normalization, escaped values, common attribute aliases, multi-valued RDN ordering and Grid proxy CN stripping.
- X.509 identity lookup now keeps the exact-match fast path and falls back to canonical comparison of historical certificate-user rows without rewriting stored DNs.
- After a canonical match, the exact historical `user.dn` value is exported back through the legacy SSL identity variables so existing literal legacy lookups continue to resolve the same row.
- CA binding remains enforced and CA DNs are compared canonically; the UI explicitly distinguishes unknown DN, matching CA, different CA and legacy records without CA metadata.
- Added focused DN/CA regression tests and retained all r32 local-authentication, CA-binding and soft-delete behavior.

## 3.0.0 r32

- Unified local and X.509 identity handling in legacy protected pages; local `master` sessions are authoritative for administration and are no longer misclassified as certificate sessions.
- Added CA binding diagnostics and role suppression on CA mismatch.
- Added soft deletion of legacy certificate users while preserving historical references and identity metadata.
- Added idempotent schema migration for CA/deletion metadata.
- Updated REST API user deletion semantics and documentation.

# Changelog

## 3.0.0 r31

- Fixed false cross-origin rejection for authenticated legacy POST forms behind HAProxy by honoring browser Fetch Metadata (`Sec-Fetch-Site: same-origin`) before proxy-dependent Origin reconstruction.
- Added the Fetch Metadata value and request path to `same_origin_rejected` diagnostics.
- Reworked legacy definition form submission for `archdef.php`, `isdef.php`, `reldef.php`, `sitedef.php`, `taskdef.php`, `tgtdef.php`, `ispardef.php`, `pardef.php`, and `sitepardef.php`. Select/Save/Delete actions are now submitted deterministically even when browsers repair invalid historical form/table markup.
- Clone/new/update source selectors now preserve `mode` and source identifiers across the generated POST.
- Added regression checks for same-origin legacy POSTs and definition-page Select flows.

## 3.0.0 r30

- Rebuilt Italian and English manuals as detailed current-system handbooks.
- Added exhaustive REST API authentication, endpoint, field, response, status-code and client examples.
- Added architecture, Kubernetes, authentication, database-migration, data-model and API-flow diagrams.
- Added sanitized illustrative UI/API screenshots and a local-chart example.
- Updated Web documentation, project design and GitHub documentation index.
- Regenerated professional downloadable PDF manuals with table of contents, figures and endpoint reference.

## 3.0.0-r29

- Optimized `protected/req.php`: lightweight count query, explicit joins, no per-row admin lookup, and dedicated request indexes.
- Added idempotent startup schema migrations for restored older database copies, including missing LJSF 3 tables/columns/indexes.
- Added persistent, dedicated Kubernetes DB schema-migration Secret support; runtime RW credentials remain separate.
- Added a browser-safe fallback for legacy definition-page Select/Save/Delete controls whose historical form/table markup is repaired differently by modern browsers.
- Expanded central Italian/English translation coverage for legacy Web UI text.
- Reorganized application and GitHub documentation into professional chapters with product metadata and separate navigation links.
- Added downloadable Italian and English PDF manuals.

## 3.0.0-r28

- Fixed nested config bootstrap path resolution for legacy_compat.php and security.php.
- All nested config.php files now discover the application root dynamically.
- Added regression coverage for shared bootstrap files from nested directories.

## 3.0.0-r27

- Reliable PHP/application error streaming into Kubernetes logs through a tailed runtime log.
- Local authenticated users are idempotently mapped into the legacy user table for protected legacy pages.
- Language switching uses a dedicated safe redirect endpoint and remains functional for authenticated sessions.
- list.php literal \n text regression removed and installation-state colours restored in responsive cards.
- req.php startup/dependency checkpoints expanded for diagnostics.

# 3.0.0-r26

- Reworked `list.php`: filters moved to a collapsed search/sort panel; row expansion uses a compact leading icon; full-width responsive cards and sortable fields.
- Fixed home/list footer flow so the installation-team contact footer always follows content.
- Added local TOTP QR-code enrollment generated entirely on the server with `qrencode`.
- Hardened Kubernetes logging: PHP errors, exceptions, DB failures, same-origin rejects and HTTP 5xx completion events go to stderr with request IDs.
- Added additional `protected/req.php` diagnostics and defensive handling for missing legacy assignee rows/status colors.

## 3.0.0-r25

- Initialize PHP/container error logging before request validation and same-origin checks, and log fatal shutdown errors with request IDs.
- Local login and password-change forms rely on their cryptographic session CSRF tokens instead of the proxy-sensitive generic Origin guard, eliminating false 403 responses behind HAProxy/TLS passthrough.
- Localized authentication/certificate/master-role access-denied messages in Italian and English.
- Prevent late exceptions from rendering a second application header/menu; late failures are logged and rendered as a single in-page alert.
- Made home chart rendering failure-isolated and removed stale legacy table-closing markup that could corrupt the page layout.
- Reworked list.php as a full-window collapsible record view on desktop and mobile; closed records always show Num, Release number, Site name and Release arch, while expanded records reveal the remaining fields without horizontal scrolling.
- Removed the legacy inline identity text from list.php and use the same collapsed identity-details section as the rest of the application.
- Moved the installation-team footer after list content and removed legacy wrapper closures that could overlap the deployment-status heading/table.
- Made access logging best-effort and proxy-aware so an access-log write failure is logged but cannot turn protected pages into HTTP 500 responses.
- Stopped protected/req.php from creating legacy users merely on GET; local identities use their authenticated local role for read/update authorization.


## 3.0.0-r19

- Automatically initializes the bundled application schema when the configured application database is absent.
- Automatically creates missing local-authentication tables in an existing application database.
- Adds `ATLAS_DB_AUTO_INIT` (default `1`) and explicit bootstrap diagnostics. Existing databases are never dropped or reset.
- Renders the full application header/menu on access-denied and application-message pages.
- Reworks mobile navigation labels into descriptive section-specific actions.

## 3.0.0-r18

- Fix local-login GET failures caused by implicit DDL: runtime now verifies the local-auth schema and logs a clear diagnostic instead of issuing CREATE TABLE on each request.
- Add one-time `sql/local-auth-schema.sql` and `scripts/init-local-auth-schema.sh`.
- Add mobile right-side collapsed navigation drawer.
- Add browser-only large-table pagination (default 200; 50/100/200/500/1000/all) and horizontal mobile scrolling.
- Serve current user documentation at `/atlas_install/documentation.php` and point the existing home-page LJSFi Documentation link to it.
- Preserve all r17 functionality and security behavior.

## 3.0.0-r16

- Fix the landing-page search to use HTTP GET rather than POST, so read-only searches are not rejected by the same-origin guard for state-changing requests.
- Give the autocomplete search fields real form names (`rel`, `sitename`, `resource`) and remove the legacy JavaScript query-string workaround.
- Redirect the HTTPS virtual-host root `/` to `/atlas_install/` with HTTP 302, so the Ingress hostname opens the application directly.
- Add regression tests for the GET search form and root redirect.

## 3.0.0-r15

- HAProxy Ingress rendering now always emits both `spec.ingressClassName: haproxy` and the compatibility annotation `kubernetes.io/ingress.class: haproxy`.
- Added static regression checks for the HAProxy ingress-class annotation in generated and example manifests.

## 3.0.0-r14

- Fix Kubernetes `ReadWriteOnce` maintenance scheduling: maintenance CronJobs now use required pod affinity to the running server pod on `kubernetes.io/hostname`, keeping all users of the shared PVC on the same node and avoiding CSI multi-attach failures.
- Refuse the incompatible combination `ReadWriteOncePod` + maintenance CronJobs.
- Keeps r13 database TLS and wizard self-update behavior unchanged.

## 3.0.0-r13

- Fix Kubernetes `bootstrap-secret.example.yaml` indentation for the DB TLS keys so GitHub CI YAML validation succeeds.
- No runtime behavior change from r12; retains optional DB TLS and wizard self-update/re-exec.

## 3.0.0-r12

- Added optional TLS/SSL for all MariaDB connections with `ATLAS_DB_SSL`, optional certificate verification via `ATLAS_DB_SSL_VERIFY`, and optional CA bundle `ATLAS_DB_SSL_CA`.
- Readiness, application RW/RO/broker connections, DB setup, and the protected configuration DB test now share the same TLS-aware mysqli connector.
- Kubernetes wizard configures database TLS and preserves those settings in the bootstrap Secret.
- Wizard self-update is enabled by default, refuses downgrades, verifies SHA-256, atomically replaces itself, and immediately re-execs the updated version.

# Changelog

## 3.0.0-r11

- Kubernetes bootstrap configuration is now authoritative for the managed `atlas-install.env` inside the container.
- On every startup the entrypoint compares the mounted bootstrap Secret with the persistent env file and atomically refreshes the env file when they differ.
- A lightweight runtime watcher (`ATLAS_BOOTSTRAP_SYNC_SECONDS`, default 5 seconds) propagates mounted Secret updates to the managed env file.
- The Kubernetes wizard tracks real bootstrap Secret changes and performs a Deployment rollout restart, ensuring startup-only settings are also reloaded.


## 3.0.0 r9

- Fix Apache mod_ssl startup with CRL checking by configuring `SSLCARevocationPath /etc/grid-security/certificates`.
- Add entrypoint validation: `SSLCARevocationCheck` cannot be enabled without `SSLCARevocationPath` or `SSLCARevocationFile`.
- Keep asynchronous, timeout-bounded `fetch-crl`; Apache can start while the initial CRL refresh completes in the background.
## 3.0.0-r8

- Hardened Apache startup: generated HTTPS config is validated for an active `Listen` and VirtualHost before launch.
- If Rocky Linux does not load `/etc/httpd/conf.d/25-atlas-install-container.conf` through its normal include chain, the entrypoint adds one explicit `Include` idempotently.
- Generated Apache config is mode 0644 and startup logs now report listener/VHost validation explicitly.
- Prevents the misleading `Syntax OK` followed by `no listening sockets available`.

# Changelog

## 3.0.0 r7

- Fixed Kubernetes/container startup failure caused by Rocky Linux httpd default `Listen 80`.
- Container image now disables the privileged HTTP listener and serves only on `ATLAS_HTTPS_PORT` (default 8443).
- Added runtime guard in the container entrypoint so a future base-image change cannot silently re-enable port 80.
- Kubernetes security context intentionally does not add `NET_BIND_SERVICE`; no privileged port is required.

## 3.0.0-r6

- Split Kubernetes resources into dedicated Namespace, PVC, Deployment, Service, Ingress and optional maintenance CronJob manifests.
- Added first-class `kustomization.yaml` support; generated installations are applied with `kubectl apply -k`.
- Updated the Kubernetes wizard to download, cache, render and apply the modular manifest set.
- Added optional wizard self-update from the selected GitHub repository/ref with SHA-256 verification and atomic replacement.
- Added optional `nodeSelector` support for the server pod; no `nodeSelector` is emitted unless explicitly enabled.
- Persisted self-update and node-selector choices as non-secret wizard state while retaining existing idempotent Secret/TLS behavior.

## 3.0.0-r5

- Prevented startup from blocking indefinitely in `fetch-crl`.
- Initial container startup now validates/refreshes IGTF trust anchors but defers CRL retrieval until Apache is running.
- Added explicit CRL refresh logging and `ATLAS_CRL_FETCH_TIMEOUT_SECONDS` (default 120 seconds).
- Added a Kubernetes `startupProbe` so liveness does not restart the pod before HTTPS is available.
- Periodic IGTF/CRL refresh now runs once immediately in the background after startup, then at the configured interval.


## 3.0.0 r4

- Fixed IGTF startup failure caused by assuming hash-named certificates were present at the archive root before local trust-store preparation.
- IGTF bundles are now merged from nested `certificates` directories, validated in staging, rehashed with `openssl rehash`, and promoted only after validation.
- Added automatic periodic IGTF trust-anchor and CRL refresh (6-hour check; 24-hour trust-bundle maximum age by default) with graceful Apache reload.
- Existing CRLs are preserved while trust anchors are replaced; failed refreshes leave the previous validated trust store active.
- Kubernetes wizard now asks before changing existing bootstrap/TLS Secrets and defaults to keeping them unchanged.
- Existing bootstrap values are used as defaults only when an update is explicitly requested; blank password input preserves the current password.
- Initial host certificate/key paths are persisted and reused; changing them requires an explicit operator choice.
- Repeated wizard runs are idempotent: unchanged manifests/state are not rewritten and identical TLS material does not cause a Secret update or Deployment restart.

## 3.0.0

- Added the standalone Kubernetes installation wizard `scripts/atlas-install-k8s-wizard.sh`.
- Added GitHub-backed Kubernetes templates with cache/update detection and installation-specific rendering.
- Added idempotent wizard management for bootstrap and host TLS Secrets, including certificate/key validation and hostname checks.
- Added persistent non-secret wizard choices while deliberately excluding database passwords and GitHub tokens from saved state.
- Established the 3.0.0 server release identity throughout first-party source, build, CI and documentation.
- Renamed the versioned application tree to `var/www/html/atlas_install-3.0.0`.
- Fixed Git/Docker build-context rules that previously excluded `var/www/html` and caused the GitHub `Build and test Rocky Linux image` job to fail.
- Added CI regression checks for required application source and ignore rules.
- Added EUPL-1.2 project licensing metadata and notices.
- Added explicit project/design, development, release-management, user and administrator documentation.
- Retained Rocky Linux 10, PHP 8.3+, Kubernetes TLS-passthrough, Docker Compose and native EL10 deployment support.
- Retained multi-architecture Docker Hub publication for `linux/amd64` and `linux/arm64`.

## 3.0.0-r17
- Local users with password + TOTP, master administration, configurable session lifetime.
- Protected pages accept certificate identity or authenticated local sessions and render a full access-denied page.
- Identity/role footer and mobile-responsive UI across PHP pages.
- Application/authentication errors are forwarded to container stderr for kubectl logs.
- Persistent IGTF/CRL cache avoids unnecessary fetch-crl runs on pod restart.

## 3.0.0-r23

- Fixed mobile drawer initialization on all application pages, with a self-contained fallback in the common menu renderer.
- Restored home search suggestions with safe server-generated datalists plus a vanilla-JavaScript autocomplete fallback; jQuery UI remains optional.
- Fixed same-origin validation behind HAProxy/TLS passthrough by accepting Host, X-Forwarded-Host, or the configured public hostname, with same-host Referer fallback for WebKit form POSTs.
- Reworked the home search matrix for portrait mobile so labels and controls stack within the viewport instead of overflowing horizontally.

## 3.0.0-r22

- Added versioned JSON REST API v1 alongside all existing legacy GET/POST/PUT endpoints.
- Added resource endpoints for releases, sites, requests, tasks, architectures, targets, certificate users and local users, with filtering, pagination, CRUD methods and standard HTTP status codes.
- REST API authentication supports X.509 identity, local Web sessions and HTTPS HTTP Basic local users; TOTP-enabled accounts require `X-ATLAS-TOTP`.
- Added Italian and English REST API documentation and an OpenAPI discovery endpoint.
- Reworked wide mobile data tables into compact per-row cards, collapsed by default, with expandable labelled details and no normal horizontal overflow.
- Desktop table rendering and browser-only 200-row pagination remain unchanged.

## 3.0.0-r21

LJSF 3 user-interface and runtime consolidation release. The application uses local LJSF 3 branding/favicons, browser-language Italian/English UI chrome and documentation, a right-side mobile navigation drawer with one item per row, collapsible identity details and explicit authentication guidance on access-denied pages. Database bootstrap supports a temporary administrator credential for creating a missing application schema or local-auth tables and the Kubernetes wizard removes the temporary password after a successful rollout. Charts are rendered locally as SVG/HTML; maintenance jobs produce only summary/cache data. Startup logs now identify each startup phase, including the initial IGTF/fetch-crl run.

## 3.0.0-r24

- Removed duplicate mobile-menu event registration; the common menu renderer is now the single owner of drawer and submenu interactions on every page.
- Rebuilt `list.php` filter/header markup as valid table HTML with an external GET filter form and a real `thead` row.
- Removed repeated invalid `tbody` emission from `list.php` records.
- Mobile `list.php` records now always expose Num, Release number, Site name and Release arch as primary fields.
- Technical/selection fields remain in the DOM when required but are hidden from the compact mobile presentation.
- Mobile records expand/collapse by tapping the row or the Details button.
- Compact `list.php` rendering remains active on coarse-pointer phones in both portrait and landscape orientations.
