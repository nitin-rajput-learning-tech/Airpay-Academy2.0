# External trainer: interim runbook (until session 5)

**For:** tenant L&D admins and the platform (site) admin. **Date:** 19 Nov 2026 (post-demo plan items 2.4
and C6). **Applies to:** Sentientia on local and UAT. Live airpay.academy is still BizLMS; nothing here is
done there. **Status:** interim. Session 5 of the post-demo plan (C6, decisions D14 and D15, flag
`sentientia.users.external_trainer.enabled`) builds the real "external trainer" user type: own classrooms
only, names-only rosters, email invite, expiry at the last session + 30 days, audit log. Until then an
external trainer is an ordinary account in a tenant, with the gaps listed in section 5.

Checked on the local site on 19 Nov 2026 (role definitions, capability checks and the classroom code).
UAT role definitions were not read; check them before the first use there.

## 1. First ask: do they need an account at all?

Usually not. A trainer who only delivers the session does not need to log in. The classroom's internal
trainer or an L&D admin marks attendance (classroom > **Sessions** > attendance page). Create an account
only when the external trainer must mark attendance themselves.

## 2. Create the account (tenant L&D admin)

**Manage Users > Add User**, then:

| Field | Value |
|---|---|
| Username | `ext-` + first name and last name (for example `ext-firstname.lastname`), so externals stand out in lists |
| Email, First name, Last name | The trainer's own work email and name |
| Authentication method | **Manual (admin sets password)**. Not SSO or LDAP: they are not in Airpay's directory |
| Employee ID | Empty, or `EXT-` + a running number. Never an Airpay employee number: the HRMS import matches on it |
| Designation | `External trainer` |
| Phone, Location, Date of birth, Date of joining, Reporting manager | Leave empty (collect only what is needed) |
| Organisation | The tenant whose classrooms they will teach. Required: the classroom's trainer field refuses an account from another tenant. A trainer for two tenants needs two accounts |
| Password | A long random one. Ticked, **Email welcome message with login details** sends it in the email and forces a change at first login. If outgoing mail does not work on that site, untick it and give the password by phone. Unticking it also drops the forced change: see the note below the table |

**Welcome email unticked: force the change yourself.** The account form forces a password change at first
login only together with the welcome email. Without it nothing makes the trainer replace the password you chose
(and gave by phone). So, after **Add User**:
- a site admin opens `/user/editadvanced.php?id=<id>` (the id is in the account's profile address), ticks
  **Force password change** and saves. This is a site admin step: that core page has no tenant check, and the
  platform links only site admins to it; or
- with no site admin at hand, stay on the phone while the trainer logs in and changes it on their profile page
  (**Change password**, `/login/change_password.php`), and check that they did.

Tell the trainer what the account is for and that it will be suspended after the training.

## 3. Give them classroom access (site admin)

The Classrooms pages need a role at system level. Tenant L&D admins cannot give one: on the local site their
role may assign only manager, course creator, teacher, non-editing teacher and administrator. A site admin does
this step.

**A. Recommended: a minimal "External trainer (interim)" role** (create once per site; on UAT only on Nitin's go):
1. **Site administration > Users > Permissions > Define roles > Add a new role**, role archetype **No role**,
   **Continue**.
2. Short name `externaltrainerinterim`, full name `External trainer (interim)`; **Context types where this role
   may be assigned**: **System** only.
3. Allow exactly two capabilities: `local/sentientia_classroom:view` and `local/sentientia_classroom:attendance`.
   Nothing else. **Create this role**.
4. **Site administration > Users > Permissions > Assign system roles > External trainer (interim)**, add the
   account.

**B. Today's practice: the Trainer role at system level. Do not use it for external people.** It carries 217
capabilities (section 4). On the local site Trainer may be assigned in categories and courses only, so it is not
even offered under **Assign system roles**; widening the Trainer role definition to make it appear changes it for
every internal trainer.

## 4. Attach them and send the link (tenant L&D admin)

1. Classrooms > the classroom > **Edit Classroom** > **Primary trainer**, or on one session **Trainer (overrides
   classroom default)**. They can take attendance only for sessions where they are the session's trainer, the
   classroom's primary trainer or a listed co-trainer.
2. Send them the link to the Classrooms page (`/local/sentientia_classroom/index.php`) or to the session's
   attendance page. Their menu is the learner menu, which has no Classrooms entry.

**What they can see:**

| | A. Minimal interim role | B. Trainer role at system level |
|---|---|---|
| Classrooms list and each classroom's sessions | Every classroom in their tenant | Every classroom in their tenant, with edit and delete icons (saving and deleting are refused) |
| Rosters | Every roster in the tenant: name, email, employee ID, designation, enrolment date | Same |
| Attendance | Marks it for their own sessions only | Same |
| Bulk enrol a target audience into a classroom | No | Yes, in any classroom of the tenant, when the flag `sentientia.classroom.bulk_enrol_audience` is on |
| Live sessions (polls, quizzes) | No | Create and run, when `live.enabled` is on |
| AI quiz, authoring, skills extraction | No | Yes, where those flags are on |
| User profiles | Their own; others only where Moodle's normal course rules allow | **Any user in any tenant**, hidden fields included (email) |
| Site and course logs (`/report/log/index.php`) | No | **Yes, for every tenant** |
| Grades | Their own | All grades in any course they can enter |
| Their own learning | Catalogue of their tenant, self-enrolment where a course allows it, My courses, Certificates, Profile | Same |

## 5. Risks that remain, and what to do about them

- **Tenant-wide classroom and roster view**, with emails and employee IDs, under both options. Use an account
  only when needed (section 1); session 5 limits this to their own classrooms and names only (D14, D15).
- **No expiry.** Moodle has no end date for an account. Put a reminder in the L&D calendar for the last session
  + 30 days and suspend then (section 6).
- **They count as a tenant user.** They appear in user lists and headcounts, and a rule aimed at the whole tenant
  or their organisation unit (a mandatory course, a learning-path audience, a classroom audience enrolment) can
  enrol them and email them. Check new assignments in their first week.
- **Password by email** when the welcome message is ticked; the forced change at first login limits this.
  **Unticked**, the password goes by phone and nothing forces a change: the admin-chosen password stays in use
  unless a site admin ticks **Force password change** or the trainer changes it while you check (section 2).
- **No "external" marker** beyond the `ext-` username and the designation, and no audit trail of who created
  the account and why. Keep the register in section 7.
- **One person, one account.** Never share an account between trainers or with an employee.
- **Trainer role at system level (option B):** cross-tenant profiles and logs, live sessions and AI tools. If an
  external person already holds it, move them to option A or suspend them.

## 6. Remove access (last session + 30 days, or when the engagement ends)

1. **Manage Users**: find the `ext-` account, **Suspend User**. They can no longer log in, and their open
   sessions end at once.
2. Site admin: **Assign system roles** > the role > remove the account.
3. L&D admin: remove them as trainer from classrooms and sessions that have not happened yet. Leave past
   sessions as they are, so attendance history keeps its trainer.
4. Do not delete the account while its attendance history matters. If the person asks for their data to be
   erased, use the platform's data privacy request flow, not **Delete User**.

## 7. Register (kept by L&D, outside this repository)

One row per account: username, tenant, classrooms, who asked for it, created on, planned suspension date,
suspended on. Never put names or emails of real people into this repository.

## Verify on the local site (test accounts only)

1. As a site admin, create the role in section 3A and give it to a test account in the Airpay tenant; make that
   account the primary trainer of a test classroom with one session.
2. Log in as the test account and open `/local/sentientia_classroom/index.php`. **Expected:** the tenant's
   classrooms, no edit or delete icons; the session's attendance page opens and saves; a session of another
   classroom (with another trainer) says "You can open and take attendance only for sessions you are the
   assigned trainer of."
3. Still as the test account, open `/report/log/index.php`. **Expected:** refused (with option B it opens).
4. Suspend the test account. **Expected:** the next page load sends it to the login page, and login fails.

Source: `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` (section C, C6; decisions D14, D15).
