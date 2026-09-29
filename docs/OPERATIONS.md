# Operations guide

## Logs

Container Apache access/error logs are emitted to stdout/stderr:

```bash
kubectl -n atlas-install logs -f deployment/atlas-install
```

PHP errors flow through the container error stream and must not contain plaintext database passwords.

## Readiness problems

Check:

```bash
kubectl -n atlas-install get pods
kubectl -n atlas-install describe pod -l app=atlas-install
kubectl -n atlas-install logs deployment/atlas-install
```

A pod can be alive but not ready when the RO database account cannot connect to the configured database.

## Host certificate renewal

Replace the Kubernetes TLS Secret, then restart the pod so Apache re-reads the key pair:

```bash
kubectl -n atlas-install create secret tls atlas-install-tls \
  --cert=/new/fullchain.pem \
  --key=/new/privkey.pem \
  --dry-run=client -o yaml | kubectl apply -f -

kubectl -n atlas-install rollout restart deployment/atlas-install
```

The container validates that certificate and private key match and that the certificate covers the configured public hostname before starting Apache.

## IGTF trust anchors and CRLs

The container refreshes IGTF trust anchors when absent/stale and calls `fetch-crl`. It repeats CRL refresh periodically (default six hours). To force an update in a running pod:

```bash
kubectl -n atlas-install exec deployment/atlas-install -- \
  env ATLAS_IGTF_FORCE=1 /usr/local/sbin/atlas-update-igtf
```

Then reload Apache or restart the pod.

## Configuration backup

Back up the PVC containing `/var/lib/atlas-install`. The configuration file contains DB passwords and must be encrypted at rest in any backup system.

Never include the configuration file in public support bundles.

## Database backup

Database backup strategy depends on the selected engine. Use engine-native consistent backup tools and test restores. The Web server should not receive database-administration privileges solely to perform backups.

## Application upgrade

1. Build/publish a new immutable image tag.
2. Back up database and persistent application data.
3. Update the Deployment image.
4. Observe readiness and application logs.
5. Test one public page and one protected workflow.

Do not reuse a mutable `latest` tag for production change control.

## Native host deployment

The native installer remains available as `install-atlas-rhel10.sh`. It stores configuration outside the document root and supports certificate-only updates. Because the new Web UI can modify application configuration, the native configuration directory/file is group-writable only by the dedicated service group that includes Apache.

## IGTF automatic refresh

The running container refreshes IGTF trust material automatically. Defaults are a six-hour check interval and a 24-hour maximum age for downloaded trust-anchor bundles. To force an immediate refresh inside a pod:

```bash
kubectl -n atlas-install exec deployment/atlas-install -- \
  env ATLAS_IGTF_FORCE=1 /usr/local/sbin/atlas-update-igtf
```

A successful periodic refresh triggers an Apache graceful reload. If a remote IGTF endpoint is temporarily unavailable, the previous validated trust store remains active.


## Initialize local authentication schema

The local authentication schema is intentionally not created by the runtime `dbwriter` account. Run once from the extracted release directory on a trusted administrative host that has the MariaDB client and network access to the database:

```bash
ATLAS_SCHEMA_ADMIN_USER=root ./scripts/init-local-auth-schema.sh
```

The script reads database host/name/TLS settings from `atlas-install.env` and asks interactively for the administrative database password. Passwords are not stored. After schema creation, the application creates/updates the bootstrap `admin` record using normal DML.

## r19 automatic schema bootstrap and navigation

- `ATLAS_DB_AUTO_INIT=1` (default) makes the container check the configured application database at startup. If `atlas_install_panda` is absent, the bundled default schema is installed using the configured RW database account. Existing application databases are never replaced or reset.
- If only the local-authentication tables are missing, they are created automatically in the existing application database.
- The RW account therefore needs the relevant `CREATE` privileges for automatic initialization. If it does not, startup continues and the exact failure is written to Kubernetes logs with the `[atlas-db-bootstrap]` prefix.
- Access-denied/error pages render the same full navigation menu as ordinary pages.
- The mobile drawer uses descriptive, section-specific menu labels rather than generic `Definition/Update/Removal` entries.

## Startup diagnostics

The serving container logs a numbered startup sequence covering configuration synchronization, local-auth key initialization, HTTPS validation, database/schema bootstrap, IGTF trust restoration and the initial `fetch-crl` refresh, Apache configuration validation, PHP-FPM and Apache startup. Search the pod logs for `[atlas-container]`, `[atlas-db-bootstrap]` and `[ATLAS_APP]`.

## Charts and maintenance

The Web application renders charts locally as SVG/HTML on demand. Maintenance CronJobs do not call external chart services and do not create chart images; they refresh historical database summaries and compact JSON cache data that the browser pages can read dynamically.


## Application errors in Kubernetes logs
PHP warnings, exceptions, fatal errors, database errors, rejected same-origin requests and completed HTTP 5xx responses are written to the container standard error with the `[ATLAS_APP]` prefix and request ID. Use `kubectl logs -n <namespace> deploy/<deployment> -f` to inspect them.
