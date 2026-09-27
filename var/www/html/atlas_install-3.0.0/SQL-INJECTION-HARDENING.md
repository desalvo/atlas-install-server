# SQL injection hardening

Target: ATLAS Installation Server 3.0.0, RHEL 10 / PHP 8.3+

This hardening pass removes the HTTP-input-to-SQL concatenation paths found by static review across the public, `exec`, `protected`, and `protected/exec` PHP endpoints.

## Main changes

- Added centralized SQL value helpers in `db.php`: `db_quote`, `db_int`, `db_int_list`, `db_identifier`, `db_like_contains`, and `db_order_by`.
- Converted request-derived string values to connection-aware quoting and numeric values to strict integer validation.
- Restricted dynamic SQL identifiers and `ORDER BY` values to validated identifiers/whitelists.
- Hardened bulk `IN (...)` operations by validating every member as an integer.
- Hardened certificate-derived DN/CN lookups even though they are supplied by the TLS layer.
- Hardened indirect flows where request values were first copied to local variables before query construction.
- Hardened REST-style endpoints under `protected/exec` and legacy alternate endpoints that remain web-addressable.
- Hardened several second-order metadata lookups where database metadata influences SQL construction.
- Removed remaining query disclosure paths encountered during this pass.

## Validation performed

- PHP syntax check over all 106 PHP files: 0 failures.
- Static scan for direct HTTP superglobal interpolation into SQL.
- Secondary taint-style scan for local variables assigned from HTTP superglobals and subsequently used in SQL.
- Manual review of residual scanner matches. Residual matches are boolean feature switches that select constant SQL fragments and do not interpolate attacker-controlled data.

## Deployment note

Static analysis cannot prove application-level correctness. Before production deployment, run integration tests against the target MariaDB/MySQL schema, especially request submission, site/release administration, REST endpoints, pagination, subscription management, and log retrieval.
