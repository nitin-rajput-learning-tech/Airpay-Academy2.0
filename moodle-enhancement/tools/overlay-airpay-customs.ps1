# overlay-airpay-customs.ps1
#
# Lay down Airpay customizations on top of a fresh Moodle 5.2 tree.
#
# Pre-requisite:
#   - Moodle 5.2 source copied to $dst (default C:\xampp\htdocs\moodle5.2\)
#   - Our 5.1.3+ tree exists at $src (default C:\xampp\htdocs\moodle5\)
#
# What this script does:
#   - Copy our theme/airpayux into the 5.2 tree
#   - Copy all local/airpay_* + local/sentientia_* plugins
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

Log ""
Log "=== Theme ==="
Copy-Tree 'theme' 'theme\airpayux'

Log ""
Log "=== local/airpay_* (30 plugins) ==="
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
Log "=== blocks/airpay_* ==="
$blocks = Get-ChildItem (Join-Path $Source 'blocks') -Directory -Filter 'airpay_*' -ErrorAction SilentlyContinue
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
Log "=== Root utility ==="
Copy-File 'root' 'airpay-audit-loginas.php'

Log ""
Log "=== Summary ==="
$dstCount = (Get-ChildItem $Target -Recurse -File).Count
$dstSize = [Math]::Round((Get-ChildItem $Target -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB, 2)
Log "Target tree: $dstCount files, $dstSize MB"
Log "Overlay log saved to: $LogPath"
