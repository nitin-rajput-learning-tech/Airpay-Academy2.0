# State Card — sentientia_mobile (Capacitor Native Wrapper)

**Component:** sentientia_mobile — Capacitor 5.x native wrapper for Sentientia LMS  
**Status:** SCAFFOLD COMPLETE  
**Branch:** claude/gap-mobile  
**Session date:** 2026-06-16  
**ADR refs:** ADR-005 (Path B/C decision), ADR-003 (VAPID crypto), ADR-001 (fork strategy)  
**Gap analysis trigger:** GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md — Invince competitive gap P2.2 (native iOS/Android offline apps)

---

## What was scaffolded

All files under `moodle-enhancement/mobile/sentientia-app/`:

| File | Purpose |
|------|---------|
| `package.json` | npm manifest — @capacitor/core 5.x + all 9 Capacitor plugins |
| `capacitor.config.ts` | Production config — remote URL mode → `https://www.airpay.academy` |
| `capacitor.config.local.ts` | Local dev override — Android emulator `10.0.2.2:8080` / iOS localhost |
| `www/index.html` | Shell HTML for local bundle mode (not used in remote URL mode) |
| `src/push-bridge.ts` | Bridges Capacitor PushNotifications plugin to existing VAPID WS endpoint |
| `src/deep-link-handler.ts` | Routes `sentientia://` and HTTPS App Links to in-WebView navigation |
| `android/app/build.gradle` | Android Gradle template — signing via env vars / keystore.properties |
| `ios/App/Podfile` | iOS CocoaPods template — all Capacitor plugin pods |
| `.env.example` | All credential slots documented, no real values |
| `.gitignore` | Ignores node_modules, android/release, ios/Pods, keystores, .p8/.p12 |
| `scripts/sync-pwa.sh` | Local bundle mode helper — fetches manifest+SW from XAMPP via curl |
| `BUILD.md` | Full build + release guide (prerequisites → store submission) |

---

## How the wrapper reuses the existing PWA

**Architecture: Remote URL mode (default)**

The native wrapper is a Capacitor WebView shell that loads `https://www.airpay.academy`
directly. No separate build of Moodle JS/CSS is needed. Capacitor injects its
native bridge into the remote page so native plugins are accessible from Moodle
page JS.

**Reuse points:**

1. **Service worker (`sw.php`)** — runs inside the native WebView unchanged.
   Offline fallback, cache-first static assets, network-first HTML pages all
   remain active exactly as in the browser PWA.

2. **VAPID Web Push stack (ADR-003)** — the existing hand-rolled ES256/AES-GCM
   crypto (audited 2026-05-21) continues to serve web browser subscriptions.
   The `push-bridge.ts` extends this by also registering native FCM/APNs tokens
   via the same `local_sentientia_pwa_save_subscription` WS endpoint, using a
   `sentientia-native://` sentinel URL that the backend routes to FCM/APNs directly.

3. **Moodle session / authentication** — the WebView shares the session cookie
   with the remote Moodle instance. No separate auth flow is needed in the native
   wrapper. Moodle's login, sesskey CSRF, and capability checks all work normally.

4. **`local_sentientia_pwa_save_subscription` WS** — `push-bridge.ts` posts
   native tokens to this existing endpoint. A small backend extension is needed
   (see BUILD.md §5.3) to detect native token types and route to FCM/APNs.

5. **`notification_bridge.php`** — existing cron-triggered push sender. Extension
   needed to call FCM HTTP v1 / APNs HTTP/2 for `fcm_native` / `apns_native`
   token types (currently handles only Web Push endpoints).

6. **PWA manifest + icons** — not directly used by the native wrapper (the app
   has its own icon/splash configured in Xcode/Android Studio), but the manifest
   branding pipeline (`local_airpay_core::get_customer_branding()`) continues
   to serve the web PWA install flow in parallel.

---

## Build / release steps summary

1. `npm install` — install Capacitor 5.x and plugins
2. `npx cap add android` + `npx cap add ios` — generate native projects (once per machine)
3. `npx cap sync` — sync www/ and plugin bindings into native projects
4. Android: `./gradlew bundleRelease` → `.aab` for Play Store
5. iOS: Xcode → Product → Archive → Distribute → App Store Connect
6. See `BUILD.md` for full details on signing, push notification setup, deep links, and CI/CD

---

## Open items / next steps (before production)

### P0 — Required before any native build
- [ ] **Backend: extend `save_subscription.php`** to accept `sentientia-native://`
  endpoint scheme (allow in SSRF allowlist, add `token_type` field, skip VAPID key validation)
- [ ] **Backend: extend `push_sender.php`** to route `fcm_native` and `apns_native`
  token types to FCM HTTP v1 / APNs HTTP/2 APIs
- [ ] **DB migration:** add `token_type` column to `mdl_local_sentientia_pwa_push_subscriptions`
- [ ] **Firebase project:** create Firebase project, obtain `google-services.json` for Android
- [ ] **Apple Developer account:** register bundle ID `academy.airpay.sentientia`,
  enable Push Notifications, generate APNs auth key (.p8)
- [ ] **Store accounts:** Google Play Console + Apple App Store Connect apps created

### P1 — Required before store submission
- [ ] **App icons:** 1024x512 icon + adaptive icon for Android; 1024x1024 for iOS
  (replace placeholder SVG in `www/index.html`)
- [ ] **Splash screen:** branded splash for Android (drawable resources) + iOS (LaunchScreen.storyboard)
- [ ] **Deep link files:** AASA + assetlinks.json hosted at `www.airpay.academy/.well-known/`
- [ ] **Universal Links entitlement** in Xcode + Intent filters in AndroidManifest.xml
- [ ] **Privacy policy** URL confirmed (required by both stores)
- [ ] **Store listings** drafted (screenshots, descriptions, content ratings)

### P2 — Nice-to-have
- [ ] **GitHub Actions CI:** `mobile-build.yml` workflow (sketch in BUILD.md §9)
- [ ] **fastlane** integration for automated iOS uploads
- [ ] **Sentry / Crashlytics** error reporting for native crashes
- [ ] **Feature flag:** `sentientia.native_wrapper.push.enabled` to gate native
  push registration behind the admin feature flag system (ADR-002 pattern)
- [ ] **Native login screen (Swift/SwiftUI):** reduces App Store 4.2.1 rejection risk

### Parked / not in scope for scaffold
- Actual binary builds (require physical macOS + Apple Developer Program)
- FCM / APNs credential generation (external accounts)
- Play Store / App Store account creation

---

## Decision log

**Why Capacitor (not Cordova)?** ADR-005 §"Why Path C is deferred but not killed"
explicitly names Capacitor 5 as the preferred wrapper when Path C is triggered.
Capacitor has better TypeScript-first ergonomics, more active maintenance (Ionic),
and closer alignment with modern Web APIs than Cordova.

**Why remote URL mode (not local bundle)?** Instant updates without App Store
resubmission. The existing PWA service worker provides offline capability within
the session. Cold-start offline is not a current requirement for Airpay Academy.

**Why this is in `moodle-enhancement/mobile/` (not a sibling repo)?** ADR-005
§Decision point 4 planned a "sibling repo." On reflection, keeping the scaffold
in the monorepo makes it easier to track alongside the PWA plugin it wraps,
share the CI/CD secrets infrastructure, and coordinate version bumps. If the
native project grows large (multiple customers, per-customer flavors), it should
graduate to its own repo at that point.
