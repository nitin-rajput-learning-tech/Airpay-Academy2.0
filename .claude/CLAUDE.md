# .claude/ Tooling Reference — Sentientia LMS (Airpay L&D OS)
# Quick-reference index. Primary instructions: root CLAUDE.md (v5.0) + moodle-enhancement/PROJECT-STATE.md.
# Brought to current facts 2026-11-12 (Stage 0 of docs/research/2026-11-12-github-tooling/PLAN.md).

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
| `refactorer` | Legacy code | Before/after diff pairs; N+1 elimination; class extraction; PHP 8.2–8.4 fixes |
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
| `frontend-design/SKILL.md` | Any theme/sentientia/**, *.mustache, *.scss | Theme guidance for `theme/sentientia`: Brand Book 2026-06 tokens, file map, deploy cycle. Its Sprint 1 (Navbar, Footer, Login) code is kept as historical reference only |
| `code-quality-coach/SKILL.md` | On request: `/code-quality-coach [file]` | Karpathy principles check (think first, keep it simple, surgical changes, goal-driven) before a commit |

---

## Hooks (`hooks/`)

| Script | When | Checks |
|--------|------|--------|
| `pre-commit.sh` | Every `git commit` | 20 checks: PHP syntax, MOODLE_INTERNAL, superglobals, credentials, .env, core files, SOP protection, SCORM ZIP, version.php format, CONFIRM placeholders, conflict markers, state-card freshness, Mustache comment leak, stale `theme_airpayux` refs, `standard_end_of_body_html`, AMD src/build parity, en/hi lang parity, tenant path boundary, cross-tree plugin twins, SITEID comparisons |
| `lint-on-save.sh` | After every Edit/Write (PostToolUse; see the Settings block: as wired it most likely gets an empty path and lints nothing) | Per-filetype: PHP (10 checks), SCSS (colour vars, BEM), Mustache (XSS, strings, CSRF), XML (XMLDB + SCORM), Python (API security). Run it by hand: `bash .claude/hooks/lint-on-save.sh <file>` |
| `code-quality-coach.sh` | Written as a save-time checker for the `code-quality-coach` skill; NOT currently wired into settings.json | Karpathy-principle warnings (non-blocking) |

**Install pre-commit hook (per clone; `.git/hooks/` is not tracked):**
```powershell
pwsh -File tools/install-hooks.ps1
# or: Copy-Item ".claude\hooks\pre-commit.sh" ".git\hooks\pre-commit" -Force
```

---

## Settings (`settings.json`) — Key Config

```
Model:           claude-sonnet-5-5
enabledPlugins:  vercel@claude-plugins-official = false (noise on a Moodle/PHP repo;
                 kept user-globally for Nitin's Vercel projects)
                 remember@claude-plugins-official = false (its PostToolUse hook has
                 no timeout and hung workflow subagents, 2026-11-12)
Hook timeouts:   Claude Code reads a hook "timeout" in SECONDS: 15 (PostToolUse) and 5
                 (PreToolUse). Until 2026-11-13 they were 15000 and 5000 (about 4 h and 83 min),
                 no real limit: the same failure class as the remember hook.
PostToolUse:     lint-on-save.sh after every Edit/Write. It is passed
                 "${CLAUDE_TOOL_INPUT_FILE_PATH}", which Claude Code does not set (tool input
                 arrives as JSON on stdin), so the script most likely gets an empty path,
                 prints its usage line and exits 0: it currently lints nothing (unverified live).
PreToolUse:      node check meant to block Remove-Item, rm -rf, DROP TABLE, ALTER TABLE,
                 git push --force; currently a no-op (exits 1, which does not block (only
                 exit 2 does), reads an env var Claude Code does not set, and matches Bash
                 only, so PowerShell calls are never checked). permissions.deny below is
                 the real guard.
Auto-allowed:    php -l, php -r, Get-ChildItem, Get-Content, Select-String, git status/diff/log/add,
                 Copy-Item into C:\xampp\htdocs\moodle5\public\ (theme\sentientia, local\, blocks\)
Auto-denied:     Remove-Item, rm -r/-rf, del /f, git push --force/-f, git reset --hard,
                 git clean -f, DROP TABLE/DATABASE, ALTER ... DROP, curl to ElevenLabs/Gamma,
                 web requests to airpay.academy, Copy-Item into moodle5\public core dirs
                 (lib, admin, course, mod\assign, mod\quiz)
Env vars:        AIRPAY_MOODLE_LOCAL (http://localhost:8080), AIRPAY_MOODLE_PROD, AIRPAY_UAT,
                 AIRPAY_THEME_SRC / AIRPAY_THEME_DST (theme\sentientia),
                 AIRPAY_PLUGINS_SRC (moodle-enhancement\local) / AIRPAY_PLUGINS_DST (moodle5\public),
                 AIRPAY_SCORM_OUTPUT, AIRPAY_PROTOTYPES, AIRPAY_STATE_CARDS, AIRPAY_PROJECT_STATE,
                 MOODLE_ROOT (C:\xampp\htdocs\moodle5\public), MOODLE_VERSION, MOODLE_PHP,
                 AIRPAY_TENANT_ID=1, PUBLIC_TENANT_ID=77, ZEEA_TENANT_ID=177
Still stale:     the informational "context" and "_meta" blocks in settings.json (Phase 6B,
                 Moodle 4.5.10). Claude Code does not act on them; the root CLAUDE.md wins.
```

---

## Current Context (Sentientia LMS, 2026-11-12)

```
Product:  Sentientia LMS. Airpay Academy is customer zero. Sentientia is NOT live yet:
          all user data on local and UAT is a test import of a production backup.
          Live https://www.airpay.academy/ is still BizLMS on Moodle 4.1.2; never patch it.
          Path to live: UAT sign-off -> Stage B live-backup rehearsal -> cutover with all data
          (each step on Nitin's call).
Moodle:   5.1.3+ locally (XAMPP); target 5.3 LTS (ADR-033); UAT runs 5.3.
          Local 5.3 runtime: D:\Claude Local\moodle53 (PHP 8.4, MariaDB 11.4 on 127.0.0.1:3308).
UAT:      https://academy2.airpay.ninja (ssh uat-lms over VPN + tunnel; deploy with
          tools/uat/deploy_to_uat.sh, dry run by default). UAT deploys and flag flips on
          Nitin's call only. Never type credentials on academy2.airpay.ninja.
Theme:    sentientia (standalone, $THEME->parents = [], the former airpayux fork).
          Repo: theme\sentientia\ only. theme\airpayux\ and theme\epsilon\ are retired, not served.
Design:   Brand Book 2026-06: Primary #0066A7 | Accent #1985DD (teal #0f7a73 retired) |
          BG #F2F4FB | Montserrat | 8px grid. Tokens: theme\sentientia\scss\moodle\_tokens.scss
Plugins:  every plugin exists twice, e.g. moodle-enhancement\local\<plugin>\ AND local\<plugin>\;
          both trees are deployed and must stay byte-identical. php tools/check-tree-drift.php
          checks local\, payment\gateway\, enrol\, mod\quiz\accessrule\ and admin\tool\.
Target:   22 C-suite prototypes at D:\Claude Local\Moodle Backup\03-prototypes\preview\
Source:   moodle-enhancement/PROJECT-STATE.md (read first each session)
Tenants:  Airpay id=1, Public id=77, ZEEA id=177 (3 tenants — all queries MUST be scoped;
          bound tenant paths with \local_sentientia_platform\tenant::path_descendant_filter())
Tenant detection: Use open_path (NOT open_costcenterid — column doesn't exist on production)
Local Moodle (XAMPP): C:\xampp\htdocs\moodle5\public\ served at http://localhost:8080/
                       (wwwroot has no /moodle). config.php and admin\cli\ are one level up
                       in moodle5\ (run CLI tools with cwd = public\, e.g.
                       php ..\admin\cli\purge_caches.php). PHPUnit runs from moodle5\.
                       Local XAMPP can lag the repo: diff before testing.
                       htdocs\moodle-4.5-archive\ (old 4.5 codebase) and htdocs\moodle5.2\
                       are not the localhost:8080 site. Do NOT deploy there.
GitHub:   nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch;
          work branch claude/gap-integration)
Prod DB:  MySQL 8.0.44 on AWS RDS (open_costcenterid resolved dynamically by BizLMS at runtime)
```

---

## Escalation — Stop and Tell Nitin

```
□ DB schema change needed on production (live.airpay.academy)
□ Fix requires touching Moodle core (C:\xampp\htdocs\moodle5\public\lib\, admin\ ...)
  without an ADR + moodle-enhancement/docs/core-mods/ record
□ Any UAT deploy, UAT feature-flag flip, or live (airpay.academy) change: Nitin's call only
□ API call sends employee PII (names, IDs, salary) externally
□ SCORM fails validation after 2 repackaging attempts
□ Same PHP error after 3 different fix attempts
□ Task requires > 50 file operations
□ Compliance content needs L&D review before publish
```
