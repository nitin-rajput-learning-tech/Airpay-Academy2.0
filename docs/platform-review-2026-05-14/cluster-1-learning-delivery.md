# Cluster 1 — Learning Delivery & Content

**Plugins reviewed:** `local_airpay_courses`, `local_airpay_catalog`, `local_airpay_classroom`, `local_airpay_exams`, `local_airpay_learningpath`, `local_airpay_programs`, `local_airpay_recompletion`

## Summary

Functional migration from BizLMS with modern admin UI (datatables + web services) but missing modern features — AI recommendations, adaptive learning, xAPI, mobile-first design. Mixed maturity: **PRODUCTION** for courses + catalog; **FUNCTIONAL** for exams + classroom; **STUB** for learningpath + programs + recompletion.

## Per-plugin

| Plugin | Origin (BizLMS) | Status | Top gap |
|--------|-----------------|--------|---------|
| airpay_courses | local_courses (70 files) | PRODUCTION | AI recommendations, course versioning, author marketplace |
| airpay_catalog | (new — LXP-style) | FUNCTIONAL | No web service API; mobile-responsive audit; AI feed |
| airpay_classroom | local_classroom (67 files) | FUNCTIONAL | Virtual classroom integration (Zoom/Teams); session recordings |
| airpay_exams | local_onlineexams (67 files) | FUNCTIONAL | AI proctoring depth; xAPI/cmi5; adaptive difficulty |
| airpay_learningpath | local_learningplan (60+) | **STUB** | Path-course sequencing table missing; learner-facing flow incomplete |
| airpay_programs | local_program (60+) | **STUB** | Competency model unbuilt; multi-level progression unbuilt |
| airpay_recompletion | local_recompletion (66) | **STUB** | Admin UI, cron task, dashboard all missing |

## Cross-plugin themes

1. **Unified completion event stream** — all 7 plugins should emit xAPI statements to one LRS
2. **AI recommendations everywhere** — content-based + collab filtering, surface in catalog + learningpath
3. **Mobile-first** — current UIs assume desktop; need PWA + offline video
4. **Versioning + metadata standards** — every content type needs draft/published/archived
5. **Admin UX parity** — 3 plugins are stubs vs 4 with mature datatable UIs

## Top 3 strategic bets

1. **AI content recommendations engine** — highest-ROI engagement lever
2. **Complete learning paths → skills-based dynamic routing** — shifts course-centric to skill-centric
3. **xAPI / cmi5 compliance + unified analytics** — unlocks cross-plugin reporting
