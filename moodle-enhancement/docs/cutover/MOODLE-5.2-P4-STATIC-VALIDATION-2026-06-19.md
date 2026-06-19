# Moodle 5.2 Candidate — P4 Static Validation (2026-06-19)

**Scope:** the Sentientia layer of the Moodle 5.2 standalone candidate (`C:\xampp\htdocs\moodle5.2\public`)
— 46 `local_sentientia_*` plugins + `theme_sentientia` + 6 `sentientia_*` blocks + `paygw_airpay` +
`quizaccess_sentientia_proctoring` (≈55 plugins, ~1,840 files).
**Method:** parallel static-validation workflow (13 agents) — 4 scan dimensions + adversarial verification of
judgment-call findings + synthesis. Builds on the P1 compat audit (`MOODLE-5.2-RECONCILIATION-PLAN.md`).

## Verdict: **PASS-WITH-NOTES** — 0 confirmed blockers/highs

The candidate is static-clean for build. Every judgment-call finding was adversarially verified; all 5
`external_*` "high" hits were confirmed **false-positive for runtime** (the 5.2 `class_alias` shim still
resolves them). Two real-but-non-blocking items were nonetheless **fixed** for completeness/correctness;
one webroot-only cruft item was excluded from the package.

## Dimension results

| Dimension | Checked | Result |
|---|---|---|
| **PHP lint** (`php -l`) | 1,567 PHP files | **0 syntax errors** |
| **DB schema + version integrity** | 45 `install.xml`, 54 `version.php` | 0 malformed XML, 0 missing component/version, **0 utf8mb4 DDL-bug-class hits** (worst index 1,220 B vs 3,072 B limit; no CHAR > 1333). Confirms the historical 333-char-index / 1333-VARCHAR gap-build bug class is fully remediated. |
| **5.2 / PHP 8.3 deprecation** | ~1,840 files | 0 blockers. `print_error`/`each`/`create_function`/`utf8_*`/dynamic-prop/curly-interpolation all clean. `otplogin` only in doc comments. 5 `external_*` highs → verified false-positive (shim) but migrated anyway. BS4 badges → covered by the active `bs5-compat` shim. |
| **AMD parity + de-brand** | 133 items | `theme_airpayux` survivors: **0**. version.php component match: **55/55**. 10 `amd/build` gaps in the *webroot* (cart/emails/proctoring) → **builds exist in git; were webroot drift; patched into the package**. |

## Actions taken (this pass)

1. **`external_*` → `core_external\` migration completed** — 5 remaining files migrated to match every sibling
   plugin (drop `require_once externallib.php`; `use core_external\external_*`); `php -l` clean:
   - `local/sentientia_emails/classes/external/{rule_api,template_api}.php`
   - `local/sentientia_leaderboard/classes/external/{get_board,list_boards,set_optout}.php`
   - This **closes reconciliation item #3** in full (assistant + paygw were done earlier; these were the rest).
   - **Left intentionally:** `theme/sentientia/classes/output/traits/course_view.php:97` keeps its
     `require_once externallib.php` — it uses the global **function** `external_format_text()` (not a class),
     which is still shim-provided on 5.2; migrating it to `\core_external\util::format_text()` is a behavioural
     change deferred to avoid risking course-summary rendering. Tracked as low debt.
2. **AMD webroot drift closed in the package** — the 10 `min.js` builds for `sentientia_cart` (5),
   `sentientia_emails` (3), `sentientia_proctoring` (2) exist in git (parity holds, CI green) but were missing
   from the drifted dev webroot; copied the git-built bundles into the package tree. Package parity now 5/5, 3/3,
   2/2. (No new grunt build needed — the deployable source was already correct.)
3. **Nested duplicate plugin excluded from the package** — `local/sentientia_live/sentientia_live/` (a full copy
   nested one level deep, **not git-tracked**, ignored by Moodle's one-level plugin scanner so harmless at runtime)
   is now skipped by the packager so the standalone ZIP ships clean.

## Non-actionable / informational (no change)

- **BS4 `badge-*` classes** (~45 hits) — rendered correctly by the `_bs5-compat.scss` shim (incl. the
  `badge-secondary` line added in the reconciliation). No per-file sweep needed.
- **5 CHAR(1333) fields** (`sentientia_api`, `sentientia_xapi`) — at the allowed max, not over, none indexed.
- **`paygw_airpay` decimal version** `2024100700.10` — valid Moodle numeric version, intentional.

## Still gated (unchanged)

Runtime validation (P5) remains blocked on local **PHP 8.3**, and deploy on prod **MySQL 8.4 + PHP 8.3**
(open IT change requests). This pass is static only.
