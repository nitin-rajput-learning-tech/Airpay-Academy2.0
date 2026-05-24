# .claude/ Tooling Reference — Airpay L&D OS
# Quick-reference index. Primary instructions: root CLAUDE.md + PROJECT-STATE.md.

---

## Instant Decision Guide

```
I need to...                          → Use
─────────────────────────────────────────────────────────
Review PHP/plugin code                → Agent: code-reviewer
Diagnose error / blank page / SCORM   → Agent: debugger
Write PHPUnit tests                   → Agent: test-writer
Modernise legacy code / N+1 queries   → Agent: refactorer
Write state card / README / API doc   → Agent: doc-writer
Audit security before production      → Agent: security-auditor
Fix a specific issue                  → /fix-issue [description]
Deploy theme or plugin to XAMPP       → /deploy theme|plugin|scorm
Review before merging to production   → /pr-review
Edit a theme file (mustache/scss)     → Skill auto-loads frontend-design
Edit a DB query                       → rules/database.md auto-loads
Edit an API call                      → rules/api.md auto-loads
```

---

## Agents (`agents/`) — Capabilities Summary

| Agent | Trigger | Key outputs |
|-------|---------|-------------|
| `code-reviewer` | PHP code review | Security violations with exact fix code; standards check; completeness; performance |
| `debugger` | Error/bug report | Root cause from logs; error→fix lookup table; verification steps |
| `test-writer` | "write tests" | Complete PHPUnit classes; security, tenant isolation, SCORM, CRUD tests |
| `refactorer` | Legacy code | Before/after diff pairs; N+1 elimination; class extraction; PHP 8.2 fixes |
| `doc-writer` | Documentation | State cards, PROJECT-STATE updates, plugin READMEs, SCORM guides, API refs |
| `security-auditor` | Pre-deploy review | OWASP-mapped findings; GDPR check; tenant isolation audit; exact remediation |

---

## Commands (`commands/`)

| Command | What it does autonomously |
|---------|--------------------------|
| `/fix-issue [desc]` | Reads error log → classifies → fixes → verifies → updates state card |
| `/deploy theme\|plugin\|scorm` | Validates → copies → upgrades → purges → prints test checklist |
| `/pr-review` | Diffs vs production → security + standards + completeness + tenant isolation → verdict |

---

## Always-Loaded Rules (`rules/`)

Auto-loaded based on file being edited — no need to ask:

| File | Loaded when | Contains |
|------|-------------|---------|
| `frontend.md` | Editing SCSS/Mustache/layout PHP | Full design tokens, template context vars, BEM naming, deploy cycle, anti-patterns |
| `database.md` | Editing PHP with $DB calls | Complete $DB API, XMLDB template, caching patterns, multi-tenant scoping, N+1 fixes |
| `api.md` | Editing API calls | Moodle REST patterns, ElevenLabs, Gamma, Azure, [CONFIRM] gates, error handling |

---

## Skills (`skills/`)

Auto-injects on matching file paths:

| Skill | Triggers on | Contains |
|-------|-------------|---------|
| `frontend-design/SKILL.md` | Any theme/airpayux/**, *.mustache, *.scss | Complete Sprint 1 implementation (Navbar, Footer, Login) with copy-paste SCSS + Mustache |

---

## Hooks (`hooks/`)

| Script | When | Checks |
|--------|------|--------|
| `pre-commit.sh` | Every `git commit` | PHP syntax, MOODLE_INTERNAL, superglobals, credentials, .env, core files, SOP protection, SCORM ZIP, version.php format, CONFIRM placeholders |
| `lint-on-save.sh` | On every file save (PostToolUse) | Per-filetype: PHP (10 checks), SCSS (colour vars, BEM), Mustache (XSS, strings, CSRF), XML (XMLDB + SCORM), Python (API security) |

**Install pre-commit hook:**
```powershell
Copy-Item ".claude\hooks\pre-commit.sh" ".git\hooks\pre-commit"
```

---

## Settings (`settings.json`) — Key Config

```
Model:           claude-sonnet-4-6
PostToolUse:     lint-on-save.sh runs automatically after every Edit/Write
PreToolUse:      Bash hook blocks Remove-Item, force-push, DROP TABLE etc.
Auto-allowed:    php -l, Get-ChildItem, Copy-Item to XAMPP (theme/local/blocks)
Auto-denied:     rm, git push --force, DROP/ALTER TABLE, core file copies
Env vars:        AIRPAY_THEME_SRC, AIRPAY_PLUGINS_SRC, AIRPAY_SCORM_OUTPUT,
                 MOODLE_ROOT, MOODLE_VERSION, AIRPAY_PROTOTYPES, tenant IDs
```

---

## Current Context (Academy 2.0)

```
Phase:    Academy 2.0 — Production Stabilization + Platform Upgrade
Theme:    airpayux v1.0.0 (standalone fork, $THEME->parents = [], 514 files)
Design:   Primary #0066A7 | Accent #0f7a73 | BG #F2F4FB | Montserrat | 8px grid
Target:   22 C-suite prototypes at D:\Claude Local\Moodle Backup\03-prototypes\preview\
Source:   moodle-enhancement/PROJECT-STATE.md (read first each session)
Tenants:  Airpay id=1, Public id=77, ZEEA id=177 (3 tenants — all queries MUST be scoped)
Tenant detection: Use open_path (NOT open_costcenterid — column doesn't exist on production)
Local Moodle (XAMPP): Moodle 5.1.3+ at C:\xampp\htdocs\moodle5\public\ (Apache
                       alias /moodle → moodle5\public\). admin/cli is at
                       moodle5\admin\cli\ (run CLI tools with cwd = public\).
                       moodle-4.5-backup\ is a stale 4.5 codebase that shares
                       the same DB but is NOT served. Do NOT deploy there.
GitHub:   nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch)
Prod DB:  MySQL 8.0.44 on AWS RDS (open_costcenterid resolved dynamically by BizLMS at runtime)
```

---

## Escalation — Stop and Tell Nitin

```
□ DB schema change needed on production (live.airpay.academy)
□ Fix requires touching C:\xampp\htdocs\moodle\lib\ or core admin/
□ API call sends employee PII (names, IDs, salary) externally
□ SCORM fails validation after 2 repackaging attempts
□ Same PHP error after 3 different fix attempts
□ Task requires > 50 file operations
□ Compliance content needs L&D review before publish
```
