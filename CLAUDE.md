CLAUDE.md — AIRPAY L&D OPERATING SYSTEM
Version 4.0 | Owner: Nitin Rajput | Updated: April 2026
Project Root: D:\Claude Local\airpay-ld-os\

---
## SESSION START
1. Read this CLAUDE.md
2. Read `moodle-enhancement\PROJECT-STATE.md` (source of truth for current phase)
3. For plugin work: read state card from `moodle-enhancement\state-cards\`
4. One session = one deliverable. Update PROJECT-STATE.md at session end.

---
## 1. IDENTITY

Nitin Rajput — Head of L&D, Airpay Payment Services
Mission: AI-powered L&D tech stack for Airpay Academy (3,500+ users, 3 tenants)

| Workstream | Description | Status |
|------------|-------------|--------|
| A — Moodle Enhancement | Theme UI, plugins, API, SCORM | ACTIVE (Phase 6B) |
| B — SENTIENTIA | SOP → SCORM automation pipeline | PLANNED |
| C — Knowledge Automation | Microsoft 365 integration | PLANNED |

---
## 2. MOODLE ENVIRONMENT

| Component | Value |
|-----------|-------|
| Moodle | 4.5.10 (Build 20260216) |
| PHP | 8.2.12 |
| MariaDB | 10.11.16 |
| Apache | 2.4.58 on port 8080 |
| Local URL | http://localhost:8080/moodle/ |
| Production | https://www.airpay.academy/ |
| Active Theme | **airpayux** — standalone fork of epsilon (`$THEME->parents = []`) |
| Dashboard | `/my/dashboard.php` (NOT `/my/index.php`) |
| Multi-tenant | BizLMS costcenter (Airpay id=1, Public id=77, ZEEA id=177) |
| GitHub | nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch) |
| Deploy | File copy to server → Admin → Notifications → purge caches |

---
## 3. PERMISSIONS

**Write without asking** — any file in `D:\Claude Local\airpay-ld-os\**`
Approved types: `.php .js .css .scss .html .xml .json .yaml .yml .md .txt .py .sh .mustache`

**Run without asking:**
```
php -l, php -r, Select-String, Get-ChildItem, Get-Content
Compress-Archive, python, pip install, npm install
```

**ALWAYS confirm first:**
```
Any delete/Remove-Item command
Writes outside D:\Claude Local\
POST to Moodle REST API / ElevenLabs / Gamma (live server / costs money)
DB migrations (ALTER, DROP)
Touching Moodle core files
Anything tagged [CONFIRM]
```

---
## 4. WORKFLOW RULES

- **Write-to-disk:** Generate each file → write to disk → then next file. Never hold >3 files in context.
- **Atomic builds:** A partial plugin is a broken plugin. Generate ALL required files in one session.
- **State cards:** Before plugin work, create/read `state-cards/[pluginname]-state.md` with component string, version, tables, capabilities, files done/remaining.
- **SCSS strategy:** Edit component-by-component (variables → colours → layout → components → responsive). Use find-and-replace, not full rewrites.
- **SOP conversion:** Parse to summary first (max 800 words) → generate narration from summary → never paste full SOP in one prompt.
- **Context loss:** STOP coding. Re-read state card + last written file from disk. Never guess.
- **Theme workflow:** Edit SCSS/template → deploy to server → purge cache → Ctrl+Shift+R → test as Learner role → test mobile viewport.

---
## 5. MOODLE CODING STANDARDS

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

---
## 6. PLUGIN FILE CHECKLISTS

**Local plugin** — `local_[name]` → deploy to `/moodle/local/[name]/`
```
REQUIRED: version.php, lang/en/local_[name].php, index.php
IF DB:    db/install.xml, db/upgrade.php
IF CAPS:  db/access.php
IF SETTINGS: settings.php
COMMON:   lib.php, classes/*.php, templates/*.mustache
```

**Block plugin** — `block_[name]` → deploy to `/moodle/blocks/[name]/`
```
REQUIRED: version.php, block_[name].php (extends block_base), lang/en/block_[name].php
IF DB:    db/install.xml, db/upgrade.php
IF CAPS:  db/access.php
```

**Activity module** — `mod_[name]` → deploy to `/moodle/mod/[name]/`
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
$plugin->version   = 2026040300;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
```

---
## 7. THEME — airpayux (Forked Epsilon)

Standalone fork. `$THEME->parents = []`. We own 514 files.
Server path: `C:\xampp\htdocs\moodle\theme\airpayux\`

**Design system:**
```
Primary: #0066A7  |  Accent: #0f7a73  |  BG: #F2F4FB
Font: Montserrat 400-800  |  Spacing: 8px base  |  Radius: 8-20px
```

**Key files:**
- `templates/navbar.mustache` — navbar HTML (every page)
- `templates/footer.mustache` — footer HTML (every page)
- `templates/core/loginform.mustache` + `layout/login.php` — login page
- `layout/dashboard.php` — dashboard PHP (block regions, queries)
- `scss/moodle/custom_changes.scss` — main SCSS overrides
- `scss/moodle/custom_media.scss` — responsive breakpoints (590 lines)
- `classes/output/core_renderer.php` — 2,129 lines, per-tenant branding

---
## 8. SCORM PACKAGING (SCORM 1.2)

```
[course]-scorm.zip (root level):
├── imsmanifest.xml    ← MUST be at ZIP root
├── index.html         ← launch file
├── scormdriver.js     ← SCORM API bridge
├── slides/ audio/ images/ css/
```

**Validation (all must pass before packaging):**
- imsmanifest.xml at ZIP root (not in subfolder)
- `<organizations default="ORG_01">` has items, href matches real files
- masteryscore = 70 (Airpay default)
- All files in manifest exist in ZIP

**ZIP creation (CRITICAL — wrong method = Moodle rejection):**
```powershell
# CORRECT — from INSIDE the course folder:
Set-Location "content\scorm-output\[coursename]"
Compress-Archive -Path * -DestinationPath "..\[coursename]-scorm.zip"
```

---
## 9. SENTIENTIA PIPELINE (PLANNED)

Each agent = one session. Output to disk. Next agent reads from disk. Never chain.

| Agent | Input | Output | Rule |
|-------|-------|--------|------|
| 1 SOP Parser | `content/sops/*.pdf` | `content/parsed/*-parsed.json` | Max 2000 words |
| 2 Narration | parsed JSON | `content/narrations/*-narration.txt` | 25-word sentences, 130wpm |
| 3 Slides | narration | `content/slides/*-slides.json` | Max 5 bullets, 8 words each |
| 4 Voice | narration | `content/voice/*-voice.txt` | ElevenLabs = [CONFIRM] |
| 5 SCORM Pack | slides + audio | `content/scorm-output/*-scorm.zip` | Validate before ZIP |
| 6 Upload | SCORM ZIP | Moodle | [CONFIRM] — live server |

---
## 10. REST API

```
READ (no confirm): core_course_get_courses, core_user_get_users,
  core_enrol_get_enrolled_users, core_completion_get_activities_completion_status,
  mod_scorm_get_scorm_scoes
WRITE ([CONFIRM]): core_course_create_courses, core_files_upload
```
Credentials in `.env` (never commit). Never log tokens.

---
## 11. ENV VARS (.env — never commit)
```
MOODLE_URL=https://academy.airpay.in
MOODLE_TOKEN=...
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=...
GAMMA_API_KEY=...
AZURE_CLIENT_ID=...  AZURE_CLIENT_SECRET=...  AZURE_TENANT_ID=...
DB_HOST=localhost  DB_NAME=moodle  DB_USER=moodleuser  DB_PASS=...
```

---
## 12. ESCALATION FLAGS — Stop and check with Nitin
- DB schema change on live Moodle
- Plugin touches a Moodle core file
- API sends employee PII externally
- Compliance content needs review before publish
- SCORM fails validation after 2 attempts
- Same PHP error repeats 3+ times
- Task exceeds 50 file operations

---
## 13. ABSOLUTE RULES
```
NEVER modify Moodle core files
NEVER hardcode credentials — always .env
NEVER generate partial plugins — complete set or nothing
NEVER skip SCORM validation before packaging
NEVER delete files in content/sops/
NEVER chain SENTIENTIA agents — one per session
NEVER POST to Moodle/ElevenLabs/Gamma without [CONFIRM]
NEVER use raw SQL when $DB API exists
NEVER echo unescaped input — always s() or format_string()
NEVER write project files outside D:\Claude Local\
```

---
## 14. GIT PROTOCOL
Push to GitHub after every milestone.
Repo: `nitin-rajput-learning-tech/Airpay-Academy2.0` (production branch)
Backups: `D:\Claude Local\Moodle Backup\`
Prototypes: `D:\Claude Local\Moodle Backup\03-prototypes\preview\` (22 C-suite approved)
