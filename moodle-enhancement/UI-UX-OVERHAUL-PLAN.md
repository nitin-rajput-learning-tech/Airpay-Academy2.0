# Airpay Academy — UI/UX Overhaul Plan
**Date:** 2026-04-03
**Status:** DRAFT — for discussion before build
**Theme:** `theme_airpayux` (standalone fork of epsilon, 514 files)
**Reference:** 22 C-suite approved prototypes at `D:\Claude Local\Moodle Backup\03-prototypes\preview\`

---

## Design Language (extracted from ALL 22 prototypes)

Every prototype shares these patterns — this is the Airpay Academy visual DNA:

| Token | Value | Used For |
|-------|-------|----------|
| Primary | `#0066A7` | Brand, buttons, links, block headers |
| Accent | `#0f7a73` | Secondary actions, gradient end, teal highlights |
| CTA Gradient | `135deg: #0066A7 → #0f7a73` | All primary action buttons |
| Hero Gradient | `160deg: #003d66 → #0066A7 → #0f7a73` | Login left panel, page heroes |
| Background | `#F2F4FB` | Page body |
| Surface | `#FFFFFF` | Cards, modals, dropdowns |
| Text | `#1a1a2e / #607286 / #8896a6` | Primary / Secondary / Muted |
| Radius | `12px / 16px / 20px / 999px` | Cards / Modals / Heroes / Pills |
| Shadow | `sm / md / hover` | Cards / Dropdowns / Interactive |
| Font | Montserrat 400-800 | Everything |
| Hover | `translateY(-2px) + shadow-hover` | Cards and buttons lift on hover |

---

## The 10 Surfaces — Current vs Target

### Surface 1: LOGIN PAGE
**Prototype:** `login.html`
**Moodle files:** `templates/core/loginform.mustache` + `layout/login.php` + SCSS

| Aspect | Current (epsilon) | Target (prototype) |
|--------|------------------|-------------------|
| Layout | 50/50 split: form left, slider right | 50/50 split: **gradient hero left**, form right |
| Left panel | Plain white with logo + form | Gradient hero (#003d66 → #0066A7 → #0f7a73) with decorative circles, logo, tagline, 3 feature bullets, social proof stats card |
| Right panel | Image carousel slider | Clean white form: heading "Welcome back", icon-prefixed inputs, gradient CTA, forgot/register links |
| Form inputs | Basic Moodle form-control | 48px height, 12px radius, icon prefix (user/lock), blue focus ring |
| Login button | Standard BizLMS teal | **Gradient CTA** (blue→teal), hover lifts with shadow |
| Mobile | Stacks vertically | Hero panel collapses to compact header, form takes full screen |

**Implementation:** Rewrite `loginform.mustache` HTML structure + add SCSS. The `layout/login.php` stays — it renders the template. The template swap is the big change — moving form to right, adding gradient hero panel to left.

**Decision needed:** Keep BizLMS image slider on right (production pattern) OR replace with gradient hero panel (prototype pattern)?

---

### Surface 2: NAVBAR (logged-in pages)
**Prototype:** `employee-dashboard.html` navbar section
**Moodle files:** `templates/navbar.mustache` + SCSS

| Aspect | Current (epsilon) | Target (prototype) |
|--------|------------------|-------------------|
| Height | ~60px | 64px |
| Left | Logo + "Hello [Name]" + quote | Logo + brand name + nav links (Dashboard, Courses, Classrooms active states) |
| Right | Quick Access ≡ + notification icons + avatar | Search + notification bell (with badge) + theme toggle + user avatar dropdown |
| Quick Access | Hamburger popover with 20+ links | **Decision:** Keep popover OR move to sidebar (admin) / nav links (employee)? |
| Style | White bg, basic shadow | White bg, `shadow-sm`, circular bordered icon buttons |
| Mobile | Hamburger toggle | Same hamburger + bottom drawer |

**Implementation (already partially done):** `navbar.mustache` edited — greeting simplified, icon buttons styled. Still needed: nav link pills, search box, remove quote entirely.

**Decision needed:** Keep Quick Access hamburger (BizLMS pattern, 20+ links) OR redesign navigation structure entirely?

---

### Surface 3: FOOTER
**Prototype:** All prototypes share same footer
**Moodle files:** `templates/footer.mustache` + SCSS

| Aspect | Current (epsilon) | Target (prototype) |
|--------|------------------|-------------------|
| Background | Dark `#1a1d27` | Same |
| Top border | 3px primary blue | Same (already applied) |
| Content | Copyright + social icons | Quick links (Privacy, Terms, Data) + copyright + social icons |
| Debug area | Purge caches, reactive instances | Same (Moodle dev info, hidden in prod) |

**Status:** MOSTLY DONE via SCSS. Footer template edit is optional — current epsilon footer structure + our SCSS achieves 90% of the target.

---

### Surface 4: EMPLOYEE DASHBOARD
**Prototype:** `employee-dashboard.html`
**Moodle files:** `layout/dashboard.php` + `templates/dashboard.mustache` + SCSS

| Aspect | Current (epsilon) | Target (prototype) |
|--------|------------------|-------------------|
| Layout | Navbar + blocks (Timeline, Calendar, Recently Accessed) | Navbar + **welcome banner** + **stat cards** + **course grid** + **sidebar** |
| Welcome | "Hi, Super! 👋" plain text | Gradient CTA banner with greeting, motivational text, 2 quick-action buttons |
| Stats | None | 4-column KPI grid: Courses In Progress, Completed, Certificates, Hours Learned |
| Courses | Timeline block (upcoming activities) | **Course cards** with cover images, progress bars, category badges, continue/start buttons |
| Sidebar | Calendar block, Recently Accessed block | **Upcoming deadlines** list + **recent achievements** + **recommended courses** |

**Implementation:** This is the biggest change. Epsilon's `dashboard.php` queries DB and passes data to `dashboard.mustache` which arranges block regions. We need to:
1. Add PHP queries in `dashboard.php` for enrolled courses, completion stats, deadlines
2. Rewrite `dashboard.mustache` HTML structure with our component patterns
3. Add substantial SCSS for welcome banner, stat cards, course cards, sidebar

**Decision needed:** Keep BizLMS dashboard blocks (Timeline, Calendar) alongside our new components, OR replace entirely?

---

### Surface 5: ADMIN DASHBOARD
**Prototype:** `admin-dashboard.html`
**Moodle files:** Same as Surface 4 (role-conditional in `dashboard.php`)

| Aspect | Current (epsilon) | Target (prototype) |
|--------|------------------|-------------------|
| Layout | Same as employee + Quick Nav blocks | **Sidebar navigation** (260px, collapsible) + **top bar** + **main area** |
| Navigation | Quick Access hamburger popover | Fixed sidebar with icons + labels for all 20+ admin pages |
| KPIs | None (Quick Nav cards with counts) | 4-column KPI grid with color-coded top borders (users, courses, completions, revenue) |
| Content | Quick Nav cards → Calendar | **Charts** (enrollment trends, completion rates) + **quick nav tiles** + **activity feed** |

**Implementation:** This is a MAJOR structural change — adding a sidebar layout requires a new layout file or conditional logic in `dashboard.php`. The prototype uses a completely different navigation paradigm from epsilon.

**Decision needed:**
1. **Sidebar for admin?** This changes the entire page structure. Admin gets sidebar, employee gets top-bar-only. Is that the direction?
2. **KPI data source?** The prototype shows charts — where does this data come from? LearnerScript? Direct DB queries? BizLMS API?

---

### Surface 6: COURSE CATALOG
**Prototype:** `catalog.html`
**Moodle files:** `local/courses/templates/*.mustache` (BizLMS plugin, NOT in theme)

| Aspect | Current (BizLMS) | Target (prototype) |
|--------|------------------|-------------------|
| Layout | Filter sidebar + DataTable/card grid | **Filter bar** (top, horizontal) + **card grid** (4-column) |
| Filters | Left sidebar: expandable filter groups | Top bar: category dropdown + type pills + search + sort |
| Cards | Basic: image, title, description, button | Rich: gradient bg, type badge, internal badge, title, tags, description (2-line clamp), price, enrollment count, 2 action buttons |
| Pagination | Standard | Same |
| Payment | BizLMS cart → Airpay gateway | Same flow, styled with prototype modal pattern |

**Implementation:** This requires editing BizLMS `local/courses/` templates directly — not in our theme directory. Since we own the git repo, we can edit these files.

**Decision needed:** Edit BizLMS plugin templates directly OR create theme-level overrides? (With the fork approach, direct editing is cleaner.)

---

### Surface 7: COURSE DETAIL
**Prototype:** `course-detail.html`
**Moodle files:** `local/search/coursedetails.php` (BizLMS plugin)

| Aspect | Current (BizLMS) | Target (prototype) |
|--------|------------------|-------------------|
| Hero | Course image as background + title overlay | **Gradient hero** with radial glow, breadcrumb, type/compliance badges, title, rating + stats |
| Layout | Two-column: description + sidebar | Two-column: **tabbed content** (Overview, Curriculum, Instructor, Reviews) + **sticky sidebar** |
| Sidebar | Course info list + "Start Now" button | **Sticky card** with price, add-to-cart, enroll CTA, metadata list, share buttons |
| Curriculum | Not shown | **Accordion modules** with lesson list, duration, completion checkmarks |
| Reviews | Not on production | **Review cards** with avatar, rating stars, text |

**Implementation:** This is a PHP page (not a Mustache template). Requires editing `coursedetails.php` directly to change the HTML output structure.

---

### Surface 8: USER PROFILE
**Prototype:** `profile.html`
**Moodle files:** `local/users/templates/*.mustache` (BizLMS plugin)

| Aspect | Current (BizLMS) | Target (prototype) |
|--------|------------------|-------------------|
| Header | Basic profile card | **Gradient header** with avatar (96px, upload overlay), name, role, department badge, action buttons |
| Content | Tabbed: Profile, Courses, Classrooms, LPs, Exams, Programs | Same tabs but styled with prototype patterns |
| Skills | Basic table (block_myskills) | Visual skill progress indicators |

---

### Surface 9: ADMIN TABLES (Manage Users, Manage Courses, etc.)
**Prototypes:** `manage-users.html`, `manage-courses.html`
**Moodle files:** BizLMS `local/users/`, `local/courses/` templates

| Aspect | Current (BizLMS) | Target (prototype) |
|--------|------------------|-------------------|
| Page header | Title + underline | Title + subtitle + breadcrumb |
| Action bar | Search + toolbar icons | **Search** (flex, icon prefix) + **filter dropdowns** + **add button** (gradient CTA) |
| Table | DataTables (jQuery) | Clean bordered table with avatars, status badges, action buttons |
| Stats | None above table | Optional: 4-column stat cards above table |

**Implementation:** BizLMS admin pages use DataTables (jQuery). Restyling requires CSS targeting DataTables selectors — no template changes needed for basic styling.

---

### Surface 10: MOBILE EXPERIENCE
**Prototype:** All prototypes have responsive breakpoints
**Moodle files:** SCSS + `templates/primary-drawer-mobile.mustache`

| Breakpoint | Behavior |
|-----------|----------|
| `>1024px` | Full layout (4-col grids, sidebar visible) |
| `768-1024px` | 2-col grids, sidebar collapses, nav links hide |
| `<768px` | Single column, hamburger menu, bottom nav option |
| `<480px` | Compact: smaller fonts, full-width cards, stacked filters |

---

## Implementation Strategy — 3 Options

### Option A: CSS-Heavy (Safest, Fastest)
- Keep ALL existing Mustache templates as-is
- Apply the design system purely through SCSS overrides
- Target existing CSS classes with higher specificity
- **Pros:** Zero risk of breaking BizLMS functionality, fastest to implement
- **Cons:** Limited visual transformation — can't restructure HTML (e.g., can't move login form to right panel, can't add gradient hero)
- **Result:** ~60% of prototype look achieved. Rounder, bluer, cleaner — but same layout structure.

### Option B: Template Rewrite (Medium Risk, Full Visual Match)
- Rewrite key Mustache templates with new HTML structure
- Preserve ALL Mustache variables (data bindings stay identical)
- Add PHP queries in layout files where needed (dashboard stats)
- **Pros:** Achieves 90%+ of prototype visual. Full control over layout.
- **Cons:** Risk of breaking BizLMS rendering if Mustache variables are missed. More testing needed.
- **Surfaces:** Login (rewrite), Dashboard (rewrite), Navbar (partial rewrite done), Footer (CSS sufficient)

### Option C: Hybrid (Recommended)
- **CSS-only** for: Footer, Admin Tables, Mobile responsive, Quick Access popover
- **Template rewrite** for: Login page, Dashboard (both roles), Navbar
- **BizLMS plugin CSS** for: Course catalog cards, Course detail, Profile header
- **New PHP + template** for: Dashboard stat queries, Welcome banner

**Rationale:** The 3 highest-impact surfaces (Login, Dashboard, Navbar) need structural HTML changes to match the prototypes. Everything else can be achieved with CSS targeting existing elements.

---

## Build Sequence (if Option C approved)

### Sprint 1: Foundation ✅ DONE
- [x] Design tokens in head.mustache + default.scss
- [x] Navbar SCSS (circular icons, simplified greeting)
- [x] Footer SCSS (dark footer, blue border)
- [x] Git commit: `4489d44`

### Sprint 2: Login Page
- Rewrite `templates/core/loginform.mustache` — split-screen with gradient hero left, form right
- Edit `layout/login.php` — remove Bootstrap wrapper constraints
- Add login SCSS — gradient panel, decorative circles, icon-prefixed inputs, gradient CTA
- Test: desktop, mobile 375px, form submission, error states, OTP login

### Sprint 3: Employee Dashboard
- Edit `layout/dashboard.php` — add PHP queries for enrolled courses, completion stats
- Rewrite `templates/dashboard.mustache` — welcome banner, stat cards, course grid, sidebar
- Add dashboard SCSS — gradient banner, KPI cards, course cards with progress bars
- Test: multiple user roles, empty states, block compatibility

### Sprint 4: Navbar Completion
- Edit `templates/navbar.mustache` — add nav link pills (Dashboard, Courses, etc.), improve search
- Add SCSS — active states, pill styling, mobile collapse
- Decision: Quick Access stays as popover OR moves to sidebar (admin only)

### Sprint 5: Course Catalog + Detail
- Edit `local/courses/templates/*.mustache` — card grid with prototype patterns
- Edit `local/search/coursedetails.php` — hero section, tabbed content, sticky sidebar
- Add SCSS — catalog filter bar, course cards, detail page components

### Sprint 6: Admin Dashboard + Tables
- Conditional admin layout in `dashboard.php` (sidebar vs top-bar)
- KPI cards with data from BizLMS APIs
- DataTables CSS overhaul for admin management pages

### Sprint 7: Profile + Mobile + Polish
- Profile header gradient via CSS
- Responsive SCSS refactor (590 lines of custom_media.scss)
- Mobile drawer improvements
- Cross-browser testing

---

## Decisions Needed Before Build

| # | Question | Options | Impact |
|---|---------|---------|--------|
| 1 | **Login layout direction?** | A) Keep BizLMS split (form left, slider right) with CSS polish B) Flip to prototype pattern (gradient hero left, form right) | Determines Sprint 2 scope |
| 2 | **Admin sidebar navigation?** | A) Keep Quick Access hamburger popover B) Add fixed sidebar for admin role C) Keep hamburger but style it better | Determines Sprint 6 complexity |
| 3 | **Dashboard blocks?** | A) Keep BizLMS blocks (Timeline, Calendar) + add new components B) Replace blocks entirely with prototype layout | Determines Sprint 3 risk level |
| 4 | **BizLMS plugin editing?** | A) Edit plugin templates directly (cleaner, but future updates harder) B) CSS-only for plugin pages (safer, but limited visual change) | Affects Sprints 5-6 |
| 5 | **Dark mode priority?** | A) Build dark mode in Sprint 1-3 alongside each surface B) Add dark mode as Sprint 8 after all surfaces done C) Skip for now | Affects every sprint |
| 6 | **Quick Access menu — what happens to it?** | A) Keep as-is (hamburger popover) B) Redesign as dropdown with icons C) Move admin links to sidebar, employee links to nav pills | Affects Sprints 4+6 |

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Login rewrite breaks OTP/SSO flow | Medium | High | Preserve ALL Mustache variables, test OTP before commit |
| Dashboard rewrite breaks BizLMS blocks | Medium | High | Keep block regions in template, add new content alongside |
| DataTables CSS conflicts | Low | Medium | Use scoped CSS selectors, test each admin page |
| Mobile layout breaks on some pages | Medium | Medium | Test every surface at 375px after each sprint |
| Production data looks different | High | Medium | Import production DB before Sprint 5 |

---

## Definition of Done (per surface)

1. Visual match to prototype at 80%+ fidelity
2. All existing functionality preserved (forms submit, links work, data displays)
3. Desktop (1440px) + tablet (768px) + mobile (375px) tested
4. No new PHP errors in Apache log
5. No new JS console errors (pre-existing BizLMS errors exempt)
6. Git committed with descriptive message
7. Screenshot comparison: before vs after
