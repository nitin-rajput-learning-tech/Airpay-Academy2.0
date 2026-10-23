# Dashboard: every declared block region printed into the hidden container (theme 2026102301 / 1.0.60-beta)

## The defect

On UAT `/my/`, Meera saw a "TypeError: Cannot read properties of null (reading 'addEventListener')" popup in both
the Admin and the Employee view (`before-uat-typeerror.jpg`, captured through Claude in Chrome on her session).

The console stack named `theme_sentientia/chart_loader.min.js:4354`. That label is misleading. Line 4354 of the
combined RequireJS bundle is core `block_recentlyaccesseditems/main`. Its `registerEventListeners()` runs when a user
has more than 3 recent items, and it calls `document.querySelector('[data-region="recentlyaccesseditems-view"]
[data-action="more-items"]').addEventListener(...)` with no null check.

The block's markup was not on the page:

- Core `core_renderer::standard_head_html()` calls `ensure_content_created()` for every region the layout declares,
  so each block's `get_content()` runs and its `{{#js}}` init is queued (core_renderer.php:187 on 5.3).
- The `mydashboard` layout declares 10 regions: `side-pre` plus the nine `layer*` regions from BizLMS.
- `dashboard.mustache` printed none of them. Only the content region (myoverview, timeline) was printed, through
  `main_content`, inside the `#page-content` container that the template hides on purpose ("blocks not used").
- So the init ran against a root that did not exist.

Meera has the core default dashboard blocks (calendar_month, recentlyaccesseditems) in `side-pre`. The local test
learner had neither, which is why the fault did not reproduce locally until it was seeded
(`kit-scratch/uatreh/seed_recent_items.php`: the block plus 5 recent items).

## The fix

`templates/dashboard.mustache` prints every declared region into that same hidden `#page-content` container, next to
`main_content`. The block JS finds its root and nothing becomes visible. Every block was already running on every
dashboard load, so there is no new cost.

Two alternatives were tried and rejected:

| Tried | Result |
|---|---|
| Stop calling `$OUTPUT->blocks()` in `layout/dashboard.php` | No effect. Core renders the regions in `standard_head_html()` before the layout runs, so the init was still queued and the error still came. |
| Declare no regions for `mydashboard` in `config.php` | The error went away, but core's `add_region('content')` (my/index.php) takes the empty default region, and blocks recorded in undeclared regions are merged into it (blocklib.php:554, 805-811). The calendar and recent-items blocks appeared on the dashboard: a visible change. |

## Verified (local 5.3 runtime, e2e_learner seeded)

| | Before (HEAD template) | After |
|---|---|---|
| Block init queued | yes | yes |
| Block markup in DOM | no | yes (hidden) |
| Console | `jQuery.Deferred exception: Cannot read properties of null (reading 'addEventListener')`, same stack as UAT | none |
| Blocks visible (`offsetParent`) | none | none: myoverview, timeline, calendar_month, recentlyaccesseditems all hidden |
| Pending JS after load | `core/notification:displayException` | none |

Captured at desktop 1440x900 (`after-desktop.png`) and mobile 390x844 (`after-mobile.png`).

## Open, for Nitin

- **Show or keep hiding the dashboard blocks.** These are the blocks users and admins placed (calendar, recent items,
  the BizLMS layer blocks). Boost shows `side-pre` in a drawer. This fix keeps today's behaviour (hidden).
- **`course` layout has the same shape.** It declares `course-pre`, which no layout or template prints, so blocks in
  that region run on every course page for nothing. Removing the region would move those blocks into the course drawer
  (a visible change), so it is left as it is.
