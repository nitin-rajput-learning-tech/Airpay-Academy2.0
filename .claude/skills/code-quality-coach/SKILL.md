# Code Quality Coach — Karpathy Principles Validator
**Version:** 1.0 | **For:** Airpay L&D OS (Phase 6B+) | **Trigger:** Pre-write validation or `/code-quality-coach [file]`

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
- **Airpay CLAUDE.md:** Root `CLAUDE.md` Section 5 (Coding Standards)
- **Your Project State:** `moodle-enhancement/PROJECT-STATE.md`
