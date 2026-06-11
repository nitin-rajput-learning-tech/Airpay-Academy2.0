# ADR-017 — Polymorphic User Types

**Status:** Accepted (2026-05-28 — Nitin answered all 7 open questions)
**Date:** 2026-05-28
**Deciders:** Nitin Rajput, Claude (drafter)
**Builds on:** ADR-001 (fork strategy + product pivot), ADR-008 (customer brand)
**Born from:** Stabilization Audit findings F-001, F-003, F-004, F-005, F-006, F-007 (foundational)

## Resolution summary (post-Q&A 2026-05-28)

The 7 open questions in §Open questions have been answered. Key
deviations from the original draft:

| Q | Decision | Schema impact |
|---|----------|---------------|
| Q1 — promotion semantics | **Accounts never merge.** A hired consumer gets a NEW employee account. No data crosses types. | No "promotion" code path; user_type is immutable per account |
| Q2 — partner-org users | **Third type: `partner_employee`.** B2B partner staff is its own type. | New `local_airpay_partner_employee_profile` table |
| Q3 — Site Admins | **Fourth type: `operator`.** Platform operators explicit. | New `local_airpay_operator_profile` table |
| Q4 — self-visibility | **Visible read-only badge** on profile page header | Mustache change only |
| Q5 — role composition | **Two separate axes** (user_type_factory + role_detector compose at call-site) | None |
| Q6 — locale parity | **All 5 locales blocking** (en+hi+kn+mr+sw) before merge | ~150 lang strings before merge |
| Q7 — Manager modelling | **Capability of employee** (manager_userid pointer), NOT a separate type | None |

**v1 has 4 user_types:** `employee`, `consumer`, `partner_employee`, `operator`.

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
**four values** in v1 (post-Q&A 2026-05-28):

| Value | Meaning | Example | Identifying signal |
|-------|---------|---------|--------------------|
| `employee` | Person learning in a workplace context as Airpay-customer staff | Airpay HR rep, ZEEA staff | `open_path` resolves to the customer-zero tenant subtree (`/1`, `/177` for ZEEA) AND `local_costcenter.customerid = AIRPAY` |
| `consumer` | Self-directed public learner, no employment context | Public-signup learner at airpay.academy/signup | `open_path` starts with `/77` (Public root) — no employer relationship |
| `partner_employee` | B2B partner-organisation employee (future Sentientia customers' staff) | BankCo HR rep when BankCo onboards as a customer | `open_path` resolves to a non-Airpay customer tenant — different `local_costcenter.customerid` |
| `operator` | Platform operator — Site Admin or Sentientia-side staff | Airpay platform team, support engineers | `is_siteadmin()` true OR explicit role assignment; usually `open_path` NULL |

`user_type` is **derived** at provisioning time (signup OR HRMS-sync OR
admin-create OR partner-org sync) and **stored** in
`mdl_local_airpay_user_type`. It is **immutable per account** (Q1 ruling
2026-05-28): a consumer who joins a customer-org gets a NEW account
provisioned as `employee` — no data carries over from the consumer
account. The consumer account remains as a separate identity. This
keeps the type axis simple and the data privacy story clean.

### Schema changes

Two new tables (additive — does NOT touch `mdl_user`):

```xml
<TABLE NAME="local_airpay_user_type" COMMENT="User-type polymorphism (ADR-017)">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="user_type" TYPE="char" LENGTH="20" NOTNULL="true"
           COMMENT="employee | consumer | partner_employee | operator"/>
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

<TABLE NAME="local_airpay_partner_employee_profile" COMMENT="Partner-org employee profile (B2B customer staff, e.g. BankCo HR)">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="customer_id" TYPE="int" LENGTH="10" NOTNULL="true"
           COMMENT="FK to local_airpay_core.customer registry — partner org"/>
    <FIELD NAME="partner_employee_id" TYPE="char" LENGTH="40" NOTNULL="false"
           COMMENT="employee ID as supplied by the partner-org HRMS sync"/>
    <FIELD NAME="partner_department" TYPE="char" LENGTH="80" NOTNULL="false"/>
    <FIELD NAME="partner_job_title" TYPE="char" LENGTH="80" NOTNULL="false"/>
    <FIELD NAME="partner_manager_userid" TYPE="int" LENGTH="10" NOTNULL="false"
           COMMENT="FK to mdl_user.id (supervisor in same partner org)"/>
    <FIELD NAME="partner_hire_date" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="cost_center_path" TYPE="char" LENGTH="255" NOTNULL="false"
           COMMENT="cached open_path within the partner-org subtree"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="unique_user" TYPE="unique" FIELDS="userid"/>
    <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="fk_manager" TYPE="foreign" FIELDS="partner_manager_userid"
         REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="idx_customer" UNIQUE="false" FIELDS="customer_id"
           COMMENT="every partner-scoped query hits this"/>
  </INDEXES>
</TABLE>

<TABLE NAME="local_airpay_operator_profile" COMMENT="Platform operator profile (Site Admins, Sentientia-side staff)">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="operator_role" TYPE="char" LENGTH="40" NOTNULL="false"
           COMMENT="siteadmin | support | sentientia_staff | dpdp_dpo"/>
    <FIELD NAME="contact_phone" TYPE="char" LENGTH="40" NOTNULL="false"/>
    <FIELD NAME="oncall_for_customer_id" TYPE="int" LENGTH="10" NOTNULL="false"
           COMMENT="if support-rotation, the customer this operator owns this week"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="unique_user" TYPE="unique" FIELDS="userid"/>
    <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
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

class employee_provider         implements user_type_provider { /* impl */ }
class consumer_provider         implements user_type_provider { /* impl */ }
class partner_employee_provider implements user_type_provider { /* impl */ }
class operator_provider         implements user_type_provider { /* impl */ }

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
function classify_user_at_provisioning(int $userid, string $source,
                                        ?int $customerid = null): string {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // Rule 1: public signup at airpay.academy/signup → consumer.
    if ($source === 'signup_public') {
        return 'consumer';
    }

    // Rule 2: HRMS sync from a customer org → employee or partner_employee
    //         depending on whether the customer is Airpay (customer-zero)
    //         or a B2B partner customer.
    if ($source === 'hrms_sync') {
        $isairpay = ($customerid === null
            || $customerid === \local_airpay_core\customer::AIRPAY);
        return $isairpay ? 'employee' : 'partner_employee';
    }

    // Rule 3: Manual admin creation → admin picks the type via the
    //         user-type radio (defaults employee for Airpay-context admin,
    //         operator only available to siteadmins).
    if ($source === 'manual_admin') {
        $allowed = ['employee', 'consumer', 'partner_employee', 'operator'];
        $picked = optional_param('user_type', 'employee', PARAM_ALPHA);
        return in_array($picked, $allowed, true) ? $picked : 'employee';
    }

    // Rule 4: Operator (Site Admin) provisioning is admin-only and uses
    //         source 'manual_admin' OR 'sentientia_internal'.
    if ($source === 'sentientia_internal') {
        return 'operator';
    }

    // Rule 5: Paid invite from a partner-org bulk-purchase flow.
    if ($source === 'invite_paid' && $customerid !== null) {
        $isairpay = ($customerid === \local_airpay_core\customer::AIRPAY);
        return $isairpay ? 'consumer' : 'partner_employee';
    }

    // Default (defensive): employee. The migration phase classifies
    // existing users explicitly via the backfill CLI.
    return 'employee';
}

// IMMUTABILITY RULE (Q1 ruling 2026-05-28): once classified, a user's
// type cannot change for the lifetime of that account. If a consumer
// gets hired, they get a NEW employee account; the consumer account
// stays as-is. Implementation: `local_airpay_user_type.user_type` has
// no UPDATE path; only INSERT-on-provisioning + soft-archive via
// `mdl_user.deleted = 1` on the old account.
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

## Open questions (RESOLVED 2026-05-28)

All 7 questions answered by Nitin via `AskUserQuestion` 2026-05-28.
Summary table at top of this ADR; full text of each below.

1. **✅ Q1 — Mid-life promotion semantics.** RULING: **Accounts never merge.** A consumer who is hired becomes a NEW employee account; no data carries over from the consumer account. The consumer account remains as a separate identity (`deleted=0` until manually offboarded). `user_type` is immutable per account. This is stricter than the original recommendation but cleaner for both data privacy (no implicit cross-context data flow) and the data model (no "promotion" code path).

2. **✅ Q2 — Partner-org users.** RULING: **Third type `partner_employee`.** When BankCo onboards as a customer, BankCo's HR staff get user_type=`partner_employee`. They share the same workplace-context legitimate-interest basis as Airpay employees but their profile shape (employee_id, department, manager) is in a separate `local_airpay_partner_employee_profile` table tagged with `customer_id` to enforce per-customer scoping.

3. **✅ Q3 — Site Admins.** RULING: **Fourth type `operator`.** Platform operators (Airpay platform team, Sentientia support) are explicitly modelled. `local_airpay_operator_profile` carries contact_phone + operator_role + (if applicable) oncall_for_customer_id.

4. **✅ Q4 — Self-visibility.** RULING: **Visible read-only badge.** Profile page renders a small badge ("Learner" / "Employee" / "Partner staff" / "Operator") that the user can see but not edit. Changing user_type is an admin-only operation; in practice it's near-zero since type is immutable per account (per Q1).

5. **✅ Q5 — Role composition.** RULING: **Two separate axes.** `user_type_factory` returns a provider keyed only on user_type. `role_detector` (existing) handles role-aware behaviour. Call-sites compose: `user_type_factory::for_user($u)->dashboard_widgets($u, role_detector::detect($u))`. Single-responsibility, easier to test.

6. **✅ Q6 — Locale parity.** RULING: **All 5 locales blocking before merge.** ~30 new strings × 5 locales (en, hi, kn, mr, sw) = ~150 lang strings. CLAUDE.md §13 absolute rule. Translation owner: Nitin (or whoever owned the recent kn/mr/sw catch-up wave).

7. **✅ Q7 — Manager modelling.** RULING: **Capability of employee.** A manager is an employee with a `manager_userid` pointer pointing AT them (i.e. they are someone's supervisor). `employee_provider::dashboard_widgets()` queries for `mdl_user WHERE manager_userid = $USER->id` and adds the "Team certification stats" widget when result is non-empty. No separate user_type. v1 user_type count stays at 4.

---

## Acceptance criteria

- [x] Nitin reviewed all 7 open questions (2026-05-28).
- [x] ADR status flipped Proposed → Accepted (this commit).
- [x] Phase 0 (schema: user_type + 4 profile tables) landed via `local_sentientia_platform` upgrade savepoint 2026052801. **Fresh-install parity added 2026-06-11 (D-prod):** the 5 tables were upgrade-path-only, so clean installs (sandbox, customer-N, PHPUnit) lacked them and `user_type_factory` threw — now mirrored into `db/install.xml`; verified by a clean PHPUnit init creating all 5 `phpu_` tables.
- [x] Phase 1 (CLI classification) — `classify_existing_users.php` run on the 5.2 clone: 2,880 rows (682 consumer / 2,196 employee / 2 operator).
- [x] Phases 2 (providers C1.2), 4 (dashboard C1.4), 5 (sidebar/onboarding/leaderboard C1.5), 6 (signup C1.6) shipped — see task ledger.
- [ ] **Phase 3 (profile-page refactor, C1.3) — the remaining open phase.** `/user/profile.php` does not yet consume `profile_context()`; all 4 user-types rendering their correct profile shape with visual evidence is the exit gate. Deferred (M effort, > the ≤1-plugin-additive window of 2026-06-11); next D-prod engineering-tail slot.
- [ ] Locale parity: new profile-shape strings × 5 locales merged before the C1.3 call-site cuts over.

---

## Implementation order (Bucket C tracking)

Per the §4 stabilization backlog, this ADR's implementation falls into
Bucket C (Finish — large). Phased delivery:

| Phase | Deliverable | Effort | Bucket |
|-------|-------------|--------|--------|
| 0 | DB migration (`local_airpay_core/db/upgrade.php` adds 4 tables) | S | C1.0 |
| 1 | Classification CLI + dry-run CSV | S | C1.1 |
| 2 | Provider interface + 4 provider classes (with locale-parity strings) | M | C1.2 |
| 3 | Profile page consumes provider | M | C1.3 |
| 4 | Dashboard consumes provider | M | C1.4 |
| 5 | Sidebar + onboarding + leaderboard consume provider | M | C1.5 |
| 6 | Signup form gains user-type radio | S | C1.6 |

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
