# Contributing

The project targets Rocky Linux 10, PHP 8.3+ and Apache HTTP Server with TLS passthrough deployments.

Before opening a pull request run:

```bash
./tests/ci-static.sh
```

If Docker is available also run:

```bash
docker build -t desalvo/atlas-install-server:test .
docker compose config -q
```

Do not commit database passwords, private keys, client certificates, host certificates, Kubernetes Secret material, or generated runtime configuration.

## Project documentation

Changes that affect architecture, security boundaries, deployment contracts, release behaviour or operational requirements must update the relevant files under `docs/`, especially `PROJECT-DESIGN.md` and `RELEASE-MANAGEMENT.md`.

## Licence

Contributions to project-owned code are accepted for distribution under EUPL-1.2. Do not remove or rewrite third-party licence notices.
