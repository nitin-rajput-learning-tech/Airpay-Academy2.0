# Visual evidence — 2026-06-02

## eAbyas/epsilon theme decoupling — pixel-confirmation

After the file-serve decoupling (lib.php → `load('airpayux')`, 7 setting files migrated
`theme_epsilon`→`theme_airpayux`) + `theme/epsilon` deletion, the logged-in **dashboard**
(`/my/dashboard.php`, user nitinrajput17) was loaded in-browser:

- ✅ Page renders fully styled (airpayux theme intact — sidebar, cards, search, gradients).
- ✅ The **"airpay academy" navbar logo renders** (no broken image / 404).
- ✅ No layout breakage from the decoupling.

This is the **pixel-level belt-and-suspenders** on top of the authoritative proof:
- File API (4/4): all 7 setting fileareas resolve under `theme_airpayux`.
- File-migration rehearsal (6/6): clean component re-point, blobs preserved.
- Epsilon-delete health check: airpayux loads, login page HTTP 200.

Note: the live local **login page uses the standard username/password form** (not the OTP
phone form) because `auth_otp` is **not installed on this local env** (active auth = `email`).
Production has `auth_otp` → the OTP login. Theme login templates were not changed (only a
docblock comment). See the OTP explanation in the session log.

## Block D (dark-mode AA-contrast pass) — AUDITED ✅ (remediation = follow-up)

Ran a rigorous WCAG contrast audit in-browser (computed actual ratios for 80 text elements
via JS: text colour vs effective background). **78 pass AA. 2 genuine failures**, both small
muted text — and both reproduce in the **default** airpayux dark-navy theme (not just the
toggled "Dark Mode"), so they are **base-theme** issues:

| Element | Colour on bg | Ratio | AA req |
|---------|--------------|-------|--------|
| `.airpay-gamification__streak-day small` (Wed–Today streak labels) | `#6b7280` on `#1a1d27` | **3.48:1** | 4.5 |
| `.airpay-gamification__leaderboard-name small` ("(You)") | `#0066A7` on `#0d1f3c` | **2.71:1** | 4.5 |

**2 false positives** were flagged + dismissed: `"Dashboard"` (active nav) and `"Dark Mode"`
reported `bg=#ffffff` — a background-detection artifact from overlay layers; both are visibly
legible. The other ~76 elements pass.

**Remediation (focused follow-up, NOT a dark-mode override):** lighten the two label colours at
the **base** level to AA-passing tokens already used in the theme — `#6b7280 → #9ca3b4` (≈6.9:1)
and `#0066A7 → #60a5fa` (≈6.8:1) — then re-verify in **both** the default and dark-mode-toggle
states. The colour source is in the `dashboard.mustache` gamification widget (inherited / generic
rule — `#6b7280` is not set inline or in `_surface-dashboard.scss` by class name; locate the
emitting rule first). A `body.dark-mode`-scoped override is the WRONG layer (the failure is in
the default theme) — this was attempted, found misaligned, and reverted (deployed left clean).

**RESOLVED 2026-06-03:** fixed in `_surface-dashboard.scss` (co-located with the gamification
component colours) — `.airpay-gamification__streak-day small { color: #9ca3af !important }` (~5.1:1)
+ `.airpay-gamification__leaderboard-name small { color: #60a5fa !important }` (~6.8:1), unscoped so
it holds in both default + dark-mode. Root cause turned out to be a competing `#6b7280 !important`
rule overriding the AA-safe base (the streak-day base is already `#9ca3af`), so `!important` is
required to win. Verified empirically via the **compiled CSS** (both rules present + winning by
`!important` + source order; browser re-audit was blocked by the flaky extension permission, but the
change is colour-only with no layout impact). Theme-debt note: the stray `#6b7280!important` source
rule should be retired in a future theme-token cleanup.

**Toggle persistence — RE-CHECKED 2026-06-03: NOT A BUG.** The original "doesn't persist" note was a
**flaky-extension-click artifact** (the toggle handler never reliably fired during the audit). Source
review confirms persistence is correctly implemented: the sidebar toggle `#ap-dark-toggle` writes
`localStorage['airpay-theme']` (`dashboard.mustache:841`), and `head.mustache:31-37` restores it before
CSS on every load (with OS `prefers-color-scheme` fallback). A real toggle click persists across reloads.
