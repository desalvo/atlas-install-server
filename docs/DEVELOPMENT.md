# Development guide

## Supported development baseline

ATLAS Installation Server 3.0.0 targets Rocky Linux 10-compatible userspace and PHP 8.3+. The canonical repository is `desalvo/atlas-install-server`.

## Source version

`VERSION` is the canonical project release number. The versioned application source directory for this release is:

```text
var/www/html/atlas_install-3.0.0
```

The `var/www/html/atlas_install` symlink exists for compatibility in source packages, while the container recreates the runtime compatibility symlink explicitly.

## Local validation

Run before every commit:

```bash
./tests/ci-static.sh
```

When Docker or Podman is available:

```bash
./container/build-image.sh desalvo/atlas-install-server:test
```

For Docker Compose syntax/model validation, provide temporary certificate/bootstrap paths as described in `DOCKER-COMPOSE.md` and run:

```bash
docker compose config -q
```

## Code-change rules

- Do not add secrets, private keys or runtime `.env` files.
- Treat every HTTP parameter as untrusted input.
- Prefer parameterised SQL for new code.
- Preserve public/protected endpoint behaviour unless the change explicitly revises the external contract.
- Do not weaken certificate validation or role checks to solve deployment issues.
- Keep mutable runtime data outside `var/www/html`.
- Keep `Dockerfile` and `container/Containerfile` functionally identical.

## Adding dependencies

Container dependencies belong in both OCI build definitions. Native-host dependencies belong in `install-atlas-rhel10.sh`. Any dependency added to one deployment model must be assessed for the others or explicitly documented as deployment-specific.

## Tests

The current CI validates:

- shell syntax;
- PHP syntax;
- required source-tree presence;
- Git/Docker ignore rules for application source;
- secret hygiene;
- known unsafe direct HTTP-input SQL patterns;
- Kubernetes/Compose YAML;
- Rocky Linux base identity;
- PHP lint inside the built image.

Future behavioural tests should be added under `tests/` and must be runnable non-interactively in GitHub Actions.

## Branch and review model

Use feature/fix branches and pull requests into `main`. Recommended branch protection requires both `Static tests` and `Build and test Rocky Linux image` before merge.

## Release changes

A version change is not complete until the versioned source directory, `VERSION`, application version variables, OCI metadata, tests and documentation agree. Use `RELEASE-MANAGEMENT.md` as the release checklist.
