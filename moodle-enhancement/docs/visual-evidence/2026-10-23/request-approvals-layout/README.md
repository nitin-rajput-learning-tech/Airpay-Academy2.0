# Visual evidence: approvals page without the duplicated header (2026-10-23)

`local_sentientia_request` 2026102300 / 1.6.3. Local XAMPP (Moodle 5.1), test persona `vp_manager1` (line manager of
`vp_learner1`), desktop 1440 px and mobile 590 px.

**Before** (`../../2026-10-16/request/02-linemanager-approvals-page-*`): "Pending approvals" printed twice, and the
breadcrumb "Preferences / Pending approvals (2)" printed twice. `approvals.php` used the theme's `admin` layout, which
adds its own page header and breadcrumb above the template's heading.

**After** (`01-linemanager-approvals-standard-layout-*`): the page uses the `standard` layout, like My requests. The
page has one heading and one breadcrumb (measured: headings `["Pending approvals"]`, breadcrumbs 1). The two pending
test requests (52 and 54) still show with Approve and Reject; neither button was pressed.
