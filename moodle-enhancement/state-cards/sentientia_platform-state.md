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
