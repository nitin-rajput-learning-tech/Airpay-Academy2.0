<#
xampp-watchdog.ps1 - restarts XAMPP Apache (:8080) and MariaDB (:3306) if they die.

Installed because httpd.exe/mysqld.exe run console-mode and the dev account has
no admin rights to install real Windows services (httpd -k install fails with
OS 5 / AH00369). See moodle-enhancement/docs/operations/LOCAL-XAMPP-SERVICES.md.

Runs from Task Scheduler task "XAMPP Watchdog (Apache+MariaDB)" every minute,
loops internally ~55s checking every 12s, so detection latency is <= 12s.
Log: watchdog.log next to this script. Silence in the log means healthy.
LOCAL DEV ONLY - never deploy to any server.
#>

$ErrorActionPreference = 'Stop'
$LogFile = Join-Path $PSScriptRoot 'watchdog.log'

function Write-Log([string]$msg) {
    "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg" | Add-Content -Path $LogFile -Encoding UTF8
}

function Test-Port([int]$port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $iar = $client.BeginConnect('127.0.0.1', $port, $null, $null)
        if ($iar.AsyncWaitHandle.WaitOne(3000) -and $client.Connected) { return $true }
        return $false
    } catch { return $false }
    finally { $client.Close() }
}

# Single-instance guard. The task also uses -MultipleInstances IgnoreNew;
# the mutex covers manual runs overlapping scheduled ones.
$mutex = New-Object System.Threading.Mutex($false, 'Local\XamppWatchdog')
if (-not $mutex.WaitOne(0)) { exit 0 }

try {
    if ((Test-Path $LogFile) -and ((Get-Item $LogFile).Length -gt 1MB)) {
        Move-Item -Path $LogFile -Destination "$LogFile.1" -Force
    }

    $apacheAttempted = $false
    $mysqlAttempted  = $false

    for ($i = 0; $i -lt 5; $i++) {
        if ($i -gt 0) { Start-Sleep -Seconds 12 }

        # ---- Apache (console-mode httpd.exe, listens on 8080) ----
        if (-not (Test-Port 8080)) {
            if ($apacheAttempted) {
                Write-Log "Apache still down after restart attempt this run - leaving for next run."
            } else {
                $apacheAttempted = $true
                $lingering = @(Get-Process httpd -ErrorAction SilentlyContinue)
                Write-Log "Apache DOWN (port 8080 dead, $($lingering.Count) httpd process(es) lingering) - restarting."
                # A half-dead parent/child pair holds the listen socket; clear it first.
                $lingering | Stop-Process -Force -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 2
                Start-Process -FilePath 'C:\xampp\apache\bin\httpd.exe' `
                    -WorkingDirectory 'C:\xampp\apache\bin' -WindowStyle Hidden
                $up = $false
                foreach ($w in 1..10) { Start-Sleep -Seconds 3; if (Test-Port 8080) { $up = $true; break } }
                Write-Log "Apache restart attempted - port 8080 is now $(if ($up) { 'UP' } else { 'STILL DOWN after 30s' })."
            }
        }

        # ---- MariaDB (console-mode mysqld.exe, listens on 3306) ----
        if (-not (Test-Port 3306)) {
            $proc = @(Get-Process mysqld -ErrorAction SilentlyContinue)
            if ($proc.Count -gt 0) {
                # Process alive but port dead: hung or mid-recovery. Never force-kill
                # mysqld - forcing InnoDB into crash recovery makes things worse.
                Write-Log "MariaDB port 3306 dead but mysqld PID $($proc[0].Id) alive - NOT killing. Investigate manually."
            } elseif ($mysqlAttempted) {
                Write-Log "MariaDB still down after restart attempt this run - leaving for next run."
            } else {
                $mysqlAttempted = $true
                Write-Log "MariaDB DOWN (no mysqld process) - restarting."
                # Mirrors how XAMPP control panel launches it (verified via Win32_Process).
                Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' `
                    -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini', '--standalone' `
                    -WorkingDirectory 'C:\xampp\mysql\bin' -WindowStyle Hidden
                $up = $false
                foreach ($w in 1..10) { Start-Sleep -Seconds 3; if (Test-Port 3306) { $up = $true; break } }
                Write-Log "MariaDB restart attempted - port 3306 is now $(if ($up) { 'UP' } else { 'STILL DOWN after 30s' })."
            }
        }
    }
} finally {
    $mutex.ReleaseMutex() | Out-Null
    $mutex.Dispose()
}
