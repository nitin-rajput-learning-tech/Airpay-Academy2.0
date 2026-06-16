# local_sentientia_api — Sentientia Public API + LTI 1.3

Gap **P2.3** of the Invince/LXP competitive roadmap. Provides:

1. **Versioned public REST API (`/v1/`)** — safe, documented, tenant-scoped
   endpoints for courses, enrolments, completions and skills, plus a guarded
   write endpoint (enrolment creation). Built on Moodle's `external_api` /
   web-service framework so it inherits token auth, the REST/JSON server, and
   strict parameter/return validation.
2. **LTI 1.3 provider + consumer scaffolding** — OIDC login + launch endpoints
   with RS256 JWT signature verification, registered-claim validation, and
   one-time nonce replay protection.

## Feature flags (both DEFAULT OFF)

| Flag | Gates |
|------|-------|
| `sentientia.api.enabled` | The entire v1 REST surface |
| `sentientia.api.write.enabled` | WRITE endpoints (additional to master) |
| `sentientia.api.lti.enabled` | The LTI login/launch/JWKS endpoints |

Resolved via `\local_sentientia_platform\feature_flags`. With flags OFF the
plugin is a complete no-op — Airpay's current production behaviour is
unchanged until an admin flips a flag per customer/tenant in the Switchboard.

## REST usage

```
POST {wwwroot}/webservice/rest/server.php
  wstoken=<token>
  wsfunction=local_sentientia_api_v1_list_courses
  moodlewsrestformat=json
```

Mint a token against the pre-built **"Sentientia Public API v1"** service
(disabled by default). The token's user must hold `local/sentientia_api:read`
(or `:write`) AND belong to the tenant whose data is requested.

## Security model

Every endpoint runs `base::open_v1()` which enforces, in order:
feature flag → context/token → per-user rate limit → tenant scope. The
capability is declared per-function in `db/services.php` and re-asserted in
code. No endpoint returns cross-tenant data; email is exposed only to site
admins. WRITE follows the CLAUDE.md `[CONFIRM]` discipline via the separate
write flag.

## OpenAPI

Canonical spec at `docs/openapi-v1.yaml`; also served by
`local_sentientia_api_v1_openapi`.

## Endpoints

| Function | Method | Capability |
|----------|--------|-----------|
| `local_sentientia_api_v1_list_courses` | read | `:read` |
| `local_sentientia_api_v1_get_course` | read | `:read` |
| `local_sentientia_api_v1_list_enrolments` | read | `:read` |
| `local_sentientia_api_v1_list_completions` | read | `:read` |
| `local_sentientia_api_v1_list_skills` | read | `:read` |
| `local_sentientia_api_v1_create_enrolment` | write | `:write` |
| `local_sentientia_api_v1_openapi` | read | `:read` |

LTI endpoints: `lti/login.php`, `lti/launch.php`, `lti/jwks.php`.
