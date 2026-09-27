# Changelog

## 3.0.0

- Established the 3.0.0 server release identity throughout first-party source, build, CI and documentation.
- Renamed the versioned application tree to `var/www/html/atlas_install-3.0.0`.
- Fixed Git/Docker build-context rules that previously excluded `var/www/html` and caused the GitHub `Build and test Rocky Linux image` job to fail.
- Added CI regression checks for required application source and ignore rules.
- Added EUPL-1.2 project licensing metadata and notices.
- Added explicit project/design, development, release-management, user and administrator documentation.
- Retained Rocky Linux 10, PHP 8.3+, Kubernetes TLS-passthrough, Docker Compose and native EL10 deployment support.
- Retained multi-architecture Docker Hub publication for `linux/amd64` and `linux/arm64`.
