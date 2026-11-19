# Mobile app: competitor benchmark, architecture and release before cutover

**For:** Nitin (Head of L&D, product owner) and the Airpay CTO | **Date:** 2026-11-19 | **Status:** Capacitor recommendation
superseded by Nitin's choice (15:05: native-class stack, KMP or else Flutter); updated recommendation **Flutter**, behind a
3-day go/no-go spike (section 4A), awaiting Nitin's confirmation
**Decisions covered:** D28 (architecture), D30 (store release before cutover), D31 (we build it: team and tooling).
Payments (D29) and the store paperwork (D27) are in `2026-11-19-APP-STORE-PAPERWORK-PACK.md`. The revised schedule is in
section 5 of `2026-11-19-POST-DEMO-PLAN.md`.

**How this was prepared.** Web research on 19 Nov 2026, plus read-only counts from the local test import of the April
production backup. No code was changed. The corporate network rejected several official pages (LinkedIn help, Udemy
support, Coursera blog) with certificate errors; facts taken from search summaries are marked *(search summary)* and must
be re-checked before session M1. "Not found" means this research did not find it, not that the feature is absent. Every
effort figure is an estimate, not a measurement. Play Store figures come from chrome-stats.com as of 19 Nov 2026.

---

## 0. Bottom line

1. **Offline SCORM is the main job of the app.** All 851 of our SCORM activities are SCORM 1.2, averaging 26.9 MB, the
   largest 387.7 MB. There is no H5P. 223 of 235 quizzes have a time limit. Every framework, including the "fully native"
   ones, has to play SCORM inside a WebView (an embedded browser view), so a native UI framework does not make the hard
   part easier.
2. **The best-rated learning apps win on reliability, not feature count.** LinkedIn Learning (4.78), Udemy (4.37) and
   Coursera (4.28) all offer downloads that play only inside the app, a Wi-Fi-only switch and reminders. The complaints
   are about sign-ins, failing video and unreliable downloads.
3. **Superseded 19 Nov 15:05:** Nitin chose a native-class stack (KMP or else Flutter); Capacitor was not chosen. The
   updated recommendation is **Flutter**, conditional on a 3-day spike, with Capacitor and then a Moodle-app fork as
   fallbacks (section 4A). The original recommendation and its reasoning are kept below.
   **Original recommendation (D28): our own app on Capacitor 8 with Ionic React, screens bundled in the app** (not a wrapped
   website). Offline SCORM 1.2 is in v1 because you said offline is a must. Start with a **3-day go/no-go spike** on a
   2 GB Android 9 phone with our largest package. If the spike fails, fall back to a fork of the official Moodle app,
   which already plays SCORM 1.2 offline.
4. **Correction:** the official Moodle app is licensed **Apache-2.0, not GPL** (Moodle LMS itself is GPL). We may study
   and port its code with attribution.
5. **Recommendation (D30): get store approval before cutover, release on cutover day.** Do not release against live
   BizLMS (Moodle 4.1.2, out of security support, never patched) or UAT (test import of real data). Beta testers use
   TestFlight and Google Play closed testing against UAT with test accounts only.
6. **Effort:** the mobile track is now about **59 to 79 developer days from today** (was 45 to 55), mainly because
   offline moves into v1 (12 to 18 days) and the spike is added (3 days).

---

## 1. Our content, users and devices

| Fact | Value | What it means for the app |
|---|---|---|
| Activity mix | SCORM 851, forum 383 (mostly course announcement forums, probably), quiz 235, file 50, URL 28, feedback 4, page 1, assignment 1, **H5P 0** | The app is a SCORM player first |
| SCORM versions | 851 of 851 are SCORM 1.2; none opens in a pop-up | Good: SCORM 1.2 is the version the Moodle app can play offline. SCORM 2004 is online-only even there |
| SCORM package size | Average 26.9 MB, largest 387.7 MB. 504 are 2-20 MB, 344 over 20 MB, 24 over 100 MB | Streaming on 2G/3G will be poor. Download on Wi-Fi, play offline. Needs storage checks and, later, a lighter mobile version of each package |
| Quizzes | 223 of 235 timed; all deferred feedback | Moodle's offline quiz mode requires no time limit, so nearly all our quizzes stay online-only |
| Mobile use today | 0 rows in `mdl_user_devices`; 1 token for the Moodle mobile service (April import) | Learners use the mobile web today; nobody depends on the official Moodle app |
| Languages | 47 plugins carry en, hi, sw, kn and mr; the site has hi, kn, mr, sw packs | App UI needs en, hi and sw at launch |
| Users (ADR-005) | About 1,800 field merchant-onboarding agents, mainly on Android phones | Basic Android phones are the main design target |
| Existing scaffold | `moodle-enhancement/mobile/sentientia-app`: Capacitor ^5.7.0, targetSdk 34, remote-URL mode pointed at `https://www.airpay.academy`, never built | Rebuild, do not reuse. Capacitor 5 is out of support and Play now requires target API 36 |

Package sizes come from `mdl_files` metadata; the local file store is empty, so the files themselves were not opened.

---

## 2. Competitor benchmark

### 2.1 Store facts (Android unless marked)

| App | Rating (number of ratings) | Downloads | App size | Last update | Minimum OS |
|---|---|---|---|---|---|
| LinkedIn Learning | 4.78 (186,645) | 18.35 M | 87.5 MB | 6 Nov 2026 | Not found |
| Coursera | 4.28 (308,680) | 32.84 M | 57.7 MB | 4 Nov 2026 | Android 9 for 5.x builds (third party); current not found |
| Udemy | 4.37 (514,568) | 43.66 M | 51.2 MB | 16 Nov 2026 | Android 6 for older 9.x builds (APKMirror); current not found |
| Disprz | 4.06 (10,020); **iOS 3.0 (231)** | 3.0 M | 82.5 MB; iOS 123.5 MB | 3 Oct 2026 | iOS 16 |
| Moodle app (baseline) | 4.50 (215,351) | 52.1 M | **22.9 MB** | 10 Jul 2026 | **Android 7, iOS 15** (official FAQ) |
| Docebo Go.Learn, Degreed | Not found | Not found | Not found | Not found | Not found |

### 2.2 Learner features

| Feature | LinkedIn Learning | Coursera | Udemy | Disprz | Moodle app |
|---|---|---|---|---|---|
| Downloads / offline | Courses, mobile only *(search summary)* | Videos and readings; Wi-Fi-only; auto-delete | Course, section or lecture; encrypted, plays only in the app; Wi-Fi-only | Claimed (vendor blog) | Item, section or course; SCORM 1.2 and H5P offline with sync |
| Playback speed | 0.5x-2x | Yes | Yes, remembered | Not found | Depends on content |
| Low-bandwidth video | Audio-only mode (university guide) | Several renditions incl. audio-only (2015) | Auto quality + separate download quality | Not found | Depends on content |
| Notes / bookmarks | Not found | Notes tab | Notes and bookmarks | Not found | Not found |
| Quizzes on mobile | Not found | Yes; offline submit (2017) | Practice tests not downloadable | Multilingual assessments (vendor) | Offline only if untimed |
| Goals, streaks, reminders | Weekly goals + reminders | Reminders, goals | Reminders; streaks (Udemy Business) | Leaderboards, badges | Deadline reminders |
| Dark mode | Not found | iOS since 2019 | Yes | Not found | Yes |
| Accessibility | Captions, transcripts | Transcripts | Auto captions (accuracy complaints) | Not found | Not found |

### 2.3 How each app does offline

| App | What downloads | Protection | Storage controls | Sync |
|---|---|---|---|---|
| LinkedIn Learning | Course videos | Plays only in the app; app login lasts 30 days | Not found | Not found |
| Coursera | Videos, readings, quizzes (2017) | Locked to the app (unofficial) | Wi-Fi-only, auto-delete | Quiz answers on reconnect |
| Udemy | Lectures and resources | Encrypted, plays only in the app, lost on reinstall | Wi-Fi-only, quality setting, per-course delete | Not found |
| Docebo Go.Learn | Single-SCO self-contained SCORM, video, files | Not found | Offline courses and assets pages | Progress pushed on reconnect; **no SSO login while offline** |
| Moodle app | All resources except URLs; SCORM 1.2, H5P, untimed quizzes | App storage; admin can block downloads | Warns above 20 MB on Wi-Fi or 2 MB on mobile data | Auto-sync about every 10 minutes; **offline actions get the sync time, not the time they happened**; iOS syncs only in the foreground |

### 2.4 Basic phones and poor networks

| App | Download size | Low-bandwidth features | Evidence on basic phones |
|---|---|---|---|
| LinkedIn Learning | 87.5 MB | Audio-only mode | Errors after locking or rotating the screen (reviews) |
| Coursera | 57.7 MB | Audio-only, several renditions | Android 9 floor for 5.x builds |
| Udemy | 51.2 MB | Auto and download quality | Lag, buffering, battery drain (reviews) |
| Disprz | 82.5 MB | Not found | Video lag and buffering (reviews) |
| Moodle app | **22.9 MB** | Size warnings, Wi-Fi-only sync | Android 7+; slow loading (reviews) |

No competitor publishes RAM minimums or an Android Go version (not found).

**Android versions in use (StatCounter, mobile + tablet, Oct 2026):** India 92.1% on Android 11-16 and 7.9% on 10 or
older; Tanzania 84.5% / 15.5%; Africa 88.2% / 11.8%.

**Android 9 risk:** Chrome 138 was the last Chrome release for Android 8 and 9. Whether Android System WebView updates
stopped at the same time was not found. Every option plays SCORM in the system WebView, so the risk is the same for all.

**Tech stacks found:** Moodle app = Angular + Ionic + Cordova, TypeScript, Apache-2.0. Disprz = React Native (job
posting). LinkedIn Learning, Coursera, Udemy, Docebo, Degreed: not found.

### 2.5 What learners complain about, and what we do about it

| Complaint (apps) | What Sentientia does |
|---|---|
| Cannot sign in, frequent sign-outs, no organisation login (LinkedIn, Udemy, Disprz) | Long-lived refresh token, biometric unlock, working Azure AD sign-in, clear session-expiry handling |
| Videos fail, lag or buffer (LinkedIn, Udemy, Disprz) | Offline-first SCORM; later a lighter mobile version of each package; retry and resume |
| Downloads unreliable (Udemy, LinkedIn) | Resumable downloads per activity, a download queue, free-space checks |
| Home page shows stale data (Disprz) | "Last updated" time, pull to refresh, cache with expiry |
| Weak support (Coursera) | In-app help link to the tenant's L&D team |

---

## 3. Feature list for the Sentientia learner app

| Priority | Feature | Inspired by |
|---|---|---|
| **Must** | Sign-in with password and Azure AD SSO (system browser); biometric re-unlock; stays signed in with an offline grace period | Complaints in 2.5; Docebo's no-SSO-offline gap |
| Must | Home: "Continue learning" at the exact point, mandatory items with due dates, progress | All |
| Must | My learning: in progress / assigned / completed / **downloaded** | Udemy |
| Must | Catalogue search and filters, limited to the learner's tenant; free and assigned courses only (D29) | All |
| Must | Course outline, in-app SCORM 1.2 player, online quizzes, read-only forums | Moodle app |
| Must | **Offline:** download an activity, section or course; Wi-Fi-only by default; size shown first; free-space check; storage manager; auto-delete after completion; offline banner; sync queue with "last synced" | Udemy, Coursera, Moodle app |
| Must | Push notifications (assignments, due reminders, certificates) following the learner's Moodle notification preferences | All |
| Must | Certificates: view, download PDF, share, verify | Udemy, LinkedIn |
| Must | UI in English, Hindi and Swahili; tenant branding at runtime | Our requirement |
| Must | Basic phones: Android 9+ with 2 GB RAM; download under 30 MB; no autoplay; compressed images | Moodle app (22.9 MB) |
| Must | Accessibility to WCAG 2.1 AA (font scaling, screen-reader labels, contrast) | Our requirement |
| Must | Proctored and hiring assessments show "desktop required"; account-deletion route | Plan D29; store rules |
| **Should** | Untimed practice quizzes offline | Moodle app, Coursera |
| Should | Dark mode (theme tokens already support it) | Udemy, Coursera, Moodle app |
| Should | Weekly goal, reminders, streak (reuse gamification) | LinkedIn, Udemy |
| Should | Lighter mobile SCORM version (480p/360p) + download-quality setting | Udemy, Coursera |
| Should | Notes and bookmarks per activity | Udemy, Coursera |
| Should | Deep links from email and WhatsApp | Our requirement |
| **Could** | Audio-only "listen" mode for pipeline courses (narration MP3s exist) | LinkedIn, Coursera |
| Could | Daily micro-learning feed, AI recommendations | LinkedIn Daily |
| Could | Live polls, classroom QR check-in, offline H5P (none in use), home-screen widget, picture-in-picture | Various |

---

## 4. Architecture options

### 4.1 How each option would play SCORM offline

SCORM 1.2 content finds its `API` object by walking up its parent windows, which only works when the player page and
the content come from the same origin.

| Option | Offline SCORM approach | Difficulty |
|---|---|---|
| **Capacitor 8 + Ionic** | Download the zip into app storage and unzip; serve it from the app's own local origin (`Capacitor.convertFileSrc`) in an iframe whose parent provides `window.API`; store tracks locally; send them with `mod_scorm_insert_scorm_tracks` on reconnect. Same pattern as the Moodle app, whose TypeScript player can be ported (Apache-2.0, keep notices), or use scorm-again (MIT) | Medium |
| React Native | `react-native-webview` plus a local file server; SCORM's calls are synchronous, so the API object must live inside the WebView and mirror data out over postMessage | Medium-high |
| Flutter | `flutter_inappwebview` with its local server, JavaScript handlers and Dart storage | Medium-high |
| Kotlin / Compose Multiplatform | Separate Android WebView and WKWebView wiring behind shared interfaces; Compose for iOS stable only since May 2025 | High |
| Fork the Moodle app | Already built: SCORM 1.2 offline, H5P offline, untimed quizzes, course download | Low |
| Branded Moodle App (Moodle's paid service) | Same as the fork, run by Moodle | None |

### 4.2 Scorecard (my judgement, 1 to 5, 5 is best; not benchmarks)

| Criterion | Capacitor 8 + Ionic | React Native | Flutter | KMP / CMP | Moodle app fork | Branded Moodle App |
|---|---|---|---|---|---|---|
| Offline SCORM effort | 4 | 3 | 3 | 2 | **5** | **5** |
| App-shell speed on low-end phones | 3 | 4 | 4-5 | 5 | 3 | 3 |
| Memory while SCORM plays | 4 (one WebView runtime) | 3 (JS engine + WebView) | 3 (Dart VM + WebView) | 3 | 4 | 4 |
| Development speed for our web/PHP team | **5** | 3 | 2 | 1-2 | 4 parity, 2 custom UX | 5 |
| Reuse of our web code and tokens | **5** | 3 | 1 | 1 | 3 | 2 |
| Long-term maintenance | 4 | 3 | 4 | 3 | 2 (merge a large upstream codebase) | 5 |
| UX freedom ("industry leading") | **5** | 5 | 5 | 5 | 2 | 1 |
| Fits "our own app, not the Moodle app" | Yes | Yes | Yes | Yes | Partly | **No** |
| Minimum OS | Android 7 / iOS 15 | Android 7 / iOS 15.1 | Android 7 / iOS 13 | iOS 15+ | Android 7 / iOS 15 | Same |

### 4.3 Recommendation (superseded 19 Nov 15:05 by Nitin's choice; see 4A)

Nitin chose a native-class stack (KMP or else Flutter), so this Capacitor recommendation is no longer the plan. It is
kept for its reasoning and as the first fallback if the Flutter spike fails (4A.6). The offline engine, server side and
online-only rules in points 2-4 below carry over to Flutter, except that the SCORM runtime moves into a `webview_flutter`
host page (4A.2) and payments now follow D29 (paperwork pack section 2A: in-app browser to Airpay PG).

Build **our own Capacitor 8 + Ionic React app**:
1. **App shell:** native-feeling screens bundled in the app. Not a remote-URL wrapper (Capacitor advises against it for
   production, and Apple may reject a repackaged website under guideline 4.2).
2. **Offline engine, in v1:** download manager with resume, Wi-Fi-only by default; app-private storage wiped on logout
   or token revocation, with an online re-check every 30 days; SCORM 1.2 runtime in a same-origin iframe; sync queue;
   the server records when each offline attempt really happened (D28-d).
3. **Server side:** `local_sentientia_mobile` web services (M2-M4), push through Firebase (FCM HTTP v1) via a Moodle
   message output, theme "app mode" (M5).
4. **Online only:** timed quizzes, proctored and hiring assessments. **No purchasing in the app** (D29).

**Why:**
- SCORM needs a WebView in every option. Capacitor keeps that WebView as the only runtime, so it uses the least memory on
  2-3 GB phones while a package plays.
- It is the only option where the Moodle app's proven offline-SCORM code and scorm-again port almost directly.
- It fits a small web/PHP team, reuses our TypeScript, design tokens and patterns, and gives the full UX freedom you asked for.
- React Native, Flutter and KMP would scroll lists better but need a SCORM bridge on top and a new skill set. My
  estimate: +15-25% time for React Native, +25-40% for Flutter, +40-60% for KMP.

### 4.4 Risks

| Risk | Mitigation |
|---|---|
| WebView too slow or too heavy on 2 GB Android 9 phones | 3-day spike (M0e) before ADR-040, measuring cold start, memory and sync with the 387.7 MB package and a typical 27 MB one; lean bundle, virtualised lists. Fallback: Moodle app fork |
| The spike needs the real package files, and the local file store is empty | Get the largest package and one typical package from the content owner (or from UAT on your go) |
| Android 9 WebView may stop updating | Same for every option. Support Android 10+ fully, Android 9 best effort on a real device |
| Large packages fill phone storage | Free-space check, auto-delete, a recommended maximum package size, a lighter mobile version from the pipeline |
| Offline completions sync after a deadline (Moodle stores sync time) | Record device time, accept it within a 7-day grace, label "completed offline" (D28-d). Touches compliance reports; device clocks can be wrong, so the grace is capped |
| Multi-SCO or externally linked packages fail offline (Docebo documents this) | Check packages at upload; our pipeline produces single-SCO self-contained packages |
| A small team maintains the app long term | Follow Capacitor's yearly upgrade cadence; Airpay teams support signing, CI and review (D31) |

---

## 4A. KMP vs Flutter (Nitin, 19 Nov)

**Nitin's choice (15:05):** a native-class stack, Kotlin Multiplatform (KMP) or else Flutter; Capacitor not chosen.
This section compares the two and replaces the Flutter and KMP rows of 4.1 and 4.2 (4.1 named `flutter_inappwebview`;
use `webview_flutter` instead, see 4A.2). Sources are in section 9.

**Verdict for D28: build the learner app in Flutter, not KMP.** Use Flutter's official `webview_flutter` plugin for the
SCORM player, with scorm-again (MIT) running inside the WebView. Flutter is conditional on a 3-day go/no-go spike on a
2 GB Android 9 phone (4A.6), which replaces M0e (planned in a Capacitor shell). If the spike fails, fall back to
Capacitor, then to a fork of the Moodle app.

Flutter and KMP can both meet every must. Flutter meets more of them with ready, Google-maintained or well-maintained
packages. KMP's real advantages (smaller Android app, faster cold start, a native Android WebView) matter less here,
because the heavy part is the SCORM WebView, which is the same Chromium engine in both stacks. KMP costs more where it
matters to us: more glue code we write and maintain, a younger iOS UI layer, and a smaller hiring pool in India.

### 4A.1 What "native-class" buys on each OS

- **Android:** Compose Multiplatform is Jetpack Compose, Android's own UI toolkit. Flutter draws with its own engine.
- **iOS:** both Compose Multiplatform and Flutter draw their own UI (Skia or Impeller); neither uses UIKit or SwiftUI
  widgets. Compose Multiplatform ships Skia inside the iOS app.
- **Fully native on both:** only KMP shared logic with separate SwiftUI and Compose screens, which means building every
  screen twice. A small team cannot justify that for v1.
- **Status:** Compose Multiplatform for iOS has been stable since 1.8.0 (May 2025); 1.10 shipped in January 2026 and
  1.11 in May 2026 (concurrent rendering on by default on iOS); 1.12 is in beta.

### 4A.2 The musts, one by one

| Must | Flutter | KMP + Compose Multiplatform | Edge |
|---|---|---|---|
| Offline SCORM 1.2 in a WebView, with a local API bridge and progress sync | `webview_flutter` 4.14.1 (Google-maintained; Android 7+, iOS 13+). scorm-again's Flutter guide uses a fixed local address (127.0.0.1) served by the app, plus a message channel to native code. Android needs a cleartext exception for that address; iOS needs `NSAllowsLocalNetworking`. Avoid `flutter_inappwebview`: no stable release since 6.1.5 (Oct 2024), only 6.2.0 betas. The WebView is embedded as a "platform view"; before Android 10 the safe embedding mode copies every frame through main memory, so test it on Android 9 | Written twice. Android: the system WebView with `WebViewAssetLoader`, a real https origin with no local server (Google recommends it over `file://`). iOS: WKWebView with a custom URL scheme handler. scorm-again documents a KMP integration, but file serving is ours to build. No official Compose Multiplatform WebView, only community libraries | Even. KMP runs better on Android; Flutter is less code. The SCORM logic is the same JavaScript in both |
| Video download and playback with speed control | `background_downloader` 9.6.6 (iOS background `URLSession`, Android WorkManager, pause and resume, large files, notifications). `video_player` 2.14.1 (Google-maintained, `setPlaybackSpeed`) | No mature multiplatform background download manager: KDownloader has no pause or resume; the WorkManager wrappers schedule tasks, not downloads. Video uses Media3 on Android and AVPlayer on iOS, through community wrappers or our own code | **Flutter** |
| Moodle web-service client, push (FCM and APNs), secure storage, SQLite | `http` / `dio`, `firebase_messaging` (official FlutterFire), `flutter_secure_storage`, `drift` | Ktor, plus Google's Room 2.8, DataStore and SQLite with iOS support. No official Firebase KMP SDK; wrappers such as KMPNotifier exist | Even. KMP stronger on storage, Flutter on push |
| 3 tenants with runtime branding, en / hi / sw | `ThemeData` switched at runtime, `gen-l10n` ARB files | `MaterialTheme` switched at runtime, Compose string resources | Even |
| Basic Android phones (2-3 GB RAM, Android 9+), small app | Flutter 3.50 supports Android API 24-37 and iOS 15-27. Impeller is the default renderer only on API 29+, so Android 9 phones take the OpenGL fallback. Smallest Android app about 6.8 MB | Smallest Android app about 1.5 MB; cold start about 425 ms against Flutter's 634 ms (Pixel 4a). On iOS, Compose Multiplatform adds about 25-32 MB against Flutter's 18 MB | **KMP** on Android, **Flutter** on iOS. The size and startup figures are from 2023; both gaps are small next to a 27 MB average SCORM package |
| In-app browser checkout to Airpay PG (D29) | `url_launcher` in-app browser view (Custom Tabs / SFSafariViewController) | The same platform components through a few lines of platform code | Even. The store-policy risk is the same in both stacks (paperwork pack 2A) |

### 4A.3 Team, ecosystem and maintenance

- **Hiring in India:** sources agree Flutter's pool is larger, but only vendor and recruiter articles were found, no
  job-count data. KMP has strong Indian reference users (Blinkit, JioHotstar, Swiggy, Zomato, Google Docs). Google
  officially supports KMP for shared logic; the shared UI layer is JetBrains'.
- **Claude-assisted building (my judgement):** Flutter is one language (Dart) with stable APIs. KMP brings Gradle,
  Kotlin/Native, Xcode and Swift interop, and Compose Multiplatform churns its build setup (1.10 deprecated the
  `compose.*` dependency aliases; Android Gradle Plugin 9 needs Compose Multiplatform 1.9.3 or later). Build-tooling
  breakage is where assisted development loses the most time.
- **CI/CD and testing:** both work. Codemagic, Fastlane and GitHub Actions on macOS support both. Flutter has widget,
  golden and integration tests built in; KMP uses `kotlin.test`, Compose UI tests and XCTest. Maestro end-to-end tests
  work for both.
- **Long-term maintenance:** Flutter needs quarterly SDK upgrades and attention to plugin quality (`flutter_inappwebview`
  is the warning). KMP has more of our own platform code to maintain, plus the Kotlin, Android Gradle Plugin and Xcode
  version matrix.

### 4A.4 Effort for v1 (estimate)

v1 screens: login, dashboard, my courses, course player, catalogue, certificates, notifications, profile, downloads.
Baseline: the Capacitor mobile track at about 59-79 days (section 6).

| Stack | Effort against Capacitor | Days |
|---|---|---|
| **Flutter** | +25-40% | about 74-110 |
| KMP + Compose Multiplatform | +40-60% | about 83-126 |
| KMP with separate SwiftUI and Compose screens | +80-100% | not estimated |

Main risks. **Flutter:** the embedded WebView on Android 9, local-server Range requests for media inside packages, and
plugin quality. **KMP:** WebView, download and push code written twice, iOS app size, a younger iOS UI layer, a smaller
hiring pool.

### 4A.5 Where Capacitor still wins, and when KMP would

Capacitor still wins one must narrowly: offline SCORM is least effort there. The app's WebView is the player, so content
is same-origin with no extra setup, the Moodle app's TypeScript SCORM code ports directly, and there is no embedded view
to composite. It probably also uses the least memory while a package plays (unmeasured), and it reuses our web code and
design tokens. It loses on how native the screens feel, list scrolling on low-end phones, background downloads, and
Nitin's stated preference. No must is out of reach in Flutter.

**KMP would come out ahead if** Airpay's in-house mobile team builds in Kotlin and will co-own the Android app. That
changes the team factor. Ask the CTO office before ADR-040 (with D31-f).

### 4A.6 The 3-day go/no-go spike (Flutter 3.50, release builds; replaces M0e)

**Before Day 1:** the 387.7 MB package and one typical package of about 27 MB (the local file store is empty); a 2 GB
Android 9 phone and a 3 GB Android 11 phone (D31-d); a way for the phones to reach a test Moodle (D31-e), or local
Moodle through `adb reverse`.

**Day 1, offline SCORM on Android:**
- `background_downloader`: Wi-Fi-only, resume after a network drop and after the app is killed, a free-space check.
- Stream-unzip to app-private storage without loading the zip into memory.
- Serve it from a `dart:io` server on a fixed 127.0.0.1 port, with HTTP Range support.
- A `webview_flutter` host page with scorm-again providing `window.API`, a message channel to Dart, progress saved in
  drift.
- Test both WebView embedding modes on Android 9.

**Day 2, sync, video and measurements:**
- Play offline, kill the app, relaunch and resume (lesson location, suspend data).
- Reconnect and sync through `mod_scorm_insert_scorm_tracks` on a test account, recording device time (D28-d).
- Video: download an MP4, play it offline, change speed from 0.5x to 2x.
- Measure on both phones: cold start, memory during the 388 MB package (`dumpsys meminfo`), frame jank on a 200-course
  list, arm64 APK size.

**Day 3, iOS and the rest:**
- Repeat the SCORM flow on the oldest supported iPhone (iOS 15).
- One test FCM / APNs push.
- Switch branding at runtime for all 3 tenants; render Hindi and Swahili strings.
- Open the in-app browser to a test page (no payments).
- Store the token in `flutter_secure_storage`.
- One build of both platforms on Codemagic or GitHub Actions with Fastlane.

**Go criteria (all must pass):**
- On the 2 GB phone, 3 runs of the 388 MB package with no out-of-memory kill or WebView crash.
- SCORM opens in 5 s or less from the tap.
- Cold start to the dashboard in 3 s or less.
- Under 5% janky frames on the list.
- 100% of offline attempts sync with the correct status, score and time.
- arm64 download size of 15 MB or less.
- The same SCORM flow works on iOS.

**No-go paths:**
- The WebView fails on Android 9 but the Flutter screens are fine: rerun Day 1 in a Capacitor shell (the original D28
  plan).
- Both fail: fork the Moodle app.

### 4A.7 Still to update once Nitin confirms Flutter

D28-a to D28-c in section 7, section 0 and the M0e line in section 6 are updated in this doc. Not yet changed: rows M0e, M1 and M6 in section 5.2 of
`2026-11-19-POST-DEMO-PLAN.md` and the ADR-040 row in its section 5.3, which still name Capacitor 8 + Ionic React
(ADR-040 itself is not written yet), and the mobile track total in section 6 below.

---

## 5. D30: store release before cutover

### 5.1 Facts

| Fact | Source |
|---|---|
| Live runs `4.1.2+ (Build: 20230401)` | `D:\Claude Local\Moodle Backup\01-production-codebase\html\version.php` |
| Moodle 4.1 security support ended 8 Dec 2025; 5.3 LTS is supported to 1 Oct 2029 | moodledev.io releases page |
| Live has the mobile web service on and mobile notifications off (checked 11 May 2026) | `docs/MOBILE-PUSH-SETUP.md` |
| Live's 4.1.2 auto-login calls refuse any client that is not the Moodle app | BizLMS backup `admin/tool/mobile/classes/external.php` lines 304-305, 646-647 |
| Rule: never patch live BizLMS; Sentientia replaces it at cutover | CLAUDE.md, 29 Sep 2026 decision |
| Google Play requires target API 36 for new apps and updates since 31 Aug 2026 | Play Console Help |

### 5.2 What an app released before cutover could talk to

| Backend | Works for real learners? | Main problems | Verdict |
|---|---|---|---|
| **A. Live BizLMS 4.1.2** | Partly (core 4.1 and BizLMS web services) | Out of security support; no server changes allowed, so no push for our app, no new API and no offline support; content needs a separate web login; a reviewer account means a change on live near real employee data; about 10 to 15 days of throwaway adaptor code (my estimate; an earlier estimate said 6 to 10); a forced update at cutover | **No** |
| **B. UAT** | No, not a production service | Holds a test import of real personal data; resets; store reviewers are outside Airpay; reach without VPN unknown | **Never for a store release.** Fine for beta with test accounts |
| **C. New Sentientia production server before cutover** | Only for a review tenant | Holds rehearsal data that cutover replaces; real learners would create a second system of record | **Use for store review only** |
| **D. Official Moodle app on live** | Already possible | Moodle-branded; April import shows almost no use | Stopgap only |

### 5.3 What store review needs

- A working backend reachable from the internet (reviewers are outside India and outside Airpay's network), with a
  **password** login (not SSO, not MFA), an account that does not expire, and synthetic content: one SCORM package, one
  quiz, one certificate, one notification. Apple guideline 2.1; Google "App access".
- Universal-link and App Links files served from the domain the app opens. Before cutover www is BizLMS, so these can
  only live on a **separate app host** (for example `app.airpay.academy`).

### 5.4 Recommended sequence: approved before cutover, released at cutover

1. **Now:** D27 paperwork (CTO review). ADR-040 adds an app host name, so the production build never points at UAT or BizLMS.
2. **M2-M8:** build against local and UAT. Beta on TestFlight (up to 100 internal, up to 10,000 external testers after
   Beta App Review) and Google Play internal and closed tracks, **test accounts only**.
3. **After Stage B passes on the new 5.3 server, before the DNS switch:** IT points the app host at it with TLS; seed a
   **review tenant** with synthetic data; turn `sentientia.mobile.api.enabled` and the push flag on **for the review
   tenant only** (Airpay, Public and ZEEA stay OFF); publish the link files on the app host.
4. **Submit both stores about 2 to 3 weeks before cutover.** Apple: "Manually release this version" (no expiry found;
   a reminder after about 30 days, per a mirror of Apple's help). Google: Managed publishing.
5. **Cutover day:** final data load; re-seed the review tenant; turn the mobile flags on for the three tenants; release
   both listings; publish the link files on www.
6. **Cutover + 60 days:** switch off the official Moodle app service.

**Fallback:** submit on cutover day pointing at www; the apps arrive 1 to 2 weeks after cutover and no app host is needed.
Apple's unlisted distribution needs an approved app and does not solve the backend problem.

---

## 6. Effect on the mobile track (full table in plan section 5.2)

- **Done today as drafts:** M0a paperwork pack, M0b store rules and D30 options, M0c this benchmark.
- **New M0e spike** (3 days) before ADR-040; now in Flutter (4A.6), with a Capacitor shell only on a no-go.
- **New M8b offline v1** (12 to 18 days; an earlier estimate said 10 to 15).
- **M8c changes** from "purchase redirect" to "store compliance": no commerce in the app, iOS rule for consumer courses
  bought on the web, in-app account deletion (2 to 3 days).
- **Total from today:** about 59 to 79 developer days. With two parallel session streams (app side and Moodle side M2-M5)
  about 9 to 12 weeks elapsed, plus 1 to 2 weeks store review (rough estimate).

---

## 7. Decisions (choose and approve)

**D28: architecture and scope**

| # | Question | Options | Recommended |
|---|---|---|---|
| D28-a | Which architecture for the learner app? | A) Capacitor 8 + Ionic, our own app; B) fork the Moodle app; C) Branded Moodle App service; D) React Native; E) Flutter; F) Kotlin Multiplatform | **Nitin 15:05: native-class, F or else E; A not chosen.** Now recommended: **E (Flutter)**, behind the spike; fallbacks A, then B (4A). Was A |
| D28-b | UI framework inside Capacitor | A) Ionic React (larger hiring pool); B) Ionic Angular (closest to the Moodle app code) | Moot unless the spike falls back to Capacitor; then **A** |
| D28-c | Run a 3-day spike first (largest SCORM offline on a 2 GB Android 9 phone), go/no-go before ADR-040? | Yes / No | **Yes**, in Flutter 3.50 (plan and go criteria in 4A.6) |
| D28-d | Offline completion against compliance deadlines | A) Device time if it syncs within 7 days, logged "completed offline"; B) sync time (Moodle default) | **A** |
| D28-e | Offline scope for v1 | A) SCORM 1.2 + files offline in v1, untimed quizzes in v1.1, timed quizzes online only; B) offline in v2 (old plan) | **A** (mobile track about 59-79 days) |
| D28-f | Protection of downloaded content | A) App-private storage, wipe on logout or revocation, online re-check every 30 days, no DRM in v1; B) encrypt at rest from v1 | **A** |
| D28-g | Low-bandwidth content | A) Lighter mobile SCORM version in the SENTIENTIA pipeline after the MVP, plus a recommended maximum package size for new content; B) not now | **A** |
| D28-h | Supported devices | A) Android 10+ fully, Android 9 best effort (built for API 24), iOS 15+; B) Android 9 fully supported | **A** |
| D28-i | App languages at launch | A) en + hi + sw; B) add kn + mr | **A** (kn and mr in v1.1) |
| D28-j | Tenants | A) One app for all 3 tenants, branding after login; B) a separate store app for ZEEA | **A** (per-customer builds in v3) |
| D28-k | Engagement features | A) Push + reminders in v1, weekly goal + streak in v1.1; B) all in v1 | **A** |

**D30: release before cutover**

| # | Question | Options | Recommended |
|---|---|---|---|
| D30-a | Release model | A) Approved before cutover, held, released on cutover day (app host + review tenant); B) submit on cutover day; C) public release now against live BizLMS 4.1.2; D) release against UAT | **A**, with B as fallback. Not C or D |
| D30-b | App host name | Dedicated host (e.g. `app.airpay.academy`) / www only | **Dedicated host** (IT DNS and TLS request) |
| D30-c | Mobile access before cutover | Official Moodle app on live (already enabled) as a stopgap + TestFlight / Play closed beta on UAT with test accounts | **Yes to both**; count Moodle app tokens on live first (via IT, read-only) |
| D30-d | Official Moodle app service after launch | Switch off 60 days after our app is generally available / keep | **Switch off after 60 days** |

**D31: we build it, Airpay teams support**

| # | Question | Options | Recommended |
|---|---|---|---|
| D31-a | iOS build machine | A) Cloud macOS CI (GitHub Actions + Fastlane) for builds + one Airpay Mac for on-device debugging; B) CI only; C) Mac only | **A** (Safari's WebView inspector runs only on macOS) |
| D31-b | Run mobile beside the LMS work | A) Yes, a second session stream on its own branch, Moodle-side M2-M5 interleaved; B) one after the other (adds about 3 months) | **A** |
| D31-c | Security test before store release | A) Yes, Airpay InfoSec, OWASP MASVS style; B) skip for v1 | **A** |
| D31-d | Test devices (Airpay-provided) | A) 2 GB Android 9, 3 GB Android 11, mid-range Android 14+, iPhone on the oldest supported iOS, current iPhone; B) emulators only | **A** |
| D31-e | How test phones reach UAT | A) VPN profile on test devices, or an IP-allow-listed UAT hostname, UAT stays non-public; B) public UAT | **A** (ask IT whether UAT is reachable from a phone at all) |
| D31-f | Technical reviewer from the CTO office | Reviews ADR-040 + API contract (M1), first build (M6), release build (M9/M10), 2-3 hours each / none | **Yes** |

---

## 8. Not found, to re-check before M1

- Minimum Android for LinkedIn Learning, current Coursera and current Udemy; tech stacks of LinkedIn, Coursera, Udemy,
  Docebo, Degreed; Docebo and Degreed store ratings; Disprz offline details beyond vendor claims.
- Whether Android System WebView still updates on Android 9.
- Branded Moodle App pricing; completeness of the Moodle app's Hindi and Swahili translations.
- A reliable empty-app size for Capacitor, React Native and Flutter (measure in the spike).
- Whether UAT is reachable from a phone without VPN; whether live's BizLMS web services changed after the 31 Mar 2026 code backup.
- Whether Google Play organisation accounts are exempt from the 12-tester / 14-day rule (official page silent; third parties say yes).

---

## 9. Sources

**Store data:** https://chrome-stats.com/d/com.linkedin.android.learning · https://chrome-stats.com/d/org.coursera.android ·
https://chrome-stats.com/d/com.udemy.android · https://chrome-stats.com/d/com.disprz ·
https://chrome-stats.com/d/com.moodle.moodlemobile · https://apps.apple.com/in/app/disprz/id1458716803

**LinkedIn Learning:** https://www.linkedin.com/help/learning/answer/71907 · https://www.linkedin.com/help/learning/answer/a1657672 ·
https://www.linkedin.com/help/learning/answer/71922 · https://libguides.mcmaster.ca/linkedinlearning/features ·
https://www.qub.ac.uk/directorates/HumanResources/hr-filestore/Filetoupload,865307,en.pdf

**Coursera:** https://blog.coursera.org/mobile-offline-features · https://blog.coursera.org/new-mobile-features-transcripts-notes-reminders ·
https://blog.coursera.org/learn-by-day-or-night-dark-mode-now-available-on-the-coursera-ios-app ·
https://medium.com/coursera-engineering/the-power-of-goals-a5134b8d0e1f · https://transloadit.com/casestudies/2015/02/coursera/ ·
https://www.wishdown.com/apps/coursera-learn-career-skills/org.coursera.android/5.8.0

**Udemy:** https://support.udemy.com/hc/articles/229603928-Downloading-Courses-on-iOS ·
https://support.udemy.com/hc/articles/115000247508-How-to-Adjust-Video-Quality-on-the-Mobile-App ·
https://support.udemy.com/hc/articles/229231327-How-to-Troubleshoot-Downloading-Issues-on-The-App ·
https://support.udemy.com/hc/articles/1500000935721-How-to-Schedule-Learning-Reminders-on-The-Mobile-App ·
https://business-support.udemy.com/hc/en-us/articles/16943691866519-Streaks-Frequently-Asked-Questions

**Disprz, Docebo, Degreed:** https://www.foundit.in/job/software-architect-mobile-disprz-chennai-36732747 · https://disprz.ai/blog/m-learning ·
https://help.docebo.com/hc/en-us/articles/360020080020 · https://help.docebo.com/hc/en-us/articles/10433286082706-Go-Learn-app-security-and-technical-notes ·
https://degreed.zendesk.com/hc/en-us/articles/12816937759004-Get-to-Know-Your-New-Degreed-Mobile-App

**Moodle app:** https://docs.moodle.org/503/en/Moodle_app_offline_features · https://docs.moodle.org/503/en/Moodle_app_SCORM_player ·
https://docs.moodle.org/503/en/Moodle_app_FAQ · https://docs.moodle.org/503/en/Moodle_app_plans · https://github.com/moodlehq/moodleapp ·
https://moodledev.io/general/app/development/development-guide · https://docs.moodle.org/dev/Moodle_Mobile_custom_apps

**Frameworks:** https://capacitorjs.com/docs/main/reference/support-policy · https://capawesome.io/blog/how-to-upgrade-your-capacitor-app-to-capacitor-8/ ·
https://capawesome.io/blog/capacitor-file-handling-guide/ · https://reactnative.dev/blog/2024/10/23/release-0.76-new-architecture ·
https://docs.flutter.dev/reference/supported-platforms · https://www.inappwebview.dev/docs/in-app-localhost-server ·
https://blog.jetbrains.com/kotlin/2025/05/compose-multiplatform-1-8-0-released-compose-multiplatform-for-ios-is-stable-and-production-ready/ ·
https://www.npmjs.com/package/scorm-again · https://jcputney.github.io/scorm-again/docs/advanced/mobile/react-native ·
https://jcputney.github.io/scorm-again/docs/advanced/mobile/flutter

**KMP vs Flutter (section 4A):** https://blog.jetbrains.com/kotlin/2025/05/compose-multiplatform-1-8-0-released-compose-multiplatform-for-ios-is-stable-and-production-ready/ ·
https://blog.jetbrains.com/kotlin/2026/01/compose-multiplatform-1-10-0/ · https://kotlinlang.org/docs/multiplatform/whats-new-compose-110.html ·
https://kotlinlang.org/docs/multiplatform/whats-new-compose-111.html · https://kotlinlang.org/docs/multiplatform/whats-new-compose-112.html ·
https://kotlinlang.org/docs/multiplatform/supported-platforms.html · https://developer.android.com/kotlin/multiplatform ·
https://developer.android.com/develop/ui/views/layout/webapps/load-local-content ·
https://jcputney.github.io/scorm-again/docs/advanced/mobile/flutter · https://jcputney.github.io/scorm-again/docs/advanced/mobile/kotlin-multiplatform ·
https://jcputney.github.io/scorm-again/docs/advanced/mobile/android-native · https://jcputney.github.io/scorm-again/docs/advanced/mobile/ios-native ·
https://pub.dev/packages/webview_flutter · https://pub.dev/packages/flutter_inappwebview · https://github.com/pichillilorenzo/flutter_inappwebview/releases ·
https://www.inappwebview.dev/docs/in-app-localhost-server · https://pub.dev/packages/background_downloader · https://pub.dev/packages/video_player ·
https://docs.flutter.dev/reference/supported-platforms · https://docs.flutter.dev/perf/impeller ·
https://flutter.googlesource.com/mirrors/flutter/+/HEAD/docs/platforms/android/Android-Platform-Views.md ·
https://github.com/jacobras/flutter-vs-native-vs-kmp · https://medium.com/@jacobras/android-ios-native-vs-flutter-vs-compose-multiplatform-7ef3d5ec2a56 ·
https://klibs.io/project/OneXeor/KDownloader · https://klibs.io/package/dev.brewkits/kmpworker · https://github.com/mirzemehdi/KMPNotifier ·
https://www.droidcon.com/2023/12/06/how-to-implement-push-notifications-in-kotlin-multiplatform/ · https://github.com/kdroidFilter/ComposeMediaPlayer ·
https://klibs.io/package/io.github.cyborch/compose-webview-multiplatform · https://docs.codemagic.io/yaml-quick-start/building-a-kmm-app/ ·
https://www.wisemonk.io/blogs/flutter-developers-india-hiring-guide · https://www.perfectiongeeks.com/blogs/kotlin-multiplatform-vs-flutter-2026

**Platform, market and release:** https://support.google.com/googleplay/android-developer/answer/11926878 ·
https://9to5google.com/2025/06/26/google-chrome-android-versions-no-longer-supported-2025/ ·
https://gs.statcounter.com/android-version-market-share/mobile-tablet/india · https://gs.statcounter.com/android-version-market-share/mobile-tablet/tanzania ·
https://gs.statcounter.com/android-version-market-share/mobile-tablet/africa · https://moodledev.io/general/releases ·
https://developer.apple.com/app-store/review/guidelines/ · https://developer.apple.com/testflight/ ·
https://www.developer.apple.com/help/app-store-connect/manage-your-apps-availability/select-an-app-store-version-release-option ·
https://developer.apple.com/support/unlisted-app-distribution/ · https://support.google.com/googleplay/android-developer/answer/14151465 ·
https://android-developers.googleblog.com/2020/10/optimize-your-app-publishing-process.html

**Internal records:** `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` (4H, 5, 6, 6a, 6b) ·
`moodle-enhancement/docs/adr/ADR-005-pwa-install-and-native-wrapper-decision.md` · `moodle-enhancement/mobile/sentientia-app/`
(`package.json`, `capacitor.config.ts`, `android/app/build.gradle`) · `moodle-enhancement/docs/MOBILE-PUSH-SETUP.md` ·
`moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` · BizLMS code backup `D:\Claude Local\Moodle Backup\01-production-codebase\html\` ·
local XAMPP database (read-only counts on `mdl_course_modules`, `mdl_scorm`, `mdl_files`, `mdl_quiz`, `mdl_user_devices`, `mdl_external_tokens`)
