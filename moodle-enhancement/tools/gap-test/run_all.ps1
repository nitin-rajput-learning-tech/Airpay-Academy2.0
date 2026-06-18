<#
  run_all.ps1 - drive probe_links.ps1 across every persona, sequentially.
  Sequential on purpose: one local Apache, parallel hits cause false timeouts.
  All local persona passwords are the documented local-only value.
#>
$ErrorActionPreference = 'Continue'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $here
$pw = 'AcademyAudit2026!'

# label -> username  ('' = guest, no login)
# Order: siteadmin first (reaches every page = best render check), then the
# access-control personas. Without OPcache each page recompiles (~7-16s), so we
# go curated-only (no crawl) to keep the matrix to a manageable size.
$personas = [ordered]@{
  siteadmin    = 'academy@airpay.co.in'
  learner      = 'fatma.khamis@airpay.tz'
  manager      = 'binay.upadhyay@airpay.co.in'
  author       = 'asif.ansari@airpay.co.in'
  compliance   = 'joseph.mandapati@airpay.co.in'
  tenantadmin  = 'academyexadmin@airpay.co.in'
  publiclearner= 'vimalkoothattu'
}

foreach ($label in $personas.Keys) {
  $u = $personas[$label]
  Write-Output ""
  Write-Output ("######## {0} :: {1} ########" -f $label, $u)
  try {
    & "$here\probe_links.ps1" -Label $label -User $u -Pass $pw -UrlFile "$here\urls.core.txt" -OutDir "$here\reports" -TimeoutSec 60 -NoCrawl
  } catch {
    Write-Output ("PERSONA {0} FAILED (continuing): {1}" -f $label, $_.Exception.Message)
  }
}
Write-Output ""
Write-Output "ALL PERSONAS DONE."
