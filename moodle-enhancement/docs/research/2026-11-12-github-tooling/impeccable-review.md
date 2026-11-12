# Impeccable: review of the four commands and the hook (Stage 3, step 1)

**Date:** 2026-11-12
**Source:** https://github.com/pbakaus/impeccable at commit `b4bff1626c344067788c2679f048a20057c7cc5a` (2026-11-11, skill version 4.8.2, engine 0.1.22). Apache 2.0.
**What was done:** the files below were read as text from that commit. Nothing was installed, no binary was downloaded, nothing was run. The pilot run (step 2 of Stage 3) waits for Nitin's IT check.

Files read in full: `.claude/skills/impeccable/SKILL.md`; `reference/audit.md`, `critique.md` (the procedure in full; the reference half, cognitive load, Nielsen heuristics and personas, skimmed), `polish.md`, `harden.md`, `hooks.md`; `plugin/hooks/hooks.json`; the root `.claude-plugin/plugin.json`; the repo file tree; the repo's own `.claude/settings.json`; the launchers `skill/scripts/impeccable` (sh) and `impeccable.cmd` (Windows); `engine-checksums.txt`; `package.json`; `cli/bin/cli.js` (first 40 lines). Read in part: `crates/context/src/telemetry.rs`, `crates/account/src/critiques.rs`, `upload_notice.rs`, `lib.rs`, and the 18 crate manifests.

## Summary

- **Two of the four commands need no software.** `polish` and `harden` are checklists in prose. Claude can follow them with the tools we already have.
- **`audit` and `critique` depend on a downloaded program.** Each runs the detector, a 21.7 MB Windows program fetched from GitHub on first use. `critique` also starts a local server and injects a script into the page.
- **The hook runs that program after every Edit or Write.** `hooks on` installs it into `.claude/settings.local.json`, and it runs again when a session starts and when it stops. The tool's own documentation says it runs unsandboxed, with the agent's environment and network access. There are two other routes to the same hook: installing Impeccable as a Claude Code plugin switches it on with no `hooks on` step, and after one consent `impeccable context` re-writes missing hook entries by itself (see "How it is wired").
- **Telemetry and uploads exist and can be turned off.** A "roll" feature sends usage data to `impeccable.style`. If anyone signs in to an Impeccable account, `DESIGN.md` and every saved critique report upload to that account by default. `DO_NOT_TRACK=1` (or `IMPECCABLE_NO_TELEMETRY=1`) stops the roll telemetry and the critique upload. The DESIGN.md upload stops with `impeccable health design off`; whether `DO_NOT_TRACK` also stops it was not checked (`health.rs` not read). Without a sign-in, neither upload runs.
- **The Windows launcher has never run on Windows.** Its own header says it is tested "by dry parsing and string-level tests, not yet on a real Windows machine".
- **Recommendation.** Take `polish` and `harden`, and the four checkable sections of `audit`, as checklists now (no install, no IT question). Keep the detector for the pilot. Do not install the hook, and do not install Impeccable as a Claude Code plugin (the plugin carries the hook).

## What each command does

### audit
A technical check of one target, reported, not fixed. It scores five areas from 0 to 4:
1. accessibility: contrast, reduced motion, ARIA, keyboard, headings, alt text, form labels;
2. performance: layout thrashing, costly animation, lazy loading;
3. theming: colours outside the design tokens, dark mode;
4. responsive design: fixed widths, touch targets (24 px WCAG floor, 44 px advised), horizontal scroll, text zoom;
5. "implementation integrity": runs the bundled detector and checks its findings by hand.

The output is a 0 to 20 score, P0 to P3 issues each with file and line, and a list of the follow-up commands to run. At the end it saves the report through `impeccable audit-storage write` (into the project's `.impeccable/` folder) and deletes its temp file.

Fit: sections 1 to 4 match what our visual passes already look for, and they need no program. Section 5 and the saving step need the engine.

### critique
A UX review with a fixed procedure:
- two sub-agents run in parallel without seeing each other's output. Agent A does the design review: Nielsen's ten heuristics scored 0 to 4, cognitive load, two or three personas. Agent B runs the detector, and on a viewable page a browser overlay. A run done in one agent must open with a "DEGRADED" banner.
- for the overlay it starts `impeccable live-server --background` and injects `http://localhost:PORT/detect.js` into the page in a fresh browser tab.
- the report goes to `.impeccable/critique/` in the project, with a score trend across runs.
- it ends by asking the user two to four questions with the AskUserQuestion tool. That tool is not available to workflow sub-agents.

Fit: the heuristic and persona review (Agent A) works without software and is close to the fresh-eyes review added to `tools/visual-pass/README.md`. Agent B needs the engine. The overlay injects a script into the page, which our local-only harness would have to allow.

### polish
A final pass that keeps the existing look ("refinement, never concealed redesign"). In order:
1. read `DESIGN.md` and the tokens;
2. sort each drift as a missing token, a one-off, a concept mismatch, or a local defect;
3. fix broken tasks first, then missing states (loading, empty, error, disabled, permission), then layout and design-system drift, then visuals, then code clean-up;
4. walk the whole path again at mobile, intermediate and wide widths, with keyboard and zoom.

It can read the last critique's issues through `critique-storage`, which is optional and needs the engine.

Fit: good. It matches our rule that the brand book wins and that changes stay narrow. No software needed.

### harden
A checklist for real-world input:
- long, short and empty text; emoji; large numbers; 1,000+ rows;
- network and API errors (400 to 500, 429);
- translations that run longer than English; RTL; Unicode;
- empty, loading and permission states; double submits; interrupted drag gestures;
- validation on the server as well as the client; keyboard and screen reader; slow connections.

The code examples are React and plain CSS.

Fit: good as a checklist. The translation-length check maps to our Hindi, Kannada, Marathi and Swahili packs. RTL does not apply today. No software needed.

## The hook

**How it is wired.** `hooks on` writes three entries into `.claude/settings.local.json` (project scope) or the user's `~/.claude/settings.json`:

| Event | Timeout | Status shown |
|---|---|---|
| `SessionStart` | 5 s | "Preparing build session" |
| `PostToolUse` on `Edit\|Write` | 5 s | "Checking UI changes" |
| `Stop` | 30 s | "Design deep pass" |

Each entry runs the launcher `.claude/skills/impeccable/scripts/impeccable hook` (on Windows, `impeccable.cmd hook`).

There are two more ways the hook gets wired, with no `hooks on` step:
- **(a) As a Claude Code plugin.** The repo is also a plugin marketplace. Its file tree has `.claude-plugin/marketplace.json` and a plugin under `plugin/`, with `plugin/.claude-plugin/plugin.json`, `plugin/hooks/hooks.json` and four agents in `plugin/agents/` (`impeccable-asset-producer`, `-documenter`, `-finish-reviewer`, `-manual-edit-applier`). `plugin/hooks/hooks.json` (read in full) declares the same three hooks with the same timeouts (5 s, 5 s, 30 s), each running `impeccable.cmd hook` from the plugin folder. A plugin's hooks are active as soon as the plugin is enabled. Nothing is written to `settings.local.json`, so the hook does not show up there. The marketplace file was seen in the tree listing but not read, so which plugin it offers was not checked.
- **(b) Automatic repair by `context`.** Per `hooks.md`, once `hook.consent` is `"accepted"` in `.impeccable/config.local.json` and the hook is enabled, `impeccable context` restores missing or stale hook entries by itself and reports the repair in one line. `SKILL.md` tells the agent to run `context` once per session. So after one `hooks on`, deleting the entries from the settings file does not remove the hook for long; `hook.enabled: false` in `.impeccable/config.json` (or `hooks off`) does.

This matters here: commit `91bafdf85` records a plugin's PostToolUse hook (`remember`) hanging every workflow subagent's tool calls, and both routes put a PostToolUse hook in front of every Edit and Write.

**What it does.** After an edit to a `.tsx .jsx .html .vue .svelte .astro .css .scss .sass .less .ts .js` file, it runs a fast subset of the detector rules. It adds a reminder to Claude's context: findings, pending issues, or a short note that the file is clean. At `Stop` it runs the full rule set over every UI file touched in the session. It never blocks an edit. `.mustache` and `.php` are not scanned unless `detector.extensions` in `.impeccable/config.json` adds them.

**What the launcher does (read line by line).** It looks for the engine in this order: `IMPECCABLE_BIN`; a binary beside the script; `~/.impeccable/bin/`; a version-pinned cache under `%USERPROFILE%\.impeccable\bin\0.1.22\`; `impeccable` on PATH. If none is found, it downloads `impeccable-windows-x64.exe` (21,696,392 bytes, release `engine-v0.1.22`, published 2026-11-09) from `github.com/pbakaus/impeccable/releases` with `curl.exe`. It checks the SHA-256 with `certutil` against `engine-checksums.txt` (`4bacf8c756ec3fda3b9fcb69bf1869d4d700ff471be1e31cbff669222dcd8ff2`) and refuses the file on a mismatch, before running anything. That check is sound for what it covers, but it covers only the downloaded file and the version-pinned cache. The other candidates are not hashed: `IMPECCABLE_BIN` is trusted as is ("IMPECCABLE_BIN stays trusted", in the `.cmd` header), and a binary beside the script, in `~/.impeccable/bin/` or on PATH passes on a version handshake only. The download host can be changed with `IMPECCABLE_DOWNLOAD_BASE`, and the cache folder with `IMPECCABLE_HOME`.

**The npm route** gets the engine from a second host. `package.json` lists `@impeccable/cli-windows-x64@0.1.22` (and the other platforms) under `optionalDependencies`, so `npx impeccable` or `npm install impeccable` pulls the Windows engine from the npm registry, not from the GitHub release. Both hosts, and the unhashed candidates above, belong in the IT question.

**The engine** is a compiled Rust program, so no Rust build is needed on Windows (an open question in `PLAN.md`). The npm package only wraps it, and its `package.json` has no install script. The hook part of the engine links the crate that holds an HTTP client. I did not read the hook's full source (`hook_lib.rs`, 90 KB; `hook.rs`, 45 KB), so I cannot say the per-edit hook never uses the network.

## Telemetry and uploads

- **Roll telemetry.** The "roll" feature sends usage data to `https://impeccable.style/api` (`/api/roll`, `/api/chosen`). That feature deals design directions for new work. The data, as the engine's own status line puts it: the seed key, scope, mode, round, pool revision, the winning card's kind and catalog id, and the client version, "never your project's names or code". I found no call to it from `audit`, `critique`, `polish` or `harden`.
- **Account uploads.** After `impeccable login` (OAuth in a browser), `DESIGN.md` uploads on every change. Each saved critique report also uploads: its full text up to 64 KB, the target name and the scores. Critique sync is on by default for a signed-in user.
- **Opt-outs, from the source.**
  - `DO_NOT_TRACK=1` or `IMPECCABLE_NO_TELEMETRY=1` stops the telemetry and the critique upload. So do `impeccable telemetry off` and `"telemetry": false` in the project's `.impeccable/config.json`.
  - `impeccable critiques off` and `impeccable health design off` stop the two uploads.
  - With no sign-in, nothing uploads.
  - A `CI` variable also stops the critique upload.

## Fit with Windows and Moodle

| Topic | Finding |
|---|---|
| Windows launcher | Untested on real Windows, by its own header. It needs `curl.exe` and `certutil`; both ship with Windows 11. |
| Antivirus and monitoring | An unsigned 21.7 MB program downloaded into the user profile and run on every edit is the kind of thing the new monitoring software flags. The launcher even prints "check your antivirus quarantine" when its file disappears. This is the IT question. It covers two download hosts (GitHub releases via the launcher, the npm registry via `npx`/`npm`) and the engine locations the launcher does not hash (`IMPECCABLE_BIN`, PATH, `~/.impeccable/bin/`). |
| Slow machine | The hook adds up to 5 s to every Edit or Write and up to 30 s at the end of a session. We already run `lint-on-save.sh` after every edit, and pages here are slow under the antivirus scan. |
| Files outside `D:\Claude Local\` | The engine cache defaults to `%USERPROFILE%\.impeccable\`. `IMPECCABLE_HOME` can move it to `D:/Claude Local/kit-scratch/`. |
| Writes into our repo | `audit` and `critique` save reports in `.impeccable/` at the project root, and `hooks on` writes `.claude/settings.local.json`. `.claude/settings.local.json` is already gitignored (`.gitignore` line 15); `.impeccable/` needs a `.gitignore` entry, or the pilot runs in a scratch working folder. Both commands also tell the agent to delete temp files, which our workflow agents may not do. |
| Mustache and PHP | Not scanned by default. Mustache could be added as `{ "ext": ".mustache", "engine": "html" }`, but `{{#str}}` tags inside attributes are likely to cause noise. Most of our markup is built by PHP renderers, so the rendered page is the better target. |
| Rendered pages | Two ways. (a) `detect --cdp 9222`, which reads a page from a Chrome started with a DevTools port on 127.0.0.1. Our Playwright harness could start Chrome that way, and the page then stays inside our local-only, masked-data harness. (b) The live-server overlay, which injects a script. (a) fits better. |
| Brand | `SKILL.md` frames the work as "out-of-distribution craft" by "an award-winning design director". For app UI it calls for restraint ("Operate" mode), which suits an LMS. The steer commands (bolder, colorize, overdrive) were not reviewed and should stay out. `redesign` replaces `DESIGN.md`. Our `DESIGN.md` would have to be ours and read-only to the tool. |
| Skill trigger | The skill description triggers on any frontend design, audit or polish request. Installed whole, it would compete with our `frontend-design` skill on every theme task. |

## Instructions found in the texts (reported, not followed)

These are written for an agent. None was acted on.
- `SKILL.md`, Setup step 1: run `scripts/impeccable context` once per session. That is what downloads the engine on first use.
- `critique.md`: spawn two sub-agents; start `live-server`; inject `detect.js`; write a snapshot; delete the temp file; ask questions with AskUserQuestion.
- `audit.md`: run the detector; save the report with `audit-storage`; delete the temp file.
- `hooks.md`:
  - `hooks on` records consent and writes the hook manifests.
  - After that consent, `impeccable context` re-writes missing or stale hook entries on its own.
  - Its triage section tells the agent to add `ignore-value` exceptions itself, into the shared config, when it judges a finding a false positive.
  - On Hermes it tells the user to run `npx impeccable install`.

None of these is hostile. Each would run code, download, or change configuration on this machine, so each needs a decision from a person.

## Risks

1. **Code execution on every edit**, if the hook is installed by `hooks on` or by enabling the Claude Code plugin, by a program from a fast-moving repository (about 2,860 commits). Pinning the checksum stops a swapped binary, but not a change in what a future pinned version does, and it does not cover `IMPECCABLE_BIN`, PATH or the npm route.
2. **Data leaving the machine** only through a sign-in or the roll feature. Containment: never run `impeccable login`; set `DO_NOT_TRACK=1` and `IMPECCABLE_NO_TELEMETRY=1`; add `"telemetry": false` to the project config.
3. **Real personal data on screen.** The local database is an import of production. Scan only pages that show seeded test personas, as the visual-pass scripts do.
4. **Noise on Moodle markup** we do not control (core renderers, Bootstrap). The pilot must measure this.
5. **The agent self-serving detector exceptions** into a shared config file. Any ignore should be approved by a person.
6. **Licence.** Apache 2.0. Copying any of its text into our repo needs the licence text, the repo's `NOTICE.md`, a note on changed files, and a `THIRD-PARTY-NOTICES.md` entry. Paraphrase avoids all of that.

## Recommended next steps

**Now, with no install and no IT question**
- Fold the `polish` order of work and the `harden` input list into our UI checklist, paraphrased. This would go in `tools/visual-pass/README.md` or the `frontend-design` skill (another agent owns that skill today).
- Use `audit` sections 1 to 4 as the categories the fresh-eyes reviewer reports against.

**Pilot (Stage 3 step 2), only after IT agrees to the download**
1. One download of `impeccable-windows-x64.exe` v0.1.22, checked against SHA-256 `4bacf8c7...d8ff2`, into `D:/Claude Local/kit-scratch/impeccable/`, with `IMPECCABLE_HOME` pointing there.
2. Environment for every run: `DO_NOT_TRACK=1`, `IMPECCABLE_NO_TELEMETRY=1`, and no `IMPECCABLE_BIN` other than the checked file. No `login`. No `hooks on`. No Claude Code plugin install. No `npx`/`npm` route.
3. Run `detect --json --no-config` on a copy of the built theme CSS and a copy of the theme's `templates/` folder (with the Mustache extension declared). Then run `detect --cdp` on two rendered local pages (learner dashboard, course page) as seeded test personas.
4. Count real findings against noise, and compare with the last manual pass on the same pages. The go/no-go rule in `PLAN.md` stands: adopt only if at least half the findings are real and it finds something our pass missed.

## Not verified

- Whether the per-edit hook or the `context` verb makes any network call. The hook links an HTTP client. I did not read its full source.
- Whether `DO_NOT_TRACK=1` stops the DESIGN.md upload. `DO_NOT_TRACK=1` (or `IMPECCABLE_NO_TELEMETRY=1`) stops the roll telemetry and the critique upload (`telemetry.rs`, `critiques.rs`). The DESIGN.md upload stops with `impeccable health design off`; whether `DO_NOT_TRACK` also stops it was not checked (`health.rs` not read). Without a sign-in, neither upload runs.
- Which plugin `.claude-plugin/marketplace.json` offers, and what the four `plugin/agents/*.md` files instruct. Neither was read.
- Whether the Windows launcher works on this machine. By its own header, it has never run on a real Windows machine.
- What the steer commands and `new-work.md` (66 KB) instruct. They were out of scope.
- The detector's 62 rules (the `PLAN.md` figure, from the README). The rule list was not read.
