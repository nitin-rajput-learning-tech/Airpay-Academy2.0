# /deploy — Airpay Academy Deploy Command

Automated deploy from working directory to XAMPP. Validates → copies → upgrades → purges → verifies.

## Usage
```
/deploy theme           → deploy all changed airpayux theme files
/deploy plugin [name]   → deploy local plugin (e.g., /deploy plugin local_airhub)
/deploy block [name]    → deploy block plugin
/deploy scorm [name]    → validate + package SCORM course
/deploy all             → deploy theme + all plugins with changes
```

**⚠ Production deploy = [CONFIRM] required. All commands below target LOCAL XAMPP only.**

---

## /deploy theme

```powershell
# ═══════════════════════════════════════════════════
# PHASE 1: VALIDATE
# ═══════════════════════════════════════════════════

$SRC = "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux"
# Audit fix O1 (2026-05-15): Apache alias /moodle → moodle5\public\, not moodle\.
# The old moodle\ directory is a stale 4.5 backup that is never served.
$DST = "C:\xampp\htdocs\moodle5\public\theme\airpayux"

# Syntax check all PHP layout files
Write-Host "→ PHP syntax check..."
Get-ChildItem "$SRC\layout" -Filter "*.php" | ForEach-Object {
    $result = php -l $_.FullName 2>&1
    if ($result -notmatch "No syntax errors") {
        Write-Error "SYNTAX ERROR: $($_.Name): $result"
        exit 1
    }
}
Write-Host "  ✓ PHP syntax OK"

# Check for changed files (deploy only changed, not bulk copy)
$changed = Get-ChildItem "$SRC" -Recurse -File |
    Where-Object { $_.LastWriteTime -gt (Get-Date).AddHours(-2) } |
    Select-Object -ExpandProperty FullName
Write-Host "  → $($changed.Count) file(s) changed in last 2 hours"

# ═══════════════════════════════════════════════════
# PHASE 2: COPY
# ═══════════════════════════════════════════════════
Write-Host "→ Copying to XAMPP..."
foreach ($file in $changed) {
    $relative = $file.Substring($SRC.Length + 1)
    $destFile  = Join-Path $DST $relative
    $destDir   = Split-Path $destFile -Parent
    if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item $file $destFile -Force
    Write-Host "  → $relative"
}
Write-Host "  ✓ Copy complete"

# ═══════════════════════════════════════════════════
# PHASE 3: PURGE CACHES
# ═══════════════════════════════════════════════════
Write-Host "→ Purging Moodle caches..."
# Audit fix O1 (2026-05-15): Moodle 5.1's admin/cli lives at the install
# root, while config.php lives in public/. CLI scripts must be invoked
# with the public/ directory as cwd so the relative config.php resolves.
Push-Location "C:\xampp\htdocs\moodle5\public"
php "C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php"
Pop-Location
Write-Host "  ✓ Caches purged"

# ═══════════════════════════════════════════════════
# PHASE 4: VERIFY
# ═══════════════════════════════════════════════════
Write-Host ""
Write-Host "✅ Theme deployed. Verify checklist:"
Write-Host "   □ Ctrl+Shift+R in browser (force reload)"
Write-Host "   □ http://localhost:8080/moodle/ — login page"
Write-Host "   □ http://localhost:8080/moodle/my/dashboard.php — dashboard"
Write-Host "   □ Test as LEARNER role (not admin)"
Write-Host "   □ Mobile viewport 590px (Chrome devtools)"
Write-Host "   □ Browser console — zero JS errors"
```

---

## /deploy plugin [name]

```powershell
param([string]$PluginName)  # e.g., "local_airhub"

$PLUGIN_TYPE = $PluginName.Split('_')[0]   # "local", "block", "mod"
$PLUGIN_DIR  = ($PluginName -replace '^[^_]+_', '')  # "airhub"
# Audit fix O1 (2026-05-15):
#   - Source plugins live under moodle-enhancement\<type>\<dir>\, not
#     moodle-enhancement\plugins\<name>\.
#   - Destination is moodle5\public\<type>\<dir>\, not moodle\<type>\<dir>\.
$SRC = "D:\Claude Local\airpay-ld-os\moodle-enhancement\$PLUGIN_TYPE\$PluginName"
$DST = "C:\xampp\htdocs\moodle5\public\$PLUGIN_TYPE\$PLUGIN_DIR"

# ═══════════════════════════════════════════════════
# PHASE 1: VALIDATE COMPLETENESS
# ═══════════════════════════════════════════════════
Write-Host "→ Plugin completeness check..."

$required = @("version.php", "lang\en\$PluginName.php")
if ($PLUGIN_TYPE -eq "local") { $required += "index.php" }
if ($PLUGIN_TYPE -eq "block")  { $required += "block_$PLUGIN_DIR.php" }

foreach ($req in $required) {
    if (-not (Test-Path "$SRC\$req")) {
        Write-Error "MISSING required file: $req — CANNOT DEPLOY PARTIAL PLUGIN"
        exit 1
    }
}
Write-Host "  ✓ All required files present"

# ═══════════════════════════════════════════════════
# PHASE 2: PHP SYNTAX CHECK ALL FILES
# ═══════════════════════════════════════════════════
Write-Host "→ PHP syntax check..."
$errors = 0
Get-ChildItem $SRC -Filter "*.php" -Recurse | ForEach-Object {
    $result = php -l $_.FullName 2>&1
    if ($result -notmatch "No syntax errors") {
        Write-Host "  ✗ $($_.Name): $result" -ForegroundColor Red
        $errors++
    }
}
if ($errors -gt 0) { Write-Error "$errors syntax error(s) — fix before deploying"; exit 1 }
Write-Host "  ✓ All PHP files clean"

# ═══════════════════════════════════════════════════
# PHASE 3: VERSION CHECK
# ═══════════════════════════════════════════════════
Write-Host "→ version.php check..."
$versionContent = Get-Content "$SRC\version.php" -Raw
$componentMatch = [regex]::Match($versionContent, "\\\$plugin->component\s*=\s*'([^']+)'")
if ($componentMatch.Success -and $componentMatch.Groups[1].Value -ne $PluginName) {
    Write-Error "Component mismatch: '$($componentMatch.Groups[1].Value)' != '$PluginName'"
    exit 1
}
Write-Host "  ✓ version.php valid"

# ═══════════════════════════════════════════════════
# PHASE 4: COPY
# ═══════════════════════════════════════════════════
Write-Host "→ Copying $PluginName to XAMPP..."
if (Test-Path $DST) { Remove-Item $DST -Recurse -Force }
Copy-Item $SRC $DST -Recurse -Force
Write-Host "  ✓ Copied to $DST"

# ═══════════════════════════════════════════════════
# PHASE 5: RUN MOODLE UPGRADE
# ═══════════════════════════════════════════════════
Write-Host "→ Running Moodle upgrade..."
# Audit fix O1 (2026-05-15): Moodle 5.1's admin/cli lives at the install
# root, public/ holds config.php. cd into public/ before running CLI tools.
Push-Location "C:\xampp\htdocs\moodle5\public"
php "C:\xampp\htdocs\moodle5\admin\cli\upgrade.php" --non-interactive
Write-Host "  ✓ Upgrade complete"

# Purge caches
php "C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php"
Pop-Location
Write-Host "  ✓ Caches purged"

# ═══════════════════════════════════════════════════
# PHASE 6: VERIFY
# ═══════════════════════════════════════════════════
Write-Host ""
Write-Host "✅ Plugin deployed. Verify checklist:"
Write-Host "   □ Admin → Site admin → Plugins — $PluginName listed with correct version"
Write-Host "   □ http://localhost:8080/moodle/local/$PLUGIN_DIR/ — index loads"
Write-Host "   □ No errors in: Get-Content 'C:\xampp\apache\logs\error.log' -Tail 10"
Write-Host "   □ Test as LEARNER role"
Write-Host "   □ Test with Airpay tenant (costcenterid=1) AND Public (costcenterid=77)"
```

---

## /deploy scorm [coursename]

```powershell
param([string]$CourseName)

$COURSE_DIR  = "D:\Claude Local\airpay-ld-os\content\scorm-output\$CourseName"
$OUTPUT_ZIP  = "D:\Claude Local\airpay-ld-os\content\scorm-output\$CourseName-scorm.zip"

# ═══════════════════════════════════════════════════
# PHASE 1: VALIDATE (ALL must pass — no exceptions)
# ═══════════════════════════════════════════════════
Write-Host "→ SCORM validation..."
$validationFailed = $false

# Check imsmanifest.xml exists at root
if (-not (Test-Path "$COURSE_DIR\imsmanifest.xml")) {
    Write-Host "  ✗ FAIL: imsmanifest.xml not found at course root" -ForegroundColor Red
    $validationFailed = $true
} else {
    Write-Host "  ✓ imsmanifest.xml present"
    $manifest = Get-Content "$COURSE_DIR\imsmanifest.xml" -Raw

    # Check organizations default="ORG_01"
    if ($manifest -notmatch 'default="ORG_01"') {
        Write-Host "  ✗ FAIL: organizations default attribute must be ORG_01" -ForegroundColor Red
        $validationFailed = $true
    } else { Write-Host "  ✓ organizations default=ORG_01" }

    # Check masteryscore
    if ($manifest -notmatch 'masteryscore|mastery_score') {
        Write-Host "  ✗ FAIL: masteryscore not found (Airpay default: 70)" -ForegroundColor Red
        $validationFailed = $true
    } elseif ($manifest -notmatch 'masteryscore[^>]*>70') {
        Write-Host "  ⚠ WARN: masteryscore is not 70 — verify intentional" -ForegroundColor Yellow
    } else { Write-Host "  ✓ masteryscore=70" }

    # Check launch file referenced
    if ($manifest -notmatch 'index\.html?') {
        Write-Host "  ✗ FAIL: index.html not referenced in manifest" -ForegroundColor Red
        $validationFailed = $true
    } else { Write-Host "  ✓ index.html referenced" }
}

# Check index.html exists
if (-not (Test-Path "$COURSE_DIR\index.html")) {
    Write-Host "  ✗ FAIL: index.html launch file missing" -ForegroundColor Red
    $validationFailed = $true
} else { Write-Host "  ✓ index.html present" }

# Check scormdriver.js
if (-not (Test-Path "$COURSE_DIR\scormdriver.js")) {
    Write-Host "  ⚠ WARN: scormdriver.js not found — SCORM API may not work" -ForegroundColor Yellow
} else { Write-Host "  ✓ scormdriver.js present" }

if ($validationFailed) {
    Write-Error "SCORM validation FAILED — fix issues before packaging"
    exit 1
}
Write-Host "  ✓ All validations passed"

# ═══════════════════════════════════════════════════
# PHASE 2: PACKAGE (MUST run from inside course folder)
# ═══════════════════════════════════════════════════
Write-Host "→ Packaging SCORM (from inside course folder)..."
if (Test-Path $OUTPUT_ZIP) { Remove-Item $OUTPUT_ZIP -Force }
Set-Location $COURSE_DIR
Compress-Archive -Path * -DestinationPath $OUTPUT_ZIP -Force
Write-Host "  ✓ ZIP created: $OUTPUT_ZIP"
$size = (Get-Item $OUTPUT_ZIP).Length / 1MB
Write-Host "  ✓ Size: $([math]::Round($size, 1)) MB"

# ═══════════════════════════════════════════════════
# PHASE 3: VERIFY ZIP STRUCTURE
# ═══════════════════════════════════════════════════
Write-Host "→ Verifying ZIP structure..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip     = [System.IO.Compression.ZipFile]::OpenRead($OUTPUT_ZIP)
$entries = $zip.Entries | Select-Object -ExpandProperty FullName
$zip.Dispose()

if ($entries -notcontains "imsmanifest.xml") {
    Write-Error "CRITICAL: imsmanifest.xml NOT at ZIP root — Moodle WILL reject this"
    exit 1
}
$nested = $entries | Where-Object { $_ -match '/' -and $_ -match 'imsmanifest' }
if ($nested) { Write-Error "imsmanifest.xml found nested at: $nested"; exit 1 }
Write-Host "  ✓ imsmanifest.xml at ZIP root"
Write-Host "  ✓ Total files in ZIP: $($entries.Count)"

# ═══════════════════════════════════════════════════
# PHASE 4: DONE
# ═══════════════════════════════════════════════════
Write-Host ""
Write-Host "✅ SCORM package ready: $OUTPUT_ZIP"
Write-Host ""
Write-Host "Upload to PRODUCTION requires [CONFIRM]:"
Write-Host "   Moodle REST: core_files_upload → assign to course"
Write-Host "   OR: Manual upload in Moodle course editor"
```

---

## Post-Deploy Always-Do
```
□ Check error log: Get-Content "C:\xampp\apache\logs\error.log" -Tail 10
□ Update state card: moodle-enhancement/state-cards/[name]-state.md
□ Update PROJECT-STATE.md
□ Push to GitHub after milestone: git push origin production
```
