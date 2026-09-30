# Theme shell bundle - visual evidence (2026-09-30)

Branch `claude/persona-fix-theme`, theme `theme_sentientia` 1.0.58-beta / 2026093002 (2026093001 plus the review fix-up below).
Source of the work: `../personas/TRIAGE.md` (bundle 4: D5, D7, D13).

## What changed

| Id | Change | Visible |
|---|---|---|
| D5b | Gradebook (grader report) no longer pushes the page past the viewport at 390 px. `body.path-grade-report-grader #region-main` is un-floated (`float:none; width:100%; min-width:0; display:block`). | yes |
| D5a | New `templates/core/sticky_footer.mustache` adds the `stickyfooter` class, so the grader JS stops throwing `Cannot read properties of null (reading 'offsetHeight')`. It also declares the `{{$ extradata }}` block (core's Bulk edit toolbar passes its `data-for` hook through it), and `.stickyfooter.v-hidden` is `display:none` so a hidden toolbar leaves no blank band. | console only |
| D7 | Language switcher in the sidebar above Dark Mode, behind the default-OFF flag `ux.languageSwitcher.enabled`. | only when the flag is ON |
| D13 | `course.mustache` closes `body` and `html`. | no |

## Screenshots in this folder

Taken on the local XAMPP site as the seeded trainer persona (`vp_author1`), course 448, grader report.
Nothing was copied into `C:/xampp`: the "after" images are the **served** page with the new rule injected
into the live DOM (`page.addStyleTag`) and the `stickyfooter` class added before the AMD modules run, which
is exactly what the changed SCSS and template produce.

| File | State |
|---|---|
| `grader-before-390.png` | current behaviour, 390 px wide: the grader card is cut at the right edge, document `scrollWidth` 415 |
| `grader-after-390.png` | with the fix: card fits the column, the table scrolls inside `.gradeparent`, `scrollWidth` 390, zero console errors |
| `grader-before-1440.png` | current behaviour, 1440 px wide: the card is shrink-wrapped (815 px of a 1180 px column) |

Measured numbers (same probe, before -> after):

| Width | `scrollWidth` | `#region-main` | Console |
|---|---|---|---|
| 390 | 415 -> 390 | 677 px -> 358 px | 1 error -> 0 (with the `stickyfooter` class) |
| 1440 | 1440 -> 1440 | about 815 px (read off the before shot) -> 1132 px, the full column | not re-measured |

`grader-after-1440.png` was not captured: the local server started timing out (PHP 120 s limit on a cold
cache) right after the before shot. The 1440 px "after" numbers were measured earlier in the same way.

## Review fix-up (2026093002)

- Sticky footer: the template declares `{{$ extradata }}` and `.stickyfooter.v-hidden` is `display:none`. Without
  the block, Bulk edit on a course page in edit mode never enabled its toolbar, and with `visibility:hidden` the
  hidden footer left a blank band the height of the toolbar. **Check:** editing on in a course, click Bulk edit,
  the toolbar appears; editing off, no blank band under the course content.
- Language switcher: the endpoint takes the choice as `code` (core applies any GET `lang` to the session while
  `config.php` loads), sets its page context before the sesskey check, and the return url drops `sesskey`.

## Still needs a look after deployment (not possible here)

Missing shots (capture after deploy and a cache purge):

- `grader-after-1440.png` (the trainer gradebook at 1440 px, card spanning the column);
- the trainer gradebook at 390 px with `scrollWidth` 390 and no `offsetHeight` console error;
- a course page in edit mode with Bulk edit open, and the same page with it off;
- the language switcher, flag ON for a test tenant: desktop, 590 px drawer, dashboard plus one other shell page,
  sidebar expanded and collapsed.

The language switcher renders only with the flag ON, and nothing may be copied into `C:/xampp` from this
session, so there is no screenshot of it yet. After deploy: turn `ux.languageSwitcher.enabled` ON for a test
tenant in the Switchboard, then check desktop and 590 px (drawer open) on the dashboard and on one other
shell page, with the sidebar expanded (the control is hidden when the sidebar is collapsed).

## Owner configuration for D7

- Turn the flag ON (Switchboard, per customer or tenant). Default is OFF, so production looks unchanged.
- Install the `hi` language pack on UAT and production (Site admin > Language > Language packs). With only `en`
  installed the switcher hides itself.
- Nothing needs to change in core. The switcher ignores `$CFG->langmenu` (0 on the local copy, which is
  why core's own menu showed nothing). Leave `langmenu` as it is.
- The languages offered are the installed language packs (Site admin > Language > Language packs), narrowed
  by `$CFG->langlist` if that is set. Fewer than two languages means no control.
- A choice applies at once and is saved to the user's profile language (unless the site removed
  `moodle/user:editownprofile` from users, in which case it lasts for the session).

## Follow-up (D7 is not closed)

The switcher is in the signed-in shell sidebar only. The login page (guest template) and the front page
(`layout/frontpage.php`) have no language control yet.
