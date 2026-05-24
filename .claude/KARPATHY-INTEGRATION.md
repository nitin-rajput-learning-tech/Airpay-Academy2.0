# Karpathy Principles Integration — Airpay L&D OS

**Status:** ✅ Implemented | **Date:** April 2026 | **For:** Phase 6B + SENTIENTIA

---

## What Just Happened

You now have **4 Karpathy Principles** (from andrej-karpathy-skills) embedded into your `.claude/` system:

| Principle | Implementation | When It Runs | Block? |
|-----------|---|---|---|
| **Think Before Coding** | Check 11 — function docblocks | Pre-commit + on save | ⚠️ Warn only |
| **Simplicity First** | Checks 12–13 — magic numbers, unused imports | Pre-commit + on save | ⚠️ Warn only |
| **Surgical Changes** | Check 14 — file category audit | Pre-commit only | ⚠️ Warn only |
| **Goal-Driven** | Check 4B — error handling in APIs | On save | ⚠️ Warn only |

**Key fact:** All warnings are **non-blocking**. They educate; they don't enforce. You can override and commit anyway.

---

## Files Created/Enhanced

```
.claude/
├── skills/code-quality-coach/SKILL.md          ← NEW (documentation + manual invocation)
├── hooks/pre-commit.sh                         ← ENHANCED (checks 11-14 added)
├── hooks/code-quality-coach.sh                 ← NEW (save-time validation)
├── settings.json                               ← ENHANCED (PostToolUse hook added)
└── KARPATHY-INTEGRATION.md                     ← NEW (this file)
```

---

## How to Use

### 1. Real-Time Coaching (On Every File Save)

When you edit a `.php`, `.py`, or `.scss` file, the coach automatically runs:

```
⚠  CODE QUALITY (Think Before): Missing docblocks
   Line 42 — function 'buildNavbar' lacks /** @param @return @throws */

⚠  CODE QUALITY (Simplicity): Magic numbers detected
   Line 87 — extract to const: const CACHE_TTL = 300; at top of file
```

**Action:** If you agree, add the docblock/constant. If you disagree, commit anyway — it's just a warning.

### 2. Pre-Commit Enforcement (Before `git commit`)

When you run `git commit`, checks 11–14 run automatically:

```bash
→ [11/14] Function docblocks (Karpathy: Think Before)...
  ⚠ WARN: Missing docblock: plugins/local_trainer/lib.php:42 — function 'buildNavbar'

→ [12/14] Magic numbers/strings (Karpathy: Simplicity)...
  ⚠ WARN: Possible magic number/string: content/voice/generate.py:45

→ [13/14] Unused imports (Karpathy: Simplicity)...
  ✓ No suspicious magic numbers

→ [14/14] Surgical changes audit (Karpathy: One Deliverable)...
  ⚠ WARN: Commit touches 4 categories — recommend: split into focused commits
```

**Action:** Fix issues, or commit with warnings. They won't block you.

### 3. Manual Code Review

Invoke the skill mid-session for detailed feedback:

```bash
/code-quality-coach "moodle-enhancement/theme/airpayux/scss/custom_changes.scss"
```

Returns structured feedback with references to the SKILL.md documentation.

---

## When to Pay Attention

| Coach Flag | Severity | Action |
|---|---|---|
| Missing docblock on public function | 🟡 Medium | Add `/** @param @return @throws */` — helps Phase 6C review |
| Magic number (timeout, retry count) | 🟡 Medium | Extract to `const` at top of file — future-proofs when you refactor |
| Unused import in Python | 🟡 Medium | Delete or use it — keeps SENTIENTIA agents clean |
| Deep nesting (5+ levels) | 🟡 Medium | Extract helper function — improves testability |
| API call without error handling | 🔴 High | Add try-catch + assert — prevents silent failures in production |
| Commit touches 4+ categories | 🟡 Medium | Split into 2 commits — keeps git history focused |

---

## Integration with Your Workflow

### Phase 6B (Navbar → Footer → Login)

**Checkpoint:** Before writing SCSS, run coach on existing theme files:

```bash
/code-quality-coach "theme/airpayux/scss/moodle/custom_changes.scss"
```

**Expectation:** SCSS should have ≤2 levels of nesting (BEM compliance). Coach flags deeper nesting.

### SENTIENTIA (SOP Parser → Voice Generator)

**Checkpoint:** Before final Python agent commit:

```bash
/code-quality-coach "content/voice/generate_voice.py"
```

**Expectation:** All imports used, all API calls have error handling, no magic timeouts.

### Knowledge Automation (Azure Integration)

**Checkpoint:** Before committing OAuth/MSAL code:

```bash
/code-quality-coach "plugins/local_azure_auth/lib.php"
```

**Expectation:** Token handling has docblocks, error cases clear, no hardcoded secrets.

---

## FAQ

**Q: Coach flagged missing docblock. Do I have to add it?**
A: No — it's a warning. But missing docblocks make code review harder in Phase 6C. Recommend adding them for public functions.

**Q: Can I disable coach on save? It's slowing down my workflow.**
A: Yes. In `settings.json`, change `blockOnFailure: false` to `timeout: 2000` (faster) or comment out the hook entirely.

**Q: Coach says "Surgical changes — commit touches 4 categories." But I'm doing related work.**
A: The rule "one session = one deliverable" is about git history clarity, not technical constraint. If truly related, add a comment: `// Karpathy-aware: related changes in parser (SOP → JSON) and narration (JSON → TXT)`.

**Q: How do I add custom Karpathy rules for my team?**
A: Edit `code-quality-coach.sh` directly. Add new sections with:
```bash
if [ condition ]; then
    echo -e "${YEL}⚠  CODE QUALITY (Principle):${NC} Issue description"
    echo "   Suggestion: Fix it by doing..."
    ISSUES=$((ISSUES + 1))
fi
```

**Q: What if coach flags something that's intentional?**
A: Add a comment in your code:
```php
// Karpathy-aware: Magic timeout=30 is intentional for ElevenLabs rate limiting
$timeout = 30;  // KEEP THIS
```

---

## Reference

| Resource | Location |
|---|---|
| Karpathy Principles (full) | `.claude/skills/code-quality-coach/SKILL.md` |
| Pre-commit checks (11-14) | `.claude/hooks/pre-commit.sh` (lines 220-340) |
| Save-time coach | `.claude/hooks/code-quality-coach.sh` |
| Settings integration | `.claude/settings.json` (hooks section) |
| Original repo | https://github.com/forrestchang/andrej-karpathy-skills |

---

## Next Steps

1. ✅ **Verify the hook works:** Make a test commit with a function missing a docblock. Watch pre-commit.sh flag check 11.
2. ✅ **Test save-time feedback:** Edit a PHP file and save it. Watch console for coach output.
3. ✅ **Update your .claude/CLAUDE.md:** Add line to "Instant Decision Guide":
   ```
   I want code quality feedback       → Coach auto-runs on save; /code-quality-coach [file]
   ```
4. **Start Phase 6B with confidence:** Coach is now silently helping you follow Karpathy principles without slowing you down.

---

## Deactivation (If Needed)

To turn off coach completely:

1. In `settings.json`, remove the second hook from `PostToolUse`:
   ```json
   // DELETE THIS BLOCK:
   {
     "type": "command",
     "command": "bash .claude/hooks/code-quality-coach.sh..."
   }
   ```

2. In `pre-commit.sh`, comment out checks 11-14 (lines 220-340).

3. Delete `.claude/hooks/code-quality-coach.sh`.

But don't do this — the coach is non-intrusive and helps catch issues before they become debt.

---

**Ready to build Phase 6B with Karpathy principles baked in. 🚀**
