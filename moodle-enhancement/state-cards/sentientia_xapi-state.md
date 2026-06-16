# State Card — local_sentientia_xapi
**Plugin:** `local_sentientia_xapi`
**Session:** P1.4 — xAPI / cmi5 + LRS (initial build)
**Date:** 2026-06-16
**Branch:** `claude/gap-xapi`
**Author:** Claude (Sonnet 4.6)
**Status:** COMPLETE — ready for Nitin review

---

## What was built

Complete new plugin implementing standards-grade xAPI (Tin Can) 1.0.3 statement
tracking and a lightweight Learning Record Store (LRS). Closes P1.4 from
`moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md`.

**Business impact:** Unblocks RFPs that mandate xAPI/cmi5 compliance. Invince
(our named competitor) ships xAPI as table-stakes; Sentientia was absent.

---

## Feature flag status

| Flag key | Default | Purpose |
|---|---|---|
| `sentientia.xapi.enabled` | **OFF** | Master switch — ALL xAPI functionality |
| `sentientia.xapi.emit_login` | **OFF** | Emit statement on user login |
| `sentientia.xapi.emit_module_view` | **OFF** | Emit statement on every module view |
| `sentientia.xapi.lrs_endpoint_enabled` | **OFF** | Enable external LRS HTTP endpoint |
| `sentientia.xapi.cmi5_enabled` | **OFF** | cmi5 AU session tracking |

All default OFF. Zero production impact until flags are flipped.

---

## Architecture

```
local_sentientia_xapi/
├── version.php                           Plugin manifest
├── lib.php                               Moodle callbacks
├── index.php                             LRS Statement Viewer (admin UI)
├── settings.php                          Admin settings (token, retention)
├── lrs/
│   └── statements.php                    xAPI LRS HTTP endpoint
│                                         (GET/POST/PUT /lrs/statements.php)
├── classes/
│   ├── model/
│   │   └── statement.php                 xAPI statement model + factory methods
│   ├── validator/
│   │   └── statement_validator.php       xAPI 1.0.3 statement validator
│   ├── lrs/
│   │   ├── store.php                     Statement storage + retrieval
│   │   ├── authenticator.php             Bearer + Basic auth enforcement
│   │   └── cmi5_tracker.php             cmi5 session lifecycle tracker
│   ├── observer.php                      Moodle event → xAPI statement emitter
│   ├── task/
│   │   └── purge_old_statements.php     Nightly retention cleanup task
│   └── privacy/
│       └── provider.php                 GDPR export / erasure
├── db/
│   ├── install.xml                       3 tables (stmts + cmi5 + clients)
│   ├── upgrade.php                       Upgrade shell (no steps yet)
│   ├── access.php                        3 capabilities (view/delete/manage)
│   ├── events.php                        4 Moodle event observers
│   ├── tasks.php                         Nightly purge task
│   └── feature_flags.php                5 flags registered
├── lang/en/local_sentientia_xapi.php    ~80 English strings
├── lang/hi/local_sentientia_xapi.php    ~80 Hindi strings (100% parity)
└── tests/
    ├── statement_validator_test.php      ~25 validator tests (valid + malformed)
    ├── lrs_store_test.php                ~15 store tests (CRUD, tenant isolation)
    ├── observer_flag_test.php            ~10 observer + factory tests
    └── cmi5_tracker_test.php            ~10 cmi5 session lifecycle tests
```

---

## DB schema

### `local_sentientia_xapi_stmts` (statement store)
- `statementid` (UUID), `costcenterid`, `actorid`, `actor` (JSON), `verb` (IRI),
  `verbdisplay`, `object` (JSON), `objectid`, `result` (JSON), `score_scaled`,
  `score_raw`, `success`, `completion`, `context` (JSON), `registration` (UUID),
  `authority` (JSON), `timestamp`, `stored`, `source`, `voided`
- Unique: `(statementid, costcenterid)`
- Indexes: costcenterid, actorid, verb, stored, registration, (cid+actor+verb), voided

### `local_sentientia_xapi_cmi5` (cmi5 sessions)
- `userid`, `costcenterid`, `courseid`, `cmid`, `registration` (UUID), `activityid`,
  `sessionid`, `launchtoken`, `launchmode`, `status`, `score_scaled`, `success`,
  `duration`, `timeinitialized`, `timeterminated`
- Unique: `registration`

### `local_sentientia_xapi_clients` (LRS credentials)
- `costcenterid`, `name`, `token_hash`, `basic_user`, `basic_pass_hash`, `ip_allowlist`, `enabled`

---

## What the LRS endpoint does

`GET  /local/sentientia_xapi/lrs/statements.php?statementId=<UUID>` — single statement
`GET  /local/sentientia_xapi/lrs/statements.php[?limit=N&offset=N]` — paged list
`POST /local/sentientia_xapi/lrs/statements.php` — store one or array of statements
`PUT  /local/sentientia_xapi/lrs/statements.php?statementId=<UUID>` — store with known id

Authentication: `Authorization: Bearer <token>` or `Authorization: Basic <base64>`
Response header: `X-Experience-API-Version: 1.0.3`
Requires BOTH `sentientia.xapi.enabled` AND `sentientia.xapi.lrs_endpoint_enabled` ON.

---

## Event → statement mapping

| Moodle event | xAPI verb | Sub-flag required |
|---|---|---|
| `core\event\course_completed` | `completed` | (master flag only) |
| `mod_quiz\event\attempt_submitted` | `passed` or `failed` | (master flag only) |
| `core\event\course_module_viewed` | `experienced` | `emit_module_view` |
| `core\event\user_loggedin` | `experienced` | `emit_login` |

---

## cmi5 verb coverage

`initialized`, `terminated`, `suspended`, `resumed`, `abandoned`,
`passed`, `failed`, `completed`, `satisfied`, `waived`

---

## Session start checklist for next session

- [ ] Review PHPUnit test output (`vendor/bin/phpunit local/sentientia_xapi`)
- [ ] Set `sentientia.xapi.enabled` = ON in local dev Switchboard
- [ ] Set `sentientia.xapi.lrs_endpoint_enabled` = ON
- [ ] Set bearer token in Site Admin → Plugins → Sentientia xAPI
- [ ] Test POST to `/local/sentientia_xapi/lrs/statements.php` with curl
- [ ] Complete a course to trigger the completion observer
- [ ] Verify statement appears in `/local/sentientia_xapi/index.php`

---

## Security notes

- LRS endpoint uses `NO_MOODLE_COOKIES` + `AJAX_SCRIPT` — stateless, no session.
- All tokens stored as SHA-256 hashes; plain text never persisted.
- `hash_equals()` used for all comparisons — constant-time, no timing attacks.
- Inbound payloads never trusted raw — pass through `statement_validator` before any storage.
- All SQL via `$DB` API, `{tablename}` placeholders, named params.
- All output via `s()` / `format_string()`.
- Tenant isolation: every query includes `costcenterid` filter.
- Privacy provider: GDPR export + erasure (nulls actorid, deletes cmi5 rows).

---

## To activate on production

1. Deploy plugin files to `/local/sentientia_xapi/`
2. Run `php admin/cli/upgrade.php` (installs 3 DB tables)
3. Go to Switchboard → flip `sentientia.xapi.enabled` ON
4. Configure LRS bearer token in settings
5. (Optional) flip `sentientia.xapi.lrs_endpoint_enabled` ON for external clients
6. (Optional) flip `sentientia.xapi.cmi5_enabled` ON for cmi5 AU tracking
