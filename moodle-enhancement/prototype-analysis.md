# Airpay Academy Prototype Analysis Report
## 22 HTML Prototypes — Structured Breakdown
**Source:** `D:\Claude Local\Moodle Backup\03-prototypes\preview\`
**Generated:** 2026-04-04

---

## Hub Page Organization (from hub.html)

The hub.html file categorizes all 22 pages into 3 groups:

| Group | Label | Pages |
|-------|-------|-------|
| 0 — Public Surface | Globe icon | homepage, catalog, course-detail, login, signup, privacy-policy, terms-of-use |
| 1 — Learner Journey | Graduation cap | employee-dashboard, course-player, assessment, certificate, profile, edit-profile |
| 2 — Admin Panel | Tachometer | admin-dashboard, manage-users, manage-courses, reports, exams, organisation, classrooms |

Plus: `index.html` (stakeholder preview) and `hub.html` (page hub/index) as meta pages.

---

## FILE 1: admin-dashboard.html

**Page name:** Dashboard (Admin)
**Color mode:** Light + Dark (toggle via data-theme attribute, persisted in localStorage)
**Navbar style:** Left sidebar navigation with collapsible toggle. Logo at top, user avatar at bottom. Links: Dashboard (active), Manage Users, Manage Courses, Reports, Online Exams, Organisation, Classrooms, Settings. Top bar has hamburger menu, search input, notifications bell, and theme toggle button.
**Main content sections (top to bottom):**
1. KPI grid — 4 cards in a row
2. Charts row — 2 chart cards side by side (Enrollment Trend line chart, Course Distribution pie chart)
3. Quick Navigation — 6 tile links to admin sub-pages
4. Two-column cards row — Recent Activity (timeline list) + System Alerts (color-coded alert items)

**Key UI components:** KPI stat cards with trend badges, CSS-drawn line chart with bars, CSS-drawn pie chart with donut segments, quick-nav tile grid, activity feed list, alert items (amber/blue/neutral), sidebar navigation with active states, fade-in animations

**Data elements shown:**
- Total Users: 94 (+12 this month)
- Active Courses: 54 (+6 this quarter)
- Enrollments: 1,847 (+156 this week)
- Completion Rate: 76.2% (+3.4% vs last month)
- Enrollment trend by month (Jan-Dec bar chart)
- Course distribution pie: Compliance 35%, Tech Skills 25%, Soft Skills 20%, Business 15%, Other 5%
- Recent Activity: course published, user enrolled, certificate issued, course completed, exam scheduled
- System Alerts: 3 users pending approval, 7 certificates in queue, scheduled maintenance

**Footer style:** No visible footer (admin sidebar layout fills the page)

---

## FILE 2: assessment.html

**Page name:** Assessment -- IT Security Awareness
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Minimal header bar with back arrow link to course-player, course title centered, timer badge on right, theme toggle. No full sidebar or traditional navbar.
**Main content sections (top to bottom):**
1. Header with course title, assessment title, timer badge
2. Progress section — question counter, progress bar with percentage
3. Question card — question number badge, question text, 4 answer option cards in grid
4. Navigation buttons — Previous, Submit, Next
5. Sidebar navigation panel — question grid (numbered cells, color-coded answered/unanswered), legend
6. Results screen (shown after submission) — score ring, pass/fail badge, question breakdown, action buttons

**Key UI components:** Timer badge (with warning pulse animation), progress bar, question card with options grid, option cards with selection states (radio-style), question navigation grid, confirm submit modal, processing modal with spinner, results score ring (SVG animated), pass/fail badge, question breakdown list, mobile bottom drawer for question navigation

**Data elements shown:**
- 5 questions total
- Timer countdown
- Progress percentage (20% per question)
- Final score as percentage with animated ring
- Pass threshold: 70%
- Per-question breakdown: correct/incorrect indicators with correct answer shown
- Actions: View Certificate (if passed), Retake Assessment, Back to Dashboard

**Footer style:** No footer (assessment is full-screen focused interface)

---

## FILE 3: catalog.html

**Page name:** Course Catalog
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar with: logo (links to homepage), nav links (Home, Courses active, About, Contact), search icon, cart icon with count badge, theme toggle, Login + Register buttons. Mobile hamburger menu.
**Main content sections (top to bottom):**
1. Page header hero — gradient background, title "Course Catalog", subtitle, course count badge
2. Filter bar — search input, category dropdown, sort dropdown
3. Filter type buttons — All, E-Learning, Classroom, Blended, Exams
4. Course grid — responsive card grid (JS-rendered from data array)
5. Payment modal — multi-step flow (course summary, billing, payment method, processing, success)
6. Footer — 4-column layout

**Key UI components:** Course cards with gradient color bar, type badge, title, description, price, rating stars, enrolled count, duration, Add to Cart + View Details buttons. Filter buttons with active states. Search input. Category/sort dropdowns. Multi-step payment modal (5 steps). Cart badge counter. Animate-in scroll effects.

**Data elements shown per course card:**
- Course title, description (2-line clamp)
- Type badge (E-Learning, Classroom, Blended, Exam)
- Category (Compliance, Technology, Business, Soft Skills)
- Price (INR with Rupee symbol, or "Free")
- Star rating (out of 5)
- Enrolled count
- Duration
- Add to cart state

**Footer style:** 4-column grid footer: brand column (logo + description), Quick Links, Resources, Contact Us. Bottom bar with copyright, Privacy Policy, Terms links.

---

## FILE 4: certificate.html

**Page name:** Certificate of Completion
**Color mode:** Light + Dark (toggle, localStorage persisted). Certificate card itself always renders on white for print fidelity.
**Navbar style:** Minimal header bar with "Back to Dashboard" link (arrow icon), Airpay Academy logo on right, theme toggle button.
**Main content sections (top to bottom):**
1. Confetti animation overlay (15 animated pieces)
2. Congratulations text — heading with trophy icon, subtitle
3. Certificate card — ornate bordered card with:
   - Corner ornaments (gradient lines)
   - Watermark (conic gradient circle, very low opacity)
   - Certificate header: Airpay Academy logo, "Certificate of Completion" title (Playfair Display serif font), "Airpay Academy" subtitle
   - Gradient divider line
   - Body: "This is to certify that" + recipient name (Playfair Display, large), "has successfully completed" + course name
   - Score section: animated SVG score ring (92%) + metadata items
   - Signatures: two signature blocks with lines
   - Academy seal badge (gradient circle with shield icon)
4. Action buttons row: Download PDF, Share on LinkedIn, Print Certificate, Back to Dashboard

**Key UI components:** Animated confetti, ornate certificate with corner decorations, SVG score ring with gradient animation, dual signature blocks, verification seal badge, gradient CTA buttons, LinkedIn share button, print-optimized CSS

**Data elements shown:**
- Recipient name: Nitin Rajput (populated from localStorage)
- Course: IT and Information Security Awareness
- Score: 92%
- Date: March 15, 2026
- Certificate ID: APAC-2026-IT-00342
- Duration: 8 Hours
- Status: Passed
- Signatories: Dr. Priya Sharma (Programme Director), Amit Verma (Head of Academy)

**Footer style:** No footer (certificate is a standalone page)

---

## FILE 5: classrooms.html

**Page name:** Classrooms (ILT Session Management)
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Left sidebar (identical to admin-dashboard): Logo, nav items (Dashboard, Manage Users, Manage Courses, Reports, Online Exams, Organisation, Classrooms active, Settings), collapsible toggle, user avatar section at bottom. Top bar with hamburger, search, notifications, theme toggle.
**Main content sections (top to bottom):**
1. Page header — title "Classroom Training", subtitle, "Schedule New Session" CTA button
2. Stats bar — 3 stat cards in a row
3. Tab bar — All Sessions (18), Upcoming, Completed, Cancelled
4. Filter bar — search input, location dropdown, trainer dropdown, date picker
5. Session cards list — each card shows session details with progress bar and action buttons
6. Calendar section — weekly view with color-coded time blocks
7. Schedule New Session modal — form with fields for course, trainer, dates, location, capacity

**Key UI components:** Session cards with icon, title, meta info (date, time, location, trainer), enrollment progress bar (with "FULL" state), status badges (Upcoming, Completed, Cancelled), action buttons (View Details, Edit, Cancel, Clone). Calendar grid with color-coded blocks. Modal form with dropdowns and date pickers.

**Data elements shown:**
- Upcoming Sessions: 8 (+3 this week)
- Total Enrolled: 156 (+24 this month)
- Avg Attendance: 92% (+5% vs last quarter)
- Per session: course name, date/time, location (Mumbai/Delhi/Bangalore), trainer name, enrolled/capacity counts, enrollment percentage, status
- Calendar: weekly view with session blocks

**Footer style:** No footer (admin sidebar layout)

---

## FILE 6: course-detail.html

**Page name:** Course Detail
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo (links to homepage), nav links (Home, Courses, About, Contact), search icon, cart icon with badge, theme toggle, Login + Register buttons. Mobile hamburger.
**Main content sections (top to bottom):**
1. Course hero section — breadcrumb, category + type badges, course title, description, meta row (rating stars, enrolled count, duration)
2. Two-column layout: main content + sidebar
3. Main column: Course overview tabs/sections (About, Curriculum, Instructor, Reviews)
4. Sidebar card: price, Add to Cart button, Enroll Now button, course meta list (modules, lessons, duration, level, certificate, language, last updated)
5. Login modal — for unauthenticated users
6. Payment modal — multi-step (summary, billing, card fields, processing)
7. Enrollment success modal — confirmation with animation
8. Footer — 4-column layout

**Key UI components:** Hero section with gradient overlay, badges, star ratings, sidebar sticky card with price and CTA. Curriculum accordion (modules with expandable lessons). Instructor profile card. Review cards with star ratings. Multi-step payment modal. Login/register modal. Success celebration modal.

**Data elements shown per course:**
- Title, full description, category, type
- Rating (out of 5 with star display)
- Enrolled count
- Duration
- Price (INR or Free)
- Modules count, lessons count
- Level (Beginner/Intermediate/Advanced)
- Certificate availability
- Language
- Last updated date
- Instructor: name, title, bio, avatar

**Footer style:** Same 4-column footer as homepage (brand, Quick Links, Resources, Contact Us) + bottom bar with copyright.

---

## FILE 7: course-player.html

**Page name:** Course Player -- POSH Training
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Compact player header with: back arrow to dashboard, vertical divider, course title, right side has progress badge (percentage circle), "Mark Complete" button, theme toggle. No traditional navbar.
**Main content sections (top to bottom):**
1. Player header — back link, course title, progress badge, mark complete button
2. Two-column player layout:
   - Main area: video player placeholder (16:9 aspect ratio with play button overlay), lesson info (title, meta — duration, module name, completion status), lesson description, notes textarea with save button, lesson action buttons (Mark as Complete, Next Lesson)
   - Sidebar: course outline with module groups, each containing lesson items

**Key UI components:** Video player area (placeholder with play button and gradient overlay), module accordion groups (completed/active/locked states), lesson items with status icons (check for completed, play for current, circle for available, lock for locked), progress bar in sidebar header, notes textarea, mobile sidebar toggle button, lesson duration badges

**Data elements shown:**
- Course: POSH Training
- 5 modules with 3 lessons each (15 total lessons)
- Module 1: Understanding POSH Act (Completed) — Introduction to POSH 8:30, Legal Framework 11:15, Key Definitions 6:45
- Module 2: Types of Sexual Harassment (Completed) — Quid Pro Quo 9:20, Hostile Work Environment 10:05, Case Studies 14:30
- Module 3: Roles & Responsibilities (In Progress, Active) — Internal Committee 12:45 (current), Employer Obligations 7:50, Employee Rights 9:15
- Module 4: Reporting & Redressal (Locked) — Filing a Complaint 11:00, Inquiry Process 13:25, Timelines & Penalties 8:40
- Module 5: Prevention & Best Practices (Locked)
- Overall progress: 65%
- Per-lesson descriptions provided

**Footer style:** No footer (player is full-screen focused interface)

---

## FILE 8: edit-profile.html

**Page name:** Edit Profile
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Back link bar — "Back to Profile" arrow link at top, Airpay Academy logo on right, theme toggle. No full navbar or sidebar.
**Main content sections (top to bottom):**
1. Profile header — avatar (with camera upload overlay), name, role badge, member since date
2. Form card 1: Personal Information — First Name, Last Name, Email (disabled/readonly), Phone Number, Date of Birth, Gender dropdown
3. Form card 2: Professional Details — Employee ID (disabled), Department dropdown, Designation, Reporting Manager, Location dropdown, Join Date (disabled)
4. Form card 3: Preferences & Notifications — Language dropdown, Timezone dropdown, Notification toggles (Email, Push, SMS, Course Reminders, Weekly Digest)
5. Form card 4: Change Password — Current Password, New Password (with strength meter), Confirm Password
6. Action buttons: Save Changes (primary), Cancel (outline), Delete Account (danger)
7. Delete Account confirmation modal

**Key UI components:** Avatar with upload overlay, form cards with icons in headers, form input groups with labels, readonly/disabled inputs, dropdown selects, notification toggle switches, password fields with visibility toggle buttons, password strength meter (animated bar), Save/Cancel/Delete buttons, danger confirmation modal

**Data elements shown:**
- Name: Rajesh Kumar
- Email: rajesh.kumar@airpay.co.in (readonly)
- Phone: +91 9876543210
- DOB: 1995-06-15
- Employee ID: AP-2024-0847 (readonly)
- Department options: Technology, Operations, Finance, HR, Business Development
- Designation: Senior Software Engineer
- Manager: Vikram Singh
- Location options: Mumbai HQ, Delhi Office, Bangalore Tech Center
- Join Date: 2024-01-15 (readonly)
- Language options: English, Hindi
- Timezone options: IST (UTC+5:30), UTC

**Footer style:** No footer (form page, back-link navigation only)

---

## FILE 9: employee-dashboard.html

**Page name:** Dashboard (Employee/Learner)
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo (links to index), nav links (Dashboard active, My Courses, Catalog, Profile), right side has notifications bell, avatar with user menu. Theme toggle in nav area.
**Main content sections (top to bottom):**
1. Welcome banner (implied from greeting area)
2. Stats row — 4 stat cards
3. Continue Learning section — 3 horizontal course cards with progress bars
4. Two-column layout:
   - Main column: Upcoming Deadlines (3 items with due dates and action buttons), Recent Achievements (badges/certificates list with view links), Recommended for You (3 recommendation cards)
   - Sidebar column: Learning Activity timeline (monthly activity log)
5. Footer bar

**Key UI components:** Stat cards with colored icons, course progress cards with progress bar and "Continue" button, deadline items with date badges and action buttons, achievement items with icons and view links, recommendation cards with explore buttons, vertical timeline with month markers and activity descriptions

**Data elements shown:**
- Courses Enrolled: 12
- Completed: 8
- In Progress: 3
- Certificates: 4
- Continue Learning courses: POSH Training (65%), AML Compliance (30%), IT Security (85%)
- Deadlines: IT Security Assessment (due April 5), Compliance Certification (due April 10), Annual Review (due April 15)
- Achievements: IT Security Certified (March 2026), POSH Awareness Badge
- Recommendations: Leadership Skills, Data Analytics, Digital Marketing
- Timeline: March 2026 activities (completed courses, earned certificates, enrolled in courses)

**Footer style:** Simple single-line footer: copyright 2026 Airpay Payment Services

---

## FILE 10: exams.html

**Page name:** Exam Management
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo, nav links (Dashboard, Courses, Exams active, Reports), search icon, notifications bell, avatar. Theme toggle.
**Main content sections (top to bottom):**
1. Page header — breadcrumb, title "Exam Management", subtitle, Create Exam button
2. Stats row — 3 stat cards
3. Filter bar — search input, Course filter button, Status filter button, Date filter button, Create Exam CTA
4. Tabs — All Exams (24), Scheduled (6), Completed, Draft
5. Exam card grid — responsive grid of exam cards

**Key UI components:** Exam cards with header (title, course link, status badge), meta row (question count, duration, pass threshold), stats section (attempts, pass rate, avg score), action buttons (Edit, Results, Preview). Status badges (Active green, Scheduled blue, Draft grey, Completed teal). Filter buttons. Tab bar with count badges.

**Data elements shown:**
- Active Exams: 24 (3 new this month)
- Attempts This Month: 1,847 (+12% vs last month)
- Avg Pass Rate: 76.2% (+2.4% improvement)
- Per exam card: title, linked course, status, question count, time limit, pass threshold, total attempts, pass rate, average score
- Exam examples: POSH Assessment (30Q/45min/70%/342 attempts/82% pass/78.4 avg), IT Security Quiz (25Q/30min/80%/518 attempts/71% pass/74.1 avg), AML Certification (50Q/60min/75%/286 attempts/68% pass)

**Footer style:** No visible footer in extracted content

---

## FILE 11: homepage.html

**Page name:** Airpay Academy -- A Comprehensive & Hybrid Learning Platform
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo (links to homepage), nav links (Home, Courses, About, Contact), search icon, cart icon with badge, theme toggle, Login + Register buttons. Mobile hamburger.
**Main content sections (top to bottom):**
1. Hero section — gradient background, kicker badge ("Airpay Academy"), headline, subtext, two CTA buttons (Explore Courses, Learn More), stats strip (6+ Courses, 500+ Learners, 50+ Organizations)
2. Trust bar — 4 trust items (RBI Compliant, DPDP 2023, ISO Certified, 24/7 Support)
3. Featured Courses section — heading, 3-column course card grid (JS-rendered)
4. Learning Pillars section — 4 pillar cards: Employability Skills, Business Acumen, Financial Sector Expertise, Regulatory Compliance. Each with icon, description, skill tags.
5. Testimonials section — 3 testimonial cards with star ratings, quote text, author avatar/info
6. CTA section — gradient background, heading, CTA button ("Get Started For Free")
7. Course catalog extended section (if more courses)
8. Footer — 4-column layout

**Key UI components:** Hero with gradient overlay and animated entrance, stats strip, trust bar with icons and labels, course cards (gradient bar, type badge, price, rating, actions), pillar cards with colored icons and skill tag pills, testimonial cards with star ratings and avatars, full-width CTA banner, scroll-triggered animations

**Data elements shown:**
- Stats: 6+ Courses, 500+ Learners, 50+ Organizations
- Trust marks: RBI Compliant, DPDP 2023, ISO Certified, 24/7 Support
- Course cards with titles, types, prices, ratings, enrolled counts
- 4 Learning Pillars with associated skill tags
- 3 Testimonials with names, roles, star ratings (all 5-star)

**Footer style:** 4-column footer: brand (logo, description), Quick Links, Resources, Contact Us. Bottom bar with copyright, social links, Privacy Policy, Terms links.

---

## FILE 12: hub.html

**Page name:** Airpay Academy -- Page Hub
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Minimal top bar with theme toggle and Airpay Academy logo. No navigation links (this is the index/hub page).
**Main content sections (top to bottom):**
1. Hub hero — animated gradient background, Airpay Academy logo, "Page Hub" title, subtitle describing it as a navigation index
2. Page groups — 3 grouped sections:
   - Public Surface (7 pages): Homepage, Course Catalog, Course Detail, Login, Registration, Privacy Policy, Terms of Use
   - Learner Journey (6 pages): Dashboard, Course Player, Assessment, Certificate, Profile, Edit Profile
   - Admin Panel (7 pages): Admin Dashboard, Manage Users, Manage Courses, Reports, Online Exams, Organisation, Classrooms
3. Footer bar

**Key UI components:** Hero section with gradient animation, page group headers with emoji and icon, page card grid (icon, title, description, arrow indicator), cards with hover effects (translate + shadow), fade-in animations with delays, color-coded icons per page

**Data elements shown:**
- 20 pages organized in 3 groups (plus hub and index as meta pages)
- Each page card: title, short description, icon, color

**Footer style:** Simple footer bar at bottom

---

## FILE 13: index.html

**Page name:** Airpay Academy -- Stakeholder Preview
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** No traditional navbar. This is a standalone presentation/landing page for stakeholders.
**Main content sections (top to bottom):**
1. Hero section — animated gradient background, Airpay Academy logo, "Stakeholder Preview" eyebrow badge, large title, tagline, description paragraph about the build
2. Stats section — build statistics bar (7 tranches, 10,000+ lines, 100+ file touches, 3 shells, 20 pages)
3. Links to hub.html for exploring all pages

**Key UI components:** Animated gradient hero, fade-in animations with delays, stats bar, logo display

**Data elements shown:**
- Build stats: 7 tranches, 10,000+ lines of code, 100+ file touches, 3 shells, 20 pages
- Links to the hub for navigation

**Footer style:** No footer (standalone landing page)

---

## FILE 14: login.html

**Page name:** Login
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** No navbar. Login is a standalone full-page layout with split design.
**Main content sections (top to bottom):**
1. Login wrapper — split layout:
   - Left panel: branded gradient panel with Airpay Academy logo, tagline, stats strip (500+ Active Learners, 50+ Courses, 98% Completion Rate)
   - Right panel: login card with form
2. Login card: Airpay Academy logo, "Welcome back" heading, subtitle, alert area (error messages), form with Email + Password fields, "Remember me" checkbox + "Forgot password?" link, Login button (with loading spinner state), "Don't have an account? Register" link
3. Forgot Password modal — email input, Send Reset Link button, success confirmation state

**Key UI components:** Split-screen layout (brand panel + form panel), form inputs with icon wraps, field validation with error messages, password visibility toggle, remember me checkbox, loading spinner on submit button, forgot password modal with success state, responsive (stacks on mobile)

**Data elements shown:**
- Platform stats in brand panel: 500+ Active Learners, 50+ Courses, 98% Completion Rate
- Form fields: email, password
- Login validation states

**Footer style:** No footer (standalone login page)

---

## FILE 15: manage-courses.html

**Page name:** Manage Courses
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Left sidebar (identical admin sidebar pattern): Logo, nav items (Dashboard, Manage Users, Manage Courses active, Reports, Online Exams, Organisation, Classrooms, Settings), collapsible toggle, user section. Top bar with hamburger, search, notifications, theme toggle.
**Main content sections (top to bottom):**
1. Stats bar — 4 stat cards in a row
2. Filter bar — search input, category dropdown, status dropdown, "Add Course" button
3. Data table — course management table with sortable columns
4. Table footer — showing X of Y records, pagination controls
5. Add Course modal — form with fields for name, category, type, price, status, description, thumbnail

**Key UI components:** Stat cards, search + filter dropdowns, full data table with headers, status pills (Active green, Draft yellow, Archived grey), action buttons per row (Edit, View, Delete), pagination controls, Add Course modal form with dropdowns

**Data elements shown (table columns):**
- Course name
- Category
- Type (E-Learning, Classroom, Blended, Exam)
- Price (INR)
- Status (Active, Draft, Archived)
- Enrolled count
- Actions (Edit, View, Delete)
- Stats: Total Courses 54, Active 48, Draft 4, Archived 2

**Footer style:** No footer (admin sidebar layout)

---

## FILE 16: manage-users.html

**Page name:** User Management
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Standalone page header with "Back to Hub" link, breadcrumb navigation (Admin > Users). No sidebar. Top-level admin page with back navigation.
**Main content sections (top to bottom):**
1. Page header — back link, breadcrumb, title "User Management", subtitle
2. Filter/toolbar — search input, department dropdown, role dropdown, status dropdown, "Add User" button
3. Bulk actions bar — Export CSV, Bulk Assign Course, Send Notification buttons
4. Data table — user management table with checkbox selection
5. Table footer — record count and pagination

**Key UI components:** User table with avatar initials (gradient backgrounds), user info (name, email, employee ID), status pills (Active, Inactive, Pending), role badges (Learner, Admin, Manager), checkbox row selection, action dropdown per row (View, Edit, Courses, Suspend, Delete), bulk action buttons, pagination

**Data elements shown (table columns):**
- Checkbox select
- User (avatar, full name, email, employee ID)
- Department (Operations, Technology, Finance, HR, Business Development)
- Role (Learner, Admin, Manager) with colored badges
- Status (Active, Inactive, Pending)
- Courses enrolled count
- Last Login date
- Actions menu
- Sample users: Priya Singh, Amit Patel, Nitin Rajput, Rajesh Kumar, Anita Joshi, Vikram Sharma, Deepa Menon, Sanjay Gupta

**Footer style:** No visible footer

---

## FILE 17: organisation.html

**Page name:** Organisation Structure
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo, nav links (Dashboard, Courses, Organisation active, Reports), search icon, notifications bell, avatar. Theme toggle.
**Main content sections (top to bottom):**
1. Page header — breadcrumb, title "Organisation Structure", subtitle, KPI stat cards in header area
2. Organisation layout — two-column:
   - Left: Department Tree panel — expandable tree view with department hierarchy
   - Right: Department detail panel — selected department info, training compliance stats, team member table
3. "Assign Course to Department" button
4. Location Overview section — 3 location cards in grid

**Key UI components:** Collapsible tree view (checkbox-based toggle) with folder icons, department nodes with employee counts, sub-department items with team-specific icons, department detail panel, member table with avatars, status badges (Active, On Leave), compliance stat with progress ring, location cards with stat items, "Assign Course" CTA

**Data elements shown:**
- Department tree: Technology (23: Engineering 14, QA 5, DevOps 4), Operations (31: Support 12, Logistics 10, Process 9), Business Development (18: Partnerships 8, Marketing 6, Sales 4), Finance & Compliance (14: Accounts 6, Compliance 5, Legal 3), Human Resources (8: Recruitment 3, L&D 3, Welfare 2)
- Team Members table: name, role, team, status (Active/On Leave), completion percentage
- Locations: Mumbai HQ (52 employees, 4 departments, 78% completion), Delhi Office (24 employees, 3 departments, 71% completion), Bangalore Tech Center (18 employees, 2 departments, 84% completion)

**Footer style:** Standard footer present at bottom

---

## FILE 18: privacy-policy.html

**Page name:** Privacy Policy
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo on left, nav links (Home, Courses, About, Contact), right side theme toggle, mobile menu toggle. Simpler nav compared to homepage.
**Main content sections (top to bottom):**
1. Page hero — gradient background, "Privacy Policy" title, last updated date
2. Content wrapper with sidebar:
   - Sidebar: table of contents (sticky, scrollspy-style)
   - Main content: 7 policy sections
3. Policy sections:
   - Section 1: Introduction
   - Section 2: Data We Collect
   - Section 3: How We Use Your Data
   - Section 4: Legal Basis for Processing
   - Section 5: Your Rights
   - Section 6: Data Protection Measures
   - Section 7: Policy Changes
4. Footer

**Key UI components:** Hero banner, sticky table of contents sidebar, section cards with numbered headers, section icons (FontAwesome), expand/collapse sections, smooth scroll anchors, section number badges

**Data elements shown:**
- 7 policy sections with full legal text
- Section icons and numbering
- Last updated date
- Contact information for data inquiries

**Footer style:** 4-column footer: brand, Quick Links, Resources, Contact. Bottom bar with copyright.

---

## FILE 19: profile.html

**Page name:** Learner Profile
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Back bar at top with "Back to Dashboard" link and Airpay Academy logo. Theme toggle. No full navbar.
**Main content sections (top to bottom):**
1. Profile header — avatar, name (Rajesh Kumar), role badge (Employee), detail row (email, employee ID, member since), "Edit Profile" button
2. Stats row — 3 stat cards
3. Main content area — collapsible section cards:
   - Learning Activity — chart/visualization area
   - Recent Achievements — 4 achievement cards in grid (gold/blue/green/purple colored icons)
   - Enrolled Courses — course list with progress bars and status pills
   - Skills & Competencies — skill badges/tags
   - Certificates — certificate cards with download actions
   - Account Settings — preferences summary

**Key UI components:** Profile header with avatar and details row, stat cards with colored icons, collapsible section cards with toggle arrows, achievement cards with colored icon badges, course list items with status pills (Completed, In Progress, Not Started), skill tag badges, certificate cards, toggle sections (open/closed states)

**Data elements shown:**
- Name: Rajesh Kumar
- Email: rajesh.kumar@airpay.co.in
- Employee ID: AP-2024-1847
- Member since: January 2024
- Courses Completed: 12
- Certifications: 4
- Learning Hours: 847
- Achievements: Top Performer, Quick Learner, Streak Master, Course Champion
- Enrolled courses with completion status
- Skills/competencies tags
- Certificate details with download links

**Footer style:** No visible footer

---

## FILE 20: reports.html

**Page name:** Analytics & Reports
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Page header with breadcrumb navigation, back link. Standalone admin page (not sidebar-based). Title area with export/download actions.
**Main content sections (top to bottom):**
1. Page header — breadcrumb, title "Analytics & Reports", subtitle, date range selector, export button
2. KPI grid — 4 KPI cards
3. Charts row — 2 chart cards (Enrolment Trends line chart, Course Distribution donut chart)
4. Top Performing Courses — data table with performance metrics
5. Department Breakdown — horizontal bar chart showing completion rates per department
6. Download & Schedule section — report format dropdown, Download Report button, Schedule Report button

**Key UI components:** KPI cards with trend indicators (up/down arrows with color), CSS-drawn line chart, donut chart with center label and legend, data table with columns, horizontal bar charts with percentage fills, download controls with format selector, schedule report button

**Data elements shown:**
- Total Enrolments: 2,847 (+12%)
- Course Completions: 1,234 (+8%)
- Avg Score: 78.5% (-2%)
- Active Users: 456 (+23%)
- Enrolment trends chart (monthly data)
- Course distribution donut: total 2,847
- Top courses table: course name, enrolled, completed, completion rate, avg score
- Department breakdown bars: Operations 88%, Sales 76%, Technology 71%, Finance 64%, Human Resources 58%
- Download formats: CSV, PDF, Excel
- Schedule report option

**Footer style:** No visible footer

---

## FILE 21: signup.html

**Page name:** Sign Up
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** No navbar. Standalone full-page layout similar to login page.
**Main content sections (top to bottom):**
1. Signup wrapper — centered card layout:
   - Signup card: Airpay Academy logo, "Create your account" heading, subtitle
   - Form with field groups:
     - Row: First Name + Last Name
     - Email
     - Phone (with country code selector)
     - Password (with strength indicator)
     - Confirm Password
     - Organisation dropdown
     - Department dropdown
   - Register button (with loading spinner)
   - "Already have an account? Log in" link
2. Success state — confirmation message with "Continue to Login" button

**Key UI components:** Form inputs with icon wraps, field validation with error messages, password strength meter, phone country code dropdown, organisation/department dropdowns, loading spinner on submit, success state transition, responsive layout

**Data elements shown:**
- Form fields: First Name, Last Name, Email, Phone (with +91 default), Password, Confirm Password, Organisation selector, Department selector
- Validation rules per field
- Success confirmation

**Footer style:** No footer (standalone signup page)

---

## FILE 22: terms-of-use.html

**Page name:** Terms of Use
**Color mode:** Light + Dark (toggle, localStorage persisted)
**Navbar style:** Top horizontal navbar: logo on left, nav links (Home, Courses, About, Contact), right side theme toggle, mobile menu toggle. Same pattern as privacy-policy.html.
**Main content sections (top to bottom):**
1. Page hero — gradient background, "Terms of Use" title, effective date
2. Content wrapper with sidebar:
   - Sidebar: table of contents (sticky)
   - Main content: 7 terms sections
3. Terms sections:
   - Section 1: Welcome (acceptance of terms)
   - Section 2: Use of Website
   - Section 3: Limitation of Liability
   - Section 4: Intellectual Property
   - Section 5: User Responsibilities
   - Section 6: Data Privacy
   - Section 7: Termination
4. Footer

**Key UI components:** Same structure as privacy-policy.html — hero banner, sticky TOC sidebar, numbered section cards with icons, smooth scroll navigation

**Data elements shown:**
- 7 terms sections with legal text
- Section icons and numbering
- Effective date
- Contact information

**Footer style:** 4-column footer: brand, Quick Links, Resources, Contact. Bottom bar with copyright.

---

## Cross-Cutting Design Patterns

### Shared Across All 22 Pages:
- **Font:** Montserrat (400-800 weights) loaded from Google Fonts
- **Icon library:** Font Awesome 4.7.0
- **Theme system:** data-theme="dark" attribute on html element, persisted in localStorage as 'airpay-theme', auto-detects system preference
- **CSS Variables:** Consistent token set (--primary: #0066A7, --accent: #0f7a73, --bg-body: #F2F4FB, etc.) with dark mode overrides
- **Border radius:** 12px (sm), 16px (md), 20px (lg), 999px (pill)
- **Shadows:** 3-tier system (sm, md, hover)
- **Animations:** fadeInUp on scroll, fade-in with delay classes

### Navigation Patterns (3 distinct patterns):
1. **Public navbar** (homepage, catalog, course-detail, exams, organisation, homepage): Top horizontal bar with logo, nav links, search, cart, auth buttons
2. **Admin sidebar** (admin-dashboard, manage-courses, classrooms): Collapsible left sidebar with icon-label nav items, top utility bar
3. **Minimal header** (certificate, assessment, course-player, edit-profile, profile, login, signup): Back-link + logo + theme toggle only

### Footer Patterns (3 types):
1. **Full 4-column footer** (homepage, catalog, course-detail, privacy-policy, terms-of-use): Brand, Quick Links, Resources, Contact + bottom copyright bar
2. **Simple footer bar** (employee-dashboard, hub): Single-line copyright
3. **No footer** (admin pages, assessment, course-player, certificate, login, signup, edit-profile, profile): Content fills the page

### Page Layout Types:
1. **Sidebar + main area** (admin-dashboard, manage-courses, classrooms): Fixed sidebar, scrollable main
2. **Full-width stacked sections** (homepage, catalog, hub, index): Hero + content sections + footer
3. **Two-column content** (course-detail, organisation, reports, privacy-policy, terms-of-use, profile): Main + sidebar or split layout
4. **Focused single-task** (login, signup, assessment, course-player, certificate, edit-profile): No navigation chrome, task-focused
5. **Standalone page header + content** (manage-users, exams, reports): Page header bar + data area
