# Security model

## Trust boundaries

### HAProxy Ingress

HAProxy Ingress is a TCP/SNI routing layer for this hostname. It must not terminate TLS for the ATLAS Installation endpoint. Otherwise Apache would no longer receive the original client certificate.

### Apache

Apache is the mTLS policy enforcement point. It requests client certificates globally but requires successful verification only under `/atlas_install/protected/`.

### Application

The application repeats certificate checks for protected requests and maps the complete DN to a database user. The configuration console requires role `master`.

### Database

The Web server is not a database administrator. Use minimum-privilege application accounts and restrict their source network/host.

## Secret handling

Secrets are prohibited from:

- source code;
- the OCI image layers;
- the Apache document root;
- Kubernetes ConfigMaps;
- HTML responses;
- application audit logs.

The runtime configuration file containing DB passwords lives outside the document root and has service-only permissions. Kubernetes TLS private keys remain mounted read-only from a Secret.

## Web configuration hardening

The configuration console uses:

- verified client certificate;
- `master` role authorization;
- secure/HTTP-only/SameSite session cookie;
- CSRF token;
- same-origin state-changing request guard;
- server-side input validation;
- atomic file replacement;
- hidden existing password values;
- no TLS key or CA upload capability.

## SQL security

The EL10 codebase includes systematic input quoting/type validation and prepared-statement support. Continue to treat any new database query as untrusted-input-sensitive and prefer prepared statements for new development.

## Container security

The Kubernetes example:

- uses an immutable application image;
- runs TLS on unprivileged port 8443;
- disables privilege escalation;
- drops Linux capabilities and adds only those required by the traditional Apache privilege-drop model;
- uses `RuntimeDefault` seccomp;
- mounts TLS Secret read-only;
- keeps writable application data on explicit volumes.

Further hardening can move Apache/PHP-FPM to fully non-root processes after validating all historical file operations.

## IGTF trust-anchor and CRL refresh policy

Apache is configured with `SSLCARevocationCheck chain`. The container automatically checks IGTF trust anchors and refreshes CRLs every six hours by default. Trust-anchor bundles are refreshed when older than 24 hours. The updater downloads into staging, merges the accredited classic/MICS/IOTA trust directories, runs `openssl rehash`, validates the resulting hash entries, and only then updates the active directory. Existing CRLs are protected during trust-anchor replacement and `fetch-crl` refreshes them afterwards.

A failed periodic download or rehash does not replace the active trust store. Monitor repeated updater/fetch-crl failures and certificate-expiration alerts; stale/missing revocation material can affect client-certificate acceptance.
