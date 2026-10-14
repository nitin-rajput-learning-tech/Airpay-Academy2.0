# package-sentientia.ps1 - build Sentientia LMS distributables
#
# Produces:
#   <OutRoot>/plugins/<dir>.zip    one Moodle-install ZIP per custom plugin (folder at zip root)
#   <OutRoot>/MANIFEST.md          component / type / release / install-order table
#   <OutRoot>/full/...zip          full platform bundle (whole public/ MINUS config.php)
#
# Usage:
#   pwsh -File tools/packaging/package-sentientia.ps1                 # plugins + manifest + full bundle
#   pwsh -File tools/packaging/package-sentientia.ps1 -SkipFull       # plugins + manifest only (fast)
#
# RETIRED FOR MOODLE 5.3 (ADR-033, 2026-10-08). Use tools/packaging/build-standalone.sh --target 5.3.
# This script packages public/ only. Moodle 5.3 keeps Bootstrap, Font Awesome, React and the design system in
# the ROOT lib/bundles (outside public/), so a public/-only bundle ships without icons and without all
# React/ESM UI. It also reads the local 5.1 dev webroot rather than git, which is how a fix committed to git
# could miss a package. It still works against a pre-5.3 tree and refuses a 5.3 one.
param(
  [string]$PublicRoot = "C:\xampp\htdocs\moodle5\public",
  [string]$OutRoot    = "D:\Claude Local\Moodle Backup\sentientia-package",
  [switch]$SkipFull
)
$ErrorActionPreference = "Stop"
$verFile = Join-Path $PublicRoot "version.php"
if (Test-Path $verFile) {
  $relLine = Get-Content $verFile | Where-Object { $_ -match '^\$release\s*=' } | Select-Object -First 1
  if ($relLine -match "'(\d+)\.(\d+)") {
    if ([int]$Matches[1] -gt 5 -or ([int]$Matches[1] -eq 5 -and [int]$Matches[2] -ge 3)) {
      throw "package-sentientia.ps1 is retired for Moodle 5.3+ (it packages public/ only and drops root lib/bundles). Use: tools/packaging/build-standalone.sh --target 5.3"
    }
  }
}
$stamp = Get-Date -Format "yyyy-MM-dd"
$OutRoot = "$OutRoot-$stamp"
$pluginsOut = Join-Path $OutRoot "plugins"
$fullOut    = Join-Path $OutRoot "full"
New-Item -ItemType Directory -Force -Path $pluginsOut | Out-Null
New-Item -ItemType Directory -Force -Path $fullOut    | Out-Null
# Clear any stale ZIPs from a prior run so the output is exactly the current set.
Get-ChildItem $pluginsOut -Filter *.zip -ErrorAction SilentlyContinue | Remove-Item -Force

# Enumerate the custom plugins by Moodle type (airpay_* / sentientia_* + the airpayux theme).
$typemap = @(
  @{ type = "local";  dir = "local"  },
  @{ type = "block";  dir = "blocks" },
  @{ type = "enrol";  dir = "enrol"  },
  @{ type = "theme";  dir = "theme"  }
)
$targets = @()
foreach ($t in $typemap) {
  $base = Join-Path $PublicRoot $t.dir
  if (-not (Test-Path $base)) { continue }
  Get-ChildItem -Path $base -Directory | Where-Object {
    if ($t.type -eq "theme") { $_.Name -eq "airpayux" }
    else { $_.Name -match '^(airpay_|sentientia)' }
  } | ForEach-Object {
    $targets += [pscustomobject]@{ Type = $t.type; Dir = $t.dir; Name = $_.Name; Path = $_.FullName }
  }
}

$lines = @()
$lines += "# Sentientia LMS - Package Manifest"
$lines += ""
$lines += "Generated: $stamp from ``$PublicRoot``"
$lines += ""
$lines += "Per-plugin ZIPs install via *Site administration -> Plugins -> Install plugins*."
$lines += "Install ``local_sentientia_core`` and ``local_airpay_core`` FIRST (other plugins depend on them)."
$lines += ""
$lines += "| # | Component | Type | Install path | Release |"
$lines += "|---|-----------|------|--------------|---------|"

$i = 0
foreach ($p in ($targets | Sort-Object Type, Name)) {
  $i++
  $component = "{0}_{1}" -f $p.Type, $p.Name
  $rel = ""
  $vphp = Join-Path $p.Path "version.php"
  if (Test-Path $vphp) {
    $m = Select-String -Path $vphp -Pattern "release\s*=\s*'([^']+)'" | Select-Object -First 1
    if ($m) { $rel = $m.Matches[0].Groups[1].Value }
  }
  $zip = Join-Path $pluginsOut ("{0}.zip" -f $component)
  if (Test-Path $zip) { Remove-Item $zip -Force }
  Compress-Archive -Path $p.Path -DestinationPath $zip -CompressionLevel Optimal
  $lines += ("| {0} | {1} | {2} | {3}/{4} | {5} |" -f $i, $component, $p.Type, $p.Dir, $p.Name, $rel)
}
$lines += ""
$lines += "Total custom plugins packaged: $i"
$lines += ""
$lines += "## Full platform bundle"
$lines += "``full/`` contains the whole Moodle + Sentientia tree (public/) MINUS ``config.php``"
$lines += "(each install supplies its own config.php). Deploy by extracting over a clean web root,"
$lines += "then run ``php admin/cli/install.php`` (fresh) or copy onto an existing Moodle and upgrade."
Set-Content -Path (Join-Path $OutRoot "MANIFEST.md") -Value $lines -Encoding utf8
Write-Output ("PLUGINS: {0} ZIPs -> {1}" -f $i, $pluginsOut)

if (-not $SkipFull) {
  $fullZip = Join-Path $fullOut ("sentientia-full-platform-{0}.tar.gz" -f $stamp)
  if (Test-Path $fullZip) { Remove-Item $fullZip -Force }
  # Compress-Archive cannot read live, server-locked files. Stage a static copy
  # first with robocopy (tolerant of open/locked files), excluding config.php (DB
  # creds) and volatile cache/session dirs, then zip the static copy.
  $stage = Join-Path $env:TEMP ("sentientia-stage-{0}" -f $stamp)
  if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
  robocopy $PublicRoot $stage /E /R:1 /W:1 /XF "config.php" "config.php.*" /XD "cache" "localcache" "sessions" "temp" "node_modules" /NFL /NDL /NJH /NJS /NP | Out-Null
  if ($LASTEXITCODE -ge 8) { throw "robocopy staging failed (exit $LASTEXITCODE)" }
  $global:LASTEXITCODE = 0
  # tar (bsdtar) handles Moodle's deep paths that exceed Windows MAX_PATH; Compress-Archive does not.
  tar -czf $fullZip -C $stage .
  if ($LASTEXITCODE -ne 0) { throw "tar failed (exit $LASTEXITCODE)" }
  Remove-Item $stage -Recurse -Force
  Write-Output ("FULL: {0}  ({1} MB)" -f $fullZip, [int]((Get-Item $fullZip).Length / 1MB))
}
Write-Output "DONE"
