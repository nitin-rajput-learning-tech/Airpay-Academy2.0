# Code Quality Coach — Karpathy Principles Validator
**Version:** 1.1 (2026-11-12: "Write only what the task needs") | **For:** Airpay L&D OS (Phase 6B+) | **Trigger:** Pre-write validation or `/code-quality-coach [file]`

---

## Purpose

Enforces **4 Karpathy Principles** (from andrej-karpathy-skills) on your code *before* you commit:
1. **Think Before Coding** — surface reasoning, not magic
2. **Simplicity First** — minimal solutions, no speculation
3. **Surgical Changes** — only touch what's necessary
4. **Goal-Driven Execution** — verifiable success criteria

This skill prevents code debt and ensures Phase 6B/SENTIENTIA work stays maintainable.

---

## The 4 Principles → Validation Rules

| Principle | Rule | Violation Examples | Fix |
|-----------|------|-------------------|-----|
| **Think Before** | Every non-trivial function has a docblock explaining the "why" | `function processScorm() { ... }` (no comment) | Add `/** Packages SCORM ZIP with validation. Process: 1. Parse slides 2. Embed audio 3. Validate manifest. */` |
| **Simplicity First** | No unused variables, imports, or nested abstractions | `$data = get_data(); /* unused */` or 3+ nested if-statements | Remove unused var, extract complex logic to helper function |
| **Surgical Changes** | Each commit touches ≤3 file categories (theme, plugin, content, db, api) | Commit with navbar.scss + settings.php + voice.py + install.xml (4 categories) | Split into 2 commits |
| **Goal-Driven** | Error handling present, success/failure paths clear | API call without try-catch, missing return checks, no validation | Add try-catch, assert() checks, return validation |

---

<!--
Source (2026-11-12): the rules in "Write only what the task needs" are paraphrased, not copied, from Ponytail (MIT),
https://github.com/dietrichgebert/ponytail , files skills/ponytail/SKILL.md and AGENTS.md at commit
96608e078b2d5ea607c5c3b8b5a54d1c1fba6846 (2026-10-29). Read-only; nothing installed (no hooks, no status band).
No text is copied verbatim, but the step order and several bullets follow upstream closely, so its MIT notice is
recorded in THIRD-PARTY-NOTICES.md as adapted material. The Moodle list is ours.
-->

## Write only what the task needs

This sharpens **Simplicity First** and **Surgical Changes**. Smaller diffs are easier to review, but only once the
change is complete.

**Before writing.** Read the task, then the code around it. Write down everywhere the change has to land: callers,
tests, fixtures, lang strings, both plugin trees, the state card. Those places are the scope; features nobody
requested are outside it. For a bug fix, find all callers of the function before editing, and fix the cause in the
shared function rather than patching one caller.

**Go down this list and stop at the first answer that solves the whole task:**
1. Is it needed at all? Do not add options, settings or flexibility that no one requested; list what you left out in one line.
2. Does this codebase already have it? Reuse the helper, class or pattern the way the code around it does
   (for example `\local_sentientia_platform\tenant::path_descendant_filter()`, `feature_flags::is_enabled()`,
   `moodle-enhancement/tools/visual-pass/evidence_lib.mjs`).
3. Does Moodle core or PHP already do it? Use the core API (`$DB`, `get_string()`, `html_writer`, the File API,
   Mustache templates) before writing your own.
4. Is a dependency already installed that does it? Use it. Do not add a new dependency to save a few lines.
5. Otherwise, write the smallest code that works.

**While writing.**
- No wrapper, abstraction, setting or "for later" code that has one caller and no request behind it.
- A short line is better only when a reader understands it at a glance.
- Comments say why, not what, in one line.
- Moved or merged code keeps the validation and error handling it had.
- When two options are about the same size, prefer the one that handles edge cases: an empty list, zero, the last
  item, the site course id, a tenant path boundary.
- Logic that can go wrong (conditions, loops, parsing, money, security, tenant scoping) ships with a test. A
  one-line rename or wording change does not need one.
- A limit you accept on purpose gets a comment: `// shortcut: <the limit>, <when to revisit>`.

**When done,** end with a line or two naming what you deliberately left out and anything you could not verify.

**Never "extra" in this repo.** Moodle's required plugin files, lang strings, privacy providers, upgrade steps and
tests are part of a complete change, never extra code to trim. Leaving any of these out is a defect, not a saving:
- Moodle's required plugin files: everything root `CLAUDE.md` section 6 marks REQUIRED for the plugin type (for a
  local plugin `version.php`, `lang/en/local_<name>.php`, `index.php`), and `db/access.php`, `db/install.xml`,
  `db/upgrade.php`, `settings.php`, `db/feature_flags.php` when the plugin needs them.
- Language strings: every new string in `lang/en` and `lang/hi` (en/hi parity is a CI gate).
- A real privacy provider when the plugin stores personal data. A `null_provider` claims it stores none.
- Upgrade steps in `db/upgrade.php` with their savepoint, and the version bump.
- Tests for new logic, and a feature flag (default OFF) for any user-visible feature.
- Both plugin trees (`moodle-enhancement/local/` and `local/`) kept byte-identical, and the state card update.
- Validation at trust boundaries (`required_param()`/`optional_param()`, `require_login()`,
  `require_capability()`, sesskey), output escaping (`s()`, `format_string()`), tenant scoping, error handling that
  keeps data from being lost, and accessibility.
- Anything the person asked for.

---

## How It Works

### Auto-Check (Pre-Commit Hook Extension)

When you run `git commit`, checks 11–14 (below) fire automatically:

```bash
→ [11/14] Function docblocks (PHP)...
  ✗ ERROR: Missing docblock in plugins/local_trainer/classes/scorm_builder.php:23
         suggest: /** @param array $slides SENTIENTIA slide JSON
                      @param string $audiopath Path to MP3 file
                      @return stdClass SCORM package metadata */

→ [12/14] Magic numbers (PHP/Python)...
  ⚠ WARN: Hardcoded timeout (30) in content/voice/generate.py:45
          suggest: const VOICE_API_TIMEOUT_SEC = 30;

→ [13/14] Unused imports (Python)...
  ⚠ WARN: Unused import 'requests' in content/parsed/sop_parser.py:3
          suggest: Remove line or use for API call

→ [14/14] Surgical changes audit...
  ⚠ WARN: Commit touches 4 file categories: theme, local/plugin, rules/, content/
          suggest: One session = one deliverable. Split into separate commits.
```

### Manual Check (During Session)

```powershell
# Invoke manually before writing critical files:
/code-quality-coach "moodle-enhancement/theme/airpayux/scss/moodle/custom_changes.scss"

# Or check entire directory:
/code-quality-coach "moodle-enhancement/plugins/"
```

---

## Validation Checklist (When to Invoke)

✅ **Invoke manually when:**
- Writing a new PHP class (check for docblocks + error handling)
- Creating a SENTIENTIA agent (check imports, success criteria)
- Refactoring existing code (check for unused vars, nested complexity)
- Before final commit (surgical changes audit)

✅ **Pre-commit hook runs automatically on:**
- All staged PHP files (docblocks, magic numbers, error handling)
- All staged Python files (unused imports, hardcoded values)
- Commit summary (category audit)

---

## Example: Phase 6B Navbar Implementation

**Bad (violates Karpathy principles):**
```php
// moodle-enhancement/plugins/local_navbar/lib.php
function render_navbar($USER, $PAGE) {
    $items = array();
    $items[] = array('text' => 'Home', 'url' => '/');
    $items[] = array('text' => 'Courses', 'url' => '/courses');
    $items[] = array('text' => 'Dashboard', 'url' => '/dashboard');
    return json_encode($items);  // ← Magic numbers in URL routing, no error handling
}
```

**Karpathy-compliant (good):**
```php
/**
 * Render primary navigation bar for Airpay Academy.
 * 
 * Surfaces role-based menu items (Learner vs Admin vs Manager).
 * Items are filtered by user capability and tenant context.
 * 
 * @param stdClass $user Moodle user object (must have open_path tenant context)
 * @param moodle_page $page Current page context (provides $PAGE->context)
 * @return array Navigation items [{text, url, isactive, role_required}]
 * @throws moodle_exception If user lacks navigation capability
 */
function render_navbar($user, $page) {
    global $DB;
    
    // Validate prerequisites
    require_capability('local/navbar:view', $page->context);
    
    $costcenterid = $this->get_user_tenant($user);  // ← Extracted to helper method
    if (!in_array($costcenterid, AIRPAY_TENANT_IDS, true)) {
        throw new moodle_exception('invalidtenant', 'local_navbar');
    }
    
    // Build role-specific items
    $items = [];
    if (has_capability('moodle/course:create', $page->context)) {
        $items[] = $this->build_admin_menu();
    } else {
        $items[] = $this->build_learner_menu($costcenterid);
    }
    
    return $items;  // ← Clear return type, no magic
}
```

**Coach feedback on bad code:**
```
[11] Missing docblock: render_navbar() — add @param, @return, purpose explanation
[12] Magic string: '/courses' (line 5) — use MOODLE_URL constant
[12] Magic string: '/dashboard' (line 6) — use MOODLE_URL constant
[14] Surgical audit: This commit touches theme/ + plugins/local_navbar + rules/
     → One session = one deliverable. Recommend: Navbar commit → then Rules commit (separate)
```

---

## Integration with Your Workflow

| When | Action |
|------|--------|
| **Writing Phase 6B theme/plugin** | Coach checks auto-run at commit time (pre-commit hook) |
| **Building SENTIENTIA agents** | Manual `/code-quality-coach content/voice/` before final commit |
| **Reviewing code before production** | Use `/code-quality-coach` + `/code-reviewer` agent (in sequence) |
| **Merging to main** | Coach passes + code-reviewer passes = safe to push |

---

## What Coach DOES NOT Check

❌ Functional correctness (use code-reviewer agent for that)  
❌ Security vulnerabilities (use security-auditor agent)  
❌ Moodle API compliance (use database.md + api.md rules)  
❌ Test coverage (use test-writer agent)  

**Coach is lightweight** — focused on *code quality* and *adherence to Karpathy principles*, not security/correctness.

---

## Output Format

Coach runs silently on passes. On violations:

```
══════════════════════════════════════════════════════
  Code Quality Coach — Karpathy Principles Report
══════════════════════════════════════════════════════

File: moodle-enhancement/theme/airpayux/scss/moodle/custom_changes.scss
Lines: 1247 | Issues: 1

[Simplicity First]
  Line 342: Nested 4 levels deep
    .airpay-card__body .nav__item a span.badge.active::before {
    
  Suggest: Extract .airpay-badge-active class; keep nesting ≤2 levels
           Reason: Easier to maintain, test, override in subthemes

══════════════════════════════════════════════════════
  1 issue (warning) | Commit allowed. Good catch!
══════════════════════════════════════════════════════
```

---

## FAQ

**Q: Coach flagged my code as non-compliant. Does that block commit?**  
A: No — checks 11–14 are warnings only. They educate; they don't enforce. If you disagree with a suggestion, commit anyway and add a comment: `// Karpathy-aware: complexity needed here because...`

**Q: Coach checks seem slow. How can I disable them?**  
A: In `settings.json`, set `code-quality-coach.blockOnFailure: false` and `timeout: 10000`. Or run `bash .claude/hooks/pre-commit.sh --skip-coach` to skip this specific check.

**Q: Can I add custom Karpathy rules for my team?**  
A: Yes. Edit `checks/11-14-custom.sh` in this skill directory. Patterns: regex + suggestion + principle name. Each check takes ~100ms to run.

---

## References

- **Andrej Karpathy Skills:** https://github.com/forrestchang/andrej-karpathy-skills
- **Ponytail** (MIT, paraphrased into "Write only what the task needs", 2026-11-12): https://github.com/dietrichgebert/ponytail at commit 96608e078b2d5ea607c5c3b8b5a54d1c1fba6846
- **Airpay CLAUDE.md:** Root `CLAUDE.md` Section 5 (Coding Standards)
- **Your Project State:** `moodle-enhancement/PROJECT-STATE.md`
