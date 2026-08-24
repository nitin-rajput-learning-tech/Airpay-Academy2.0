# UAT remote-dev workflow — code on Claude locally, test on the UAT server

**Goal:** reproduce the local loop (Claude edits in `D:\Claude Local\airpay-ld-os`
→ copy to XAMPP webroot → purge caches → test at `localhost:8080`) against
`UAT-Sentientia-LMS`, with the same immediacy.
**Owner:** Nitin · **Written:** 2026-08-24 · **Companion:** `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md`

---

## 1. The connectivity model

```
Nitin's laptop ──ssh + .pem + TOTP──▶ UAT-Jump (public) ──▶ UAT-Sentientia-LMS 10.0.135.185 (private)
                                                        └──▶ RDS lms-sentientia-uat-db :3306 (private)
```

The LMS box has **no public IP** and SSM is disabled — everything goes through the
jump host, and the jump host asks for a **2FA code on every connection**. That would
make an automated edit-test loop unbearable, so we authenticate **once per work
session** and multiplex through port forwards:

> ⚠ Windows OpenSSH does not support `ControlMaster` connection re-use — the
> long-lived **tunnel session** below is the Windows-correct equivalent.

**Once per work session (human step, ~10 seconds):**

```powershell
ssh uat-tunnel     # .pem + one TOTP code; keep this window open (it holds no shell)
```

This single session forwards, for as long as it stays open:

| Local port | Remote target | Used for |
|---|---|---|
| `2222` | LMS box `:22` | all ssh/scp — **no further 2FA** |
| `3307` | RDS `:3306` | direct DB access with the XAMPP mysql client |
| `8081` | LMS box `:80` | HTTP smoke checks via curl |

**Everything after that** — Claude included — talks to `localhost` ports:

```powershell
ssh uat-lms "hostname && php -v"                                  # remote commands
scp -P 2222 file.php nitin.rajput@localhost:/tmp/                 # file transfer
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u <appuser> -p # RDS queries
curl.exe -s -H "Host: uat.airpay.academy" http://localhost:8081/login/index.php
```

The host aliases live in `~/.ssh/config` (`uat-tunnel`, `uat-lms`); the only edit
needed when Cloud.in replies is replacing `REPLACE_WITH_JUMP_HOST` (two places).

---

## 2. One-time bootstrap (first session after Cloud.in's reply)

1. Fill `REPLACE_WITH_JUMP_HOST` in `~/.ssh/config`; scan the 2FA QR if not done.
2. First login to the LMS box (`ssh uat-lms`, password from the credentials email):
   `passwd` → **rotate the emailed password immediately** (plaintext-email exposure).
3. Install a public key so the inner hop becomes prompt-free:
   ```powershell
   type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh uat-lms "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"
   ```
   (generate with `ssh-keygen -t ed25519` if none exists; then add `IdentityFile` to
   the `uat-lms` block and password auth is never typed again).
4. First RDS connect through the tunnel as `db_user` → **rotate that password too**,
   then create the app-scoped user (no SUPER) for Moodle's config.php per checklist §0.
5. Verify sudo: `ssh uat-lms "sudo -n true && echo HAVE-SUDO || echo NO-SUDO"`.
   If NO-SUDO, server prep (web server, PHP extensions, service restarts) stays with
   Ganesh and our loop covers app files + CLI + DB only — see §6 ask list.

---

## 3. Installation & dry runs (Stage A) through this channel

With the tunnel up, Stage A (checklist §3) can be executed or co-piloted from a
Claude session on this machine — same division of labour as local:

| Step | Command shape (all via `ssh uat-lms "…"`) |
|---|---|
| Land the package | upload the OneDrive zip via `scp -P 2222`, or download server-side if outbound internet exists; `sha256sum` must print `8b8676f1…7ebc` |
| Extract + config | `unzip` to `/var/www/sentientia/`, write `config.php` (RDS endpoint, wwwroot, `/var/sentientiadata`) |
| Install | `php admin/cli/install_database.php …` — capture output verbatim (first-ever 5.2 runtime validation) |
| Post-install | `php admin/cli/upgrade.php --non-interactive`, purge caches, `repair_task_registrations.php --apply`, `enable_oneclick_enrol.php` |
| Smoke | `curl` through port 8081 (login 200, admin login with cookie jar), plus browser checks per §4 |

Dry-run discipline: every command is echoed into the session log before it runs;
DB-destructive steps (drop/reinstall between dry runs) are [CONFIRM]-gated exactly
like local rules.

---

## 4. The daily edit-test loop (mirror of the XAMPP loop)

| Local loop today | UAT equivalent (tunnel up) |
|---|---|
| Claude edits in `D:\Claude Local\airpay-ld-os` | identical — the repo stays the single source of truth |
| `Copy-Item` → `C:\xampp\htdocs\moodle5\public\...` | `tools/uat/deploy-to-uat.ps1 <path-in-repo>` (scp the changed files to `/var/www/sentientia/moodle5.2/public/...`) |
| `php admin\cli\purge_caches.php` | `tools/uat/uat-purge.ps1` (`ssh uat-lms "php …/admin/cli/purge_caches.php"`) |
| Ctrl+Shift+R on `localhost:8080` | browser on `https://uat.airpay.academy` (once the LB/DNS exist — see §6.3), or `curl` via port 8081 for Claude's own verification |
| mysql on localhost | mysql via `127.0.0.1:3307` |

Helper scripts live in `tools/uat/` (this repo). Claude uses them like it uses the
XAMPP copy-purge cycle today.

**UAT hygiene rule (important):** UAT exists to validate the *packaged release*
(Stage A evidence, then the Stage B rehearsal that gates go-live). Hot-editing UAT
all day turns it into a second dev box and invalidates that evidence. So:

- **Primary dev loop stays local XAMPP** — it is faster and consequence-free.
- The UAT loop is for **triage and release-candidate verification**: Stage A install
  blockers (fast iteration is exactly right there), flag-flip verifications
  (aiquiz auto_push, authoring publish — checklist §5), OAuth2 SMTP, SSO/MFA — the
  things local *cannot* validate.
- After any hot-fix applied on UAT during triage, the same change lands in git the
  same day, and the package is refreshed before Stage B. Drift check: `sha256sum`
  of touched files vs the repo copy.

---

## 5. Can Claude Code run ON the UAT server?

Technically yes (Ubuntu 24.04; `npm i -g @anthropic-ai/claude-code`, needs outbound
443 to Anthropic). **Not recommended** for this environment: it bypasses the
git-first discipline, puts an API key on a shared evidence box, and the tunnel
workflow above already gives the same immediacy with the repo as the anchor.
Revisit only if the tunnel loop proves too slow in practice.

---

## 6. What we still need from Cloud.in / DevOps for this workflow

1. **Jump-host public address** (already asked in the email thread) → fills
   `REPLACE_WITH_JUMP_HOST`.
2. **sudo for `nitin.rajput` on UAT-Sentientia-LMS** (or confirmation that server
   prep + service restarts stay with Ganesh) — decides how much of Stage A we can
   drive ourselves.
3. **LB exposure decision:** internet-facing with an office-IP allowlist
   (recommended — enables real-browser testing of `https://uat.airpay.academy`
   from our machines, including Claude's in-app browser) **or** internal-only
   (browser testing then needs the tunnel + Host-header tricks; workable for curl,
   painful for humans; note we have no local admin to edit the Windows hosts file).
4. **Outbound internet from the LMS box?** (github.com for git pulls, later
   Anthropic/Ollama pulls). If none, everything travels by scp — fine, just slower
   for the 154 MB package.
5. Keep-alive tolerance on the jump host (our config sends keep-alives every 30 s;
   flag if their SSH policy kills long sessions anyway).

---

## 7. Session-start ritual (once everything exists)

```powershell
# Window 1 (leave open all day):
ssh uat-tunnel          # .pem + one TOTP

# Window 2 (Claude session works as usual; UAT commands now just work):
ssh uat-lms "php /var/www/sentientia/moodle5.2/public/admin/cli/purge_caches.php"
```

Close window 1 → all forwards drop → UAT is unreachable again. Nothing about the
jump 2FA is weakened; we only avoid *repeating* it per command.
