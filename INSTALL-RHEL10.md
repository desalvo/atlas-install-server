# ATLAS Install 3.0.0 - RHEL/EL 10 deployment

Target topology:

- backend web server: `192.168.1.144`
- database server default: `192.168.1.145`
- public hostname default: `atlas-install-el10.apps.desalvo.eu`
- Kubernetes HAProxy Ingress: TLS passthrough
- Apache on the backend terminates TLS and validates optional client certificates
- public application areas remain public
- `/atlas_install/protected` requires a successfully validated client certificate

## Installer

Run as root from the extracted deployment archive:

```bash
./install-atlas-rhel10.sh
```

First run prompts for:

- database address;
- RW/RO/broker application DB users and passwords;
- source host certificate/full-chain PEM and private-key PEM;
- public hostname.

Defaults are proposed for the known deployment values. Password entry is hidden and has no insecure first-run password default.

On later runs without options, the current general configuration is displayed before changes are finalized. Passwords are masked.

Reconfigure, with current settings proposed as defaults:

```bash
atlas-install-config --reconfigure
```

Update only the host certificate/key:

```bash
atlas-install-config --cert-only
```

Force refresh of IGTF trust anchors and CRLs:

```bash
atlas-install-config --update-igtf
```

## Secrets

The generated configuration is:

`/etc/atlas-install/atlas-install.env`

Permissions are enforced as `root:atlas-install 0640`. Apache is added only to the `atlas-install` supplementary group so PHP-FPM can read the application DB credentials. The cron service account `atlas-install` also needs these credentials for the application's scheduled PHP jobs. No DB password file is installed below `/var/www/html`.

The installed server private key is `/etc/pki/tls/private/atlas-install.key`, owned `root:root` mode `0600`.

## IGTF and CRLs

The installer installs `fetch-crl` (enabling EPEL 10 if necessary), manages IGTF trust anchors under `/etc/grid-security/certificates`, and performs an immediate CRL refresh. If the distribution package does not provide its own `fetch-crl.timer`, the installer creates `atlas-fetch-crl.timer` and refreshes every six hours.

Apache uses `SSLCARevocationCheck chain`; therefore a revoked client certificate is rejected even when its issuing CA is trusted.

The trust-anchor refresh uses the official IGTF distribution over HTTPS. A normal full installer run refreshes the anchors if the local set is missing or older than 30 days; `--update-igtf` forces it.

## TLS/client certificate access model

At TLS handshake Apache uses `SSLVerifyClient optional`: clients may connect without a certificate, preserving the historically public areas.

Only `/atlas_install/protected` requires `SSL_CLIENT_VERIFY=SUCCESS`. The public `/atlas_install/exec` tree remains public as in the original deployment; SQL hardening remains applied there.

Because Kubernetes uses TLS passthrough, the end-user certificate reaches Apache directly and no identity is trusted from proxy-supplied HTTP headers.

## Kubernetes

See `kubernetes/haproxy-ingress-tls-passthrough.yaml` for a Service + EndpointSlice pointing at `192.168.1.144:443` and an HAProxy Ingress with SSL passthrough enabled.

## Protected Web configuration console

This edition also provides `/atlas_install/protected/configuration.php`.
The page requires a verified client certificate and an enabled application user with role `master`.

To allow safe atomic updates, the installer stores `/etc/atlas-install/atlas-install.env` as `root:atlas-install` mode `0660`, and `/etc/atlas-install` as a setgid service directory. Apache is a member of the dedicated `atlas-install` group. No configuration secret is placed below `/var/www/html`.

Existing database passwords are never rendered in the browser; an empty password field means “keep the current value”. TLS private keys and IGTF trust material are deliberately not editable from the Web UI.
