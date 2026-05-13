# MFA Enforcement Runbook

Mitigates Supplement-A risk **S4** (site-administrator account
credential compromise). Currently the platform allows password-only
sign-in for all roles including site administrators. This runbook
defines how to enable multi-factor authentication enforcement and
the operational consequences.

## 1. Current state

- The Moodle core `tool_mfa` plugin is bundled in the codebase and
  installed (visible at `/admin/tool/mfa/`).
- MFA is **optional** by default — users can opt in but no role is
  forced to.
- The break-glass site-administrator account (`academy@airpay.co.in`)
  is jointly held by L&D and IT and is currently password-only.
- TOTP, email, and IP-allowlist factors are configured but inactive.

## 2. Target state

Three enforcement tiers, applied progressively.

### Tier A — Site administrators

**Status:** must enforce within 30 days of cutover.

- All accounts holding the `manager` archetype at `CONTEXT_SYSTEM` are
  required to register TOTP.
- Login without TOTP factor present is denied.
- Recovery codes printed at registration time and stored in the
  Airpay corporate password manager.
- Break-glass account uses TOTP held by the L&D + IT leads jointly
  (two physical tokens, kept in separate locations).

### Tier B — Tenant administrators + managers

**Status:** enforce within 90 days of cutover.

- All accounts holding the `manager` archetype at any context are
  required to register at least one factor (TOTP preferred, email
  fallback acceptable).
- IP-allowlist factor used for tenant administrators whose office IP
  ranges are known.

### Tier C — All users (long-term)

**Status:** evaluate after 12 months of operation.

- All employees are encouraged but not required to register MFA.
- External Public-tenant learners are out of scope (they sign in
  with self-managed credentials; mandating MFA would create friction
  for the paid commercial offering).

## 3. Enabling MFA enforcement — admin steps

Run as site administrator.

### Step 1 — Enable the MFA subsystem

Site administration → Security → MFA → Enable.

### Step 2 — Configure factors

For each factor:

- **TOTP** (Google Authenticator / Authy / 1Password):
  Site administration → Plugins → Authentication → TOTP — set "Enabled".

- **Email**:
  Site administration → Plugins → Authentication → Email — set "Enabled".
  Verify SMTP works (the email factor cannot send a code if SMTP fails).

- **IP-allowlist** (optional, for office-bound roles):
  Site administration → Plugins → Authentication → IP — set "Enabled".
  Add Airpay corporate IP ranges.

### Step 3 — Define enforcement rules

Site administration → Security → MFA → Roles:

- Find `manager` archetype.
- Set MFA enforcement to "Required" at the system context.
- Save.

### Step 4 — Set grace period

Site administration → Security → MFA → Settings:

- Grace period: 14 days.
- Lockout after grace period: yes.
- Recovery code count: 10.

The 14-day grace period gives existing site administrators time to
register a factor without being locked out at the moment enforcement
turns on.

### Step 5 — Comms

Send a T-7 days advance notice to every site administrator:

```
Subject: ACTION REQUIRED — register MFA on Airpay Academy within 14 days

Hi,

You hold an administrative role on Airpay Academy. From <DATE+14>,
sign-in to the platform will require a second factor in addition to
your password.

Please register a Time-Based One-Time Password factor in the next
14 days at:

  https://www.airpay.academy/admin/tool/mfa/user_preferences.php

You will be guided through registering Google Authenticator (or
equivalent TOTP app) and printing 10 recovery codes. Store the
recovery codes in your Airpay password manager — they are the only
way back into the platform if your TOTP device is lost.

After <DATE+14> any administrator without an active MFA factor will
be unable to sign in. The break-glass account remains available for
emergencies via the IT helpdesk.

Questions: reply here, or ping me directly.

Head of L&D
```

## 4. Operational consequences

| Consequence | Mitigation |
|---|---|
| A site admin loses their TOTP device and recovery codes | Break-glass account (held by L&D + IT joint custody) signs in, resets the affected admin's MFA. Documented in IT runbook. |
| Email factor fails because SMTP is degraded | Each admin should have TOTP registered as primary; email is fallback only. The grace period also gives breathing room. |
| Audit shows a manager has not registered MFA after grace period ends | Account is locked. Manager contacts L&D for re-enablement. The lockout is by design — it's the enforcement. |
| New manager hire onboarded post-enforcement | Onboarding checklist requires MFA registration on Day 1. |
| Compliance Officer / DPO needs to assist a learner | Compliance Officer is itself a manager-archetype role and follows the same MFA enforcement. |
| Mobile app sign-in | Moodle Mobile supports MFA via the same TOTP flow. Verify in pre-enforcement testing. |

## 5. Verification after enforcement is live

### Automated check (run weekly)

```sql
-- Find admin/manager users without an MFA factor registered.
-- Run from the audit log helper or as a direct query.
SELECT u.id, u.username, u.email,
       (SELECT COUNT(*) FROM mdl_tool_mfa
         WHERE userid = u.id AND enabled = 1) AS factor_count
  FROM mdl_user u
  JOIN mdl_role_assignments ra ON ra.userid = u.id
  JOIN mdl_role r ON r.id = ra.roleid
  JOIN mdl_context c ON c.id = ra.contextid
 WHERE r.archetype = 'manager'
   AND c.contextlevel = 10  -- CONTEXT_SYSTEM
   AND u.deleted = 0
   AND u.suspended = 0
HAVING factor_count = 0;
```

Expected: zero rows after the grace period ends. Each row returned is
a manager-archetype account that is currently unable to sign in (and
should be remediated).

### Manual spot-check

Quarterly, the Head of L&D logs in as `academy@airpay.co.in` (using
TOTP), confirms the break-glass path works.

## 6. Rolling back enforcement

If MFA enforcement causes a material operational issue (e.g. half the
manager population locks themselves out on Day 1), the rollback is:

1. Site admin: Security → MFA → Roles → set manager enforcement back
   to "Optional".
2. Save. Sign-in immediately reverts to password-only for the affected
   role.
3. Investigate the root cause before re-enabling.

The rollback is non-destructive — registered factors remain
registered; only the enforcement is lifted.

## 7. Annual review

Every January:

- Verify TOTP devices are still in active use by all administrators
  (a device lost without report is a security gap).
- Rotate the break-glass account's recovery codes (regenerate 10 new
  codes, securely re-distribute to L&D + IT leads).
- Review whether to extend MFA enforcement to additional roles per
  Tier C.

## 8. Compliance positioning

This runbook supports the Digital Personal Data Protection Act 2023
"reasonable security safeguards" requirement (s.8(4)). MFA on
administrative accounts is broadly considered industry-standard and
its absence would be a finding in any external security audit.

Documented status: MFA enforcement scheduled in Phase 9.6 backlog,
target enablement within 30 days of production cutover.
