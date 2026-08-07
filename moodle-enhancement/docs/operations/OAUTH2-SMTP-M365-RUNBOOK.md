# OAuth2 (XOAUTH2) Outbound SMTP — Microsoft 365 Runbook

**Audience:** IT / DevOps configuring outbound mail for Sentientia LMS (Airpay Academy)
**Applies to:** Moodle 5.1.3+ and the 5.2 deploy candidate — the code paths cited below are identical in both
**Environments:** ninja sandbox + production (`https://www.airpay.academy`) **ONLY** — see §7 for why local dev is excluded
**Code ground-truth verified:** 2026-08-05 against the local 5.1.3+ tree (`admin/settings/server.php`, `lib/phpmailer/moodle_phpmailer.php`, `lib/classes/oauth2/issuer.php`)
**Companion doc:** [`../OAUTH2-SSO-SETUP.md`](../OAUTH2-SSO-SETUP.md) — login SSO. This runbook is the *send-mail* counterpart; the two may use **separate** Entra app registrations.

---

## 0. How Moodle decides to use XOAUTH2 (read this first)

Modern authentication for SMTP replaces the mailbox password with an OAuth2
access token minted from a **system account refresh token**. Three pieces of
core code drive everything in this runbook:

| Code | What it does | Operational consequence |
|---|---|---|
| `admin/settings/server.php:475-532` | Builds the *Outgoing mail configuration* page. `smtpauthtype` offers only `LOGIN/PLAIN/NTLM/CRAM-MD5` **unless at least one enabled OAuth2 issuer exists** — only then is `XOAUTH2` appended (lines 505-517) and the `smtpoauthservice` issuer selector rendered (lines 522-532). | If you can't see XOAUTH2 in the dropdown, the issuer (§3) is missing or disabled. Do §3 before §4. |
| `lib/phpmailer/moodle_phpmailer.php` (ctor lines 54-60 → `process_oauth()` lines 152-175) | At send time, when `$CFG->smtpauthtype == 'XOAUTH2'`, Moodle loads the issuer from `$CFG->smtpoauthservice`, requires it **enabled**, and calls `\core\oauth2\api::get_system_oauth_client($issuer)` — i.e. it authenticates with the issuer's **connected system account** refresh token, passing `$CFG->smtpuser` as the SMTP `userName`. | Mail is only as healthy as the issuer's system account. No connected system account (or missing scopes on it) = every send fails auth. |
| `lib/classes/oauth2/issuer.php:44` | `const SMTPWITHXOAUTH2 = 3;` — the issuer usage mode "SMTP with XOAUTH2 only". The issuer also carries a `systememail` field (line 132) shown as the sending address hint. | Use this mode so the mailer issuer never appears as a login button. |

One more mechanism you must internalise before touching anything:

> **Scopes are frozen at connect time.** When you click *Connect a system
> account*, Moodle stores the granted scopes on the system account record
> (`lib/classes/oauth2/api.php:643`, `$record->grantedscopes = $scopes;`).
> Afterwards, `issuer::is_system_account_connected()`
> (`lib/classes/oauth2/issuer.php:228-253`) demands that **every required
> scope be present in that stored `grantedscopes` string**. Editing the
> issuer's scopes *after* connecting does **not** fix an existing refresh
> token — you must reconnect. This is the root of the single most common
> failure in this setup (§3 step 5, §6).

```
Send path (every outbound email):
email_to_user() → moodle_phpmailer ctor
  └─ $CFG->smtpauthtype == 'XOAUTH2'?
       └─ process_oauth()
            ├─ issuer = api::get_issuer($CFG->smtpoauthservice)   ← must be enabled
            ├─ client = api::get_system_oauth_client($issuer)     ← system account refresh token
            └─ PHPMailer OAuth(provider, clientId, clientSecret,
                               refreshToken, userName=$CFG->smtpuser)
                 └─ SMTP AUTH XOAUTH2 → smtp.office365.com:587
```

---

## 1. Azure / Entra ID — app registration

This **may be a separate app registration** from the SSO app documented in
[`../OAUTH2-SSO-SETUP.md`](../OAUTH2-SSO-SETUP.md) §3a. Keeping them separate
is recommended: the mailer app carries the `SMTP.Send` permission and its
secret can be rotated/revoked without touching login.

1. **Entra ID → App registrations → New registration**
   - Name: `Airpay Academy SMTP Mailer` (or per-environment: `… (ninja)`)
   - Supported account types: **Single tenant**
   - Redirect URI — type **Web**:
     `{wwwroot}/admin/oauth2callback.php`
     - Production: `https://www.airpay.academy/admin/oauth2callback.php`
     - Ninja: the sandbox's own wwwroot + `/admin/oauth2callback.php`
     - Redirect URIs must match **exactly** (scheme, host, path). Register
       both URIs on one app, or use one app per environment.
2. Note the **Application (client) ID** and **Directory (tenant) ID**.
3. **Certificates & secrets → New client secret** — copy the *value*
   immediately (shown once). Calendar the expiry: an expired secret silently
   kills all outbound mail (§6).
4. **API permissions → Add a permission** — all **Delegated**:
   - **Office 365 Exchange Online → SMTP.Send**
     ⚠ This lives under *APIs my organization uses* → search
     "Office 365 Exchange Online" — it is **not** the similarly named Graph
     permission. The token audience for SMTP AUTH is
     `https://outlook.office365.com`, which only the Exchange Online API
     permission grants.
   - **Microsoft Graph →** `openid`, `profile`, `email`, `offline_access`,
     `User.Read` (needed by the Moodle issuer's login/userinfo flow when
     connecting the system account).
5. **Grant admin consent** for the tenant. Without it, the system-account
   connect in §3 fails with `AADSTS65001` (consent required).

---

## 2. Exchange Online prerequisite — SMTP AUTH on the mailbox

XOAUTH2 still rides the **Authenticated SMTP (SMTP AUTH)** protocol endpoint.
If SMTP AUTH is disabled for the mailbox — or blocked tenant-wide (transport
config default, or Security Defaults) — authentication fails with
`535 5.7.3` even when the OAuth token itself is perfectly valid.

1. **Use a dedicated, licensed service mailbox** (e.g.
   `academy-mailer@airpay.co.in`). It needs an Exchange Online license to own
   a mailbox; don't send platform mail as a human's account.
2. Enable Authenticated SMTP on that mailbox:
   - **M365 admin center → Users → Active users →** *mailbox* **→ Mail →
     Manage email apps → tick "Authenticated SMTP"**, or
   - Exchange Online PowerShell:
     ```powershell
     Set-CASMailbox -Identity academy-mailer@airpay.co.in -SmtpClientAuthenticationDisabled $false
     ```
3. Check the tenant-wide picture with the Exchange admin. If the org disables
   SMTP AUTH globally (`Set-TransportConfig -SmtpClientAuthenticationDisabled $true`)
   the per-mailbox override above must be explicitly set; if **Security
   Defaults** is enforced it can block SMTP AUTH regardless — an exclusion /
   Conditional Access carve-out for the service mailbox is then an IT
   decision to record.

---

## 3. Moodle — OAuth2 issuer for SMTP

**Site administration → Server → OAuth 2 services**

1. **Create new service: Microsoft.**
   - Name: `M365 SMTP mailer`
   - Client ID / Client secret: from §1
   - (Azure "tenant" field / baseurl as per the Microsoft template — same
     single-tenant values used in the SSO doc)
2. **"This service will be used" = `SMTP with XOAUTH2 only`.**
   This is issuer usage mode `SMTPWITHXOAUTH2` (`lib/classes/oauth2/issuer.php:44`)
   — it keeps the mailer issuer off the login page and out of every other
   service list.
3. **SMTP email** = the sending mailbox UPN (`academy-mailer@airpay.co.in`).
   Stored in the issuer's `systememail` field (`issuer.php:132`).
4. Save. The issuer must be **enabled** (it is by default) — a disabled
   issuer removes XOAUTH2 from the outgoing-mail page entirely
   (`server.php:505-517`) and is rejected at send time
   (`moodle_phpmailer.php:160`).
5. **⚠ CRITICAL — fix the scopes BEFORE connecting the system account.**
   The stock Microsoft template ships
   (`lib/classes/oauth2/service/microsoft.php:42-43`):

   ```
   loginscopes         = openid profile email user.read
   loginscopesoffline  = openid profile email user.read offline_access
   ```

   **No SMTP scope.** Because `grantedscopes` are captured at connect time
   (§0), connecting with the stock scopes yields a refresh token that can
   never mint an SMTP token → guaranteed `535` on every send.

   Edit the issuer → **"Scopes included in a login request for offline
   access"** (`loginscopesoffline`) → append the SMTP scope so it reads:

   ```
   openid profile email user.read offline_access https://outlook.office365.com/SMTP.Send
   ```

   If you already connected before doing this: edit the scopes, then
   **reconnect** the system account (step 6). The old token is not upgraded
   in place.
6. **Connect a system account** — click the link in the issuer's row and sign
   in **as the sending mailbox itself** (`academy-mailer@…`), *not* as your
   own admin account. The refresh token belongs to whoever signs in; mail
   will authenticate as that identity. After the round-trip the issuer row
   must show the system account as connected —
   `is_system_account_connected()` (`issuer.php:228-253`) verifies both the
   refresh token and that every required scope is inside the stored
   `grantedscopes`.

---

## 4. Moodle — Outgoing mail configuration

**Site administration → Server → Email → Outgoing mail configuration**
(settings page defined at `admin/settings/server.php:475-532`)

| Setting | Value | Notes |
|---|---|---|
| SMTP hosts (`smtphosts`) | `smtp.office365.com:587` | |
| SMTP security (`smtpsecure`) | `TLS` | STARTTLS on 587 |
| SMTP Auth Type (`smtpauthtype`) | `XOAUTH2` | Only listed when an enabled issuer exists (`server.php:515-517`) |
| Issuer (`smtpoauthservice`) | `M365 SMTP mailer` (the §3 issuer) | Selector only rendered when an enabled issuer exists (`server.php:522-532`) |
| SMTP username (`smtpuser`) | `academy-mailer@airpay.co.in` (mailbox UPN) | Passed verbatim as the XOAUTH2 `userName` (`moodle_phpmailer.php:168`) — must be the connected mailbox |
| SMTP password (`smtppass`) | **empty** | The OAuth token replaces it; leave blank |
| SMTP session limit (`smtpmaxbulk`) | `1` (default) | Tune later if cron mail volume warrants |

Also align the sender identity: `noreplyaddress` should either be the mailbox
UPN itself or an address the mailbox holds **Send As** rights on — otherwise
Exchange rejects or rewrites the envelope.

---

## 5. Verification

1. **Test email:** Site administration → Server → Email → **Test outgoing
   mail configuration** (`/admin/testoutgoingmailconf.php`). Send to your own
   mailbox. On failure, re-run with the debug option enabled on that page and
   read the SMTP transcript — the `535` reasons in §6 are distinguishable
   there.
2. **Token refresh task:** Site administration → Server → Tasks → Scheduled
   tasks → confirm **`\core\oauth2\refresh_system_tokens_task`** ("Refresh
   OAuth tokens for service accounts") exists and is enabled
   (`lib/classes/oauth2/refresh_system_tokens_task.php`, registered in
   `lib/db/tasks.php`). It keeps the system-account token fresh; recurring
   failures of this task are the early-warning signal for §6's
   "worked-then-stopped" row.
3. **Issuer health:** OAuth 2 services list — the issuer shows *system
   account connected*. If it shows "No" despite a completed connect, a
   required scope is missing from `grantedscopes` (§3 step 5).
4. **Real-path smoke:** trigger a genuine platform mail (e.g. a password
   reset to a test account) and confirm delivery + correct From address.

---

## 6. Troubleshooting

| Symptom | Root cause | Fix |
|---|---|---|
| `XOAUTH2` absent from the *SMTP Auth Type* dropdown | No **enabled** OAuth2 issuer exists — the option is only appended when one does (`server.php:505-517`) | Create/enable the §3 issuer, reload the page |
| *Issuer* (`smtpoauthservice`) selector not shown at all | Same root cause as above (`server.php:522-532`) | Same fix |
| `SMTP Error: 535 5.7.3 Authentication unsuccessful` | (a) SMTP AUTH disabled on the mailbox or blocked tenant-wide / Security Defaults (§2); **or** (b) `SMTP.Send` missing from the system account's `grantedscopes` — connected before the scope was appended (§3 step 5) | (a) enable Authenticated SMTP per §2; (b) append `https://outlook.office365.com/SMTP.Send` to the offline scopes, then **reconnect** the system account |
| Mail worked for months, then all sends fail auth | Entra client secret expired, or the refresh token was revoked/invalidated (password reset, CA policy change) | New client secret in Entra → update issuer → reconnect system account; check `refresh_system_tokens_task` run history |
| `AADSTS65001` (consent required) when connecting the system account | Admin consent never granted on the app | Grant admin consent (§1 step 5), retry |
| `AADSTS50011` redirect URI mismatch on connect | Registered redirect URI ≠ `{wwwroot}/admin/oauth2callback.php` exactly | Fix the app registration's Web redirect URI (per environment) |
| Issuer shows system account **not** connected right after a successful-looking connect | `is_system_account_connected()` scope containment check failing (`issuer.php:243-250`) | Fix issuer scopes (§3 step 5) and reconnect |
| Sends succeed but From is wrong / Exchange rejects sender | `smtpuser` ≠ connected mailbox, or `noreplyaddress` is an address the mailbox can't Send As | Align `smtpuser` + `noreplyaddress` with the mailbox (or grant Send As) |
| Test says success but nothing arrives (ninja) | `noemailever` / `divertallemailsto` set in that environment's config | Check `config.php` / `admin/cli/cfg.php --name=noemailever` — see §7 |

---

## 7. Local dev is out of scope — `noemailever` stays ON

Local XAMPP keeps **`$CFG->noemailever = 1`** — the standing rule from the
151-email incident (real production users mailed from a local clone). Moodle
itself flags this state: the outgoing-mail settings page renders a red
warning banner whenever `noemailever` is set (`server.php:477-481`).

**Do not** configure real SMTP credentials, issuers, or system accounts on
local. This runbook applies to the **ninja sandbox and production only**, and
production changes remain gated on Nitin's explicit go (rollout-gate rule).

---

## 8. Quick reference — who to call

| Layer | Owner | Artifacts |
|---|---|---|
| Entra app / consent / secret rotation | IT (Azure admin) | Client ID, secret expiry calendar |
| Exchange SMTP AUTH / service mailbox / licenses | IT (Exchange admin) | `Set-CASMailbox`, transport config |
| Moodle issuer + outgoing mail config | LMS admin (Nitin / DevOps) | §3 + §4 of this runbook |
| Login SSO (separate concern) | LMS admin | [`../OAUTH2-SSO-SETUP.md`](../OAUTH2-SSO-SETUP.md) |
