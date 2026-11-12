# Proposed plan: six GitHub repos for faster Claude coding on Sentientia

**Date:** 2026-11-12
**For:** Nitin Rajput
**Status:** PROPOSAL ONLY. Nothing was cloned, downloaded, installed or run. All six repos were read on GitHub only.

---

## Summary

Of the six repos, one is worth adopting now, two are worth a contained pilot or a partial borrow, one is worth borrowing ideas from, and two should be skipped.
**Humanizer** is a small writing skill that fits our document work today.
**Impeccable** has a UI audit and an automatic "generic-looking UI" detector that could shorten our visual passes, but it needs a pilot because it was built for React-style web apps, not Moodle.
**Hallmark** has one useful part (a page checker that runs at eight screen widths); the rest (fifty visual themes) conflicts with our brand book.
**Ponytail** pushes Claude to write less code; we should borrow its ideas into our existing code-quality coach rather than install it whole.
**REA** (reverse engineering) and **UI UX Pro Max** (design-system generator) do not fit our work now.
Separately, this review found that our own Claude setup is out of date in places. Fixing that is likely the largest gain and costs nothing (see "Step 0").

---

## At a glance

| Repo | What it is | Verdict | Where it plugs in | Expected gain | Main risk |
|---|---|---|---|---|---|
| [blader/humanizer](https://github.com/blader/humanizer) | A writing skill. Removes signs of AI-written text. MIT. | **ADOPT** | Project skill, `.claude/skills/humanizer/`. Used on documents, not code. | Fewer rewrite rounds on the Product Guide, cutover briefs, C-suite memos, UX copy. | Low. It can change meaning if run unchecked on technical text. |
| [pbakaus/impeccable](https://github.com/pbakaus/impeccable) | A design toolkit: 24 slash commands plus a detector for generic-looking UI. Apache 2.0. | **ADOPT IN PART (pilot)** | The `audit`, `critique`, `polish`, `harden` commands and the detector, scoped to `theme/sentientia/**`. | Shorter visual passes. Defects found before Nitin's review, not after. | Medium. It installs a hook, has telemetry, and fetches from its own website. Built for web apps, not Moodle. |
| [nutlope/hallmark](https://github.com/nutlope/hallmark) | A design skill with 50 themes and a page checker. MIT. | **ADAPT** | Borrow two ideas: check each page at eight widths, and a fresh-eyes review by a second agent. | Catches layout breaks at widths we do not test today. | Medium. The themes would override our brand book if the whole skill were active. |
| [dietrichgebert/ponytail](https://github.com/dietrichgebert/ponytail) | A prompt that makes the agent write only the code the task needs. MIT. | **ADAPT** | Fold its rules into our existing `code-quality-coach` skill. Optional one-week trial later. | Smaller diffs, less to review. | Medium. "Less code" can clash with Moodle's required files (privacy provider, Hindi strings, full plugin set). |
| [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) | A searchable catalog of styles, palettes and fonts that generates design systems. MIT. | **SKIP (for now)** | None today. | None today. | It would propose colours and fonts that conflict with the brand book. |
| [morluto/rea](https://github.com/morluto/rea) | A reverse-engineering toolkit for AI agents. MIT. | **SKIP** | None. | None. | High for a corporate machine. Wrong tool for building an LMS. |

---

## What we already have (so we do not duplicate it)

Read from `CLAUDE.md`, `.claude/CLAUDE.md`, `.claude/` and `.claude/rules/frontend.md`.

- **Six agents:** code-reviewer, debugger, test-writer, refactorer, doc-writer, security-auditor.
- **Three commands:** `/fix-issue`, `/deploy`, `/pr-review`.
- **Two project skills:** `frontend-design` (theme guidance) and `code-quality-coach` (four "Karpathy" principles: think first, keep it simple, change only what is needed, be goal-driven).
- **Three rule files:** frontend (the design system: primary #0066A7, accent #1985DD, Montserrat, 8 px grid, BEM names, motion tokens), database, API.
- **Hooks:** a lint-on-save hook, a blocker for destructive commands, and a pre-commit hook with 18+ checks.
- **Many repo checks** under `tools/` (tree drift, path boundary, language parity, theme layout regions, AMD build parity).
- **Installed plugins already cover a lot:** superpowers, code-review, pr-review-toolkit, design (critique, accessibility review, UX copy), and Anthropic's `frontend-design`.

So the gaps a new tool could fill are narrow:

1. An automatic check of how a rendered page looks (we check code, not the picture).
2. Checks at more screen widths (we test desktop and 590 px mobile).
3. A plain-writing pass for documents.

Code review, security review, testing and debugging are already covered. We should not add more of those.

### Step 0: our own setup is stale (found during this review)

These are facts from the files, not from the repos:

- `.claude/settings.json` still says `MOODLE_VERSION 4.5.10`, points `MOODLE_ROOT` at `C:\xampp\htdocs\moodle`, and names the theme `airpayux`. The project is on Moodle 5.3 with theme `sentientia`.
- `.claude/skills/frontend-design/SKILL.md` triggers on `theme/airpayux/**` and describes "Sprint 1 (Navbar, Footer, Login)", which finished long ago.
- `.claude/rules/frontend.md` gives the stylelint config path as `theme/airpayux/.stylelintrc.json`. The file is actually at `theme/sentientia/.stylelintrc.json`.
- `.claude/CLAUDE.md` still describes "Moodle 4.5.10" and "airpayux v1.0.0".

Every session loads these files. Stale guidance costs tokens and causes wrong paths. Bringing them up to date is a bigger and safer gain than any third-party tool. It needs your approval because it changes `.claude/`.

---

## Per repo

### 1. blader/humanizer — ADOPT

Source: https://github.com/blader/humanizer

**What it is.** A skill that rewrites AI-sounding text so it reads as written by a person, while keeping the claims. It checks 26 patterns in six groups, based on Wikipedia's "Signs of AI writing" guide. The README says it does not try to evade AI detectors. MIT licence. The core is one file, `SKILL.md`. Last commit seen: 10 Nov 2026. 122 commits.

**Why it fits.** We write a lot of prose for people outside engineering: `docs/SENTIENTIA-PRODUCT-GUIDE`, cutover briefs, decision memos, ADRs, visual-evidence READMEs, UAT notes. These get rewritten when they read as machine-made.

**How to adopt.**
- Copy the reviewed `SKILL.md` into `.claude/skills/humanizer/` at a pinned commit. Do not use the plugin marketplace route, because that updates itself and we would no longer know what text Claude is reading.
- Use it on request only (`/humanizer` on a named file). No automatic trigger.
- Scope: Markdown documents under `moodle-enhancement/docs/` and customer-facing English text.

**Do not use it on.** PHP, language string files in bulk, `PROJECT-STATE.md` history, or legal lines (the footer notice and per-customer legal strings are fixed by you). Hindi and other language packs are out of scope; it is an English tool.

**Risks and containment.**
- It may soften a precise technical statement. Containment: review the diff; never run it on a file and commit in the same step.
- Overlap with the installed `brand-voice` and `design:ux-copy` skills is small. Those set tone; this removes tells.
- Token cost: one skill file loaded only when called.

**Expected gain.** One fewer rewrite round on each manager-facing document.

---

### 2. pbakaus/impeccable — ADOPT IN PART, as a pilot

Source: https://github.com/pbakaus/impeccable

**What it is.** A design toolkit for coding agents. 24 slash commands in groups: steer (bolder, quieter, typeset, layout, colorize...), review (critique, audit), finish (polish, harden, clarify, adapt, optimize, onboard), live, and setup (init, document, extract). It also ships a command-line detector (`npx impeccable detect`) with, per its README, 62 rules for generic-looking UI. It reads two context files, `PRODUCT.md` and `DESIGN.md`. Apache 2.0. The README says it started from Anthropic's frontend-design skill. Last commit seen: 11 Nov 2026. About 2,860 commits, so it is very active.

**Why it fits.** Our UI work ends in a visual pass and your review. An automatic detector plus `audit` and `harden` could catch spacing, contrast, empty-state and overflow problems before that review. `DESIGN.md` could be written once from our brand book, so the tool judges against our colours and font, not its own taste.

**What to take.**
- The detector, run on demand against built CSS and rendered pages.
- Four commands: `audit`, `critique`, `polish`, `harden`.

**What to leave.**
- The "steer" commands (bolder, colorize, overdrive, delight). They invite changes away from the brand book.
- `live` mode and the browser and VS Code extensions.
- The automatic design hook, at least during the pilot.

**Risks and containment.**
- **It installs a hook.** The README asks the user to "Accept the hook". We already run two hooks on every save. Containment: decline the hook in the pilot; call commands by hand.
- **Telemetry and network.** The README documents a telemetry option and says it fetches design directions from the impeccable.style catalog. Containment: turn telemetry off before first use; confirm what is sent; never run it on pages showing real user data (our local database is an import of production).
- **Fit with Moodle is unproven.** It was built for modern web apps. Our UI is Mustache templates, SCSS and Bootstrap inside Moodle. The detector may produce noise on Moodle core markup we do not control. This is the main thing the pilot must answer.
- **Overlap.** It overlaps our `frontend-design` skill, `rules/frontend.md`, and the installed `design:design-critique` and `design:accessibility-review`. Containment: scope it to `theme/sentientia/**`; our rule file wins on any conflict.
- **Fast-moving code.** Containment: pin one commit or release; review the skill text before install; update only on purpose.
- **Windows.** It needs Node (we have one under `<repo>/.tools`). The repo also has Rust code; whether the npm install needs a Rust build on Windows is not verified.
- **Token cost.** 24 commands add to every session's skill list. Installing only the four we want keeps this small.

**Expected gain.** Fewer UI defects reaching your review, and a shorter fix loop per screen. To be measured, not assumed.

---

### 3. nutlope/hallmark — ADAPT (ideas only)

Source: https://github.com/nutlope/hallmark

**What it is.** A design skill "made by Together AI" that tries to stop AI-built pages looking AI-built. It has 50 visual themes, a custom mode without themes, `redesign` and `audit` commands, and a checker script (`sloplint.mjs`) that renders a page at eight widths and tests it against three tiers of rules. The build process ends with a review by a fresh agent that has not seen the work. It bundles 755 openly licensed photos. MIT. Browser checks need `puppeteer-core` and Chrome. Last commit seen: 11 Nov 2026. 349 commits. Its README credits Impeccable as an inspiration.

**Why not adopt whole.** Its core is choosing a theme and building a new page from it. We do the opposite: one brand, one design system, existing Moodle pages. A theme-driven skill that triggers on UI work would pull against the brand book. The photos are of no use inside an LMS.

**What to borrow.**
1. **Eight-width checks.** We capture desktop and mobile. Our own journeys script could capture more widths. This is our code, with no install.
2. **Fresh-eyes review.** After a UI change, a second agent that has only the screenshots and the brand rules reviews the result. We can do this today with existing agents.
3. **The checker's rule list** as a reading reference for our own stylelint and visual checks.

**Possible later use.** If we build a marketing site to sell Sentientia, Hallmark is a reasonable tool for that separate project. Not for the product.

**Risks if installed anyway.** Brand drift; a global install (`-g`) that affects every project on the box; a headless browser dependency on a machine where the antivirus already blocks some browser automation.

---

### 4. dietrichgebert/ponytail — ADAPT (borrow rules; optional trial)

Source: https://github.com/dietrichgebert/ponytail

**What it is.** A prompt (`skills/ponytail/SKILL.md`, plus a short `AGENTS.md`) that pushes the agent to write only what the task needs, while, in its words, never cutting "validation, error handling, security, or accessibility". It has intensity levels (lite, full, ultra, off), review and audit commands, two lifecycle hooks, and a status band in Claude Code showing cost and tokens, with data kept in `~/.config/ponytail/band/`. MIT. Last commit seen: 29 Oct 2026. 369 commits.

**Its claims.** The author reports 53% less code, 41% less time and 26% less cost across 39 tasks. These are the author's own benchmarks. I did not verify them, and they were not run on a codebase like ours.

**Why not adopt whole.**
- We already have the same idea in `code-quality-coach` ("Simplicity First", "Surgical Changes"). Two skills giving similar advice in different words cost tokens and can disagree.
- Our rules sometimes require more files, not fewer: a complete plugin set, a real privacy provider, Hindi parity, a feature flag, tests. A "write less" prompt at high intensity could lead a session to skip these. Those are exactly the defect classes we have paid for before.
- It adds two more hooks and writes data outside `D:\Claude Local\`.

**What to borrow.** Read the skill text and move the useful rules into `code-quality-coach` in our own words: reuse an existing helper before writing a new one; no speculative options; state what was deliberately left out. Add an explicit line that Moodle's required files are never "extra".

**Optional later.** A one-week trial at `lite` in a separate worktree, if the borrowed rules show a gain and you want to test the full tool.

---

### 5. nextlevelbuilder/ui-ux-pro-max-skill — SKIP for now

Source: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill

**What it is.** A skill with a searchable catalog of UI styles, colour palettes, font pairings, chart types and UX guidelines. A Python script (`search.py`) searches it and can generate a design system into a `design-system/` folder. The README says the script uses only the Python standard library and makes no network calls. There is also an npm CLI (`uipro`). MIT. Last commit seen: 10 Nov 2026. 296 commits. The README promotes a paid tier and carries donation links.

**Why skip.** Its main job is to choose a look. Ours is already chosen and signed off (brand book 2026-06). A skill that activates on every UI request and suggests palettes and fonts works against that. The general UX guidance overlaps what `rules/frontend.md` and the installed design skills already give.

**When to look again.** When a second customer arrives and needs a starter palette for per-customer branding. Then its catalog could be used once, by hand, as a lookup. That is a one-off use, not an install.

---

### 6. morluto/rea — SKIP

Source: https://github.com/morluto/rea

**What it is.** "Reverse Engineer Anything": a toolkit that connects AI agents to reverse-engineering tools (Ghidra, IDA, Hopper) through an MCP server. It covers program binaries, Android apps, firmware, network captures and JavaScript apps. Its setup command registers a server and installs workflow instructions into the agent's configuration. MIT. Last commit seen: 12 Nov 2026. About 7,290 commits.

**Why skip.**
- We own all our source code. We do not need to take software apart to build an LMS.
- Its setup changes Claude Code's configuration, which our rules do not allow without your say.
- On a corporate machine with new monitoring software, installing reverse-engineering tools is likely to raise alerts and questions from IT.
- The repo itself carries a disclaimer file, which reflects the legal care this kind of tool needs.

**The one narrow case.** If we ever need to understand a third-party SCORM package or a minified vendor script with no source, its JavaScript analysis could help. That is rare, and it should be a separate, approved, one-off task on a non-corporate machine.

---

## Risks common to all third-party skills

- **Prompt injection.** A skill is text that Claude follows. Whoever controls the repo controls that text. A later update could add instructions we never read. Containment: copy a reviewed version at a pinned commit into our repo; do not use self-updating marketplace installs; re-review on every update.
- **Instructions found in the READMEs.** None of the six pages contained an instruction that asked an agent to do something unsafe. Several contain install directions aimed at agents or users ("ask your agent to install...", "Accept the hook", "trust its two lifecycle hooks"). I did not act on any of them. They are reported here as the surface to watch.
- **Hooks.** Impeccable and Ponytail both add hooks. A hook runs code on this machine on every action. Treat any hook as code to be read line by line before approval.
- **Token cost.** Every installed skill adds a line to every session, and each one that triggers adds its full text. We already carry a long skill list. Prefer on-request use.
- **Licences.** Five are MIT and one is Apache 2.0. Both allow our use. Apache 2.0 asks us to keep its notice if we copy its files into our repo. Since Sentientia is to be sold, anything copied in should be listed in a third-party notices file.
- **Popularity is not safety.** The star counts I saw were large for all six, and in one case (REA) implausibly large. They do not tell us the code is safe or suits Moodle.

---

## Staged rollout

**Stage 0 — tidy our own setup (no third-party code).**
Update the stale items listed above. Measure: fewer wrong-path corrections in sessions.

**Stage 1 — Humanizer (lowest risk, quickest to judge).**
1. Read `SKILL.md` in full at one commit. Report anything odd.
2. With your approval, copy it into `.claude/skills/humanizer/`.
3. Test on two existing documents (one cutover brief, one section of the Product Guide). Show you before and after.
4. Measure: do you accept the result without asking for a rewrite?

**Stage 2 — borrowed ideas (no installs).**
1. Add more screen widths to our own screenshot script.
2. Add a fresh-eyes review step to the UI workflow.
3. Fold Ponytail's useful rules into `code-quality-coach`.
4. Measure over the next three UI sessions and three plugin sessions: UI defects found after the session ends; lines changed per fix; review rounds before commit.

**Stage 3 — Impeccable pilot (contained).**
1. Read the four command texts and the hook script at one pinned commit.
2. Run the detector once, in the scratch folder, against a copy of the built theme CSS and two local pages (learner dashboard, course page) that show no real personal data.
3. Count real findings against noise. Compare with the last manual visual pass on the same pages.
4. Go or no-go: adopt the four commands only if at least half the findings are real and it finds something our pass missed.

**Stage 4 — review after two weeks.**
Keep what showed a measured gain. Remove the rest. Record the outcome as a short ADR.

**How we measure, in plain terms.**

| Measure | Where the number comes from | Today's baseline |
|---|---|---|
| Review rounds per document | Count of your "rewrite this" requests | To be noted over the next two documents |
| UI defects found after a session | PROJECT-STATE entries and UAT checks | Recent example: 3 fixes on 11-02/11-03 found after the fact |
| Time for a visual pass | Session length for the persona journeys | 126 steps across 11 personas, run overnight on 11-03 |
| Size of a fix | Lines changed per commit | From `git log --stat` on the last ten fixes |

---

## What needs your approval before anything is installed

1. **Stage 0:** permission to update `.claude/settings.json`, `.claude/CLAUDE.md`, the `frontend-design` skill and `rules/frontend.md` to current facts.
2. **Humanizer:** permission to fetch its `SKILL.md` for review, then to add it under `.claude/skills/humanizer/`.
3. **Impeccable pilot:** permission to download the package into the scratch folder and run its detector locally. This is a download of third-party code and an `npx` run, so it needs an explicit yes. Also your decision on its hook (my advice: decline) and telemetry (my advice: off).
4. **Ponytail:** permission to fetch its `SKILL.md` for reading only, and to edit `code-quality-coach` with the borrowed rules.
5. **Hallmark:** no install proposed. Permission only to extend our own screenshot script to more widths.
6. **REA and UI UX Pro Max:** confirm skip.
7. **Third-party notices:** agree that anything copied into the repo is recorded in a notices file, since the product is to be sold.
8. **IT check:** whether the new monitoring software permits `npx` downloads and a headless browser on this machine. If not, Stage 3 cannot run here.

---

## What I could not verify

- **Skill text.** I read the README pages only. I did not read any `SKILL.md`, hook script or source file. The verdicts above depend on a later line-by-line read.
- **Star and fork counts.** Shown on the pages as: REA 454k, Ponytail about 190k, UI UX Pro Max about 148k, Impeccable about 109k, Humanizer 68k, Hallmark 54k. The REA figure looks too high to trust. Treat all as unconfirmed.
- **Last-commit dates.** Taken from each repo's commit list page on 12 Nov 2026. Releases and version numbers were not visible.
- **Language breakdown.** Not shown on any of the six pages.
- **Impeccable on Moodle.** Whether its detector works on Mustache, SCSS and Moodle's rendered pages, what its telemetry sends, and whether install needs a Rust build on Windows.
- **Hallmark's checker.** Whether `sloplint.mjs` can be pointed at a URL of an existing page, or only at pages Hallmark built.
- **Ponytail's benchmarks.** Author's own figures; not reproduced.
- **Windows.** None of the six READMEs was checked for Windows-specific problems. All assume Node; two assume a Chrome or Puppeteer setup.
