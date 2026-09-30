# Architecture

## Network flow

```text
Client browser / automation
        |
        | HTTPS + optional client X.509 certificate
        v
Kubernetes HAProxy Ingress
        |
        | TLS passthrough (TCP/SNI; no TLS termination)
        v
ATLAS Installation container :8443
  Apache + mod_ssl
        |
        +-- public application paths: certificate optional
        |
        +-- /atlas_install/protected/: verified certificate required
        |
        v
PHP-FPM / PHP 8.3+
        |
        | MySQL protocol / TCP 3306
        v
192.168.1.145
Percona / MySQL / MariaDB
```

The public hostname is `atlas-install-el10.apps.desalvo.eu` by default.

## TLS and mTLS

The pod terminates TLS. HAProxy Ingress only forwards the TLS stream. The server certificate is mounted from the Kubernetes Secret `atlas-install-tls`.

Apache is configured with:

```apache
SSLVerifyClient optional
SSLCACertificatePath /etc/grid-security/certificates
SSLCARevocationCheck chain
```

A client certificate is therefore requested during the initial TLS handshake but is not mandatory for public pages. Apache explicitly requires `SSL_CLIENT_VERIFY=SUCCESS` under `/atlas_install/protected/`.

This is intentionally different from using `SSLVerifyClient require` globally, which would make the historical public area inaccessible.

## Identity and authorization

The identity presented to the application is the full X.509 Distinguished Name (`SSL_CLIENT_S_DN`). The application looks up the DN in the `user` table and applies the stored role and privilege flags.

The Web configuration console uses two checks:

1. Apache/PHP must report a verified client certificate;
2. the matched enabled application user must have role `master`.

## Configuration storage

Runtime application configuration is stored outside the document root:

```text
/var/lib/atlas-install/config/atlas-install.env
```

In Kubernetes it resides on the `atlas-install-data` PVC. The bootstrap Secret is copied only when no managed configuration exists. Subsequent Web UI changes update the persistent managed file rather than the read-only Kubernetes Secret.

The managed file is mode `0660` and is readable/writable only by root and the application service group. It is never served by Apache.

## TLS secrets

The host certificate and private key are mounted read-only from:

```text
/run/secrets/tls/tls.crt
/run/secrets/tls/tls.key
```

They are not editable from the Web configuration console. Server identity must remain under Kubernetes/cluster administrator control.

## IGTF trust and CRLs

On startup the container initializes `/etc/grid-security/certificates` from the current IGTF accredited `classic`, `mics` and `iota` bundles when the trust store is absent/stale. `fetch-crl` refreshes certificate revocation lists. A background refresh runs periodically and gracefully reloads Apache after a successful refresh.

## Health endpoints

- `/atlas_install/healthz.php`: process/application liveness without database dependency.
- `/atlas_install/readyz.php`: tests the read-only database connection and `SELECT 1`.

Neither endpoint returns credentials or internal database errors.


## LJSF 3 schema migration policy

At container startup, the application runs an idempotent schema migration check. It may create newly introduced tables, add missing columns, and add performance indexes required by the current release. This is intentionally safe after restoring an older database dump: existing data and objects are preserved. A dedicated Kubernetes Secret `<app>-db-admin` may retain the migration account. Kubernetes Secret objects are not inherently encrypted; production clusters should enable API-server/etcd encryption at rest.
