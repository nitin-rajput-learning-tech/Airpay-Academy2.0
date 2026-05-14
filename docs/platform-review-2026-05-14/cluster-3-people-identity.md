# Cluster 3 — People & Identity

**Plugins reviewed:** `local_airpay_users`, `local_airpay_org`, `local_airpay_manager`, `local_airpay_roles`, `local_airpay_skills`

## Summary

5 plugins covering CRUD + tenant hierarchy + manager dashboards + skill taxonomy. All four core identity plugins are FUNCTIONAL. Critical gaps: no SSO onboarding flow (despite OAuth2 in core), no learner skill-growth visualization, no career-path recommendations.

## Per-plugin

| Plugin | Origin | Status | Top gap |
|--------|--------|--------|---------|
| airpay_users | BizLMS users (17 open_* fields) | FUNCTIONAL | SSO onboarding flow; SCIM; anonymization on GDPR erasure |
| airpay_org | local_costcenter | FUNCTIONAL | People directory (search); richer branding (design tokens) |
| airpay_manager | myteam (N+3 query) | FUNCTIONAL | 1:1 prep view; skill heatmap; mobile-optimized |
| airpay_roles | (custom — new) | FUNCTIONAL | RBAC inheritance; role templates; delegation |
| airpay_skills | skillrepository (flat list) | FUNCTIONAL | **Skill graph** (related skills, prerequisites); growth view; gap analysis |

## Modern-identity gap questions

| Question | Status |
|----------|--------|
| SSO-ready (Okta/Azure AD, SCIM)? | **NO** — 4-6 weeks |
| GDPR DSR <1 hour? | **YES** — smoke test passes |
| Single canonical "skill profile" view? | **PARTIAL** — no growth timeline |
| Learner sees growth over time? | **PARTIAL** — completion counts only |
| Manager 1:1 prep view? | **NO** — only high-level dashboard |
| Searchable people directory? | **NO** — team list only |

## Top 3 strategic bets

1. **SSO + SCIM provisioning** (Okta/Azure AD) — eliminates manual CSV uploads, reduces onboarding from 2 days to 10 min (P0, Q2 2026, 4-6 weeks)
2. **Skill graph + learner growth dashboard** — "Last 90 days: +3 skills" + gap analysis ("L4 Leadership required, you're L2") (P1, Q3 2026)
3. **Manager narrative insights + 1:1 prep** — per-person month-in-review drill-down with mentorship suggestions (P1, Q3 2026)
