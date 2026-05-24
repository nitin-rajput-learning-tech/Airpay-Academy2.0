# State Card — `local_airpay_lifecycle`

**Component:** `local_airpay_lifecycle`
**Status:** Skeleton / placeholder — no version.php, minimal code
**Maturity:** N/A (skeleton)
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Reserved namespace for the planned course-lifecycle automation plugin.
Future-state ownership: automatic course archive on N-month-no-activity,
auto-publish on review-cycle completion, auto-deprecate on
replacement-course flag.

Today: skeleton only.

## DB tables

None — schema not designed yet.

## Capabilities

None.

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
