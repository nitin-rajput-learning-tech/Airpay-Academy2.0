# ADR-040 — Native learner app: Flutter or KMP chosen by spike, offline SCORM 1.2 in a WebView, one app for three tenants, store release before cutover

- **Status:** Proposed 2026-11-19. Everything below is drafted from Nitin's recorded answers D28 to D31 (post-demo plan §6a, including the D28 "plan for both" update of ~16:10). The framework is chosen after the two spikes (D1); this ADR is then amended with the result and goes to Nitin for acceptance. Sub-decisions still marked "recommended" (D28-b to D28-k, D30-a to D30-d, D31-a to D31-f, D27-a to D27-h, D29-a to D29-e) wait for his approval.
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services). Technical review by the Airpay CTO office (D31-f, pending).
- **Implementer:** Claude (Sentientia build team), with Airpay internal teams in support (D31).
- **Supersedes:** ADR-005 **Path C** only: "defer the native wrapper; when triggered, wrap the PWA with Capacitor in a sibling repo, recorded in an ADR-006". ADR-005 Path B (the PWA) stays in force as the web channel. ADR-006 was never written.
- **Amends:** ADR-028 Phase 3.3 as amended by decision memo Q5 (`docs/business/DECISION-MEMO-2026-08-04.md`): the funded native app is not Capacitor, and it does not consume REST v1 plus the 22 "MOBILE-READY" functions (see Context).
- **Related:** ADR-002, ADR-017, ADR-021, ADR-031, ADR-033, ADR-034, ADR-035; ADR-038 (assessment integrity levels, proposed; the "desktop required" rule). Full list at the end.
- **Evidence:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` (§4 H, §5.2, §5.3, §6, §6a, §6b); `docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md` (§0, §1, §4A, §5, §7); `docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` (§2, §2A, §3, §4.4, §5). Moodle 5.3 line numbers are from the local 5.3 tree (`D:/Claude Local/moodle53/moodle/public/`); repository paths are relative to the repo root.

---

## Context

Nitin asked for Android and iOS apps of our own, learner first, published under Airpay's developer accounts, instead of
the official Moodle app (plan §4 H). His answers on 19 Nov: a native-class stack, Kotlin Multiplatform (KMP) or Flutter,
not Capacitor (D28, 15:05), and at ~16:10 "plan for both": spike both stacks on the same scorecard, then choose. Purchases
go through an in-app browser to Airpay's payment gateway (D29). The app reaches the stores before cutover (D30). We build
it, with technical support from Airpay internal teams (D31).

**What the app has to do.** All 851 SCORM activities are SCORM 1.2, 26.9 MB on average and up to 387.7 MB; there is no
H5P; 223 of 235 quizzes are timed (benchmark §1). The main users are about 1,800 field agents on basic Android phones
(ADR-005). Offline is a must (D29, 12:15). Every stack, including the "fully native" ones, plays SCORM in a WebView,
because SCORM 1.2 content finds its `API` object in a parent window.

**What exists today, and why it cannot be reused.**
- The scaffold `moodle-enhancement/mobile/sentientia-app` was never built. It is Capacitor ^5.7.0 (`package.json`), in
  remote-URL mode pointed at the live BizLMS site (`capacitor.config.ts:37`), targeting API 34
  (`android/app/build.gradle:14-19`); Google Play now requires API 36.
- Native push cannot register: `local_sentientia_pwa`'s subscription service accepts only browser Web Push endpoints
  with `p256dh` and `auth` keys (`local/sentientia_pwa/classes/external/save_subscription.php:35-51`).
- The learner dashboard data is computed inside the theme (`theme/sentientia/layout/dashboard.php`: courses at 639,
  certificates at 764 and 941, deadlines at 878), so no app can call it.
- REST v1 (`moodle-enhancement/local/sentientia_api/db/services.php:89-104`) is an integration API for authorised
  systems (`restrictedusers => 1`), and the "22 MOBILE-READY" functions of the May audit are admin data-table
  endpoints (plan §4 H). Neither is a learner API.

**Moodle constraints on our own app (5.3).**
- A token for any service other than the official mobile one needs `moodle/webservice:createtoken`
  (`lib/external/classes/util.php:408-412`), which core gives to managers only, with RISK_CONFIG and RISK_XSS
  (`lib/db/access.php:1969-1975`).
- `admin/tool/mobile/launch.php` issues tokens for any enabled service (`:48`, `:89`), but a site-wide
  `forcedurlscheme` overrides the app's scheme (`:111-116`).
- Core's web auto-login only answers clients whose user agent contains "MoodleMobile"
  (`admin/tool/mobile/classes/external.php:316`, `lib/classes/useragent.php:1006`). It also needs
  `enablemobilewebservice` (`admin/tool/mobile/classes/api.php:521`), which D30-d plans to switch off after launch.
- Moodle answers only on its `wwwroot` host; other hosts are redirected or refused (`lib/setuplib.php:682-710`).
- `mod_scorm_insert_scorm_tracks` records sync time, and refuses tracks once the activity is closed
  (`mod/scorm/classes/external.php:459-489`, `scorm_require_available()` at `:483`).
- Core already has a device registry: the `user_devices` table (`lib/db/install.xml:3448`) and
  `core_user_add_user_device` / `core_user_remove_user_device` (`lib/db/services.php:1902`, `:1978`).

**Release constraints (benchmark §5).** Live is BizLMS on Moodle 4.1.2, out of security support, and is never patched.
UAT holds a test import of real personal data. Store reviewers sit outside India and Airpay's network and need a
password login on an internet-reachable backend.

---

## Decision

### D1 — Our own native-class app; Flutter or KMP chosen by two identical spikes

- **Two 3-day spikes, release builds, same scope:** M0e-F in Flutter 3.50 (benchmark §4A.6) and M0e-K in KMP with
  Compose Multiplatform (Android WebView with `WebViewAssetLoader`, iOS WKWebView with a custom URL scheme handler;
  §4A.2). Scope: password login and a course list; offline SCORM 1.2 with scorm-again; a video download with pause and
  resume, played offline at 0.5x to 2x; one FCM / APNs push; runtime branding for the three tenants with Hindi and
  Swahili strings; the in-app browser opening a test page (no payment); the token in secure storage; one CI build of
  both platforms.
- **Before day 1:** the 387.7 MB package and a typical package of about 27 MB (the local file store is empty); a 2 GB
  Android 9 phone, a 3 GB Android 11 phone and an iPhone on iOS 15 (D31-d); macOS (D31-a); a test Firebase project; a
  test Moodle the phones can reach (D31-e).
- **Gate (every item must pass):** on the 2 GB phone, three runs of the 388 MB package with no out-of-memory kill or
  WebView crash; SCORM opens in 5 s or less; cold start to the course list in 3 s or less; under 5% janky frames on a
  200-course list; 100% of offline attempts sync with the correct status, score and time; the video download resumes
  after a network drop and after the app is killed; the push arrives on both platforms; arm64 Android download of
  15 MB or less; the same SCORM flow works on iOS.
- **Scorecard (same phones, same packages):** gate results; memory while the package plays; Android and iOS app sizes;
  platform-specific code we had to write; days spent against plan; how many musts rely on community rather than
  official packages; team fit (does Airpay's in-house mobile team build in Kotlin and will it co-own Android? asked of
  the CTO office with D31-f).
- **Choice rule:** only one stack passes: that one. Both pass: Nitin chooses from the scorecard; the default is Flutter
  (benchmark §4A), KMP if the CTO office commits Kotlin co-ownership or the measurements clearly favour it. Neither
  passes: back to Nitin with the documented fallbacks (a Capacitor shell, then a Moodle-app fork); neither starts
  without his call, because he ruled Capacitor out. The result and scorecard are recorded as an amendment to this ADR.
- **Screens in v1:** login, home, my learning (with downloads), catalogue, course outline and player, certificates,
  inbox, profile.

### D2 — Offline SCORM 1.2 in v1 with one engine design, whichever stack wins

- **Download:** the package zip comes from the `mod_scorm` package file area through `webservice/pluginfile.php` with
  the app token. Downloads are resumable and Wi-Fi only by default, show the size first and check free space. The zip
  is stream-unzipped into app-private storage.
- **Playback:** a local origin serves the content (D1 names the server per stack). A host page runs scorm-again (MIT),
  which provides the SCORM 1.2 `window.API`. CMI data is saved in local SQLite, and a queue holds it for sync.
- **Sync:** through `local_sentientia_mobile` (D3), which writes tracks through the same `mod_scorm` path as
  `mod_scorm_insert_scorm_tracks`, availability checks included. It also stores the device time with a
  "completed offline" mark. Recommended rule (D28-d): device time counts when the attempt syncs within 7 days.
- **Online only:** timed quizzes; proctored and hiring assessments, which show "desktop required" (D29); SCORM 2004
  (none in use).
- **Protection (D28-f, recommended):** app-private storage, wiped on logout or token revocation; an online re-check
  every 30 days; no DRM in v1.
- **Reused code:** porting code from the Moodle app (Apache-2.0) is allowed with its notices kept. The app has a
  third-party notices screen.

### D3 — Server side: `local_sentientia_mobile`, a learner web service (not REST v1)

- **Plugin:** a new plugin in both trees, byte-identical, with en and hi at parity and sw added. It declares its own
  service, `sentientia_mobile`: `restrictedusers => 0`, `enabled => 0` (enabled at go-live; each function also checks the tenant flag), `downloadfiles => 1`,
  `uploadfiles => 0`.
- **Its own learner-self functions:** site config and branding (`\local_sentientia_platform\customer::branding()`,
  `local/sentientia_platform/classes/customer.php:208`, plus the tenant overrides that
  `theme/sentientia/classes/output/core_renderer.php:188` applies); home; my learning; the catalogue (tenant-scoped
  under ADR-031 with `tenant::path_descendant_filter()`); the course outline with `mobile_allowed` and a reason per
  activity; certificates; pending evaluations; SCORM offline sync; a web-session key; token revocation; the minimum
  app version, for forced updates.
- **Core functions:** a reviewed allowlist of core functions is added to the same service. Examples:
  `core_webservice_get_site_info`, `core_course_get_contents`, `mod_scorm_get_scorms_by_courses`,
  `mod_scorm_get_scorm_user_data`, the two `core_user_*_user_device` functions and
  `tool_dataprivacy_create_data_request`. The 36 sensitive-admin functions are never added.
- **Dashboard logic:** it moves out of `theme/sentientia/layout/dashboard.php` into a class that both the theme and
  the service call (session M2).
- **Tokens:** this plugin issues them, for its own service only, so that no learner role gets
  `moodle/webservice:createtoken`.
  - Password: `authenticate_user_login()`, the same lockout and auth plugins as the web, then
    `\core_external\util::generate_token()` (`lib/external/classes/util.php:193`).
  - Azure AD: a browser sign-in (Custom Tabs, or ASWebAuthenticationSession on iOS) to a launch page in this plugin,
    modelled on `tool_mobile`'s and not affected by `forcedurlscheme`. It returns the token to the app through an app
    link and a one-time passport.
  - Validity is the site's `tokenduration`. Unlock on the phone is biometric. Logout revokes the token.
- **Web session for the content view and checkout:** a one-time key from core `create_user_key()`, valid about 60
  seconds, bound to the IP address, needing the private token and refused for site admins. `local/sentientia_mobile/autologin.php`
  consumes it and redirects only to an allowlisted path. It also marks the session as app mode, so the theme knows
  without sniffing the user agent.
- **Account deletion (D29-d):** self-registered Public accounts get an in-app "Delete my account", which runs the
  existing DPDP erasure workflow (ADR-035). Employer-managed accounts get a page with the L&D and DPO contact.
- **Privacy and core:** the plugin has a real privacy provider for its tables (device rows sit in core `user_devices`,
  which core's provider covers). No Moodle core file is edited (ADR-033 decision 4).

### D4 — Push through FCM HTTP v1, as a Moodle message output

- **Delivery:** a new message output plugin (working name `message_sentientiapush`) sends through FCM HTTP v1 to
  Android and, through APNs keys in Firebase, to iOS. It reads devices from core `user_devices`, filtered on our app
  ID; the app registers devices with `core_user_add_user_device`.
- **Why an output:** every notification sent with `message_send()` reaches the app and follows the learner's own
  notification preferences, with no change to senders. The reminder and overdue tasks already call `message_send()`
  before the PWA bridge (for example `local/sentientia_courses/classes/task/course_reminder.php:283`).
- **Payload:** no personal data, only a message ID and a generic category title. The app fetches the text after it
  opens (paperwork pack §4.4).
- **Credentials:** the Firebase project belongs to Airpay (D27). Its service-account key stays on the server, out of
  the repository and out of the database.
- **The PWA channel:** `local_sentientia_pwa` Web Push is unchanged; ADR-005 Path B stands.

### D5 — One app for all three tenants; one listing per customer

- **Listing:** one app, "Airpay Academy", bundle ID `academy.airpay.sentientia` (permanent once published; plan §4 H),
  serves Airpay `/1`, Public `/77` and ZEEA `/177`.
- **Branding:** the customer's bundled brand before login, then customer and tenant branding at runtime from D3.
- **Languages:** en, hi and sw at launch (D28-i, recommended).
- **Future customers:** each publishes under its own developer account, from the same codebase as a branded build
  flavour (D27-e, recommended; v3).

### D6 — The app learns its server address from the app host

- **Why:** Moodle serves one `wwwroot` host, so a second hostname cannot front the same site, and the address changes
  at cutover.
- **How:** store builds carry a fixed allowlist of hosts. At launch the app reads a small static site descriptor (JSON
  served by the web server, not by Moodle) from the app host (D30-b; for example `app.airpay.academy`); it names the
  `wwwroot` the app should use, and the app caches it.
  - Before cutover, the descriptor points at the server that holds the review tenant (D7); at cutover, at www.
  - App-link files follow the same path.
- **Never in a store build:** live BizLMS or UAT. Beta and development builds may point at UAT or local.

### D7 — Store release before cutover, through a review tenant on the new production server (D30)

- **Review tenant:** after Stage B passes on the new 5.3 server and before the DNS switch, a review tenant is seeded
  there with synthetic data only: password reviewer accounts with no MFA and no expiry, one SCORM package, a quiz, a
  certificate and a notification. It is excluded from compliance, analytics and ROI, re-seeded at cutover, and stays in
  production for every later update review. The mobile flags are ON for it only; Airpay, Public and ZEEA stay OFF.
- **Submission:** both stores, 2 to 3 weeks before cutover, approved before it. Recommended (D30-a): hold the approved
  build (Apple "Manually release", Google managed publishing) and release it on cutover day. A listing that goes public
  earlier needs the three learner tenants dark until cutover; not recommended (first ratings, Apple guideline 2.1).
- **Beta:** TestFlight and Google Play internal and closed testing against UAT, test accounts only.
- **Official Moodle app:** its service is switched off 60 days after our app is generally available (D30-d,
  recommended).

### D8 — Purchases through an in-app browser to Airpay PG, behind per-platform flags (D29)

- **Who:** Public-tenant consumers only (ADR-017 user type `consumer`). Airpay and ZEEA users see no prices and no
  checkout.
- **Flow:** the app opens `local/sentientia_cart`, then `payment/gateway/airpay`, in Chrome Custom Tabs or
  SFSafariViewController, signed in with the D3 one-time key. The existing gateway flow unlocks the course on the
  server, and the app refreshes when it returns through its app link.
- **Flags:** `sentientia.mobile.checkout.android` and `sentientia.mobile.checkout.ios`, default OFF, server-side, so
  they change without a new build. Off means no price, buy button, cart or "buy on our website" anywhere in the app,
  its inbox or its push, and the cart and checkout routes return a neutral page in app mode (paperwork pack §2.5). The
  build under review shows exactly what users get (Apple 2.3.1).
- **Store risk, recorded (paperwork pack §2A.1):** **iOS India, high**: review is likely to read this as a link-out
  under guidelines 3.1.1 and 3.1.1(a), and no external-purchase entitlement for India was found. **Android, medium**:
  compliant only as user choice billing with Play Billing alongside Airpay's checkout, which brings Google's fee
  (standard minus 4 points), PCI DSS for the Airpay gateway and transaction reporting to Google within 24 hours.
- **Mitigations:** written pre-submission questions to Apple and Google before M8c; Android first, with Play Billing
  alongside; iOS checkout stays off until Apple agrees in writing or a CCI or Supreme Court ruling changes the rules.
- **Later, allowed on both stores:** physical items and classroom seats may use Airpay PG in the app (guideline
  3.1.3(e); D29-c).

### D9 — Team, repository and the old scaffold (D31)

- **Who builds:** the Sentientia build team (Nitin, with Claude-assisted sessions, one deliverable each) builds both
  the app and the server.
- **What Airpay teams provide:** store accounts and signatory (D27); signing keys in the Airpay IT vault (D27-g); macOS
  CI and one Mac (D31-a); test phones and phone access to UAT (D31-d, D31-e); the Firebase project and the Azure AD app
  registration; an OWASP MASVS-style InfoSec test before submission (D31-c); a CTO-office reviewer at M1, M6 and
  M9/M10 (D31-f).
- **Repository:** the app's source lives in its own private repository, as ADR-005 planned for Path C,
  with macOS CI and Fastlane. That keeps Dart or Kotlin toolchains and signing secrets out of the Moodle repository and
  its package. The server contract is versioned in this repository, and the app declares the minimum contract version
  it needs.
- **Old scaffold:** `moodle-enhancement/mobile/sentientia-app` is retired and not reused. Removing it is Nitin's call.

### D10 — Feature flags (all default OFF)

| Flag | Registered in | Controls |
|---|---|---|
| `sentientia.mobile.api.enabled` | `local_sentientia_mobile` | The service answers for that tenant |
| `sentientia.mobile.offline.enabled` | `local_sentientia_mobile` | Downloads and offline sync allowed (new) |
| `sentientia.mobile.push.enabled` | `local_sentientia_mobile` | The message output sends for that tenant |
| `sentientia.mobile.checkout.android` / `.ios` | `local_sentientia_mobile` | In-app checkout per platform (D8) |
| `sentientia.theme.app_mode.enabled` | `theme_sentientia` | No sidebar or topbar in app sessions; commerce hidden when checkout is off |

The store binary has no flag; its behaviour follows these server flags.

---

## Consequences

- **Positive:** native-class screens, background downloads and offline SCORM for field agents on poor networks. The
  server side (M2 to M5) does not depend on the stack, so it can start once this ADR is accepted, in parallel with the
  spikes. No Moodle core edit and no risky capability for learners. One listing serves three tenants. Store review
  never sees real personal data.
- **Negative:**
  - The second spike costs 3 days.
  - v1 is about 74 to 110 developer days in Flutter, or 83 to 126 in KMP, against 59 to 79 for Capacitor (benchmark
    §4A.4). The team takes on a language and toolchain it does not use today.
  - A synthetic review tenant lives in production for good. A fourth tenant root fails today's legacy tenant list
    (`local/sentientia_platform/classes/tenant.php:32`, `VALID_TENANTS = [1, 77, 177]`) unless the ADR-021 registry
    mode is used (`local/sentientia_core/classes/tenant_registry.php:64-89`).
  - iOS consumers probably cannot buy in the app at launch. Android checkout needs Play Billing as well (8 to 12 days,
    estimate), plus the fee and PCI DSS.
  - Device-time completion reaches compliance reports, which needs a later change outside this plugin.
  - In Flutter, the loopback server can be reached by other apps on the phone while it runs. It binds to loopback, uses
    a random path per session and stops when the player closes; the MASVS test covers it.
  - The checkout session cookie stays in the learner's own Chrome on Android.
- **Neutral:**
  - The PWA, REST v1 and the official Moodle app all keep working until D30-d.
  - Android 9 is best effort (D28-h, recommended): Chrome 138 was the last release for Android 8 and 9.

---

## Alternatives considered

- **Capacitor 8 + Ionic React** (the original recommendation): least effort for offline SCORM, and probably the least
  memory. Nitin did not choose it (15:05); it remains only as a no-go fallback that needs his call.
- **Fork the Moodle app** (Apache-2.0): offline SCORM 1.2 is already built, but it brings a web stack, large upstream
  merges and little UX freedom. Plan B if both spikes fail.
- **Branded Moodle App service:** not "our own app" in Nitin's words. Rejected.
- **React Native:** not in Nitin's choice, and it needs a bridge for SCORM's synchronous calls. Rejected.
- **KMP with separate SwiftUI and Compose screens:** every screen built twice, +80 to 100% effort. Rejected for v1.
- **Wrap the website** (the scaffold's remote-URL mode): rejection risk under Apple guideline 4.2, and no offline.
  Rejected.
- **PWA only** (ADR-005 Path B): no store presence, which Nitin asked for. It stays the web channel.
- **Release against live BizLMS 4.1.2 or UAT:** rejected (benchmark §5.2).
- **Core `login/token.php` with `createtoken` granted to learners, or core auto-login with a "MoodleMobile" user
  agent:** rejected (D3). The first carries RISK_CONFIG and RISK_XSS; the second impersonates the official app and
  depends on a service that D30-d switches off.
- **No commerce in the app** (paperwork pack option F, the earlier recommendation): Nitin chose in-app checkout. F is
  now the "checkout off" state.

---

## Implementation actions

1. **M0d:** accounts, CI skeleton, phones and UAT reach (D31). **M0e-F and M0e-K:** the two spikes, then an amendment
   to this ADR with the scorecard and Nitin's choice.
2. **M1:** this ADR, accepted, plus the API contract and `db/feature_flags.php`. **M2 to M5:** `local_sentientia_mobile`,
   the push output and theme app mode (plan §5.2).
3. **M6 to M8b:** the app in the chosen stack, and offline. **M8c:** checkout under D8, after the CTO's answers.
   **M9 and M10:** CI, beta, the review tenant and submission (D7).
4. **When this ADR is accepted (by the lead):** add the README index row; mark ADR-005's row "Path C superseded by
   ADR-040"; update plan §5.2 rows M0e, M1 and M6, and the §5.3 ADR-040 row, which still name Capacitor; mark
   `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` as superseded for app use.

---

## Open questions

1. D28-b to D28-k, D30-a to D30-d and D31-a to D31-f are still to approve. The ones that change this ADR are D28-d
   (offline time), D28-f (protection), D28-h (devices), D28-i (languages) and D30-a (hold the build, or list publicly
   with tenants dark).
2. The CTO answers for D29 (paperwork pack §2A.3): an iOS launch with checkout off; Airpay PG's PCI DSS status; the
   Play Billing budget; who sends the pre-submission questions, and by when.
3. The review tenant: ADR-021 registry mode or a fourth legacy root? Under which customer, and how do the reports
   exclude it?
4. App source: its licence and which GitHub owner holds it (IP, ADR-001).
5. A learner with both PWA Web Push and the app on one phone gets duplicates. Should the PWA bridge skip users with an
   active app device?
6. Offline attempts that sync after a SCORM close date (`scorm_require_available()` refuses them), and compliance due
   dates judged on device time: who owns the change to the compliance report?
7. Where the message output lives in the two-tree layout: `tools/check-tree-drift.php` does not cover
   `message/output/` today.
8. Tanzania's data protection law for ZEEA, and whether Android System WebView still updates on Android 9 (benchmark
   §8; paperwork pack §6).

---

## Related

- ADR-001 (fork and IP), ADR-002 (flags), ADR-005 (PWA; Path C superseded here), ADR-017 (user types), ADR-021
  (tenant registry), ADR-028 (roadmap; amended here), ADR-031 (tenant scope), ADR-033 (5.3, no core edits), ADR-034
  (pipeline: single-SCO packages and a lighter mobile version, D28-g), ADR-035 (erasure).
- `docs/plans/2026-11-19-POST-DEMO-PLAN.md`; `docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`;
  `docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md`; `docs/business/DECISION-MEMO-2026-08-04.md` (Q5);
  `docs/MOBILE-PUSH-SETUP.md`.
