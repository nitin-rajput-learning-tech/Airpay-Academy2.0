# Visual pass tools

Scripts that log the seeded test personas into the local site and save screenshots for a UI change. Logins come
from `.personas.local.json` (gitignored, written by `provision_local_personas.php`). Screenshots go to
`moodle-enhancement/docs/visual-evidence/YYYY-MM-DD/`.

All scripts fix the base URL to `http://localhost:8080`. Scripts that import `evidence_lib.mjs` (`coverage_crawl.mjs`
and the `*_checks.mjs` files not listed below) and `persona_journeys.mjs` also block every other host in the browser,
apart from the Google Fonts files. The older 2026-09-29/30 scripts (`visual_pass.mjs`, `cart_checks.mjs`,
`profile_checks.mjs`, `qr_loginas_checks.mjs`, `qr_recheck_checks.mjs`, `probe_messages.mjs`) do not block other
hosts.

`evidence_lib.mjs` holds the shared helpers (`session`, `go`, `scan`, `shots`). The `*_checks.mjs` files are the
per-change evidence scripts; `persona_journeys.mjs` runs the full persona journeys.

## Screen widths

By default `shots()` saves two full-page screenshots: desktop (1440 px) and mobile (590 px).

A check can ask for more widths with the `widths` option (added 2026-11-12, an idea taken from Hallmark):

```js
const files = await shots(page, OUT, '02-course-page', { widths: [1440, 1024, 768, 590, 390] });
// -> 02-course-page-1440px.png, 02-course-page-1024px.png, ... ; files.overflow['390px'] is the horizontal overflow
```

Use it for layout work (navigation, drawers, tables, cards), where a page can break between our two default widths.
Widths must be whole numbers from 320 to 2560. With `widths` set, the `mobile` option is ignored.

## Fresh-eyes review before a UI commit

The session that made a UI change has seen the code and knows what the page is meant to look like, so it tends to see
what it expects. Before the commit, a second agent that has not seen the change reviews the screenshots. (An idea
taken from Hallmark, 2026-11-12.)

1. Start a new agent with no access to the diff or the session history. Give it only:
   - the screenshots of the change, every width that was captured;
   - the brand rules: the design tokens in `.claude/rules/frontend.md` (primary #0066A7, accent #1985DD, Montserrat,
     8 px grid);
   - one sentence per screenshot saying what the page should show and for which persona.
2. Ask it to list defects per screenshot, each with the file name and where on the page: horizontal overflow, clipped
   or overlapping text, a missing string marker (`[[...]]`), a raw template tag, broken alignment or spacing, a colour
   or font outside the brand rules, low contrast, an empty area with no message, a control too small to tap at 590 px
   or below. It should say "no defects" for a clean screenshot rather than invent one.
3. The main session fixes each defect or writes down why it stays, then captures again.
4. The findings and what was done about each go into the visual-evidence `README.md` for that day.

This does not replace Nitin's review. It is meant to catch the obvious defects before he sees the screenshots.
