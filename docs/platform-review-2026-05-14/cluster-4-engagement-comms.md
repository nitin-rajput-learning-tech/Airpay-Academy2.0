# Cluster 4 — Engagement, Motivation & Communications

**Plugins reviewed:** `local_airpay_gamification`, `local_airpay_challenge`, `local_airpay_ratings`, `local_airpay_emails`, `local_airpay_notifications`

## Summary

Functional foundation for engagement (gamification + badges + streaks) and production-grade email dispatch (Sprint B-complete with cert PDFs + ramping cadence + 26+ PHPUnit tests). The experience remains **transactional and broadcast-oriented** — missing the modern social/mobile/community layer. Three biggest gaps: **WhatsApp/SMS** (critical for India), **no peer-social features** (leaderboards are read-only), **no real-time instructor insights**.

## Per-plugin

| Plugin | Origin | Status | Top gap |
|--------|--------|--------|---------|
| airpay_gamification | (new, April 2026) | FUNCTIONAL (beta 1.0) | UI invisibility — points/streaks/badges in DB but no dashboard widget |
| airpay_challenge | (new, April-May 2026) | FUNCTIONAL (1.1.1) | No streak-challenges, no web-push, no social layer |
| airpay_ratings | local/ratings (BizLMS) | FUNCTIONAL | No moderation pipeline; no sentiment NLP; no instructor surfacing |
| airpay_emails | local/notifications | **PRODUCTION** (Sprint B) | Teams channel stubbed; **no WhatsApp**; **no SMS** |
| airpay_notifications | local/notifications | FUNCTIONAL (1.4.0) | Push prefs exist but no mobile app/service worker |

## "Is it fun?" gap questions

| Question | Status |
|----------|--------|
| Does Airpay Academy feel "fun" vs "homework"? | **NO** — gamification hidden in sidebars, no momentum cues, no celebration animations |
| Share completion on LinkedIn in one tap? | **NO** — completion email only, no share button |
| WhatsApp as notification channel? | **NO** — zero integration (critical for Indian market) |
| Streak/momentum mechanics drive habit? | **PARTIAL** — counter in DB, no UI display |
| Real-time engagement visible to instructors? | **NO** — completion logs are async, manager summaries are weekly |
| Peer-to-peer cohort component? | **PARTIAL** — challenges scoped to cohorts, but no chat/Q&A |

## Top 3 strategic bets

1. **WhatsApp Business API + SMS fallback** — India email open rate ~25%, WhatsApp reads >95%. THE engagement multiplier for scale. New plugin `local_airpay_whatsapp` template-driven (mirror of airpay_emails). (P0, Q3 2026, 2-3 sprints)
2. **Gamification dashboard widget + daily momentum nudges** — points/streaks/badges exist in DB but invisible. Build summary card + daily quest + "3-day streak!" toast + at-risk-streak cron nudge. (P0, Q2 2026, 2 sprints)
3. **Instructor real-time dashboard + cohort chat** — currently instructors see weekly digests only. Add WebSocket analytics tab + Discord-style cohort chat + smart-reply AI suggestions. (P1, Q3-Q4 2026, 3 sprints)
