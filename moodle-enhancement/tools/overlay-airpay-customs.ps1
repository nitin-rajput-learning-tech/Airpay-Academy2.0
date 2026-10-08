# overlay-airpay-customs.ps1
#
# Lay down Airpay customizations on top of a fresh Moodle (5.2 or 5.3) tree.
#
# Two source modes:
#
#   REPO MODE (preferred, Moodle 5.3 packaging, ADR-033): -RepoRoot <git export>
#     Every component is read from a git export of the repository, so what ships is what is
#     committed. tools/packaging/build-standalone.sh does the export and calls this script.
#       theme/sentientia, payment/gateway/airpay, admin/tool/certificate, enrol/sentientiasub,
#       mod/quiz/accessrule/sentientia_proctoring, my/dashboard.php, my/switchrole.php,
#       vendor blocks                         <- repo top level
#       local/*, blocks/sentientia_*          <- moodle-enhancement/ (the tree UAT runs)
#       public/.htaccess                      <- generated from moodle-enhancement/deploy/moodle-htaccess.template
#
#   LEGACY WEBROOT MODE (no -RepoRoot): copies from the local 5.1 dev webroot ($Source). Kept only so
#     existing runbooks that pass -Source/-Target keep working. It cannot see what is committed (a fix
#     in git does not necessarily reach the package), so do not build a package with it.
#
# Pre-requisite (both modes): a vanilla Moodle tree of the right version at $Target (the public/ dir).
#
# What this script does:
#   - Copy our theme/sentientia into the tree
#   - Repair stale theme_airpayux -> theme_sentientia AMD module names baked into
#     the copied theme/sentientia/amd/build bundles (idempotent; durable fix for
#     the F-LOAD-02 / ADR-025 follow-up (c) theme-side stale-bundle gap)
#   - Copy all local/sentientia_* plugins
#   - Copy our sentientia_* blocks (+ the vendor learnerscript/reportdashboard/reporttiles blocks only
#     with -WithLearnerscript: their report modals still depend on the removed core modal factory, FX-08)
#   - Copy admin/tool/certificate (vendor plugin we ship, with the SENTIENTIA-CORE-MOD vendor patches)
#   - Copy payment/gateway/airpay, enrol/sentientiasub, mod/quiz/accessrule/sentientia_proctoring
#   - Copy my/dashboard.php + my/switchrole.php (pure additions on 5.3) and the router .htaccess
#   - DOES NOT copy config.php (that's per-instance)
#   - NEVER ships airpay-audit-loginas.php (a localhost-only auto-login-as-site-admin helper that sat in
#     the dev webroot; the old overlay copied it) and no longer ships my/templates/dropdown.mustache
#     (nothing references it)
#   - LOGS every collision (file already exists at target with different content)
#
# ASCII-only - PowerShell 5.1 friendly.

[CmdletBinding()]
param(
    [string]$Source = 'C:\xampp\htdocs\moodle5\public',
    [string]$Target = 'C:\xampp\htdocs\moodle5.2\public',
    [string]$LogPath = 'D:\Claude Local\moodle-5.2-diffs\overlay-log.txt',
    # Git export root (repo layout). When set the overlay runs in REPO MODE and ignores -Source.
    [string]$RepoRoot = '',
    # Path prefix of Moodle's error/index.php in the generated .htaccess. '' for a docroot vhost
    # (ErrorDocument /error/index.php); '/moodle' for the dev alias. Repo mode only.
    [string]$ErrorBase = '',
    # Ship the vendor report blocks. Off by default until their modal code is ported (FX-08).
    [switch]$WithLearnerscript
)

$ErrorActionPreference = 'Continue'
$RepoMode = -not [string]::IsNullOrEmpty($RepoRoot)
if ($RepoMode -and -not (Test-Path (Join-Path $RepoRoot 'moodle-enhancement'))) {
    throw "RepoRoot '$RepoRoot' does not look like a repo export (no moodle-enhancement dir)."
}
"=== Overlay log $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ===" | Out-File $LogPath

function Log {
    param([string]$Msg)
    Write-Host $Msg
    $Msg | Out-File -Append -FilePath $LogPath
}

function Resolve-Source {
    # RelPath is relative to public/ in the TARGET layout. In legacy mode it maps 1:1 onto the dev
    # webroot. In repo mode each component lives in a different place of the repository.
    param([string]$RelPath)
    if (-not $RepoMode) { return (Join-Path $Source $RelPath) }
    if ($RelPath -eq 'local' -or $RelPath -like 'local\*') {
        return (Join-Path $RepoRoot ('moodle-enhancement\' + $RelPath))
    }
    if ($RelPath -eq 'blocks') { return (Join-Path $RepoRoot 'moodle-enhancement\blocks') }
    if ($RelPath -like 'blocks\sentientia_*' -or $RelPath -like 'blocks\airpay_*' -or $RelPath -like 'blocks\*_PATCHED.php') {
        return (Join-Path $RepoRoot ('moodle-enhancement\' + $RelPath))
    }
    # theme, vendor blocks, admin/tool/certificate, payment, enrol, mod/quiz/accessrule, my
    return (Join-Path $RepoRoot $RelPath)
}

function Copy-Tree {
    param(
        [string]$Label,
        [string]$RelPath
    )
    $s = Resolve-Source $RelPath
    $t = Join-Path $Target $RelPath
    if (-not (Test-Path $s)) {
        Log "[$Label] SKIP (source missing): $RelPath"
        return
    }
    $existed = Test-Path $t
    # Repo mode MIRRORS the plugin directory (/MIR = /E + purge): the repository owns it, so a file that was
    # deleted in git must not survive from a staging base that an older overlay already touched (the 5.2
    # staging tree still carried the deleted paygw_airpay form_submit module). Legacy webroot mode merges.
    $copyMode = '/E'
    $verb = 'merging'
    if ($RepoMode) {
        $copyMode = '/MIR'
        $verb = 'mirroring'
    }
    if ($existed) {
        Log "[$Label] COLLISION (target exists): $RelPath - $verb..."
    } else {
        Log "[$Label] COPY: $RelPath"
    }
    # Use robocopy for efficient recursive copy (never dev junk: node_modules, .git, _stale-*, *.log)
    $start = Get-Date
    robocopy $s $t $copyMode /MT:8 /XD node_modules .git '_stale-*' /XF '*.log' /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    $elapsed = (Get-Date) - $start
    $count = (Get-ChildItem $t -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    Log "[$Label]   -> ${count} files total at target after copy ($([Math]::Round($elapsed.TotalSeconds,1))s)"
}

function Copy-File {
    param(
        [string]$Label,
        [string]$RelPath
    )
    $s = Resolve-Source $RelPath
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
if ($RepoMode) {
    # Repo mode: the legacy airpay_* names were retired by ADR-022/025 (ME/local still carries the
    # airpay_ratings duplicate, which is never served), so only sentientia_* ships.
    Log "=== (repo mode) local/airpay_* skipped: retired names ==="
} else {
    Log "=== local/airpay_* (legacy webroot mode) ==="
    $localPlugins = Get-ChildItem (Resolve-Source 'local') -Directory -Filter 'airpay_*'
    foreach ($p in $localPlugins) {
        Copy-Tree 'local' "local\$($p.Name)"
    }
}

Log ""
Log "=== local/sentientia_* ==="
$sentientia = Get-ChildItem (Resolve-Source 'local') -Directory -Filter 'sentientia_*' -ErrorAction SilentlyContinue
foreach ($p in $sentientia) {
    Copy-Tree 'local' "local\$($p.Name)"
}

Log ""
Log "=== blocks/sentientia_* (+ any legacy airpay_* in legacy webroot mode) ==="
$blocks = Get-ChildItem (Resolve-Source 'blocks') -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -like 'sentientia_*' -or (-not $RepoMode -and $_.Name -like 'airpay_*') }
foreach ($b in $blocks) {
    Copy-Tree 'block' "blocks\$($b.Name)"
}

Log ""
if ($RepoMode -and -not $WithLearnerscript) {
    # FX-08 (open owner decision): these three vendor blocks still depend on the core modal factory AMD
    # module that Moodle 5.2 removed, so their report modals fail. Shipping them is opt-in until their
    # modules are ported to core/modal, or until the Sentientia reports replace them.
    Log "=== blocks/learnerscript + reportdashboard + reporttiles NOT shipped (pass -WithLearnerscript to include) ==="
} else {
    Log "=== blocks/learnerscript + reportdashboard + reporttiles (vendor blocks we patch) ==="
    Copy-Tree 'block' 'blocks\learnerscript'
    Copy-Tree 'block' 'blocks\reportdashboard'
    Copy-Tree 'block' 'blocks\reporttiles'

    Log ""
    Log "=== Patched files at blocks/ root ==="
    Copy-File 'block-patch' 'blocks\learnerscript_lib_PATCHED.php'
    Copy-File 'block-patch' 'blocks\reportdashboard_dashboard_PATCHED.php'
}

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
Log "=== enrol/sentientiasub (paid-enrolment method) ==="
# Added 2026-10-08 (ADR-033 packaging recipe): the legacy overlay never carried this plugin; the
# 5.2 staging tree had it from a hand copy. A from-git package must ship it explicitly.
Copy-Tree 'enrol' 'enrol\sentientiasub'

Log ""
Log "=== Root utility: airpay-audit-loginas.php is NEVER shipped ==="
# The old overlay copied this file from the dev webroot. It logs in as the site admin from loopback
# without credentials (a dev audit helper). Behind a reverse proxy REMOTE_ADDR can be loopback, so it
# must never reach a package. build-standalone.sh also FAILS the build if it finds one.

Log ""
Log "=== Core-adjacent BizLMS files (WF-010, 2026-06-11) ==="
# Found by the foolproof campaign: the original overlay covered plugins +
# theme but NOT BizLMS files that live inside CORE directories. On the 5.2
# tree these were silently missing — php -S masked it (its path-fallback
# serves /my/index.php for ANY missing file under /my/), but on production
# Apache every BizLMS link to /my/dashboard.php would hard-404 and role
# switching (/my/switchrole.php) would be dead.
# Moodle 5.3 ships NEITHER file (vanilla public/my/ has no dashboard.php or switchrole.php), so on
# 5.3 these are pure additions, not overrides; on 5.2 they overwrite core's copies. Required either way.
Copy-File 'core-adjacent' 'my\dashboard.php'      # redirect shim — BizLMS nav links use /my/dashboard.php
Copy-File 'core-adjacent' 'my\switchrole.php'     # BizLMS role-switch handler (sidebar switcher endpoint)
# my\templates\dropdown.mustache is no longer shipped (2026-10-08): nothing references it.
if ($RepoMode) {
    # Generate the router .htaccess from the repo template (branded error pages, ServerSignature Off,
    # hardening headers, Moodle 5 router rewrite to r.php). The template's ErrorDocument lines name the
    # dev alias (/moodle/error/index.php); a docroot vhost needs /error/index.php, so the base is a parameter.
    $tpl = Join-Path $RepoRoot 'moodle-enhancement\deploy\moodle-htaccess.template'
    $htTarget = Join-Path $Target '.htaccess'
    if (-not (Test-Path $tpl)) {
        Log "[htaccess] MISSING template: $tpl"
        throw "moodle-htaccess.template not found in the repo export."
    }
    $ht = [System.IO.File]::ReadAllText($tpl)
    $ht = $ht.Replace('@@ERROR_BASE@@', $ErrorBase)
    $ht = $ht.Replace('/moodle/error/index.php', ($ErrorBase + '/error/index.php'))
    $ht = $ht -replace "`r`n", "`n"
    [System.IO.File]::WriteAllText($htTarget, $ht, (New-Object System.Text.UTF8Encoding($false)))
    Log "[htaccess] GENERATED: $htTarget (ErrorDocument base '$ErrorBase')"
} else {
    Copy-File 'core-adjacent' '.htaccess'         # branded error pages + ServerSignature Off (Bug #7);
                                                  # repo source: moodle-enhancement/deploy/moodle-htaccess.template
}

Log ""
Log "=== Summary ==="
$dstCount = (Get-ChildItem $Target -Recurse -File).Count
$dstSize = [Math]::Round((Get-ChildItem $Target -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB, 2)
Log "Target tree: $dstCount files, $dstSize MB"
Log "Overlay log saved to: $LogPath"
