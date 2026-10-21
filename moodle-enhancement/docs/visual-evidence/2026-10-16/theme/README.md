# Visual evidence: theme fixes (branch `claude/ui-fix-theme`, merged 2026-10-16 as 6447c3efc)

Local XAMPP (Moodle 5.1, the merged code, `theme_sentientia` 2026101504), desktop 1440 px and mobile 590 px. Captured
2026-10-19 by `tools/visual-pass/fix_branches_checks.mjs --only theme`.

**What changed**
1. **The edit-mode switch** is the theme's Bootstrap 4 `custom-switch`. Before, it was the 5.2 core Bootstrap 5 markup,
   which drew a bare browser checkbox.
2. **A course author keeps edit mode, and the course editor works.** Three theme causes were fixed:
   - `core_renderer` reset `$USER->editing` for everyone without `moodle/course:view`, which an editingteacher does not
     hold;
   - `layout/course.php` queued a second edit-switch init with no element;
   - `course_full_header` dropped the header actions, which broke Bulk edit.
3. **Dropdowns written with only `data-bs-toggle`** (core 5.x markup) open in this Bootstrap 4.6 theme, once per click.
   This covers elements carrying both attributes too: the FX-20 double toggle is gone.

| Shot | Persona | Shows | Measured (`results.json`) |
|---|---|---|---|
| `01-courseauthor-course-edit-switch-off` | `vp_courseauthor1`, editingteacher of the test course `vp_journey_free` (id 448) | The course page, edit mode off. The switch is the styled toggle. | `custom-control-input` inside `.custom-switch`, track 35 px; not editing |
| `02-courseauthor-course-edit-mode-on` | same | The switch turned on: the page is in edit mode with its editing controls | `body.editing`; 5 add-activity buttons, 5 section menus, the Bulk edit button; console clean |
| `03-courseauthor-course-after-reload-still-editing` | same | The same page after a reload, **still in edit mode** (before the fix every load came back in viewing mode) | identical to 02 |
| `04-courseauthor-activity-chooser` | same | "Add an activity or resource" opens the chooser. It was closed again, and nothing was added. | console clean |
| `05-learner-mycourses-sort-dropdown-open` (desktop) | `vp_learner1` | My courses: the sort selector (Bootstrap 5 markup only) opened by one click | before: `aria-expanded=false`; after one click: `true`, menu shown; a second click: `false` |
| `06-siteadmin-certificate-templates-row-menu-open` (desktop) | `vp_siteadmin` | Certificate templates: a core row action menu (`data-bs-toggle` only) opened by one click | the same: opens on the first click, closes on the second |

Shots 05 and 06 are desktop only, because resizing the window would close the open menu.

**State:** edit mode is per session and was turned off again at the end. The chooser was cancelled. No flag and no data
changed.

**Seen, not part of this fix:**
- in edit mode, the "No groups" label of an activity wraps under its icon;
- on the course page the left drawer opens empty, because the course index sits inside the page.
Both are cosmetic and are listed for the next theme pass.
