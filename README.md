# ATLAS Installation Server 3.0.0 — Rocky Linux 10 / Kubernetes / Docker Compose

This package is a hardened and modernized continuation of the historical ATLAS/LJSFi installation server.
It supports three deployment models:

1. native EL10/RHEL 10 compatible host deployment;
2. OCI container deployment on Kubernetes behind HAProxy Ingress with TLS passthrough;
3. Docker Compose deployment using the same Rocky Linux image and mTLS model.

The application keeps the historical access model:

- public pages remain accessible without a client certificate;
- `/atlas_install/protected/` requires a valid IGTF-trusted client certificate;
- application roles and privileges are resolved from the certificate Distinguished Name stored in the database;
- the protected server configuration UI additionally requires the application role `master`.

## Main changes

- PHP 8.3+ compatibility and SQL-injection hardening.
- EL10-compatible Apache/PHP-FPM deployment.
- IGTF trust-anchor and CRL management (`fetch-crl`).
- Modern responsive UI layered over the historical pages.
- Protected Web configuration console at `/atlas_install/protected/configuration.php`.
- OCI image based on Rocky Linux 10, published as `desalvo/atlas-install-server`.
- Modular Kubernetes manifests with Kustomize support and TLS passthrough.
- Liveness/readiness endpoints.
- Secrets kept outside the Web document root and outside the container image.

## Documentation

A complete documentation index is available in `docs/DOCUMENTATION.md`.

### Project and developer documentation

- `docs/PROJECT-DESIGN.md` — project scope, design, components, constraints, CI/CD and acceptance criteria.
- `docs/ARCHITECTURE.md` — detailed components, request flow and trust boundaries.
- `docs/DEVELOPMENT.md` — development conventions, validation and contribution workflow.
- `docs/RELEASE-MANAGEMENT.md` — versioning, release gates, multi-arch publication and rollback.
- `docs/SECURITY.md` — security architecture and security requirements.

### User documentation

- `docs/USER-GUIDE.md` — end-user guide for public/protected access and certificate-authenticated use.
- `docs/APPLICATION.md` — application functions, public/protected areas and roles.
- `docs/WEB-CONFIGURATION.md` — protected server configuration console.

### Administrator documentation

- `docs/ADMIN-GUIDE.md` — administrative overview and operational responsibilities.
- `INSTALL-RHEL10.md` — native EL10 host installation.
- `docs/KUBERNETES.md` — Kubernetes deployment.
- `docs/DOCKER-COMPOSE.md` — Docker Compose deployment.
- `docs/OPERATIONS.md` — upgrades, certificates, CRLs, backups and diagnostics.
- `docs/GITHUB-CI.md` — GitHub Actions and Docker Hub publication.

## Container build

```bash
./container/build-image.sh desalvo/atlas-install-server:latest
```

The build context never contains production passwords, TLS private keys or client CA material.

## Kubernetes quick start

The recommended installation path is the interactive Kubernetes wizard. It downloads or refreshes the manifest templates directly from the selected GitHub repository, remembers the last non-secret choices and can create/update the bootstrap and TLS Secrets:

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

See `docs/KUBERNETES-WIZARD.md` for the complete workflow. The wizard generates separate Namespace/PVC/Deployment/Service/Ingress manifests plus `kustomization.yaml`, supports an optional nodeSelector, and can optionally self-update from GitHub. Manual installation remains supported.

Create the TLS secret containing the wildcard/server certificate:

```bash
kubectl create namespace atlas-install
kubectl -n atlas-install create secret tls atlas-install-tls \
  --cert=/path/to/fullchain.pem \
  --key=/path/to/privkey.pem
```

Create the bootstrap configuration secret from a local file containing the database credentials:

```bash
kubectl -n atlas-install create secret generic atlas-install-bootstrap \
  --from-file=atlas-install.env=/secure/path/atlas-install.env
```

The supplied Kubernetes manifests reference `desalvo/atlas-install-server:3.0.0`; adjust the image if required, then apply the Namespace and the Kustomize set:

```bash
kubectl apply -f kubernetes/manifests/00-namespace.yaml
kubectl apply -k kubernetes/manifests/
```

The configured public endpoint is expected to be:

```text
https://atlas-install-el10.apps.desalvo.eu/atlas_install/
```

HAProxy Ingress must operate in TLS passthrough mode so the original client certificate reaches Apache unchanged.

## Docker Compose quick start

See `docs/DOCKER-COMPOSE.md`. The included `docker-compose.yml` uses the same TLS/mTLS model and persists application configuration in named volumes.

## GitHub repository and CI/CD

The repository layout is ready for `https://github.com/desalvo/atlas-install-server`. GitHub Actions validate the code and Rocky Linux image before publishing to Docker Hub. See `docs/GITHUB-CI.md`.

## Docker image

The canonical Docker Hub image is:

```text
desalvo/atlas-install-server
```

The container base is `rockylinux/rockylinux:10`. Runtime passwords, TLS keys and IGTF state are not baked into the image.

## Repository import

The source tree is ready to be uploaded as `desalvo/atlas-install-server`. See `GITHUB-UPLOAD.md` for initial Git setup and the required `DOCKERHUB_TOKEN` GitHub Actions secret.

## Licence

ATLAS Installation Server 3.0.0 is distributed under the European Union Public Licence v1.2 (`EUPL-1.2`). See `LICENSE` and `NOTICE`. Bundled third-party components retain their own applicable licence notices.

### Kubernetes/IGTF updates in r4

The Kubernetes wizard is idempotent and preserves existing Secrets by default. Host certificate/key paths chosen during initial setup are remembered until explicitly changed. The container automatically refreshes IGTF trust anchors and CRLs and validates/re-hashes the trust directory before Apache uses it.

### Container ports

The Kubernetes/container deployment serves HTTPS only on unprivileged port `8443` inside the pod. The Rocky Linux default Apache `Listen 80` is explicitly disabled; the Kubernetes Service maps port 443 to container port 8443. No `NET_BIND_SERVICE` capability is required.


### Container CRL handling (r9)

Apache uses `/etc/grid-security/certificates` as both `SSLCACertificatePath` and `SSLCARevocationPath`; `fetch-crl` refreshes CRLs asynchronously with a bounded timeout.

## r12: optional database TLS and automatic wizard restart

The Kubernetes wizard can configure TLS independently for MariaDB connections. The bootstrap environment uses:

```text
ATLAS_DB_SSL="1"
ATLAS_DB_SSL_VERIFY="0"
ATLAS_DB_SSL_CA=""
```

`ATLAS_DB_SSL=1` enables `MYSQLI_CLIENT_SSL` for every application database role and for readiness. Set `ATLAS_DB_SSL_VERIFY=1` to verify the database server certificate; `ATLAS_DB_SSL_CA` may point to a CA bundle inside the container, or remain blank to use the system trust store.

The Kubernetes wizard checks its configured GitHub source at startup by default. It accepts only a newer `WIZARD_VERSION` whose published SHA-256 matches, refuses downgrades, replaces itself atomically, and immediately restarts itself with the original arguments. Use `--no-self-update` to skip the check for a run.
