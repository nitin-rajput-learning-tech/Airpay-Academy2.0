# Visual / test evidence — 2026-05-27

## Wave D1 P3 — Cutover smoke-test first live run

JUnit XML from the first-ever live execution of `scripts/cutover-smoke-test.py`
(see `moodle-enhancement/PROJECT-STATE.md` §"Wave D1 P3" for the full writeup).

| File | Target | Result |
|------|--------|--------|
| `cutover-smoke-localhost8080.xml` | `http://localhost:8080/moodle` | 8 pass / 0 fail / 0 skip |
| `cutover-smoke-localhost8081.xml` | `http://localhost:8081/moodle` | 8 pass / 0 fail / 0 skip |

Both targets pass all 8 scenarios after remediation:

1. `test_login_page_renders`
2. `test_dashboard_route_responds`
3. `test_course_catalog_api`
4. `test_scorm_endpoint_responds`
5. `test_bizlms_tenant_switching` — `costcenterid → {1: 2, 77: 1, 177: 1}`
6. `test_dark_mode_assets`
7. `test_navbar_footer_rendering` — airpayux theme rendered on login surface
8. `test_rest_api_health`

### What changed to get here
- **Real bug fixed:** `local/airpay_lifecycle/db/messages.php` used the
  Moodle-4 `MESSAGE_DEFAULT_LOGGEDIN/OFF` constants (removed in Moodle 5) →
  install crash. Now `MESSAGE_DEFAULT_ENABLED`.
- **Test 5 rewritten:** old `profile_field_costcenterid` criteria key is
  invalid for `core_user_get_users` (PARAM_ALPHA) — would have failed on
  production. Now reads the `costcenterid` value from returned `customfields`.
- **Test 7 reframed:** the airpayux `login` layout has no `<nav>`/`<footer>`
  (`nonavbar=true`); the old assertion was unsatisfiable anonymously. Now
  verifies the airpayux theme rendered on the login surface.
- **Retry logic added** for transport-level failures (server mid-restart).

> These XML files were produced against an ephemeral Moodle 4.5.10 scaffold
> (MariaDB + PHP built-in server) stood up inside the chip container. The
> `sitename` "Airpay Academy Smoke" reflects that scaffold. The canonical
> live dry-run evidence (against staging) is captured separately per the
> runbook §3.2.
