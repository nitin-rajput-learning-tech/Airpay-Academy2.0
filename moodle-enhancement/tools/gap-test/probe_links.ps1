<#
  probe_links.ps1 - authenticated, read-only link stress-test for the Sentientia LMS.

  For one persona: logs in (or stays guest), probes a curated URL list, then
  crawls the rendered dashboard/catalog/nav for real same-origin links and probes
  those too. Classifies every response and writes <label>.csv + <label>.json.

  SAFETY: never follows destructive links (logout/delete/unenrol/remove/purge/
  reset/confirm/action=). READ-ONLY probe - no state-changing GETs.
#>
param(
  [string]$Base       = 'http://localhost:8080',
  [string]$User       = '',
  [string]$Pass       = 'AcademyAudit2026!',
  [string]$Label      = 'guest',
  [string]$UrlFile    = 'urls.core.txt',
  [string]$OutDir     = 'reports',
  [int]   $TimeoutSec = 30,
  [int]   $CrawlCap   = 35,
  [int]   $DelayMs    = 700,
  [int]   $BreakAfter = 4,
  [string]$OnlyPath   = '',
  [switch]$NoCrawl
)

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# Destructive / session-ending / blocking patterns we must NEVER request.
# Includes SSE stream endpoints (stream.php) which hold the connection open until timeout.
$DENY = '(?i)(logout|/delete|delete\.php|action=del|=delete|unenrol|/remove|remove\.php|purge|reset|confirm=|/admin/cli|backup|restore|duplicate|stream\.php|/sse|regenerate|nudge\.php|callback\.php|sw\.php)'

function New-Rec($path, $scope, $source) {
  return [pscustomobject]@{
    persona = $Label; path = $path; scope = $scope; source = $source
    status  = 0; klass = ''; bytes = 0; final = ''; note = ''
  }
}

function Classify-Body($rec, $content) {
  $c = [string]$content
  $rec.bytes = $c.Length
  if ($rec.final -match 'login/index\.php') {
    $rec.klass = 'REDIR-LOGIN'
  } elseif ($c -match 'Debug info:|Stack trace:|Exception thrown|Fatal error|coding error detected|Whoops, looks like') {
    $rec.klass = 'PHP-FATAL'
    $m = [regex]::Match($c, '(?s)(Debug info:.{0,150}|Exception.{0,120}|Fatal error.{0,120})')
    $rec.note  = ($m.Value -replace '\s+', ' ')
  } elseif ($c -match 'nopermissions|notcapable|do not have permission|do not currently have permission|/error/index\.php') {
    $rec.klass = 'DENIED'
  } elseif ($rec.bytes -lt 600) {
    $rec.klass = 'THIN'
  } else {
    $rec.klass = 'OK'
  }
  return $rec
}

function Invoke-Probe($session, $path, $scope, $source) {
  $rec = New-Rec $path $scope $source
  if ($path -match '^https?://') { $url = $path } else { $url = "$Base$path" }
  if ($url -match $DENY) { $rec.klass = 'SKIP-UNSAFE'; return $rec }
  try {
    $params = @{ Uri = $url; UseBasicParsing = $true; TimeoutSec = $TimeoutSec; MaximumRedirection = 5; ErrorAction = 'Stop' }
    if ($session) { $params['WebSession'] = $session }
    $r = Invoke-WebRequest @params
    $rec.status = [int]$r.StatusCode
    try { $rec.final = $r.BaseResponse.ResponseUri.AbsoluteUri } catch { $rec.final = $url }
    $rec = Classify-Body $rec $r.Content
  } catch {
    $resp = $null
    try { $resp = $_.Exception.Response } catch {}
    if ($resp) {
      try { $rec.status = [int]$resp.StatusCode } catch {}
      try { $rec.final  = $resp.ResponseUri.AbsoluteUri } catch {}
      if     ($rec.status -eq 403) { $rec.klass = 'HTTP-403' }
      elseif ($rec.status -eq 404) { $rec.klass = 'HTTP-404' }
      elseif ($rec.status -eq 500) { $rec.klass = 'HTTP-500' }
      elseif ($rec.status -eq 503) { $rec.klass = 'HTTP-503' }
      elseif ($rec.status -eq 0)   { $rec.klass = 'ERROR'; $rec.note = ($_.Exception.Message -replace '\s+', ' ') }
      else                         { $rec.klass = "HTTP-$($rec.status)" }
    } else {
      $rec.klass = 'ERROR'; $rec.note = ($_.Exception.Message -replace '\s+', ' ')
    }
  }
  return $rec
}

function Connect-Persona($user, $pass) {
  if ([string]::IsNullOrWhiteSpace($user)) {
    return @{ session = $null; ok = $true; guest = $true; html = '' }
  }
  $g = Invoke-WebRequest "$Base/login/index.php" -UseBasicParsing -SessionVariable s -TimeoutSec $TimeoutSec
  $tok = [regex]::Match($g.Content, 'name="logintoken"\s+value="([^"]+)"').Groups[1].Value
  $body = @{ username = $user; password = $pass }
  if ($tok) { $body['logintoken'] = $tok }
  try {
    Invoke-WebRequest "$Base/login/index.php" -UseBasicParsing -WebSession $s -Method POST -Body $body -TimeoutSec $TimeoutSec -MaximumRedirection 0 -ErrorAction Stop | Out-Null
  } catch {}
  $d = Invoke-WebRequest "$Base/my/" -UseBasicParsing -WebSession $s -TimeoutSec ($TimeoutSec * 2)
  $authed = ($d.Content -match 'login/logout\.php')
  return @{ session = $s; ok = $authed; guest = $false; html = $d.Content }
}

function Get-Links($html) {
  $set = New-Object System.Collections.Generic.HashSet[string]
  if (-not $html) { return $set }
  foreach ($m in [regex]::Matches($html, 'href\s*=\s*"([^"#]+)"')) {
    $h = $m.Groups[1].Value
    if ($h -match '^(mailto:|tel:|javascript:|data:)') { continue }
    if ($h -match '^https?://' -and $h -notmatch [regex]::Escape($Base)) { continue }
    if ($h -match '^https?://') { $h = $h -replace [regex]::Escape($Base), '' }
    if ($h -notmatch '^/') { continue }
    if ($h -match $DENY) { continue }
    if ($h -match '\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|map)(\?|$)') { continue }
    [void]$set.Add($h)
  }
  return $set
}

# ---- run ----
if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir -Force | Out-Null }
$conn = Connect-Persona $User $Pass

if ((-not $conn.guest) -and (-not $conn.ok)) {
  Write-Output "LOGIN-FAILED  $Label ($User)"
  $rec = New-Rec '(login)' '-' 'login'
  $rec.klass = 'LOGIN-FAILED'; $rec.note = "could not authenticate $User"
  @($rec) | Export-Csv -NoTypeInformation -Path (Join-Path $OutDir "$Label.csv")
  ConvertTo-Json @($rec) -Depth 4 | Set-Content -Encoding utf8 (Join-Path $OutDir "$Label.json")
  return
}

$results = New-Object System.Collections.Generic.List[object]
$probed  = New-Object System.Collections.Generic.HashSet[string]
$script:consec = 0
$tripped = $false

# Circuit-breaker: count consecutive server failures; trip after $BreakAfter so a
# crashing/wedged page can never pile up requests and take Apache down again.
function Test-Break($rec) {
  if ($rec.klass -eq 'ERROR' -or $rec.klass -eq 'HTTP-503' -or $rec.klass -eq 'HTTP-500') {
    $script:consec++
  } else { $script:consec = 0 }
  return ($script:consec -ge $BreakAfter)
}

# Single-URL isolation mode (pinpoint a crasher safely).
if ($OnlyPath -ne '') {
  $rec = Invoke-Probe $conn.session $OnlyPath 'A' 'isolation'
  $results.Add($rec)
  $results | Export-Csv -NoTypeInformation -Path (Join-Path $OutDir "$Label.iso.csv")
  Write-Output ("ISOLATION {0}  {1}  -> status {2}  klass {3}  bytes {4}  {5}" -f $Label,$OnlyPath,$rec.status,$rec.klass,$rec.bytes,$rec.note)
  return
}

# 1) curated list (with delay + breaker)
foreach ($line in Get-Content $UrlFile) {
  $t = $line.Trim()
  if ($t -eq '' -or $t.StartsWith('#')) { continue }
  $parts = $t -split "`t"
  $path  = $parts[0].Trim()
  if ($parts.Count -gt 1) { $scope = $parts[1].Trim() } else { $scope = 'A' }
  if (-not $probed.Add($path)) { continue }
  $rec = Invoke-Probe $conn.session $path $scope 'curated'
  $results.Add($rec)
  if (Test-Break $rec) {
    $b = New-Rec '(circuit-break)' '-' 'breaker'
    $b.klass = 'CIRCUIT-BREAK'; $b.note = "aborted after $BreakAfter consecutive failures (last: $path)"
    $results.Add($b); $tripped = $true; break
  }
  Start-Sleep -Milliseconds $DelayMs
}

if (-not $NoCrawl -and -not $tripped) {
  $seeds = @($conn.html)
  foreach ($hub in @('/course/index.php', '/local/sentientia_catalog/index.php', '/local/sentientia_courses/index.php')) {
    try {
      $h = Invoke-WebRequest "$Base$hub" -UseBasicParsing -WebSession $conn.session -TimeoutSec $TimeoutSec
      $seeds += $h.Content
    } catch {}
    Start-Sleep -Milliseconds $DelayMs
  }
  $crawl = New-Object System.Collections.Generic.HashSet[string]
  foreach ($html in $seeds) { foreach ($l in (Get-Links $html)) { [void]$crawl.Add($l) } }
  $n = 0
  foreach ($l in $crawl) {
    if ($n -ge $CrawlCap) { break }
    if (-not $probed.Add($l)) { continue }
    $rec = Invoke-Probe $conn.session $l 'crawl' 'crawl'
    $results.Add($rec); $n++
    if (Test-Break $rec) {
      $b = New-Rec '(circuit-break)' '-' 'breaker'
      $b.klass = 'CIRCUIT-BREAK'; $b.note = "aborted crawl after $BreakAfter consecutive failures (last: $l)"
      $results.Add($b); break
    }
    Start-Sleep -Milliseconds $DelayMs
  }
}

# Tolerate transient file locks (AV/indexer) - the CSV is the source of truth for
# summarize.ps1, so never let a .json write lock abort the persona's collected data.
try { $results | Export-Csv -NoTypeInformation -Force -Path (Join-Path $OutDir "$Label.csv") }
catch { Write-Output ("WARN: csv write failed - " + $_.Exception.Message) }
try { ConvertTo-Json $results -Depth 4 | Set-Content -Encoding utf8 -Force (Join-Path $OutDir "$Label.json") }
catch { Write-Output ("WARN: json write failed (non-fatal) - " + $_.Exception.Message) }

if ($conn.guest) { $who = 'guest' } else { $who = $User }
Write-Output ("== {0} ({1}) - {2} urls probed ==" -f $Label, $who, $results.Count)
foreach ($grp in ($results | Group-Object klass | Sort-Object Count -Descending)) {
  Write-Output ("  {0,-12} {1}" -f $grp.Name, $grp.Count)
}
$bad = $results | Where-Object { $_.klass -in @('PHP-FATAL', 'HTTP-500', 'HTTP-503', 'ERROR') }
if ($bad) {
  Write-Output "  !! HARD FAILURES:"
  foreach ($b in $bad) { Write-Output ("     {0}  [{1}]  {2}" -f $b.path, $b.klass, $b.note) }
}
