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


## 2026-09-22 - The E2E harness called a namespace that does not exist (W1-11)

`cli/run_push_e2e.php` used `\local_airpay_core\feature_flags`. That namespace was renamed by
ADR-022/025 and is **declared nowhere in the repo**, so the harness fatal'd on its first flag line.
The real class is `\local_sentientia_platform\feature_flags`, and its signature already matched
every call site - this was a pure rename miss, not a design change.

**Root cause: `CLAUDE.md` section 5 documented the dead namespace as the canonical feature-flag
pattern.** Anything written from the project instructions inherited the bug. Both the harnesses and
the doc are corrected in the same change, or it recurs on the next plugin.

Found by the 2026-09-22 confidence audit. Both trees now agree on this file; the reconciliation is
recorded as two entries drained from `tools/tree-drift-baseline.txt`.

## 2026-09-25 - ADR-031 tenant scope (0.6.0-alpha, 2026092500)

Sweep hit 59 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25). `local/sentientia_pwa:manage` no longer
defaults to the manager archetype, and upgrade step 2026092500 revokes every existing grant: the
push log is a platform-operations view, and every tenant admin could page through every tenant's
push recipients or type any user id into its filter. As defence in depth `push_logger::recent()`,
`count()` and `stats_last_24h()` confine whoever holds `:manage` to the recipients in their own
tenant (1=1 cross-tenant, 1=0 with no tenant), and `recent()` no longer selects the unused
`u.email`. Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 follow-up: release note (no code change)

This note comes from the review of wave 1 (branch `claude/adr031-comms-ff`).

**Operational, for Nitin.** The pwa upgrade 2026092500 revokes `local/sentientia_pwa:manage` from
every role at system context, and so does the notifications upgrade 2026092500. After deploy,
tenant admins lose the push log. If a platform-operations role needs the push log, re-grant
`:manage` to that role after the upgrade. The same holds for notification rule management, which
also needs `local/sentientia_platform:crosstenant` to write. For the details, see
`sentientia_notifications-state.md`.
