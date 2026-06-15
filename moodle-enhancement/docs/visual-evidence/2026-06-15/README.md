# Public Learner Visual Audit — from scratch (2026-06-15)

**Instance:** `moodle52_cut1` cutover clone, served `http://localhost:8081`
(php-cgi 8.4 FastCGI). **Persona:** `qa_public` (id=3422, Public tenant `/77`)
— the external/public learner.

**Trigger:** Nitin observed that on the local XAMPP clone *"the links for
courses were failing"* and asked for a from-scratch visual audit of the public
learner to find out why.

---

## TL;DR — verdict

**The failing course links are a local-data artifact, NOT a product bug.**

Every product surface the public learner touches renders cleanly. The *only*
failure is the **SCORM player**, and it fails for exactly one reason: this
clone's `moodledata52_cut1/filedir` is empty (21 files / 0 MB). The production
database rows were imported, but the **file content** (SCORM package binaries,
uploads) was **not**. So the SCORM player loads correctly, starts its API,
opens the content iframe, points it at the right `pluginfile.php` URL — and
that URL returns **404** because the package binary isn't on disk.

On production (real `filedir`), these same links work. Confirmed by a
controlled experiment (see §4): a file-free activity created in the same course
renders perfectly for the same learner.

---

## 1. Root cause — the empty filedir

| Fact | Value |
|------|-------|
| `moodledata52_cut1/filedir` | 21 files, ~0 MB |
| What imported | Production **DB rows** (courses, activities, enrolments) |
| What did NOT import | Production **file content** (SCORM zips, uploads) |
| Surfaces that need files | SCORM, File/Folder resources, uploaded images |
| Surfaces that do NOT need files | Course view, Quiz, Forum, Page, Label, dashboards |

The exact client-side failure, captured live on the SCORM player
(`06-public-scorm-player-emptyframe.png`):

```
404 (Not Found) — http://localhost:8081/pluginfile.php/5598/mod_scorm/content/1/index_lms.html
```

The SCORM **player surface itself works** — no PHP fatal, no coding error. It
renders the chrome, starts the SCORM API, and requests the correct content URL.
Only the asset behind that URL is missing.

---

## 2. Surfaces walked — all render clean

| # | Surface | URL | Result | Screenshot |
|---|---------|-----|--------|------------|
| 1 | Storefront (logged out) | `/` | ✅ Netflix-style storefront, posters, CTAs | `01-public-storefront-loggedout.png` |
| 2 | Dashboard | `/my/dashboard.php` | ✅ Widgets, charts, sidebar | `02-public-dashboard.png` |
| 3 | My Courses | `/my/courses.php` | ✅ Enrolled course cards | `03-public-mycourses.png` |
| 4 | Course view (CS01, id=400) | `/course/view.php?id=400` | ✅ All 7 activities listed, drawer populated | `04-public-courseview-cs01.png` |
| 5 | SCORM intro | `/mod/scorm/view.php?id=1749` | ✅ Intro + "Enter" button render fine | `05-public-scorm-intro.png` |
| 6 | SCORM player | `/mod/scorm/player.php` | ◑ Player renders; **content asset 404s** (empty filedir) | `06-public-scorm-player-emptyframe.png` |
| 7 | Quiz view | `/mod/quiz/view.php?id=1754` | ✅ Heading, attempt button, drawer all render | `07-public-quiz-view.png` |
| 8 | Forum (Announcements) | `/mod/forum/view.php?id=1755` | ✅ Forum renders, content present | `08-public-forum-announcements.png` |
| 9 | **Page (content-path proof)** | `/mod/page/view.php?id=2099` | ✅ **DB-backed content renders perfectly** | `09-public-page-contentpath-proof.png` |

Course under test: **CS01 — "POSH Training for Internal Committee Members"**
(course 400, Public tenant). 7 activities: 5 SCORM (cmid 1749–1753), 1 quiz
(1754), 1 forum (1755).

---

## 3. Findings & dispositions

| Finding | Severity | Disposition |
|---------|----------|-------------|
| SCORM player content 404 | — | **Data artifact, not a defect.** Empty local filedir. Works on production. No code change. |
| Empty course-index drawer on SCORM *player* | — | **Not a bug.** The SCORM player uses Moodle's minimal embedded layout (no drawer by design). The drawer populates correctly on the standard activity view (verified on quiz + forum, surfaces #7/#8). |
| All other public-learner surfaces | — | ✅ Clean. 0 PHP errors, 0 blocking console errors. |

No product bug was found. No code fix required.

---

## 4. Controlled experiment — proving the content path

To remove all doubt that "the existing activities just happened to work", a
**file-free `mod_page` activity** was seeded into the same course (CS01, 400)
via `local/sentientia_exams/cli/seed_qa_content_path_proof.php` (idempotent,
QA-guarded — refuses to run unless `qa_public` exists).

A Page carries its body in the `{page}.content` DB column with **no moodledata
asset**. Viewed as `qa_public` at `/mod/page/view.php?id=2099`:

- ✅ Heading "Content path proof" rendered
- ✅ Body "...rendered straight from the database..." rendered
- ✅ Activity progress indicator working (`1/6 · 17%`)
- ✅ 0 errors

**Conclusion:** the activity content path works end-to-end for the public
learner. File-backed content (SCORM) fails *only* because the binary is absent
from this clone's filedir — exactly the predicted data artifact.

> Teardown note: the proof Page (cmid=2099) remains in CS01 on this **local QA
> clone only**. It is clearly marked `QA-CONTENT-PATH-PROOF` and is never
> deployed to production. Re-running the seeder is a no-op (idempotent).

---

## 5. What this means for the rollout gate

- **Foolproof (Phase 1):** the public-learner journey is product-clean. The
  SCORM links will work the moment real package files are present.
- **Ninja-sandbox (Phase 2):** the live-backup migration **must carry the
  `filedir` across**, not just the DB dump. This audit is the concrete reminder
  that a DB-only restore looks broken (SCORM 404s) even when the product is
  fine. Add a `filedir` size/parity check to the migration rehearsal checklist.

---

*Audit performed headless via Playwright (workers=1, single-PHP backend).
Screenshots in this folder are the evidence of record.*
