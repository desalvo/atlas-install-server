# Local runtime secrets

This directory is intentionally excluded from Git except for this file.
For Docker Compose create these files locally:

- `tls.crt` - server certificate/full chain for the public hostname.
- `tls.key` - matching private key.
- `atlas-install.env` - application configuration/database credentials, normally copied from `etc/atlas-install/atlas-install.env.example` and edited locally.

Recommended local permissions:

```bash
chmod 700 secrets
chmod 600 secrets/tls.key secrets/atlas-install.env
chmod 644 secrets/tls.crt
```

Never commit the real files.
