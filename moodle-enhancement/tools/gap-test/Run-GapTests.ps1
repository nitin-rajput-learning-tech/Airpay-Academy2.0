<#
.SYNOPSIS
    Autonomous local test pass for the 11-gap competitive build (branch claude/gap-integration).
    Deploys the gap plugins to the local XAMPP Moodle, runs the DB upgrade, purges caches,
    optionally runs the PHPUnit suites, and writes a timestamped report.

.DESCRIPTION
    Runs ENTIRELY against the LOCAL XAMPP instance. It never touches live.
    Idempotent: re-running re-copies the latest plugin files and re-runs the upgrade.
    The 3 extended plugins are backed up before overwrite so you can roll back.

.PARAMETER RepoRoot
    Path to this git checkout (the Airpay-Academy2.0 repo). Default: script's repo.

.PARAMETER Webroot
    Moodle public/ webroot. Default: C:\xampp\htdocs\moodle5\public

.PARAMETER RunTests
    Also run the PHPUnit suites (requires phpunit init to have been run once).

.EXAMPLE
    pwsh -File moodle-enhancement\tools\gap-test\Run-GapTests.ps1 -RunTests
#>
[CmdletBinding()]
param(
    [string]$RepoRoot = (Resolve-Path "$PSScriptRoot\..\..\.." ).Path,
    [string]$Webroot  = "C:\xampp\htdocs\moodle5\public",
    [string]$Branch   = "claude/gap-integration",
    [switch]$RunTests,
    [switch]$SkipGitSync
)

$ErrorActionPreference = "Stop"
$ts        = Get-Date -Format "yyyyMMdd-HHmmss"
$reportDir = Join-Path $RepoRoot "moodle-enhancement\tools\gap-test\reports\$ts"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$log = Join-Path $reportDir "run.log"
function Say($m){ $line="[{0}] {1}" -f (Get-Date -Format "HH:mm:ss"), $m; Write-Host $line; Add-Content $log $line }

# New plugins (fresh install) and extended plugins (overwrite existing — backed up first).
$newPlugins      = @("sentientia_skillsai","sentientia_authoring","sentientia_content_market","sentientia_xapi","sentientia_talent","sentientia_api")
$extendedPlugins = @("sentientia_learningpath","sentientia_analytics","sentientia_assistant")
$allPlugins      = $newPlugins + $extendedPlugins

Say "=== Gap build local test pass ($ts) ==="
Say "RepoRoot=$RepoRoot"
Say "Webroot =$Webroot"
Say "Branch  =$Branch  RunTests=$RunTests"

# --- Preflight ----------------------------------------------------------------
$adminCli = Join-Path (Split-Path $Webroot -Parent) "admin\cli"   # ...\moodle5\admin\cli
if (-not (Test-Path $Webroot))                 { throw "Webroot not found: $Webroot" }
if (-not (Test-Path "$Webroot\local"))         { throw "No local/ under webroot — is this a Moodle public/ dir?" }
if (-not (Test-Path "$adminCli\upgrade.php"))  { throw "admin/cli/upgrade.php not found at $adminCli" }
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw "php not on PATH (add XAMPP php to PATH)" }
Say "Preflight OK. php: $((php -v | Select-Object -First 1))"

# --- Git sync to the integration branch --------------------------------------
if (-not $SkipGitSync) {
    Push-Location $RepoRoot
    Say "Fetching + checking out $Branch ..."
    git fetch origin $Branch 2>&1 | Tee-Object -Append $log | Out-Null
    git checkout $Branch    2>&1 | Tee-Object -Append $log | Out-Null
    git pull origin $Branch 2>&1 | Tee-Object -Append $log | Out-Null
    Pop-Location
} else { Say "SkipGitSync set — using working tree as-is." }

$src = Join-Path $RepoRoot "moodle-enhancement\local"

# --- Backup extended plugins --------------------------------------------------
$backupDir = Join-Path $reportDir "backup-extended"
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
foreach ($p in $extendedPlugins) {
    $dest = Join-Path $Webroot "local\$p"
    if (Test-Path $dest) {
        Say "Backing up existing $p ..."
        Copy-Item $dest (Join-Path $backupDir $p) -Recurse -Force
    }
}
Say "Backups of extended plugins -> $backupDir"

# --- Deploy -------------------------------------------------------------------
foreach ($p in $allPlugins) {
    $from = Join-Path $src $p
    if (-not (Test-Path $from)) { Say "WARN: source missing for $p ($from) — skipped"; continue }
    Say "Deploying $p ..."
    Copy-Item $from (Join-Path $Webroot "local") -Recurse -Force
}

# --- Upgrade + purge ----------------------------------------------------------
Say "Running DB upgrade (non-interactive) ..."
& php "$adminCli\upgrade.php" --non-interactive 2>&1 | Tee-Object -Append (Join-Path $reportDir "upgrade.log")
Say "Purging caches ..."
& php "$adminCli\purge_caches.php" 2>&1 | Tee-Object -Append $log

# --- PHPUnit (optional) -------------------------------------------------------
if ($RunTests) {
    $phpunit = Join-Path $Webroot "vendor\bin\phpunit"
    if (-not (Test-Path $phpunit)) {
        Say "phpunit not found at $phpunit — run admin\tool\phpunit\cli\init.php once, then re-run with -RunTests"
    } else {
        foreach ($p in $allPlugins) {
            $comp = "local_$p"
            Say "PHPUnit: $comp ..."
            Push-Location $Webroot
            & $phpunit --filter $comp 2>&1 | Tee-Object -Append (Join-Path $reportDir "phpunit-$p.log")
            Pop-Location
        }
    }
}

# --- Summary ------------------------------------------------------------------
Say "=== DONE. Report dir: $reportDir ==="
Say "Next: open the switchboard at /local/sentientia_platform/admin/switchboard.php and enable flags per the runbook (docs/competitive/GAP-BUILD-XAMPP-TEST-RUNBOOK-2026-06-16.md), then smoke-test as a Learner."
Say "Rollback: disable flags; restore extended plugins from $backupDir; remove the 6 new plugin dirs; re-run upgrade.php."
