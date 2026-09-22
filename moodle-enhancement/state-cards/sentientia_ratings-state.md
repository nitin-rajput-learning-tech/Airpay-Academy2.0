# State Card — `local_airpay_ratings`

**Component:** `local_airpay_ratings`
**Version:** `2026052001` / `1.1.1`  (+P1 #51 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Per-course star ratings.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Per-course star ratings (1-5) submitted by enrolled learners. Per-course
aggregates surface in the catalog (`local_airpay_catalog`) and on the
course detail page. Distinct from Moodle core ratings (which are
activity-scoped); this plugin is course-scoped.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_airpay_ratings` | Individual user ratings — `(userid, courseid, stars, comment, timecreated)`; unique on `(userid, courseid)` (one rating per user per course; re-submit updates). |

## Capabilities (1)

`local/airpay_ratings:rate` — granted to enrolled learners.

## Feature flags

None registered.

## Key files

```
local/airpay_ratings/
├── version.php                                  2026052001 / 1.1.1
├── README.md
├── lib.php
├── classes/
│   ├── rating_manager.php                       Rating CRUD + aggregate
│   └── external/                                 WS endpoint (rate course)
├── db/
│   ├── install.xml                              1 table
│   ├── upgrade.php
│   └── access.php                               1 capability
├── amd/
├── lang/
│   ├── en/local_airpay_ratings.php
│   └── hi/local_airpay_ratings.php              (100% parity post-P1 #51)
└── tests/                                       1 PHPUnit class / 14 methods
```

## Tests

1 PHPUnit class, 14 methods. Covers the rate/update/aggregate flow
with multi-user fixtures.

## Open items

- [ ] Comment moderation queue (today: comments go live immediately)
- [ ] Profanity filter
- [ ] Rating reasons / categorical tags (today: stars + free text only)
- [ ] Per-tenant minimum-rating-threshold to publish
- [ ] Behat coverage of the rating widget

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-22 - Real privacy provider (was no provider file at all)

`\core_privacy\local\metadata\null_provider` is not a neutral default. It is a positive assertion
to Moodle's privacy registry that the plugin stores **no** personal data. This plugin owns
`local_sentientia_ratings`, each keyed on a user id, so under DPDP a subject-access
request returned nothing from it and an erasure request deleted nothing - both reporting success, and
the registry page confirming the plugin held nothing.

Replaced with a full provider (`metadata\provider` + `request\plugin\provider` +
`request\core_userlist_provider`) implementing export, per-user erasure, bulk erasure and
context-wide deletion.
Separately, `moodle-enhancement/local/sentientia_ratings/` was an incomplete copy of the plugin: four files, no `version.php`, no `lang/`, no `lib.php`. Every shared file was byte-identical to the complete top-level `local/` tree, so the ten missing files were copied across rather than either copy being edited. The two trees now match.

Version bumped to 2026092201 so the cached privacy registry picks up the new tables.

Guarded platform-wide by `local_sentientia_platform\privacy_coverage_test`, which walks every
Sentientia plugin's `install.xml` and fails the build if a plugin declaring a user-identifying column
declares `null_provider`, ships no provider, or declares only some of the tables it owns. Structural
rather than an allowlist, so a new plugin with a copy-pasted `null_provider` fails on its first CI run.
