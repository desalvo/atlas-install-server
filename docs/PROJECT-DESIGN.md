# LJSF 3 - Project design

![LJSF 3](assets/ljsf3-logo.png)

| Item | Value |
| --- | --- |
| Product | LJSF 3 - ATLAS Installation System |
| Application version | 3.0.0 |
| Package revision | r32 |
| Creator / maintainer | Alessandro De Salvo |
| License | EUPL-1.2 |
| Repository | `desalvo/atlas-install-server` |
| Container image | `desalvo/atlas-install-server` |

## Visual architecture

![System architecture](assets/arch.png)

![Kubernetes topology](assets/k8s.png)

![Authentication model](assets/auth.png)

![Core data model](assets/model.png)

## Documentation contract

The repository documentation describes the current system, not a chronological request log. User-facing documentation is maintained in Italian and English; API behavior is documented from the v1 router contract and database schema. Changes to public API semantics require matching documentation and regression coverage.

---

## 1. Project identity

**Project:** ATLAS Installation Server  
**Version:** 3.0.0  
**Package revision:** r32  
**Creator / maintainer:** Alessandro De Salvo  
**Repository:** `desalvo/atlas-install-server`  
**Container image:** `desalvo/atlas-install-server`  
**Licence:** EUPL-1.2

ATLAS Installation Server 3.0.0 is the maintained, hardened continuation of the historical ATLAS/LJSFi installation service. The project preserves the externally visible installation workflows and database model while modernising the operating system, PHP runtime, deployment model, security controls and release engineering.

## 2. Project objectives

The project has five primary objectives:

1. preserve compatibility with the historical ATLAS installation workflows and stored data;
2. run on current EL10-compatible systems with PHP 8.3+;
3. support reproducible OCI deployment on amd64 and arm64;
4. enforce a modern TLS/mTLS and least-privilege security model without breaking public endpoints;
5. provide automated validation, packaging and image publication from GitHub.

## 3. Scope

In scope:

- the PHP Web application and machine-oriented endpoints;
- protected X.509-authenticated administration functions;
- application-to-database access;
- Rocky Linux 10 native and OCI deployment;
- Kubernetes and Docker Compose deployment assets;
- IGTF trust-anchor and CRL lifecycle;
- CI/CD, multi-architecture container publication and source releases;
- application configuration and operational documentation.

Out of scope:

- database engine administration itself;
- Kubernetes ingress-controller lifecycle;
- PKI issuance of server/client certificates;
- ATLAS software payload distribution outside the installation-server interfaces;
- destructive redesign of the historical database schema. Additive, idempotent migrations required by current application features are in scope.

## 4. Architectural principles

### Compatibility first

The public and protected URL structure, historical client interfaces and database semantics are retained wherever possible. Modernisation changes infrastructure and security boundaries before changing application contracts.

### Explicit trust boundaries

TLS terminates at Apache in the application pod/host. Kubernetes HAProxy Ingress uses TLS passthrough so Apache receives the original client certificate. Protected paths require successful IGTF validation and application-level authorisation.

### Secrets outside source and Web root

Database credentials and private keys are never stored in the Git repository, OCI image or Apache document root. Runtime database configuration is kept under `/var/lib/atlas-install/config`; TLS material is supplied by the deployment environment.

### Least privilege

The application uses separate read-only, read/write and optional broker database identities. Web-facing accounts are not database administrators.

### Reproducible releases

The source version is held in `VERSION`. Container and source releases are produced by GitHub Actions after static and image-level validation. Release images target both `linux/amd64` and `linux/arm64`.

## 5. Logical architecture

```text
Browser / automation
        |
        | HTTPS, optional client X.509 certificate
        v
HAProxy Ingress (TLS passthrough) or direct host access
        |
        v
Apache httpd + mod_ssl :8443
        |
        +--> public application endpoints
        |
        +--> /atlas_install/protected/*
              verified certificate required
        |
        v
PHP-FPM / PHP 8.3+
        |
        +--> runtime configuration (/var/lib/atlas-install/config)
        +--> IGTF trust + CRLs (/etc/grid-security/certificates)
        |
        v
MySQL/MariaDB-compatible database
```

Detailed request and trust flows are documented in `ARCHITECTURE.md` and `SECURITY.md`.

## 6. Source-tree design

Key paths:

```text
VERSION                              canonical release version
var/www/html/atlas_install-3.0.0/   application source tree
var/www/html/atlas_install           compatibility symlink in source archives
container/                           OCI runtime/build support
kubernetes/                          Kubernetes deployment examples
etc/atlas-install/                   configuration templates
.github/workflows/                   CI/CD workflows
tests/                               static/release validation
docs/                                user, administrator and project documentation
```

`var/www/html/atlas_install-3.0.0` is source code, not runtime state. It must therefore be tracked by Git and included in the Docker build context. Runtime mutable state is deliberately kept elsewhere.

## 7. Main components

### Web application

The PHP application contains public status/reporting pages, protected administration functions and historical machine interfaces. New code should use parameterised SQL or the established validation/quoting helpers and must preserve the certificate-based authorisation model.

### Apache and PHP-FPM

Apache terminates TLS and enforces the first mTLS boundary. PHP-FPM executes the application. The container listens on 8443 to avoid requiring a privileged application port.

### Configuration subsystem

The managed configuration is outside DocumentRoot and persists independently of the immutable image. The bootstrap secret initializes configuration only when no managed configuration exists.

### IGTF subsystem

The container/host maintains trusted IGTF anchors and CRLs. Revocation checking is part of client-certificate acceptance and is therefore an operational dependency.

### Database integration

The application remains compatible with the historical MySQL-family schema. Separate accounts support least-privilege read and write paths. Schema and data migration are operational activities rather than implicit application startup actions.

## 8. Deployment designs

Three deployment modes are supported:

- native EL10/RHEL-compatible host installation;
- Kubernetes using the Rocky Linux 10 OCI image and TLS passthrough;
- Docker Compose using the same image and secret model.

Deployment-specific documentation is in `KUBERNETES.md`, `DOCKER-COMPOSE.md`, `OPERATIONS.md` and `../INSTALL-RHEL10.md`.

## 9. CI/CD design

`.github/workflows/ci.yml` implements three gates:

1. static source, syntax, YAML, Compose, secret-hygiene and source-tree checks;
2. native amd64 Rocky Linux image build plus in-image PHP lint;
3. trusted-event Docker Hub publication as a multi-platform manifest for `linux/amd64` and `linux/arm64`.

`.github/workflows/release.yml` creates versioned source archives for `v*` tags.

The application source under `var/www/html` is intentionally **not** ignored by Git or Docker. Static tests guard this invariant because omitting it makes the Docker `COPY` instruction fail.

## 10. Versioning

The project uses semantic versioning for maintained releases. Version 3.0.0 is the first release under the new major-version line.

A release must keep these locations consistent:

- `VERSION`;
- application `$LJSFi_server_version` values;
- versioned application directory;
- Docker/OCI metadata;
- CI tests and documentation;
- Git tag (`v3.0.0`).

The release process and validation rules are documented in `RELEASE-MANAGEMENT.md`.

## 11. Security design

Security controls include:

- TLS for every application request;
- optional client certificate globally, mandatory verified certificate for protected paths;
- application role/privilege checks after certificate verification;
- CRL checking;
- secrets outside source/image/DocumentRoot;
- restricted database privileges;
- SQL-input hardening;
- container privilege/capability restrictions in Kubernetes;
- automated checks against accidental private-key inclusion.

See `SECURITY.md` for the operational security contract.

## 12. Design constraints and technical debt

The application intentionally retains historical PHP structure and database contracts. A complete framework rewrite would increase migration risk and is not part of 3.0.0. Future refactoring should therefore be incremental and backed by compatibility tests for public endpoints, protected workflows and database behaviour.

Historical third-party assets and client code may carry their own version and licence notices. These are not application release identifiers and must not be rewritten solely to match the server version.

## 13. Acceptance criteria for 3.0.0

A 3.0.0 release candidate is acceptable when:

- static tests pass;
- the Rocky Linux 10 image builds successfully from a fresh Git checkout;
- all bundled PHP files lint successfully inside the image;
- no private key or runtime secret is present in the source/image payload;
- Docker Compose and Kubernetes YAML validate;
- the multi-architecture image publishes for amd64 and arm64;
- public health and protected mTLS paths behave as documented;
- release documentation and `VERSION` are consistent.


## Kubernetes installation wizard

The Kubernetes deployment layer includes a standalone Bash wizard. The wizard separates version-controlled templates from generated site-specific manifests, retrieves template updates from a user-confirmed GitHub repository/ref, persists only non-secret operator choices, and applies Kubernetes Secrets using client-generated manifests piped to `kubectl apply`. Host TLS material remains external to Git and the container image.


## LJSF 3 schema migration policy

At container startup, the application runs an idempotent schema migration check. It may create newly introduced tables, add missing columns, and add performance indexes required by the current release. This is intentionally safe after restoring an older database dump: existing data and objects are preserved. A dedicated Kubernetes Secret `<app>-db-admin` may retain the migration account. Kubernetes Secret objects are not inherently encrypted; production clusters should enable API-server/etcd encryption at rest.
