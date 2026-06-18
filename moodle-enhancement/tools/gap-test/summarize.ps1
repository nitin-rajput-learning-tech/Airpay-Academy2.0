<#
  summarize.ps1 - consolidate per-persona probe CSVs into:
    1. matrix.csv  - curated paths x personas (klass per persona) for cross-persona comparison
    2. findings.csv - every non-OK / non-expected result across all personas+sources (triage worklist)
    3. stdout summary - per-persona counts + the hard-failure + all-persona-broken shortlist
#>
param(
  [string]$ReportsDir = 'reports',
  [string[]]$Order = @('guest','learner','publiclearner','author','manager','compliance','tenantadmin','siteadmin')
)
$ErrorActionPreference = 'Stop'

$personas = @()
$rows = @{}        # path -> @{ persona = record }
$all  = New-Object System.Collections.Generic.List[object]

foreach ($label in $Order) {
  $csv = Join-Path $ReportsDir "$label.csv"
  if (-not (Test-Path $csv)) { continue }
  $personas += $label
  foreach ($r in (Import-Csv $csv)) {
    $all.Add($r)
    if (-not $rows.ContainsKey($r.path)) { $rows[$r.path] = @{} }
    $rows[$r.path][$label] = $r
  }
}

Write-Output ("personas present: {0}" -f ($personas -join ', '))
Write-Output ""

# Per-persona klass breakdown
Write-Output "=== per-persona classification counts ==="
foreach ($label in $personas) {
  $sub = $all | Where-Object { $_.persona -eq $label }
  $parts = ($sub | Group-Object klass | Sort-Object Count -Descending | ForEach-Object { "{0}={1}" -f $_.Name,$_.Count }) -join '  '
  Write-Output ("  {0,-13} n={1,-4} {2}" -f $label, $sub.Count, $parts)
}
Write-Output ""

# Hard failures anywhere
Write-Output "=== HARD FAILURES (PHP-FATAL / HTTP-500 / HTTP-503 / ERROR / THIN) ==="
$hard = $all | Where-Object { $_.klass -in @('PHP-FATAL','HTTP-500','HTTP-503','ERROR','THIN') } |
  Sort-Object path, persona
if ($hard) {
  $hard | Select-Object persona,path,scope,status,klass,note | Format-Table -AutoSize -Wrap | Out-String -Width 200 | Write-Output
} else { Write-Output "  (none)" }
Write-Output ""

# Curated cross-persona matrix
$curatedPaths = $rows.Keys | Where-Object { $rows[$_].Values | Where-Object { $_.source -eq 'curated' } } | Sort-Object
$matrix = foreach ($p in $curatedPaths) {
  $o = [ordered]@{ path = $p; scope = ($rows[$p].Values | Select-Object -First 1).scope }
  foreach ($label in $personas) {
    if ($rows[$p].ContainsKey($label)) { $o[$label] = $rows[$p][$label].klass } else { $o[$label] = '' }
  }
  [pscustomobject]$o
}
$matrix | Export-Csv -NoTypeInformation -Path (Join-Path $ReportsDir 'matrix.csv')

# Findings worklist: anything not OK and not an expected gate result.
# Expected-gate = REDIR-LOGIN (guest) or DENIED/HTTP-403/HTTP-404 on a P-scope page for a non-admin.
$findings = $all | Where-Object { $_.klass -notin @('OK','SKIP-UNSAFE') }
$findings | Select-Object persona,path,scope,source,status,klass,bytes,note |
  Export-Csv -NoTypeInformation -Path (Join-Path $ReportsDir 'findings.csv')

Write-Output ("=== curated paths in matrix: {0}  ->  matrix.csv ===" -f $curatedPaths.Count)
Write-Output ("=== findings (non-OK) rows: {0}  ->  findings.csv ===" -f $findings.Count)
Write-Output ""

# Curated A-scope pages that FAIL for an ordinary authed learner (most likely real breakage)
Write-Output "=== A-scope curated pages NOT OK for learner (candidate real breakage) ==="
$learnerBad = $matrix | Where-Object { $_.scope -eq 'A' -and $_.learner -and $_.learner -ne 'OK' }
if ($learnerBad) {
  $learnerBad | Select-Object path,scope,learner,manager,siteadmin | Format-Table -AutoSize | Out-String -Width 160 | Write-Output
} else { Write-Output "  (none - all A-scope pages OK for learner)" }
Write-Output ""

# Curated P-scope pages reachable (OK) by learner/publiclearner = possible privilege escalation
Write-Output "=== P-scope curated pages OK for learner/public (possible over-exposure) ==="
$esc = $matrix | Where-Object { $_.scope -eq 'P' -and ($_.learner -eq 'OK' -or $_.publiclearner -eq 'OK') }
if ($esc) {
  $esc | Select-Object path,scope,learner,publiclearner,manager | Format-Table -AutoSize | Out-String -Width 160 | Write-Output
} else { Write-Output "  (none)" }
