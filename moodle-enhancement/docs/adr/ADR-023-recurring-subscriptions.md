# ADR-023 — Recurring subscriptions for course access (Airpay v4 mandate + Moodle recurring enrolment)

**Status:** PROPOSED — design only, gated on (a) a **product decision** (subscription model) and
(b) an **Airpay sandbox** + the payment-verification fix (`fix/airpay-payment-verification`)
merged first. **No code is shipped by this ADR.**
**Date:** 2026-06-02 · **Decision-makers:** Nitin Rajput · **Implementer:** TBD (post-decision)
**Relates to:** the 2026-06-02 payment-verification security fix (the subscription callback inherits
its verification lesson), ADR-018 (independence roadmap), the mandatory feature-flag rule
(CLAUDE.md §5), the BizLMS payment migration (`paygw_airpay`).

> Triggered by the question *"can the Public tenant user be given a subscription?"*
> **Answer today: no.** This ADR records how to add the capability.

## Context — the capability gap

The Public tenant already **sells courses** (180 fee courses, 61 priced) via Moodle's payment
subsystem: `enrol_fee` + `paygw_airpay`. That path is **one-time**: pay once → enrolled forever.
Moodle core has **no recurring/subscription concept** in the payment subsystem.

| Layer | Recurring support today |
|-------|-------------------------|
| Airpay v4 (provider) | **Yes** — mandate-based recurring: `sb_*` request params (`sb_isrecurring`, `sb_period`, `sb_frequency`, `sb_amount`, `sb_maxamount`, `sb_recurringcount`, `sb_retryattempts`, `sb_nextrundate`) + the `/v4/payments/subscription/*` APIs (manage, adhoc-charge, status-check, skip-cycle, update-amount) + a `subscription-callback`. |
| `paygw_airpay` (our gateway) | **No** — one-time only; sends no `sb_*` params. |
| Moodle enrolment | **No** — `enrol_fee` is a single charge; no renew/suspend-on-non-payment lifecycle. |

So enabling subscriptions is **net-new on two layers** (gateway + enrolment) plus a callback. It is
**not a config toggle.**

## THE decision that drives everything: the subscription *model*

This must be answered before implementation, because it changes the data model and enrolment logic:

| Option | What the learner buys | Moodle mapping | Notes |
|--------|----------------------|----------------|-------|
| **A. All-access** (SaaS-style) | One subscription → access to **all** (or a catalogue of) courses while active | Cohort/category-wide enrolment driven by subscription status | The *typical* LMS subscription. Highest value, biggest enrolment-plumbing change. |
| **B. Program / category bundle** | Subscription → access to one **program/category** | Category-scoped enrolment | Middle ground. |
| **C. Per-course recurring** | Subscription → keep access to **one course** while paying | Minimal extension of the current per-course `enrol_fee` paradigm | Least-assuming; unusual UX (you rarely "subscribe" to a single course). |

**Recommendation:** **A (all-access)** is the standard LMS subscription and the likely product
intent, but it is the largest build.

> **DECISION (Nitin, 2026-06-02): all of the above.** `enrol_sentientiasub` ships **one**
> plugin with a per-instance **`scope`** setting — `allaccess` | `category` | `course` — so the
> admin chooses the model per subscription product. The data model therefore carries
> `scope` + a nullable `scopeid` (categoryid for `category`, courseid for `course`, null for
> `allaccess`); the enrolment-grant step branches on `scope` (catalogue-wide cohort grant /
> category-scoped grant / single-course grant). This unifies A/B/C rather than forking the
> schema — the most flexible option and the one to build.

## Proposed architecture

### Moodle side — a new enrolment plugin

Moodle's idiomatic way to have **payment-driven, revocable** enrolment with a status is an **enrol
plugin** (it owns `enrol`/`user_enrolments` rows, supports `ENROL_USER_SUSPENDED`, and gets a
`cron`/scheduled hook). Proposed: **`enrol_sentientiasub`** (product-named from day one, per ADR-022's
direction — avoids a future rename).

Rejected alternative: a `local_*` plugin orchestrating `enrol_manual`. It works but reimplements
enrolment-lifecycle state that the enrol API already models, and fights Moodle's enrolment UI.

### Airpay side — extend `paygw_airpay`

Add a recurring path to the gateway: send `sb_*` params on checkout to create a **mandate**; handle
the **`subscription-callback`** (Airpay → server, once per billing cycle); call **status-check** /
**Verify** to confirm each cycle server-side before extending access.

### Data model (sketch — `enrol_sentientiasub` instance config + a subscription table)

```
{enrol_sentientiasub_subscription}
  id, enrolid (FK enrol), userid (FK user),
  ap_mandate_id, ap_subscription_id,           -- Airpay handles
  status,                                       -- active|suspended|cancelled|pending
  period, amount, currency,                     -- billing terms (from enrol instance)
  next_charge_ts, last_charge_ts,
  started_ts, cancelled_ts,
  costcenterid,                                 -- tenant scoping (Public=77 first)
  timecreated, timemodified
```

### Lifecycle

```
checkout → gateway creates mandate (sb_* params)         [sandbox-gated]
        → customer authorises mandate at Airpay
        → subscription-callback: mandate active           [verify like the payment fix]
        → activate enrolment (ENROL_USER_ACTIVE)
  each cycle:
        → Airpay auto-charges → subscription-callback      [verify + status-check]
        → record cycle, advance next_charge_ts
        → on charge failure (after sb_retryattempts):
              suspend enrolment (ENROL_USER_SUSPENDED) [+ grace period?]
        → on cancel (learner or admin):
              cancel mandate (manage-subscription) → revoke enrolment
  scheduled task (daily): reconcile via status-check; suspend lapsed, reactivate recovered
```

## Security — the callback inherits the payment fix

The `subscription-callback` is the **same class of public callback** as `process.php`. The 2026-06-02
bypass (guard commented out → forged success enrolled) must **not** be repeated. Therefore the callback
MUST: verify the response signature (reuse `airpay_helper::verify_secure_hash`-style logic), **and**
confirm each cycle server-side via the Verify/status-check API before granting/extending access. This
is why `fix/airpay-payment-verification` (and its Verify-API follow-up) is a **prerequisite**, not a
parallel track.

## Feature flag (mandatory)

Ships behind `sentientia_subscriptions` (default **OFF**), per-tenant override (Public/77 first). Default
behaviour is unchanged: the one-time `enrol_fee` path everyone sees today is untouched until the flag flips.

## Build increments

1. **This ADR + product decision** (model A/B/C, period, pricing, failure policy, tenants).
2. `enrol_sentientiasub` skeleton + data model + feature flag (OFF) + capability + lang — **no payment**, unit-tested. *(Safe, no sandbox.)*
3. Gateway mandate creation (`sb_*`) — **sandbox**.
4. `subscription-callback` handler + verification (reuse the payment-fix verifier + Verify/status-check) — **sandbox**.
5. Enrolment grant/suspend/revoke + daily reconciliation task.
6. Admin config (which courses/catalogue are subscription-eligible, period, amount) + learner cancel UX + visual evidence.
7. Public-tenant pilot behind the flag.

## Gates (cannot proceed past increment 2 without these)

- **Product decision:** the model (A/B/C), billing period (monthly/annual/configurable), pricing
  (flat all-access vs per-item), charge-failure policy (immediate suspend vs grace period), and target
  tenants (Public only, or all three).
- **Airpay sandbox:** mandate-create + subscription-callback + status-check round-trips (the same
  sandbox the payment fix needs). Recurring/mandate flows **cannot** be validated without it.
- **Payment fix merged:** the callback verification depends on it.

## Open decisions for Nitin

1. ~~Subscription model~~ — **RESOLVED 2026-06-02: all three via a per-instance `scope` setting** (see Decision above).
2. Billing period + pricing model?
3. Charge-failure: immediate suspend, or N-day grace?
4. Tenants: Public (77) only first, or all?
5. Build now to increment 2 (the safe, no-sandbox skeleton behind the OFF flag), or hold the whole feature until the sandbox + payment fix are validated?
