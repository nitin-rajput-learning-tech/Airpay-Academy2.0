<#
.SYNOPSIS
    One-command deploy of Airpay Academy / Sentientia LMS source to local XAMPP.

.DESCRIPTION
    Copies the in-repo Moodle plugin/theme subtrees from the source working
    copy (default D:\Claude Local\airpay-ld-os\moodle-enhancement) to the
    matching paths under the local XAMPP Moodle public root (default
    C:\xampp\htdocs\moodle5\public), then runs Moodle's CLI upgrade + cache
    purge so the deployed code is live for the next request.

    What gets copied (relative paths, source -> target):

        theme/airpayux/                            -> theme/airpayux/
        local/*/                                   -> local/*/
        blocks/sentientia_*/                       -> blocks/sentientia_*/
        mod/quiz/accessrule/airpay_proctoring/     -> mod/quiz/accessrule/airpay_proctoring/
        payment/gateway/airpay/                    -> payment/gateway/airpay/

    Then runs (against <target>\..\..\admin\cli):
        php admin/cli/upgrade.php --non-interactive
        php admin/cli/purge_caches.php

    Prints a next-steps checklist so the operator doesn't forget the
    Ctrl+Shift+R hard reload (which is what caused the "stale localhost"
    confusion on 2026-05-24).

.PARAMETER Target
    Absolute path to the XAMPP Moodle public dir. Default:
    C:\xampp\htdocs\moodle5\public

    The script derives the CLI dir as "<Target>\..\..\admin\cli\", i.e.
    "C:\xampp\htdocs\moodle5\admin\cli\". This matches the XAMPP layout
    documented in CLAUDE.md section 2 (CLI lives at moodle5\admin\cli\,
    public lives at moodle5\public\).

.PARAMETER Source
    Absolute path to the moodle-enhancement working copy. Default:
    D:\Claude Local\airpay-ld-os\moodle-enhancement

.PARAMETER DryRun
    List every source -> target copy that would happen, but don't copy.
    Skips the upgrade + purge_caches calls as well. Safe to run anywhere.

.PARAMETER VerboseLog
    Emit per-file robocopy output (default is summary-only). Useful when
    a stale-file issue is suspected and you want to see exactly which
    files were touched.

.EXAMPLE
    # Default deploy: copy then upgrade + purge.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1

.EXAMPLE
    # Preview what would be copied without touching anything.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -DryRun

.EXAMPLE
    # Verbose per-file output.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -VerboseLog

.NOTES
    Authored: 2026-05-24, Sentientia LMS P1 deploy-automation chip.
    Reference: moodle-enhancement/docs/operations/deploy-runbook.md
    Reference: moodle-enhancement/tools/overlay-airpay-customs.ps1 (sibling
        script for the one-time 5.1 -> 5.2 cutover overlay; this script is
        for daily local-XAMPP redeploys).

    Hard rules (from CLAUDE.md section 13):
      - This script ONLY touches the local XAMPP path. It NEVER reaches out
        to live.airpay.academy. Production deploy lives in
        .github/workflows/deploy-production.yml under a workflow_dispatch
        confirm gate.
      - Pre-commit hooks are honoured by every commit that touches this file.
#>

[CmdletBinding()]
param(
    [string]$Target = 'C:\xampp\htdocs\moodle5\public',
    [string]$Source = 'D:\Claude Local\airpay-ld-os\moodle-enhancement',
    [switch]$DryRun,
    [switch]$VerboseLog
)

$ErrorActionPreference = 'Stop'

# ----------------------------------------------------------------------
# Logging helpers (ASCII-only for PowerShell 5.1 compatibility).
# ----------------------------------------------------------------------
function Write-Section {
    param([string]$Title)
    Write-Host ''
    Write-Host ('=' * 70) -ForegroundColor Cyan
    Write-Host $Title -ForegroundColor Cyan
    Write-Host ('=' * 70) -ForegroundColor Cyan
}

function Write-Step {
    param([string]$Msg)
    Write-Host "[STEP] $Msg" -ForegroundColor Yellow
}

function Write-Ok {
    param([string]$Msg)
    Write-Host "  OK   $Msg" -ForegroundColor Green
}

function Write-Skip {
    param([string]$Msg)
    Write-Host "  SKIP $Msg" -ForegroundColor DarkGray
}

function Write-Info {
    param([string]$Msg)
    Write-Host "  ..   $Msg" -ForegroundColor Gray
}

function Write-Err {
    param([string]$Msg)
    Write-Host "  ERR  $Msg" -ForegroundColor Red
}

# ----------------------------------------------------------------------
# Pre-flight: validate Source + Target + php.exe before touching anything.
# ----------------------------------------------------------------------
Write-Section 'Airpay Academy / Sentientia LMS - deploy to local XAMPP'

Write-Step 'Pre-flight checks'

if (-not (Test-Path -LiteralPath $Source)) {
    Write-Err "Source path not found: $Source"
    Write-Err 'Pass -Source <path> if your working copy lives elsewhere.'
    exit 2
}
Write-Ok "Source exists: $Source"

if (-not (Test-Path -LiteralPath $Target)) {
    Write-Err "Target path not found: $Target"
    Write-Err 'Pass -Target <path> if your XAMPP install is elsewhere.'
    Write-Err 'Expected layout (per CLAUDE.md section 2):'
    Write-Err '    C:\xampp\htdocs\moodle5\public\        <- -Target'
    Write-Err '    C:\xampp\htdocs\moodle5\admin\cli\     <- CLI tools'
    exit 2
}
Write-Ok "Target exists: $Target"

# Derive CLI dir from Target. Layout per CLAUDE.md section 2:
#   <Target>             = C:\xampp\htdocs\moodle5\public
#   <Target>\..          = C:\xampp\htdocs\moodle5
#   <Target>\..\admin\cli = C:\xampp\htdocs\moodle5\admin\cli
$cliDir = Join-Path (Split-Path $Target -Parent) 'admin\cli'
if (-not (Test-Path -LiteralPath $cliDir)) {
    Write-Err "CLI dir not found at expected location: $cliDir"
    Write-Err 'Verify the XAMPP layout matches CLAUDE.md section 2.'
    exit 2
}
Write-Ok "CLI dir resolved: $cliDir"

# Look up php.exe via Get-Command (uses PATH); bail if missing.
$phpCmd = Get-Command -Name php.exe -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    Write-Err 'php.exe not found on PATH.'
    Write-Err 'Either add C:\xampp\php to PATH, or run from "XAMPP Shell".'
    exit 2
}
Write-Ok "php.exe found: $($phpCmd.Source)"

if ($DryRun) {
    Write-Info 'DryRun is ON - no copies, no upgrade, no cache purge.'
}
if ($VerboseLog) {
    Write-Info 'VerboseLog is ON - per-file output enabled for robocopy.'
}

# ----------------------------------------------------------------------
# Build the copy plan.
#
# Each entry is a hashtable: { Label, RelPath, Optional }.
#   - RelPath is the path under Source AND under Target.
#   - Optional=true entries that are missing in Source are SKIPPED quietly.
# Wildcard expansion (e.g. local/*, blocks/sentientia_*) is done below by
# enumerating Source matching the pattern.
# ----------------------------------------------------------------------
$copyPlan = @()

# 1. Theme - always present.
$copyPlan += @{ Label='theme';      RelPath='theme\airpayux'; Optional=$false }

# 2. local/*/ - every local plugin in the source tree.
$localRoot = Join-Path $Source 'local'
if (Test-Path -LiteralPath $localRoot) {
    Get-ChildItem -Path $localRoot -Directory -ErrorAction SilentlyContinue | ForEach-Object {
        $copyPlan += @{ Label='local'; RelPath="local\$($_.Name)"; Optional=$false }
    }
} else {
    Write-Skip "No local/ dir at source - skipping all local plugins."
}

# 3. blocks/sentientia_*/ - sentientia-prefixed block plugins only (per chip scope).
$blocksRoot = Join-Path $Source 'blocks'
if (Test-Path -LiteralPath $blocksRoot) {
    Get-ChildItem -Path $blocksRoot -Directory -Filter 'sentientia_*' -ErrorAction SilentlyContinue | ForEach-Object {
        $copyPlan += @{ Label='block'; RelPath="blocks\$($_.Name)"; Optional=$false }
    }
} else {
    Write-Skip "No blocks/ dir at source - skipping all block plugins."
}

# 4. mod/quiz/accessrule/airpay_proctoring - quiz access rule.
$copyPlan += @{ Label='quizaccess'; RelPath='mod\quiz\accessrule\airpay_proctoring'; Optional=$true }

# 5. payment/gateway/airpay - paygw plugin.
$copyPlan += @{ Label='paygw';      RelPath='payment\gateway\airpay';                Optional=$true }

Write-Step "Copy plan built: $($copyPlan.Count) source subtree(s) to mirror."

# ----------------------------------------------------------------------
# Execute the copy plan.
# ----------------------------------------------------------------------
Write-Section 'Copy phase'

$copied  = 0
$skipped = 0
$failed  = 0

foreach ($item in $copyPlan) {
    $src = Join-Path $Source $item.RelPath
    $dst = Join-Path $Target $item.RelPath

    if (-not (Test-Path -LiteralPath $src)) {
        if ($item.Optional) {
            Write-Skip "[$($item.Label)] source missing (optional): $($item.RelPath)"
            $skipped++
        } else {
            Write-Err "[$($item.Label)] source missing (required): $($item.RelPath)"
            $failed++
        }
        continue
    }

    if ($DryRun) {
        Write-Info "[$($item.Label)] WOULD COPY $($item.RelPath)"
        Write-Info "             $src"
        Write-Info "         ->  $dst"
        $copied++
        continue
    }

    # Ensure target parent exists.
    $dstParent = Split-Path -Path $dst -Parent
    if (-not (Test-Path -LiteralPath $dstParent)) {
        New-Item -ItemType Directory -Path $dstParent -Force | Out-Null
    }

    # robocopy flags:
    #   /E    copy subdirs incl. empty
    #   /MT:8 multi-thread (8 threads)
    #   /NFL  no file list (unless VerboseLog)
    #   /NDL  no dir list (unless VerboseLog)
    #   /NJH  no job header
    #   /NJS  no job summary
    #   /NP   no progress percentage
    # robocopy exit code semantics: 0/1/2/3 = OK, 4+ = failure.
    $robocopyArgs = @($src, $dst, '/E', '/MT:8', '/NJH', '/NJS', '/NP')
    if (-not $VerboseLog) {
        $robocopyArgs += @('/NFL', '/NDL', '/NC', '/NS')
    }

    Write-Info "[$($item.Label)] copying $($item.RelPath)..."
    $start = Get-Date
    & robocopy @robocopyArgs | Out-Null
    $rcExit = $LASTEXITCODE
    $elapsed = (Get-Date) - $start

    if ($rcExit -ge 8) {
        Write-Err "[$($item.Label)] robocopy failed (exit=$rcExit) for $($item.RelPath)"
        $failed++
    } else {
        $fileCount = (Get-ChildItem -LiteralPath $dst -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
        Write-Ok "[$($item.Label)] $($item.RelPath) ($fileCount files, $([Math]::Round($elapsed.TotalSeconds,1))s)"
        $copied++
    }
}

Write-Host ''
Write-Host "Copy summary: $copied copied, $skipped skipped, $failed failed." -ForegroundColor Cyan

if ($failed -gt 0) {
    Write-Err "$failed copy operation(s) failed - bailing out before running upgrade.php."
    exit 3
}

# ----------------------------------------------------------------------
# Run Moodle CLI upgrade + cache purge (skipped on -DryRun).
# ----------------------------------------------------------------------
if ($DryRun) {
    Write-Section 'DryRun complete - skipping upgrade.php + purge_caches.php'
    exit 0
}

Write-Section 'Moodle CLI phase'

$upgradeScript = Join-Path $cliDir 'upgrade.php'
$purgeScript   = Join-Path $cliDir 'purge_caches.php'

if (-not (Test-Path -LiteralPath $upgradeScript)) {
    Write-Err "upgrade.php not found at: $upgradeScript"
    exit 4
}
if (-not (Test-Path -LiteralPath $purgeScript)) {
    Write-Err "purge_caches.php not found at: $purgeScript"
    exit 4
}

# Upgrade. Per CLAUDE.md section 2, the CLI tools must run with cwd = public\.
Write-Step 'Running: php admin/cli/upgrade.php --non-interactive'
Push-Location -LiteralPath $Target
try {
    & php $upgradeScript --non-interactive
    $upgradeExit = $LASTEXITCODE
} finally {
    Pop-Location
}
if ($upgradeExit -ne 0) {
    Write-Err "upgrade.php exited with code $upgradeExit - inspect output above."
    exit 5
}
Write-Ok 'upgrade.php completed.'

# Purge caches.
Write-Step 'Running: php admin/cli/purge_caches.php'
Push-Location -LiteralPath $Target
try {
    & php $purgeScript
    $purgeExit = $LASTEXITCODE
} finally {
    Pop-Location
}
if ($purgeExit -ne 0) {
    Write-Err "purge_caches.php exited with code $purgeExit - inspect output above."
    exit 6
}
Write-Ok 'purge_caches.php completed.'

# ----------------------------------------------------------------------
# Next-steps checklist - the part that prevents the "stale localhost" pitfall.
# ----------------------------------------------------------------------
Write-Section 'Next steps'

Write-Host ''
Write-Host '  1. Hard-reload your browser:        Ctrl+Shift+R'      -ForegroundColor White
Write-Host '     (regular reload will NOT pick up new CSS/JS bundles)' -ForegroundColor DarkGray
Write-Host ''
Write-Host '  2. Smoke-test these URLs as Learner role (NOT admin):' -ForegroundColor White
Write-Host '     - http://localhost:8081/moodle/'                    -ForegroundColor White
Write-Host '     - http://localhost:8081/moodle/my/dashboard.php'    -ForegroundColor White
Write-Host '     - http://localhost:8081/moodle/local/airpay_courses/' -ForegroundColor White
Write-Host '     - http://localhost:8081/moodle/login/index.php'     -ForegroundColor White
Write-Host ''
Write-Host '  3. Open DevTools console - verify zero JS errors.'     -ForegroundColor White
Write-Host ''
Write-Host '  4. Switch to mobile viewport (Ctrl+Shift+M, 590px):'   -ForegroundColor White
Write-Host '     - Navbar collapses to hamburger'                    -ForegroundColor DarkGray
Write-Host '     - No horizontal scrollbar on any page'              -ForegroundColor DarkGray
Write-Host ''
Write-Host '  5. If a UI change was deployed, save 3 screenshots to' -ForegroundColor White
Write-Host '     docs/visual-evidence/YYYY-MM-DD/ (per CLAUDE.md section 4).' -ForegroundColor DarkGray
Write-Host ''
Write-Host '  6. For production deploy, do NOT use this script:'     -ForegroundColor White
Write-Host '     GitHub UI -> Actions -> "Deploy to production".'    -ForegroundColor DarkGray
Write-Host '     See docs/operations/deploy-runbook.md.'             -ForegroundColor DarkGray
Write-Host ''

Write-Section 'Deploy complete.'
exit 0
