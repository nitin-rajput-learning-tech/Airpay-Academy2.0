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

### Active-marker fix — RESOLVED 2026-05-28 (theme 2026052409)

The first-load gap noted above (neither option highlighted before any
switch) is fixed. Root cause: `$USER->useraccess['currentroleinfo']` is
written by two paths with different keys —
`accesslib::set_user_role_switch()` stores `{roleid, contextid}` while
`core_renderer::role_switch_basedon_userroles()` stores
`{roleid, orgcatid, depth, contextinfo}` — but the builder required
`roleid` **and** `depth` **and** `orgcatid` to all match, so it silently
failed whenever the last two were absent. Now it matches on `roleid` (the
only key both paths guarantee), tightens with `contextid`/`orgcatid` only
when present, and falls back to `role_detector` (the same source of truth
that selects the dashboard) when there is no switch state — guaranteeing
exactly one option is marked, agreeing with the rendered dashboard.

Verified headlessly via the new
`theme/airpayux/cli/verify_roleswitch.php` (a keepable QA tool) against the
real Nitin (id 142) across all three `currentroleinfo` states:

```
A: fresh login (no switch)        -> Operations - Administrator  ACTIVE   PASS
B: switched to Employee           -> Employee                    ACTIVE   PASS
C: switched to category role      -> Operations - Administrator  ACTIVE   PASS   (the {roleid,contextid}-only shape the old triple-match missed)
RESULT: all states PASS — exactly one option active per state.
```

The CLI is the deterministic regression check; it covers cases (cold load,
the set_user_role_switch shape) that a single screenshot cannot. The
sidebar's rendered appearance is unchanged from the captures above — only
*which* option carries the active highlight on a cold load changed.

Pre-existing env note (not introduced here): on this local XAMPP,
`role_detector::detect()` calls `has_capability('local/courses:manage')`,
which isn't registered in the local DB → a debug notice per non-admin
dashboard load. Benign (Nitin resolves via his category-admin role) and
absent on production where `local_courses` is installed; flagged for a
separate look.

### Multi-user cold-load confirmation (2026-05-28)

The fix was re-verified visually on two additional multi-role users on a
fresh session each (login → land on `/my/` → no prior switch):

| User | Detected tier | Switcher cold-load | Result |
|------|---------------|---------------------|--------|
| Asif Ansari (id 2304, Course Author) | learner-style dashboard | **"Operations - Trainer" ACTIVE** ✓ (green check) + "Employee" link | PASS |
| Joseph Mandapati (id 627, Compliance Officer) | admin dashboard (L&D Admin tier via category-context administrator role) | **"Operations - Administrator" ACTIVE** ✓ + "Employee" link | PASS |

Both screenshots also incidentally re-confirm **Bug #11** (Joseph reaches
Compliance from the sidebar) and the learner-vs-admin nav rules from the
role_detector matrix tests.

---

## Trainer-side result panels — 5 newer Live question types (2026-05-28)

Closes the "low-risk visual follow-up" noted in the question-types section
above: yesterday only captured the audience-side renders + the multichoice
trainer result panel (via VIS-6/7/10). Today's session captures the trainer
result panel for the **5 remaining question types**, exercising the same
seeded session 18 (code 800 844) and its persisted responses.

Captures were driven by setting `local_sentientia_live_sessions.current_slide_id`
to each slide id in turn (PHP CLI), then reloading `/local/sentientia_live/trainer/run.php?id=18`.
Each capture cross-references the tally numbers in the question-types section above:

| Type | Trainer panel renders | Tally cross-ref (yesterday's CLI) |
|------|------------------------|-----------------------------------|
| wordcloud (slide 31) | CSS-bucket frequency cloud — "innovation" largest, "speed" + "trust" smaller | `{innovation:2, speed:1, trust:1}` |
| openended (slide 32) | Named-response list — "Bob: Very practical, thank you." (first of 2) | 2 responses |
| rating (slide 33) | Summary cards: "Average 4.67 · Responses 3 · Scale 1–5" | avg 4.67 |
| quiz (slide 34) | Correct-answer reveal: "Paris ✓ Correct" with prominent green bar `2 (66…%)` | `[2,0,1]` correct=index 0 |
| ranking (slide 35) | "Rank · Item · Avg position" table — "Quality / 1.33" at rank 1 | Borda `[1.33, 2, 2.67]` |

**Environmental note:** XAMPP's prefork Apache holds one worker per open
SSE connection, so `run.php` (which opens an SSE channel) intermittently
hangs subsequent screenshot/JS calls on the same tab. The captures above
each landed cleanly on first or second try; the rendered panel is below
the large join-code banner so each screenshot frames the top of the panel
(enough to verify the type-specific layout). A proper MPM (production
Apache or nginx) doesn't have this constraint.

After capture, session 18's `current_slide_id` was restored to slide 30
(multichoice, position 1 — the natural start) so the demo is left in a
clean state.
