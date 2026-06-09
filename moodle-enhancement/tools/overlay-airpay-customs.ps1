# overlay-airpay-customs.ps1
#
# Lay down Airpay customizations on top of a fresh Moodle 5.2 tree.
#
# Pre-requisite:
#   - Moodle 5.2 source copied to $dst (default C:\xampp\htdocs\moodle5.2\)
#   - Our 5.1.3+ tree exists at $src (default C:\xampp\htdocs\moodle5\)
#
# What this script does:
#   - Copy our theme/sentientia into the 5.2 tree
#   - Repair stale theme_airpayux -> theme_sentientia AMD module names baked into
#     the copied theme/sentientia/amd/build bundles (idempotent; durable fix for
#     the F-LOAD-02 / ADR-025 follow-up (c) theme-side stale-bundle gap)
#   - Copy all local/sentientia_* + local/sentientia_* plugins
#   - Copy our airpay_* + patched vendor blocks
#   - Copy admin/tool/certificate (vendor plugin we ship)
#   - Copy root utility files (airpay-audit-loginas.php)
#   - DOES NOT copy config.php (that's per-instance)
#   - LOGS every collision (file already exists at target with different content)
#
# ASCII-only - PowerShell 5.1 friendly.

[CmdletBinding()]
param(
    [string]$Source = 'C:\xampp\htdocs\moodle5\public',
    [string]$Target = 'C:\xampp\htdocs\moodle5.2\public',
    [string]$LogPath = 'D:\Claude Local\moodle-5.2-diffs\overlay-log.txt'
)

$ErrorActionPreference = 'Continue'
"=== Overlay log $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ===" | Out-File $LogPath

function Log {
    param([string]$Msg)
    Write-Host $Msg
    $Msg | Out-File -Append -FilePath $LogPath
}

function Copy-Tree {
    param(
        [string]$Label,
        [string]$RelPath
    )
    $s = Join-Path $Source $RelPath
    $t = Join-Path $Target $RelPath
    if (-not (Test-Path $s)) {
        Log "[$Label] SKIP (source missing): $RelPath"
        return
    }
    $existed = Test-Path $t
    if ($existed) {
        Log "[$Label] COLLISION (target exists): $RelPath - merging..."
    } else {
        Log "[$Label] COPY: $RelPath"
    }
    # Use robocopy for efficient recursive copy
    $start = Get-Date
    robocopy $s $t /E /MT:8 /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    $elapsed = (Get-Date) - $start
    $count = (Get-ChildItem $t -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    Log "[$Label]   -> ${count} files total at target after copy ($([Math]::Round($elapsed.TotalSeconds,1))s)"
}

function Copy-File {
    param(
        [string]$Label,
        [string]$RelPath
    )
    $s = Join-Path $Source $RelPath
    $t = Join-Path $Target $RelPath
    if (-not (Test-Path $s)) {
        Log "[$Label] SKIP (source missing): $RelPath"
        return
    }
    $existed = Test-Path $t
    if ($existed) {
        $sHash = (Get-FileHash $s).Hash
        $tHash = (Get-FileHash $t).Hash
        if ($sHash -eq $tHash) {
            Log "[$Label] NOOP (identical): $RelPath"
            return
        } else {
            Log "[$Label] CONFLICT (different content): $RelPath - overwriting"
        }
    } else {
        Log "[$Label] NEW FILE: $RelPath"
    }
    $parent = Split-Path $t -Parent
    if (-not (Test-Path $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    Copy-Item $s $t -Force
}

function Repair-AmdModuleNames {
    # SENTIENTIA de-brand AMD fix (durable pipeline form of the 2026-06-09 hot-fix).
    #
    # The git-tracked theme is theme_airpayux; the deployed webroot theme is
    # theme_sentientia. The hand-minified amd/build/*.js bundles bake the OLD
    # module name into their define("theme_airpayux/X", ...) call. RequireJS maps
    # a requested module to a FILE BY PATH (theme_sentientia/X ->
    # theme/sentientia/amd/build/X.min.js) but each file registers itself under
    # theme_airpayux/X, so the requested name never gets a matching define and the
    # factory never runs -> every AMD feature silently no-ops (dashboard charts
    # blank, cart badge dead, datatable/quickactions/loader/drawer inert).
    #
    # Theme-side sibling of ADR-025 follow-up (c) (the plugin-side stale-bundle
    # gap). Recorded as F-LOAD-02; hot-fixed live on 2026-06-09 (see
    # docs/audits/AMD-LOADING-FIXES-2026-06-09.md sections 2 / 6 / 7). This step
    # is the durable fix so a clean redeploy-from-git survives it.
    #
    # IDEMPOTENT: the .Contains guard skips files that are already clean, so on a
    # webroot->webroot overlay (source already de-branded) this is a no-op; on a
    # clean-from-git deploy (source carries theme_airpayux) it self-corrects.
    # Relative './X' deps and the dev-only *.min.js.map sourcemaps are untouched
    # (the *.js filter excludes .map; maps are never executed).
    #
    # SCOPE: deliberately limited to the THEME's amd/build tree. Do NOT broaden
    # this to local/ or blocks/: airpay_ratings (and paygw_airpay) are
    # legitimately NOT renamed per ADR-025, and a blanket airpay->sentientia
    # rewrite would corrupt them. (The literal token here, theme_airpayux, cannot
    # match those plugins anyway - but keep the path scope narrow regardless.)
    param(
        [string]$BuildDir = (Join-Path $Target 'theme\sentientia\amd\build')
    )
    $old = 'theme_airpayux'
    $new = 'theme_sentientia'
    if (-not (Test-Path $BuildDir)) {
        Log "[amd-rename] SKIP (no theme build dir): $BuildDir"
        return
    }
    # UTF-8 with NO BOM - a BOM before the leading define(...) would break the
    # module registration that this fix exists to repair.
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $changed = 0
    Get-ChildItem -Path $BuildDir -Recurse -Filter '*.js' -File | ForEach-Object {
        $content = [System.IO.File]::ReadAllText($_.FullName)
        if ($content.Contains($old)) {
            [System.IO.File]::WriteAllText($_.FullName, $content.Replace($old, $new), $utf8NoBom)
            $changed++
        }
    }
    Log "[amd-rename]   -> $changed build file(s) rewritten ${old} -> ${new}"

    # Post-condition grep-gate (house style; mirrors ADR-025 follow-up (c)'s
    # "guard: grep -rl ... == 0"). A surviving token means the deploy would serve
    # dead JS, so FAIL LOUD rather than ship a silently-broken platform. To soften
    # to warn-and-continue, replace the throw with a Log line.
    $survivors = Get-ChildItem -Path $BuildDir -Recurse -Filter '*.js' -File |
        Where-Object { ([System.IO.File]::ReadAllText($_.FullName)).Contains($old) }
    if ($survivors) {
        $names = ($survivors | ForEach-Object { $_.Name }) -join ', '
        Log "[amd-rename] FAIL: '$old' still present in $($survivors.Count) build file(s): $names"
        throw "AMD module-name rename incomplete: '$old' survives in theme/sentientia/amd/build ($names). Aborting deploy."
    }
    Log "[amd-rename]   OK: 0 '${old}' tokens remain in theme/sentientia/amd/build"
}

Log ""
Log "=== Theme ==="
Copy-Tree 'theme' 'theme\sentientia'
# Durable form of the 2026-06-09 hot-fix - rewrite stale theme_airpayux module
# names in the copied build bundles (idempotent; see Repair-AmdModuleNames).
Repair-AmdModuleNames -BuildDir (Join-Path $Target 'theme\sentientia\amd\build')

Log ""
Log "=== local/sentientia_* (30 plugins) ==="
$localPlugins = Get-ChildItem (Join-Path $Source 'local') -Directory -Filter 'airpay_*'
foreach ($p in $localPlugins) {
    Copy-Tree 'local' "local\$($p.Name)"
}

Log ""
Log "=== local/sentientia_* ==="
$sentientia = Get-ChildItem (Join-Path $Source 'local') -Directory -Filter 'sentientia_*' -ErrorAction SilentlyContinue
foreach ($p in $sentientia) {
    Copy-Tree 'local' "local\$($p.Name)"
}

Log ""
Log "=== blocks/sentientia_* (+ any legacy airpay_*) ==="
$blocks = Get-ChildItem (Join-Path $Source 'blocks') -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -like 'sentientia_*' -or $_.Name -like 'airpay_*' }
foreach ($b in $blocks) {
    Copy-Tree 'block' "blocks\$($b.Name)"
}

Log ""
Log "=== blocks/learnerscript + reportdashboard + reporttiles (vendor blocks we patch) ==="
Copy-Tree 'block' 'blocks\learnerscript'
Copy-Tree 'block' 'blocks\reportdashboard'
Copy-Tree 'block' 'blocks\reporttiles'

Log ""
Log "=== Patched files at blocks/ root ==="
Copy-File 'block-patch' 'blocks\learnerscript_lib_PATCHED.php'
Copy-File 'block-patch' 'blocks\reportdashboard_dashboard_PATCHED.php'

Log ""
Log "=== admin/tool/certificate (vendor tool) ==="
Copy-Tree 'admin-tool' 'admin\tool\certificate'

Log ""
Log "=== payment/gateway/airpay (live payment gateway plugin) ==="
# Added 2026-05-23 — Phase B.12 hotfix. Earlier overlay missed this plugin
# because it lived only in the production XAMPP tree, not in the
# moodle-enhancement source repo. Now tracked in repo + copied through.
Copy-Tree 'paygw' 'payment\gateway\airpay'

Log ""
Log "=== mod/quiz/accessrule/sentientia_proctoring (quiz access proctoring) ==="
# Added 2026-05-23 — Phase B.12 hotfix. Was tracked in repo but the overlay
# script's copy list was incomplete. Source-of-truth is the repo version
# (2026051300, has db/install.xml + db/upgrade.php) which is newer than
# what production XAMPP 5.1 has (2026051120, no DB schema).
Copy-Tree 'quizaccess' 'mod\quiz\accessrule\sentientia_proctoring'

Log ""
Log "=== Root utility ==="
Copy-File 'root' 'airpay-audit-loginas.php'

Log ""
Log "=== Summary ==="
$dstCount = (Get-ChildItem $Target -Recurse -File).Count
$dstSize = [Math]::Round((Get-ChildItem $Target -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB, 2)
Log "Target tree: $dstCount files, $dstSize MB"
Log "Overlay log saved to: $LogPath"
