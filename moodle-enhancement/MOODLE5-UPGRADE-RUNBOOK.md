# Moodle 5.0 Upgrade Runbook — Airpay Academy
**Created:** 2026-04-17 | **From:** Moodle 4.5.10 | **To:** Moodle 5.0.x
**Pre-upgrade tag:** `v3.0.0-pre-moodle5`

---

## Pre-Upgrade Checklist

- [ ] Download Moodle 5.0 latest from https://download.moodle.org/releases/latest/
- [ ] Backup database: `mysqldump -u root moodle > pre-moodle5-backup.sql`
- [ ] Backup entire XAMPP htdocs/moodle: `xcopy C:\xampp\htdocs\moodle C:\xampp\htdocs\moodle-4.5-backup /E /I`
- [ ] Verify PHP 8.2+ (required): `php -v` (we have 8.2.12 ✅)
- [ ] Git tag exists: `v3.0.0-pre-moodle5` ✅

---

## Upgrade Steps

### Step 1: Put site in maintenance mode
```
php C:\xampp\htdocs\moodle\admin\cli\maintenance.php --enable
```

### Step 2: Extract Moodle 5.0 core
```powershell
# Extract downloaded ZIP to a temp location
Expand-Archive -Path "C:\Users\nitin.rajput\Downloads\moodle-latest-500.zip" -DestinationPath "C:\xampp\htdocs\moodle5-temp"

# IMPORTANT: Do NOT overwrite these directories:
#   theme/airpayux/     (our theme)
#   local/airpay_*/     (our plugins)
#   local/airpay_org/   (our plugins)
#   blocks/airpay_*/    (our blocks)
#   config.php          (our config)
#   .htaccess           (if customized)
```

### Step 3: Replace Moodle core files
```powershell
# Copy Moodle 5.0 core (excluding our customizations)
# Replace these directories:
$coreDirs = @('admin', 'auth', 'backup', 'badges', 'blog', 'cache', 'calendar',
  'cohort', 'comment', 'competency', 'completion', 'contentbank', 'course',
  'customfield', 'enrol', 'error', 'files', 'filter', 'grade', 'group',
  'h5p', 'iplookup', 'lang', 'lib', 'login', 'media', 'message', 'mod',
  'notes', 'payment', 'plagiarism', 'portfolio', 'privacy', 'question',
  'rating', 'report', 'repository', 'rss', 'search', 'tag', 'user',
  'userpix', 'webservice')

foreach ($dir in $coreDirs) {
    if (Test-Path "C:\xampp\htdocs\moodle5-temp\moodle\$dir") {
        Remove-Item "C:\xampp\htdocs\moodle\$dir" -Recurse -Force
        Copy-Item "C:\xampp\htdocs\moodle5-temp\moodle\$dir" "C:\xampp\htdocs\moodle\$dir" -Recurse
    }
}

# Copy root PHP files (except config.php)
Copy-Item "C:\xampp\htdocs\moodle5-temp\moodle\*.php" "C:\xampp\htdocs\moodle\" -Exclude "config.php"
Copy-Item "C:\xampp\htdocs\moodle5-temp\moodle\version.php" "C:\xampp\htdocs\moodle\version.php" -Force

# Copy Moodle core blocks (preserve our custom blocks)
$coreBlocks = Get-ChildItem "C:\xampp\htdocs\moodle5-temp\moodle\blocks" -Directory |
    Where-Object { $_.Name -notlike 'airpay_*' -and $_.Name -ne 'learnerscript' }
foreach ($block in $coreBlocks) {
    Remove-Item "C:\xampp\htdocs\moodle\blocks\$($block.Name)" -Recurse -Force -ErrorAction SilentlyContinue
    Copy-Item $block.FullName "C:\xampp\htdocs\moodle\blocks\$($block.Name)" -Recurse
}

# Copy Moodle core themes (preserve airpayux)
$coreThemes = Get-ChildItem "C:\xampp\htdocs\moodle5-temp\moodle\theme" -Directory |
    Where-Object { $_.Name -ne 'airpayux' -and $_.Name -ne 'epsilon' }
foreach ($theme in $coreThemes) {
    Remove-Item "C:\xampp\htdocs\moodle\theme\$($theme.Name)" -Recurse -Force -ErrorAction SilentlyContinue
    Copy-Item $theme.FullName "C:\xampp\htdocs\moodle\theme\$($theme.Name)" -Recurse
}
```

### Step 4: Deploy Airpay plugins (BS5-ready versions)
```powershell
# Copy our updated theme + plugins from working directory
$src = "D:\Claude Local\airpay-ld-os\moodle-enhancement"
$dst = "C:\xampp\htdocs\moodle"

# Theme
Copy-Item "$src\theme\airpayux\*" "$dst\theme\airpayux\" -Recurse -Force

# All Airpay local plugins
Get-ChildItem "$src\local\airpay_*" -Directory | ForEach-Object {
    Copy-Item $_.FullName "$dst\local\$($_.Name)" -Recurse -Force
}

# Airpay blocks
Copy-Item "$src\blocks\airpay_trainer\*" "$dst\blocks\airpay_trainer\" -Recurse -Force
```

### Step 5: Run upgrade
```
php C:\xampp\htdocs\moodle\admin\cli\upgrade.php
```

Watch for:
- Plugin version conflicts → update version numbers
- Deprecated function warnings → note for later fix
- DB schema changes → should auto-migrate

### Step 6: Purge caches + disable maintenance
```
php C:\xampp\htdocs\moodle\admin\cli\purge_caches.php
php C:\xampp\htdocs\moodle\admin\cli\maintenance.php --disable
```

### Step 7: Test
```
Hard refresh: Ctrl+Shift+R

Test checklist:
□ Login page renders (no broken layout)
□ Dashboard loads for all 5 roles
□ Navbar renders correctly
□ Dark mode toggle works
□ Course catalog loads
□ Course player works
□ Profile page renders
□ Admin quick nav works
□ Zero JS console errors
□ Forms render correctly (Bootstrap 5 form classes)
□ Tooltips work (data-bs-toggle)
□ Modals work (data-bs-toggle)
□ Mobile viewport (590px) renders correctly
```

---

## Post-Upgrade

- [ ] Git tag: `v4.0.0-moodle5`
- [ ] Update config.php if needed (wwwroot, dataroot)
- [ ] Run `php local/airpay_org/cli/verify_branding.php`
- [ ] Push to GitHub

---

## Rollback Plan

If upgrade fails:
```powershell
# 1. Enable maintenance mode
php C:\xampp\htdocs\moodle\admin\cli\maintenance.php --enable

# 2. Restore database
mysql -u root moodle < pre-moodle5-backup.sql

# 3. Restore files
Remove-Item "C:\xampp\htdocs\moodle" -Recurse -Force
Rename-Item "C:\xampp\htdocs\moodle-4.5-backup" "moodle"

# 4. Disable maintenance
php C:\xampp\htdocs\moodle\admin\cli\maintenance.php --disable
```

---

## Key Moodle 5.0 Changes to Know

| Change | Impact on Airpay |
|--------|-----------------|
| Bootstrap 5 | ✅ HANDLED — BS5 compat layer added to custom_changes.scss |
| `data-toggle` → `data-bs-toggle` | ✅ HANDLED — templates updated |
| `.float-left` → `.float-start` | ✅ HANDLED — dashboard.php updated |
| `.ml-*` → `.ms-*` | ✅ HANDLED — navbar/drawers updated |
| Chat/Survey modules removed from core | No impact (we don't use them) |
| New activity overview page | May affect course player — test |
| Oracle DB support removed | No impact (we use MariaDB/MySQL) |
| PHP 8.4 support added | Can upgrade PHP later if needed |

## Sources
- https://moodledev.io/general/releases/5.0
- https://moodledev.io/docs/5.0/devupdate
- https://moodledev.io/docs/5.0/guides/bs5migration
