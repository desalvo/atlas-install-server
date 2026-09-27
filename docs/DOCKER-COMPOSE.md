# Docker Compose deployment

The published image is `desalvo/atlas-install-server` and is based on Rocky Linux 10.

## Prepare local secrets

```bash
cp .env.compose.example .env
mkdir -p secrets
cp etc/atlas-install/atlas-install.env.example secrets/atlas-install.env
cp /secure/path/fullchain.pem secrets/tls.crt
cp /secure/path/privkey.pem secrets/tls.key
chmod 700 secrets
chmod 600 secrets/tls.key secrets/atlas-install.env
chmod 644 secrets/tls.crt
$EDITOR secrets/atlas-install.env
```

The files below `secrets/` are ignored by Git. Do not commit them.

## Start

Using the published image:

```bash
docker compose pull
docker compose up -d
```

Or build locally from the repository:

```bash
docker compose build
docker compose up -d
```

By default HTTPS is exposed on host port `8443`. Change `ATLAS_HTTPS_PORT` in `.env` if needed.

## Validate

```bash
docker compose ps
docker compose logs -f atlas-install-server
curl -k https://127.0.0.1:8443/atlas_install/healthz.php
```

The public pages remain accessible without a client certificate. `/atlas_install/protected/` requires an IGTF-trusted client certificate and application authorization.

## Update image

```bash
docker compose pull
docker compose up -d --remove-orphans
```

The application configuration and IGTF trust store are persisted in named volumes. The image can therefore be replaced without losing managed configuration.
