# install-hooks.ps1 — Airpay Academy git hook installer (PowerShell)
#
# Installs .claude/hooks/pre-commit.sh into .git/hooks/pre-commit so every
# `git commit` from this clone runs the 11-check guard (PHP syntax, MOODLE
# guards, superglobals, credentials, .env, core files, SOPs, SCORM ZIPs,
# version.php format, CONFIRM placeholders, stray git conflict markers).
#
# Usage (from repo root in pwsh / PowerShell 5.1+):
#
#   pwsh -Command "Copy-Item .claude/hooks/pre-commit.sh .git/hooks/pre-commit -Force"
#
# Or just run this script:
#
#   pwsh -File tools/install-hooks.ps1
#
# P0 cleanup A, 2026-05-24 — added after CI runs #397 + #403 caught stray
# conflict markers post-push. The hook's CHECK 11 catches them at commit
# instead. See CLAUDE.md §13 → "Pre-commit guards" for the full rundown.

$ErrorActionPreference = 'Stop'

$repoRoot = (Get-Item -Path '.').FullName
$source   = Join-Path $repoRoot '.claude\hooks\pre-commit.sh'
$target   = Join-Path $repoRoot '.git\hooks\pre-commit'

if (-not (Test-Path $source)) {
    Write-Error "Source hook missing: $source. Run this script from the repo root."
    exit 1
}
if (-not (Test-Path (Join-Path $repoRoot '.git'))) {
    Write-Error "No .git/ directory — not in a git repository."
    exit 1
}

Copy-Item -Path $source -Destination $target -Force
Write-Host "Installed: $target" -ForegroundColor Green
Write-Host "Next commit will run the 11-check guard." -ForegroundColor Cyan
