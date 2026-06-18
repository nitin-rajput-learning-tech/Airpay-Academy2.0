<# Re-probe the 3 personas that hit LOGIN-FAILED, after credential repair. #>
$ErrorActionPreference = 'Continue'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$pw = 'AcademyAudit2026!'
$personas = [ordered]@{
  author      = 'asif.ansari@airpay.co.in'
  compliance  = 'joseph.mandapati@airpay.co.in'
  tenantadmin = 'academyexadmin@airpay.co.in'
}
foreach ($label in $personas.Keys) {
  Write-Output ("######## {0} :: {1} ########" -f $label, $personas[$label])
  try { & "$here\probe_links.ps1" -Label $label -User $personas[$label] -Pass $pw -UrlFile "$here\urls.core.txt" -OutDir "$here\reports" -TimeoutSec 60 -NoCrawl }
  catch { Write-Output ("PERSONA {0} FAILED: {1}" -f $label, $_.Exception.Message) }
}
Write-Output "3-PERSONA REPROBE DONE."
