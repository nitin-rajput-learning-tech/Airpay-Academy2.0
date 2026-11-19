# Phase C visual check: vp_compliance1 (local, 2026-11-19 04:45-05:20)

Local http://localhost:8080 only, read-only (GET loads, one sidebar link click, one BU filter change, which is a GET
reload). Dark mode was set in browser localStorage only. Tables listing people were blurred with injected CSS before
each screenshot, and every copied PNG was checked by eye. Screenshots show the viewport (900 px at 1440, 1000 px at
390); the DOM numbers below come from the same page loads.

Note: locally this persona holds the org-admin role at the Airpay category, and the active role in the sidebar
switcher is "Operations - Administrator". That decides the shell it gets on /my/.

| Check | Result | Proof |
|---|---|---|
| G6: report tabs at 1440 and 390 | PASS: all 4 tabs open with HTTP 200 and no error box (matrix 50 rows, defaulters 4, scorecard 34, manager 23). Export button shown | `G6-*`, `PB-2-*` |
| G7: /my/ is the learner dashboard | BLOCKED: shows the org-admin dashboard because the persona's active role is Operations - Administrator. Sidebar: Dashboard, Analytics, Compliance. No My Team | `G7-my-dashboard-orgadmin-shell-*` |
| G7: sidebar Compliance link opens the tenant report | PASS: opens `index.php` with 4 tabs and Export. KPIs 72% / 659 / 4 / 0 / 0; on 11-18 they were 71% / 659 / 4 / 0 / 0 (71 to 72 is the rounding fix). Also opens at 390 | `G7-sidebar-compliance-click-*`, `G7-compliance-report-opens-390` |
| PB-2: matrix, defaulters, scorecard and manager tabs at 390 | PASS: filter bar is a column; label at y 645, then the select at y 677, 284 px wide, right edge 337 inside the card's 354; scrollWidth 390 | `PB-2-*-390` |
| PB-2: `bu` selected, 390 | PASS: BU and Departments selects are each 284 px wide; red "Clear filters" (rgb 220,38,38) shows below them inside the card | `PB-2-matrix-bu-selected-390` |
| PB-2: matrix at 1440 | PASS: filter bar is one row, white, 1 px border, 10 px radius; select 384 px wide (same as 11-18). Changing BU loads `?tab=matrix&bu=1` with dept and subdept removed | `PB-2-matrix-filterbar-row-1440`, `PB-2-matrix-bu-selected-1440` |
| PB-2: dark mode at 1440 | PASS: bar #1a1d27, select #22252f with text #e8eaed, no white card | `PB-2-matrix-filterbar-dark-1440` |
| PB-3+PB-4: no `&amp;` on analytics | PASS: 0 `&amp;` in the page text; "Human Resources & Admin" and 4 other `&` names show a plain `&` (skill-gap card, 52 rows) | DOM only (the card is below the viewport) |
| PB-3+PB-4: Hindi heatmap at 1440 | PASS: 34 rows, label width 70-73 px with no clipping ("89 उपयोगकर्ता" is 73 px), bar and % on the same row | `PB-3+PB-4-analytics-hi-heatmap-full-label-1440` |
| PB-3+PB-4: English heatmap at 1440 | PASS: users column 70 px on all 34 rows ("N users"), no clipping | `PB-3+PB-4-analytics-en-heatmap-1440` |
| PB-3+PB-4: Hindi heatmap at 390 | PASS (see note): page overflow 0, rows end at 334 inside the card's 355. But the bar is only 0-3 px wide | `PB-3+PB-4-analytics-hi-heatmap-390` (card captured as an element screenshot) |

Observations, not counted as Phase C regressions:
- At 390, the 140 px department column and the users label leave the heatmap bar 0-3 px wide, so it cannot be
  seen. English leaves about the same room, so this was most likely already the case before Phase C.
- At 390 the floating help button partly covers the "Export to Excel" button on the compliance report.
- In dark mode, the active compliance tab looks the same as the other tabs, and the sidebar links are blue. That CSS
  did not change tonight.
