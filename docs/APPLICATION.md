# Server functional documentation

## Purpose

ATLAS Installation System (historically LJSFi) coordinates software installation and validation across ATLAS sites. The server stores release definitions, sites, targets, installation requests, installation jobs and user privileges in a relational database and exposes both interactive Web pages and machine-oriented endpoints.

## Public area

The public part of the site is intentionally available without a client certificate. It provides read-oriented status and reporting functionality such as:

- home/status dashboard;
- installation status search;
- installation summaries;
- release and site information;
- usage and status plots;
- selected `/exec/` interfaces preserved for historical clients.

Although these paths do not require a certificate, they remain subject to input validation, SQL hardening, TLS transport and server-side security headers.

## Protected area

All paths below:

```text
/atlas_install/protected/
```

require a client X.509 certificate that can be validated against the configured IGTF trust anchors and is not revoked according to the installed CRLs.

The application then maps the certificate DN to the database user and applies roles/privileges. Existing functions include:

- user registration and approval workflows;
- installation request creation/administration;
- release management;
- site management;
- architecture, target and task definition;
- release criticality management;
- release/site parameters;
- subscriptions;
- release pinning;
- administrative status changes;
- log/JDL inspection where permitted.

## Roles and privilege flags

The historical database supports named roles such as `admin` and `master` together with fine-grained flags including:

- `priv_view`;
- `priv_insert`;
- `priv_update`;
- `priv_pin`;
- `priv_relsub`;
- `priv_critical`;
- validity start/end dates;
- enabled/disabled state.

Role/privilege decisions remain in application logic to preserve compatibility with the existing schema and workflows.

## Server configuration console

The new page:

```text
/atlas_install/protected/configuration.php
```

is available only to an enabled user with role `master` and a verified certificate. It can manage:

- public hostname;
- database server and database name;
- RW, RO and broker database users/passwords;
- VO name;
- service sender address and contacts;
- default information system;
- activity period.

Passwords are never displayed. Leaving a password field blank preserves the currently stored password.

The console also provides a database connectivity test.

For security reasons it does **not** upload/replace:

- the HTTPS private key;
- the HTTPS certificate;
- IGTF trust anchors;
- CRLs.

Those items are infrastructure trust material and are managed by Kubernetes/host administrators.

## Configuration reload behavior

Database and application settings saved through the Web console are picked up by subsequent PHP requests.

A change to the public hostname may require a pod restart because Apache's virtual-host configuration is rendered at container startup. TLS Secret updates also require a restart/reload unless your deployment automation restarts the pod.

## Database access model

The recommended database server is `192.168.1.145` with separate accounts:

- read-only account: `SELECT` only;
- read/write account: only `SELECT`, `INSERT`, `UPDATE`, `DELETE` required by the application;
- broker account: separate credentials if the deployment uses it, otherwise it may intentionally match the RW identity.

Do not grant `FILE`, `CREATE`, `ALTER`, `DROP`, `SUPER` or `GRANT OPTION` to Web application accounts.

## Generated data and maintenance

The server writes operational files below `/var/lib/atlas-install` and caches below `/var/cache/atlas-install`.

Historical scheduled activities include:

- hourly plot generation (`create_ljsfi_plots.php`);
- daily log archival/cleanup.

Kubernetes CronJob examples are included in `kubernetes/maintenance-cronjobs.yaml`.
