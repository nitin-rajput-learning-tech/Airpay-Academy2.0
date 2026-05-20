# Rename Map — "Moodle" → "Sentientia LMS"

> **Status:** PENDING NITIN APPROVAL
> **Date:** 2026-05-20 (Session 4 — Stream A)
> **Rationale:** ADR-001 §License posture + §Trademark cleanup obligation
> **Total renames proposed:** 18 EN strings + 18 HI mirrors + 1 layout label = 37 changes

This document is the audit trail per CLAUDE.md v5.0 §core-mods discipline.
Every line below catalogues a user-visible "Moodle" mention and proposes a
disposition. Three categories:

- ✅ **RENAME** — clearly user-visible brand text; replace with "Sentientia LMS"
- ❌ **KEEP** — GPL attribution (required by license), code comment (not rendered), or technical reference where the Moodle name is the canonical identifier
- ⚠️ **YOUR CALL** — admin-facing technical context where either choice is defensible

---

## ✅ RENAME (proposed — needs your `confirm`)

### 1. Layout label — admin dashboard system-health widget

**File:** `theme/airpayux/layout/dashboard.php` line 514
```php
// Before:
['label' => 'Moodle Version', 'value' => $CFG->release ?? 'Unknown', ...]
// After:
['label' => 'Platform Version', 'value' => $CFG->release ?? 'Unknown', ...]
```
Rationale: shows `$CFG->release` (e.g. "5.1.3+"). The engine version is real
diagnostic info but the brand "Moodle" is leaking. "Platform Version" is brand-
neutral and still informative.

### 2-7. Lang string: `privacy:metadata` — across 4 plugins

These all share the same shape: "user state lives on core Moodle tables
exported by their respective providers."

| File | Line | Plugin |
|---|---|---|
| `local/airpay_courses/lang/en/local_airpay_courses.php` | 92 | courses |
| `local/airpay_org/lang/en/local_airpay_org.php` | 102 | org |
| `local/airpay_reports/lang/en/local_airpay_reports.php` | 53 | reports |
| `local/airpay_integrations/lang/en/local_airpay_integrations.php` | 69 | integrations |

```
Before: "...core Moodle tables exported by their respective providers."
After:  "...core Sentientia LMS tables exported by their respective providers."
```

Plus matching Hindi mirrors in `lang/hi/`.

### 8. `airpay_evaluation` — `template_payload_corrupt`

**File:** `local/airpay_evaluation/lang/en/local_airpay_evaluation.php` line 205
```
Before: "...The row may have been edited outside Moodle. Delete and re-save."
After:  "...The row may have been edited outside Sentientia LMS. Delete and re-save."
```
Plus Hindi mirror line 243.

### 9. `airpay_evaluation` — `notify_admin_on_response_help`

**File:** `local/airpay_evaluation/lang/en/local_airpay_evaluation.php` line 221
```
Before: "...fires a Moodle notification to all site admins..."
After:  "...fires a Sentientia LMS notification to all site admins..."
```
Plus Hindi mirror line 161.

### 10. `airpay_roles` — `err_capability_not_found`

**File:** `local/airpay_roles/lang/en/local_airpay_roles.php` line 105
```
Before: 'Capability "{$a}" is not registered in this Moodle.'
After:  'Capability "{$a}" is not registered in this Sentientia LMS install.'
```
Plus Hindi mirror line 110.

---

## ⚠️ YOUR CALL — admin-facing technical references

These mention "Moodle" in admin help text where the reference is to an
underlying technical concept. Two valid postures:

- **Option A (brand-clean):** rename to "Sentientia LMS"
- **Option B (technical-accuracy):** keep "Moodle" — these strings describe
  filesystem paths, config tables, and Moodle PHP function names. An admin
  reading the help text benefits from knowing which physical PHP function or
  DB table to inspect when things break.

### 11. `airpay_exams` — `quiz_help` (line 23)
```
"Pick an existing Moodle quiz activity to register as an enterprise exam.
Only quizzes not already registered are shown. Build the quiz first in
its course (Add activity > Quiz), then register it here."
```
Refers to Moodle's `mod_quiz` activity by its UI name. Admin sees an
"Add activity > Quiz" link with that exact label. **Recommendation:** keep
"Moodle quiz" → just "quiz" (drop the qualifier). Less brand exposure than
"Moodle", less ambiguous than "Sentientia LMS quiz" (which doesn't exist).

### 12. `airpay_exams` — `confirmdelete` (line 45)
```
"Unregister exam "{$a}"? The underlying Moodle quiz will NOT be deleted —
it stays in its course..."
```
Same situation. Recommendation: "Moodle quiz" → "quiz activity".

### 13. `airpay_exams` — `noexams_subtitle` (line 56)
```
"Register existing Moodle quizzes as enterprise exams to add tenant scoping..."
```
Same. Recommendation: "Moodle quizzes" → "quizzes".

### 14-16. `airpay_users` — HRMS sync help (lines 218, 220, 222)
```
218: "...Stored unencrypted in the Moodle config table — rotate the token..."
220: "...The Moodle web-server user must have read permission..."
222: "...Defaults to <code>2</code> (the site admin on a stock Moodle)..."
```
These are deep technical references — an SRE adopting Sentientia LMS who
inherits the codebase needs to know that "the Moodle config table" is the
`mdl_config` SQL table, and "the Moodle web-server user" is the OS user
running Apache (typically `www-data`). Renaming to "Sentientia LMS config
table" / "Sentientia LMS web-server user" loses that internal-implementation
clarity.

**Recommendation:** KEEP these unchanged. Brand the user-facing surfaces;
keep technical-implementation references accurate.

### 17. `airpay_emails` — `email_to_user_failed` (line 57)
```
"Moodle email_to_user() returned false. Check mail server config + recipient address."
```
References Moodle's literal PHP function `email_to_user()`. **Recommendation:**
KEEP — function name is canonical, renaming it would confuse anyone debugging.

---

## ❌ KEEP (GPL attribution + technical references)

All preserved by default. Per ADR-001 §License posture, GPL v3 requires the
"part of Moodle" attribution be present in derivative works. Removing it
would put Sentientia LMS in violation of the license that grants us the right
to fork.

### License headers preserved (these stay AS-IS):
- All `// This file is part of Moodle - http://moodle.org/` lines
- All `// Moodle is free software:` / `Moodle is distributed in the hope` paragraphs
- All `// along with Moodle.  If not, see <http://www.gnu.org/licenses/>` lines

Files containing GPL headers (un-renamed):
- `local/airpay_courses/lang/en/local_airpay_courses.php` lines 2-15
- `local/airpay_org/lang/en/local_airpay_org.php` lines 2-15
- `local/airpay_users/lang/en/local_airpay_users.php` lines 2-15
- `theme/airpayux/templates/*.mustache` (every file)
- `theme/airpayux/templates/core/*.mustache` (every file)
- `theme/airpayux/templates/core_form/*.mustache` (every file)
- `theme/airpayux/templates/core_courseformat/**/*.mustache` (every file)
- `theme/airpayux/layout/*.php` (every file)

### Code comments (not rendered to users) preserved:
- `local/airpay_classroom/lang/en:145` — PHP `//` comment about `[[filterstoolong]]`
- `local/airpay_emails/lang/en:48` — PHP `//` comment about lang-string interpolation
- `local/airpay_users/lang/hi:62` — `// Privacy metadata declaration (Moodle compliance).`
- `theme/airpayux/layout/columns2.php:32` — PHP comment
- `theme/airpayux/layout/course.php:33` — PHP comment
- `theme/airpayux/layout/dashboard.php:64` — PHP comment
- All Mustache `{{!...}}` comments in templates
- All JS comments in template inline scripts

---

## Summary by category

| Category | Count | Action |
|---|---|---|
| RENAME (clear user-visible brand) | 7 EN + 7 HI = **14** | Will execute on your confirm |
| YOUR CALL (admin-facing technical) | 7 strings (6 EN + 1 HI mirror) | **Awaiting your decision** |
| KEEP (GPL + comments + technical) | ~80 occurrences | Untouched |
| **Total potential changes** | 14-25 depending on your YOUR CALL decision | |

---

## Footer — Sentientia LMS branding addition

ADR-001 commits to "Built on Moodle (GPL v3)" attribution in footer. Current
`theme/airpayux/templates/footer.mustache` doesn't yet show ANY product brand —
it just renders Moodle's standard footer HTML with no surrounding wrapper.
Proposed footer addition (new — not a rename):

```mustache
<div class="ap-product-attribution text-center small text-muted py-2">
    Sentientia LMS · Built on Moodle (GPL v3) · © Airpay Payment Services 2026
</div>
```

This adds the product brand WHERE it's currently absent + keeps GPL attribution.
Recommend including this in Session 4.

---

## Login page — Sentientia LMS hero

Current `theme/airpayux/templates/core/loginform.mustache` shows Airpay Academy
branding (logo + "Airpay Academy" wordmark). For customer-zero this is correct
— users are signing into Airpay Academy, not Sentientia LMS. Recommendation:
**no change here**. The Sentientia LMS product brand surfaces on:

- Footer attribution (per above)
- Site administration → System health → "Platform Version" (per #1 above)
- Future Sentientia LMS marketing site / portal (not in scope for Session 4)

This preserves Airpay Academy's customer-zero identity while marking the
product brand in administrative/footer surfaces.

---

## Your action — three quick decisions

Reply with:

1. **Section ✅ RENAME (items 1-10):** "yes" / "no" / "skip item N"
2. **Section ⚠️ YOUR CALL (items 11-17):**
   - "go with my recommendations" (drop "Moodle" qualifier in airpay_exams strings, keep airpay_users + airpay_emails as-is)
   - "rename all to Sentientia LMS"
   - "keep all unchanged"
   - "I'll specify per-item"
3. **Footer + login changes:**
   - Add Sentientia LMS attribution to footer? (Y/N)
   - Leave login page Airpay Academy? (Y/N)

After your reply I'll execute the renames + visual evidence + commit + push.

---

## File created

This rename map: `docs/core-mods/2026-05-20-moodle-to-sentientia-rename.md`
