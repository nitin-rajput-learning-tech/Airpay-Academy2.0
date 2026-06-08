# local_sentientia_skills

Skills taxonomy, gap analysis, skill-radar chart. Learner-facing
self-assessment plus admin-facing skill-to-course mapping.

| Field | Value |
|---|---|
| Component | `local_sentientia_skills` |
| Version | 1.4.0 |
| Depends on | `local_sentientia_org`, `local_sentientia_courses` |

## What it does

- Skill catalogue with categories and levels (beginner / intermediate /
  advanced).
- Per-user skill profile (what skills the user has + at what level).
- Skill-radar chart visualising the user's profile.
- Course-skill mapping: tag a course as developing skill X at level Y.
- Gap analysis: for a target role, list skills the user lacks plus the
  courses that develop them.

## Tables (5)

- `local_sentientia_skills` — skill catalogue.
- `local_sentientia_skill_cats` — categories grouping skills.
- `local_sentientia_skill_levels` — proficiency-level definitions.
- `local_sentientia_user_skills` — user-to-skill-level mapping.
- `local_sentientia_role_skills` — target-role skill requirements.
- `local_sentientia_course_skills` — course-to-skill development mapping.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_skills/cli/smoke_course_mapping.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_skills/cli/smoke_observer.php"
```

## Privacy / GDPR

Provider exists. Skill-profile data is the user's own self-assessment;
DSR export bundles it.

## Open backlog

- Manager-assigned skill level (currently self-assessed only — a manager
  override would add credibility).
- Skill detail page with levels / designations / courses / learners tabs
  (FUTURE-DESIGN in master-doc Section 12).
- LinkedIn-style endorsements from peers.
