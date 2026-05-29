# Visual evidence — 2026-05-29

## F-024 — sentientia_live analytics (trainer run.php result panels)

Closes the last open "partial" item from
`docs/audits/BUCKET-F-CLOSEOUT-2026-05-28.md`. The audit asked: "walk
the analytics page; list gaps." This is that walk, with real-browser
evidence.

**Method:** drove a real Chrome (chrome-devtools MCP) against the local
Moodle, logged in via the actual airpayux login form (a throwaway QA
siteadmin, created + deleted via `tools/f024_qa_account.php`), and
walked the trainer runner for the seeded demo session 18
("QA Demo — 6 Question Types", join code 800 844, 6 slides, 4
participants, 17 responses).

**What "analytics" actually is.** There is **no** standalone
`local/sentientia_live/admin/analytics.php` page (the Bucket F draft
referenced one that was never built). The Sentientia Live "analytics"
surface is two things, both of which now have evidence:

1. **`trainer/run.php` live result panels** — the per-slide
   visualisation the trainer projects. One result-panel template per
   question type. Shown below.
2. **`trainer/export.php`** — the CSV download (E.7, shipped + tested
   under task #124).

### Screenshots

| File | What it shows |
|------|---------------|
| `f024-run-slide1-multichoice-desktop.png` | Multiple-choice result panel — live bar chart, 3 responses, "Favourite colour?" |
| `f024-run-slide5-quiz-leaderboard-desktop.png` | Quiz result panel (richest analytics view) — bar chart + "2 of 3 got it right (67%)" + "Correct answer: Paris" + leaderboard (Alice #1, Carol #2) |
| `f024-run-slide2-wordcloud-desktop.png` | Word-cloud result panel |
| `f024-run-quiz-mobile-590.png` | Quiz result panel at the 590px primary mobile breakpoint |

### Findings

- ✅ Result panels render correctly with real seeded data for
  multichoice, quiz (incl. leaderboard + correct-answer summary), and
  word cloud. The other three flagged types (rating, ranking,
  open-ended) were verified in the prior session (task #304) and share
  the same `result_panel` renderable + per-type template path
  (tasks #104/#105), so the rendering contract is uniform.
- ✅ **Zero JS console errors/warnings** on run.php — the
  `chart_updater` AMD module and SSE wiring run clean.
- ✅ Mobile (590px): the result panel reflows correctly — chart,
  stats, leaderboard table all stack and stay legible.
- ✅ `set_current.php` slide-advance works (used it to walk between
  the 6 slides).

### Byproduct finding (logged in the F-024 closeout)

`local/airpay_core/cli/mint_session.php` is **broken on this box** and
cannot mint a usable browser session here:

1. `$CFG->dbsessions = 0` → Moodle uses **file** sessions, so the
   `sessions` DB row the tool inserts is never read.
2. `session.serialize_handler = php` (pipe format) → the tool's
   `base64_encode(serialize($array))` payload is the wrong format even
   when DB sessions are on.

The walk worked around this by authenticating through the real login
form with a throwaway QA siteadmin account, created + torn down via
`tools/f024_qa_account.php` (LOCAL-DEV-ONLY, refuses on production
wwwroot). The account was deleted immediately after the walk;
`mdl_user` holds no `f024qa` row and `$CFG->siteadmins` is back to its
prior value.

### Verdict

F-024 → **RESOLVED**. The analytics surface (run.php panels + CSV) is
real, populated, console-clean, and mobile-responsive. No standalone
analytics dashboard exists, and none is needed for v1 — the live
result panels are the analytics. Bucket F now has **zero** open
"partial" items.

---

## C4 / F-004 — public.php guest storefront → LXP/Netflix restyle

Closes Bucket C / C4 ("course catalog should look like Netflix of
learning"). Per the scoping finding
(`docs/audits/C4-CATALOG-NETFLIX-SCOPING-2026-05-29.md`), the
logged-in member catalog (`index.php` + `catalog.mustache`) was
*already* a full Netflix-LXP; the only gap was the **guest/public**
storefront (`public.php`) — the consumer's first impression, which
was still a plain inline-styled grid. C4 brings that one page up to
the member catalog's visual language.

**Mechanism — feature-flagged, default OFF (CLAUDE.md §13).** Flag
`sentientia.catalog.public_lxp.enabled` (registered in
`local/airpay_catalog/db/feature_flags.php`, default `false`). OFF =
today's production look, byte-for-byte. ON = the LXP storefront. The
flag was flipped ON only to capture this evidence, then reverted to
default OFF via `feature_flags::set(..., null, ...)` (which deletes
the global override row + writes an audit row). Verified back OFF
after capture.

**Method:** real Chrome (chrome-devtools MCP), guest / no-login,
isolated browser context, against local Moodle. Captured the LXP
storefront (flag ON) at desktop 1280px + mobile 590px, then reverted
the flag and re-captured the legacy grid (flag OFF) for a clean
before/after pair.

### Screenshots

| File | Flag | What it shows |
|------|------|---------------|
| `c4-public-storefront-lxp-desktop.png` | **ON** | LXP storefront, 1280px — "183 courses available", search + Popular/Newest/A-Z sort pills, a 🔥 "Popular picks" scroll-snap carousel rail (8 cards, Scroll-left/right arrows, Free badges, enrolled counts, "Enrol free" CTAs), then a "Browse all courses" grid + Next pager |
| `c4-public-storefront-lxp-mobile-590.png` | **ON** | LXP storefront full page at the 590px primary mobile breakpoint — carousel + grid stack single-column |
| `c4-public-storefront-lxp-mobile-590-abovefold.png` | **ON** | LXP storefront above-the-fold at 590px — heading, search, sort pills, first "Popular picks" card (gradient header, Free badge, 121-enrolled pill, "Enrol free") |
| `c4-public-storefront-legacy-OFF-desktop.png` | OFF | The default production look — plain card grid (Details + Enroll per card), preserved byte-for-byte |

### Findings

- ✅ LXP path reuses the member catalog's `airpay-catalog__*` BEM card
  + carousel components — no new design, exactly the "make the
  storefront look like the member catalog we already shipped" mandate.
- ✅ Commerce preserved in both modes: price (Free / amount), Add-to-
  cart / Enrol-free CTA, cart pill. Data source stays
  `commerce::get_public_catalog()`.
- ✅ **Zero JS console errors** on the LXP path — only Moodle's
  standard guest-layout logs ("missing drawer region/toggle",
  session-timeout init). The inline carousel-arrow AMD
  (`$PAGE->requires->js_amd_inline()`) runs clean.
- ✅ Mobile 590px: carousel rail + grid reflow to single-column;
  CTAs stay tappable; gradient card headers legible.
- ✅ Flag OFF renders the legacy grid identically to production
  (verified post-revert) — additive, non-breaking.
- 🐛 **Side-benefit fix (LXP path only):** the legacy grid's
  Add-to-cart link is malformed — `course.php?id=71?action=addtocart`
  (double `?`, so PHP swallows `action` into the `id` value and the
  cart action silently no-ops). The LXP path builds it correctly via
  `moodle_url('/local/airpay_catalog/course.php', ['id'=>…,
  'action'=>'addtocart', 'sesskey'=>sesskey()])`. The C4 session kept
  the legacy OFF path's quirk deliberately (byte-for-byte production
  parity); a follow-up now fixes it there too — see "C4 follow-up —
  legacy (flag-OFF) add-to-cart URL fix" below.

### Verdict

C4 / F-004 → **SHIPPED (flag-gated, default OFF)**. The guest
storefront now has an LXP/Netflix treatment matching the member
catalog, behind `sentientia.catalog.public_lxp.enabled`. Default OFF
preserves production exactly; flip ON per-customer/tenant when signed
off. Ready for owner greenlight to enable.

---

## C4 follow-up — legacy (flag-OFF) add-to-cart URL fix

The C4 session above deliberately left the legacy grid's malformed
add-to-cart URL in place (the 🐛 bullet) to keep the flag-OFF path
byte-for-byte with production. This follow-up fixes the legacy path
too — because production runs the OFF path **today**, and the bug means
guests cannot add **paid** courses to cart (the click silently no-ops
and just lands on the course detail page).

**Change:** `public.php` legacy branch, one line. The hand-concatenated
`s($course['detailurl']) . '?action=addtocart&sesskey=' . sesskey()`
(which yielded `course.php?id=71?action=addtocart…` — a double `?`) is
replaced with the exact `moodle_url()` construction the LXP path already
uses:

```php
s((new moodle_url('/local/airpay_catalog/course.php', [
    'id' => $course['id'], 'action' => 'addtocart', 'sesskey' => sesskey(),
]))->out(false))
```

**Visual delta:** none. The button, its label ("Enroll" / "Add to
Cart"), position and styling are unchanged — only the `href` target.
`c4-public-storefront-legacy-OFF-desktop.png` therefore still represents
the page exactly; the meaningful evidence is the href value, below.

**Verification.** This remote container has no XAMPP/Moodle/browser, so
real-browser click-through could not run here. Instead:

- `php -l public.php` → no syntax errors.
- A standalone PHP harness replicating `course.php`'s param handling
  (`parse_str` of the query PHP sees → PARAM_INT on `id` → PARAM_ALPHA
  on `action` → `$action === 'addtocart'` gate + `require_sesskey()`):

  | | `href` | `$_GET` PHP parses | add-to-cart |
  |--|--------|--------------------|-------------|
  | BEFORE | `course.php?id=71?action=addtocart&sesskey=…` | `id="71?action=addtocart"`, `sesskey="…"` — **no `action`** | ❌ silent no-op |
  | AFTER  | `course.php?id=71&action=addtocart&sesskey=…` | `id="71"`, `action="addtocart"`, `sesskey="…"` | ✅ fires |

**Still TODO on the local box (cannot run remotely):** deploy to XAMPP,
purge caches, click "Enroll" on a paid course as a guest, confirm the
cart pill increments + the resolved href is `?id=N&action=addtocart&…`,
and drop a fresh `c4-legacy-OFF-addtocart-AFTER-desktop.png` here.

```powershell
Copy-Item "D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_catalog\public.php" `
          "C:\xampp\htdocs\moodle5\public\local\airpay_catalog\public.php" -Force
php "C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php"
# Then: guest-browse public.php → paid course → "Add to Cart" → cart count +1
```

**Production-ship status:** this **changes the default (flag-OFF)
production behaviour**, so it is gated on Nitin's go/no-go (CLAUDE.md
§13 — "NEVER break Airpay Academy current production behaviour"). Once
`sentientia.catalog.public_lxp.enabled` flips ON the bug is moot (the
LXP path was already correct), so the fix matters only for the window
before the flag flips.

---

## Signup-flow UI fixes (2026-05-29) — owner-reported

Five defects Nitin reported live across the public self-registration
flow (`local/airpay_users/signup.php` + the airpayux login layout).
Real-browser verified at the reported laptop viewport (≈609px tall,
dark mode).

| # | Defect (reported) | Root cause | Fix |
|---|-------------------|------------|-----|
| A | Empty unlabeled text field between ToS checkbox and buttons | Honeypot hide CSS used `.fitem_id_honeypot_url` (class) but Moodle's wrapper is `id="fitem_id_honeypot_url"` → matched nothing → honeypot visible | `signup_form.php`: `.` → `#` selector |
| B | Must zoom to 75% to see the whole card | Signup wrapper `align-items:center` + a form taller than the viewport → card top clipped 413px **above** the scroll origin (flex-centring overflow trap) | `_surface-login.scss`: `#page-signup` wrapper → `align-items:flex-start` + 40px padding |
| C | Success message shown **twice** | `redirect()` queued the message as a flash AND the success view rendered it again | `signup.php`: drop the message arg from `redirect()` |
| D | Stray `▬` glyph after the success text | `$OUTPUT->notification()` is a **dismissible** alert; its close button rendered as a tofu glyph on the dark card | `signup.php`: render a non-dismissible `.alert.alert-success` (`role="status"`) |
| E | "You need to confirm your account" page flush-left + unstyled | `login/index.php` notices render through the login layout but `#page-login-index` resets the region card to `padding:0` (Section 1 expects the split-screen) | `_surface-login.scss`: `:not(:has(.airpay-login))` card rule (light + dark), scoped so the real split-screen login is untouched |

### Screenshots

| File | What it shows |
|------|---------------|
| `signup-form-fixed-abovefold.png` | Form at 609px viewport, 100% zoom — top fields (First/Last/Email/Password) now visible without zooming; **no stray empty field** (A + B) |
| `signup-success-fixed.png` | Success page — **single** green message, clean em-dash, no close-button glyph, "Back to login" CTA (C + D) |
| `signup-confirm-page-fixed.png` | "Confirm your account" now a branded dark card (gradient bg, #1e293b card, 16px radius, 40px 44px padding, legible white heading, gradient CTA) vs the original flush-left/unstyled page (E) |

### Verification (measured in the live DOM)

- **A**: honeypot wrapper `display:none`; ToS checkbox now directly precedes "Create account" (no field between).
- **B**: `pageOffsetTop` −413px → **+40px**; "First name" reachable at scroll 0 / 100% zoom.
- **C/D**: success view has exactly one `role="status"` message, no dismissible close button.
- **E**: confirm card `background:#1e293b` (dark) / `#fff` (light), `padding:40px 44px`, heading `#f1f5f9` (dark) / `#0f172a` (light) — legible in both modes. **Split-screen login untouched** (`:has` guard): on the real login page the rule does not apply (region stays `padding:0`, split-screen bg).

### Notes

- The E fix surfaced (and resolved) a light/dark specificity interaction: the
  card's padding needs a 2-id selector (`#region-main`) to beat a core
  `#page-X #region-main` padding reset, but that id was kept OUT of the
  bg/radius rule so the 1-id dark-mode override still wins by class-count
  (otherwise the dark-mode card rendered white with an invisible light
  heading — caught + fixed during verification).
- No feature flags: these are corrective fixes to the existing
  (already flag-gated) signup feature, not new features.
- Versions: `local_airpay_users` 2.7.0→2.7.1; `theme_airpayux`
  1.0.39-beta→1.0.40-beta.

---

## C-002 v2 — Compliance Report export gate → dedicated capability

Supersedes the inline C-002 fix (QA walk) with a dedicated capability, and
**tightens scope** by owner decision: **admins + Compliance Officers may export;
line managers may VIEW the dashboard but NOT bulk-export PII** (the inline v1 had
let any dashboard-viewer export).

**Plugin:** `local_airpay_compliance_report` `2026041200` → `2026052900`.

### What changed
| File | Change |
|------|--------|
| `db/access.php` *(new)* | Capability `local/airpay_compliance_report:export` (`captype=read`, `RISK_PERSONAL`, `CONTEXT_SYSTEM`, `manager` archetype). |
| `classes/permission.php` *(new)* | `can_export()` — one gate for export.php + the button. Checks the cap at **system context** AND every **category context** where the user holds a role (so a cap granted via the category-assigned Compliance Officer / OrgAdmin shell resolves — a system-context `has_capability()` alone misses it). `grant_export_to_default_roles()` — idempotent install/upgrade grant to course-managers + role 9. |
| `export.php` | Inline C-002 block → `if (!permission::can_export()) throw nopermission;`. Drops phantom `local/courses:manage`. |
| `index.php` | Adds `can_export` to template context. |
| `templates/dashboard.mustache` | Export button wrapped in `{{#can_export}}…{{/can_export}}`. |
| `db/install.php`, `db/upgrade.php` | Grant on install / upgrade to `2026052900`. |
| `lang/en`, `lang/hi` | Capability string (suite convention = en + hi). |
| `tests/permission_test.php` *(new)* | 6 PHPUnit cases incl. the category-context cap + manager-exclusion. |

### Verification (local XAMPP, Moodle 5.1.3+)
**`permission::can_export()` per qa persona — matches the chosen policy:**

| Persona | can_export | | Persona | can_export |
|---------|:---:|---|---------|:---:|
| qa_compliance (3423) | **YES** ✅ | | qa_manager (3420) | **no** ✅ |
| qa_orgadmin (3418) | YES ✅ | | qa_employee (3421) | no ✅ |
| qa_siteadmin (3417) | YES ✅ | | qa_trainer (3419) | no ✅ |
| | | | qa_public (3422) | no ✅ |

**End-to-end export as qa_compliance** (isolated authenticated HTTP session — the
owner's acceptance test):
```
export.php → 200
  Content-Type:        application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
  Content-Disposition: attachment; filename="Compliance_Report_2026-05-29.xlsx"
  body: 22104 bytes   (no nopermission)
```
**Button visibility:** `index.php` (qa_compliance) → 200; rendered HTML contains
the `airpay-compliance-rpt__export` "Export Excel" button → the `{{#can_export}}`
gate shows it for the Compliance Officer (hidden for managers per the table).
Saved artifact: [`compliance-report-qa_compliance.html`](./compliance-report-qa_compliance.html).

DB sanity: cap `REGISTERED`; role 9 (`administrator` locally / Compliance Officer
on prod) has the export rule `perm=1 @ system`.

### Notes
- **No live screenshot** — a user Chrome is connected, but logging it in as
  `qa_compliance` would swap the `MoodleSession` cookie and disrupt the user's
  own session. Used an isolated HTTP session instead (deterministic). A live
  screenshot can be added on request.
- Re-run: `php moodle-enhancement/tools/_verify_export_cap.php` (read-only,
  localhost-guarded).
- **Separate pre-existing bug — now also fixed (C-005):** `export.php?format=csv`
  returned a themed 404 (CSV branch read the matrix with the wrong shape:
  `$course['shortname']` on objects, row keys `empid`/`statuses` that don't
  exist). Unrelated to permissions. Fixed by aligning the CSV branch with the
  proven xlsx data shape (`->coursename`; `employee_id`/`fullname`/`designation`/
  `courses`→`status_label`) + xlsx-parity summary block. Verified: qa_compliance
  `?format=csv` → **200 + text/csv** (33 KB; header + per-course status columns +
  summary). See `../qa-walk-2026-05-29/BUG-LOG.md` C-005.

---

## T-01 + T-02 — Sentientia Live reachable by the BizLMS trainer role

Fixes the trainer-walk cluster blockers (`../qa-walk-2026-05-29/trainer.md` §5/§12):
the `trainer` role (archetype `teacher`) could neither **enter** the live trainer
dashboard (T-01, capability) nor **find** it (T-02, no sidebar link).

**Plugins:** `local_sentientia_live` `2026052504` → `2026052900`;
`theme_airpayux\sidebar_navigation` (no theme version bump — class-only change).

### What changed
| File | Change |
|------|--------|
| `local/sentientia_live/db/access.php` | `'teacher' => CAP_ALLOW` added to `:create` + `:run` archetypes. |
| `local/sentientia_live/db/upgrade.php` *(step 2026052900)* | Back-fills the new default onto existing `archetype=teacher` roles via `assign_capability(overwrite=false)` — archetype defaults only auto-apply on a cap's first install (`lib/accesslib.php::update_capabilities`). |
| `theme/airpayux/classes/sidebar_navigation.php` | New `can_create_live_session()` gate (`live.enabled` flag **+** `:create` cap, both safe-failing); "Live Sessions" → `trainer/index.php` link added to the Manager **and** Learner shells. |

### Verification (local XAMPP, Moodle 5.1.3+)
**T-01 — `has_capability` for qa_trainer (id 3419, role `trainer`/archetype `teacher`):**

| Capability | Before | After |
|------------|:------:|:-----:|
| `local/sentientia_live:create` | NO | **YES** ✅ |
| `local/sentientia_live:run` | NO | **YES** ✅ |

Owner's exact one-liner → `YES`. Upgrade ran clean (`++ 2026052900: Success ++`, `purge_all_caches: Success`).

**T-02 — rendered qa_trainer sidebar (Manager shell):**
```
Dashboard · My Team · Compliance · Live Sessions · ──── · My Courses · Catalog · Certificates · Profile
                                   └─ /local/sentientia_live/trainer/index.php
```

### Notes
- **No live screenshot** — same call as C-002 v2: the QA-walk `chrome-devtools` MCP
  isn't connected this session, and logging the user's own Chrome in as `qa_trainer`
  would swap the `MoodleSession` cookie and disrupt their session. Verification is
  deterministic instead (`tools/_qa_t01_live_capcheck.php` + `tools/_qa_t02_navdump.php`,
  both read-only / localhost-guarded). A live PNG can be added on request before the
  production deploy.
- Sidebar labels follow the file's existing convention (hardcoded English nav strings,
  not `get_string`) — i18n is a separate all-labels pass, not introduced here.
- Feature-flag respected: the link is gated on `live.enabled` (CLAUDE.md §13).

## E-01 + E-03 — one-click free self-enrolment for internal tenants

Fixes the employee-walk P1 (`../qa-walk-2026-05-29/BUG-LOG.md` E-01): an Airpay
employee could not self-enrol in a "Free" course. The catalog "Enroll" button
routed free courses to `course.php?action=addtocart` (session cart) and never
enrolled; the cart's `enrollfree` path then called core `enrol_self()`, which
**silently no-ops on key-gated courses** (course 71 has an `enrol.password` set)
while falsely reporting success.

**Plugin:** `local_airpay_catalog` `2026052901` → `2026052902`.

### What changed
| File | Change |
|------|--------|
| `classes/enrolment.php` *(new)* | `should_offer_oneclick()` policy (logged-in **internal-tenant** user + free course + flag) and `enrol_now()` mechanism — idempotent **manual** enrol that bypasses any self-enrol key, mirroring `cart_manager::enrol_user_in_course()`. |
| `db/feature_flags.php` | New flag `sentientia.catalog.free_oneclick_enrol.enabled` (default **OFF** — OFF reproduces today's cart behaviour byte-for-byte). |
| `course.php` | New `action=enrolnow` handler (require_sesskey → policy check → `enrol_now` → redirect into the course; safe fallback to cart) + a one-click CTA branch on the detail page. |
| `public.php` | Catalog grid button (legacy + LXP paths) points internal-tenant viewers at `?action=enrolnow` for free courses; guests/Public keep `addtocart`. |
| `cart.php` | `enrollfree` rerouted through `enrol_now()` — actually enrols (key bypassed) and reports truthfully (was the silent-success lie). |
| `lang/{en,hi,kn,mr,sw}/…` | +4 strings × 5 languages (`enrol_now_free`, `enrolled_welcome`, `enrolled_count`, `enrolled_none`). |

### Decisions (owner, 2026-05-29)
- **Scope = internal tenants only.** Airpay /1 + ZEEA /177 (any non-Public tenant) get one-click; the Public /77 storefront keeps the cart so its B2C funnel is untouched. The policy is **user-centric** (the viewer's tenant), not course-centric.
- **Key handling = bypass via manual enrol.** Catalog tenant-visibility is the access gate for internal staff.

### Screenshots
| File | What it shows |
|------|---------------|
| `enrol-fix-01-catalog-oneclick.png` | Catalog as logged-in **qa_employee** (Airpay /1) — Learner shell; free-course cards now carry the **"Enrol now — free"** CTA (→ `?action=enrolnow`, confirmed in the a11y snapshot) instead of "Enroll"→cart. |
| `enrol-fix-02-mycourses-enrolled.png` | **My Courses = 2** ("Aptitude Test Advanced" #71 + "POSH Training" #403) after one-click enrol — directly contradicts the original symptom (this page showed *"No courses found"*). |
| `enrol-fix-03-catalog-mobile.png` | Mobile (412 px) — single-column catalog, **"Enrol now — free"** button renders correctly; layout unaffected (no CSS change). |

### Verification (local XAMPP, Moodle 5.1.3+)
- **CLI** (`_verify-enrol.php`, read+write): policy correct (Airpay+free+flag → one-click; Public → cart; paid → cart); `enrol_now(71)` enrolled qa_employee **despite the key**; idempotent; exactly 1 enrolment row. **ALL PASS.**
- **Browser** (real airpayux login as `qa_employee`): catalog button = "Enrol now — free" → `enrolnow`; clicked POSH (403) → enrolled; My Courses → 2 courses.
- **PHPUnit** `tests/enrolment_test.php` (8 cases) written for CI — local box has no `vendor/bin/phpunit` (Composer dev-deps absent), so it runs in the CI `phpunit` job, not locally.
- **HTTP smoke**: `public.php` + `course.php` return 200 with no PHP fatal for guests.

### Notes
- **Flag is enabled for tenant /1 on this local box** (set during verification, so the QA re-walk can exercise it in-browser). **Production must enable** `sentientia.catalog.free_oneclick_enrol.enabled` for the internal tenants (Airpay /1, ZEEA /177) via the Switchboard — until then, OFF preserves current cart behaviour.
- `course.php` keeps the old `/enrol/index.php` path as the fallback for non-internal logged-in users (unchanged behaviour for the Public tenant).

---

## Course-card poster thumbnails (2026-05-29) — owner-requested

Owner ask: *"course cards will have thumbnails for the courses, like movies on
netflix has posters, current catalogues don't have images."* Every course-card
surface showed an identical generic icon (member catalogue) or a flat
text-only tile — no per-course imagery.

**Mechanism.** New shared helper
`local_airpay_catalog\catalog_manager::course_poster($courseid)` returns the
course's uploaded **overview image** (`overviewfiles`) when present; otherwise
`imageurl=''` + `has_image=false`, and always `thumb_variant = id % 6`. Each
card renders a real `<img>` poster (`object-fit:cover`, reduced-motion-safe
hover zoom) when `has_image`, else falls back to one of six on-brand gradient
tiles (`--vN`) with the course code — so an image-less wall still looks varied,
not a block of identical tiles. No new data requirement: real images appear the
moment a course image is uploaded (Course settings → Course image).

**Surfaces wired (every course-card surface):**

| Surface | File(s) | Live by default? |
|---------|---------|------------------|
| Member catalogue grid + trending/new carousels | `templates/course_card.mustache` ← `catalog_manager::format_course()` | yes |
| Member "Continue Learning" carousel | `templates/catalog.mustache` (image behind the progress ring) | yes |
| Public guest storefront LXP cards | `public.php` `$render_card` ← `commerce::get_public_catalog()` | flag `sentientia.catalog.public_lxp.enabled` (default OFF) |
| Dashboard "Featured for you" widget | `local_airpay_courses` `featured_widget.mustache` + `featured_manager` + new `styles.css` | yes (when curated) |
| Guest frontpage "Featured Courses" | `theme/airpayux/layout/frontpage.php` | yes |

**Method:** real Chrome (chrome-devtools MCP) against local Moodle. No
production-import course on this box has an uploaded image (the import left
`overviewfiles` rows whose files were never copied to `filedir`), so
`tools/_poster_demo.php --seed=3` generated gradient posters onto 3 Public
courses to prove the real-image path; the storefront flag was flipped ON only
to capture the storefront, then reverted to default OFF (`tools/_c4_flag_off.php`,
verified back OFF).

### Screenshots

| File | What it shows |
|------|---------------|
| `posters-01-storefront-real-and-fallback.png` | Guest storefront (flag ON), `?q=E000` — DOM-confirmed **3 real `<img>` posters** (600×340, `object-fit:cover`, all loaded OK) + 1 gradient-tile fallback, side by side. |
| `posters-02-dashboard-featured-widget.png` | Dashboard "Featured for you" — 3 cards each with a gradient poster header (FO_HR001 / A0001 / A0002), "Featured demo" badge, Enrol/Preview. Previously flat text-only cards. |
| `posters-03-guest-frontpage-featured.png` | Guest frontpage "Featured Courses" — `.ap-course` cards with 150px poster headers (COMPLIANCE / FINANCE / LEADERSHIP gradients). |

### Findings

- ✅ Member catalogue: all 12 grid cards carry a variant poster thumb
  (`variant_thumbs:12`, spread `--v1…--v5`); gradient+code fallback renders for
  image-less courses; zero broken images.
- ✅ Storefront (flag ON): real `<img>` posters render `object-fit:cover` (no
  distortion) next to gradient fallbacks; legacy grid byte-for-byte when OFF.
- ✅ Featured widget + frontpage: poster headers render, no layout break;
  overlay badges/type/ring/difficulty stay above the poster (z-index).
- ✅ Reduced-motion-safe hover zoom (catalogue uses `var(--ap-transition-slow)`,
  which collapses to 0ms under `prefers-reduced-motion`).

### Verdict

**SHIPPED.** Course cards now have poster thumbnails across every surface.
Default behaviour preserved (image-less → on-brand gradient tile; real image
appears automatically when uploaded). `theme_airpayux` 1.0.41-beta →
1.0.42-beta.

**Note on `public.php`:** its poster hunk is intentionally left in the working
tree (uncommitted) to land with the owner's in-flight E-01 one-click-enrol
commit — E-01 spans `public.php` + the untracked `enrolment.php`/`course.php`,
so committing `public.php` alone would be a broken partial. The data layer
(`commerce::get_public_catalog`) IS committed, and the storefront LXP path is
flag-OFF by default, so committed default behaviour is unaffected.
