# /pr-review — Airpay Academy Pull Request Review

Full autonomous PR review before merging to `production` branch on `nitin-rajput-learning-tech/Airpay-Academy2.0`.

## Usage
```
/pr-review                  → review latest staged changes (git diff production...HEAD)
/pr-review [branch]         → review specific branch
```

---

## Autonomous Review Protocol

### STEP 1: Understand the diff
```powershell
# List all changed files
git diff production...HEAD --name-only

# Full diff
git diff production...HEAD

# Commits included in this PR
git log production...HEAD --oneline
```

### STEP 2: Classify changes

```
Files changed determine which reviews apply:

PHP files (*.php)      → Security review A, Standards review B, Completeness C
SCSS files (*.scss)    → Design system review D
Mustache (*.mustache)  → Template review E
XML (install.xml)      → Schema review F
version.php            → Version format review G
Python (*.py)          → API security review H
SCORM (*.zip)          → SCORM structure review I
```

---

## A. Security Review (Run on ALL PHP changes)

**Auto-search patterns:**
```powershell
# Superglobal access
git diff production...HEAD -- "*.php" | Select-String '\$_GET|\$_POST|\$_REQUEST'

# Raw SQL
git diff production...HEAD -- "*.php" | Select-String 'mysqli_query|->query\('

# Possible credentials
git diff production...HEAD | Select-String -Pattern '(api_key|token|secret)\s*=\s*["'"'"'][a-zA-Z0-9]{10,}'

# Unescaped echo
git diff production...HEAD -- "*.php" | Select-String '^\+.*echo\s+\$'
```

**Block deploy if any found.** See auto-fix patterns in agents/code-reviewer.md.

---

## B. Moodle Standards Review

**Checklist — check each in diff:**

```
□ defined('MOODLE_INTERNAL') || die() in every class/lib file
□ version.php: 10-digit version, component = dir name, requires ≥ 2024100700
□ All user-facing strings via get_string() — no hardcoded English
□ require_login() + require_capability() before $OUTPUT->header()
□ $PAGE->set_url() + set_title() + set_heading() before header()
□ $OUTPUT->footer() always called (no early exit leaving footer missing)
□ DB queries use $DB API (never PDO/mysqli)
□ {tablename} used in all SQL (never mdl_tablename hardcoded)
```

---

## C. Plugin Completeness (If new plugin added)

```powershell
# Find new plugin directories in diff
git diff production...HEAD --name-only | Select-String '^moodle-enhancement/plugins/(local|block|mod)_'
```

**Local plugin must have ALL:**
```
version.php ✓ | lang/en/local_[n].php ✓ | index.php ✓
db/install.xml (if tables) | db/upgrade.php (if tables) | db/access.php (if caps)
```

**BLOCK if partial plugin.** "A partial plugin is a broken plugin."

---

## D. Theme / Design System Review

```
□ Colors: only $ap-primary #0066A7, $ap-accent #0f7a73, $ap-bg #F2F4FB (no hex literals in rules)
□ Font: Montserrat only, weights 400–800
□ Spacing: multiples of 8px only
□ Border radius: 8/12/16/20px only
□ SCSS: used find-and-replace not full rewrite (< 50% of file changed)
□ No Moodle core theme files touched (only theme/airpayux/)
□ Mobile breakpoint 590px preserved in custom_media.scss
```

---

## E. Mustache Template Review

```powershell
# Check for unescaped triple-braces
git diff production...HEAD -- "*.mustache" | Select-String '^\+.*\{\{\{'

# Check for hardcoded English
git diff production...HEAD -- "*.mustache" | Select-String '^\+.*>[A-Z][a-z]+ [a-z]'
```

```
□ {{ }} used for user content (auto-escaped) — NOT {{{ }}}
□ All strings via {{# str }}key, component{{/ str }}
□ Forms include sesskey or logintoken
□ {{{ output.standard_footer_html }}} in layout templates
□ {{{ output.standard_end_of_body_html }}} in layout templates
```

---

## F. Database Schema Review

```
□ New tables named local_[pluginname]_[purpose] (prefix prevents conflicts)
□ All tables have: id (PK, sequence), timecreated, timemodified
□ Multi-tenant tables have costcenterid column
□ FK columns have corresponding KEY definition
□ Frequently-queried columns have INDEX
□ db/upgrade.php has savepoint for each version bump
□ upgrade.php checks field/table existence before add (field_exists, table_exists)
```

---

## G. Version Number Review

```
$plugin->version   = YYYYMMDDNN  ← exactly 10 digits
$plugin->requires  = 2024100700  ← Moodle 4.5 minimum
$plugin->component = 'local_[n]' ← matches directory name exactly
$plugin->maturity  = MATURITY_STABLE
```

---

## H. API Security Review (if Python files changed)

```
□ No hardcoded tokens/keys (use os.getenv() with load_dotenv())
□ All requests.post/get include timeout=30 (or 120 for uploads)
□ ElevenLabs/Gamma calls have [CONFIRM] docstring
□ .env not committed (.gitignore includes .env)
□ Moodle tokens not logged
```

---

## I. SCORM Review (if ZIP files staged)

```powershell
# Verify imsmanifest.xml at root of any staged ZIP
git diff --cached --name-only | Where-Object {$_ -match '\.zip$'} | ForEach-Object {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $z = [System.IO.Compression.ZipFile]::OpenRead($_)
    $entries = $z.Entries | Select-Object -ExpandProperty FullName
    $z.Dispose()
    if ($entries -notcontains "imsmanifest.xml") {
        Write-Error "FAIL: $_ — imsmanifest.xml not at ZIP root"
    } else { Write-Host "OK: $_" }
}
```

---

## Blocker Patterns (Auto-reject — no exceptions)

```
❌ Moodle core file modified (lib/, admin/, core mod/ files)
❌ Hardcoded credential, token, or API key
❌ Raw $_GET/$_POST/$_REQUEST access
❌ Raw SQL string concatenation
❌ content/sops/ file deleted
❌ Partial plugin (missing required files)
❌ SCORM ZIP with imsmanifest.xml not at root
❌ .env file committed
```

---

## Review Output Format

```markdown
## PR Review — [branch name] → production
Date: YYYY-MM-DD
Files changed: N | Commits: N

### Security: ✅ PASS / ❌ FAIL
[findings with exact file:line and fix]

### Standards: ✅ PASS / ❌ FAIL
[findings]

### Plugin Completeness: ✅ PASS / N/A
[missing files if any]

### Theme/Design System: ✅ PASS / ⚠ WARNINGS / N/A
[findings]

### Multi-tenant Isolation: ✅ PASS / ❌ FAIL / N/A
[costcenter scoping check results]

### Blockers (must fix before merge)
1. [blocker]
2. [blocker]

### Warnings (fix in next sprint)
1. [warning]

### VERDICT: ✅ APPROVE | ⚠ CONDITIONAL | ❌ BLOCK
```
