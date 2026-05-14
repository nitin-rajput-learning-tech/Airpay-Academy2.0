# Airpay Academy — Configurability Architecture

**Date:** 2026-05-14
**Companion to:** `UI-UX-MANIFESTO.md`, `SURFACE-ROADMAP.md`, `PLATFORM-EVOLUTION-ROADMAP-2026-2027.md`
**Build artefact:** `local_airpay_core\feature_flags` (real PHP — shipped today)
**Admin surface:** `/local/airpay_core/admin/switchboard.php` (the Switchboard)

The user's mandate: *"AI and all major capabilities in the platform should be configurable by super admin, should be able to toggle on/off without breaking the platform."*

This document explains the architecture that delivers on that mandate.

---

## 1. The four kinds of configuration

Not everything in Airpay Academy needs the same kind of toggle. Treating them as one bucket leads to chaos. Four distinct kinds:

| Kind | What | Lives where | Who changes it |
|------|------|-------------|----------------|
| **Feature flag** | Bool — on/off | `local_airpay_feature_flags` table (new) | Super admin via Switchboard |
| **Tenant config** | Per-tenant variant (Airpay/Public/ZEEA can each have different values) | Same table, `tenant_id` column | Super admin (global) or tenant admin (own) |
| **Setting** | Typed value (string, int, JSON) — e.g. cadence days, threshold | Moodle's `config_plugins` table | Plugin's `settings.php` page (existing pattern) |
| **Capability** | Per-role permission — e.g. who can share a course | Moodle's `role_capabilities` table | Site admin via Permissions UI |

**This doc is about feature flags + tenant overrides.** Settings + Capabilities are existing Moodle patterns and stay as-is.

---

## 2. The Feature Flag contract

### 2.1 What a flag IS

A flag is **one boolean** with three properties:

```
key:         string  — namespaced, e.g. "ai.assistant.enabled"
default:     bool    — what the flag returns if nobody has configured it
description: string  — admin-facing copy explaining what toggling does
```

That's it. Three properties. No "rollout percentage", no "A/B variant", no "expiry date". This is intentional — **flags that grow features become invisible bugs**. We use simple flags and explicit lifecycle docs.

### 2.2 What a flag IS NOT

- ❌ Not an A/B test framework (use a real one — Statsig, GrowthBook — when we need that)
- ❌ Not a feature gate by user attribute (HRIS sync + capability handles that)
- ❌ Not a kill-switch with a 30-second SLA (use load-balancer + Moodle maintenance mode for that)
- ❌ Not a way to ship half-done features ("disabled by default in production") — features ship complete, flagged after to allow per-tenant variation

### 2.3 Naming convention

Three-level dotted keys: `category.feature.aspect`.

```
ai.assistant.enabled
ai.sentientia.enabled
ai.recommendations.enabled
engagement.gamification.enabled
engagement.gamification.showLeaderboard
engagement.whatsapp.enabled
commerce.cart.upi.enabled
identity.sso.enabled
learning.proctoring.aws.enabled
search.semantic.enabled
ux.commandPalette.enabled
obs.telemetry.enabled
```

Category prefixes (locked vocabulary):
- `ai.*` — anything AI-driven
- `engagement.*` — gamification, notifications, social
- `commerce.*` — cart, payments, marketplace
- `identity.*` — auth, SSO, HRIS, RBAC extensions
- `learning.*` — courses, exams, paths, proctoring, recompletion
- `search.*` — search backend toggles
- `obs.*` — observability, logging, telemetry
- `ux.*` — UI/UX experimental features

### 2.4 The runtime API

Three functions. That's the whole API surface.

```php
// Check if a flag is enabled — for the current tenant.
\local_airpay_core\feature_flags::is_enabled('ai.assistant.enabled'): bool

// Check explicitly for a tenant (super-admin code looking at any tenant).
\local_airpay_core\feature_flags::is_enabled_for_tenant('ai.assistant.enabled', $tenant_id): bool

// Get all flags + their current resolved values (for the Switchboard UI).
\local_airpay_core\feature_flags::all(): array  // [key => ['default' => bool, 'description' => str, 'resolved' => bool, 'overridden' => bool]]
```

That's it. No bespoke per-flag method. No closure-based gates. No "rollout context" object.

### 2.5 The resolution order

When code asks `is_enabled('foo.bar.enabled')`:

```
1. Look up the row in {local_airpay_feature_flags} for (key='foo.bar.enabled', tenant_id=<current>)
   → if found and is_enabled IN (0,1): return that value (tenant override wins)

2. Look up the row for (key='foo.bar.enabled', tenant_id=0)
   → if found and is_enabled IN (0,1): return that value (global override)

3. Look up the flag's REGISTERED default (from the registry — see §3)
   → return default

4. If no registered default either: WARN debug log + return false (fail-safe)
```

Tenant-specific > global override > registered default > false.

---

## 3. The Registry

Flags must be DECLARED before they're checkable. This prevents typos becoming silent regressions ("`is_enabled('ai.assitant.enabled')` — typo, falls through to false, AI assistant disabled in prod, learners scream").

### 3.1 Where flags are declared

Each plugin contributes a `db/feature_flags.php` file:

```php
<?php
// local/airpay_assistant/db/feature_flags.php
defined('MOODLE_INTERNAL') || die();

$flags = [
    'ai.assistant.enabled' => [
        'default' => true,
        'description' => 'Master switch for the AI chat assistant. When off, the assistant
                          drawer is hidden everywhere and ai_client::send_message() returns
                          a polite "assistant temporarily unavailable" response.',
    ],
    'ai.assistant.voice_input' => [
        'default' => false,  // Phase Β2 — not yet built
        'description' => 'Enables microphone voice input on the assistant drawer.
                          Requires browser mic permission.',
    ],
];
```

### 3.2 Discovery

`local_airpay_core\feature_flags::all()` walks every installed plugin, reads its `db/feature_flags.php`, merges into a single in-memory registry. Result is cached for 60s in `MUC` (Moodle Cache).

### 3.3 Validation

A CLI tool (`local/airpay_core/cli/flags_audit.php`) walks the codebase looking for `feature_flags::is_enabled('xxx')` calls and verifies every key has a matching declaration. CI gate fails the build on unknown keys.

---

## 4. The Switchboard UI

`/local/airpay_core/admin/switchboard.php`.

### 4.1 Layout

```
┌─ Top bar ────────────────────────────────────────────────────────┐
│  ⚙ Switchboard            🔍 Search flags…    [Apply] [Discard] │
├─ Filters ────────────────────────────────────────────────────────┤
│  Category: [All ▼]  Tenant: [Airpay ▼]  Status: [Any ▼]         │
├─ Flag list ──────────────────────────────────────────────────────┤
│                                                                   │
│  🤖  AI                                                           │
│  ──────────────────────────────────────────────────────────────── │
│  ai.assistant.enabled            ●━━━━━○   ON   (default)        │
│  AI chat assistant drawer. When off, the drawer is hidden        │
│  everywhere.                                                      │
│                                                                   │
│  ai.sentientia.enabled           ○━━━━━●   OFF  (tenant override)│
│  SOP→SCORM authoring pipeline. Currently disabled for Airpay     │
│  tenant only.                                                     │
│                                                                   │
│  💬  Engagement                                                   │
│  ...                                                              │
└───────────────────────────────────────────────────────────────────┘
```

### 4.2 Interactions

- **Toggle** — click the switch. Change shows as "modified" (orange dot). Banner appears: "3 flags modified — [Apply] [Discard]".
- **Apply** — opens a confirmation modal listing every change. Each change records an audit log entry (who, when, before→after, why-note optional).
- **Discard** — reverts all unsaved toggles.
- **Search** — Cmd+K opens search palette, type "what" → matches description text + key name.
- **Filter by tenant** — view + edit Airpay's flags, then switch to Public's. UI clearly indicates which tenant you're editing.
- **"Modified" badge** — shows when a flag's value differs from its registered default (so admin sees what's been customised).
- **Dependency warning** — if you try to disable `commerce.cart.enabled` while `commerce.cart.upi.enabled` is still on, modal warns: "UPI depends on cart. Disable UPI first, or both together."

### 4.3 What the Switchboard DOES NOT do

- ❌ Not a place to set numeric values (cadence days, thresholds) — that's plugin Settings pages
- ❌ Not a place to manage Capabilities (role perms) — that's the Roles UI
- ❌ Not editable by anyone except super admin
- ❌ Not callable from the public REST API

---

## 5. Graceful degradation contract

When a flag is turned off, the platform must **not break**. It should hide the feature, return a no-op, or fall back to a sensible alternative. This is the contract every plugin must honour.

### 5.1 Three degradation styles

| Style | When | Example |
|-------|------|---------|
| **Hide** | Pure UI feature with no side effects | AI assistant drawer — flag off → button doesn't render |
| **No-op** | Background job or event observer | `ai.recommendations.enabled = false` → `compute_recommendations()` returns empty array, callers see "No recommendations yet" empty state |
| **Fall back** | Feature is part of a chain | `engagement.whatsapp.enabled = false` → `notification_sender` skips WhatsApp dispatch, still sends email |

### 5.2 The code pattern

Every callsite uses one of three patterns:

**Pattern A — Guard at the top:**
```php
public function fire_streak_nudge(int $userid): void {
    if (!\local_airpay_core\feature_flags::is_enabled('engagement.streakReminders.enabled')) {
        return;  // silent no-op
    }
    // ... real implementation
}
```

**Pattern B — Skip in the loop:**
```php
foreach ($channels as $channel) {
    if ($channel === 'whatsapp'
            && !\local_airpay_core\feature_flags::is_enabled('engagement.whatsapp.enabled')) {
        continue;  // fall through to next channel
    }
    // ... dispatch
}
```

**Pattern C — Hide in the template:**
```mustache
{{#feature_enabled_ai_assistant}}
<button class="ai-drawer-toggle">…</button>
{{/feature_enabled_ai_assistant}}
```

The template helper `feature_enabled_<key>` is auto-registered for every flag (via a global Mustache helper in `theme/airpayux/lib.php`).

### 5.3 What "doesn't break" means

A flag toggle is **safe** if and only if:
1. No PHP fatal errors when the flag is off
2. No JavaScript console errors
3. No 500 responses on any page
4. No broken layouts (empty states render gracefully)
5. Toggling a flag DOES NOT require a Moodle upgrade or cache purge — flag changes apply within 60s (cache TTL)

CI gate: every flag's "off" state has at least one PHPUnit test that loads a representative page and asserts it 200s.

---

## 6. The flag inventory (target: 60+ flags by end of 2026)

### 6.1 AI category (Phase Β + ongoing)
| Key | Default | Owner plugin |
|-----|---------|--------------|
| `ai.assistant.enabled` | true | airpay_assistant |
| `ai.assistant.voice_input` | false | airpay_assistant |
| `ai.sentientia.enabled` | false | airpay_sentientia (new in Β1) |
| `ai.sentientia.parser` | true | airpay_sentientia |
| `ai.sentientia.narration` | true | airpay_sentientia |
| `ai.sentientia.slides` | true | airpay_sentientia |
| `ai.sentientia.voice` | true | airpay_sentientia |
| `ai.recommendations.enabled` | false | airpay_catalog |
| `ai.skillInference.enabled` | false | airpay_skills |
| `ai.essayGrading.enabled` | false | airpay_exams |
| `ai.summarizeLesson.enabled` | true | airpay_assistant |
| `ai.translation.enabled` | true | airpay_emails / airpay_pages |

### 6.2 Engagement category (Phase Α + Α')
| Key | Default | Owner |
|-----|---------|-------|
| `engagement.gamification.enabled` | true | airpay_gamification |
| `engagement.gamification.showLeaderboard` | true | airpay_gamification |
| `engagement.gamification.confetti` | true | airpay_gamification |
| `engagement.streakReminders.enabled` | true | airpay_gamification |
| `engagement.whatsapp.enabled` | false | airpay_whatsapp (Α1) |
| `engagement.sms.enabled` | false | airpay_whatsapp (Α1) |
| `engagement.teams.enabled` | false | airpay_integrations |
| `engagement.linkedinShare.enabled` | false | airpay_catalog |
| `engagement.cohortPresence.enabled` | false | airpay_catalog (Z2) |
| `engagement.peerCheers.enabled` | false | airpay_notifications |

### 6.3 Commerce category
| Key | Default | Owner |
|-----|---------|-------|
| `commerce.cart.enabled` | true | airpay_cart |
| `commerce.cart.upi.enabled` | true | airpay_cart |
| `commerce.cart.netbanking.enabled` | true | airpay_cart |
| `commerce.cart.emi.enabled` | false | airpay_cart |
| `commerce.cart.applePay.enabled` | false | airpay_cart |
| `commerce.cart.coupons.enabled` | false | airpay_cart |
| `commerce.crossTenantShare.enabled` | true | airpay_courses (Sprint C) |
| `commerce.crossTenantRequest.enabled` | true | airpay_courses (Sprint D) |
| `commerce.revenueShare.enabled` | false | airpay_courses (Phase Δ4) |
| `commerce.publicMarketplace.enabled` | false | airpay_marketplace (Phase F) |

### 6.4 Identity category
| Key | Default | Owner |
|-----|---------|-------|
| `identity.sso.azure.enabled` | false | airpay_integrations (Δ1) |
| `identity.sso.okta.enabled` | false | airpay_integrations (Δ1) |
| `identity.sso.google.enabled` | false | airpay_integrations |
| `identity.scim.enabled` | false | airpay_integrations |
| `identity.hris.keka.enabled` | true | airpay_integrations |
| `identity.hris.bamboo.enabled` | false | airpay_integrations (Δ2) |
| `identity.hris.personio.enabled` | false | airpay_integrations |
| `identity.softDelete.anonymize` | true | airpay_users (GDPR) |
| `identity.peopleDirectory.enabled` | false | airpay_users |

### 6.5 Learning category
| Key | Default | Owner |
|-----|---------|-------|
| `learning.proctoring.enabled` | true | airpay_proctoring |
| `learning.proctoring.aws.enabled` | true | airpay_proctoring |
| `learning.proctoring.faceMatch.enabled` | true | airpay_proctoring |
| `learning.compliance.enabled` | true | airpay_compliance_report |
| `learning.compliance.attestation.enabled` | false | airpay_compliance_report |
| `learning.compliance.predictiveRisk` | false | airpay_compliance_report (Phase B) |
| `learning.skillGraph.enabled` | false | airpay_skills (Γ1) |
| `learning.adaptivePaths.enabled` | false | airpay_learningpath (Γ3) |
| `learning.xapi.enabled` | false | airpay_core (Ε3) |
| `learning.recompletion.enabled` | true | airpay_recompletion |

### 6.6 Search category
| Key | Default | Owner |
|-----|---------|-------|
| `search.elasticsearch.enabled` | false | airpay_integrations (Ε2) |
| `search.semantic.enabled` | false | airpay_integrations (Ε2) |
| `search.commandPalette.enabled` | true | theme/airpayux |

### 6.7 Observability category
| Key | Default | Owner |
|-----|---------|-------|
| `obs.telemetry.enabled` | false | airpay_core (Ε1) |
| `obs.apm.datadog.enabled` | false | airpay_core (Ε1) |
| `obs.structuredLogging.enabled` | true | airpay_core |

### 6.8 UX category
| Key | Default | Owner |
|-----|---------|-------|
| `ux.darkMode.enabled` | true | theme/airpayux |
| `ux.commandPalette.enabled` | true | theme/airpayux |
| `ux.aiCopilot.author.enabled` | false | airpay_courses (Β2) |
| `ux.skeletonLoaders.enabled` | true | theme/airpayux |
| `ux.confettiOnCompletion.enabled` | true | airpay_gamification |
| `ux.onboardingTour.enabled` | true | theme/airpayux |

**Total at end of inventory: 60 flags.** This is the target by end of 2026. We ship them incrementally — every phase adds 5-10 flags.

---

## 7. The audit trail

Every flag change is an event. Schema:

```
{local_airpay_feature_flag_audit}
  id            int PK
  flag_key      varchar(128)
  tenant_id     int (0 for global)
  old_value     tinyint nullable (NULL = was using default)
  new_value     tinyint nullable
  changed_by    userid
  reason        varchar(255) nullable (admin-entered note)
  timecreated   bigint
```

The Switchboard surfaces this as the "Flag history" page (`4.6` in the Surface Roadmap). Audit retention matches GDPR + SOC2 expectations (7 years for compliance-affecting flags).

---

## 8. The first 5 flags — what we wire today

Part D ships:
1. **The infrastructure** — DB table, class, registry loader, cache layer
2. **The Switchboard** — admin page + form + audit log entry per change
3. **5 real flags wired to existing features** as proof:
   - `ai.assistant.enabled` → hides the AI assistant drawer
   - `engagement.gamification.enabled` → hides gamification widgets
   - `engagement.gamification.confetti` → suppresses completion confetti
   - `commerce.crossTenantShare.enabled` → hides the Share button on course list
   - `commerce.crossTenantRequest.enabled` → hides Browse Airpay link in sidebar

These five demonstrate every degradation pattern (hide, no-op, fall back) and prove the architecture. Phase Α phases add more flags as features land.

---

## 9. What CAN'T be flagged

For honesty: some things must not be flag-gated, because gating them breaks the platform.

**Never flagged:**
- Authentication (login, session, password reset)
- Core data model migrations
- Database integrity (FK constraints)
- Audit logging itself (Phase 8.1 hardening; if you can flag-off audit, audit is useless)
- Tenant isolation queries (`tenant::sql_filter()` etc — those are security-critical, not optional)
- Capability checks (use Roles UI, not flags)

These are checked in the `flags_audit.php` CI tool — adding a flag to one of these surfaces fails the build.

---

## 10. The 30-second pitch for the Switchboard

A non-technical Head of L&D walks up to the platform and wants to:
- Try AI authoring on Public tenant only without breaking Airpay
- Disable WhatsApp during a vendor outage
- Turn off the leaderboard during a compliance audit
- Roll out new features tenant by tenant

The Switchboard is the one screen where they do all of that, without filing a ticket, without redeploying code, with a full audit trail of every change. That's the product.
