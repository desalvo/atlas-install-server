# LJSF 3 — ATLAS Installation System

**Application version:** 3.0.0  
**Package revision:** r31  
**Creator / maintainer:** Alessandro De Salvo  
**License:** EUPL-1.2  
**Target:** Rocky Linux 10 / PHP 8.3+ / Kubernetes or Docker Compose

LJSF 3 is the ATLAS installation and deployment management service. It provides release, site, architecture, InfoSys, target, task and installation-request management through a responsive Web UI and a set of legacy-compatible machine endpoints.

### Runtime diagnostics and responsive list view

Application/PHP errors are emitted to container stderr with an `ATLAS_APP` event and request ID, including request-validation and fatal-shutdown failures. `list.php` uses the same full-width collapsible record presentation on desktop and mobile: primary fields remain visible while details expand in-place; current identity information is collapsed by default.


## Deployment models

- Kubernetes behind HAProxy Ingress with TLS passthrough;
- Docker Compose using the same Rocky Linux 10 image;
- native EL10/RHEL 10 compatible host deployment.

## Authentication and authorization

Public read-only areas remain accessible without authentication. Protected functions require either a valid client certificate associated with an application role or an enabled local account. Local authentication supports passwords, TOTP and role-based authorization. Access-denied pages retain the normal navigation and provide login/certificate guidance.

## Database

The application uses separate RW, RO and broker identities. MariaDB TLS is configurable. `ATLAS_DB_AUTO_INIT=1` enables idempotent startup checks: if the application database is absent, the bundled default schema is installed; if only local-authentication tables are absent, they are created without replacing existing application data. The Kubernetes wizard can retain a dedicated schema-migration/admin database credential in the Kubernetes Secret `<app>-db-admin`, separate from runtime RW/RO/broker credentials. Actual encryption at rest requires Kubernetes Secret encryption to be enabled in the cluster.

## User interface

The UI is responsive. Mobile navigation is a right-side vertical drawer, collapsed by default, with one item per row. Large browser tables default to 200 rows per page and can be changed to 50, 100, 200, 500, 1000 or All. Pagination affects browser rendering only. The common UI and in-application documentation use Italian for Italian browsers and English otherwise; users can override language with the top-right selector.

## Charts and summaries

Charts are rendered locally as SVG/HTML on demand. No external chart-rendering service is required. Maintenance CronJobs only refresh summary/cache data used by the Web application.

## TLS, client certificates and CRLs

Apache terminates TLS in the pod. HAProxy Ingress operates in TLS passthrough mode so the original client certificate reaches Apache. IGTF trust anchors and CRLs are managed by the container. Startup logs explicitly report the initial trust-anchor and `fetch-crl` run.

## Documentation

The documentation is organized as a current-system handbook rather than a revision diary.

- [Italian complete manual](docs/USER-GUIDE.it.md)
- [English complete manual](docs/USER-GUIDE.en.md)
- [Italian REST API reference](docs/REST-API.it.md)
- [English REST API reference](docs/REST-API.en.md)
- [Documentation index](docs/DOCUMENTATION.md)
- [Project design](docs/PROJECT-DESIGN.md)
- PDF manuals are included in `docs/LJSF3-Manual.it.pdf` and `docs/LJSF3-Manual.en.pdf`.


## Kubernetes quick start

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

The wizard remembers non-secret choices, keeps application database passwords in Kubernetes Secrets, can retain a dedicated schema-migration credential for automatic post-restore migrations, supports database TLS, generates RWO-safe maintenance jobs, adds both HAProxy Ingress class forms, self-updates from GitHub and immediately re-executes itself after a verified update.

## Container image

Canonical image:

```text
desalvo/atlas-install-server:3.0.0
```

The image is based on Rocky Linux 10. Runtime database passwords, host TLS private keys and client trust state are not baked into the image.

## Licence

EUPL-1.2. See `LICENSE` and `NOTICE`.

## REST API v1

LJSF 3 keeps all historical GET/POST/PUT interfaces and additionally exposes a versioned JSON REST API at `/atlas_install/api/v1/`. Core resources include releases, sites, requests, tasks, architectures, targets and users. See `docs/REST-API.it.md` and `docs/REST-API.en.md`. The discovery document is available at `/atlas_install/api/v1/openapi`.

On mobile, wide data tables are rendered as compact collapsible records: key fields remain visible while secondary columns are expanded vertically on demand, avoiding horizontal scrolling where practical.
