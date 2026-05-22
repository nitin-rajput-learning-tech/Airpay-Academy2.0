# local_sentientia_pwa

Sentientia LMS — Progressive Web App. Service worker, push notifications,
installability, and offline shell.

**Status:** Phase D.1 complete (a–d). v0.5.0-alpha, version `2026052110`.
**Maturity:** ALPHA — code complete, real-device walk pending before flipping production flags ON.

---

## What this plugin does

1. **PWA install** — `manifest.php` serves a per-customer Web App Manifest;
   `install_prompt.js` captures `beforeinstallprompt` and reveals a custom
   Install CTA on the dashboard. iOS Safari gets a guided 3-step
   "Add to Home Screen" modal because it doesn't fire `beforeinstallprompt`.

2. **Service worker** — `sw.php` registers at root scope (via
   `Service-Worker-Allowed: /`). Phase D.1.d strategies:
   - Navigation requests → network-first, branded `offline.html` fallback
   - Static assets (CSS/JS/woff/svg/png/jpg/ico) → cache-first with
     stale-while-revalidate
   - Everything else → passthrough (SSE/REST/admin paths explicitly bypass)

3. **Web Push notifications** — hand-rolled VAPID + RFC 8291 crypto stack:
   - `vapid_key_manager` — P-256 keypair generation + at-rest envelope encryption (audit fix #6)
   - `jwt_signer` — ES256 JWT for VAPID auth (`Authorization: vapid t=<jwt>,k=<pub>`)
   - `payload_encrypter` — AES-128-GCM + HKDF-SHA256 per RFC 8291
   - `push_sender` — tenant-scoped cross-tenant boundary (audit fix #4)
   - `subscription_manager` — strict base64url + SSRF allowlist (audit fixes #1/#2/#5)
   - `mock_receiver.php` — gated on `$CFG->debugdeveloper` (audit fix #3)
   - 6/6 BLOCKING audit findings remediated. See `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`.

4. **Feature flags** (registered in `db/feature_flags.php`):

   | Flag | Default | Purpose |
   |---|---|---|
   | `sentientia.pwa.enabled` | ON | Master switch — service worker registers |
   | `sentientia.pwa.push.enabled` | OFF | Master Web Push delivery switch |
   | `sentientia.pwa.push.reminders` | OFF | Deadline-reminder push (B.3) |
   | `sentientia.pwa.push.overdue` | OFF | Overdue-escalation push (B.3) |
   | `sentientia.pwa.install.enabled` | OFF | Show Install CTA on dashboard (D.1.b) |

---

## Capability inventory (D.1.e — per ADR-005)

This is the contract sheet for "what does Path B (PWA) unlock vs. native?".
When a customer asks "do we need an app?" — answer with this table.

### Capabilities we use today (Path B / PWA)

| Capability | PWA support | Used here |
|---|---|---|
| **Web Push (FCM/APNs)** | Yes on Chrome/Edge/Firefox; iOS 16.4+ on Safari | Phase B.2.5 — full RFC 8291 stack |
| **Service worker — offline shell** | Yes everywhere except iOS pre-11.3 | Phase D.1.d — branded `offline.html` |
| **Service worker — cache-first static** | Yes everywhere | Phase D.1.d — CSS/JS/SVG/PNG/woff |
| **`beforeinstallprompt` + custom CTA** | Chrome/Edge/Samsung/Firefox 122+; iOS Safari skips (guided modal instead) | Phase D.1.b |
| **Web App Manifest (`display: standalone`)** | All modern browsers | Phase D.1.a |
| **`appinstalled` event** | Chrome/Edge; iOS via `display-mode: standalone` heuristic | Phase D.1.b |
| **`window.matchMedia('(display-mode: standalone)')`** | All modern browsers | iOS-install-hint dismissal logic |
| **`localStorage` (7-day quarantine)** | Universal | Install CTA + iOS hint dismiss |
| **Camera (`<input capture>`)** | Universal mobile | Future: profile picture upload |
| **Geolocation API** | Universal, requires permission | Future: classroom attendance |
| **Server-Sent Events (EventSource)** | Universal | Sentientia Live (realtime polls) |
| **`fetch` + streaming response** | Universal | SCORM player + course content |
| **`navigator.share`** | Mobile only; not Firefox | Future: share course link |
| **`navigator.clipboard`** | Universal modern | Future: copy session join code |
| **Form autofill** | Native browser, no API needed | Login + signup flows |

### Capabilities we do NOT use (Path B sufficient)

Most native-only APIs are irrelevant to L&D. Listed here so a future
"do we need native?" review can confirm none are required:

| Capability | Native-only? | Why we don't need it |
|---|---|---|
| **Background Sync** | iOS PWA gap (Android PWA has it) | Quiz answers submit online-only; offline queue not planned |
| **Bluetooth (Web Bluetooth)** | Chrome only on Android desktop | No connected-device use case in L&D |
| **NFC** | Chrome Android only | No tap-to-clock-in scenarios |
| **Filesystem write** | Chrome desktop only | SCORM packages don't need write access |
| **Contacts Picker** | Chrome Android only | No phonebook integration planned |
| **WebAuthn (FIDO2)** | Universal PWA support | Will use if SSO mandates it — already feasible in PWA |
| **App-Store presence** | Native-only | Acquisition is internal HR-driven; Store-listing irrelevant |
| **Deep background scheduling** | Native-only | Cron-driven server tasks cover all scheduled work |
| **System notification channels** | Native-only | Web Push notifications sufficient |
| **AppLinks / Universal Links** | Native-only | `https://` URLs are the canonical entry point |
| **Force-stop / kill-switch from server** | Native-only | Feature flags + SW kill-switch handle this |
| **In-app purchases** | Native-only | No monetisation in L&D |
| **Push without permission** | Native-only via FCM topics | We require explicit opt-in (GDPR-compliant) |
| **Local notifications without server** | Native-only | All notifications originate from cron tasks |
| **Persistent background workers** | iOS native-only | SW + Web Push covers our delivery |

### Path-C readiness checklist (Cordova/Capacitor wrap)

When the trigger fires (Play-Store trust signal, iOS Background Sync
requirement, or Apple PWA deprecation per ADR-005), the following
items become the **wrap-ready inventory**:

- [ ] **Manifest is canonical.** `local/sentientia_pwa/manifest.php`
      already accepts per-customer branding via `customer::branding()`.
      Capacitor reads the manifest at wrap time → no separate
      `capacitor.config.json` editing per customer.
- [ ] **All routes are HTTPS-only.** Capacitor's WebView blocks mixed
      content; Moodle's `core` enforces HTTPS in production. ✅ done.
- [ ] **Push token plumbing reusable.** The current FCM/APNs endpoints
      that `push_sender` POSTs to also accept native push tokens via
      Capacitor's `@capacitor/push-notifications` plugin. Substitution
      is one resolver swap in `subscription_manager::save()`.
- [ ] **No reliance on Chrome-only APIs.** Verified — see "Capabilities
      we use today" table.
- [ ] **Deep-link allowlist.** Capacitor App Links plugin needs the
      list of paths to intercept (course URL, audience join URL, push
      click URL). Today: `/my/`, `/local/sentientia_live/audience/join.php`,
      `/local/sentientia_pwa/preferences.php`. Document in wrap repo.
- [ ] **Splash + icon set.** 192 + 512 PNGs already shipped at
      `local/airpay_core/pix/customer/1/icon-*.png`. Capacitor needs
      additional sizes (1024×1024, splash adaptive). Generate from the
      same source SVG when wrap kicks off.
- [ ] **App ID + bundle reservation.** `in.airpay.academy` (Android)
      + `in.airpay.academy` (iOS) need to be reserved at wrap time.
      Not done — costs Apple Developer Program enrolment + Google Play
      Console enrolment first.
- [ ] **Privacy disclosures.** Both stores require:
      - Data collected (we collect: email, name, course progress)
      - Why (LMS service delivery)
      - How shared (not shared with third parties; pushes via FCM/APNs
        are transient and Apple/Google handle the encryption)
      - Push notification opt-in disclosure (GDPR + iOS App Tracking
        Transparency)
- [ ] **In-app review / rating prompts.** Out of scope — no rating
      flow planned.

When all checkboxes are green, wrap is a 1–2 day engineering effort
(create sibling repo `sentientia-native-wrapper`, install
`@capacitor/cli`, `npx cap add android/ios`, sign with Airpay's existing
production certificates, submit). See ADR-005 for the decision context.

---

## Local development

### One-time setup
```powershell
# Generate VAPID keypair (Phase B.2.b)
cd C:\xampp\htdocs\moodle5\public
php local\sentientia_pwa\cli\vapid_keygen.php

# Audit-fix self-test (28 checks)
php local\sentientia_pwa\cli\test_audit_fixes.php

# Install CTA injection self-test (7 checks)
php local\sentientia_pwa\cli\verify_install_cta.php
```

### Master key for VAPID PEM envelope (audit fix #6)
Production push REQUIRES a master key for the AES-256-GCM PEM envelope.

Generate one:
```powershell
php local\sentientia_pwa\cli\generate_master_key.php
```

Set via env (preferred — never on disk):
```powershell
$env:SENTIENTIA_VAPID_MASTER_KEY = '<paste base64url here>'
```

Or in `config.php` (file-system protection only):
```php
$CFG->sentientia_vapid_master_key = '<paste base64url here>';
```

After setting the master key, regenerate the VAPID keypair so the
existing PEM is encrypted at rest:
```powershell
php local\sentientia_pwa\cli\vapid_keygen.php --force
```
This invalidates every existing subscription — production rollout
needs to coordinate with the user-facing "re-enable notifications"
message.

### Push delivery smoke test
```powershell
# End-to-end mock — generates a mock subscriber, signs/encrypts/sends
# to mock_receiver.php, verifies decryption.
php local\sentientia_pwa\cli\run_push_e2e.php
```

### Toggle the install CTA
```php
// CLI — turn ON on local for testing
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled', 0, true, 2, 'dev', 0);

// CLI — turn OFF (default)
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled', 0, false, 2, 'dev', 0);
```

---

## Production gates (must clear before flipping push flag ON)

1. **VAPID master key configured** — env var or `$CFG`. Audit fix #6.
2. **VAPID keypair regenerated under master key** — so the PEM is
   encrypted at rest, not legacy plaintext.
3. **Real-device walk** — Android Chrome shows Install CTA, install
   completes, push delivered to standalone PWA.
4. **Real-device walk on iOS Safari** — Add-to-Home-Screen modal
   appears, install completes, push delivered.
5. **Notification permission UX** — confirm `preferences.php` flow
   doesn't double-prompt or get blocked.
6. **Tenant isolation walk** — login as Public-tenant user, verify
   pushes from Airpay-tenant cron tasks do NOT deliver.

---

## File map

```
local/sentientia_pwa/
├── README.md                   ← this file (D.1.e capability inventory)
├── version.php                 ← 2026052110, 0.5.0-alpha
├── lib.php                     ← extend_navigation + install CTA injection
├── settings.php                ← admin → Plugins → Local → Sentientia PWA
├── manifest.php                ← application/manifest+json (D.1.a)
├── sw.php                      ← service worker (D.1.d, v2)
├── offline.html                ← branded offline fallback (D.1.d)
├── preferences.php             ← user → "Browser notifications" page (B.2.b)
├── mock_receiver.php           ← dev-only crypto oracle for e2e tests (gated #3)
├── admin/
│   └── push_log.php            ← admin → push delivery log viewer (B.3.c)
├── classes/
│   ├── external/
│   │   └── save_subscription.php  ← WS endpoint w/ SSRF allowlist (#1/#2/#5)
│   ├── output/
│   │   └── push_log_table.php  ← B.3.c table renderer
│   ├── task/
│   │   └── prune_push_log.php  ← cron — 90-day retention
│   ├── jwt_signer.php          ← ES256 JWT (RFC 7518 §3.4)
│   ├── notification_bridge.php ← cron → push fanout
│   ├── payload_encrypter.php   ← AES-128-GCM + HKDF (RFC 8291)
│   ├── push_logger.php         ← delivery log writer
│   ├── push_sender.php         ← orchestrator (tenant-scoped #4)
│   ├── subscription_manager.php← upsert + for_user(tenant_filter)
│   └── vapid_key_manager.php   ← keygen + envelope encryption (#6)
├── cli/
│   ├── generate_master_key.php ← NEW: VAPID PEM master key generator
│   ├── run_push_e2e.php        ← 9-step end-to-end mock verification
│   ├── setup_mock_subscription.php
│   ├── teardown_mock.php
│   ├── test_audit_fixes.php    ← 28 audit-fix checks
│   ├── verify_install_cta.php  ← 7 CTA-injection checks (D.1.b)
│   └── vapid_keygen.php
├── db/
│   ├── access.php
│   ├── caches.php
│   ├── feature_flags.php       ← 5 flags incl sentientia.pwa.install.enabled
│   ├── install.xml             ← local_sentientia_push_subs + push_log tables
│   ├── services.php
│   ├── tasks.php
│   └── upgrade.php
├── lang/{en,hi}/
│   └── local_sentientia_pwa.php
├── amd/{src,build}/
│   ├── install_prompt.js[.min.js]      ← D.1.b
│   ├── ios_install_hint.js[.min.js]    ← D.1.c (guided modal)
│   └── subscribe.js[.min.js]            ← B.2.b
└── templates/
    ├── install_cta.mustache    ← D.1.b hidden-by-default banner
    ├── manifest.mustache        ← D.1.a Web App Manifest body
    └── subscribe_widget.mustache← B.2.b enable-notifications button
```

---

## Related ADRs

| ADR | Topic |
|---|---|
| ADR-001 | Fork strategy + product pivot |
| ADR-002 | Customer-level feature flags |
| ADR-003 | Hand-rolled Web Push crypto (no Composer) |
| ADR-005 | PWA install + native-wrapper decision |
| ADR-008 | Customer brand DB schema (Phase 2 forward-looking) |

---

## Self-test green status

```
$ php local/sentientia_pwa/cli/test_audit_fixes.php
Summary: 28 passed, 0 failed.

$ php local/sentientia_pwa/cli/verify_install_cta.php
Summary: 7 passed, 0 failed.

$ php local/sentientia_pwa/cli/run_push_e2e.php
9/9 steps PASS (mock mode)
```
