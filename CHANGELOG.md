# Changelog


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
