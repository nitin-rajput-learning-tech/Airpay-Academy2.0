# Airpay Academy — User Access Matrix
**Generated:** 2026-04-04 | **Source:** BizLMS capability scan + prototype analysis

---

## User Types

| ID | Role | Moodle Role | Example User | Tenant |
|----|------|-------------|-------------|--------|
| SA | Super Admin | siteadmin | Super Admin | All |
| LA | L&D Admin | manager + local/courses:manage | Amit Patel (test_admin) | Airpay (1) |
| MG | Manager/HRBP | manager (system level) | Vikram Sharma (test_manager) | Airpay (1) |
| EE | Employee (Learner) | student | Priya Singh (test_employee) | Airpay (1) |
| EX | External Learner | student | Deepa Menon (test_external) | Public (77) |
| TR | Trainer | editingteacher | (not created yet) | Airpay (1) |
| GU | Guest | guest | Anonymous | N/A |

---

## Dashboard Access

| Feature | SA | LA | MG | EE | EX | TR | GU |
|---------|----|----|----|----|----|----|-----|
| **KPI Tiles** (users, courses, enrolments, completion) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Quick Navigation** (admin shortcuts with stats) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Enrolment Trend Chart** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Course Distribution Chart** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **System Health** (cron, disk, PHP, Moodle version) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **User Analytics** (logins, new users, inactive) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Team KPIs** (team members, completion rate) | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Team Compliance Table** | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Welcome Banner** | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Stat Cards** (enrolled, in-progress, completed, certs) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Continue Learning** (course cards) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Upcoming Deadlines** | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Recent Achievements** (certificates) | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Activity Timeline** | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Recommended Courses** | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |

---

## Navbar Access

| Feature | SA | LA | MG | EE | EX | TR | GU |
|---------|----|----|----|----|----|----|-----|
| **Dashboard pill** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **My Courses pill** | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Catalog pill** | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Profile pill** | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Search bar** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Quick Access menu** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Notifications** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Messaging** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Cart** | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| **Dark mode toggle** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Edit mode** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **User menu (logout, profile)** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |

---

## Quick Access Menu Items

| Item | SA | LA | MG | EE | EX | TR |
|------|----|----|----|----|----|----|
| Dashboard | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Company Structure | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Users | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Groups | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| View Transactions | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Categories | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Courses | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Forum | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Online Exams | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Classrooms | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| Manage Learning Paths | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Programs | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Feedbacks | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Skills | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Notifications | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage Requests | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Analytics | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Trainer Dashboard | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Administration | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## Footer Access

| Feature | SA | LA | MG | EE | EX | TR | GU |
|---------|----|----|----|----|----|----|-----|
| **Full 4-column footer** (Brand, Learn, Support) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| **Minimal footer** (copyright only) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Made in India badge** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Page Access (BizLMS capabilities)

| Page | SA | LA | MG | EE | EX | TR |
|------|----|----|----|----|----|----|
| `/my/` (Dashboard) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/local/users/index.php` (Manage Users) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/local/courses/courses.php` (Manage Courses) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/local/search/allcourses.php` (Course Catalog) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/local/search/coursedetails.php` (Course Detail) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/local/users/profile.php` (My Profile) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/local/classroom/index.php` (Classrooms) | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| `/local/onlineexams/index.php` (Online Exams) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/local/program/index.php` (Programs) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/local/learningplan/index.php` (Learning Paths) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/blocks/learnerscript/` (Reports) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `/admin/` (Site Admin) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `/local/airpay_pages/` (Static pages) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Current Implementation Status

| Feature | SA | LA | MG | EE | EX | Status |
|---------|----|----|----|----|----|----|
| Dashboard role detection | ✅ | ⚠️ sees same as SA | ⚠️ was seeing SA, now fixed | ✅ | ✅ | LA needs separate branch |
| Navbar pills | ✅ Dashboard only | ⚠️ Dashboard only | ✅ should show 4 pills | ✅ 4 pills | ✅ 4 pills | MG needs pills back |
| Footer | ✅ minimal | ✅ minimal | ✅ minimal | ✅ full | ✅ full | Working |
| Quick Access | ✅ all items | ✅ all items | ⚠️ showing all items | ❌ hidden | ❌ hidden | MG needs filtered items |

---

## Next Steps

1. **Separate L&D Admin from Siteadmin**: L&D admin should see admin dashboard but NOT system health (that's siteadmin only). Currently both see the same view.
2. **Manager navbar pills**: Manager should see all 4 pills (they browse courses too + have a profile), not just Dashboard.
3. **Manager Quick Access**: Should be filtered — only Dashboard, Classrooms, Notifications, Manage Requests, Analytics. NOT Manage Users/Courses/Forum etc.
4. **Test user: Trainer**: Not created yet. Needs editingteacher role + trainer dashboard.
5. **External learner**: Should NOT see Quick Access at all. Should see Cart. Currently seeing same as employee.
