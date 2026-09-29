# User guide

## ATLAS Installation Server 3.0.0

This guide describes how application users interact with ATLAS Installation Server. Deployment and platform administration are covered in `ADMIN-GUIDE.md`.

## Access

The service is normally exposed over HTTPS. The public area can be used without a client certificate. Protected functions require a valid X.509 client certificate trusted by the configured IGTF trust store.

Typical entry point:

```text
https://<public-hostname>/atlas_install/
```

## Public functions

Public pages provide read-oriented information including installation status, release/site information, summaries, plots and historical machine-oriented interfaces preserved for compatibility.

A certificate is not required for these paths, but all traffic remains protected by HTTPS.

## Protected functions

Paths below:

```text
/atlas_install/protected/
```

require a successfully validated client certificate. The certificate Distinguished Name is mapped to the application user stored in the database; application roles and privilege flags determine which operations are available.

Protected capabilities can include:

- installation request creation and administration;
- release management;
- site and architecture management;
- target/task configuration;
- release criticality and pinning;
- subscription management;
- user registration/approval;
- protected log and JDL inspection.

The exact controls visible to a user depend on the database role, privilege flags, account validity period and enabled state.

## Authentication and authorisation

Authentication is certificate-based. A protected request succeeds only when:

1. TLS client-certificate verification succeeds;
2. the certificate DN maps to an enabled application user;
3. the user has the role/privilege required by the requested operation.

If the browser does not present the expected certificate, check that the certificate is installed in the browser/keychain and that it is valid and not revoked.

## Server configuration console

The configuration console is available at:

```text
/atlas_install/protected/configuration.php
```

It is restricted to an enabled user with role `master` and a verified certificate.

The page can manage application/database settings including hostname, database connection identities, VO/contact settings and selected service defaults. Existing passwords are never displayed; an empty password field preserves the stored password.

TLS private keys, TLS certificates, IGTF trust anchors and CRLs are deliberately not editable from the application UI.

## Database connectivity test

The protected configuration console can test configured database connectivity without displaying stored credentials. A failed test should be reported to the service administrator together with the time of the failure and the endpoint being used; do not send passwords in support messages.

## Session and browser behaviour

Protected operations use secure session cookies and CSRF protection. If a protected form repeatedly rejects a request, refresh the page and retry from the same browser session before escalating the issue.

## Health endpoints

Operational health URLs are intended mainly for monitoring:

- `/atlas_install/healthz.php` checks application/process liveness;
- `/atlas_install/readyz.php` checks database readiness.

They do not expose database credentials or internal error details.

## Reporting a problem

Provide:

- server URL;
- date/time including timezone;
- public or protected page involved;
- operation attempted;
- visible error message;
- whether client-certificate authentication succeeded.

Never include database passwords, private keys or exported client-certificate private keys in a support report.


## Mobile navigation

On screens up to 760 px the horizontal navigation is replaced by a right-hand vertical drawer. The drawer is collapsed by default and opened with the **☰ Menu** button. Menu groups expand vertically and the drawer can be closed with the close button, tapping the backdrop, or Escape.

## Large tables and browser pagination

Large HTML tables are paginated in the browser with a default of **200 records per page**. Users can select 50, 100, 200, 500, 1000, or **All**. The same choice can be requested with the `per_page` GET parameter (`per_page=all` is accepted). This pagination applies only to browser rendering; machine-oriented endpoints and legacy scripts are not truncated. Wide tables are horizontally scrollable on mobile devices.

## Local login schema

Local authentication uses dedicated `atlas_local_*` tables. They are installed once with `scripts/init-local-auth-schema.sh` using a database account with DDL privileges. Normal web requests do not execute `CREATE TABLE`; the application writer only needs its normal DML permissions. If the schema is missing, the login page returns an administrative error and the detailed reason is written to the container log with the `[ATLAS_APP]` prefix.

## In-application documentation

The **LJSFi Documentation** link on the application home page opens `/atlas_install/documentation.php`, which contains the current user-facing operational documentation shipped with the running release.
