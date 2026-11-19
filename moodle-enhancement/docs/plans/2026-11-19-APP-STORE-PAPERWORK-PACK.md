# App store paperwork pack and in-app payments (D27, D29)

**For:** Airpay CTO review (D27), then Nitin | **Date:** 2026-11-19 | **Status:** draft for CTO review; payments
decided by Nitin at 15:05 (in-app browser to Airpay PG, section 2A), store risk for the CTO to confirm
**Related:** architecture and release timing in `2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`; revised schedule in
section 5 of `2026-11-19-POST-DEMO-PLAN.md`.

**How this was prepared.** Web research on 19 Nov 2026 plus read-only reading of the repository. Store rules are
paraphrased; read the linked guideline before acting. Where a fact could not be confirmed this pack says "not found" or
"verify". Effort figures are estimates. This is not legal advice: Airpay Legal should review sections 3 and 4.

---

## 0. Summary

- **Decision D29 (Nitin, 19 Nov 15:05): consumers buy in the app through an in-app browser to Airpay's payment
  gateway.** This replaces the v1 design below for consumers; that design stays as the fallback when checkout is
  switched off. Review risk per store, mitigations and what the CTO should confirm: **section 2A**.
- **Payments (D29): redirecting learners from the app to Airpay's payment gateway is not store-compliant in India for
  digital courses.**
  - **iOS:** not allowed. Apple has no external-purchase entitlement for the India storefront (none found).
  - **Android:** allowed only as Google's **user choice billing**: we must also build Google Play Billing, show it next to
    Airpay's checkout, pay Google a fee (4 percentage points below standard), and meet PCI DSS and 24-hour reporting.
  - **Recommended v1 design: learn in the app, buy on the web.** No prices, buy buttons, cart or "buy on our website"
    wording in the app. Purchases stay on the website and PWA through Airpay's gateway. Telling users about web purchases
    by e-mail, WhatsApp or SMS is allowed by both stores. Employee use (Airpay, ZEEA) is compliant on both stores today.
  - **One iOS risk remains:** Apple has reportedly rejected course apps that open paid courses bought on the web without
    also selling them through Apple's in-app purchase. So iOS v1 hides consumer (Public tenant) courses bought on the web.
    Commerce is dark at go-live anyway (PROJECT-STATE), so this costs little.
- **Paperwork (D27):** the longest lead items are the **D-U-N-S number** (Apple: up to 5 business days plus 2; Google
  says up to 30 days) and **Apple's organisation verification** (no published review time). Start the D-U-N-S lookup
  as soon as the CTO approves. Cash cost: USD 99 a year (Apple) + USD 25 once (Google) + macOS CI minutes.
- **Two code facts the paperwork depends on:** Google Play requires target API 36 (the scaffold targets 34), and the
  scaffold still points at `https://www.airpay.academy` in remote-URL mode. Both are fixed in session M6.

---

## 1. Checklist for the CTO (owner, lead time, cost)

"Airpay" = an Airpay team does it. "Us" = the Sentientia build team does it. "Needed by" = the mobile session that is
blocked without it.

### 1.1 Accounts and identity

| # | Item | What is needed | Owner | Lead time | Cost | Needed by |
|---|---|---|---|---|---|---|
| 1 | D-U-N-S number for the legal entity | Exact registered name (e.g. "Airpay Payment Services Private Limited"; verify). Look up an existing number first with Apple's tool; otherwise request one free through Apple | **Airpay** Legal / Finance | Existing: same day. New: up to 5 business days + 2 for Apple; Google says up to 30 days | Free | Apple enrolment |
| 2 | Apple Developer Program, **Organisation** (not Enterprise) | Legal entity (no trade names or branches); D-U-N-S; working public website on the organisation's domain; work e-mail on that domain; Apple Account with 2FA; enroller must be an owner, executive or someone with legal authority (Apple may ask for a reference or notarised documents) | **Airpay** CTO or authorised signatory | Not published (days to weeks) | USD 99 a year | M9 (TestFlight) |
| 3 | Google Play Console, **Organisation** | Payments profile with D-U-N-S, name and address; public organisation phone; website; developer e-mail and phone verified by one-time code | **Airpay** CTO or delegate | Payments verification up to 5 days + D-U-N-S | USD 25 once (plan figure; not on the help page fetched) | M9 (Play testing) |
| 4 | Android developer verification | Over 99% of Play apps were registered automatically; India rollout in 2027; organisation accounts need a D-U-N-S | **Airpay** Play account owner | n/a | Free | before 2027 |
| 5 | Account custody | Role mailboxes on the Airpay domain; at least two Airpay administrators per store; no personal account as Account Holder; least-privilege roles for us (Apple: Developer / App Manager; Play: release manager); offboarding steps | **Airpay** CTO / IT | 1 day | None | M0d |
| 6 | App identity | Name "Airpay Academy"; bundle and package ID `academy.airpay.sentientia` (**permanent once published**); category Education | Nitin + CTO approve; **us** set up | n/a | None | M6 |
| 7 | Future white-label customers | Apple 4.2.6 and 5.2.1: apps built from one template must be submitted by each content owner (or one "picker" app). Each future customer publishes from its own developer account | Nitin (product) | n/a | None | ADR-040 |

### 1.2 Keys, signing and push

| # | Item | Detail | Owner | Custody | Needed by |
|---|---|---|---|---|---|
| 8 | Firebase project | In an Airpay Google Cloud organisation; Android and iOS apps registered; `google-services.json` / `GoogleService-Info.plist` never committed | **Airpay** IT creates; **us** register the apps | Airpay IT | M4 |
| 9 | FCM HTTP v1 server credential | The legacy FCM API was shut down from 22 Jul 2024; v1 uses a service account with short-lived tokens; stored in server config, never in the repository | **Airpay** IT | Airpay IT | M4 |
| 10 | APNs authentication key (.p8) | Downloadable once; uploaded to Firebase (Cloud Messaging settings) | **Airpay** IT | Two custodians + vault | M4 |
| 11 | Apple distribution certificate + App Store profile | App ID with Push Notifications and Associated Domains | **Us**, in Airpay's account | Airpay vault; CI reads as secrets | M9 |
| 12 | App Store Connect API key (.p8) | For CI uploads with Fastlane | **Airpay** creates | CI secret | M9 |
| 13 | Play App Signing + upload key | Google holds the app signing key (default for new apps); we hold the upload key (resettable if lost) | **Us** generate | Airpay vault + CI secret | M9 |
| 14 | macOS CI and a Mac | GitHub Actions macOS runner (about USD 0.062 a minute; verify) for builds; one Mac for on-device debugging | **Airpay** IT approves | n/a | M6 |

### 1.3 Public pages (needed before submission, reachable from outside India)

Before cutover these cannot sit on www (still BizLMS). Host them on Airpay's corporate website or the app host (D30-b).

| # | Page | Required by | Content | Owner | Lead time |
|---|---|---|---|---|---|
| 15 | Privacy policy, app section | Apple 5.1.1(i) (store metadata **and** inside the app); Google | What is collected and why; third parties (Firebase); retention and deletion; withdrawing consent; DPDP grievance contact | **Airpay** Legal; **us** supply the data inventory (section 4) | 1-2 weeks |
| 16 | Support URL | Apple; Google contact details | Contact route for learners (tenant L&D) | **Airpay** L&D | 1 day |
| 17 | Account deletion page | Google (web link that works without the app) | How to request account and data deletion; retention exceptions | **Airpay** Legal + **us** (wired to the DPDP erasure workflow) | 1 week |
| 18 | Terms of use | Good practice | Per-customer text (footer rule: private and confidential to Airpay) | **Airpay** Legal | 1 week |

### 1.4 Store forms and review

| # | Item | Owner | When |
|---|---|---|---|
| 19 | Apple privacy labels + privacy manifest (`PrivacyInfo.xcprivacy`; uploads without it are rejected since 1 May 2024) | **Us** draft (4.1), **Airpay** Legal review | M10 |
| 20 | Google Data safety form | **Us** draft (4.2), **Airpay** Legal review | M10 |
| 21 | Content rating (IARC), target audience 18+, Apple age-rating questionnaire | **Us** draft, Legal review | M10 |
| 22 | Apple export compliance (each version) | **Us** | M10 |
| 23 | Storefronts: India + Tanzania only | Nitin / CTO | M10 |
| 24 | Review tenant + reviewer accounts (synthetic data, password login, MFA off, never expires, excluded from compliance reports) | **Us** | M10 |
| 25 | App host DNS + TLS (e.g. `app.airpay.academy`) and universal-link / App Links files | **Airpay** IT (DNS, TLS); **us** (files) | before submission |
| 26 | Icon, screenshots, store text (en + hi) | **Airpay** Brand | M10 |
| 27 | 10-20 testers from L&D/HR on TestFlight and Play internal testing | **Airpay** L&D | from M9 |
| 28 | Mobile security test (OWASP MASVS style) | **Airpay** InfoSec | before M10 |
| 29 | Azure AD app registration with the app's redirect URI (SSO) | **Airpay** IT | M6 |

### 1.5 Timeline and cost

| When | What |
|---|---|
| This week | CTO review of this pack; D-U-N-S lookup; name the account holder and two administrators |
| Weeks 1-2 | Apple and Google organisation enrolment; Firebase project; role mailboxes; IT asks for app host, Mac / CI, Azure AD registration |
| Weeks 2-6 | Legal drafts privacy policy, deletion page, terms; Brand assets |
| M9 | Signing, CI, TestFlight and Play testing on UAT with test accounts |
| M10 | Forms, review tenant, security test; submit 2-3 weeks before cutover and hold (D30-a) |

**Cash costs:** Apple USD 99 a year; Google USD 25 once; D-U-N-S free; Firebase free tier; macOS CI minutes; a Mac and
5 test phones (Airpay hardware). No store commission while in-app checkout is off; once it is on for Android, Google's
user choice billing fee applies (section 2A). The native app is outside the
spend-annex envelope (`docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md`), so it needs its own budget line.

---

## 2. D29: payments in the app (India)

### 2.1 What the store rules say (paraphrased)

| Rule | Apple App Store | Google Play |
|---|---|---|
| Digital content unlocked in the app | 3.1.1: must use Apple in-app purchase | Payments policy: must use Play Billing (education included) |
| Steering to other payment methods inside the app | 3.1.1(a): no buttons, links or calls to action, except where Apple grants an entitlement (EU, South Korea, Netherlands dating apps) and in the US. **None found for India** | No leading users to another payment method (listings, promotions, webviews, links, sign-up flows), except through regional programs. **India: user choice billing only.** The external-link programs cover AU, JP, UK, EEA and the US; **not India** |
| Reader / consumption-only | 3.1.3(a): magazines, newspapers, books, audio, music, video. **Courses are not listed** | Consumption-only apps allowed: log in and use content paid for elsewhere, nothing buyable in the app |
| Multiplatform | 3.1.3(b): content bought on the web may be used in the app **only if also sold by in-app purchase** | n/a |
| Enterprise | 3.1.3(c): organisations buying for employees may use previously bought access | No purchase, not relevant |
| Physical goods and services | 3.1.3(e): must **not** use in-app purchase (Airpay gateway fine) | Play Billing must not be used |
| One-to-one live services | 3.1.3(d): other payment methods allowed | Allowed if not recordable or replayable |
| Telling users outside the app | Allowed | "Outside of your app, you are free to communicate with your users about alternative purchase options" (Google FAQ) |

### 2.2 Legal status in India (latest reporting found)

- **Apple:** the CCI reportedly found the payment rule abusive in 2024. The Delhi High Court told the CCI in May 2026 not
  to pass a final order before 15 July 2026. **No final order or remedy in force was found.**
- **Google:** CCI order of October 2022; NCLAT largely upheld it on 28 March 2025 (penalty cut to INR 216.69 crore).
  Cross-appeals in the Supreme Court were **pending as of 9 Oct 2026**. The remedy in practice is user choice billing.
- **Result:** nothing in India today lets an app send users to a website to buy digital courses without the store's
  billing. Re-check before M1 and again before M10.

### 2.3 What is allowed today, by audience

| Scenario | iOS (India) | Android (India) |
|---|---|---|
| Employees (Airpay /1, ZEEA /177), employer-provided, free | **Allowed** (3.1.3(c); Azure AD sign-in fits the 4.8 enterprise exception) | **Allowed** |
| Consumer buys a digital course inside the app | Apple in-app purchase required | Play Billing, or Play Billing **plus** Airpay as user choice billing |
| Consumer redirected from the app to the Airpay gateway (your D29 answer) | **Not allowed** | **Not allowed as a link-out**; only as user choice billing |
| Consumer buys on the web; the app says nothing about buying; the course opens in the app | **Unclear**: courses are not a reader category; a third-party guide reports rejections; no Apple statement allowing it found | **Allowed** (consumption-only) |
| E-mail, WhatsApp or SMS about web purchases | Allowed | Allowed |
| Classroom seat, printed certificate, physical kit | Airpay gateway required | Airpay gateway required |
| Live virtual group class | In-app purchase | Play Billing or user choice billing |

**Store fees (only on sales inside the app):** Apple 30%, or 15% in the Small Business Program (under USD 1M a year)
and on subscriptions after a year. Google 15% on the first USD 1M a year and on subscriptions, 30% above (third-party
source for India; confirm in Play Console); user choice billing is 4 points lower.

### 2.4 Options compared

| Option | iOS | Android | Store fee | Effort (estimate) | Verdict |
|---|---|---|---|---|---|
| A. Link out to Airpay's web checkout | Not allowed | Not allowed as a link-out | n/a | n/a | **Not compliant** |
| B. Reader model only | Rejection risk (3.1.1) | Allowed | 0 | S | Android only |
| C. Apple in-app purchase for paid consumer courses | Compliant | n/a | 15-30% on iOS app sales | 6-10 d | Worth it once there is mobile consumer sales volume |
| D. User choice billing (Airpay next to Play Billing) | n/a | Compliant | Standard minus 4 points | 8-12 d | Only if Android sales justify it |
| E. Store billing only | Compliant | Compliant | 15-30% | Large | Most work, highest fee |
| F. **Learn in the app, buy on the web, nothing in the app points at buying** | Compliant **if** iOS hides consumer courses bought on the web (or adds C) | Compliant | 0 | 1-2 d | **Recommended for v1** |

### 2.5 Recommended design for v1

1. **No commerce in the app:** no prices, "Buy", cart, coupons, checkout, order history or "available on our website",
   including in the sign-up flow (Google names sign-up flows as a steering place).
2. **Block purchase pages inside the app.** In theme app mode (M5), hide prices, cart icons and buy buttons, and return
   a neutral page for cart and checkout routes. In the app, a navigation allowlist excludes `/local/sentientia_cart/` and
   `/payment/gateway/airpay/`. Without this, the in-app content view could reach checkout, which counts as steering.
3. **No promotions in the app inbox or push.** Cart reminders and sale messages go only by e-mail, WhatsApp or SMS.
4. **App catalogue** shows free courses and courses the learner is assigned or enrolled in. Paid courses the learner
   does not own are not listed.
5. **Courses bought on the web:** shown on Android; **hidden on iOS v1** for Public-tenant consumers until option C
   exists. Employee and ZEEA content is shown.
6. **Physical items and in-person seats** may later be paid through the Airpay gateway in the app on both stores (not v1).
7. **Buying stays on the website and PWA** (`payment/gateway/airpay`, `local/sentientia_cart`); store rules do not apply there.
8. **Revisit C and D** when Public-tenant mobile sales data exists, or when the Supreme Court or CCI changes the rules.

**Plan impact:** session M8c changes from "purchase redirect" to "store compliance": commerce suppression in app mode,
iOS rule for web-bought consumer content, commerce filter on inbox and push, and in-app account deletion (2-3 days).

---

## 2A. Decision D29 (Nitin, 19 Nov): in-app browser + Airpay PG

**Decision (15:05).** Public-tenant consumers buy paid courses inside the app. The app opens Airpay's checkout
(`local/sentientia_cart`, `payment/gateway/airpay`) in an in-app browser view (Chrome Custom Tabs on Android,
SFSafariViewController on iOS), and the course unlocks in the app after payment. No Apple in-app purchase, no Play
Billing. The section 2.5 design becomes the fallback for when checkout is switched off.

### 2A.1 Review risk per store (India; paraphrased from 2.1 and 2.2)

| Store | How review is likely to read it | Risk |
|---|---|---|
| Apple App Store | Buying digital content in an in-app browser is treated like a link-out: the checkout is a button or link to a non-Apple purchase method (3.1.1(a)) for content unlocked in the app (3.1.1). Apple grants the external-purchase entitlement only in named regions; none for India was found. Expect rejection under 3.1.1 at first review, or at any later update review | **High** |
| Google Play | Leading users to another payment method through a webview or link is not allowed. In India the only route is **user choice billing**: Play Billing shown next to Airpay's checkout in the same purchase flow, enrolment in the program, Google's fee at standard minus 4 points, PCI DSS for Airpay's checkout, each transaction reported to Google within 24 hours. Without Play Billing alongside, expect rejection, removal or a policy strike on the account | **Medium**: compliant only with Play Billing alongside |

Not affected: classroom seats, printed certificates and physical kits may use the Airpay gateway in the app on both
stores (3.1.3(e); Play Billing must not be used for them). Legal status is unchanged (2.2): no Indian order in force lets
an app send users to its own checkout for digital content. Re-check before M1 and M10.

### 2A.2 Mitigations

1. **Ask before building.** The account holder (D27-a) sends a written pre-submission question to Apple App Review and
   to Google Play policy support, with screenshots of the flow, the India storefront and the Public tenant. Record both
   answers before M8c starts.
2. **Feature flag per platform.** In-app checkout ships behind a server-side flag (default OFF) that switches it off
   for iOS and Android separately, without a new app build. Off means the section 2.5 design: no prices, no buy
   buttons, buying on the web. The flag switches checkout off; it must never hide checkout from reviewers. The build
   under review shows what users will see (Apple 2.3.1: no hidden features).
3. **Android first.** Turn purchases on for Android first, as user choice billing with Play Billing alongside (option
   D, 8-12 days, estimate). iOS keeps checkout off until Apple answers yes or a CCI or Supreme Court ruling changes the
   rules; until then iOS follows D29-b. If iOS sales are needed sooner, Apple in-app purchase (option C, 6-10 days) is
   the compliant route.
4. **Employee use unaffected.** It is one app for all tenants (D28-j), but checkout shows only to Public-tenant
   consumers. Airpay (/1) and ZEEA (/177) users see no prices or checkout and stay compliant on both stores (3.1.3(c);
   Google consumption-only).

### 2A.3 What the CTO should confirm

1. Airpay accepts the iOS rejection risk, or approves an iOS launch with checkout off until Apple answers
   (recommended).
2. Airpay PG can run as Google user choice billing: its PCI DSS status (section 6: not found), 24-hour transaction
   reporting to Google, and the fee (with Finance).
3. Budget for Play Billing next to Airpay on Android (option D), and for Apple in-app purchase later if iOS sales are
   needed (option C).
4. Who sends the pre-submission questions to Apple and Google, and by when (before M8c).
5. Airpay Legal reviews this section with 2.1 and 2.2 (this pack is not legal advice).

**Plan impact:** M8c changes again: in-app browser checkout behind per-platform flags, Play Billing next to Airpay on
Android, and the section 2.5 rules as the "checkout off" state. About 10-15 days instead of 2-3 (estimate; re-scope
after the CTO answers). In-app account deletion (D29-d) stays. When checkout is on, the Purchases privacy label (4.1)
and the Financial info answer in Data safety (4.2) change from "None".

**Sources (all in section 7):** Apple App Review Guidelines 2.3.1, 3.1.1, 3.1.1(a), 3.1.3(c), 3.1.3(e); Apple StoreKit
external purchase entitlement page; Apple forum thread 759766; BuddyBoss guide to 3.1.1. Google Play payments policy
(answer 9858738), payments FAQ (10281818), India billing (13306652), user choice billing (13821247), billing choice
program (17161464). Legal status: MediaNama (May 2026, Delhi High Court and the CCI) and Outlook Business (Supreme Court
cross-appeals).

---

## 3. Account deletion and reviewer accounts

- **Apple 5.1.1(v):** if the app supports account creation, deletion must be offered inside the app.
  **Google:** an in-app path **plus** a web page; directing users to sign up outside the app still counts as account creation.
- **Recommendation:** a "Delete my account" button for self-registered Public accounts, wired to the existing DPDP
  erasure workflow. For employer-managed accounts (Airpay, ZEEA), a page explaining that the employer manages the
  account, with the L&D / DPO contact. Neither store addresses employer-managed accounts (not found); explain it in the
  review notes.
- **Reviewer accounts** (Apple "App Review Information", Google "App access"): a synthetic review-tenant user with
  password login, MFA off, never expiring, enrolled in a sample SCORM package, quiz and certificate, no real employee
  data, excluded from compliance reports. Review notes say the app is B2B for employees and sells nothing.

---

## 4. Draft privacy and data-safety answers (for Legal review)

### 4.1 Apple privacy labels

No data is used for tracking and there are no advertising SDKs, so no App Tracking Transparency prompt.

| Apple data type | What it is in Sentientia | Purpose | Linked to user |
|---|---|---|---|
| Contact Info: Name, Email Address | Profile | App Functionality | Yes |
| Identifiers: User ID | Moodle user ID, employee ID | App Functionality | Yes |
| Identifiers: Device ID | FCM token / Firebase installation ID | App Functionality (push) | Yes |
| Usage Data: Product Interaction | Progress, completions, quiz attempts | App Functionality | Yes |
| User Content: Other User Content | Quiz answers, feedback, forum posts (confirm v1 scope) | App Functionality | Yes |
| User Content: Photos or Videos | Only if the app uploads a profile photo (confirm) | App Functionality | Yes |
| Diagnostics: Crash Data, Performance Data | Crash reporting **without a user ID** | App Functionality / Analytics | No |
| Other Data | Designation, department, if shown (confirm) | App Functionality | Yes |
| Purchases | **None in v1** | n/a | n/a |

Also required: the privacy manifest declaring "required reason" API use. Third-party SDKs ship their own; Capacitor
documents a scanner for this.

### 4.2 Google Data safety

| Category | Data type | Collected / shared | Purpose |
|---|---|---|---|
| Personal info | Name, email, user IDs (employee ID) | Collected; not shared (Firebase acts as our service provider) | App functionality, account management |
| App activity | App interactions (progress), other user-generated content | Collected | App functionality |
| App info and performance | Crash logs, diagnostics | Collected | App functionality, analytics |
| Device or other IDs | FCM token / installation ID | Collected | App functionality |
| Financial info | None in v1 | n/a | n/a |
| Security | Encrypted in transit: **Yes**. Users can request deletion: **Yes** (in the app and on the web page) | | |

### 4.3 Ratings, age and export

- **Google:** IARC questionnaire; target audience **18+** (any under-13 audience triggers the Families policy). If
  forums or chat ship, answer "users can interact".
- **Apple:** the 2025 age-rating questionnaire (4+, 9+, 13+, 16+, 18+). Keep the in-app browser limited to our own
  domains so "unrestricted web access" can be answered No.
- **Export compliance:** if the app uses only operating-system TLS and keychain, set `ITSAppUsesNonExemptEncryption =
  false` (verify against Apple's documentation, which could not be fetched).

### 4.4 India (DPDP Act 2023, DPDP Rules 2025) and Tanzania

- **Timing:** the Rules were notified 13/14 Nov 2025; most duties (notice, consent, security, breach reporting) apply
  from **about 13 May 2027**. Design for them now.
- **Employees (Airpay):** Section 7(i), legitimate use for employment, covers training and compliance tracking without
  consent; a clear notice is still recommended.
- **Consumers (Public):** consent with a Section 5 / Rule 3 notice (what and why, how to withdraw, how to complain to
  the Data Protection Board), in plain English or a scheduled Indian language; withdrawing must be as easy as consenting.
- **Children:** under 18 needs verifiable parental consent (Section 9). Confirm 18+ at Public sign-up in v1.
- **Firebase:** accept Google's data processing terms; keep personal data out of push payloads (send an ID, fetch the
  text after the app opens).
- **ZEEA (Tanzania):** Tanzania's data protection law was **not researched**. Flag for legal review.

---

## 5. Decisions (choose and approve)

**D27: paperwork**

| # | Question | Options | Recommended |
|---|---|---|---|
| D27-a | Who owns the store accounts | Authorised signatory (CTO or delegate) with Airpay role mailboxes and at least two Airpay administrators / a named individual | **Signatory + role mailboxes + two admins** |
| D27-b | Store countries | India + Tanzania / worldwide | **India + Tanzania** (avoids EU trader-status and US setup) |
| D27-c | App age / target audience | 18+ / 13+ | **18+** (avoids the Families policy and DPDP child consent) |
| D27-d | Crash reporting | Crashlytics without user identifiers / none in v1 | **Crashlytics without user identifiers** |
| D27-e | Future white-label customers | Each customer's own developer account / one picker app | **Each customer's own account** (record in ADR-040) |
| D27-f | Scaffold | Retarget to API 36 and Capacitor 8, drop remote-URL mode, in M6 / patch the old scaffold | **Rebuild in M6** |
| D27-g | Signing key custody | Airpay IT vault, CI reads secrets / build team holds keys | **Airpay IT vault** |
| D27-h | Where the privacy, support and deletion pages live before cutover | Airpay corporate website / the app host | **Corporate website**, moved to www at cutover |

**D29: payments**

| # | Question | Options | Recommended |
|---|---|---|---|
| D29-a | How consumers pay, given the store rules | (1) Buy on the web, nothing in the app points at buying; (2) Android user choice billing with Airpay; (3) Apple in-app purchase for paid courses; (4) redirect to the Airpay gateway from the app (your original answer) | **Decided 15:05: (4) as an in-app browser to Airpay PG** (section 2A), with (2) on Android and (1) as the fallback when checkout is off. Recommended was (1) for v1: (4) is not compliant on iOS in India and works on Android only as (2) |
| D29-b | On iOS, consumer courses bought on the web | (a) hidden in iOS v1; (b) shown, accepting rejection risk; (c) add Apple in-app purchase | **(a)** (no rejection risk; commerce is dark at go-live) |
| D29-c | Classroom seats or physical items paid in the app through Airpay (allowed on both stores) | Now / later | **Later** |
| D29-d | In-app account deletion for self-registered (Public) accounts | Yes / no | **Yes** (required by both stores; reuse the DPDP erasure workflow; 1-2 days) |
| D29-e | Commerce messages in the app inbox or push | Allow / filter out | **Filter out** (e-mail, WhatsApp, SMS only) |

---

## 6. Not found, or to verify before acting

- Any Apple statement allowing web-bought courses in an app without in-app purchase (an Apple forum question from July
  2024 is unanswered); any Apple external-purchase entitlement for India.
- Whether the CCI anti-steering direction against Google survived NCLAT; the Supreme Court result (pending 9 Oct 2026);
  any CCI final order against Apple after 15 Jul 2026.
- Google's fee rates for India in 2026 (the official page says only "4 points lower").
- Whether organisation Play accounts are exempt from the 12-tester / 14-day testing rule (third parties say yes).
- Apple's encryption documentation and the Firebase v1 migration guide could not be fetched; dates and keys come from
  search summaries and a mirror.
- Whether the Airpay gateway is PCI DSS certified (needed only for Android user choice billing).
- Tanzania data protection law.

---

## 7. Sources

**Apple:** https://developer.apple.com/app-store/review/guidelines/ (2.1, 2.3.1, 3.1.1, 3.1.1(a), 3.1.3(a)-(g), 4.2, 4.2.6,
4.8, 5.1.1, 5.2.1) · https://developer.apple.com/support/storekit-external-entitlement/ ·
https://developer.apple.com/support/unlisted-app-distribution/ · https://developer.apple.com/testflight/ ·
https://developer.apple.com/help/account/membership/program-enrollment/ · https://developer.apple.com/support/D-U-N-S/ ·
https://developer.apple.com/support/offering-account-deletion-in-your-app/ · https://developer.apple.com/app-store/app-privacy-details/ ·
https://developer.apple.com/help/app-store-connect/manage-app-information/overview-of-export-compliance ·
https://capacitorjs.com/docs/ios/privacy-manifest · https://9to5mac.com/2025/07/24/apple-notifies-developers-of-new-app-store-age-rating-system/ ·
https://buddyboss.com/docs/app-store-guideline-3-1-1-business-payments-in-app-purchase/ · https://developer.apple.com/forums/thread/759766

**Google:** payments policy https://support.google.com/googleplay/android-developer/answer/9858738 · payments FAQ
https://support.google.com/googleplay/android-developer/answer/10281818 · India billing
https://support.google.com/googleplay/android-developer/answer/13306652 · user choice billing
https://support.google.com/googleplay/android-developer/answer/13821247 · billing choice program
https://support.google.com/googleplay/android-developer/answer/17161464 · account information
https://support.google.com/googleplay/android-developer/answer/13628312 · testing requirement
https://support.google.com/googleplay/android-developer/answer/14151465 · developer verification
https://android-developers.googleblog.com/2026/03/android-developer-verification-rolling-out-to-all-developers.html ·
target API https://support.google.com/googleplay/android-developer/answer/11926878 · Play App Signing
https://support.google.com/googleplay/android-developer/answer/9842756 · account deletion
https://support.google.com/googleplay/android-developer/answer/13327111 · Data safety
https://support.google.com/googleplay/android-developer/answer/10787469 · content ratings
https://support.google.com/googleplay/android-developer/answer/9859655 · target audience
https://support.google.com/googleplay/android-developer/answer/9867159 · FCM v1 migration (mirror)
https://firebase.google.cn/docs/cloud-messaging/migrate-v1?hl=en

**India competition and data protection:** https://www.medianama.com/2026/05/223-delhi-hc-stops-final-cci-order-against-apple/ ·
https://www.medianama.com/2026/08/223-apple-eu-app-store-fees/ ·
https://www.livelaw.in/high-court/delhi-high-court/delhi-high-court-defers-hearing-in-apples-plea-against-global-turnover-penalty-rule-to-january-27-513484 ·
https://www.outlookbusiness.com/corporate/sc-admits-google-cci-adif-cross-appeals-in-play-store-antitrust-case-hearing-in-november ·
https://www.legal500.com/intelligence/india/antitrust-competition-law/adjudication-or-legislation-googles-play-store-battle-before-the-cci-and-the-future-of-digital-competition-law-in-india ·
DPDP Rules 2025 https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf ·
https://www.azbpartners.com/bank/update-indias-digital-personal-data-protection-framework-comes-into-effect/ ·
https://www.barandbench.com/view-point/navigating-legitimate-use-exemption-employee-data-digital-personal-data-protection-act-2023

**Other:** GitHub Actions runner pricing https://docs.github.com/en/enterprise-server@3.16/billing/reference/actions-runner-pricing

**Internal records:** `moodle-enhancement/mobile/sentientia-app/` (`capacitor.config.ts`, `package.json`,
`android/app/build.gradle`, `.env.example`) · `moodle-enhancement/docs/MOBILE-PUSH-SETUP.md` ·
`moodle-enhancement/PROJECT-STATE.md` ("commerce dark at go-live") ·
`moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` · `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md`
