# Course Edit mode on UAT as the Airpay L&D admin (21 Nov 2026)

Nitin's own check on UAT (Moodle 5.3, code `2962954a6`), course id 3 (POSH 2026), logged in as the Airpay L&D admin test account. He ran it after asking how content is added to a course.

| File | What it shows |
|---|---|
| `meera-course-edit-mode-sections.png` | Course page with Edit mode on: the switch sits at the right of the top bar, a "New section" with an inline rename pencil and a ⋮ menu, unlabelled "+" buttons under each section, "Add section" at the bottom, and the learner "Next: ..." button still showing in Edit mode. |
| `meera-activity-chooser.png` | The "Add an activity or resource" chooser, opened from "+ Add content". |

The browser's own top bar (tabs, address bar and profile picture) is cropped off both images. The rest is UAT test data.

## Result

- **Works.** Edit mode appears for the L&D admin. "+ Add content" opens the activity chooser, and Nitin could edit the course.
- **Settled by this check.** The section button reads "+ Add content", so Subsection is enabled on UAT. The chooser opens on 5.3 under the theme.
- **Nitin's verdict:** "UI is not smooth", and he found no way to delete a section. In core 5.3, Delete sits in the section's ⋮ menu. We have not yet checked whether that menu opens under the theme or what it lists.

Backlog and the full list of what we observed: `moodle-enhancement/docs/audits/2026-11-21-COURSE-EDITING-UX-BACKLOG.md`.
