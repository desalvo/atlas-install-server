# GitHub Actions CI/CD

Repository: `desalvo/atlas-install-server`

## Required Docker Hub secret

Create this GitHub Actions repository secret:

- `DOCKERHUB_TOKEN` - a Docker Hub personal access token with permission to push `desalvo/atlas-install-server`.

The Docker Hub username is intentionally fixed to `desalvo` in the workflow.

## CI workflow

`.github/workflows/ci.yml` runs on pull requests, `main`, version tags and manual dispatch.

The dependency chain is:

1. static tests;
2. Rocky Linux container build and in-image PHP lint;
3. Docker Hub publication only after both test jobs succeed.

Pull requests never authenticate to Docker Hub and never push images.

A push to `main` publishes `latest`, `edge` and a SHA tag. A tag such as `v3.0.0` publishes semantic-version tags and a SHA tag.

The release image is built for `linux/amd64` and `linux/arm64` and includes BuildKit provenance and an SBOM.

## GitHub release package

`.github/workflows/release.yml` runs for `v*` tags and creates:

```text
atlas-install-server-VERSION.tar.gz
atlas-install-server-VERSION.tar.gz.sha256
```

The files are attached to the corresponding GitHub Release.

## Recommended repository settings

Protect `main` and require these checks before merge:

- `Static tests`
- `Build and test Rocky Linux image`

Keep Actions secrets unavailable to untrusted pull requests; the provided workflow already limits Docker Hub login/push to trusted push events.

## Base image validation

The image test job builds the Dockerfile and checks `/etc/rocky-release` to ensure the resulting container is actually Rocky Linux 10 before publication.
