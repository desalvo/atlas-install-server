# Protected Web configuration

## Access requirements

Open:

```text
https://atlas-install-el10.apps.desalvo.eu/atlas_install/protected/configuration.php
```

The browser must present a valid client certificate. The certificate DN must resolve to an enabled database user whose role is exactly `master`.

A valid certificate by itself is not enough.

## Password handling

Password fields are intentionally empty when the form is displayed. This prevents stored secrets from being reflected into HTML or copied into browser password stores.

- blank password field: retain existing value;
- non-empty password field: replace stored value.

Configuration writes are atomic. The application writes a temporary file in the same protected directory, sets restrictive permissions and then renames it over the active configuration.

## Database test

`Test database connection` tests the current form values using the RO database user and performs `SELECT 1`. Failure details are shown only at a connection level and database credentials are not logged.

## Auditing

Each successful configuration update writes an application log entry containing the authenticated certificate DN and the names of configuration keys involved. Secret values are not included in the audit log.

## Changes requiring a pod restart

Most application settings take effect on subsequent requests. Restart the pod after changing values that influence the Apache TLS endpoint, in particular the public hostname.

The Web UI deliberately does not modify TLS material. Update the `atlas-install-tls` Secret instead, then restart the Deployment:

```bash
kubectl -n atlas-install create secret tls atlas-install-tls \
  --cert=/path/to/fullchain.pem \
  --key=/path/to/privkey.pem \
  --dry-run=client -o yaml | kubectl apply -f -

kubectl -n atlas-install rollout restart deployment/atlas-install
```
