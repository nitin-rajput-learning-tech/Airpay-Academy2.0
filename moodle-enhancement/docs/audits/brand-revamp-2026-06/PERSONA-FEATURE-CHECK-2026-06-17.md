# Persona feature check — new gap-build plugins (2026-06-17)

Live in-browser walk (authenticated fetch-probes, Chrome MCP) of the 9 new
"11-gap competitive build" plugins (the 2026061600 cohort) across personas, on
local XAMPP (`http://localhost:8080`). All gap feature flags were flipped ON in
**mock mode** (no API cost) for the walk via `enable_gap_flags.php`.

## Environment fixes made to enable the walk
- **OPcache was disabled** in `C:\xampp\php\php.ini` (`;zend_extension=opcache`
  commented) → every page recompiled all of Moodle = **7–16 s/page**. Enabled
  the extension (+`opcache.enable_cli=1`); after an Apache restart pages dropped
  to ~2–3 s. Backup: `php.ini.bak-opcache-20260617`. **This also affects real
  usage** — keep OPcache on.
- Persona passwords were stale (post-reinstall). Reset the 8 persona accounts to
  the documented local password via `reset_persona_pw.php` (all 8 exist, none
  suspended; academy@ = siteadmin).

## Result 1 — all 9 new plugins INSTALL + RENDER (Site Admin)
Every primary surface returned HTTP 200 with its real page, no PHP fatal:

| Plugin | Surface(s) verified | Verdict |
|--------|--------------------|---------|
| sentientia_api | index ("Sentientia Public API"), lti/jwks.php (JSON `{"keys":[]}`) | OK |
| sentientia_xapi | index ("xAPI Statement Viewer") | OK |
| sentientia_learningpath | index ("Learning Paths") | OK |
| sentientia_analytics | index ("Analytics Dashboard") | OK |
| sentientia_content_market | index (browse grid) | OK |
| sentientia_skillsai | index ("Skills extraction queue"), extract.php | OK |
| sentientia_talent | index ("Talent mobility console"), opportunities.php | OK |
| sentientia_authoring | index + studio.php ("Authoring Studio") | OK |
| sentientia_assistant | agent.php ("Learning Copilot") | OK |

(The just-installed api + xapi — fixed this session for the XMLDB DDL blockers —
both render correctly.)

## Result 2 — persona access / gating
| Persona (account) | Reachable new surfaces | Correctly blocked | Notes |
|---|---|---|---|
| Site Admin (academy@) | ALL 9 | — | siteadmin bypasses caps |
| Learner (fatma.khamis) | assistant copilot, talent opportunities, content_market | analytics, skillsai, authoring, api (all denied) | ✅ no privilege escalation |
| Manager (binay.upadhyay) | — (blocked from analytics/learningpath/talent) | analytics, learningpath, talent | ⚠️ holds no `manager` **role** at system ctx (manager only by org-path) |
| Course Author (asif.ansari) | assistant copilot | authoring, skillsai | ⚠️ airpay `trainer` role = **teacher** archetype, excluded |

Public Learner / Compliance / Tenant Admin not individually walked — their access
follows the capability matrix below (Public ≈ Learner-minus; Compliance/Tenant
Admin gated by whether their role carries manager-archetype caps).

## KEY FINDING (deploy-critical) — new-plugin caps not on airpay roles
The new plugins grant caps to standard archetypes (`editingteacher`, `manager`),
but the airpay role suite maps differently:
- `trainer` (the SME/author role, "only one on the DB") → archetype **`teacher`**
- `employee` (the learner role) → archetype **`student`**
- org-position "managers" hold no Moodle `manager` role at system context

Resulting gaps (verified via `mdl_role_capabilities`):
- **`authoring:generate` + `skillsai:extract` → only admin/editingteacher/manager**,
  NOT `trainer`/`teacher`. **Airpay SMEs/trainers cannot use AI course-authoring
  or skills-extraction.** (Same class as the fixed qa-walk **T-01**, where
  `sentientia_live:create` excluded the `teacher` archetype → fixed by adding the
  archetype + an `upgrade.php` cap back-fill onto existing roles.)
- `talent`/`analytics`/`learningpath` admin surfaces need the `manager` role —
  org-position managers won't see them until assigned that role (or the caps).

**Recommendation:** for each new gap plugin whose author/manager features airpay
staff are meant to use, add the airpay archetypes (`teacher` for the trainer/SME
role) to the relevant capability defaults in `db/access.php` **and** ship an
`upgrade.php` back-fill (`assign_legacy_capabilities` / explicit
`role_change_permission`) onto existing roles — mirroring the T-01 fix. Otherwise
the competitive features render for siteadmins in demos but are invisible to the
real authors/managers in production.

## Minor finding
- Capability denials on the new plugins return **HTTP 404** (themed "no
  permission" page) rather than the conventional **403**. Access is correctly
  denied (no security issue); the status code is non-standard (may be intentional
  existence-hiding — worth a deliberate decision).

## Flag state left ON (mock mode) for continued testing
`enable_gap_flags.php` turned 16 gap flags ON globally (mock mode). To restore
production-default OFF: `FLAG_OFF=1 php enable_gap_flags.php`. Live/prod ships
default-OFF regardless (these are local DB flag rows only).

## RESOLUTION — capability back-fill implemented (2026-06-17)
The author/SME cap gap above is **fixed in code** (commits `f2242e9a1` + state-card
`91f222239`):
- Added `teacher => CAP_ALLOW` to authoring (generate/review/managetemplates) +
  skillsai (extract/review) in each `db/access.php`; manager-only curation caps
  left untouched.
- Idempotent `db/upgrade.php` back-fill (2026061700) grants those caps onto
  existing teacher-archetype roles (the airpay `trainer` role). Upgrade ran clean.
- **Verified end-to-end:** the `trainer` role now holds all 5 caps
  (`permission=1`), and a **system-context** trainer (`qa_trainer`, uid 3419)
  resolves `has_capability(:generate / :extract) = YES` — the exact page gate.

**Provisioning answer — dedicated "Sentientia Author" role shipped (2026-06-17).**
The tools check at CONTEXT_SYSTEM, but airpay assigns the `trainer` role at
CATEGORY context, so a category-scoped SME (e.g. asif.ansari) resolved NO at
system. Rather than over-grant the broad `trainer` role site-wide, the product now
ships a dedicated, scoped role:

- **Role:** `Sentientia Author` (shortname `sentientiaauthor`), assignable at
  **SYSTEM context only**, carrying exactly the five author/SME caps
  (`authoring:generate|review|managetemplates` + `skillsai:extract|review`) and
  **nothing else** — no teacher/manager archetype breadth.
- **Shipped in code** via `local_sentientia_authoring/db/upgrade.php` step
  **2026061701** (idempotent: created only when the shortname is free; caps
  re-synced each run; caps whose plugin isn't installed are skipped). So it
  auto-creates on every deployment — Airpay and future customers — with no manual
  step. Verified locally: role id=11, SYSTEM-only, all 5 caps `ALLOW`.
- **Provisioning helper:** `assign_author_role.php` (this folder) — idempotent
  CLI that assigns named SMEs to the role at system context and verifies
  `has_capability` resolves YES at the page gate. Run once per deployment as part
  of the rollout gate (`UNASSIGN=1` to revoke). Dry-run (no args) audits the role.
- **End-to-end verified:** assigned `asif.ansari@airpay.co.in` (uid 2304, the
  Course Author persona) → `has_capability` = **YES** for all five caps at system
  context. The asif gap (row 4 of the persona table above) is **closed**.

**Still open (PROVISIONING, per-deployment — NOT code) for the MANAGER surfaces:**
`analytics`/`learningpath`/`talent` admin caps are already on the `manager` role,
but org-position managers hold no system-context `manager` role. Provision them
the same way (assign the `manager` role — or a future scoped "Sentientia Analyst"
role — at system context). Belongs in the rollout-gate provisioning checklist
alongside the author-role assignment above.
