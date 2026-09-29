# LJSF 3 REST API — English guide

## Purpose

REST API v1 runs alongside the existing legacy GET/POST/PUT endpoints. New clients should prefer `/atlas_install/api/v1/`: resource-oriented URIs, JSON payloads, semantic HTTP methods and standard status codes.

Base URL:

```text
https://HOST/atlas_install/api/v1
```

## Authentication

Reads for `releases`, `sites`, `tasks`, `architectures` and `targets` are public, matching the public application views. Writes require authentication. `requests` also requires authentication for reads. `users` and `local-users` require the `master` role.

Supported mechanisms:

1. the existing X.509 client certificate;
2. an existing local Web UI session cookie;
3. HTTP Basic using a local account, over HTTPS only.

```bash
curl -u admin:PASSWORD https://HOST/atlas_install/api/v1/me
```

When TOTP is enabled for the local account:

```bash
curl -u admin:PASSWORD -H 'X-ATLAS-TOTP: 123456' \
  https://HOST/atlas_install/api/v1/me
```

## JSON and status codes

Errors use:

```json
{
  "error": {
    "status": 400,
    "code": "invalid_request",
    "message": "...",
    "request_id": "...",
    "details": {}
  }
}
```

Main statuses are `200`, `201`, `204`, `400`, `401`, `403`, `404`, `409`, `415`, and `500`.

## Resources

- `/releases`
- `/sites`
- `/requests`
- `/tasks`
- `/architectures`
- `/targets`
- `/infosys`
- `/release-subscriptions`
- `/release-status`
- `/grids`
- `/facilities`
- `/site-types`
- `/request-statuses`
- `/request-types`
- `/users` — master only
- `/local-users` — master only
- `/me`
- `/health`

### Collection, filtering and pagination

```bash
curl 'https://HOST/atlas_install/api/v1/releases?limit=100&offset=0&sort=name&order=ASC&q=24.1'
```

Any exposed field can be used as an exact filter:

```bash
curl 'https://HOST/atlas_install/api/v1/sites?tier_level=1&status=1'
```

Collection responses contain `data` plus `meta` (`total`, `limit`, `offset`, `count`). Maximum `limit` is 1000.

### One resource

```bash
curl https://HOST/atlas_install/api/v1/releases/123
```

### Create

```bash
curl -u admin:PASSWORD \
  -H 'Content-Type: application/json' \
  -d '{"name":"24.2.0","tag":"AtlasOffline_24_2_0","comments":"created via API"}' \
  https://HOST/atlas_install/api/v1/releases
```

Successful creation returns `201 Created` and a `Location` header.

### Update

```bash
curl -u admin:PASSWORD -X PATCH \
  -H 'Content-Type: application/json' \
  -d '{"obsolete":1}' \
  https://HOST/atlas_install/api/v1/releases/123
```

`PUT` is accepted with the same supplied-field update semantics to ease migration from legacy clients.

### Delete

```bash
curl -u admin:PASSWORD -X DELETE \
  https://HOST/atlas_install/api/v1/releases/123
```

Successful response: `204 No Content`.

## Local users

Password hashes are never returned. Create a local user:

```bash
curl -u admin:ADMIN_PASSWORD \
  -H 'Content-Type: application/json' \
  -d '{"username":"operator","first_name":"Op","last_name":"User","email":"operator@example.org","role":"user","enabled":1,"password":"A-strong-password"}' \
  https://HOST/atlas_install/api/v1/local-users
```

Change its password:

```bash
curl -u admin:ADMIN_PASSWORD -X PATCH \
  -H 'Content-Type: application/json' \
  -d '{"password":"Another-strong-password","must_change_password":0}' \
  https://HOST/atlas_install/api/v1/local-users/5
```

## Compatibility

Legacy endpoints under `/exec`, `/protected/exec`, and existing GET/POST pages remain unchanged. REST v1 is a parallel, versioned API. A future v2 will not silently change the v1 contract; deprecation will be documented first.
