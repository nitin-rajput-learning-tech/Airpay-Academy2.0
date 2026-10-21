# Visual evidence: skills and org names, self-rate warning (branch `claude/ui-fix-skills-org`, merged 2026-10-16 as a5b26a161)

Local XAMPP (Moodle 5.1, the merged code: skills 2026101502, org 2026101502, users 2026101501, theme with the
2026-10-21 fix below), desktop 1440 px and mobile 590 px. Captured 2026-10-21 by
`tools/visual-pass/fix_branches_checks.mjs --only skills`.

**What changed**
1. **The self-rate dialog warns inside the dialog** when Save is pressed with no level. Before, the warning went to the
   page's notification area, behind the dialog's backdrop, so Save seemed to do nothing.
2. **Skill, skill-category and org names print "&" once.** `format_string()` output used to reach `{{ }}` templates,
   the datatable and the radar, which escaped it again: "Compliance &amp; Risk".
3. **The profile's "Suggested next courses" names are plain text** (`users`).

| Shot | Persona | Shows | Measured (`results.json`) |
|---|---|---|---|
| `01-learner-my-skills` | `vp_learner1` | My Skills | 0 visible `&amp;` |
| `02-learner-skill-view` | `vp_learner1` | "Data Privacy & GDPR" (category "Compliance & Risk"): title, breadcrumb, category | 0 visible `&amp;` |
| `03-learner-self-rate-no-level-warning` | `vp_learner1` | Save my level pressed with no level: "Please pick a level first." inside the dialog, under the select; the select is marked invalid; the dialog stays open. Then cancelled, and nothing was saved. | warning shown, `is-invalid`, `aria-invalid=true` |
| `04-siteadmin-skills-admin` | `vp_siteadmin` | Skills administration: "Compliance & Risk", "Sales & Business", "Acquiring & Settlement" | 0 `&amp;` in text or attributes |
| `05-siteadmin-org-tree` | `vp_siteadmin` | Organisation tree with the Airpay tenant opened: "Compliance & Security", "Finance & Accounts", "Alliances & Partnerships", "Learning & Development" | 0 `&amp;`; all 104 row-menu `data-name` values carry the plain "&" |

**Not shown:** finding 3, the profile suggestions. No local test persona has a skill gap with a mapped course, so the
profile shows no "Suggested next courses" card. It is covered by the users unit test added on the branch
(`user_manager::build_profile_context` passes `plain = true`).

**Found and fixed during this capture (2026-10-21, commit feb6f6cc9).** The theme's screen-reader-only page heading
(A11Y-8 in `core_renderer`) ran `format_string()` and `s()` over `$PAGE->heading`, which is already HTML. So
`view.php` had a hidden `<h1>` reading "Skill: Data Privacy &amp; GDPR", and screen readers said "amp". The same
applies to every page whose heading has "&". `core_renderer::sr_heading_text()` now reduces the heading to text once.
Shot 02 was taken after that fix: 0 `&amp;`.

**Timing note:** the warning is a language string fetched on demand, which took about 9 s on this slow local box. The
capture waits for it.

**State:** no flag, no seed. The dialog was cancelled. Only test personas appear; skill and org names are
configuration, not personal data.
