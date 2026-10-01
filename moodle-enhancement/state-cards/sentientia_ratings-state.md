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


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-30 - ADR-032 BizLMS import: ratings (version 2026093001 / 1.2.0)

The `ratings` importer is built (mapping doc section 20). It moves what BizLMS's `local_ratings` plugin kept into
Sentientia history without touching the BizLMS tables, which stay as the archive.

What was built
- `classes/bizlms/`: `importer` (feature `ratings`, depends on classroom, program and learningplan, no PRESERVE step,
  no tenant column, not atomic), `ratings_step`, `reviews_step`, `reactions_step`, and the helpers `area_map`,
  `row_rules`, `native_probe` and `oracle`. Declared in `db/bizlms_import.php`.
- Sources `local_rating`, `local_comment`, `local_like`. Declined: `local_ratings_likes` (derived cache, the parity oracle)
  and `block_trending_modules` (a third copy of the averages).
- Two new tables (install.xml and an idempotent upgrade step): `local_sentientia_ratings_reviews` (no unique key: a learner
  may leave several reviews) and `local_sentientia_ratings_reactions` (UNIQUE userid, itemid, ratearea; likestatus 1 like,
  2 dislike, other values kept and never counted). The area column is `ratearea`, as in `local_sentientia_ratings`; the
  mapping doc called it `area`.
- Area map: local_courses -> local_sentientia_courses, local_classroom -> local_sentientia_classroom, local_program ->
  local_sentientia_programs, local_learningplan -> local_sentientia_learningpath. Any other area (certification included),
  and an area over 100 characters, is skipped as `unknown_area`. Classroom, programme and learning plan items resolve
  through the legacy map.
- Skips, never guesses: `invalid_rating` (not 1 to 5), `orphan_user` (NULL, 0, the guest, no such user), `orphan_item`,
  `unknown_area`, `invalid_reaction`. Duplicates of a (user, item, area) key collapse to the latest change
  (`dup_natural_key`, merged). A row Sentientia already holds for the key is never overwritten: the legacy row folds
  into it (`native_row_kept`).
- Owner choices read from the decisions file: `ratings.invalid_rows`, `ratings.blank_reviews`, `ratings.deleted_users`,
  `ratings.certification_area`. `ratings.show_dislike_counts` is a reader choice, built into the reaction counts.
- Privacy provider extended to the two new tables (export, erase, userlist), English and Hindi strings.
- Parity oracle: `cli/ratings_oracle.php` compares imported averages with `local_ratings_likes` within 0.05 and gives each
  difference a reason; the preflight reports how far the cache already disagreed with the legacy rows.

Reader changes
- `rating_manager`: the `local_rating` fallback is removed (it compared the new area name with rows stored under the old one).
- `theme/sentientia` `core_renderer`: the dead BizLMS `display_rating()` branch is removed at both sites.
- `submit_rating`: the four BizLMS-era areas are dropped from the whitelist (it now reads `rating_manager::AREAS`).
- New, flag-gated, OFF by default: `reviews.php` with `review_manager`, `reaction_manager` and `item_summary`; flags
  `sentientia.ratings.reviews` and `sentientia.ratings.reactions`. The review list is limited to the viewer's tenant
  (fail closed), hides blank reviews and deleted users, and escapes every review.
- README corrected (no tenant gate, no review column in the ratings table, erasure deletes rows).

Not done, on purpose
- Initialising the rating widget on course pages (or rendering the stars read-only) changes what every learner sees by
  default: the owner's call, with visual evidence.
- No visual evidence yet for `reviews.php`: nothing was run against a site. Capture desktop and mobile screenshots before
  either flag is ever turned on. The reaction counts are not wired into any theme template.
- Removing the dead `local/airpay_ratings` directory is a [CONFIRM] delete.
- `theme/airpayux` and `theme/epsilon` still call BizLMS `display_rating()`; they are not the runtime theme.

Tests: `tests/bizlms_import_test.php` (contract plus the feature rules), `tests/bizlms_readers_test.php`,
`tests/privacy/provider_test.php`; fixture `tests/fixtures/bizlms/ratings.install.xml`. Not yet run: the local PHPUnit
database has to be re-initialised for version 2026093001 first.
