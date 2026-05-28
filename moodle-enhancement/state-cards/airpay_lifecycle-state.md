# State Card — `local_airpay_lifecycle`

**Component:** `local_airpay_lifecycle`
**Status:** Live observer + compliance-check cron — not just a skeleton
**Maturity:** BETA (no formal stamp yet — see version.php)
**Last refreshed:** 2026-05-28 (B20 stabilization sweep — F-073/F-092 closure)

---

## Mission

Course-lifecycle automation plugin. Observes events on courses,
enrolments, and completions; runs a daily compliance-check cron task
that flags overdue mandatory training; emits notification messages
via the Moodle messages API.

Earlier state cards described this plugin as a skeleton. **It is
not** — the runtime (db/events.php + db/tasks.php + db/messages.php
+ classes/observer.php + classes/task/compliance_check.php) all exist
in deployed xampp. They were missing from the workspace mirror until
the F-092 back-port on 2026-05-28 (commit `e32473e58`).

## DB tables

None — the plugin uses Moodle's standard event + scheduled-task
infrastructure (no plugin-owned tables).

## Capabilities

None directly. Compliance-check reads `local/airpay_compliance:view`
indirectly via the airpay_compliance_report dependency.

## Wired surfaces

- `db/events.php` — subscribes to:
  - `\core\event\course_completed`
  - `\core\event\user_enrolment_created`
  - `\core\event\user_enrolment_deleted`
- `db/tasks.php` — registers `\local_airpay_lifecycle\task\compliance_check`
  to run daily at 02:00 IST.
- `db/messages.php` — declares the `compliance_overdue` and
  `compliance_due_soon` message providers.
- `classes/observer.php` — handles the 3 subscribed events.
- `classes/task/compliance_check.php` — runs the daily compliance scan.
- `classes/privacy/provider.php` — privacy provider (already in workspace).

## Stabilization notes

- F-092 (workspace drift) — RESOLVED 2026-05-28 by back-porting
  `version.php`, `db/`, `classes/observer.php`,
  `classes/task/compliance_check.php` from deployed.
- F-073 (state-card stale) — RESOLVED 2026-05-28 by this refresh.

## Open follow-ups

- Add explicit `MATURITY_BETA` stamp in `version.php`.
- Add PHPUnit tests for observer + task (no test file present today).
- Document the lifecycle event flow in an ADR (currently implicit).

## Feature flags

None registered.

## Key files

```
local/airpay_lifecycle/
├── README.md
├── classes/
│   └── privacy/                                   GDPR / DPDP stub (empty provider)
└── lang/                                          (en stub)
```

No `version.php`, no `db/`, no `tests/`. Treated as a reserved
directory for future scope.

## Tests

None.

## Open items

- [ ] **Design decision:** is this plugin still in scope? If so, design
      schema + state machine. If not, delete the directory.
- [ ] If kept: minimum viable scope — auto-archive courses with
      no enrolment activity for N months
- [ ] If kept: hook into `local_airpay_emails` for archive-warning
      emails (N days before archive)
- [ ] If kept: per-tenant archive policy (today: no policy at all)

## State card created — 2026-05-24

Initial state card. Plugin is a skeleton placeholder — surfaced in
the P1 audit so the design decision (keep or delete) is on the
backlog.
