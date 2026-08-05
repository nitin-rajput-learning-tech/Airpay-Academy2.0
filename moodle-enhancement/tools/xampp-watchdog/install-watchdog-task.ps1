# install-watchdog-task.ps1 - registers the "XAMPP Watchdog (Apache+MariaDB)"
# scheduled task for the CURRENT user. No admin needed: the task is interactive
# ("run only when user is logged on"), which is exactly right for a dev box -
# Apache only matters while Nitin is working. Re-run safe (replaces the task).
param(
    [string]$WatchdogDir = 'D:\Claude Local\xampp-watchdog'
)
$ErrorActionPreference = 'Stop'
$taskName = 'XAMPP Watchdog (Apache+MariaDB)'
$vbs = Join-Path $WatchdogDir 'run-hidden.vbs'
if (-not (Test-Path $vbs)) { throw "Missing $vbs - copy the watchdog files there first." }

$action = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "//B //NoLogo `"$vbs`""
$logon  = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
$repeat = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
             -RepetitionInterval (New-TimeSpan -Minutes 1) `
             -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
             -StartWhenAvailable -MultipleInstances IgnoreNew `
             -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

try { Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction Stop } catch {}
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $logon, $repeat `
    -Settings $settings `
    -Description 'Restarts console-mode XAMPP Apache (:8080) and MariaDB (:3306) if they die. Local dev only. Runbook: moodle-enhancement/docs/operations/LOCAL-XAMPP-SERVICES.md' | Out-Null
Write-Host "Registered task '$taskName' (logon trigger + every 1 minute)."
