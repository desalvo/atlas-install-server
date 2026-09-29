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
