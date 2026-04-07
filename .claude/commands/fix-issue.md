# /fix-issue — Airpay Issue Fix Command

Autonomous issue diagnosis and fix. Reads error logs → identifies root cause → applies minimal fix → verifies.

## Usage
```
/fix-issue [paste error message or describe symptom]
/fix-issue "PHP Fatal error: Call to undefined function in index.php on line 23"
/fix-issue "Blank white page on dashboard after last SCSS change"
/fix-issue "Plugin install fails with 'Table already exists'"
/fix-issue "SCORM uploads but shows 0% completion"
/fix-issue "Airpay tenant users see Public courses"
```

## Autonomous Protocol (run these steps without asking)

### STEP 1: Read error logs (always first)
```powershell
# Primary error log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50

# Filter for Moodle-related errors
Select-String "C:\xampp\apache\logs\error.log" -Pattern "moodle|local_|block_|theme_airpay" | Select-Object -Last 15

# PHP parse check on recently edited file
php -l "C:\xampp\htdocs\moodle\[recently-edited-file].php"
```

### STEP 2: Classify into decision tree

```
Is it a BLANK PAGE?
  → Run php -l on last edited file
  → Check for missing MOODLE_INTERNAL guard in included files
  → Check error.log for Fatal errors or redirect loops

Is it a PLUGIN INSTALL FAILURE?
  → Check version.php: component matches dir? version is 10 digits? requires ≥ 2024100700?
  → Check db/install.xml: valid XMLDB? no duplicate table names?
  → Check lang/en/local_[name].php: has $string['pluginname']?

Is it a THEME/RENDERING ISSUE?
  → Check if caches purged (common cause: forgot purge + Ctrl+Shift+R)
  → Check Mustache syntax: balanced {{ # / }} tags?
  → Check SCSS: compilation error in custom_changes.scss?
  → Check layout PHP: syntax error? missing $OUTPUT->footer()?

Is it a CAPABILITY/PERMISSION ERROR?
  → Check context level (system vs course vs module)
  → Check capability name format ('local/pluginname:view' not 'local_pluginname:view')
  → Run Admin → Notifications (capability may not be installed)

Is it a SCORM ISSUE?
  → Validate: imsmanifest.xml at ZIP root (not subfolder)
  → Check: masteryscore=70 in manifest
  → Check: all href values resolve to real files (case-sensitive on Linux)

Is it a MULTI-TENANT BUG?
  → Check costcenterid filter in all queries
  → Check: (int)$costcenterid — not string comparison
  → Test with both Airpay (id=1) AND Public (id=77) users
```

### STEP 3: Apply minimal fix
- **Change ONE thing at a time**
- **Preserve all surrounding code** — fix only the identified line(s)
- **php -l [file]** after every PHP change

### STEP 4: Deploy and verify
```powershell
# Copy fixed file to XAMPP
Copy-Item "[fixed-file]" "C:\xampp\htdocs\moodle\[destination]" -Force

# Purge Moodle caches
php "C:\xampp\htdocs\moodle\admin\cli\purge_caches.php"

# Hard refresh browser: Ctrl+Shift+R

# Test as LEARNER role (not admin — admin bypasses capability checks)
# URL: http://localhost:8080/moodle/

# Confirm error gone from log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 10
```

### STEP 5: Update state card
After fixing, append to `moodle-enhancement/state-cards/[pluginname]-state.md`:
```markdown
## Fix Log
- YYYY-MM-DD: Fixed [issue description] — root cause: [cause], fix: [what changed]
```

## Escalation Triggers (STOP — tell Nitin)
```
□ Same error after 3 different fix attempts → describe all attempts + ask for input
□ Root cause requires editing C:\xampp\htdocs\moodle\lib\ → STOP (core file)
□ Fix requires ALTER TABLE or DROP on production → STOP ([CONFIRM] required)
□ SCORM fails validation after 2 complete repackaging attempts → STOP
□ Error reveals employee PII was logged or sent externally → STOP + report urgently
□ > 50 file operations needed to fix → STOP and describe plan first
```

## Quick Error Reference (Most Common Airpay Issues)

| Error message | Root cause | Fix |
|---------------|-----------|-----|
| Blank white page | PHP syntax error | `php -l [last-edited.php]` |
| "Invalid component" | version.php component ≠ dir name | Fix `$plugin->component` |
| "Table already exists" | install.xml table name conflict | Prefix table with plugin name |
| "Access all groups" | Wrong context level | Use correct context_X::instance() |
| "Capability does not exist" | Wrong capability format | Use `local/name:action` not `local_name:action` |
| Plugin upgrade error | DB schema mismatch | Check db/upgrade.php savepoint version |
| SCORM rejected | manifest not at ZIP root | Re-package from inside course folder |
| SCORM 0% completion | Missing masteryscore | Add `<imsss:masteryScore>70` to manifest |
| Tenant data leak | Missing costcenterid filter | Add `WHERE costcenterid = :cid` to query |
| CSS not updating | Cache not purged | Purge + Ctrl+Shift+R |
| "Deprecated: dynamic property" | PHP 8.2 compat | Use `new stdClass()` explicitly |
