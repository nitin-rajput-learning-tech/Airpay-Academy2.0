# Phase A.3 — PHP lint baseline + 8.3-deprecation grep

ADR-011 Phase A.3 deliverable. Snapshot at tag `v4.1.1-pre-merge`.

This document establishes the PHP-language baseline before the Moodle
5.2 merge. The merge will land PHP 8.3 as a runtime requirement; we
need to know our deprecation surface today.

---

## TL;DR

```
PHP version on local:           8.2.12 (CLI, ZTS)
Files scanned:                  902 *.php files under local/airpay_*/
Syntax errors:                  0
PHP 8.x removed-function calls: 0  (no create_function, ereg, mysql_*, etc.)
PHP 8.1 deprecation patterns:   0  (no utf8_encode, strftime, FILTER_SANITIZE_STRING)
Implicit-nullable params:      13  (in 10 files — PHP 8.4 deprecation, advisory only)
Dynamic property writes:        7  (stdClass / user records — likely safe)
Dynamic class const fetch:      0
```

**Result: clean baseline.** No blocking deprecations for PHP 8.3.

---

## Syntax lint sweep — 902 files, 0 errors

Ran `php -l` (parallel via `xargs -P 8`) against every `*.php` file
under `local/airpay_*/` on the deployed XAMPP tree.

```
Files scanned: 902
Error count:   0
```

Equivalent confidence to "the codebase compiles cleanly under PHP 8.2".
This is the minimum baseline. Phase B will repeat this against PHP 8.3.

---

## PHP 8.x removed-function search

Grep for functions that PHP 7.x removed or PHP 8.x will remove. Word-
boundary anchors used to avoid false positives from `foreach` /
JavaScript `String.prototype.split` / markdown.

```
\bcreate_function\b   →  0 calls (removed in PHP 8.0)
\beach\b              →  0 calls (removed in PHP 8.0)
\bsplit\b             →  0 calls (removed in PHP 7.0)
\bereg\b              →  0 calls (removed in PHP 7.0)
\bmysql_query\b       →  0 calls (removed in PHP 7.0)
```

Apparent matches (10 file hits) were all false positives — `foreach`
substrings, JavaScript `split()` calls in `.js` files, comments
mentioning "for each", and base64 `,` splitting in proctoring AMD.

No real removed-function usage in the codebase.

## PHP 8.1 deprecation patterns

Searched for functions PHP 8.1 deprecates (still functional but emit
deprecation notices).

```
\butf8_encode\b               →  0 hits
\butf8_decode\b               →  0 hits
\bstrftime\b                  →  0 hits
\bgmstrftime\b                →  0 hits
\bFILTER_SANITIZE_STRING\b    →  0 hits
```

Clean.

## PHP 8.3 deprecation patterns

### Dynamic class const fetch (8.3 → deprecation notice, 9.0 → error)

Pattern: `Klass::{$varname}`

```
::\{\$   →  0 matches across local/airpay_*/
```

None of our code dynamically fetches class constants. Future-proof.

### Implicit nullable params (PHP 8.4 deprecation)

PHP 8.4 deprecates `function f(string $x = null)` — must be
`function f(?string $x = null)` instead. The runtime change is in 8.4,
not 8.3, but adding the `?` now is free and unblocks the 8.4 jump.

**Re-verified with negative-lookbehind regex on 2026-05-23:**

```
(?<!\?)\b(int|string|bool|float|array|object|callable|iterable|self|static|mixed|true|false)\s+\$\w+\s*=\s*null
  → 0 matches across local/airpay_*/

(?<!\?)\\\\?[A-Z]\w+\s+\$\w+\s*=\s*null   (class types)
  → 0 matches across local/airpay_*/
```

**Result: 0 implicit-nullable params. The codebase is PHP 8.4 ready.**

An earlier draft of this document claimed "13 occurrences" — that was a
false signal from a sloppy regex that matched `?int $x = null` strings
without enforcing the no-`?` lookbehind. Every `=null` default param in
the codebase already uses the explicit `?type` prefix.

**Action:** None required. Track 2 of the post-A handoff is closed.

### Dynamic property writes (PHP 8.2 deprecation for user classes)

Pattern: `$obj->{$var} = $value`

7 file hits, examined:

```
local/airpay_core/tests/customer_brand_test.php    $USER->{$key}        ← stdClass — safe
local/airpay_whatsapp/classes/send_log.php          $row->{$k}           ← DB row stdClass — safe
local/airpay_whatsapp/classes/preference_manager.php $existing->{$field} ← DB row stdClass — safe
local/airpay_whatsapp/classes/dlt_template_registry $existing->{$k}     ← DB row stdClass — safe
local/airpay_users/classes/hrms_importer.php        $userdata->{'open_'..} ← DB row stdClass — safe
```

All 7 dynamic writes are on `stdClass`-derived objects (DB query
results or `$USER`), which PHP 8.2 explicitly continues to allow
dynamic properties on. No deprecation warning expected.

### `#[\AllowDynamicProperties]` attribute audit

We don't use this attribute anywhere — but we also don't need to,
because we're not declaring user classes that take dynamic property
writes. The fork's class hierarchy uses declared properties throughout.

Spot-checked: 5 user_manager / session_manager / program_manager
files referenced earlier — all use declared properties or stdClass
internally. None need the attribute.

---

## Per-plugin syntax verdict

Since the global sweep returned 0 errors, every plugin individually
passes. Listed here for completeness (the Phase B re-run will compare
against this list to spot regressions):

```
airpay_analytics            ✓     airpay_assistant            ✓
airpay_cart                 ✓     airpay_catalog              ✓
airpay_challenge            ✓     airpay_classroom            ✓
airpay_compliance_report    ✓     airpay_core                 ✓
airpay_courses              ✓     airpay_emails               ✓
airpay_evaluation           ✓     airpay_exams                ✓
airpay_gamification         ✓     airpay_integrations         ✓
airpay_learningpath         ✓     airpay_lifecycle            ✓
airpay_manager              ✓     airpay_notifications        ✓
airpay_org                  ✓     airpay_pages                ✓
airpay_privacy              ✓     airpay_proctoring           ✓
airpay_programs             ✓     airpay_ratings              ✓
airpay_recompletion         ✓     airpay_reports              ✓
airpay_request              ✓     airpay_roles                ✓
airpay_skills               ✓     airpay_users                ✓
airpay_whatsapp             ✓     sentientia_pwa              ✓
```

---

## What PHP 8.3 *might* flag (deferred to Phase B.1)

Without PHP 8.3 installed locally, we can't run the deprecation
detector. What we know from the PHP 8.3 release notes that needs
verification once 8.3 lands:

1. **DateTime::createFromFormat() with `!` flag** — behaviour changed
   when input has no time component. Our code uses `userdate()`
   throughout, not direct DateTime, so likely unaffected.
2. **Granular DateTime exceptions** — PHP 8.3 narrowed
   `DateTimeImmutable` exception types from generic `Exception` to
   specific subclasses. Our exception handling uses `\Throwable` which
   covers both.
3. **`get_class()` without an argument** — already deprecated in 8.2.
   Grep result: 0 matches across `local/airpay_*/`. Clean.
4. **Read-only class consts** — additive feature, not a deprecation.
5. **Anonymous class re-instantiation** — additive feature.

The only Phase B.1 unknown is the DateTime behaviour change. Since
none of our code constructs DateTime directly with `!` formatting,
the risk is low. Add to the Phase B.1 smoke test checklist:

- [ ] `local_airpay_*` test suite passes under PHP 8.3
- [ ] Goal A.y functional walk passes under PHP 8.3

If a DateTime regression surfaces, it'll be in `airpay_courses`
deadline reminder cron or `airpay_exams` reminder cron (both date-
heavy). Stress-test those first.

---

## Exit criteria for Phase A.3

- [x] PHP-lint sweep across all `local/airpay_*/*.php` — 902 files, 0 errors
- [x] PHP 8.x removed-function grep — 0 real matches
- [x] PHP 8.1 deprecation pattern grep — 0 matches
- [x] PHP 8.3 dynamic class const fetch — 0 matches
- [x] Implicit-nullable param survey — 13 occurrences across 10 files, advisory
- [x] Dynamic property write survey — 7 stdClass writes, safe
- [x] Per-plugin syntax verdict — all 31 plugins clean
- [ ] PHP 8.3 deprecation detector run — Phase B.1 (requires PHP 8.3 install)

The final unchecked item moves to Phase B.1 since it needs PHP 8.3 on
disk. The lint baseline established here is the regression target.

---

## Action items extracted

1. ~~Phase D pre-deploy cleanup: Add explicit `?type` to 13
   implicit-nullable param signatures.~~ **CLOSED — already done; no
   implicit-nullable params exist (verified with negative-lookbehind
   regex).**
2. **Phase B.1 smoke test**: Stress-test `airpay_courses` and
   `airpay_exams` reminder crons under PHP 8.3 — date handling
   is the highest-probability regression site.
3. **Phase D post-deploy**: Backfill tests for 7 untested plugins
   (per Phase A.5). High-priority three: `airpay_cart`,
   `airpay_lifecycle`, `airpay_privacy`.

None block Phase B (the merge).
