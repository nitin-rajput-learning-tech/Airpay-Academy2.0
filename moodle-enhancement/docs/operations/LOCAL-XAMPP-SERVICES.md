# LOCAL-XAMPP-SERVICES — keeping local Apache/MariaDB alive

**Scope: local dev box only** (`C:\xampp` on Nitin's Windows 11 machine).
Nothing here touches production or the ninja target.
Created: 2026-08-05 | Owner: Nitin Rajput

---

## 1. Problem

Console-mode `C:\xampp\apache\bin\httpd.exe` (serving Moodle at
`http://localhost:8080`) **died silently at least 3 times** across the
2026-08-04/05 sessions — no crash entries in `C:\xampp\apache\logs\error.log`,
`opcache.enable=0` so the June OPcache crash class (see memory note
`project_opcache_win_instability`) is ruled out. Each death broke browser
verification mid-session and needed a manual restart.

MariaDB has the **same exposure**: `mysqld.exe --defaults-file=c:\xampp\mysql\bin\my.ini --standalone`
runs console-mode with no service and no recovery (verified 2026-08-05).

## 2. Why the "proper" fix is blocked

The intended fix was a real Windows service with SCM failure-recovery.
**It cannot be done from this account:**

```
> C:\xampp\apache\bin\httpd.exe -k install -n "Apache2.4"
(OS 5) Access is denied. AH00369: Failed to open the Windows service manager
```

`airpaycom\nitin.rajput` is not in the local Administrators group
(`whoami /groups` → `BUILTIN\Users` only, Medium integrity). Windows 11's
built-in `sudo.exe` exists but elevation still requires admin membership.
Service install therefore needs IT — see §5.

## 3. What is deployed instead — user-level watchdog

A Task Scheduler task running as the current user (no admin needed) that
detects dead services within ~12 s and restarts them.

| Item | Value |
|------|-------|
| Task name | `XAMPP Watchdog (Apache+MariaDB)` |
| Runs as | `AIRPAYCOM\nitin.rajput`, interactive ("only when logged on" — correct for a dev box) |
| Triggers | At logon + repeating every 1 minute (10-year repetition window) |
| Action | `wscript.exe //B //NoLogo run-hidden.vbs` → runs the PS1 with zero console flash |
| Runtime dir | `D:\Claude Local\xampp-watchdog\` (stable path, outside any git worktree) |
| Canonical source | `moodle-enhancement/tools/xampp-watchdog/` (this repo) |
| Log | `D:\Claude Local\xampp-watchdog\watchdog.log` — **silence means healthy**; every restart is logged with timestamp, rotates at 1 MB |

### Behaviour

- Each run holds a named mutex (single instance) and loops ~55 s checking
  every 12 s, so worst-case detection is ~12 s even with a 1-minute cadence.
- **Apache**: if port 8080 is dead → force-kill lingering `httpd.exe`
  (a half-dead parent/child pair holds the listen socket), relaunch hidden,
  poll up to 30 s, log outcome. One attempt per run (no kill-loop flapping).
- **MariaDB**: if port 3306 is dead **and no `mysqld` process exists** →
  relaunch with the exact XAMPP arguments. If the process is alive but the
  port is dead (hung / mid-recovery), it logs loudly and **never force-kills
  mysqld** — forcing InnoDB into crash recovery makes things worse.

### Verified 2026-08-05

- Baseline: `http://localhost:8080/` and `/moodle/` → **200**.
- Simulated crash: `Stop-Process -Force` on both httpd PIDs → port confirmed
  dead → watchdog restarted Apache → port back **in 12 s**, HTTP **200**.
- Log recorded both the death and the recovery (`watchdog.log`).
- MariaDB restart path uses the same verified code path; not force-kill
  tested by design (see InnoDB note above).

## 4. Operations

```powershell
# Status of the task
Get-ScheduledTask -TaskName 'XAMPP Watchdog (Apache+MariaDB)' | Format-List TaskName, State

# See what the watchdog has done (silence = healthy)
Get-Content 'D:\Claude Local\xampp-watchdog\watchdog.log' -Tail 20

# Force an immediate check (instead of waiting up to a minute)
Start-ScheduledTask -TaskName 'XAMPP Watchdog (Apache+MariaDB)'

# Pause it (e.g. when intentionally stopping XAMPP for an upgrade)
Disable-ScheduledTask -TaskName 'XAMPP Watchdog (Apache+MariaDB)'
Enable-ScheduledTask  -TaskName 'XAMPP Watchdog (Apache+MariaDB)'

# Uninstall completely
Unregister-ScheduledTask -TaskName 'XAMPP Watchdog (Apache+MariaDB)' -Confirm:$false

# Reinstall / repair (re-run safe; copies live in the repo under
# moodle-enhancement/tools/xampp-watchdog/ if the runtime dir is lost)
powershell -NoProfile -ExecutionPolicy Bypass -File 'D:\Claude Local\xampp-watchdog\install-watchdog-task.ps1'
```

**Gotcha:** when you *intentionally* stop Apache/MariaDB, the watchdog will
restart them within a minute. Disable the task first (command above).

## 5. The proper fix — for IT (requires local admin)

When an admin is available, replace the watchdog with real services.
Run **elevated**, with XAMPP stopped first (`Get-Process httpd, mysqld | Stop-Process -Force`
for Apache; clean-shutdown MariaDB with `C:\xampp\mysql\bin\mysqladmin.exe -u root shutdown`):

```powershell
# Apache as a service with auto-restart on failure
C:\xampp\apache\bin\httpd.exe -k install -n "Apache2.4"
sc.exe config Apache2.4 start= auto
sc.exe failure Apache2.4 reset= 86400 actions= restart/5000/restart/5000/restart/5000
sc.exe start Apache2.4

# MariaDB as a service with the same recovery policy
C:\xampp\mysql\bin\mysqld.exe --install mysql --defaults-file=C:\xampp\mysql\bin\my.ini
sc.exe config mysql start= auto
sc.exe failure mysql reset= 86400 actions= restart/5000/restart/5000/restart/5000
sc.exe start mysql
```

Notes for that day:
- Services default to `LocalSystem`, which can read `C:\xampp` — no ACL work
  needed unless IT mandates a service account (then grant it Read+Execute on
  `C:\xampp` and Modify on `apache\logs`, `mysql\data`, `htdocs\moodle5\moodledata`
  equivalents).
- Verify: reboot-free test is `taskkill /f /im httpd.exe` → SCM restarts
  within 5 s per the failure policy → `http://localhost:8080/` returns 200.
- Then **uninstall the watchdog task** (§4) so the two mechanisms don't fight.
- XAMPP Control Panel's "Svc" checkboxes do the same install but without the
  `sc failure` recovery policy — set it explicitly either way.

## 6. Root cause still open

The watchdog restores availability but does not explain *why* httpd dies
silently. `watchdog.log` now gives exact death timestamps — correlate those
with Windows Event Viewer (Application log, source `Application Error` /
`Windows Error Reporting`) and `C:\xampp\apache\logs\` on the next occurrence.
Tracked as an open investigation item in PROJECT-STATE.md.
