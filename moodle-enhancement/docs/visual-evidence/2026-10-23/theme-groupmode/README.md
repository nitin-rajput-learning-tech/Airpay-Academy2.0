# Visual evidence: the activity group-mode badge in course edit mode (2026-10-23)

`theme_sentientia` 2026102300 / 1.0.59-beta. Local XAMPP (Moodle 5.1), test persona `vp_courseauthor1`
(editingteacher of the test course `vp_journey_free`), edit mode on, desktop 1440 px and mobile 590 px.

**Before** (`../../2026-10-16/theme/03-courseauthor-course-after-reload-still-editing-*`): every activity's group-mode
button showed its icon and then "No groups", which wrapped under the icon inside the round 36 px button.

**After** (`01-courseauthor-edit-mode-groupmode-icon-*`): the badge shows the icon only on desktop, as Moodle's Boost
does. Below the sm breakpoint the text is shown inline under the activity, also as Boost does. The rules were ported
from Boost's `course.scss` (`.activity-groupmode-info`) into the theme's `scss/moodle/course.scss`. No template or PHP
change.
