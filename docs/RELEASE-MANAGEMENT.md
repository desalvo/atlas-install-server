# Release management

## Release identity

Current release: **3.0.0**

Canonical Git tag:

```text
v3.0.0
```

Canonical Docker Hub repository:

```text
desalvo/atlas-install-server
```

## Required repository secret

GitHub Actions requires `DOCKERHUB_TOKEN`, containing a Docker Hub access token able to push to `desalvo/atlas-install-server`.

## Pre-release checks

From a clean checkout:

```bash
./tests/ci-static.sh
```

If Docker is available:

```bash
docker build --no-cache -t desalvo/atlas-install-server:3.0.0 .
docker run --rm --entrypoint bash desalvo/atlas-install-server:3.0.0 -lc \
  'test -d /var/www/html/atlas_install-3.0.0 && php -v'
```

Verify that `Dockerfile` and `container/Containerfile` have not diverged:

```bash
cmp Dockerfile container/Containerfile
```

## GitHub Actions behaviour

A push to `main` runs validation and, after successful tests, publishes `latest`, `edge` and a SHA tag.

A version tag such as `v3.0.0` additionally publishes semantic-version Docker tags and creates a GitHub source release archive.

The release Docker manifest contains:

```text
linux/amd64
linux/arm64
```

## Tagging 3.0.0

After the `main` workflow is green:

```bash
git tag -a v3.0.0 -m "ATLAS Installation Server 3.0.0"
git push origin v3.0.0
```

## Post-release verification

Inspect the published manifest:

```bash
docker buildx imagetools inspect desalvo/atlas-install-server:3.0.0
```

Verify both target platforms are present and record the immutable manifest digest used by production deployments.

## Rollback

Production should be pinned to an immutable version/digest. Rollback consists of restoring the previously approved image digest and, where necessary, the corresponding application/database backup. Do not move historical version tags to new digests.
