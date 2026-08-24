# deploy-to-uat.ps1 - mirror of the local XAMPP copy step, aimed at UAT.
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
$remoteDocroot = '/var/www/sentientia/moodle5.2/public'
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
