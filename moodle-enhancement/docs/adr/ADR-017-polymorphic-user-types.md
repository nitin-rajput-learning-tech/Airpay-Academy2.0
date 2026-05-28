# ADR-017 — Polymorphic User Types (employee vs consumer)

**Status:** Proposed (drafted 2026-05-28 during Stabilization Audit Phase 2)
**Date:** 2026-05-28
**Deciders:** Nitin Rajput (pending review), Claude (drafter)
**Builds on:** ADR-001 (fork strategy + product pivot), ADR-008 (customer brand)
**Born from:** Stabilization Audit findings F-001, F-003, F-004, F-005, F-006, F-007 (foundational)

---

## Context

Across the four weekly cycles preceding this audit, the same architectural
mistake kept resurfacing in different costumes. Every fix was correct
locally but the *kind* of bug kept reappearing:

| Surface | Symptom | Root cause |
|---------|---------|------------|
| Onboarding step 2 | Public learner saw 9 internal Airpay categories (Vyaapaar, ZEEA, Tanzania subsidiaries) | No tenant scoping on the category query (F-008, fixed) |
| Dashboard "Featured for you" widget | Public learner saw "Introduction to airpay" (internal course) | No tenant scoping on the recommendations query (F-002 spawned task) |
| Profile page | Public learner saw "Department: N/A / Manager: N/A / Employee ID: N/A" fields | Profile schema modelled for employees; consumers don't have these (F-005) |
| Dashboard landing | Public learner lands on `/my/` with empty "Continue Learning" + "Recommended for you" widgets | Dashboard layout assumes employee-shape data (F-006) |
| Sidebar nav | Public learner saw "Manage Users / Manage Courses" links (filtered out late) | Sidebar built for the employee-tenant model first, consumer scoping bolted on after |
| Leaderboard widget | Showed other public learners' identities without consent | Leaderboard assumes the employee social contract (gamification within a known org) (F-002) |

All six surface bugs trace to the same modelling error: **the platform
treats "Public learner" as just another tenant in the same shape as Airpay
or ZEEA — a flat 3rd costcenter alongside two real organisations.**

Reality is more polymorphic:

- An **employee** (Airpay / ZEEA / any future enterprise) has a manager, a
  department, an employee ID, a hire date, a job role, mandatory compliance
  training, a learning path assigned by L&D, and a workplace social
  context where seeing colleagues on a leaderboard is appropriate.

- A **consumer** (a Public learner who signed up at airpay.academy/signup)
  has none of these. They have **an email, an interest taxonomy, and a
  course they paid for or self-enrolled in.** They have NO manager,
  NO department, NO compliance obligations, NO workplace social context.
  A leaderboard showing them next to other strangers is a privacy issue
  AND a UX mismatch — they're not competing with peers, they're learning
  on their own.

The current model — `open_path = '/77'` = Public tenant — pretends these
are the same kind of entity. They are not.

### Why this can't keep getting patched per-surface

Every weekly cycle, a new surface inherits the same employee-shape
assumption. Counting just today's six findings: each was a separate
two-hour-ish patch. At ~6 findings × ~2 hours × cycles-to-come, the
incremental cost beats a one-time architectural fix by N+2.

More importantly: **future Sentientia customers will have a mix too.**
A SaaS LMS sold to a marketing-training company will have:
  - Internal trainers (employees)
  - Paying B2C learners (consumers)
  - Possibly partner-org learners (employees of a different customer)

The user-type axis is **orthogonal to the tenant axis.** Both axes need
to exist as first-class concerns.

---

## Decision

Introduce **`user_type`** as a first-class concept on `mdl_user`, with
exactly two values in v1:

| Value | Meaning | Example | Identifying signal |
|-------|---------|---------|--------------------|
| `employee` | Person learning in a workplace context (organisation tenant) | Airpay HR rep, ZEEA staff | `open_path` resolves to a tenant root that has a non-null `local_costcenter` row marking it as an org tenant |
| `consumer` | Person learning in a self-directed context (no workplace tenant) | Public-signup learner | `open_path = '/77'` (the Public root, by convention) OR newly-signed-up users with no managed-org membership |

`user_type` is **derived** at provisioning time (signup OR HRMS-sync) and
**stored** in the new `mdl_local_airpay_user_type` extension table. It is
NOT re-derived on every request — once classified, the user keeps their
type until an explicit migration event (an L&D Admin promotes a public
learner to a tenant employee).

### Schema changes

Two new tables (additive — does NOT touch `mdl_user`):

```xml
<TABLE NAME="local_airpay_user_type" COMMENT="User-type polymorphism (ADR-017)">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="user_type" TYPE="char" LENGTH="20" NOTNULL="true"
           COMMENT="employee | consumer"/>
    <FIELD NAME="provisioning_source" TYPE="char" LENGTH="40" NOTNULL="true"
           COMMENT="signup_public | hrms_sync | manual_admin | invite_paid"/>
    <FIELD NAME="provisioned_at" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="last_promoted_at" TYPE="int" LENGTH="10"
           COMMENT="set when a consumer is promoted to employee"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="unique_user" TYPE="unique" FIELDS="userid"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="idx_type" UNIQUE="false" FIELDS="user_type"/>
  </INDEXES>
</TABLE>

<TABLE NAME="local_airpay_employee_profile" COMMENT="Employee-only profile fields">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="employee_id" TYPE="char" LENGTH="40" NOTNULL="false"/>
    <FIELD NAME="department" TYPE="char" LENGTH="80" NOTNULL="false"/>
    <FIELD NAME="job_title" TYPE="char" LENGTH="80" NOTNULL="false"/>
    <FIELD NAME="manager_userid" TYPE="int" LENGTH="10" NOTNULL="false"
           COMMENT="FK to mdl_user.id (the supervisor)"/>
    <FIELD NAME="hire_date" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="cost_center_path" TYPE="char" LENGTH="255" NOTNULL="false"
           COMMENT="cached snapshot of mdl_user.open_path at provisioning"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="unique_user" TYPE="unique" FIELDS="userid"/>
    <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="fk_manager" TYPE="foreign" FIELDS="manager_userid"
         REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
</TABLE>

<TABLE NAME="local_airpay_consumer_profile" COMMENT="Consumer-only profile fields">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="interests_json" TYPE="text" NOTNULL="false"
           COMMENT="comma-list of course_categories.id or topic tag IDs"/>
    <FIELD NAME="weekly_goal" TYPE="int" LENGTH="2" NOTNULL="false"
           COMMENT="hours per week the learner committed to (1-7)"/>
    <FIELD NAME="referral_source" TYPE="char" LENGTH="40" NOTNULL="false"
           COMMENT="signup attribution: google | social | direct | invite | partner"/>
    <FIELD NAME="consent_marketing" TYPE="tinyint" LENGTH="1" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="consent_leaderboard" TYPE="tinyint" LENGTH="1" NOTNULL="true" DEFAULT="0"
           COMMENT="opt-in to be visible in leaderboards; defaults OFF (DPDP)"/>
    <FIELD NAME="payment_history_url" TYPE="char" LENGTH="255" NOTNULL="false"
           COMMENT="URL to airpay_cart purchase history (NOT internal fields)"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="unique_user" TYPE="unique" FIELDS="userid"/>
    <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
</TABLE>
```

### Provider interface

A new abstraction lives in `local/airpay_core/classes/user_type_provider.php`:

```php
namespace local_airpay_core;

interface user_type_provider {
    /** The type ID this provider serves (employee | consumer). */
    public static function type_id(): string;

    /** Human-readable label for this user-type (lang-aware). */
    public static function label(): string;

    /** Profile-page context — returns Mustache data shape for /user/profile.php. */
    public function profile_context(\stdClass $user): array;

    /** Dashboard widgets this user-type sees (in display order). */
    public function dashboard_widgets(\stdClass $user): array;

    /** Sidebar nav items relevant to this user-type. */
    public function sidebar_items(\stdClass $user): array;

    /** Onboarding flow steps (overrides the default 3-step employee flow). */
    public function onboarding_steps(\stdClass $user): array;

    /** Consent surfaces this user-type encounters (GDPR/DPDP). */
    public function required_consents(): array;

    /** Whether a specific feature is enabled for this user-type. */
    public function feature_supported(string $featurekey): bool;
}

class employee_provider implements user_type_provider { /* impl */ }
class consumer_provider implements user_type_provider { /* impl */ }

class user_type_factory {
    public static function for_user(int $userid): user_type_provider {
        // Read mdl_local_airpay_user_type, instantiate the right provider.
        // Cache result per-request.
    }
}
```

### Resolution rule (the only place the difference is decided)

```php
// local/airpay_core/classes/user_type_resolver.php
function classify_user_at_provisioning(int $userid, string $source): string {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // Rule 1: public signup → consumer (unless the signup form explicitly
    //         indicates an employee invitation token).
    if ($source === 'signup_public') {
        return 'consumer';
    }

    // Rule 2: HRMS sync → employee.
    if ($source === 'hrms_sync') {
        return 'employee';
    }

    // Rule 3: Manual admin creation → employee (admin must explicitly
    //         pick consumer via the user-type radio).
    if ($source === 'manual_admin') {
        // The form field is required and defaults to 'employee'.
        return optional_param('user_type', 'employee', PARAM_ALPHA);
    }

    // Rule 4: Paid invite (B2B partner) → consumer (paid B2C-style, not
    //         an Airpay employee).
    if ($source === 'invite_paid') {
        return 'consumer';
    }

    // Default (defensive): employee. The migration phase classifies
    // existing /77 users as consumer explicitly via the migration CLI.
    return 'employee';
}
```

### How existing call-sites change

Three patterns replace what's there today:

```php
// BEFORE (the F-005 N/A profile fields)
$context['department']   = $user->profile_field_department ?? 'N/A';
$context['employee_id']  = $user->profile_field_employee_id ?? 'N/A';
$context['manager_name'] = $user->profile_field_manager ?? 'N/A';

// AFTER (provider decides shape)
$provider = \local_airpay_core\user_type_factory::for_user($user->id);
$context  = array_merge($context, $provider->profile_context($user));
// employee_provider returns department/employee_id/manager_name
// consumer_provider returns interests/weekly_goal/courses_completed
```

```php
// BEFORE (the F-006 dashboard widget that leaks Airpay courses)
$featured = $DB->get_records('course', ['visible' => 1], '', '*', 0, 6);

// AFTER (provider decides which widgets render)
$provider = \local_airpay_core\user_type_factory::for_user($user->id);
foreach ($provider->dashboard_widgets($user) as $widget) {
    echo $OUTPUT->render($widget);
}
// employee_provider: ContinueLearning, MandatoryCompliance, TeamCertifications
// consumer_provider: InterestBasedRecommendations, EnrolledCourses, PaymentHistory
```

```php
// BEFORE (the F-002 leaderboard widget renders for everyone)
echo $renderer->leaderboard_widget();

// AFTER (consent + user-type aware)
$provider = \local_airpay_core\user_type_factory::for_user($user->id);
if ($provider->feature_supported('leaderboard') &&
    $provider->required_consents()['leaderboard'] === true) {
    echo $renderer->leaderboard_widget();
}
// employee_provider: leaderboard always supported (workplace social contract)
// consumer_provider: only if user opted IN to consent_leaderboard (DPDP-safe)
```

---

## Consequences

### Positive

1. **6 downstream findings close at once** — F-001, F-003, F-004, F-005, F-006, and the leaderboard arm of F-002 all become "use the provider" — single fix, single mental model.

2. **GDPR/DPDP becomes structural.** `consumer_provider::required_consents()` is the canonical list. No more "did we forget to ask consent on the new feature?" — the abstract method forces every new feature to declare its consent surface.

3. **Future Sentientia customers slot in.** When the marketing-training company joins, their B2C learners are `consumer`, their trainers are `employee`. The same code paths work. No special-case for the new customer.

4. **Dashboard widgets become composable.** Adding a new widget = registering it in one provider, no more "should this widget render for X?" if-ladder in the layout.

5. **Onboarding becomes pluggable.** Consumer onboarding can show interest-picker + weekly goal; employee onboarding can show manager-introduction + compliance-walkthrough. Two separate flows, one mechanism.

6. **The leaderboard consent problem (F-002) becomes a default OFF for consumers.** Solves the DPDP exposure.

### Negative / cost

1. **One-time migration risk.** Currently `/77` users have NO `user_type` row. The migration CLI must classify ~669 Public-tenant users on local and ~similar on production. Bad classification = wrong dashboard.

2. **Two new tables to maintain.** Schema migrations on every new field in either profile table. Mitigated by: most profile fields stay stable, plugin developers extend via key-value bag in interests_json if needed.

3. **Provider boilerplate.** Every user-type-aware surface has to invoke `user_type_factory::for_user()`. ~80 surfaces total. Mitigated by: trait `user_type_aware` for renderables.

4. **Backwards-incompatible profile shape.** The Mustache template for `/user/profile.php` becomes polymorphic. Existing customisations that read `{{department}}` directly will get empty string for consumers. Mitigated by: provider always returns the union of keys; missing values are empty string not undefined.

### Neutral

5. **A user can be promoted across types (consumer → employee).** When a Public learner is hired by Airpay, their consumer profile data is preserved (interests carry over) but a new employee_profile row is created. The `user_type` flips with `last_promoted_at` timestamp. Audit trail intact.

---

## Migration path

### Phase 0 (DB-only): add the schema

- Create the 3 tables via `local/airpay_core/db/install.xml` upgrade.
- No code reads them yet.
- Safe to deploy alongside no behaviour change.

### Phase 1: backfill classification (CLI)

```bash
php local/airpay_core/cli/classify_existing_users.php
```

Walks every user, applies the resolution rule retroactively:
- Users with `open_path LIKE '/77%'` → consumer
- Users with `open_path LIKE '/1%'` OR `/177%'` → employee
- Users with NULL `open_path` (siteadmins) → employee
- Writes one row per user to `local_airpay_user_type`
- Backfills `employee_profile` from existing custom profile fields where present
- Backfills `consumer_profile` for /77 users with empty interests (defaults)
- Outputs a CSV of the classification for review

### Phase 2: switch the profile page

- Refactor `local/airpay_users/profile.php` to use `user_type_factory`
- Both providers' `profile_context()` implementations land together
- Mustache template updated to render the union shape
- Visual diff verified for both an employee account and a consumer account

### Phase 3: switch the dashboard

- Layout/dashboard.php uses provider's `dashboard_widgets()`
- Public learner lands on consumer-shaped dashboard (interest-based, no "Team")
- Employee continues to see today's dashboard

### Phase 4: switch onboarding + sidebar + leaderboard

- Provider-driven onboarding (consumer = interest picker + weekly goal; employee = today's flow)
- Provider-driven sidebar (consumer hides "Compliance / Team / Manage Users"; only shows "My Courses / Catalog / Leaderboard (opt-in)")
- Leaderboard consults provider for consent + feature_supported gate

### Phase 5: signup form gains user-type

- New users from public signup are classified `consumer` at provisioning time
- HRMS sync users are classified `employee` (existing path; just add the row write)
- Manual admin form gains a user-type radio (employee | consumer); default employee for Airpay-context admin

---

## Open questions (for Nitin to triage before implementation)

These are NOT blockers but they need a call before Phase 2-5 execute:

1. **Mid-life promotion semantics.** A consumer who later becomes an employee — does their consumer interest history transfer to their employee dashboard? Or does the previous identity get archived and a fresh employee profile created? Recommendation: preserve interest history (it's still useful) but the new employee profile is the "source of truth" going forward. Their old consumer profile becomes read-only.

2. **Partner-org users (third tenant pattern).** ZEEA users today are modelled the same as Airpay employees. When we onboard the next Sentientia customer (let's call them BankCo), are BankCo's employees `employee` or do we need a third type? Recommendation: stay with two types for v1; the tenant axis already gives us per-customer scoping. Only add a third type if we genuinely have a non-employee-non-consumer use case (e.g. instructors-as-vendors).

3. **What about Site Admins?** They have `open_path = NULL` and are technically neither employees nor consumers — they're operators. Recommendation: default to `employee` for siteadmins (the profile fields department/manager mostly apply if they ARE Airpay staff). Don't introduce a third type for ~3 admin users.

4. **Should `user_type` be visible to users themselves?** I.e. on the profile-edit page, can a user see/change their own type? Recommendation: visible (badge in header) but NOT editable. Changing user-type is an admin operation.

5. **Provider for Site Admin role?** Or do we delegate to provider's behaviour with `is_siteadmin($USER)` bypass everywhere? Recommendation: providers are not role-aware; they only model the user-type. Role-aware behaviour (siteadmin can see everything; L&D admin sees admin surfaces) stays where it is today, in `role_detector`. The two axes (user_type, role) compose.

6. **Hindi parity for the user-type strings.** New strings (label, profile field labels, consent surfaces, onboarding step titles) need 100% Hindi parity per CLAUDE.md absolute rule. Add ~30 strings × 5 locales = 150 lang strings before merge.

7. **Does Manager fit somewhere here?** A manager is an employee who ALSO has a `team_members` list. Recommendation: keep manager as a *capability* of the employee user-type, not a separate user-type. `employee_provider` returns "team certification stats" widget when the user is a supervisor; otherwise it skips.

---

## Acceptance criteria (when this ADR is "accepted")

1. Nitin reviews the 7 open questions above and gives a yes/no on each.
2. Phase 0 (schema migration) lands on local with no behaviour change.
3. Phase 1 (CLI classification) runs against local DB and produces a clean
   2,871-row CSV of `userid, user_type, source` for review.
4. Phase 2 (profile-page refactor) lands and both a `/77` user and a `/1`
   user render their correct profile shape with visual evidence captured.
5. ADR-017 status changes from Proposed → Accepted.

If question (2) above (Site Admin user-type) lands differently than the
recommendation, the migration CLI changes accordingly.

---

## Cross-references

- F-007 (foundational) — this ADR closes that finding.
- F-001, F-003, F-004, F-005, F-006 — closed by Phase 3-4 of this ADR.
- F-002 (leaderboard consent) — partially closed by Phase 4 (consent
  surface). The "is the leaderboard a fit at all?" decision is separate
  and stays in F-002.
- ADR-008 (customer brand) — the user_type axis is orthogonal to the
  customer axis. They compose.
- ADR-009 (detection consistency) — `role_detector` and
  `user_type_factory` are two separate single-sources-of-truth.
