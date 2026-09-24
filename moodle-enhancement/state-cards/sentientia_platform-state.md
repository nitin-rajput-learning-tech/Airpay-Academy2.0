# sentientia_platform - state card

Cross-cutting primitives shared by every Sentientia plugin: tenant path
handling, customer/branding resolution, structured logging, cron health.

## 2026-09-22 - structured_logger stamped a component name that no longer exists

The class docblock documents the emitted shape as:

```json
{ "component": "local_sentientia_cart", "event": "checkout_completed" }
```

The code said:

```php
'component' => 'local_airpay_' . $plugin,
```

`local_airpay_*` was retired by ADR-022/025. So every structured log line named
a component that does not exist, and a log search or APM dashboard filtered on
`component` found nothing. Nothing errored; the docblock and the code had
simply disagreed since the rename.

Replaced with `qualify_component()`, which prefixes a short name
(`'cart'`, `'core'`) with `local_sentientia_` and passes an already-qualified
name (`local_*`, `theme_*`, `mod_*`, `core`) straight through so a caller
cannot double-prefix.

**The interesting part is how it survived.** The top-level `local/` copy had
already been corrected. Only the `moodle-enhancement/local/` copy was stale.
Both trees are deployed from, so the bug was live on whichever surface served
the ME copy - see the drift gate below.

## 2026-09-22 - Cross-tree drift gate

Every local plugin exists twice in this repo, and `deploy_to_uat.sh` takes
`--prefer-top` / `--prefer-me` precisely because which copy is authoritative
varies by plugin (UAT serves org, analytics, learningpath, compliance_report
and courses from the ME tree). An edit applied to one tree and not the other is
invisible until the wrong copy is served.

Two live instances found the same day:

| Instance | What drifted |
|----------|--------------|
| `structured_logger.php` | ME carried the retired `local_airpay_` prefix; top-level was already correct |
| `sentientia_ratings/` | The ME copy was four files - no `version.php`, no `lang/`, no `lib.php`. Every shared file was byte-identical, so it was a truncated copy rather than a fork. Completed from the top-level tree. |

New `tools/check-tree-drift.php` compares every file of every plugin present in
both trees, normalising line endings first (the trees genuinely differ in
CRLF/LF and that is not drift). It reports CONTENT, ONLY-ME and ONLY-TOP
findings.

**99 files already diverge**, so a gate that failed on all of them would block
every push. `tools/tree-drift-baseline.txt` records the known set; the gate is
BLOCKING for anything new. It also fails when a baselined path has since been
reconciled, so the list cannot rot - it can only shrink without a deliberate
`--update-baseline`.

Wired into the `tree-drift-check` CI job (13 jobs now) and pre-commit CHECK 19,
which checks only the staged files so it stays fast. CHECK 19 warns rather than
blocks, because some of the 99 divergences may be deliberate; CI is what blocks.

Verified by injecting a one-line change into `local/sentientia_cart/lib.php`:
the gate reported `FAIL new drift: CONTENT sentientia_cart/lib.php` and exited
1, then went green when the change was reverted.

## 2026-09-22 - privacy_coverage_test

New `tests/privacy_coverage_test.php` walks every Sentientia plugin's
`install.xml` and fails the build if a plugin declaring a user-identifying
column also declares `null_provider`, ships no provider at all, or declares
only some of the tables it owns. Structural rather than an allowlist, so a new
plugin with a copy-pasted `null_provider` fails on its first CI run. Written
after the audit found four plugins asserting they held no personal data while
owning nine tables keyed on a user id. See the individual plugins' state cards.


## 2026-09-22 - The migration parity check proved counts, then claimed "data intact"

`cli/migration_parity_check.php` is the gate for the ninja-sandbox rehearsal and the eventual live
replacement. It compared row **counts** across seventeen metrics and then printed:

```
RESULT: 100% PARITY - data intact.
```

Counts cannot see a migration that preserved every row but changed what is in them: a truncated
column, a collation change mangling non-ASCII names, timestamps shifted by a timezone, grades
rounded differently. The sentence claimed it anyway - the same "reported success it did not achieve"
shape as the erasure defect fixed earlier the same day, on the one script whose entire job is to
decide whether real user data survived a migration.

**Added:** `sentientia_parity_checksums()`, summing a CRC over the meaningful columns of nine
critical tables (`user`, `course`, `course_categories`, `user_enrolments`, `course_completions`,
`course_modules_completion`, `quiz_attempts`, `badge_issued`, `grade_grades`). Column lists are
explicit, so adding a schema column cannot silently invalidate an old baseline. NULLs get a
sentinel because `CONCAT_WS` skips them and `(a, NULL, b)` would otherwise collide with
`(a, b, NULL)`. Floats are rounded before hashing, because a spurious drift would be worse than no
check - it teaches people to ignore the output.

**And it refuses to over-claim.** `CRC32` is MySQL/MariaDB-only. On any other engine, or against a
baseline captured before checksums existed, the comparison reports SKIPPED and **exits 2** with
"Data is NOT proven intact" rather than printing a parity it did not verify.

Verified against the real 2,890-user production import on local MariaDB:

| Path | Result |
|------|--------|
| unchanged data | `RESULT: 100% PARITY - counts AND value checksums match.` exit 0 |
| one character changed in one of 3,178 `user` rows | `DRIFT user rows 3178->3178 crc 6799845373188->6800747854502` exit 1 |
| baseline without checksums | `SKIPPED - the baseline predates value checksums.` exit 2 |

The middle row is the point: the row count is identical and the old script would have called that
100% parity. The mutation was reverted and parity re-confirmed green.

The CLI now also exists in both plugin trees (it was top-level only), draining one entry from
`tools/tree-drift-baseline.txt`.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.
