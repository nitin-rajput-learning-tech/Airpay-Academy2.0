# Airpay Academy 2.0 — Deployment Script (PowerShell)
# Run on the production server or from a machine with SSH/file access.
# IMPORTANT: Update $moodleRoot to match your production Moodle path.

param(
    [string]$moodleRoot = "C:\inetpub\wwwroot\moodle",  # UPDATE THIS
    [switch]$DryRun = $false
)

$ErrorActionPreference = "Stop"
$releaseDir = Split-Path -Parent $PSScriptRoot  # Parent of scripts/

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Airpay Academy 2.0 — Deploy v1.0.0-rc1" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Moodle root: $moodleRoot"
Write-Host "Release dir: $releaseDir"
Write-Host "Dry run: $DryRun"
Write-Host ""

# Pre-flight checks
if (-not (Test-Path "$moodleRoot\config.php")) {
    Write-Error "Moodle not found at $moodleRoot — update `$moodleRoot parameter"
    exit 1
}

# Step 1: Backup
Write-Host "[1/7] Creating backup..." -ForegroundColor Yellow
$backupDir = "$moodleRoot\..\backups\pre-academy2-$(Get-Date -Format 'yyyyMMdd-HHmm')"
if (-not $DryRun) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Copy-Item -Recurse "$moodleRoot\theme\airpayux" "$backupDir\theme-airpayux" -ErrorAction SilentlyContinue
    Copy-Item -Recurse "$moodleRoot\local\airpay_pages" "$backupDir\local-airpay_pages" -ErrorAction SilentlyContinue
    Write-Host "  Backup saved to: $backupDir" -ForegroundColor Green
} else {
    Write-Host "  [DRY RUN] Would backup to $backupDir"
}

# Step 2: Deploy theme
Write-Host "[2/7] Deploying theme/airpayux..." -ForegroundColor Yellow
if (-not $DryRun) {
    Copy-Item -Recurse -Force "$releaseDir\theme\airpayux" "$moodleRoot\theme\airpayux"
    Write-Host "  Theme deployed" -ForegroundColor Green
} else {
    Write-Host "  [DRY RUN] Would copy theme"
}

# Step 3: Deploy plugins
Write-Host "[3/7] Deploying plugins..." -ForegroundColor Yellow
$plugins = @(
    @{src="plugins\airpay_pages";     dest="local\airpay_pages"},
    @{src="plugins\airpay_compliance"; dest="blocks\airpay_compliance"},
    @{src="plugins\airpay_lifecycle";  dest="local\airpay_lifecycle"},
    @{src="plugins\airpay_integrations"; dest="local\airpay_integrations"}
)
foreach ($p in $plugins) {
    if (Test-Path "$releaseDir\$($p.src)") {
        if (-not $DryRun) {
            Copy-Item -Recurse -Force "$releaseDir\$($p.src)" "$moodleRoot\$($p.dest)"
            Write-Host "  $($p.dest) deployed" -ForegroundColor Green
        } else {
            Write-Host "  [DRY RUN] Would deploy $($p.dest)"
        }
    }
}

# Step 4: Apply BizLMS fixes
Write-Host "[4/7] Applying BizLMS fixes (22 files)..." -ForegroundColor Yellow
if (-not $DryRun) {
    Get-ChildItem -Recurse "$releaseDir\bizlms-fixes" -File | ForEach-Object {
        $relPath = $_.FullName.Substring("$releaseDir\bizlms-fixes\".Length)
        $destPath = "$moodleRoot\$relPath"
        $destDir = Split-Path $destPath -Parent
        if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
        Copy-Item -Force $_.FullName $destPath
    }
    # Special: fulldescriptionpopover.js goes to local/courses/
    Copy-Item -Force "$releaseDir\bizlms-fixes\js\fulldescriptionpopover.js" "$moodleRoot\local\courses\fulldescriptionpopover.js"
    Write-Host "  22 BizLMS fixes applied" -ForegroundColor Green
} else {
    Write-Host "  [DRY RUN] Would apply 22 fixes"
}

# Step 5: Run Moodle upgrade
Write-Host "[5/7] Navigate to Site Admin > Notifications to run DB upgrade" -ForegroundColor Yellow
Write-Host "  URL: https://www.airpay.academy/admin/index.php" -ForegroundColor Cyan

# Step 6: Run post-deploy SQL
Write-Host "[6/7] Run post-deploy.sql on the database" -ForegroundColor Yellow
Write-Host "  File: $releaseDir\config\post-deploy.sql" -ForegroundColor Cyan

# Step 7: Purge caches
Write-Host "[7/7] Purging caches..." -ForegroundColor Yellow
if (-not $DryRun) {
    & php "$moodleRoot\admin\cli\purge_caches.php" 2>$null
    Write-Host "  Caches purged" -ForegroundColor Green
} else {
    Write-Host "  [DRY RUN] Would purge caches"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " Deployment complete!" -ForegroundColor Green
Write-Host " Test: https://www.airpay.academy/" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Post-deploy checklist:" -ForegroundColor Yellow
Write-Host "  [ ] Login page renders (split-screen, logo, stats)"
Write-Host "  [ ] Siteadmin dashboard shows KPIs + System Health"
Write-Host "  [ ] Employee dashboard shows courses + progress"
Write-Host "  [ ] Manager dashboard shows team compliance"
Write-Host "  [ ] Dark mode toggle works + persists"
Write-Host "  [ ] Manage Users loads (card view)"
Write-Host "  [ ] Manage Courses loads (card view)"
Write-Host "  [ ] Catalog loads (course cards + filters)"
Write-Host "  [ ] All 3 tenants visible in Manage Company"
Write-Host "  [ ] Hard refresh browser (Ctrl+Shift+R)"
