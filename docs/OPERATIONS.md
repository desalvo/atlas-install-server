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
