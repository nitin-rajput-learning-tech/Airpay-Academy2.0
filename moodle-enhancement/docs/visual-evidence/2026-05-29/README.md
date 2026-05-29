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
