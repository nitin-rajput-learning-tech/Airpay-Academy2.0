# SSO Setup Guide — Airpay Academy

## Option 1: OAuth 2.0 (Built-in, No Plugin Needed)

Moodle 4.5 includes `auth/oauth2` which supports Azure AD, Google Workspace, and any OIDC provider.

### Azure AD (Recommended for Airpay)

1. **Azure Portal:**
   - Go to Azure AD → App Registrations → New Registration
   - Name: "Airpay Academy SSO"
   - Redirect URI: `https://www.airpay.academy/admin/oauth2callback.php`
   - Copy: Application (client) ID + Directory (tenant) ID

2. **Create Client Secret:**
   - Certificates & Secrets → New Client Secret
   - Copy the Value (not the Secret ID)

3. **Moodle Admin:**
   - Site Admin → Server → OAuth 2 services → Microsoft
   - Enter: Client ID, Client Secret
   - Scopes: `openid profile email User.Read`
   - Enable: "Login page" button

4. **Enable Auth Plugin:**
   - Site Admin → Plugins → Authentication → Manage → Enable "OAuth 2"
   - Settings: Match by email (recommended for existing users)

### Google Workspace

1. Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client
2. Authorized redirect: `https://www.airpay.academy/admin/oauth2callback.php`
3. Moodle Admin → OAuth 2 services → Google → Enter Client ID/Secret

## Option 2: SAML 2.0 (Requires Plugin Install)

For enterprise SAML (Okta, OneLogin, PingIdentity):

1. Download: https://github.com/catalyst/moodle-auth_saml2/releases
2. Extract to: `/moodle/auth/saml2/`
3. Site Admin → Notifications (install)
4. Site Admin → Plugins → Authentication → SAML2 → Configure
5. Metadata URL: Your IdP's metadata endpoint
6. Map attributes: email → user email

## Option 3: LDAP (On-premise Active Directory)

Already installed at `auth/ldap`. Configure via:
- Site Admin → Plugins → Authentication → LDAP
- LDAP Server URL: `ldap://your-dc.airpay.co.in`
- Bind DN + Password
- User type: AD (ActiveDirectory)

## Recommended for Airpay

| Tenant | SSO Method | Identity Provider |
|--------|-----------|-------------------|
| Airpay (internal) | **OAuth 2 + Azure AD** | Azure AD (Airpay's Microsoft 365) |
| Public (external) | Email/password + optional Google | Google Workspace |
| ZEEA (Tanzania) | Email/password | Manual (no enterprise IdP) |

## Post-Setup Checklist
- [ ] Test login with SSO button on login page
- [ ] Verify existing users match by email
- [ ] Verify new SSO users get assigned to correct costcenter
- [ ] Test logout → SSO logout (single logout)
- [ ] Test MFA passthrough (Azure AD MFA should work transparently)
