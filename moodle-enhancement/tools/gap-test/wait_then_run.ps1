<#
  wait_then_run.ps1 - poll for Apache health, then run the full gentle persona
  matrix. Lets the user restart Apache (XAMPP) whenever; this fires automatically
  once /login serves a real page again. Aborts if Apache stays down ~8 min.
#>
$ProgressPreference = 'SilentlyContinue'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$ok = $false
for ($i = 1; $i -le 80; $i++) {
  try {
    $r = Invoke-WebRequest 'http://localhost:8080/login/index.php' -UseBasicParsing -TimeoutSec 6
    if ([int]$r.StatusCode -eq 200 -and $r.Content.Length -gt 5000) {
      Write-Output ("APACHE HEALTHY after {0} polls" -f $i); $ok = $true; break
    }
  } catch {}
  Start-Sleep -Seconds 6
}
if (-not $ok) { Write-Output "APACHE STILL DOWN after ~8 min polling - aborting probe."; exit 1 }
Start-Sleep -Seconds 3
Write-Output "Starting gentle persona matrix..."
& "$here\run_all.ps1"
