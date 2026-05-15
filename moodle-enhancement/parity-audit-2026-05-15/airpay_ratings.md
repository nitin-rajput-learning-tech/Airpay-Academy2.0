# airpay_ratings vs BizLMS local_ratings — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (Opus 4.7, 1M)
**Verdict:** **SHELL ONLY — 92% feature loss.** BizLMS shipped a full rating + comment + like-unlike + reviews engine. Airpay shipped a 5-star averager. Eight P0/P1 gaps; this is a sketch, not a feature. **The plugin is essentially non-functional from a learner's perspective — there is no write endpoint to submit a rating.**

---

## Source paths + size

- **BizLMS**: `C:\xampp\htdocs\moodle5\bizlms_disabled\ratings\` — **19 PHP files, 1,932 LOC**
  - Entry points: `index.php` (82), `update.php` (73 — **the rating-write endpoint**), `comment.php` (36), `delete.php` (15), `reviews.php` (66 — full reviews page), `allrating.php` (0 — empty placeholder), `allcomment.php` (25)
  - Library: `lib.php` (423) — `display_rating()`, `display_like_unlike()`, `ask_for_rating()`, `can_participate()`, `get_rating()`, `display_comment()`, `get_existing_rates()`, `get_existing_comments()` (commented)
  - Class library: `classes/lib/ratinglib.php` (92), `classes/external.php` (523), `classes/output/renderer.php` (172)
  - DB: 4 tables — `local_rating` (5-star), `local_comment` (text reviews), `local_like` (thumbs up/down), `local_ratings_likes` (denormalized aggregate cache)
  - AMD: `amd/src/ratings.js` (drives the AJAX update of stars)
  - Pix: `rating_graphic.php` + star images
  - Settings: `settings.php` (34) with admin toggle `review_enable`
  - Languages: en + es
  - Capability: `local/ratings:canrate` (single cap with RISK_SPAM)

- **Airpay**: `C:\xampp\htdocs\moodle5\public\local\airpay_ratings\` — **4 PHP files, 149 LOC** (≈8% of BizLMS)
  - **NO entry point files. There is no `index.php`, no `view.php`, no `update.php`.**
  - Library: `lib.php` (13) — empty defined()-check only
  - Manager: `classes/rating_manager.php` (122) — `get_average()`, `get_user_rating()`, `render()`. **NO `submit_rating()` method.**
  - 1 DB table: `local_airpay_ratings`
  - 1 lang string: `noratings`
  - No comments, no likes, no reviews, no admin moderation

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|------------|------------|-----|----------|
| 1 | **5-star rating submit** | `update.php` AJAX endpoint — inserts/updates `local_rating` row; updates `local_ratings_likes` aggregate; triggers `block_trending_modules` recompute | **No write endpoint.** `rating_manager.php` has `get_user_rating()` but no `set_user_rating()`. **A user cannot rate anything via this plugin.** | **Plugin is read-only from end-user POV** | **P0** |
| 2 | **5-star rating display** | `display_rating()` in lib.php (52 LOC); per-user rateYo widget; tooltip with per-star breakdown | `rating_manager::render()` (lines 108-121) — returns plain HTML stars + average + count; no JS widget, no click handler | Decorative only — clicking stars does nothing | **P0** |
| 3 | **Comments / reviews on items** | `local_comment` table; `comment.php` AJAX endpoint; `display_comment()` lib function; rich-text textarea; per-user single comment with edit; `reviews.php` page lists all comments on an item with paginator | **Not present.** No `local_airpay_comment` table. Users cannot write a review. | Voice-of-customer / course feedback **gone** | **P0** |
| 4 | **Like / Dislike (thumbs up/down)** | `local_like` table; `display_like_unlike()` lib function (66 LOC); like/dislike toggle; per-user single vote; counts shown | **Not present.** No like table | "Useful resource?" signal gone | **P1** |
| 5 | **Reviews page (all reviews for one item)** | `reviews.php` (66) — full-page view of all reviews for a course/classroom/program/learningplan/certification with filter/sort | **Not present.** No reviews page | Can't see "everything users said about Course X" | **P1** |
| 6 | **Trending modules feed integration** | `update.php:63-71` recomputes `block_trending_modules` when ratings change; produces "popular courses this week" feed | Not present | "Popular this week" widget lost | **P2** |
| 7 | **Enrollment gating ("you must be enrolled to rate")** | `can_participate()` in lib.php:212-250 — switch over ratearea (`local_courses`, `local_classroom`, `local_program`, `local_learningplan`, `local_certification`); returns enroll=false if user not in the underlying table; UI disables stars with explanation | Not present. Anyone with `view` cap can rate. (Though there's no submit anyway.) | Anti-spam gate **gone** | **P1** |
| 8 | **External service API** | `classes/external.php` (523) — WSDL-style functions for rating/commenting via REST | Not present | Mobile app + API rating gone | **P1** |
| 9 | **Comment moderation (admin delete)** | `delete.php` — admin-only endpoint to remove a comment | Not present. Reviews don't exist; thus moderation doesn't exist either | Compliance / abuse takedown **gone** | **P1** |
| 10 | **Settings — toggle reviews on/off** | `settings.php` exposes `review_enable` flag; admin can disable comments without disabling stars | Not present | Cannot turn off feature for a tenant | **P2** |
| 11 | **Aggregate cache (`local_ratings_likes`)** | Denormalized cache table for fast "popular" queries; refreshed on every rating | Not present. Every avg-query does a live `AVG()` aggregate | At 411 courses × 2871 users this is still cheap. But "popular catalog" queries get slower | **P2** |
| 12 | **Index of all ratings** | `index.php` (82) admin-side list of all ratings cross-tenant | Not present | Admin cannot audit ratings | **P1** |
| 13 | **Multilingual** | en + es | en only | Spanish missing | **P2** |
| 14 | **`canrate` capability with RISK_SPAM** | Single cap `local/ratings:canrate` with RISK_SPAM warning | No capability declared in `db/access.php` (file may not even exist) | Cannot delegate "this role can rate" granularly | **P2** |
| 15 | **Star widget JS (rateYo / radiostars)** | AMD `local_ratings/ratings.js` initializes interactive 5-star widget with hover preview | Not present | UI is decorative `<i class="fa fa-star">` only — no hover, no click | **P0** |
| 16 | **Backward-read of BizLMS data** | N/A | `rating_manager::get_average()` falls back to `local_rating` table (BizLMS legacy) if Airpay table empty (lines 50-60) | Airpay has migration-friendly read path | **Airpay is good** here | none |
| 17 | **Per-area "you need to enroll" message** | Returns localized string with the module type (course/classroom/program/learningplan/certification) and an inline disabled state | None | UX hint gone | **P2** |

---

## User flows (multi-step tasks) — works/broken trace

### Flow 1: Learner rates a course
**BizLMS:**
1. On `/course/view.php?id=N`, the course header includes a rateYo widget initialized by `local_ratings/ratings.js`.
2. Learner clicks 4th star → AMD posts to `update.php` with `itemid + ratearea + rating`.
3. `update.php` inserts/updates `local_rating`, recomputes aggregate `local_ratings_likes.module_rating`, returns new average.
4. Widget refreshes "4.2 (37 users)" inline.
5. `block_trending_modules` cache updated → if rating threshold crossed, course bubbles up on dashboard.

**Airpay:**
1. Some surface (e.g. course detail card) calls `rating_manager::render()` → emits `<i class="fa fa-star">` HTML.
2. Learner clicks star → **nothing happens.** No JS handler, no AJAX endpoint, no `submit_rating()` method.
3. Average never changes because no one can write.

**Result:** **Step 1 partial (display works). Step 2 BROKEN.** This plugin is non-functional from the end-user side. **P0**

### Flow 2: Learner writes a review / comment
**BizLMS:** `display_comment()` shows existing review or "Write a review" link → opens modal with rich-text textarea → POST to `comment.php` → inserts `local_comment` row → renders inline.

**Airpay:** **No comment functionality whatsoever.** **P0**

### Flow 3: Learner browses all reviews of a course before enrolling
**BizLMS:** Click "View all reviews" link → `/local/ratings/reviews.php?itemid=N&commentarea=local_courses` → paginated list of every review with reviewer name, date, full text, star.

**Airpay:** **No reviews page exists.** **P0**

### Flow 4: Admin moderates a complaint about a review
**BizLMS:** Admin views review → clicks delete → `delete.php` removes the row.
**Airpay:** N/A — no reviews to moderate.

**Result:** N/A — feature gap upstream.

### Flow 5: Admin views ratings analytics
**BizLMS:** `/local/ratings/index.php` lists all rated items with average + rater count + recent reviews.
**Airpay:** No admin surface exists.

**Result:** **DEGRADED — P1.** No way to monitor SLA on ratings.

### Flow 6: Trending courses computation
**BizLMS:** Every rating-write triggers `block_trending_modules\lib::trending_modules_crud()` which updates a denormalized popularity score.
**Airpay:** No integration.

**Result:** **DEGRADED — P2.** "Hot this week" UI feature lost (only matters if airpay still uses trending_modules block — which it does not in Phase B0 surfaces).

---

## Severity legend
- **P0** = blocks enterprise use (or here: blocks ANY use — plugin is read-only)
- **P1** = important workflow degraded but workaround exists
- **P2** = polish / ergonomics

---

## Recommended fixes (prioritised)

### Wave 1 — **P0 unblockers (this week)** — these all need to land together to make the plugin functional

1. **[P0] Add `submit_rating()` method to `rating_manager.php`**
   - **Start at:** `C:\xampp\htdocs\moodle5\public\local\airpay_ratings\classes\rating_manager.php` — append new method around line 122 (file end).
   - Signature: `public static function submit_rating(int $itemid, string $ratearea, int $rating, ?int $userid = null): object`. Returns `{average, count}`.
   - Implementation: validate 1≤rating≤5; check `can_participate()` (port from BizLMS `lib.php:212-250`); upsert into `local_airpay_ratings` (UNIQUE index on `userid+itemid+ratearea` already enforces single-vote-per-user).
   - Reference: `bizlms_disabled\ratings\update.php:28-38`.
   - Estimate: 0.5 day.

2. **[P0] Add HTTP endpoint `/local/airpay_ratings/submit.php`**
   - JSON AJAX POST handler that calls `submit_rating()`.
   - CSRF check + `require_login()` + `require_capability('local/airpay_ratings:rate', context_system::instance())`.
   - Add `local/airpay_ratings:rate` cap to **new** `db/access.php` (file does not exist yet).
   - Estimate: 0.5 day.

3. **[P0] Interactive star widget AMD module**
   - **Create:** `amd/src/star_widget.js` that:
     - Finds `.airpay-rating-widget[data-itemid][data-ratearea]` elements.
     - On hover: highlight stars up to cursor.
     - On click: POST to `/local/airpay_ratings/submit.php`, replace innerHTML with new average.
     - Show toast on error ("You must be enrolled to rate this course").
   - Update `rating_manager::render()` (line 108) to emit interactive HTML with `data-` attributes; require AMD module.
   - Estimate: 0.5 day.

4. **[P0] Reviews / comments table + write endpoint**
   - Schema: new table `local_airpay_ratings_reviews(id, itemid, ratearea, userid, review_text, status[approved|pending|removed], timecreated, timemodified)`.
   - `rating_manager::submit_review(int $itemid, string $ratearea, string $text): int`.
   - `/local/airpay_ratings/submit_review.php` endpoint.
   - Render review-modal trigger from `rating_manager::render()` (link "Write a review").
   - `/local/airpay_ratings/reviews.php` page listing all reviews for an item.
   - Estimate: 1.5 days.

5. **[P0] `can_participate()` enrolment gate**
   - Port the switch over `ratearea` from `bizlms_disabled\ratings\lib.php:212-250`.
   - Update ratearea constants to airpay equivalents: `local_airpay_courses`, `local_airpay_classroom`, `local_airpay_programs`, `local_airpay_learningpath`.
   - For courses → use Moodle `is_enrolled(context_course::instance($itemid), $USER->id)`.
   - For others → check airpay-table membership.
   - Estimate: 0.5 day.

### Wave 2 — **P1 (next week)**

6. **[P1] Like / Dislike**
   - New table `local_airpay_ratings_likes(id, itemid, ratearea, userid, likestatus[1|2], timecreated)`.
   - `rating_manager::toggle_like()` + endpoint + AMD widget.
   - Pattern from BizLMS `lib.php:6-69` (`display_like_unlike`).
   - Estimate: 1 day.
7. **[P1] Comment moderation UI for admins** — admin lists all reviews + filters by status + bulk approve/remove.
   - New `/local/airpay_ratings/moderate.php` + capability `local/airpay_ratings:moderate`.
8. **[P1] External services** for mobile/API rating submission.
9. **[P1] Admin index page** showing top-rated, lowest-rated, most-reviewed.

### Wave 3 — **P2 (ongoing)**

10. **[P2] Aggregate cache table** `local_airpay_ratings_summary` (denormalized AVG) refreshed on every submit + nightly cron.
11. **[P2] Settings page** for tenant-level review-enable toggle.
12. **[P2] Spanish lang pack**.
13. **[P2] Tooltip showing per-star distribution** (5★: 12 / 4★: 8 / etc.) on hover.
14. **[P2] Trending modules cache integration** (only if/when block is reintroduced).
15. **[P2] Per-tenant rating configuration** (allow 5-star vs 10-star scale).

---

## Risk callouts

1. **No write endpoint = ratings are dead.** Any UI surface (catalog cards, course detail pages, classroom listings, etc.) that calls `rating_manager::render()` shows zero stars and zero count, and clicking does nothing. If users complain "I can't rate this", this is why.
2. **`render()` includes `font-size:16px` inline style** (line 114). This bypasses theme tokens. Will look inconsistent in dark mode + on the airpayux brand. Fix while in there.
3. **BizLMS legacy fallback in `get_average()`** (lines 50-60) — reads `local_rating` if Airpay table empty. Once Airpay writes start happening, that fallback should be removed to prevent split-brain.
4. **No unique constraint at the DB layer beyond `userid+itemid+ratearea`** — that's correct for ratings but means there's no per-row PK lookup pattern. Upsert path needs to be careful.
5. **Compliance audit:** If BizLMS shipped a "users gave 4.3★ avg satisfaction" KPI, that number is **stuck on whatever date BizLMS stopped writing**. No one has rated anything in Airpay because there's no way to.

---

## Files most likely touched during fixes

- `classes/rating_manager.php` — add `submit_rating()`, `submit_review()`, `toggle_like()`, `can_participate()`
- **New:** `submit.php`, `submit_review.php`, `toggle_like.php`, `reviews.php`, `moderate.php`, `index.php` (admin)
- **New:** `db/access.php` (capabilities), `db/services.php` (REST), `db/upgrade.php`
- **New:** `db/install.xml` additions for `local_airpay_ratings_reviews`, `local_airpay_ratings_likes`, `local_airpay_ratings_summary`
- **New:** `amd/src/star_widget.js`, `amd/src/like_widget.js`, `amd/src/review_modal.js`
- **New:** `templates/star_widget.mustache`, `templates/reviews.mustache`, `templates/moderate.mustache`
- `lib.php` — currently a 13-line stub; needs callbacks for pluginfile + privacy
- **New:** `classes/privacy/provider.php` (GDPR — ratings + reviews are user-attributable)

---

## Bottom line

**This plugin is shipped but doesn't work.** It is a 3-method facade over a single table. The "ratings" you see in the UI today are reading legacy BizLMS data that hasn't grown since the cutover. Build at minimum #1, #2, #3 from Wave 1 to give learners a functioning star widget. Without that, every ratings number on every catalog page is a lie.
