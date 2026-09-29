# LJSF 3 — User guide

LJSF 3 (ATLAS Installation System) manages software releases, sites, architectures, InfoSys entries, targets, tasks and installation requests.

## Access
Public read-only areas do not require authentication. Protected functions require a valid client certificate mapped to a role or an enabled local account. The initial local account is `admin` with the `master` role; its initial password is defined by bootstrap and may require a change at first login. TOTP is supported for local accounts.

## Navigation
Desktop uses horizontal navigation. Mobile uses a right-side vertical drawer, collapsed by default, with one item per row and expandable sections. Language follows `Accept-Language` (`it` => Italian, otherwise English) and can be changed using the top-right selector.

## Tables
HTML tables with more than 200 records are paginated in the browser. Available sizes are 50, 100, 200, 500, 1000 or All. Pagination does not change APIs, scripts or machine-oriented queries. Wide tables are horizontally scrollable on mobile.

## Charts and reports
Charts are rendered locally as SVG/HTML when requested. No external chart-rendering service is used. Maintenance CronJobs only build historical summary/cache data.

## Authorization
Access-denied pages retain the normal header and menu and provide a local-login link plus guidance when an appropriate client certificate is required.

### `list.php` tables on phones

On phones, `list.php` results are rendered as compact cards regardless of screen orientation. A collapsed card always shows **Num**, **Release number**, **Site name**, and **Release arch**. Tap the card or **Details** to reveal the remaining fields; tap again to collapse them. Technical controls and hidden fields required by the page remain in the DOM but are not displayed as record data.
