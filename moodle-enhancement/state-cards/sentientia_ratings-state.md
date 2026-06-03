# State Card — `local_sentientia_ratings`

**Component:** `local_sentientia_ratings`  (renamed from `local_airpay_ratings`, ADR-022 batch-1)
**Version:** `2026060302` / `1.1.2`  (+ADR-022 rename)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Per-course star ratings.
**Last refreshed:** 2026-06-03 (ADR-022 batch-1 rename)

---

## Mission

Per-course star ratings (1-5) submitted by enrolled learners. Per-course
aggregates surface in the catalog (`local_airpay_catalog`) and on the
course detail page. Distinct from Moodle core ratings (which are
activity-scoped); this plugin is course-scoped.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_sentientia_ratings` | Individual user ratings — `(userid, courseid, stars, comment, timecreated)`; unique on `(userid, courseid)` (one rating per user per course; re-submit updates). |

## Capabilities (1)

`local/sentientia_ratings:rate` — granted to enrolled learners (7 role-capability
grants preserved across the rename hand-over).

## Feature flags

None registered.

## Key files

```
local/sentientia_ratings/
├── version.php                                     2026060302 / 1.1.2
├── README.md
├── lib.php
├── classes/
│   ├── rating_manager.php                          Rating CRUD + aggregate
│   └── external/submit_rating.php                  WS endpoint (rate course)
├── db/
│   ├── install.xml                                 1 table
│   ├── upgrade.php                                  no-op; drives classmap rebuild + WS re-register
│   ├── services.php                                 WS: local_sentientia_ratings_submit_rating
│   └── access.php                                  1 capability
├── amd/
├── lang/
│   ├── en/local_sentientia_ratings.php             12 strings
│   └── hi/local_sentientia_ratings.php             12 strings (100% parity)
└── tests/submit_rating_test.php                    PHPUnit
```

## Tests

PHPUnit covering the rate/update/aggregate flow with multi-user fixtures.

## Open items

- [ ] Comment moderation queue (today: comments go live immediately)
- [ ] Profanity filter
- [ ] Rating reasons / categorical tags (today: stars + free text only)
- [ ] Per-tenant minimum-rating-threshold to publish
- [ ] Behat coverage of the rating widget

## ADR-022 batch-1 rename — 2026-06-03

Renamed `local_airpay_ratings` -> `local_sentientia_ratings` (first leaf plugin
of the 30-plugin Sentientia rename program). Source via `tools/rename/codemod.php`;
DB hand-over via `tools/rename/handover.php` (table, config_plugins, capability
name + component, the 7 role-capability grants, files, web service). Verified
12/12 on the local production-import: plugin recognized, table/capability/WS
migrated, classes autoload, WS method executes, en+hi 12/12 strings, zero
`airpay_ratings` residue. The production hand-over is a separate gated step.

## State card created — 2026-05-24

Initial state card (P1 state-card pass). Plugin live for many phases.
