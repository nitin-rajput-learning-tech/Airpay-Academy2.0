# OAuth2 SSO Setup — Airpay Academy
## Microsoft Entra (Azure AD) + Google + custom OIDC per tenant

**Phase 1I of ENTERPRISE-GRADE-PLAN.** Configures per-tenant Single Sign-On
via Moodle's bundled `auth_oauth2` plugin. Each tenant (Airpay, Public, ZEEA)
can wire up its own OAuth2 provider independently.

> **Related runbook — outbound mail:** OAuth2 (XOAUTH2) **SMTP** via
> Microsoft 365 is covered separately in
> [`operations/OAUTH2-SMTP-M365-RUNBOOK.md`](operations/OAUTH2-SMTP-M365-RUNBOOK.md)
> — the mailer may use a **separate** Entra app registration (delegated
> `SMTP.Send`) from the SSO app below, and its issuer uses the
> "SMTP with XOAUTH2 only" usage mode so it never appears on the login page.

---

## 1. Why per-tenant OAuth2?

| Tenant | User population | Likely IdP |
|---|---|---|
| Airpay (`/1`) | 2,188 internal employees | Microsoft Entra (already in Microsoft 365) |
| Public (`/77`) | 676 external learners | Google Workspace (free for individuals) OR self-signup |
| ZEEA (`/177`) | 6 partner accounts | Their own corporate IdP (custom OIDC) |

Single SSO config for all three would force one IdP to handle every user.
Per-tenant config lets each tenant pick the IdP its users already have.

---

## 2. Architecture — uses Moodle core, no new plugin

Moodle's `auth_oauth2` plugin (bundled) already supports multiple OAuth2
**issuers**. We just configure one issuer per tenant and use the
existing branding chain to make login look right for each tenant.

```
┌─────────────────────────────────────────────────┐
│  Moodle 5 — auth_oauth2 (already shipped)        │
├─────────────────────────────────────────────────┤
│  Issuer 1: "Airpay (Entra)"     → Microsoft     │
│  Issuer 2: "Public (Google)"    → Google         │
│  Issuer 3: "ZEEA (custom OIDC)" → their IdP      │
└─────────────────────────────────────────────────┘
        ↓
auth_oauth2 button on login page →
        ↓
User picks an issuer → OAuth2 flow → user provisioned in correct tenant
```

**No custom code required for the SSO itself.** Configuration only.

Additional work to make the experience tenant-aware:
1. **Show only the relevant issuer button** on each tenant's login page
   (Public users shouldn't see "Sign in with Entra" if they don't have it)
2. **Provision new users into the right tenant** based on which issuer
   they used to log in (custom hook in `auth_oauth2` callback)
3. **Map IdP claims → user.open_path** so new users land in the correct
   org node

---

## 3. Step-by-step: Microsoft Entra (Azure AD) for Airpay tenant

### 3a. In Azure portal

1. Go to **Azure AD → App registrations → New registration**
2. Name: `Airpay Academy SSO`
3. Supported account types: **Single tenant** (only Airpay employees)
4. Redirect URI:
   - Type: **Web**
   - URL: `https://www.airpay.academy/admin/oauth2callback.php`
5. Click **Register**
6. Note the **Application (client) ID** and **Directory (tenant) ID**
7. Go to **Certificates & secrets → New client secret**
   - Description: `Airpay Academy 2026`
   - Expires: 24 months
   - **Copy the secret value immediately** (only shown once)
8. Go to **API permissions → Add a permission → Microsoft Graph → Delegated**
   - `openid`
   - `email`
   - `profile`
   - `User.Read`
9. Click **Grant admin consent**

### 3b. In Moodle admin

1. Site administration → Server → OAuth2 services → **Create new
   service for Microsoft**
2. Service name: `Airpay (Entra)`
3. Client ID: (paste Application ID from step 3a.6)
4. Client secret: (paste secret from step 3a.7)
5. Service base URL: leave blank (Microsoft is auto-configured)
6. **Save changes**
7. Click the new service row → **Configure endpoints** → ensure all 4
   endpoints exist (Moodle's pre-configured template auto-fills these)
8. Click **Configure user field mappings**:
   - email → email
   - given_name → firstname
   - family_name → lastname
   - upn → idnumber
9. Save
10. Site administration → Plugins → Authentication → Manage authentication
    → enable **OAuth 2** plugin
11. Site administration → Plugins → Authentication → OAuth 2 → ensure
    "Airpay (Entra)" is in the active issuer list

### 3c. Tenant routing — new users land in `/1` (Airpay)

Add to `local/airpay_org/lib.php`:

```php
/**
 * Auto-assign Entra-authenticated users to Airpay tenant.
 *
 * Called by auth_oauth2 after successful login if the user is brand new.
 */
function local_airpay_org_oauth2_user_provisioned($event) {
    global $DB;
    $userid  = $event->relateduserid;
    $issuer  = $event->other['issuer'] ?? '';

    // Each issuer maps to a tenant root.
    $issuer_to_tenant = [
        'Airpay (Entra)'  => '/1',
        'Public (Google)' => '/77',
        'ZEEA (custom)'   => '/177',
    ];

    $path = $issuer_to_tenant[$issuer] ?? null;
    if (!$path) {
        return;  // unknown issuer, let admin manually assign
    }

    $DB->set_field('user', 'open_path', $path, ['id' => $userid]);
}
```

Register the hook in `db/events.php`:

```php
$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => 'local_airpay_org_oauth2_user_provisioned',
    ],
];
```

---

## 4. Step-by-step: Google Workspace for Public tenant

### 4a. In Google Cloud Console

1. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**
2. Application type: **Web application**
3. Name: `Airpay Academy Public`
4. Authorized redirect URIs:
   - `https://www.airpay.academy/admin/oauth2callback.php`
5. Click **Create**
6. Copy Client ID + Client secret

### 4b. In Moodle admin

Same as Microsoft above, but:
- Service template: **Google**
- Service name: `Public (Google)`
- All other fields as in 3b

### 4c. Tenant routing

The hook in 3c already handles this — `'Public (Google)' => '/77'`.

---

## 5. Step-by-step: Custom OIDC for ZEEA tenant

### 5a. ZEEA provides

- Authorization endpoint URL (e.g. `https://login.zeea.com/oauth2/authorize`)
- Token endpoint URL
- UserInfo endpoint URL
- JWKS URL (for token verification)
- Client ID + secret (issued by ZEEA)

### 5b. In Moodle admin

1. OAuth2 services → **Create new service** (NOT from template)
2. Service name: `ZEEA (custom)`
3. Client ID + secret: from ZEEA
4. Service base URL: ZEEA's issuer URL (e.g. `https://login.zeea.com`)
5. **Configure endpoints** manually:
   - `authorization_endpoint`: authorize URL
   - `token_endpoint`: token URL
   - `userinfo_endpoint`: userinfo URL
   - `jwks_uri`: jwks URL
6. Save + configure user field mappings as in 3b

---

## 6. Per-tenant login page

Each tenant gets its own login URL that only shows that tenant's SSO button:

```
https://www.airpay.academy/login/index.php?tenant=airpay  → Entra only
https://www.airpay.academy/login/index.php?tenant=public  → Google only
https://www.airpay.academy/login/index.php?tenant=zeea    → ZEEA only
```

Implementation: extend `theme_airpayux/templates/core/loginform.mustache`
to read `tenant` query parameter and filter the `identityproviders` array
before rendering. See `theme_airpayux/classes/output/core_renderer.php`
`get_login_providers()` for the hook.

---

## 7. Testing checklist

Before going live, walk:

1. **New user provisioning**: Log in via Entra → user created → ends up
   in `/1` org path, default Employee role, lands on dashboard
2. **Existing user login**: User who exists in Airpay tenant logs in via
   Entra → matched on email → no duplicate user created
3. **Cross-tenant prevention**: User in `/77` (Public) cannot log in via
   the `/1` (Airpay) Entra issuer — emails don't match enabled domain
4. **Logout**: Logout works and clears SSO session
5. **Password fallback**: If SSO is broken, admin can still log in via
   the local-password form (don't disable manual auth)
6. **MFA**: Verify MFA prompt fires on Entra side; Moodle should not need
   its own MFA when SSO is up

---

## 8. Security checklist

- [ ] Client secrets stored in environment variables, not in DB
- [ ] Client secret rotation procedure documented (every 24 months for Entra)
- [ ] Webhook secret for token revocation (if IdP supports)
- [ ] Logging of all OAuth2 logins to audit trail (Moodle does this by default)
- [ ] Email-domain restriction per issuer (Entra → only @airpay.* domains)
- [ ] Reject auto-provisioning for emails not in approved domains
- [ ] HTTPS-only redirect URI (no http://)
- [ ] CORS allowed origins limited to airpay.academy

---

## 9. Operational notes

- **Secret rotation**: Schedule calendar reminder 60 days before expiry
- **IdP outage handling**: Manual `auth_manual` remains enabled so site
  admins can always log in if SSO breaks
- **User provisioning lag**: New Entra users may take up to 15 min to
  sync (Moodle reads on demand, no scheduled task needed)
- **De-provisioning**: When an Airpay employee leaves, removing them from
  Entra group `airpay-academy-users` blocks future logins. Existing
  active session continues until next login attempt.

---

## 10. Cost summary

| Component | Cost |
|---|---|
| auth_oauth2 plugin | $0 (bundled with Moodle) |
| Microsoft Entra (P1+) | included if Airpay has M365 Business Premium |
| Google Workspace | $0 (using free Google accounts is fine for Public tenant) |
| ZEEA's OIDC | Per their contract |

No new infrastructure required. SSO is a configuration task, not a
build task — that's why this section is short.

---

## 11. Status

- **Code**: 0 lines required (Moodle ships everything)
- **Configuration**: ~2 hours per tenant (Steps 3, 4, 5 above)
- **Testing**: ~2 hours end-to-end per tenant
- **Documentation**: This file
- **Optional enhancement**: ~4-6 hours for per-tenant login URL routing
  (Section 6)

This document is the deliverable for **Phase 1I (F.8)** in
ENTERPRISE-GRADE-PLAN. The actual IdP setup happens in production when
Nitin provides:
1. Azure AD app registration for Airpay tenant
2. Google Cloud OAuth client for Public tenant
3. ZEEA's OIDC endpoint URLs + credentials

Until then, manual authentication (`auth_manual`) remains the production
default — every existing user can log in normally.
