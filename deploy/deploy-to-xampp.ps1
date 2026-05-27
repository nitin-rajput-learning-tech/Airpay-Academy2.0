<#
.SYNOPSIS
    One-command deploy of Airpay Academy / Sentientia LMS source to local XAMPP.

.DESCRIPTION
    Copies the in-repo Moodle plugin/theme subtrees from the source working
    copy (default D:\Claude Local\airpay-ld-os\moodle-enhancement) to the
    matching paths under one or more local XAMPP Moodle public roots, then
    runs Moodle's CLI upgrade + cache purge so the deployed code is live
    for the next request.

    Targets are named in the $Targets ordered-hashtable at the top of the
    script. The default target is `local80` (the primary XAMPP install on
    port 8080). The second target `local81` covers the snapshot/comparison
    XAMPP install on port 8081 - used to verify the same behaviour against
    a parallel codebase. Pass -TargetName local81 to deploy there, or
    -TargetName all to deploy to every configured target sequentially.

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
    confusion on 2026-05-24). The smoke-test URLs in the checklist are
    pulled from the active target so they always match what was deployed.

.PARAMETER TargetName
    Named target key from the $Targets hashtable. Default 'local80' (the
    port-8080 primary). Use 'local81' for the snapshot install on
    port 8081. Use 'all' to deploy sequentially to every configured target.

    To register a new target, add an entry to the $Targets block at the
    top of this script.

.PARAMETER Target
    Absolute path to the XAMPP Moodle public dir. When set, overrides
    the path resolved from -TargetName (useful when an install lives
    outside the registered locations). The CLI dir is derived as
    "<Target>\..\admin\cli\" - matches the XAMPP layout documented
    in CLAUDE.md section 2.

.PARAMETER Source
    Absolute path to the moodle-enhancement working copy. Default:
    D:\Claude Local\airpay-ld-os\moodle-enhancement

.PARAMETER SkipCli
    Skip the upgrade.php + purge_caches.php steps. Useful when the host
    PHP version doesn't match the target's required PHP (e.g. a target
    bind-mounted into a Docker container with PHP 8.4 while the host
    runs PHP 8.2). Files are still copied. The operator runs the CLI
    themselves afterwards.

.PARAMETER DryRun
    List every source -> target copy that would happen, but don't copy.
    Skips the upgrade + purge_caches calls as well. Safe to run anywhere.

.PARAMETER VerboseLog
    Emit per-file robocopy output (default is summary-only). Useful when
    a stale-file issue is suspected and you want to see exactly which
    files were touched.

.EXAMPLE
    # Default deploy: copy + upgrade + purge against local80 (port 8080).
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1

.EXAMPLE
    # Deploy to the snapshot/comparison install on port 8081.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -TargetName local81

.EXAMPLE
    # Deploy to BOTH the primary (8080) and the snapshot (8081) sequentially.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -TargetName all

.EXAMPLE
    # Preview what would be copied without touching anything.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -DryRun

.EXAMPLE
    # Custom XAMPP install location (overrides whatever -TargetName resolves to).
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -Target 'D:\xampp-alt\htdocs\moodle\public'

.EXAMPLE
    # Verbose per-file output.
    pwsh -NoProfile -File deploy\deploy-to-xampp.ps1 -VerboseLog

.NOTES
    Authored: 2026-05-24, Sentientia LMS P1 deploy-automation chip.
    Extended: 2026-05-24, Wave A3 P0-cleanup chip - multi-target support
        for the parallel localhost:8081 install (was stuck at v1.0.31-beta
        because deploys only hit port 8080).
    Reference: moodle-enhancement/docs/operations/deploy-runbook.md
    Reference: moodle-enhancement/tools/overlay-airpay-customs.ps1 (sibling
        script for the one-time 5.1 -> 5.2 cutover overlay; this script is
        for daily local-XAMPP redeploys).

    Hard rules (from CLAUDE.md section 13):
      - This script ONLY touches local XAMPP paths. It NEVER reaches out
        to live.airpay.academy. Production deploy lives in
        .github/workflows/deploy-production.yml under a workflow_dispatch
        confirm gate.
      - Pre-commit hooks are honoured by every commit that touches this file.
#>

[CmdletBinding()]
param(
    [ValidateSet('local80', 'local81', 'all')]
    [string]$TargetName = 'local80',

    [string]$Target,

    [string]$Source = 'D:\Claude Local\airpay-ld-os\moodle-enhancement',

    [switch]$SkipCli,
    [switch]$DryRun,
    [switch]$VerboseLog
)

$ErrorActionPreference = 'Stop'

# ----------------------------------------------------------------------
# Named target registry.
#
# Each key maps to a hashtable with:
#   - Label : human-readable name shown in logs.
#   - Path  : absolute path to the Moodle "public" dir (the same dir
#             passed to -Target in the original single-target script).
#   - Url   : the http://localhost URL prefix shown in the next-steps
#             checklist. Without this, the script previously hard-coded
#             port 8081 in every run regardless of which target was hit.
#
# To register a new target (e.g. a third install on another port), add a
# new entry here. All downstream logic resolves through this table.
# ----------------------------------------------------------------------
$Targets = [ordered]@{
    'local80' = @{
        Label = 'Primary XAMPP - port 8080 (PHP 8.2, Moodle 5.1, default deploy)'
        Path  = 'C:\xampp\htdocs\moodle5\public'
        Url   = 'http://localhost:8080/moodle/'
    }
    'local81' = @{
        Label = 'Snapshot XAMPP - port 8081 (parallel install for comparison testing)'
        Path  = 'C:\xampp81\htdocs\moodle5\public'
        Url   = 'http://localhost:8081/moodle/'
    }
}

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
# Resolve the target list. -TargetName 'all' fans out to every registered
# target. An explicit -Target overrides the resolved path of whatever
# -TargetName picked (single target only - -Target + -TargetName all is
# rejected as ambiguous).
# ----------------------------------------------------------------------
function Resolve-TargetList {
    param(
        [string]$TargetNameArg,
        [string]$ExplicitPath
    )

    $resolved = @()

    if ($TargetNameArg -eq 'all') {
        if ($ExplicitPath) {
            throw "-Target cannot be combined with -TargetName 'all'. Pick one."
        }
        foreach ($key in $Targets.Keys) {
            $resolved += @{
                Name  = $key
                Label = $Targets[$key].Label
                Path  = $Targets[$key].Path
                Url   = $Targets[$key].Url
            }
        }
        return $resolved
    }

    if (-not $Targets.Contains($TargetNameArg)) {
        throw "Unknown -TargetName '$TargetNameArg'. Known: $($Targets.Keys -join ', '), all"
    }

    $entry = $Targets[$TargetNameArg]
    $path  = if ($ExplicitPath) { $ExplicitPath } else { $entry.Path }

    $resolved += @{
        Name  = $TargetNameArg
        Label = $entry.Label
        Path  = $path
        Url   = $entry.Url
    }
    return $resolved
}

# ----------------------------------------------------------------------
# Locate php.exe for a given target.
#
# Strategy:
#   1. If <xampp_root>\php\php.exe exists, use it. This avoids picking
#      up the WRONG XAMPP's PHP when both are on PATH (e.g. C:\xampp\php
#      and C:\xampp81\php both in PATH, but we're deploying to xampp81).
#   2. Otherwise fall back to Get-Command (PATH lookup).
#
# Layout assumption: <xampp_root>\htdocs\moodle*\public is the Target,
# so the XAMPP root is 3 directories up from Target.
# ----------------------------------------------------------------------
function Resolve-PhpExe {
    param([string]$TargetPath)

    # <Target> = ...\xampp\htdocs\moodle5\public
    # Walk up: public -> moodle5 -> htdocs -> xampp
    $xamppRoot = Split-Path (Split-Path (Split-Path $TargetPath -Parent) -Parent) -Parent
    $candidate = Join-Path $xamppRoot 'php\php.exe'

    if (Test-Path -LiteralPath $candidate) {
        return $candidate
    }

    $phpCmd = Get-Command -Name php.exe -ErrorAction SilentlyContinue
    if ($phpCmd) {
        return $phpCmd.Source
    }

    return $null
}

# ----------------------------------------------------------------------
# Deploy one target. Wrapped so -TargetName all can call us N times
# without copy-pasting the body. Returns 0 on success, non-zero on failure
# (matching the original script's exit codes for back-compat).
# ----------------------------------------------------------------------
function Invoke-OneTarget {
    param([hashtable]$T)

    Write-Section ("Deploying to: {0}" -f $T.Name)
    Write-Info $T.Label

    # ------------------------------------------------------------------
    # Pre-flight per target.
    # ------------------------------------------------------------------
    Write-Step 'Pre-flight checks'

    if (-not (Test-Path -LiteralPath $Source)) {
        Write-Err "Source path not found: $Source"
        Write-Err 'Pass -Source <path> if your working copy lives elsewhere.'
        return 2
    }
    Write-Ok "Source exists: $Source"

    if (-not (Test-Path -LiteralPath $T.Path)) {
        Write-Err ("Target path not found: {0}" -f $T.Path)
        Write-Err ("Resolved from -TargetName '{0}'." -f $T.Name)
        Write-Err 'Pass -Target <path> to override, or edit the $Targets'
        Write-Err 'hashtable at the top of this script to register the right path.'
        Write-Err 'Expected layout (per CLAUDE.md section 2):'
        Write-Err '    <xampp>\htdocs\moodle*\public\        <- -Target'
        Write-Err '    <xampp>\htdocs\moodle*\admin\cli\     <- CLI tools'
        return 2
    }
    Write-Ok ("Target exists: {0}" -f $T.Path)

    # Derive CLI dir from target path. Layout per CLAUDE.md section 2:
    #   <Target>             = C:\xampp\htdocs\moodle5\public
    #   <Target>\..          = C:\xampp\htdocs\moodle5
    #   <Target>\..\admin\cli = C:\xampp\htdocs\moodle5\admin\cli
    $cliDir = Join-Path (Split-Path $T.Path -Parent) 'admin\cli'
    if (-not (Test-Path -LiteralPath $cliDir)) {
        Write-Err "CLI dir not found at expected location: $cliDir"
        Write-Err 'Verify the XAMPP layout matches CLAUDE.md section 2.'
        return 2
    }
    Write-Ok "CLI dir resolved: $cliDir"

    # PHP discovery - prefer XAMPP-local php.exe over PATH so we don't
    # accidentally use the wrong XAMPP's PHP when both are on PATH.
    $phpExePath = $null
    if (-not $SkipCli -and -not $DryRun) {
        $phpExePath = Resolve-PhpExe -TargetPath $T.Path
        if (-not $phpExePath) {
            Write-Err 'php.exe not found in <xampp_root>\php\ nor on PATH.'
            Write-Err 'Either add C:\xampp\php (or C:\xampp81\php) to PATH,'
            Write-Err 'run from "XAMPP Shell", or pass -SkipCli to copy files only.'
            return 2
        }
        Write-Ok "php.exe resolved: $phpExePath"
    }

    if ($DryRun) {
        Write-Info 'DryRun is ON - no copies, no upgrade, no cache purge.'
    }
    if ($SkipCli) {
        Write-Info 'SkipCli is ON - files copied, upgrade.php + purge_caches.php skipped.'
    }
    if ($VerboseLog) {
        Write-Info 'VerboseLog is ON - per-file output enabled for robocopy.'
    }

    # ------------------------------------------------------------------
    # Build the copy plan. Each entry is { Label, RelPath, Optional }.
    # RelPath is the path under Source AND under Target. Optional entries
    # that are missing from Source are SKIPPED quietly.
    # Wildcard expansion (local/*, blocks/sentientia_*) enumerates Source.
    # ------------------------------------------------------------------
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

    # ------------------------------------------------------------------
    # Execute the copy plan.
    # ------------------------------------------------------------------
    Write-Section ("Copy phase ({0})" -f $T.Name)

    $copied  = 0
    $skipped = 0
    $failed  = 0

    foreach ($item in $copyPlan) {
        $src = Join-Path $Source $item.RelPath
        $dst = Join-Path $T.Path $item.RelPath

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
    Write-Host ("Copy summary ({0}): {1} copied, {2} skipped, {3} failed." -f $T.Name, $copied, $skipped, $failed) -ForegroundColor Cyan

    if ($failed -gt 0) {
        Write-Err "$failed copy operation(s) failed - bailing out before running upgrade.php."
        return 3
    }

    # ------------------------------------------------------------------
    # Run Moodle CLI upgrade + cache purge (skipped on -DryRun / -SkipCli).
    # ------------------------------------------------------------------
    if ($DryRun) {
        Write-Section ("DryRun complete ({0}) - skipping upgrade.php + purge_caches.php" -f $T.Name)
        return 0
    }
    if ($SkipCli) {
        Write-Section ("SkipCli set ({0}) - copy done, upgrade + purge skipped" -f $T.Name)
        Write-Info 'Run the CLI yourself when ready:'
        Write-Info ("  Set-Location '{0}'" -f $T.Path)
        Write-Info ("  & '{0}' '{1}\upgrade.php' --non-interactive" -f $phpExePath, $cliDir)
        Write-Info ("  & '{0}' '{1}\purge_caches.php'"              -f $phpExePath, $cliDir)
        return 0
    }

    Write-Section ("Moodle CLI phase ({0})" -f $T.Name)

    $upgradeScript = Join-Path $cliDir 'upgrade.php'
    $purgeScript   = Join-Path $cliDir 'purge_caches.php'

    if (-not (Test-Path -LiteralPath $upgradeScript)) {
        Write-Err "upgrade.php not found at: $upgradeScript"
        return 4
    }
    if (-not (Test-Path -LiteralPath $purgeScript)) {
        Write-Err "purge_caches.php not found at: $purgeScript"
        return 4
    }

    # Upgrade. Per CLAUDE.md section 2, the CLI tools must run with cwd = public\.
    Write-Step 'Running: php admin/cli/upgrade.php --non-interactive'
    Push-Location -LiteralPath $T.Path
    try {
        & $phpExePath $upgradeScript --non-interactive
        $upgradeExit = $LASTEXITCODE
    } finally {
        Pop-Location
    }
    if ($upgradeExit -ne 0) {
        Write-Err "upgrade.php exited with code $upgradeExit - inspect output above."
        return 5
    }
    Write-Ok 'upgrade.php completed.'

    # Purge caches.
    Write-Step 'Running: php admin/cli/purge_caches.php'
    Push-Location -LiteralPath $T.Path
    try {
        & $phpExePath $purgeScript
        $purgeExit = $LASTEXITCODE
    } finally {
        Pop-Location
    }
    if ($purgeExit -ne 0) {
        Write-Err "purge_caches.php exited with code $purgeExit - inspect output above."
        return 6
    }
    Write-Ok 'purge_caches.php completed.'

    return 0
}

# ----------------------------------------------------------------------
# Main flow.
# ----------------------------------------------------------------------
Write-Section 'Airpay Academy / Sentientia LMS - deploy to local XAMPP'

try {
    $targetList = Resolve-TargetList -TargetNameArg $TargetName -ExplicitPath $Target
} catch {
    Write-Err $_.Exception.Message
    exit 2
}

Write-Info ("Targets resolved: {0}" -f (($targetList | ForEach-Object { $_.Name }) -join ', '))

$overallExit = 0
$results     = @()

foreach ($t in $targetList) {
    $rc = Invoke-OneTarget -T $t
    $results += [pscustomobject]@{
        Name = $t.Name
        Path = $t.Path
        Url  = $t.Url
        Exit = $rc
    }
    if ($rc -ne 0 -and $overallExit -eq 0) {
        $overallExit = $rc
    }
    if ($rc -ne 0 -and $targetList.Count -gt 1) {
        Write-Err ("Target '{0}' failed with exit code {1}. Continuing to next target." -f $t.Name, $rc)
    }
}

# ----------------------------------------------------------------------
# Next-steps checklist - the part that prevents the "stale localhost" pitfall.
# Smoke-test URLs are pulled from each target's Url field so multi-target
# runs show every URL that needs verifying.
# ----------------------------------------------------------------------
Write-Section 'Next steps'

Write-Host ''
Write-Host '  1. Hard-reload your browser:        Ctrl+Shift+R'      -ForegroundColor White
Write-Host '     (regular reload will NOT pick up new CSS/JS bundles)' -ForegroundColor DarkGray
Write-Host ''
Write-Host '  2. Smoke-test these URLs as Learner role (NOT admin):' -ForegroundColor White
foreach ($r in $results) {
    if ($r.Exit -ne 0) { continue }
    Write-Host ("     [{0}]" -f $r.Name) -ForegroundColor Cyan
    Write-Host ("     - {0}"                          -f $r.Url)                    -ForegroundColor White
    Write-Host ("     - {0}my/dashboard.php"          -f $r.Url)                    -ForegroundColor White
    Write-Host ("     - {0}local/airpay_courses/"     -f $r.Url)                    -ForegroundColor White
    Write-Host ("     - {0}login/index.php"           -f $r.Url)                    -ForegroundColor White
}
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

# Per-target summary table (useful when -TargetName all was used).
if ($results.Count -gt 1) {
    Write-Section 'Per-target summary'
    Write-Host ''
    foreach ($r in $results) {
        $status = if ($r.Exit -eq 0) { 'OK  ' } else { ('FAIL{0}' -f $r.Exit) }
        $color  = if ($r.Exit -eq 0) { 'Green' } else { 'Red' }
        Write-Host ("  [{0}] {1}  {2}" -f $status, $r.Name.PadRight(8), $r.Path) -ForegroundColor $color
    }
    Write-Host ''
}

if ($overallExit -eq 0) {
    Write-Section 'Deploy complete.'
} else {
    Write-Section ("Deploy finished with errors (exit={0})" -f $overallExit)
}
exit $overallExit
