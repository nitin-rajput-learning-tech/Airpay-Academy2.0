# Mobile push notifications — Airpay Academy

**Phase 6 F.7 of ENTERPRISE-GRADE-PLAN.** Enables push notifications to
the Moodle mobile app for `airpay_*` events.

---

## 1. Current state (verified 2026-05-11 on production DB)

```
enablemobilewebservice = 1   ✓ web service ON
mobilenotifications   = OFF  ✗ — needs to be enabled
```

The Moodle mobile app is published in App Store + Play Store as the
"Moodle App", but to send pushes you need either:

1. **Moodle's own AirNotifier** (free, hosted by Moodle.com) — easiest
2. **Self-hosted AirNotifier** — for control over data
3. **OneSignal / Firebase Cloud Messaging** via custom plugin — most flexibility

The minimum-viable setup is option 1. This doc covers that path.

---

## 2. Step-by-step: enable Moodle.com AirNotifier

### 2a. Apply for an AirNotifier key

1. Visit `https://moodle.net/airnotifier`
2. Sign up with the Airpay Academy production admin email
3. Provide site URL: `https://www.airpay.academy`
4. Receive an API key + app secret via email (24-48h SLA)

### 2b. Configure Moodle

```
Site administration → Plugins → Message outputs → Mobile

  AirNotifier URL    : https://messages.moodle.net
  AirNotifier app    : commoodlemobile
  AirNotifier key    : (from email)
  Enable mobile      : ✓
```

Save.

### 2c. Enable mobile web services

```
Site administration → Mobile app → Mobile authentication

  Enable web services for mobile devices: ✓
  Set the device ID                     : (auto)
```

Save. (Already done on production — verified.)

### 2d. Test the round-trip

```
Site administration → Mobile app → Notifications → Send a test
```

Pick a user, send a test push, verify their phone receives it.

---

## 3. Wire airpay events to push

The default Moodle mobile message provider already pushes core events
(messages, forum, etc.). For our custom airpay events (`waitlist_promoted`,
`cart_order_paid`, `proctor_session_flagged`, `request_pending`,
`recompletion_due_soon`), we already declare them in each plugin's
`db/messages.php`.

For these to push, ensure `'popup'` and/or `'airnotifier'` is set as an
allowed default in each plugin's `messageproviders`. Today we use:

```php
'popup' => 1 + 8,   // PERMITTED | DEFAULT_LOGGEDIN
```

This routes to in-app notification + web push if airnotifier provider
is allowed. To explicitly enable airnotifier for a provider, add it
to the defaults map:

```php
'defaults' => [
    'email'       => 1 + 8 + 16,
    'popup'       => 1 + 8,
    'airnotifier' => 1 + 8 + 16,  // ← push for both logged-in + logged-off
],
```

Per-plugin opt-in. All `airpay_*` plugins now declare popup; opting in
to airnotifier is a 1-line change per provider.

---

## 4. Per-user controls

Users can opt in / out per channel + per event:

```
Preferences → Notification preferences
```

Push notifications respect the user's `mobilenotifications` flag.
Bypass-able by site admins via per-user override.

---

## 5. Testing checklist (production)

- [ ] Apply for AirNotifier key
- [ ] Enter key in `Plugins → Message outputs → Mobile`
- [ ] Verify `Notifications → Send a test` reaches a real device
- [ ] Sign in to Moodle mobile app on the test device
- [ ] Confirm the device appears under user's `user_devices` table
- [ ] Add `airnotifier` to `defaults` in 1-2 plugin messages.php files
- [ ] Trigger the event manually (or via cron-forced run)
- [ ] Verify the push arrives within 30s
- [ ] Verify quiet hours (per-user setting) suppress pushes correctly
- [ ] Verify revoking access (logout from device) removes user_devices row

---

## 6. Mobile app theming

Moodle mobile app uses the `core_styles` API. Branding can be done via:

```
Site administration → Mobile app → Mobile appearance

  Custom CSS         : (paste airpay tokens)
  Override the appearance of branding elements: ✓
  Custom language pack: (English overrides if needed)
```

Use the canonical Airpay tokens from `theme/airpayux/scss/moodle/_tokens.scss`:

```css
.ion-color-primary { --ion-color-base: #0066A7 !important; }
.ion-color-secondary { --ion-color-base: #0f7a73 !important; }
```

---

## 7. Privacy + GDPR

- Push tokens are stored in `mdl_user_devices` (managed by core)
- The `core_user` privacy provider includes `user_devices` in DSR exports
  and deletes on user-delete-on-DSR
- AirNotifier proxies through Apple APNs / Google FCM — both have
  their own DPAs. Airpay's tenant relationship is with Moodle.com (not
  Apple/Google directly).

---

## 8. Cost

- AirNotifier free tier: 100K pushes/month
- Airpay scale: 2,870 users × ~3 pushes/day = 258K/month
  → Likely need Moodle Premium tier (paid). Cost depends on agreement.
- Alternative: self-host AirNotifier — open-source, deploy to AWS for
  ~$10/month VPS + Apple/Google direct integration

---

## 9. Status

- **Code**: 0 lines required (Moodle + airpay_* plugins already declare
  the message providers)
- **Configuration**: ~30 minutes to enable, ~24-48h SLA for the key
- **Documentation**: This file
- **Optional enhancement** (~4h): add `'airnotifier' => ...` defaults to
  every plugin's `db/messages.php` for explicit push opt-in

Total: This is a **config task** — no code work in production. The
foundation (message providers in every plugin) was built incrementally
through Phase 1-5.
