# Administrator guide

## ATLAS Installation Server 3.0.0

This guide provides an administrative overview. Detailed deployment-specific procedures remain in `KUBERNETES.md`, `DOCKER-COMPOSE.md`, `OPERATIONS.md`, `GITHUB-CI.md` and `../INSTALL-RHEL10.md`.

## Supported deployment modes

The same application release supports:

- native Rocky/RHEL/EL10-compatible host installation;
- OCI deployment on Kubernetes;
- Docker Compose deployment.

The canonical OCI image is:

```text
desalvo/atlas-install-server
```

Production should use a version tag or immutable digest rather than a mutable `latest` tag.

## Runtime paths

Application source in the image/host:

```text
/var/www/html/atlas_install-3.0.0
/var/www/html/atlas_install -> atlas_install-3.0.0
```

Managed runtime configuration:

```text
/var/lib/atlas-install/config/atlas-install.env
```

IGTF trust and CRLs:

```text
/etc/grid-security/certificates
```

Container TLS mounts:

```text
/run/secrets/tls/tls.crt
/run/secrets/tls/tls.key
```

## TLS and client certificates

Apache terminates TLS. In Kubernetes the ingress must use TLS passthrough; terminating TLS at the ingress would prevent Apache from validating the original client certificate.

Public paths use optional client-certificate negotiation. `/atlas_install/protected/` requires successful certificate verification.

Keep server certificates, private keys, IGTF trust anchors and CRLs under administrator control. They are intentionally not managed through the application Web console.

## Database accounts

Use least-privilege identities:

- RO account for read-only operations;
- RW account for application data changes;
- broker account where required.

Do not grant broad administrative privileges such as `FILE`, `SUPER`, `GRANT OPTION`, schema-changing privileges or unrestricted global grants to Web application accounts unless a documented application requirement explicitly demands them.

## Kubernetes deployment

Create the namespace and TLS/bootstrap secrets before deploying the manifests. The application container listens on 8443; the Service exposes HTTPS and the ingress forwards TLS unchanged.

Typical checks:

```bash
kubectl -n atlas-install get pods,svc,ingress
kubectl -n atlas-install logs deployment/atlas-install
kubectl -n atlas-install describe deployment atlas-install
```

See `KUBERNETES.md` for the complete procedure.

## Docker Compose deployment

Compose uses the same Rocky Linux image, TLS model and persistent configuration design. Validate configuration before startup:

```bash
docker compose config -q
docker compose up -d
```

See `DOCKER-COMPOSE.md` for secret-path and volume details.

## Native installation

Use:

```bash
./install-atlas-rhel10.sh
```

The installer manages Apache/PHP-FPM integration, persistent configuration, certificate locations and IGTF/fetch-crl prerequisites. See `../INSTALL-RHEL10.md`.

## Health and readiness

Monitor:

```text
/atlas_install/healthz.php
/atlas_install/readyz.php
```

Liveness deliberately does not depend on the database. Readiness does, so a database outage should remove the instance from ready service without making process liveness fail.

## Logs

In Kubernetes:

```bash
kubectl -n atlas-install logs -f deployment/atlas-install
```

Application/PHP errors are routed to the container error stream. Logs must never contain plaintext database passwords or private-key material.

## IGTF and CRL maintenance

The container initializes/refreshed IGTF material and runs `fetch-crl`. CRL freshness is part of the client-certificate security model and should be monitored.

A forced refresh can be run as documented in `OPERATIONS.md`.

## Backup

Back up at least:

- the database using engine-native consistent backup tooling;
- persistent `/var/lib/atlas-install` data;
- deployment manifests/configuration required to reconstruct secrets from the organisation's secret-management system.

Backups containing database credentials must be encrypted and access controlled.

## Upgrade to a new release

1. verify the candidate image and immutable digest;
2. back up database and persistent application state;
3. deploy the new version/digest;
4. wait for readiness;
5. test a public function;
6. test a protected certificate-authenticated function;
7. review logs and metrics.

The versioned application directory changes with the release; the `/var/www/html/atlas_install` compatibility symlink remains stable.

## GitHub/Docker Hub release administration

GitHub Actions requires repository secret:

```text
DOCKERHUB_TOKEN
```

The CI workflow validates the source and an amd64 Rocky Linux image before publishing a multi-architecture manifest for amd64 and arm64. See `GITHUB-CI.md` and `RELEASE-MANAGEMENT.md`.

## Security administration

Administrators are responsible for:

- protecting TLS and database secrets;
- preserving TLS passthrough for client-certificate validation;
- monitoring CRL refresh failures;
- enforcing least-privilege database grants;
- pinning production images;
- reviewing CI and dependency updates;
- testing restore and rollback procedures.

See `SECURITY.md` for the complete trust-boundary model.


## Kubernetes installation wizard

For Kubernetes deployments, prefer `scripts/atlas-install-k8s-wizard.sh`; see `docs/KUBERNETES-WIZARD.md`. It renders installation-specific manifests from version-controlled templates and manages the bootstrap and TLS Secrets idempotently.

## Idempotent Kubernetes wizard and Secrets

On repeated runs, the Kubernetes wizard keeps existing bootstrap and TLS Secrets unless the administrator explicitly chooses to update them. Existing bootstrap values are offered as defaults when an update is requested. The TLS certificate/key paths selected on first configuration are remembered locally and remain unchanged until the administrator explicitly chooses new paths. This allows the wizard to be safely re-run after template updates without rotating credentials or certificates unintentionally.

The container also refreshes IGTF trust anchors and CRLs automatically; see `KUBERNETES.md` and `OPERATIONS.md` for the default intervals and failure policy.

## r19 automatic schema bootstrap and navigation

- `ATLAS_DB_AUTO_INIT=1` (default) makes the container check the configured application database at startup. If `atlas_install_panda` is absent, the bundled default schema is installed using the configured RW database account. Existing application databases are never replaced or reset.
- If only the local-authentication tables are missing, they are created automatically in the existing application database.
- The RW account therefore needs the relevant `CREATE` privileges for automatic initialization. If it does not, startup continues and the exact failure is written to Kubernetes logs with the `[atlas-db-bootstrap]` prefix.
- Access-denied/error pages render the same full navigation menu as ordinary pages.
- The mobile drawer uses descriptive, section-specific menu labels rather than generic `Definition/Update/Removal` entries.
