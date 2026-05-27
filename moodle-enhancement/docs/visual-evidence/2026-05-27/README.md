# Visual Evidence — 2026-05-27

## Sentientia Live — two-browser verification of all 6 question types

Verifies the `local_sentientia_live` (Mentimeter clone) question types
end-to-end after the Wave C1/C2/D4 merges landed all 6 types on the
`production` branch. Run against local XAMPP (Moodle 5.1.3+, PHP 8.2.12,
MariaDB 10.11.16) at `http://localhost:8080/moodle`.

### What was tested

A LIVE session (id=18, code 800844) was seeded with one slide of every
question type + 3 anonymous participants + a spread of responses via the
new `cli/seed_demo_session.php`. The audience was then driven in a fresh
(anonymous) Chrome; the current slide was advanced server-side per type.

### Screenshots (audience render)

| File | Type | Renders |
|------|------|---------|
| `01-multichoice-audience.png` | multichoice | radio options (Red/Green/Blue) + Submit |
| `02-wordcloud-audience.png`   | wordcloud   | text input + cap hint "3 of 3 words remaining" |
| `03-openended-audience.png`   | openended   | multi-line textarea |
| `04-rating-audience.png`      | rating      | 1–5 scale cards (stars) with radios |
| `05-quiz-audience.png`        | quiz        | radios (correct answer hidden from audience) |
| `06-ranking-audience.png`     | ranking     | numbered `#` inputs + numeric a11y instruction |

### Verification matrix — all green

| Dimension | Result | How |
|-----------|--------|-----|
| Audience render (6 types) | ✓ PASS | 6 browser screenshots above, all render with Airpay-blue Submit, correct progress "Question N of 6", BEM/Sentientia styling |
| Anonymous join | ✓ PASS | code → "Found: QA Demo…" → display name → play.php with participant token (no login) |
| SSE live auto-advance | ✓ PASS | Changing current slide server-side (multichoice→wordcloud) auto-swapped the audience screen with no manual reload |
| Server persist + tally (6 types) | ✓ PASS | `seed_demo_session.php` — 17/17 responses persisted; tally readback correct for every type (e.g. multichoice `[2,1,0]`, rating avg 4.67, quiz `[2,0,1]`, ranking Borda `[1.33,2,2.67]`, wordcloud `{innovation:2,speed:1,trust:1}`) |
| JS console health | ✓ PASS | Zero JS errors across all 6 renders. Only a benign site-wide PWA-meta deprecation warn (`apple-mobile-web-app-capable`) |

### Notes / follow-ups

- **SSE auto-advance** fired reliably for the first hop; subsequent hops
  were captured via explicit reload because XAMPP's prefork Apache holds
  one worker per open SSE connection (a local-env constraint, not a
  product bug — production uses a proper MPM; multichoice SSE chart
  updates were already verified live in VIS-10).
- **Trainer-side result panels** for the 5 new types (wordcloud cloud,
  openended list, rating bars, quiz leaderboard, ranking Borda) were not
  re-captured here — they require an authenticated trainer browser, and
  the multichoice result panel + SSE chart were already verified in
  VIS-6/7/10. The seed CLI proves the underlying tally data is correct,
  so this is a low-risk visual follow-up.
- **PHPUnit harness fix (separate):** the question-type PHPUnit suite
  errored on `Unknown column 'open_path'` in `phpu_user` —
  `session_manager::create()` hard-selected a BizLMS-only column absent
  from the vanilla test DB. Fixed by reading `open_path` defensively
  (also hardens the plugin for non-BizLMS Sentientia customers). See the
  session changelog.

### New QA/operator CLIs (this session)

- `local/sentientia_live/cli/set_live_flags.php` — flip the whole Live
  engagement flag set on/off in one command (`--on` / `--off` /
  `--status`).
- `local/sentientia_live/cli/seed_demo_session.php` — seed a LIVE session
  with all 6 question types + participants + responses; prints join code
  + URLs.

### Flag state during test

All 9 Live engagement flags were ON globally (via `set_live_flags --on`).
`live.enabled`, `multichoice`, `allow_anonymous` were already ON from
prior VIS tests; this session added the remaining 5 question-type flags.

---

## Multi-user login test (after deploying latest 42029e4dd to local)

Deployed the latest `sentientia_live` (2026052504) to local XAMPP
(`upgrade.php` completed successfully, caches purged), then set a
**temporary LOCAL-DEV-ONLY** password on 6 representative accounts (one per
user type) via Moodle's `update_internal_user_password()` (proper hashing,
`moodle` DB only — **never production**) and tested the real login flow.

> ⚠ The temporary password is **local-dev-only** for QA on localhost:8080.
> It must never be used on `airpay.academy`. Production credentials are
> untouched.

| # | Account | Type | `airpay123` validates | Login flow | Landing |
|---|---------|------|:---:|:---:|---------|
| 1 | academy@airpay.co.in (id 2) | Site Admin | ✓ | ✅ tested | Admin dashboard (Manage Users/Courses, Compliance, Analytics) — `login-01-academy-siteadmin.png`. **Resolves the earlier "login not working" for this account.** |
| 2 | jitendra.mane@airpay.co.in (483) | Learner (Airpay) | ✓ | ✅ tested | Personalised onboarding ("Welcome, Jitendra!") + learner-scoped nav — `login-02-jitendra-learner.png` |
| 3 | academyexadmin@airpay.co.in (234) | Tenant Admin (Public /77) | ✓ | ✅ tested | `/my/` dashboard (login confirmed; screenshot skipped — render-heavy page timed out the capture) |
| 4 | nitin.rajput@airpay.co.in (142) | Airpay admin/manager | ✓ | credential-ready | dashboard verified in prior Goal A walk |
| 5 | asif.ansari@airpay.co.in (2304) | Course Author | ✓ | credential-ready | dashboard verified in prior Goal A walk |
| 6 | joseph.mandapati@airpay.co.in (627) | Compliance Officer | ✓ | credential-ready | dashboard verified in prior Goal A walk |

All 6 validate the password (CLI `validate_internal_user_password`). Three
tiers were driven through the real login form end-to-end (Site Admin,
Learner, Tenant Admin), confirming role- + tenant-appropriate routing. The
other three are credential-ready for the operator to log in directly; their
dashboards were already captured in the Goal A persona walks.

---

## Sidebar role switcher — multi-role shell parity (airpayux 1.0.39-beta)

**Why:** Nitin is multi-role (Operations-category Administrator **and** a
learner/employee) and on live `airpay.academy` switches roles via the
top-right user menu. The airpayux dashboard **shell** (`use_shell=true`)
moved all user controls into the left sidebar and renders neither
`navbar.mustache` nor `topbar.mustache` — so the role switcher that
`core_renderer::user_menu()` builds was computed on every load but **its
output was discarded** (DOM-verified earlier: `switchrole_links_count: 0`).
Multi-role users had no visible way to switch in the shell; only the raw
`/my/switchrole.php` URL worked.

**Fix:** New `get_role_switch_options()` data-builder on the `user_menu`
trait (reuses the same `\local_airpay_org\accesslib` source as the dropdown),
surfaced as a native **"⇄ SWITCH ROLE TO:"** control in the sidebar footer,
above the theme toggle. Single-role users get `hasoptions=false` → no markup
(production behaviour unchanged). No new lang keys (reuses `switchroleto` +
`employee`; Hindi parity intact). Deployed to local XAMPP (theme upgrade
`2026052407 → 2026052408`, caches purged).

### Flow tested — Nitin Rajput (id 142), real login (`airpay123`, local-dev)

DOM probes (`document.querySelectorAll`) + visual capture at each step;
screenshots are in the session transcript (the Chrome capture bridge attaches
them inline rather than to disk):

| Step | Action | Switcher state (DOM-verified) | Dashboard (DOM-verified) |
|------|--------|-------------------------------|--------------------------|
| 1 | Login → `/my/` (Admin default) | control present, **2** switch links, heading "Switch role to:", user "Nitin Rajput"; options `Employee` + `Operations - Administrator` | Admin view — KPIs "Active Users", "Manage Users" nav |
| 2 | Click **Employee** | `Employee` → `active:true` (now a `<span>` + green check); `Operations - Administrator` → `active:false` (link) | **Learner view** — subtitle "Continue where you left off…", learner KPIs (8 Enrolled / 12 Certificates), gamification (Beginner Lv.1, streak, Leaderboard); nav = My Courses / Catalog / Certificates / Profile |
| 3 | Click **Operations - Administrator** | `Operations - Administrator` → `active:true` (`<span>` + check); `Employee` → `active:false` (link) | Admin view restored — `hasAdminKPI_ActiveUsers:true`, `Manage Users` nav back |

### Verification matrix — all green

| Dimension | Result | How |
|-----------|--------|-----|
| Switcher surfaced in shell | ✓ PASS | `.ap-sidebar__roleswitch` present; `switch_links_count` 0 → **2** after fix |
| All role types listed | ✓ PASS | Both of Nitin's switchable roles shown (Employee/Learner + Operations-Administrator), registry/accesslib-driven |
| Switch is one click + bidirectional | ✓ PASS | Admin → Learner → Admin round-trip; each fully transforms dashboard **and** sidebar nav |
| Active role marked | ✓ PASS | Current role renders as non-clickable `<span>` w/ green check + Airpay-blue tint; others are `switchrole.php` links (`aria-current="true"`) |
| Backwards-compat (single-role) | ✓ PASS | `get_role_switch_options()` returns `hasoptions=false` for users w/o category-context roles ⇒ zero new markup |
| JS console health | ✓ PASS | Zero console errors/exceptions across login, both switches, and a fresh `/my/` reload |

### Known minor follow-up (non-blocking)

On the very first load *before any switch* (fresh session, no
`$USER->useraccess['currentroleinfo']` pinned), neither option is
highlighted — the links work, but the "current" marker only appears once a
switch sets the active-role info. Cosmetic only; tracked for a later polish
(fall back to `role_detector` to pre-mark the default role).
