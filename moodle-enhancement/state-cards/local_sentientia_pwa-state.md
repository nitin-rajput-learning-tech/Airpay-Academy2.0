# State Card — `local_sentientia_pwa` (Sentientia LMS PWA + Push)

**Component:** `local_sentientia_pwa`
**Version:** `2026052302` / `0.5.3-alpha`  — Phase B.3 hook migration
**Maturity:** `MATURITY_ALPHA` — crypto audit non-blocking sweep (NB #7-#15)
**Status:** Phase B.3 — service worker + manifest + Web Push pipeline (real VAPID); secured against crypto audit findings
**ADR:** ADR-005 (PWA install flow + native wrapper)
**Last refreshed:** 2026-05-24

---

## Mission

Wraps Airpay Academy / any Sentientia LMS customer deployment as a
Progressive Web App. Two halves:

1. **Install + offline shell** — Web manifest, service worker (cache-first
   for static assets, network-first for HTML), "Add to Home Screen" prompt,
   offline.html fallback. Native-wrapper compatibility for Phase D
   (Cordova/Capacitor wrappers eventually).
2. **Web Push** — VAPID-signed push notifications routed via a
   `notification_bridge` to every Moodle channel (course completion, exam
   reminder, classroom session, etc.). Per-user opt-in flow with
   `preferences.php`; per-tenant + per-customer flag gating.

## Architecture decision

- Self-hosted VAPID — no third-party push service (FCM/APNs proxied
  via this plugin's own keypair).
- Payload encryption via JWE (RFC 7516) — see `payload_encrypter.php`.
- Hook migration to Moodle 5.x `\core\hook\*` API (Phase B.3).

## Database schema (2 tables)

| Table | Purpose |
|-------|---------|
| `local_sentientia_push_subs` | One row per (user, browser) push subscription. Endpoint + keys (auth, p256dh) stored encrypted at rest. |
| `local_sentientia_push_log` | Append-only audit of every push dispatched. Tenant-scoped via subscription's user → open_path resolution. |

## Capabilities (2)

| Capability | Purpose |
|------------|---------|
| `local/sentientia_pwa:subscribe` | Per-user — opt in via preferences page |
| `local/sentientia_pwa:manage` | Admin — view push log, regenerate VAPID, prune stale subs |

## Feature flags (5)

| Flag | Default | Purpose |
|------|---------|---------|
| `sentientia.pwa.enabled` | OFF | Master switch — manifest + service worker registration |
| `sentientia.pwa.install.enabled` | OFF | "Add to Home Screen" install prompt |
| `sentientia.pwa.push.enabled` | OFF | Web Push master switch |
| `sentientia.pwa.push.reminders` | OFF | Sub-channel: course-incomplete reminders |
| `sentientia.pwa.push.overdue` | OFF | Sub-channel: manager overdue alerts |

Sub-channels coordinate with `engagement.whatsapp.reminders` /
`engagement.whatsapp.overdue` (Phase C.1) — each channel independently
flag-able so admins can roll out one without the other.

## Key files

```
local/sentientia_pwa/
├── version.php                                  2026052302 / 0.5.3-alpha
├── README.md
├── lib.php
├── settings.php                                  Admin settings
├── manifest.php                                  Web app manifest endpoint
├── sw.php                                        Service worker (PHP-served JS)
├── register.js                                   Browser-side SW registrar
├── offline.html                                  Offline fallback page
├── preferences.php                               Per-user push opt-in UI
├── dismiss_install.php                           "Don't show install prompt" toggle
├── mock_receiver.php                             Dev: pretend-receiver for push tests
├── admin/                                        Admin UI surfaces
├── cli/                                          Operations + diagnostics
├── classes/
│   ├── subscription_manager.php                  Subscription CRUD
│   ├── push_sender.php                           Dispatch entry point
│   ├── push_logger.php                           Log writer
│   ├── notification_bridge.php                   Hooks Moodle message_send → push
│   ├── payload_encrypter.php                     JWE encryption
│   ├── vapid_key_manager.php                     VAPID keypair lifecycle
│   ├── jwt_signer.php                            JWT signer used by VAPID
│   ├── hook_callbacks.php                        Moodle 5.x hook callbacks
│   ├── external/                                 WS endpoints (subscribe/unsubscribe)
│   ├── output/                                   Renderer
│   └── task/                                     Scheduled tasks
├── db/
│   ├── install.xml                               2 tables
│   ├── upgrade.php
│   ├── access.php                                2 capabilities
│   ├── feature_flags.php                         5 flags
│   ├── hooks.php                                 hook registrations
│   ├── services.php                              WS function registrations
│   └── tasks.php                                 Scheduled task registry
├── templates/
├── amd/
├── lang/
│   ├── en/local_sentientia_pwa.php
│   └── hi/local_sentientia_pwa.php               (100% parity)
└── tests/
    ├── payload_encrypter_test.php                5 methods (JWE round-trip)
    ├── tenant_isolation_test.php                 13 methods (per-tenant subscription scoping)
    └── audit_fixes_test.php                      16 methods (crypto-audit non-blocking sweep, NB #7-#15)
```

## Tests

3 PHPUnit classes, 34 methods. `audit_fixes_test.php` exercises the
crypto audit non-blocking sweep findings (NB #7 through NB #15) —
sanity-checking encoding rules, key derivation, and IV reuse paths.

## Open items / next phase

- [ ] Phase B.4 — analytics dashboard (delivery success rate, bounce
      rate per browser, average notification → click latency)
- [ ] Phase B.5 — cohort targeting (push to a slice of users, not
      every subscriber)
- [ ] Phase C — Native wrapper handshake (Cordova/Capacitor)
- [ ] Phase D — Customer-brand push payload variations (per-customer
      icon, accent colour in the notification body)
- [ ] Quiet hours per user (currently service-worker default applies
      to everyone)

## State card created — 2026-05-24

Initial state card. Plugin shipped in Phase B but had no state card
through Phase B.0 / B.1 / B.2; created now as part of the P1
state-card pass after the merge wave.
