# Airpay Academy deploy — Windows / XAMPP rehearsal mirror.
# Copies moodle-enhancement\local|theme|blocks → C:\xampp\htdocs\moodle5\public\
# Uses Robocopy with /MIR to keep destination identical to source.
#
# Re-run safe: a second invocation reports "0 copied" if nothing changed.

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSCommandPath)
$srcBase  = Join-Path $repoRoot 'local'
$themeSrc = Join-Path $repoRoot 'theme\airpayux'
$blocksSrc = Join-Path $repoRoot 'blocks'

# Override with $env:MOODLE_PUBLIC if needed.
$dstBase = if ($env:MOODLE_PUBLIC) { $env:MOODLE_PUBLIC } else { 'C:\xampp\htdocs\moodle5\public' }

Write-Host "═══════════════════════════════════════════════════════════════════"
Write-Host "Airpay Academy deploy (Windows rehearsal)"
Write-Host "  source:      $repoRoot"
Write-Host "  destination: $dstBase"
$gitDir = Split-Path -Parent (Split-Path -Parent $repoRoot)
if (Test-Path (Join-Path $gitDir '.git')) {
    Push-Location $gitDir
    Write-Host "  branch:      $(git rev-parse --abbrev-ref HEAD 2>$null)"
    Write-Host "  commit:      $(git rev-parse --short HEAD 2>$null)"
    Pop-Location
}
Write-Host "═══════════════════════════════════════════════════════════════════"

if (-not (Test-Path $srcBase)) { throw "Source not found: $srcBase" }
if (-not (Test-Path $dstBase)) { throw "Destination not found: $dstBase" }

# Robocopy flags:
#   /E   — copy subdirs including empty
#   /XO  — only newer (no overwrite of newer dest files)
#   /R:1 — retry once on locked file
#   /W:1 — wait 1s between retries
#   /NFL — no file list (less noise)
#   /NDL — no dir list
#   /NP  — no progress (avoid carriage-return spam)
#   /XD  — exclude dirs (.git etc.)
#   /XF  — exclude file patterns (*.bak, *.orig)

$robocopyArgs = @('/E', '/XO', '/R:1', '/W:1', '/NFL', '/NDL', '/NP',
                  '/XD', '.git', 'node_modules',
                  '/XF', '*.bak', '*.orig')

$totalCopied = 0
$totalSkipped = 0

function DeployDir($src, $dst, $label) {
    if (-not (Test-Path $src)) { return }
    if (-not (Test-Path $dst)) { New-Item -ItemType Directory -Force -Path $dst | Out-Null }
    Write-Host "  → $label"
    $output = & robocopy $src $dst @robocopyArgs 2>&1
    $exit = $LASTEXITCODE
    # Robocopy exit codes: 0 = no copies, 1 = files copied, 2 = extra files, 4 = mismatched
    # 8+ = error.
    if ($exit -ge 8) {
        Write-Host "    ROBOCOPY ERROR ($exit):"
        $output | Out-String | Write-Host
        throw "Robocopy failed for $label"
    }
    # Parse the summary line for counts.
    $summary = $output | Select-String -Pattern 'Files :' | Select-Object -First 1
    if ($summary) {
        Write-Host "    $summary"
    }
    return $exit
}

# 1. Each local plugin.
Get-ChildItem -Path $srcBase -Directory | ForEach-Object {
    $plugin = $_.Name
    DeployDir $_.FullName (Join-Path $dstBase "local\$plugin") "local\$plugin"
}

# 2. Theme.
if (Test-Path $themeSrc) {
    DeployDir $themeSrc (Join-Path $dstBase 'theme\airpayux') 'theme\airpayux'
}

# 3. Blocks.
if (Test-Path $blocksSrc) {
    Get-ChildItem -Path $blocksSrc -Directory | ForEach-Object {
        $block = $_.Name
        DeployDir $_.FullName (Join-Path $dstBase "blocks\$block") "blocks\$block"
    }
}

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════════"
Write-Host "OK: file deploy complete."
Write-Host ""
Write-Host "Next steps (run manually):"
Write-Host "  C:\xampp\php\php.exe C:\xampp\htdocs\moodle5\admin\cli\upgrade.php --non-interactive"
Write-Host "  C:\xampp\php\php.exe `"D:\Claude Local\airpay-ld-os\moodle-enhancement\audit\bump_jsrev.php`""
Write-Host "  C:\xampp\php\php.exe C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php"
Write-Host "═══════════════════════════════════════════════════════════════════"

# Robocopy returns 0-7 for various non-error conditions (1 = copied,
# 2 = extra files in dest, 3 = both). We've already handled real errors
# (>= 8) inline with throw. Force a clean exit so callers don't see
# robocopy's exit codes.
exit 0
