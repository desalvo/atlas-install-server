# Documentation map

## Project / engineering

- `PROJECT-DESIGN.md` — scope, design goals, architecture, components, constraints and acceptance criteria.
- `ARCHITECTURE.md` — request flow, identity, TLS/mTLS and configuration architecture.
- `DEVELOPMENT.md` — developer workflow, coding rules and validation.
- `RELEASE-MANAGEMENT.md` — release gates, tags, multi-architecture publication and rollback.
- `SECURITY.md` — security architecture and trust boundaries.

## User

- `USER-GUIDE.md` — end-user access, public/protected functions, certificates and configuration UI.
- `APPLICATION.md` — detailed functional description.
- `WEB-CONFIGURATION.md` — master-only server configuration console.

## Administrator

- `ADMIN-GUIDE.md` — administrative overview and operational responsibilities.
- `KUBERNETES.md` — Kubernetes deployment.
- `KUBERNETES-WIZARD.md` — interactive manifest/Secret generator and updater.
- `DOCKER-COMPOSE.md` — Docker Compose deployment.
- `OPERATIONS.md` — logs, certificates, CRLs, backup, upgrade and diagnostics.
- `GITHUB-CI.md` — CI/CD and Docker Hub publication.
- `../INSTALL-RHEL10.md` — native EL10 installation.

## r19 automatic schema bootstrap and navigation

- `ATLAS_DB_AUTO_INIT=1` (default) makes the container check the configured application database at startup. If `atlas_install_panda` is absent, the bundled default schema is installed using the configured RW database account. Existing application databases are never replaced or reset.
- If only the local-authentication tables are missing, they are created automatically in the existing application database.
- The RW account therefore needs the relevant `CREATE` privileges for automatic initialization. If it does not, startup continues and the exact failure is written to Kubernetes logs with the `[atlas-db-bootstrap]` prefix.
- Access-denied/error pages render the same full navigation menu as ordinary pages.
- The mobile drawer uses descriptive, section-specific menu labels rather than generic `Definition/Update/Removal` entries.
