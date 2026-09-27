# RHEL/EL 10 migration notes

This tree is the PHP 8.3/RHEL 10 port of ATLAS Install 3.0.0.

Use the deployment-level `install-atlas-rhel10.sh` script from the package root. The script is idempotent and installs a persistent copy as `/usr/local/sbin/atlas-install-config` for later reconfiguration or certificate-only renewal.

Key security changes:

- database passwords are stored only in `/etc/atlas-install/atlas-install.env`, outside the web document root, mode `0640`, owner `root:atlas-install`;
- host private key is stored as `/etc/pki/tls/private/atlas-install.key`, mode `0600`, owner `root:root`;
- PHP 8.3 compatibility fixes and SQL-injection hardening are included;
- `/atlas_install/protected` requires a successfully validated client certificate, while historically public paths remain public;
- IGTF trust anchors and CRLs are managed under `/etc/grid-security/certificates` and Apache performs CRL validation;
- cron jobs run as the dedicated `atlas-install` account rather than root;
- runtime writable data is outside `/var/www/html`.

See the top-level `INSTALL-RHEL10.md` for installation and reconfiguration instructions.
