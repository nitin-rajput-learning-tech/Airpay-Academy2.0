# deploy-to-uat.ps1 - mirror of the local XAMPP copy step, aimed at UAT.
#
# ⚠ SUPERSEDED (2026-09-07) by tools/uat/deploy_to_uat.sh — use that instead.
#   This script scp's directly as the SSH user into the www-data-owned docroot with
#   no sudo (fails: permission denied), no backup, no checksum verify, and no
#   admin/cli/upgrade.php step (so version.php bumps never register). Its docroot
#   constant was also stale. The .sh deployer does stage→tar→scp→backup→sudo
#   extract→chown www-data→sha256 verify→upgrade→purge, dry-run by default.
#   Kept only as a reference; the docroot below is corrected to the real path.
#
# Prereq: the tunnel session is up in another window (`ssh uat-tunnel`).
#
# Usage:
#   pwsh -File tools/uat/deploy-to-uat.ps1 moodle-enhancement/theme/sentientia/scss/moodle/custom_changes.scss
#   pwsh -File tools/uat/deploy-to-uat.ps1 moodle-enhancement/local/sentientia_ai            # whole plugin dir
#   pwsh -File tools/uat/deploy-to-uat.ps1 <path> -Purge                                     # + purge caches after
#
# Maps repo paths -> UAT docroot the same way the XAMPP deploy does:
#   moodle-enhancement/theme/<x>  -> /var/www/sentientia/moodle5.2/public/theme/<x>
#   moodle-enhancement/local/<x>  -> /var/www/sentientia/moodle5.2/public/local/<x>
#   moodle-enhancement/blocks/<x> -> /var/www/sentientia/moodle5.2/public/blocks/<x>
param(
    [Parameter(Mandatory = $true)][string]$RepoPath,
    [switch]$Purge
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$remoteDocroot = '/var/www/html/moodle5.2/public'   # corrected 2026-09-07 (was /var/www/sentientia/…)
$sshHost = 'uat-lms'   # localhost:2222 via the tunnel (see ~/.ssh/config)

$full = Join-Path $repoRoot $RepoPath
if (-not (Test-Path $full)) { throw "Not found in repo: $full" }

# Repo-relative -> docroot-relative (strip the moodle-enhancement/ prefix)
$rel = ($RepoPath -replace '\\', '/') -replace '^moodle-enhancement/', ''
if ($rel -notmatch '^(theme|local|blocks|mod|admin|lib)/') {
    throw "Refusing: '$rel' does not map under the docroot (theme/local/blocks/mod). Deploy explicit paths only."
}
$remote = "$remoteDocroot/$rel"

Write-Host "deploy: $RepoPath  ->  ${sshHost}:$remote"
if ((Get-Item $full).PSIsContainer) {
    $remoteParent = ($remote -replace '/[^/]+$', '')
    ssh $sshHost "mkdir -p '$remoteParent'"
    scp -P 2222 -r $full "nitin.rajput@localhost:$remoteParent/"
} else {
    $remoteDir = ($remote -replace '/[^/]+$', '')
    ssh $sshHost "mkdir -p '$remoteDir'"
    scp -P 2222 $full "nitin.rajput@localhost:$remote"
}
if ($LASTEXITCODE -ne 0) { throw "scp failed (is the tunnel up? run: ssh uat-tunnel)" }

if ($Purge) {
    ssh $sshHost "php $remoteDocroot/admin/cli/purge_caches.php"
}
Write-Host "done."
