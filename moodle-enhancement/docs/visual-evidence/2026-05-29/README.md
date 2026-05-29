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
  'action'=>'addtocart', 'sesskey'=>sesskey()])`. The legacy OFF path
  keeps the quirk deliberately (byte-for-byte production parity); the
  fix ships only in the flag-gated LXP path.

### Verdict

C4 / F-004 → **SHIPPED (flag-gated, default OFF)**. The guest
storefront now has an LXP/Netflix treatment matching the member
catalog, behind `sentientia.catalog.public_lxp.enabled`. Default OFF
preserves production exactly; flip ON per-customer/tenant when signed
off. Ready for owner greenlight to enable.
