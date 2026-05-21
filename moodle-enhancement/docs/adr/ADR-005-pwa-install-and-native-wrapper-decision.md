# ADR-005 — PWA install flow + native-wrapper decision (Path B vs Path C)

**Status:** Accepted
**Date:** 2026-05-21
**Deciders:** Nitin Rajput, Claude
**Supersedes:** none (this is the formal write-up of the Path B / Path C choice
referenced in `ADR-001-fork-strategy-and-product-pivot.md` §"Mobile strategy")

---

## Context

Airpay Academy (Sentientia LMS customer-zero) needs a mobile-app-like experience
for 3,500+ learners — including 1,800 field-merchant-onboarding agents who
work primarily from Android phones. The decision is between:

- **Path B:** Progressive Web App — installable from the browser via
  `manifest.json` + service worker + push notifications. No app-store.
- **Path C:** Cordova / Capacitor wrapper — wraps the PWA in a native
  container, submitted to Apple App Store + Google Play Console.

The Phase B work shipped Path B's foundation:
- `sw.php` service worker scaffold (Phase B.1, commit `47df08ff1`)
- VAPID-keyed push subscription backend (Phase B.2, `bcec33d8b`)
- Hand-rolled ES256/AES-GCM crypto (Phase B.2.5, ADR-003) — security-audited
  2026-05-21, see `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`
- Wired into 4 reminder/overdue crons via `notification_bridge` (Phase B.3.a/b)
- Admin delivery-log viewer + iOS install UX hints (Phase B.3.c/d)

What is **not** yet built:
- Web App Manifest (`manifest.webmanifest`) — the PWA "install" gate
- `beforeinstallprompt` capture + custom "Install Sentientia LMS" CTA on
  dashboard
- iOS "Add to Home Screen" guided flow (iOS Safari does NOT fire
  `beforeinstallprompt`)
- Per-customer theming of the manifest (`name`, `theme_color`, `icons`) so
  hypothetical Enterprise N can ship "Acme LMS" not "Sentientia LMS"
- A PWA-only-vs-native-app capability gap inventory

This ADR records the decision now so the Phase D implementation can proceed
without rehashing it.

---

## Decision

**Pick Path B (PWA-only) for Airpay Academy customer-zero. Defer Path C
(native wrapper) until a concrete customer requests it.**

Specifically:
1. Ship Phase D.1 (PWA install UX) under feature flag `sentientia.pwa.install.enabled`
   — default OFF, flip ON per-customer when manifest + icons are ready
2. Build the Web App Manifest as a Mustache template (`manifest.mustache`)
   served via `manifest.php` so per-customer branding flows through
   `local_airpay_core::get_customer_branding()` (the same pipeline that
   feeds `core_renderer` per ADR-002)
3. Add a `Path-C-readiness checklist` to the plugin README so we know what
   blocks Capacitor wrapping when a future customer requests it
4. Do **not** vendor Cordova / Capacitor into the repo. Path C, when
   needed, will live in a sibling repo (`sentientia-native-wrapper`)
   that ingests the PWA and produces signed `.apk` + `.ipa` artifacts

---

## Why PWA-only is the right answer for customer-zero

### The capability gap is small and shrinking
Of the 30 device APIs a native-app would unlock, only 4 matter for an LMS:

| API                    | Native | PWA on Android | PWA on iOS 17+ |
|------------------------|--------|----------------|----------------|
| Push notifications     | Yes    | Yes (FCM)      | Yes (since iOS 16.4) |
| Camera (for profile pic)| Yes   | Yes (`<input type="file" accept="image/*" capture>`) | Yes |
| Offline + background sync| Yes  | Yes (service worker) | Partial (no Background Sync API on iOS) |
| Geolocation (for classroom attendance) | Yes | Yes | Yes |

The remaining 26 native-only APIs (Bluetooth, NFC, contacts, advanced
biometric auth, etc) don't appear in any L&D use case Airpay has identified.

### Distribution friction is the real win, not capabilities
Airpay's 1,800 field agents change phones frequently (every 12-18 months,
mostly entry-level Android). The install flow on a 4G connection on a
Redmi 8A:

- **Native**: Open Play Store → search "Airpay Academy" → tap Install →
  wait for download (45 MB minimum APK + base.apk delta) → wait for
  install → open → login. ~3-5 minutes on a flaky connection.
- **PWA**: Open the link an HR rep WhatsApped them → log in → tap the
  "Install Sentientia LMS" prompt → done. ~30 seconds. The "app" is a
  shell pointing at the production URL — no APK to download, no
  signature mismatch issues, no Play-Store policy reviews to clear.

For a captive enterprise audience that is told "this is your training app"
by their employer, the Play Store has no acquisition value. It's pure
friction.

### App-Store policy risk is asymmetric
Apple's App Store Review Guidelines 4.2.1 ("Minimum Functionality") have
historically rejected enterprise-LMS wrappers as "just a website in an
app". Getting a rejection mid-rollout would block the entire deploy.
Google Play is more forgiving but still requires Closed Testing tracks
to validate updates before production — adding 1-2 weeks to every
significant release. The PWA route updates instantly with a Moodle
deploy; no app-store gate.

### One codebase, both phones — and the customer-zero benefit
Airpay's L&D team (Nitin + 2 admins) cannot maintain a native iOS app,
a native Android app, AND the web app. The PWA collapses all three into
one Moodle deploy. When the design system updates (e.g. the airpayux v2
theme refresh planned for Q4 2026), every learner sees the new look the
next time they open the PWA — no submit-to-store, no waiting for the
30% of users who never update their apps.

For Sentientia LMS as a sellable product: every PWA customer adds
zero per-platform maintenance burden. A native wrapper would be
per-customer (Acme LMS APK, Beta Corp LMS IPA, etc) and quickly
becomes a maintenance trap.

### Push notification parity arrived in 2024
The historical reason to pick native was push: iOS Safari didn't support
Web Push until 16.4 (March 2023). As of 2026, both major platforms
support Web Push at parity with native. The B.2.5 crypto stack already
delivers messages successfully to APNs (via Apple's WPS bridge) and
FCM (Google's WPS bridge); see the e2e self-test results in
`docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`.

---

## Why Path C is deferred but not killed

Three scenarios where we'd revisit:

1. **A Sentientia LMS customer says "we need Play-Store presence as a
   trust signal."** Some industries (banking, healthcare) require
   employees to install corporate apps only from official stores due
   to MDM policies. If a future paying customer raises this, the
   Capacitor wrap is ~1-2 weeks of work (Cordova/Capacitor has had
   stable Moodle support since Capacitor 5).
2. **iOS Background Sync becomes a hard requirement.** If we ever
   need to enqueue learner actions offline (e.g. quiz answers on a
   plane) and reliably sync on reconnect, iOS PWA's lack of
   Background Sync API forces native. Today none of the planned
   features need this.
3. **Apple ships a "PWA-on-the-doorstep" deprecation** (unlikely but
   non-zero). They've signalled the opposite — full Web Push + push
   subscriptions landed in iOS 16.4 + 17 — but if Cupertino changes
   course, Path C becomes the fallback within a quarter.

If any of these triggers, we'll write **ADR-006** picking
Capacitor (more PWA-native than Cordova, Ionic-owned, active maintenance,
better TypeScript-first developer ergonomics).

---

## Phase D.1 implementation plan (in scope for next session)

### D.1.a — Web App Manifest
**File:** `local/sentientia_pwa/manifest.php` (PHP-served, sets `Content-Type:
application/manifest+json`)

**Template:** `local/sentientia_pwa/templates/manifest.mustache`

**Context variables** (resolved per-customer by `local_airpay_core`):
- `name` — "Airpay Academy" / "Acme LMS" / "Sentientia LMS" (default)
- `short_name` — "Academy" / "Acme" / "Sentientia"
- `theme_color` — `#0066A7` for Airpay; per-customer override
- `background_color` — `#F2F4FB` for Airpay; per-customer override
- `display` — `standalone`
- `start_url` — `/my/dashboard.php?utm_source=pwa_install`
- `scope` — `/`
- `icons[]` — `/local/airpay_core/pix/customers/{customer_id}/icon-{192,512}.png`

**Index hook:** `core_renderer::standard_head_html()` override to inject:
```html
<link rel="manifest" href="<?= $CFG->wwwroot ?>/local/sentientia_pwa/manifest.php">
<meta name="theme-color" content="<?= $brand['theme_color'] ?>">
<!-- iOS-specific (no manifest support pre-iOS 17): -->
<link rel="apple-touch-icon" href="<?= $brand['icon_192_url'] ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= $brand['short_name'] ?>">
```

### D.1.b — `beforeinstallprompt` capture + custom CTA
**File:** `local/sentientia_pwa/amd/src/install_prompt.js`

```javascript
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (ev) => {
    ev.preventDefault();      // we'll trigger it manually
    deferredPrompt = ev;
    document.querySelector('.sentientia-install-cta')?.removeAttribute('hidden');
});

document.querySelector('.sentientia-install-cta')?.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    // log choice.outcome ('accepted' | 'dismissed') for analytics
    deferredPrompt = null;
});
```

Mount the `.sentientia-install-cta` button on the dashboard at the top right,
hidden by default. Show only when `beforeinstallprompt` fires (= Android
Chrome / Edge / Samsung Internet that judges the site "installable" by
default heuristics: HTTPS + valid manifest + service worker + 30s
engagement).

### D.1.c — iOS "Add to Home Screen" guided flow
**File:** `local/sentientia_pwa/templates/ios_install_modal.mustache`

iOS Safari **does not** fire `beforeinstallprompt`. The user has to tap
Share → Add to Home Screen manually. Solution: detect iOS, show a one-shot
modal with screenshots of the steps. Already shipped as Phase B.3.d
(`docs/visual-evidence/2026-05-20/iOS-install-hint.png`); D.1.c upgrades
it to a guided flow with screenshots and dismiss-permanent storage so
returning users don't keep seeing it.

### D.1.d — Service Worker offline fallback
**File:** `local/sentientia_pwa/sw.php` (extend existing scaffold from B.1)

Cache strategy:
- **Cache-first** for static assets (CSS, JS, fonts, icons)
- **Network-first with cache fallback** for HTML pages
- **Network-only** for API endpoints + SSE streams (`/local/sentientia_live/stream.php`)
- **Offline fallback page** — `local/sentientia_pwa/offline.html` — minimal
  branded page saying "You're offline. Course content not available. Some
  features (push, completion sync) will resume when you reconnect."

### D.1.e — Capability inventory + Path-C readiness checklist
**File:** `local/sentientia_pwa/README.md` — append a section listing every
device API we currently use vs every native-only API we'd need if we wrap.
Used as a sanity-check when a future customer asks "what would Path C
unlock?"

---

## Per-customer branding flow (multi-customer architecture)

Per ADR-002 customer-level feature flags, each Sentientia customer ID resolves
to a branding bundle. The PWA manifest is the FIRST surface where this
matters end-to-end:

```
HTTP request to /local/sentientia_pwa/manifest.php
  → Resolve customer_id (from session OR host-header OR open_path)
  → local_airpay_core::get_customer_branding(customer_id)
     → returns ['name', 'short_name', 'theme_color', 'icon_192_url', ...]
  → Render manifest.mustache with that context
  → Stream as application/manifest+json
```

Today (single-tenant Airpay), customer_id is hardcoded to 1 → returns the
Airpay Academy branding. Tomorrow (Enterprise N) the resolver returns a
different bundle and the same `manifest.php` serves a different manifest.
**No code changes needed per customer** — that's the goal.

---

## Open questions (parked, not blocking D.1)

1. **Push notification fan-out on PWA vs native** — if a user installs the
   PWA on both their phone and laptop, they get the push twice. Need an
   `endpoint_dedupe` UX hint or a "only on one device" preference.
2. **Service Worker cache invalidation strategy** — Moodle's `jsrev` bumps
   per-deploy. Does our SW respect that, or do we ship stale assets after
   a deploy? (Answer: cache key includes jsrev, but verify on first
   real-customer ship.)
3. **`vapidPublicKey` exposure** — currently served via JS data attribute
   on the subscribe widget; should ideally be served via a public REST
   endpoint so the SW can refresh on key rotation without page reload.
   Non-blocking; ship D.1.a-e first, revisit on key rotation.

---

## Consequences

**Positive:**
- One codebase, all platforms — perpetual maintenance saving
- Instant updates — no app-store review queues
- Lower acquisition friction — captive enterprise audience never visits
  the Play Store anyway
- Per-customer branding flows through one Mustache template — fully
  productised
- Smaller security surface — no native binary signing, no Play-Console
  enrolment, no Apple Developer Program

**Negative:**
- No "app icon in the Play Store" trust signal for customers who require it
- iOS Background Sync gap — quiz-offline-then-sync flows can't be
  guaranteed
- Onboarding-team training: HR will explain "install from browser, not
  the app store" the first week — some learners will be confused
- Service worker debugging is harder than native crash logs (Chrome
  DevTools is our only window)

**Neutral:**
- Future Path C escape hatch stays open via the sibling-repo plan;
  no code lock-in
- Path D's Cordova/Capacitor ADR remains parked, not killed

---

## References

- [W3C Web App Manifest spec](https://www.w3.org/TR/appmanifest/)
- [Apple iOS PWA install Quirks Mode notes (Stoyan Stefanov)](https://www.netguru.com/blog/pwa-on-ios)
- [Capacitor 6 release notes](https://capacitorjs.com/docs/v6/changelog)
- [Apple Web Push announcement (WWDC 2023)](https://developer.apple.com/videos/play/wwdc2023/10120/)
- ADR-001 Mobile strategy section
- ADR-002 Customer-level feature flags
- ADR-003 Hand-rolled Web Push crypto
- B.2.5 Crypto audit, 2026-05-21
