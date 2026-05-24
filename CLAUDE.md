CLAUDE.md — SENTIENTIA LMS / AIRPAY ACADEMY OPERATING SYSTEM
Version 5.0 | Owner: Nitin Rajput | Updated: 2026-05-20 (Day 0 — Sentientia LMS pivot)
Project Root: D:\Claude Local\airpay-ld-os\

---
## MISSION (NEW — Day 0, 2026-05-20)

We are building **Sentientia LMS** — a white-label, enterprise-grade LMS/LXP/SaaS
product. Airpay Academy is **customer-zero** — the first production deployment,
used to harden every feature against real-world enterprise scale (3,500+ users,
3 tenants, multi-tenant, multi-language).

The product is positioned for future sale to other enterprises. Every architectural
decision is made with TWO customers in mind:
  1. Airpay Payment Services (live customer, today)
  2. Hypothetical Enterprise N (any future customer, tomorrow)

**Backwards compatibility with Airpay Academy's current production is non-negotiable.**
New features ship as additive, feature-flagged extensions. Default behaviour
matches what airpay.academy users see today, until a feature flag flips.

---
## SESSION START
1. Read this CLAUDE.md
2. Read `moodle-enhancement\PROJECT-STATE.md` (source of truth for current phase)
3. Read latest ADR in `moodle-enhancement\docs\adr\` (architectural context)
4. For plugin work: read state card from `moodle-enhancement\state-cards\`
5. One session = one deliverable. Update PROJECT-STATE.md at session end.
6. Every UI-touching session ends with screenshots in `docs\visual-evidence\YYYY-MM-DD\`.

---
## 1. IDENTITY

Nitin Rajput — Head of L&D, Airpay Payment Services
Mission: Build Sentientia LMS, with Airpay Academy as customer-zero.

| Workstream | Description | Status |
|------------|-------------|--------|
| **0 — Sentientia LMS Foundation** | Fork + branding + design system + multi-customer architecture | **ACTIVE (Day 0, 2026-05-20)** |
| A — Plugin Suite | 30 `local_airpay_*` plugins (now `local_sentientia_*` over time) | Active, refactoring toward product |
| B — SENTIENTIA Content Pipeline | SOP → SCORM automation — becomes a SELLABLE FEATURE of the product, not a sibling workstream | Planned |
| C — Knowledge Automation | Microsoft 365 integration — becomes a sellable feature | Planned |
| D — Mobile (PWA + native wrapper) | Phase X.1/X.2 WS surface, PWA, Cordova/Capacitor wrappers | Planned |
| E — Live engagement (Mentimeter clone) | `local_sentientia_live` plugin — real-time polls, quizzes, Q&A | Planned |
| F — WhatsApp deepening | `local_airpay_whatsapp` extended for course-content notifications | Planned |
| G — AI features | Course recommendations, quiz generation, content translation | Planned (requires Anthropic API key) |

---
## 2. ENVIRONMENT

| Component | Value |
|-----------|-------|
| Local Moodle | 5.1.3+ at `C:\xampp\htdocs\moodle5\public\` (Apache alias `/moodle` → `moodle5\public\`) |
| CLI tools | `C:\xampp\htdocs\moodle5\admin\cli\` (run with cwd = `public\`) |
| PHP | 8.2.12 |
| MariaDB | 10.11.16 |
| Apache | 2.4.58 on port 8080 |
| Local URL | http://localhost:8080/moodle/ |
| Production | https://www.airpay.academy/ (Airpay Academy customer-zero deployment) |
| Active Theme | **airpayux** — standalone fork of epsilon (`$THEME->parents = []`) |
| Multi-tenant (current) | BizLMS costcenter (Airpay id=1, Public id=77, ZEEA id=177) |
| Multi-customer (future) | New `local_sentientia_core` layer above tenant — each customer = top-level tenant tree |
| Tenant detection | `$USER->open_path` (NOT `open_costcenterid` — column doesn't exist on production) |
| Dashboard | `/my/dashboard.php` (NOT `/my/index.php`) |
| GitHub | `nitin-rajput-learning-tech/Airpay-Academy2.0` (production branch) |
| Production DB | MySQL 8.0.44 on AWS RDS |
| Deploy | File copy to server → Admin → Notifications → purge caches |

---
## 3. PERMISSIONS

**Write without asking** — any file in `D:\Claude Local\airpay-ld-os\**`
Approved types: `.php .js .css .scss .html .xml .json .yaml .yml .md .txt .py .sh .mustache`

**Core file modifications NOW PERMITTED** (Day 0 change). Every core mod MUST be:
  1. Documented in `docs\core-mods\YYYY-MM-DD-<change>.md` with file, line, before/after, justification
  2. Tagged with `// SENTIENTIA-CORE-MOD: <reason>` at the modification site
  3. Tested for upgrade-safety (record how it merges with future upstream Moodle pulls)

**Run without asking:**
```
php -l, php -r, Select-String, Get-ChildItem, Get-Content
Compress-Archive, python, pip install, npm install
git add/commit/push to nitin-rajput-learning-tech/Airpay-Academy2.0:production
```

**ALWAYS confirm first ([CONFIRM] tag):**
```
Any delete/Remove-Item command
Writes outside D:\Claude Local\
POST to LIVE Moodle REST API (live.airpay.academy)
POST to ElevenLabs / Gamma / Anthropic (costs money)
DB migrations on live (ALTER, DROP)
Anything tagged [CONFIRM]
Production deploy of any plugin/theme/core change
```

---
## 4. WORKFLOW RULES

- **Write-to-disk:** Generate each file → write to disk → then next file. Never hold >3 files in context.
- **Atomic builds:** A partial plugin is a broken plugin. A partial feature flag is a broken switch. Ship whole.
- **State cards:** Before plugin work, create/read `state-cards/[pluginname]-state.md`.
- **ADRs (NEW):** Every cross-cutting architectural decision lands as an Architecture Decision Record in `docs\adr\ADR-NNN-<slug>.md`.
- **SCSS strategy:** Edit component-by-component. Use find-and-replace, not full rewrites.
- **Context loss:** STOP coding. Re-read state card + last written file from disk. Never guess.
- **Theme workflow:** Edit SCSS/template → deploy to local → purge cache → Ctrl+Shift+R → test as Learner role → test mobile viewport.

---
## 5. CODING STANDARDS

### PHP (Moodle plugins + selective core mods)
```php
// Every PHP file:
defined('MOODLE_INTERNAL') || die();

// Input (never raw $_GET/$_POST):
$id = required_param('id', PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);

// Output (never echo raw strings):
echo format_string($text);   // trusted content
echo s($value);              // HTML attributes

// Page setup:
require_login();
$context = context_system::instance();
require_capability('local/pluginname:view', $context);
$PAGE->set_url('/local/pluginname/index.php');
$PAGE->set_title(get_string('pluginname', 'local_pluginname'));
echo $OUTPUT->header();
echo $OUTPUT->footer();

// DB API (never raw SQL when API exists, always {tablename}):
$DB->get_record('table', ['field' => $val], '*', MUST_EXIST);
$DB->get_records('table', ['field' => $val]);
$DB->insert_record('table', $obj);
$DB->update_record('table', $obj);  // must have ->id
$DB->delete_records('table', ['id' => $id]);
$DB->get_records_sql("SELECT * FROM {user} WHERE id = :id", ['id' => $id]);
```

### Feature flags (mandatory for every new feature)
Every new user-visible feature ships behind a feature flag in
`local_airpay_core\feature_flags`. Default OFF. Per-customer + per-tenant
override supported. See `local_airpay_core\db\feature_flags.php` for
registration pattern.

```php
if (!\local_airpay_core\feature_flags::is_enabled('sentientia_live_polls', $customerid, $tenantid)) {
    return;  // feature flag off — render nothing
}
```

### Visual evidence (mandatory for every UI session)
Every session that touches UI ends with:
1. Screenshots (desktop + mobile breakpoint) saved to `docs\visual-evidence\YYYY-MM-DD\`
2. A short `README.md` in the same folder noting what changed
3. Nitin reviews before merge

---
## 6. PLUGIN FILE CHECKLISTS

**Local plugin** — `local_[name]` → deploy to `/moodle5/public/local/[name]/`
```
REQUIRED: version.php, lang/en/local_[name].php, index.php
IF DB:    db/install.xml, db/upgrade.php
IF CAPS:  db/access.php
IF SETTINGS: settings.php
IF FLAGS: db/feature_flags.php
COMMON:   lib.php, classes/*.php, templates/*.mustache
HINDI:    lang/hi/local_[name].php (100% parity required — drive enforced today)
```

**Block plugin** — `block_[name]` → deploy to `/moodle5/public/blocks/[name]/`
```
REQUIRED: version.php, block_[name].php (extends block_base), lang/en/block_[name].php
IF DB:    db/install.xml, db/upgrade.php
IF CAPS:  db/access.php
```

**Activity module** — `mod_[name]` → deploy to `/moodle5/public/mod/[name]/`
```
REQUIRED: version.php, mod_form.php, index.php, view.php, lib.php,
          lang/en/[name].php, db/install.xml, db/upgrade.php, db/access.php
LIB CALLBACKS: [name]_add_instance, [name]_update_instance, [name]_delete_instance
```

**version.php template:**
```php
<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_[name]';
$plugin->version   = 2026052100;  // YYYYMMDDNN
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
```

---
## 7. THEME — airpayux (Sentientia LMS design system base)

Standalone fork. `$THEME->parents = []`. We own all 514+ files.
Local path: `C:\xampp\htdocs\moodle5\public\theme\airpayux\`
Working path: `D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\`

**Design system (current — will evolve to Sentientia design system in Phase 2):**
```
Primary: #0066A7  |  Accent: #0f7a73  |  BG: #F2F4FB
Font: Montserrat 400-800  |  Spacing: 8px base  |  Radius: 8-20px
```

**Per-customer branding (NEW capability):**
Each Sentientia customer (today: just Airpay) can override:
  - Logo (light + dark)
  - Primary + accent + BG colours
  - Typography (font family)
  - Favicon
Implemented via `local_airpay_core::get_customer_branding()` consumed by `core_renderer`.

**Key files:**
- `templates/navbar.mustache` — navbar HTML
- `templates/footer.mustache` — footer HTML
- `templates/core/loginform.mustache` + `layout/login.php` — login page
- `layout/dashboard.php` — dashboard PHP
- `scss/moodle/custom_changes.scss` — main SCSS overrides
- `scss/moodle/custom_media.scss` — responsive breakpoints
- `classes/output/core_renderer.php` — per-tenant + per-customer branding

---
## 8. SCORM PACKAGING (SCORM 1.2)

Sellable feature — Sentientia LMS's SOP→SCORM pipeline.
```
[course]-scorm.zip (root level):
├── imsmanifest.xml    ← MUST be at ZIP root
├── index.html         ← launch file
├── scormdriver.js     ← SCORM API bridge
├── slides/ audio/ images/ css/
```

**Validation gates (all must pass before [CONFIRM] to upload):**
- imsmanifest.xml at ZIP root (not in subfolder)
- `<organizations default="ORG_01">` has items, href matches real files
- masteryscore = 70 (Airpay default; configurable per customer)
- All files in manifest exist in ZIP

**ZIP creation (CRITICAL — wrong method = Moodle rejection):**
```powershell
Set-Location "content\scorm-output\[coursename]"
Compress-Archive -Path * -DestinationPath "..\[coursename]-scorm.zip"
```

---
## 9. SENTIENTIA CONTENT PIPELINE (sellable feature)

Each agent = one session. Output to disk. Next agent reads from disk. Never chain.

| Agent | Input | Output | Rule |
|-------|-------|--------|------|
| 1 SOP Parser | `content/sops/*.pdf` | `content/parsed/*-parsed.json` | Max 2000 words |
| 2 Narration | parsed JSON | `content/narrations/*-narration.txt` | 25-word sentences, 130wpm |
| 3 Slides | narration | `content/slides/*-slides.json` | Max 5 bullets, 8 words each |
| 4 Voice | narration | `content/voice/*-voice.mp3` | ElevenLabs = [CONFIRM] |
| 5 SCORM Pack | slides + audio | `content/scorm-output/*-scorm.zip` | Validate before ZIP |
| 6 Upload | SCORM ZIP | Moodle | [CONFIRM] — live server |

---
## 10. REST API

```
READ (no confirm): core_course_get_courses, core_user_get_users,
  core_enrol_get_enrolled_users, core_completion_get_activities_completion_status,
  mod_scorm_get_scorm_scoes, local_airpay_*_list_*
WRITE ([CONFIRM]): core_course_create_courses, core_files_upload,
  any state-changing local_airpay_* WS to live server
```
Credentials in `.env` (never commit). Never log tokens.

**Mobile-app surface (per `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`):**
- 22 MOBILE-READY WS endpoints ready for Phase X.1
- 14 learner-write endpoints ready for Phase X.2
- 36 SENSITIVE-ADMIN endpoints permanently desktop-only

---
## 11. ENV VARS (.env — never commit)
```
MOODLE_URL=https://www.airpay.academy
MOODLE_TOKEN=...
ELEVENLABS_API_KEY=...        # SENTIENTIA Agent 4
ELEVENLABS_VOICE_ID=...
GAMMA_API_KEY=...             # Slide gen
ANTHROPIC_API_KEY=...         # AI features (quiz gen, translation, content)
AZURE_CLIENT_ID=...           # M365 SSO (future)
AZURE_CLIENT_SECRET=...
AZURE_TENANT_ID=...
WHATSAPP_API_TOKEN=...        # WhatsApp Business API
WHATSAPP_PHONE_ID=...
DB_HOST=localhost  DB_NAME=moodle  DB_USER=moodleuser  DB_PASS=...
```

---
## 12. ESCALATION FLAGS — Stop and check with Nitin

- DB schema change on live (`live.airpay.academy`)
- Core file change without ADR + doc-mod record
- API sends employee PII externally
- Compliance content needs review before publish
- SCORM fails validation after 2 attempts
- Same PHP error repeats 3+ times
- Task exceeds 50 file operations
- A feature is about to ship without a feature flag
- A UI change is about to commit without visual evidence

---
## 13. ABSOLUTE RULES (revised Day 0)

```
NEVER ship a feature without a feature flag (default OFF)
NEVER break Airpay Academy current production behaviour
NEVER hardcode credentials — always .env
NEVER generate partial plugins — complete set or nothing
NEVER skip SCORM validation before packaging
NEVER delete files in content/sops/
NEVER chain SENTIENTIA agents — one per session
NEVER POST to live Moodle / ElevenLabs / Gamma / Anthropic without [CONFIRM]
NEVER use raw SQL when $DB API exists
NEVER echo unescaped input — always s() or format_string()
NEVER write project files outside D:\Claude Local\
NEVER touch core files without an ADR + entry in docs/core-mods/
NEVER ship UI changes without screenshots in docs/visual-evidence/
NEVER mark a session "done" without updating PROJECT-STATE.md
```

**RULE REMOVED (Day 0):** "NEVER modify Moodle core files" — replaced with the
docs/core-mods/ tracking discipline above. Core changes are permitted when
justified and recorded.

### Pre-commit guards (P0 cleanup A, 2026-05-24)

The local pre-commit hook (`.claude/hooks/pre-commit.sh`) and the
GitHub CI gate (`.github/workflows/ci.yml::conflict-marker-check`) form
a two-layer defence against the failure mode that broke CI runs #397
and #403 — mid-merge commits with stray `<<<<<<<` / `=======` /
`>>>>>>>` markers in PHP and lang files.

| Layer | Catches at | Scope | Bypass risk |
|-------|------------|-------|-------------|
| `.claude/hooks/pre-commit.sh` CHECK 11 | `git commit` time | STAGED `.php .mustache .scss .js .json .xml .md .yml` files | `--no-verify`, GUI tools that skip hooks, the hook not being installed |
| `.github/workflows/ci.yml::conflict-marker-check` | Push to `production` + every PR | Whole working tree across `moodle-enhancement/`, `theme/airpayux/`, `local/`, `.github/`, `.claude/` | None — every push triggers it |

Both layers use the same regex, matching git's exact marker format
only:

```
^<<<<<<<( |$)    '<<<<<<< HEAD' or bare '<<<<<<<'
^=======$        bare '=======' alone (not 32-wide '=====' banners
                 or '// ====' SCSS comment dividers)
^>>>>>>>( |$)    '>>>>>>> branch' or bare '>>>>>>>'
```

Verified clean against the existing repo. Setext-style help-text
banners (`================` in a `<<<EOT` heredoc) and Mustache
parent-template inheritance (`{{<base/columns}}`) do not false-positive.

**Install the local hook (one-liner):**

```powershell
pwsh -Command "Copy-Item .claude/hooks/pre-commit.sh .git/hooks/pre-commit -Force"
```

Or run the wrapper from the repo root:

```powershell
pwsh -File tools/install-hooks.ps1
```

The hook is per-clone (`.git/hooks/` is not tracked), so every fresh
clone or worktree needs a one-time install. The CI gate is global —
it runs on every push to `production` regardless of who pushed.

---
## 14. GIT PROTOCOL

Push to GitHub after every session.
Primary repo: `nitin-rajput-learning-tech/Airpay-Academy2.0` (production branch)
Personal backup repo: maintained by Nitin separately (IP hedge — see ADR-001)
Local backups: `D:\Claude Local\Moodle Backup\`
Prototypes: `D:\Claude Local\Moodle Backup\03-prototypes\preview\` (22 C-suite approved — used as UX reference for Sentientia design system v1)

---
## 15. CURRENT PHASE

**Day 0 (2026-05-20) — Sentientia LMS Foundation**

Wave 1 + Wave 2 of the parity audit are COMPLETE (10/10 P0s, 60/60 P1s, Hindi
parity at 100%, reCAPTCHA + mobile-WS audit shipped). Today we pivot from
"patch the Moodle deployment" to "build the Sentientia LMS product".

See latest ADR + PROJECT-STATE.md for current session focus.
