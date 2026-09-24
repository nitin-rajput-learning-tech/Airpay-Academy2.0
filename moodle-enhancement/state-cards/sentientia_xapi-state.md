# State Card — local_sentientia_xapi
**Plugin:** `local_sentientia_xapi`
**Session:** P1.4 — xAPI / cmi5 + LRS (initial build)
**Date:** 2026-06-16
**Branch:** `claude/gap-xapi`
**Author:** Claude (Sonnet 4.6)
**Status:** COMPLETE — ready for Nitin review

> **2026-09-03 — 1.0.1 (2026090300), UAT Stage A finding:** the `local_sentientia_xapi_stmts.stored`
> column broke the fresh install on UAT (MySQL 8.4): `STORED` is a MySQL 8 reserved word (generated
> columns), MariaDB accepted it unquoted so local + the P4 static pass never saw it. Renamed to
> `timestored` (field + index `idx_timestored`, store/statements/index/privacy/purge task/tests, en+hi
> lang key); upgrade step renames the field on existing installs (verified on local XAMPP). The xAPI
> JSON response key `stored` is unchanged. Repo-wide scan of all 49 `install.xml` files against the
> MySQL 8.4 reserved-word list found no other hits.

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

## 2026-09-24 - Privacy provider fix (erasure audit)

**Scope bug.** The single-user erasure nulled `actorid`, then redacted the actor JSON `WHERE actorid IS NULL`. That is every statement in the LRS whose actor never resolved to a local user, across all tenants. The bulk path never redacted at all. Both now redact the subject's statements first, then unlink them. New `anonymise_data_for_user()` for the DPDP flow: it redacts the actor, keeps `actorid` and the cmi5 attempt rows, and clears their launch token and session id.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Actor resolution: this site's homePage, the client's tenant

**Defect (minor, confirmed).** The privacy provider redacts and exports by `actorid`, but `store::resolve_actor_userid()` could set a wrong one on statements that external LRS clients post. It mapped an account IFI to local user `name` whatever the `homePage` said, and never checked that the user belonged to the posting client's tenant. So erasing user U could overwrite the actor JSON of a statement about somebody else: a partner-LMS learner who carries U's id on that system, or a user in another tenant who shares U's mailbox.

**Fix (root cause, `classes/lrs/store.php`).**
- An account IFI resolves only when `rtrim(homePage,'/') === rtrim($CFG->wwwroot,'/')`. That is the homePage `statement::build_actor()` emits, so our own observer statements still round-trip.
- `resolve_actor_userid(array $actor, int $costcenterid)` takes the new parameter with no default. `lrs/statements.php` passes the authenticated client's `costcenterid`. When it is > 0, the resolved user (by account, mbox or openid) must sit inside `/<costcenterid>` via `tenant::path_descendant_filter()` on `user.open_path`. Anyone else, or an empty path, gives null. When `local_sentientia_platform` is absent it also gives null. `0` (platform credential) keeps resolving any user.
- An email or idnumber that matches more than one live user now resolves to nobody. Before, it went to whichever row the DB returned first.

The provider's erasure semantics are unchanged; only a docblock was added. Tests are in `tests/lrs_store_test.php`: account tests now use `$CFG->wwwroot`, and there is a build_actor round-trip test. Negative tests cover foreign/prefix/sub-path/`redacted` homePages, the cross-tenant client (including the `/1` vs `/177` prefix boundary and empty/NULL paths), and duplicate emails. An end-to-end test checks that `anonymise_data_for_user()` leaves refused statements untouched. These tests are written but not run (the shared test DB is being rebuilt). Class change only: no version bump. Rows stored before this fix are not re-resolved. The LRS endpoint flag is default OFF, so none should exist.
