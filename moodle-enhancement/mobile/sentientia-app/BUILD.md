# BUILD.md — Sentientia LMS Native Wrapper Build & Release Guide

**Project:** Capacitor native wrapper for Sentientia LMS  
**Wraps:** `local_sentientia_pwa` PWA (at `moodle-enhancement/local/sentientia_pwa/`)  
**ADR:** ADR-005 (Path B/C decision), ADR-003 (Web Push crypto)  
**Status:** Scaffold — no actual binary has been built yet. This guide covers the full path to store submission.

---

## 1. Prerequisites

### Required tools (all machines)
| Tool | Minimum version | Install |
|------|----------------|---------|
| Node.js | 18.0.0+ | https://nodejs.org (LTS) |
| npm | 9.0.0+ | bundled with Node |
| Capacitor CLI | 5.x | installed via `npm install` in this dir |

### Android builds (Windows / Linux / macOS)
| Tool | Version | Notes |
|------|---------|-------|
| Android Studio | Hedgehog (2023.1.1) + | Includes SDK, AVD Manager |
| Android SDK | API 34 (Android 14) | Install via SDK Manager |
| JDK | 17 | Android Studio bundles one |

### iOS builds (macOS only)
| Tool | Version | Notes |
|------|---------|-------|
| macOS | Ventura (13.0)+ | Xcode requirement |
| Xcode | 15.0+ | From Mac App Store |
| CocoaPods | 1.13.0+ | `sudo gem install cocoapods` |
| Apple Developer Program | Active membership | Required for device testing + store submission |

---

## 2. First-Time Setup

```bash
# From this directory (moodle-enhancement/mobile/sentientia-app/)

# Install npm dependencies
npm install

# Add native platforms (run once per developer machine)
npx cap add android
npx cap add ios        # macOS only

# Sync www/ and plugins into native projects
npx cap sync
```

After `npx cap add android` you will have an `android/` directory with a full
Gradle project. After `npx cap add ios` you will have an `ios/` directory with
an Xcode project. These directories are gitignored (generated per-machine by
Capacitor) except for the template files checked into this scaffold
(`android/app/build.gradle` and `ios/App/Podfile`).

---

## 3. Remote URL Mode vs Local Bundle Mode

### Remote URL Mode (production default)

**How it works:** The native app shell loads `https://www.airpay.academy`
directly into a native WebView. Capacitor injects its JS bridge into the
remote page, making native plugins (push notifications, status bar, splash
screen) available to the Moodle page JS.

**Configuration:** `capacitor.config.ts` has `server.url: 'https://www.airpay.academy'`.

**Advantages:**
- App updates deploy instantly with Moodle — no App Store resubmission
- No separate front-end build step in this repo
- The live PWA manifest, service worker, and VAPID push stack are used as-is
- Mirrors the PWA-on-device experience exactly

**Disadvantages:**
- Device must be online to load the app (no cold-start offline support)
- Remote URL apps may face App Store Review Guideline 4.2.1 scrutiny
  ("Minimum Functionality") — the reviewer must perceive the native wrapper
  as adding value beyond a web bookmark. The native push notification and
  deep linking integration provides this justification.

**When to use:** Always, for production.

### Local Bundle Mode

**How it works:** The `www/` directory is bundled into the APK/IPA. The app
loads from the local bundle first, then optionally updates from the network.

**Configuration:** Remove `server.url` from `capacitor.config.ts` (or use
`capacitor.config.local.ts`). Run `scripts/sync-pwa.sh` to populate `www/`.

**Advantages:**
- Cold-start offline support (the app shell loads without network)
- Immune to App Store 4.2.1 concerns (the app is fully self-contained)

**Disadvantages:**
- Content updates require building and submitting a new app binary
- Requires maintaining a build pipeline for the www/ content
- Moodle's dynamic PHP rendering cannot be bundled — only static output

**When to use:** If a future customer requires guaranteed offline launch or
Play Store presence without 4.2.1 risk. Not the current plan for Airpay Academy.

---

## 4. How the Wrapper Reuses the Existing PWA

The Sentientia LMS native wrapper is a thin shell around the existing
`local_sentientia_pwa` plugin. Nothing in the PWA plugin needs to change
for remote URL mode — the wrapper simply loads the same URL that a browser
would load, but in a native WebView with Capacitor plugins injected.

| PWA asset | How the wrapper uses it |
|-----------|------------------------|
| `manifest.php` | Not used directly — the native app has its own app icon + splash configured in Xcode/Android Studio. The manifest is used by the PWA install flow (browser context) not by the native wrapper. |
| `sw.php` | The service worker runs inside the native WebView. Cache behaviour, offline fallback (`offline.html`), and network strategies are all active. |
| `local_sentientia_pwa_save_subscription` WS | Used by `push-bridge.ts` to register native FCM/APNs tokens with Moodle. The endpoint currently accepts VAPID Web Push subscriptions; a small backend extension is needed to also accept native tokens (see §Push Notification Setup). |
| `notification_bridge.php` | The existing cron-triggered push sending logic. For native tokens, the bridge needs to call FCM HTTP v1 / APNs HTTP/2 instead of the VAPID Web Push endpoint. |
| VAPID crypto stack (ADR-003) | Used as-is for web-browser push subscriptions. Native token delivery bypasses VAPID (FCM/APNs handle encryption end-to-end). |
| `offline.html` | Bundled into the native WebView as a fallback. The service worker's `catch` handler serves this when the network is unavailable. |

---

## 5. Push Notification Setup

### 5.1 Android (FCM)

**Step 1: Create a Firebase project**
1. Go to https://console.firebase.google.com
2. Create a project (or use an existing one) for Sentientia LMS
3. Add an Android app with package name `academy.airpay.sentientia`
4. Download `google-services.json`

**Step 2: Place `google-services.json`**
```
android/app/google-services.json
```
This file is gitignored. Each developer and the CI/CD system must have their own copy.
For CI/CD: store the file content as a GitHub Actions secret and write it during the build.

**Step 3: FCM → VAPID bridge (backend)**
The `local_sentientia_pwa` backend currently sends push via the VAPID Web Push
endpoint (RFC-8291). For native Android tokens, the backend needs to call the
FCM HTTP v1 API instead. Required changes:

- Add a `token_type` column to `mdl_local_sentientia_pwa_push_subscriptions`
  (values: `web_push` | `fcm_native` | `apns_native`)
- Extend `push_sender.php` to detect the token type and route accordingly:
  - `web_push` → existing VAPID path (unchanged)
  - `fcm_native` → FCM HTTP v1 API (`POST https://fcm.googleapis.com/v1/projects/{project}/messages:send`)
  - `apns_native` → APNs HTTP/2 (`POST https://api.push.apple.com/3/device/{token}`)
- The FCM server key (from Firebase Console → Project Settings → Cloud Messaging)
  must be stored in Moodle's plugin config (not in code). Access with:
  `get_config('local_sentientia_pwa', 'fcm_server_key')`

**Step 4: Test push on Android emulator**
```bash
npx cap open android
# In Android Studio: Run → Run 'app' → select AVD
# Trigger a test push from: php local/sentientia_pwa/cli/test_push.php
```

### 5.2 iOS (APNs)

**Step 1: Apple Developer setup**
1. Log in to https://developer.apple.com
2. Register an App ID with bundle ID `academy.airpay.sentientia`
3. Enable **Push Notifications** capability on the App ID
4. Create an **APNs Auth Key** (.p8) — one key works for all apps in your team
5. Note the Key ID (10-char) and Team ID (10-char)

**Step 2: Store credentials**
- APNs .p8 key: store path in `.env` → `APPLE_APNS_KEY_PATH`
- For CI/CD: store base64-encoded key content as a GitHub Actions secret
- NEVER commit the .p8 file to git

**Step 3: Add Push Notifications capability in Xcode**
```
Xcode → open ios/App/App.xcworkspace
Target: App → Signing & Capabilities → + Capability → Push Notifications
```

**Step 4: APNs → Moodle bridge (backend)**
Same as FCM above — extend `push_sender.php` to call the APNs HTTP/2 API
for `apns_native` token types. Use the APNs .p8 key + Key ID + Team ID.
Store credentials via Moodle admin settings (never in code).

### 5.3 How native tokens integrate with `save_subscription`

`src/push-bridge.ts` posts the FCM/APNs token to the existing
`local_sentientia_pwa_save_subscription` Moodle WS endpoint using a sentinel
endpoint URL: `sentientia-native://push/{platform}/{token}`.

The backend `save_subscription.php` must be extended to:
1. Detect the `sentientia-native://` scheme in the `endpoint` parameter
2. Parse the platform and token from the URL
3. Store with `token_type = fcm_native` or `apns_native`
4. Skip the VAPID key validation (p256dh / auth will be empty strings)

The endpoint allowlist in `save_subscription.php` (the SSRF defence from
the B25 crypto audit) must be extended to also allow the `sentientia-native://`
scheme — but ONLY for this specific prefix, not arbitrary custom schemes.

---

## 6. Deep Link Configuration

### 6.1 iOS — Universal Links

Universal Links allow `https://www.airpay.academy/...` URLs to open in the app.

**Step 1: AASA file**
Host at `https://www.airpay.academy/.well-known/apple-app-site-association`:
```json
{
  "applinks": {
    "apps": [],
    "details": [{
      "appID": "XXXXXXXXXX.academy.airpay.sentientia",
      "paths": [
        "/course/view.php",
        "/my/dashboard.php",
        "/local/sentientia_*"
      ]
    }]
  }
}
```
Replace `XXXXXXXXXX` with your Apple Team ID.

**Step 2: Entitlement**
In Xcode: Target → Signing & Capabilities → + Capability → Associated Domains
Add: `applinks:www.airpay.academy`

This writes to `ios/App/App/App.entitlements`:
```xml
<key>com.apple.developer.associated-domains</key>
<array>
  <string>applinks:www.airpay.academy</string>
</array>
```

### 6.2 Android — App Links

**Step 1: assetlinks.json**
Host at `https://www.airpay.academy/.well-known/assetlinks.json`:
```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "academy.airpay.sentientia",
    "sha256_cert_fingerprints": ["<SHA-256 of your release signing certificate>"]
  }
}]
```
Get the fingerprint: `keytool -list -v -keystore sentientia-release.jks -alias sentientia-key`

**Step 2: Intent filter in AndroidManifest.xml**
```xml
<intent-filter android:autoVerify="true">
  <action android:name="android.intent.action.VIEW" />
  <category android:name="android.intent.category.DEFAULT" />
  <category android:name="android.intent.category.BROWSABLE" />
  <data android:scheme="https"
        android:host="www.airpay.academy"
        android:pathPrefix="/course/view.php" />
</intent-filter>
```

### 6.3 Custom URL scheme (sentientia://)

Android `AndroidManifest.xml`:
```xml
<intent-filter>
  <action android:name="android.intent.action.VIEW" />
  <category android:name="android.intent.category.DEFAULT" />
  <category android:name="android.intent.category.BROWSABLE" />
  <data android:scheme="sentientia" />
</intent-filter>
```

iOS `Info.plist`:
```xml
<key>CFBundleURLTypes</key>
<array>
  <dict>
    <key>CFBundleURLSchemes</key>
    <array><string>sentientia</string></array>
    <key>CFBundleURLName</key>
    <string>academy.airpay.sentientia</string>
  </dict>
</array>
```

---

## 7. Building for Release

### 7.1 Android — Signed AAB (Play Store)

```bash
# 1. Ensure keystore is configured (see §Android Signing in .env.example)
# 2. Copy capacitor.config.ts (production — remote URL mode)
# 3. Sync latest changes
npx cap sync

# 4. Open Android Studio and build a signed bundle
npx cap open android
# Android Studio: Build → Generate Signed Bundle/APK → Android App Bundle → Release
# OR from CLI:
cd android
./gradlew bundleRelease
# Output: android/app/build/outputs/bundle/release/app-release.aab
```

### 7.2 iOS — Archive + Export (App Store)

```bash
# 1. Ensure Apple signing is configured in Xcode
# 2. Sync latest changes
npx cap sync

# 3. Open Xcode
npx cap open ios

# 4. In Xcode:
#    - Select "Any iOS Device (arm64)" as the build target
#    - Product → Archive
#    - Window → Organizer → Distribute App → App Store Connect → Upload
```

---

## 8. Store Submission

### 8.1 Google Play Console

1. Create a new app in Play Console (https://play.google.com/console)
2. Complete the store listing:
   - App name: "Airpay Academy"
   - Short description (80 chars max)
   - Full description (4000 chars max)
   - Screenshots (phone + tablet, multiple aspect ratios)
   - Feature graphic (1024x500)
   - Icon (512x512, PNG, no alpha)
3. Content rating questionnaire (Learning/Education category)
4. Privacy policy URL (required): link to Airpay's privacy policy
5. Upload the `.aab` file to the Internal Testing track first
6. Test with internal testers (5+ devices, Android 7.0 through 14)
7. Promote to Production when testing passes
8. **Review time:** typically 1-3 business days for new apps

**Play Store policy note (Guideline 4.2.1 equivalent — "Thin Apps"):**
Google Play is generally permissive toward enterprise apps. Ensure the listing
clearly describes the push notification and deep-link features as the
native-only value-add over the web version.

### 8.2 Apple App Store Connect

1. Create an App Record in App Store Connect (https://appstoreconnect.apple.com)
2. App information: bundle ID `academy.airpay.sentientia`, SKU, category (Education)
3. Store listing:
   - Name: "Airpay Academy" (30 chars max)
   - Subtitle (30 chars max)
   - Description (4000 chars max)
   - Keywords (100 chars)
   - Support URL + marketing URL
4. Screenshots for all required device sizes (6.5" and 5.5" at minimum)
5. Privacy policy URL (required)
6. Upload build via Xcode Organizer or Transporter
7. Submit for review

**App Store Review Guideline 4.2.1 risk:**
Apple reviewers may question a web wrapper. Mitigation:
- Describe push notifications and deep-link routing prominently in the app description
- Ensure the app works correctly offline (service worker offline.html shows)
- Consider adding at least one native UI screen (e.g. a login screen built in Swift/SwiftUI
  that precedes the WebView — this clearly differentiates from "just a website")
- **Review time:** 1-3 business days standard; can request expedited review

---

## 9. Secrets Management

### Local development
1. Copy `.env.example` to `.env` and fill in values
2. For Android signing: create `android/keystore.properties`
3. For FCM: place `google-services.json` at `android/app/`
4. For iOS APNs: store `.p8` file at path referenced in `.env`

### CI/CD (GitHub Actions)
Set the following as repository secrets (Settings → Secrets → Actions):
- `ANDROID_KEYSTORE_BASE64` — base64-encoded .jks file content
- `ANDROID_KEY_ALIAS`
- `ANDROID_STORE_PASSWORD`
- `ANDROID_KEY_PASSWORD`
- `GOOGLE_SERVICES_JSON` — base64-encoded google-services.json content
- `APPLE_TEAM_ID`
- `APPLE_BUNDLE_ID`
- `APPLE_APNS_KEY_ID`
- `APPLE_APNS_KEY_BASE64` — base64-encoded .p8 file content
- `APPLE_ASC_KEY_ID`
- `APPLE_ASC_ISSUER_ID`
- `APPLE_ASC_KEY_BASE64` — base64-encoded App Store Connect API key

### GitHub Actions workflow sketch

```yaml
# .github/workflows/mobile-build.yml (sketch — not yet implemented)
name: Mobile Build

on:
  push:
    paths:
      - 'moodle-enhancement/mobile/sentientia-app/**'
    branches: [production]

jobs:
  android:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '18' }
      - run: npm install
        working-directory: moodle-enhancement/mobile/sentientia-app
      - name: Write google-services.json
        run: echo "${{ secrets.GOOGLE_SERVICES_JSON }}" | base64 -d > android/app/google-services.json
        working-directory: moodle-enhancement/mobile/sentientia-app
      - name: Write keystore
        run: echo "${{ secrets.ANDROID_KEYSTORE_BASE64 }}" | base64 -d > sentientia-release.jks
        working-directory: moodle-enhancement/mobile/sentientia-app/android
      - run: npx cap sync
        working-directory: moodle-enhancement/mobile/sentientia-app
      - run: ./gradlew bundleRelease
        working-directory: moodle-enhancement/mobile/sentientia-app/android
        env:
          ANDROID_KEYSTORE_PATH: sentientia-release.jks
          ANDROID_KEY_ALIAS: ${{ secrets.ANDROID_KEY_ALIAS }}
          ANDROID_STORE_PASSWORD: ${{ secrets.ANDROID_STORE_PASSWORD }}
          ANDROID_KEY_PASSWORD: ${{ secrets.ANDROID_KEY_PASSWORD }}

  ios:
    runs-on: macos-14  # Xcode 15 on apple silicon
    steps:
      - uses: actions/checkout@v4
      # ... (fastlane or xcodebuild steps)
      # iOS CI requires macOS runner — significantly higher cost than Linux
      # Consider manual Xcode uploads for initial submissions
```

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Push not received on Android emulator | AVD does not support FCM | Use a physical device or a Google-Play-enabled AVD |
| `pod install` fails | Outdated CocoaPods specs | Run `pod repo update` then `pod install` |
| White screen on launch | Remote URL unreachable from emulator | Check `server.url` — Android emulator uses `10.0.2.2` not `localhost` |
| "ITMS-90078: Missing Push Notification Entitlement" | Push capability not added in Xcode | Add Push Notifications capability in Signing & Capabilities |
| App rejected (Guideline 4.2.1) | Reviewer saw "just a website" | Add native login screen or other native UI before the WebView |
| Gradle build fails: "keystore not found" | keystore.properties missing or env vars not set | Create `android/keystore.properties` or set env vars |
