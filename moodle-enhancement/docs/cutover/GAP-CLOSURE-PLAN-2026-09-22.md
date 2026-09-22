> **Provenance.** Produced 2026-09-22 by a seven-way parallel audit of the code (not of the docs),
> one auditor per confidence gap, each returning an executable task list. It corrects several
> long-carried false gaps — those are listed in §6 so we stop planning around them. Execution
> status is tracked in `PROJECT-STATE.md`; Wave 1 items W1-01 to W1-06 and the CI enforcement
> change landed the same day.

# Sentientia LMS — Gap-Closure Plan
**Date:** 2026-09-22 · **Branch:** `claude/gap-integration` · **Owner:** Nitin Rajput
**Status of platform:** pre-production. UAT at `academy2.airpay.ninja` runs 13 test personas on an imported backup. **No real user has ever used Sentientia.**

---

## 0. Read this first — the branch has 56 uncommitted files

Before any planning: `git status` shows **56 modified files and one untracked file** on this branch, written by a concurrent session. This is not a side note, it reshapes the whole plan:

- The five cross-tenant `LIKE '/N%'` leaks the audit flagged as *live* are **already fixed** in the working tree. All five now route through a new canonical helper, `\local_sentientia_platform\tenant::path_descendant_filter()` (`moodle-enhancement/local/sentientia_platform/classes/tenant.php:295-317`).
- That helper has **8 dedicated tests**, including adjacent-prefix exclusion and a real-database boundary test — the audit's "the canonical tenant helper has zero tests" finding is closed.
- A machine guard exists: `tools/check-path-boundary.php` scans **2,655 files clean**, wired into pre-commit as CHECK 18 and into CI as job `path-boundary-check`.

**None of it is committed. CI has never run it.** And the scanner both new gates invoke is **untracked** — so committing the other 56 files without it makes the CI job fail on every push with "Could not open input file". That is task W1-01 and it is the cheapest, highest-consequence action on this list.

---

## 1. The honest frame

Three classes of gap. Only the first two are ours.

**Class A — we can close alone, offline (Wave 1).**
Engineering controls these completely: commit what is written, flip advisory gates to blocking, fix one live compliance bug, close one live payment fail-open, write the missing tests. Twelve tasks. No key, no budget, no tunnel, no human tester. This is the bulk of the remaining risk and it is all executable today.

**Class B — needs exactly one tunnel or one browser window (Wave 2).**
Deploys to UAT and on-screen confirmation. Constrained by one hard operational fact from project memory: **VPN-on (SSH/on-box) and VPN-off (browser/HTTPS) are mutually exclusive**, so these must be batched into separate windows rather than interleaved.

**Class C — irreducibly external. We cannot close these, and I will not pretend otherwise (Wave 3).**
Each needs one named input from one named owner:
- An **ANTHROPIC_API_KEY plus a signed Addendum-A cap figure** before one rupee of AI spend can be metered end-to-end.
- A **reCAPTCHA v2 key pair** from Airpay IT.
- A **live airpay.academy backup, an ALB IP allowlist, and a UAT resize** from Cloud.in before DR can be proven at real volume.
- **~INR 2L of load-generation infrastructure** before any 25k-user claim is measurable.
- An **independent penetration test** by a third-party vendor.
- **A real user having actually used the platform.**
- **Certification calendar time** (ISO 27001 / SOC 2 Type II observation windows are measured in months and cannot be compressed by engineering effort).

The last three are not tasks. They are facts about the world. No amount of Wave 1 and Wave 2 work converts them.

---

## 2. Wave 1 — closable now, offline (blocker: none)

Execute in this order. W1-01 through W1-04 are the ones I would not sleep on.

| # | Task | File(s) | What it must prove | Effort |
|---|------|---------|--------------------|--------|
| **W1-01** | **Track the path-boundary scanner, then commit all 56 files** | `git add tools/check-path-boundary.php` + the 56 modified | CI job `path-boundary-check` (`ci.yml:298-309`) runs `php tools/check-path-boundary.php` with **no** `continue-on-error`. Untracked scanner ⇒ every push fails. Prove: clean clone + `php tools/check-path-boundary.php` exits 0 on 2,655 files. | S |
| **W1-02** | **Fix the silent right-to-erasure skip** | `moodle-enhancement/local/sentientia_privacy/classes/privacy_manager.php:178-179` | ME tree (the tree UAT runs) deletes from `local_airpay_user_skills` — **a table that does not exist**. `table_exists()` returns false, the delete is skipped, and the request is then marked `completed`. The top-level tree is already correct. New `tests/erasure_test.php`: seed a user in `local_sentientia_user_skills` + `local_sentientia_user_skill_hist`, run erasure, assert 0 rows in **both** and status `completed`. Add an assertion that every table name passed to `delete_records()` exists in the live schema. | S |
| **W1-03** | **Make the cart payment webhook fail closed** | `moodle-enhancement/local/sentientia_cart/classes/gateway/airpay_gateway.php:73-81`; `callback.php` | `verify_callback()` reads `airpay_secret` and **never checks it is non-empty**. Default is `''` (`settings.php`). With an empty secret the checksum is `sha256(sorted pairs|secret=)` — fully computable, so a self-registered learner can self-sign a callback and get a free enrolment plus a genuine invoice. Add non-empty guard on secret and merchant id. Separately: `callback.php` picks the gateway **from the payload**, not from `$cart->gateway`, so a forged airpay callback settles a `manual` NET-30 order — bind it to the order. | S |
| **W1-04** | **First tests for `local_sentientia_cart`** | NEW `moodle-enhancement/local/sentientia_cart/tests/callback_test.php` | The plugin has **no `tests/` directory at all**. The dead legacy gateway has 5 tests; the live money path has 0. Must assert: empty-secret ⇒ `verify_callback()` false (locks W1-03); valid signature accepted, one-byte mutation rejected; amount mismatch >0.01 never reaches `mark_paid`; currency mismatch rejected; `manual_gateway::verify_callback()` always false; `mark_paid()` idempotent (one ledger row, one enrolment on double call). | M |
| **W1-05** | **Flip the PHPUnit isolation suite to blocking** | `.github/workflows/ci.yml:620` | `phpunit-52` carries `continue-on-error: true`, so ~159 isolation tests are advisory and a leak regression merges green. Either remove it, or add a second job `tenant-isolation-gate` with no `continue-on-error` running `--group tenant_isolation`, and tag the isolation suites. **Note:** the audit's claim that no PHPUnit job exists in CI is false — `phpunit-52` is at `ci.yml:605`. | S |
| **W1-06** | **Fix the blind mutation test** | `moodle-enhancement/local/sentientia_gamification/tests/badge_manager_test.php:143,146` | Still pairs `/1` with `/77`. Because `'/1%'` never matched `'/77'`, this test passed while the bug 50 lines away in the code it covers was live. Change `/77` → `/177`. That one-character change must fail on the pre-fix code and pass on the fixed code — the only proof the test can now see the class of bug it exists to catch. | S |
| **W1-07** | **Give analytics a real capability layer** | NEW `moodle-enhancement/local/sentientia_analytics/db/access.php`; `index.php:14`, `drilldown.php:17`, `export.php:15` | `db/` contains `caches/feature_flags/tasks/upgrade` and **no `access.php`**. All three pages gate on `local/courses:manage` — a **retired** capability name (the live one is `local/sentientia_courses:manage`, `sentientia_courses/db/access.php:29`). `has_capability()` on an undefined capability logs a debugging notice and returns false, so the Analytics Dashboard is effectively siteadmin-only. Declare `:view`/`:viewallorgs`, back-fill to roles holding the renamed capability, delete the hardcoded `roleid = 9` fallback. Test: siteadmin allowed, `:view` role allowed, student denied, **no debugging notice raised**. | M |
| **W1-08** | **Add the 7 missing AI no-spend guards** | `sentientia_{aiquiz,skillsai,translate,recommendations}/classes/anthropic_client.php`, `authoring/classes/tts_client.php`, `assistant/classes/{ai_client,agent/agent_client}.php` | Verified: all 7 have **zero** `PHPUNIT_TEST`/`BEHAT` occurrences; `sentientia_ai/classes/gateway.php` has the guard. All 7 use raw PHP cURL, bypassing Moodle's `\curl` phpunit host blocking — the exact defect the gateway's own comment records as "found the hard way". One test per plugin: set api_key, call `call_live()`, assert `error === 'live_blocked_in_tests'`. | S |
| **W1-09** | **Contain AI spend to the gateway** | the 6 consumers' standalone fallbacks; NEW `sentientia_ai/tests/spend_containment_test.php` | `gateway.enabled` defaults **OFF**, so every consumer falls through to its own `call_live()` — no ledger row, no daily token cap, no monthly USD cap. The Addendum-A ceiling exists **only inside the gateway**. Make each fallback return its mock when the gateway plugin is present but the flag is off. Test: gateway installed, flag off, plugin `live_api` on, plugin key set ⇒ mode `mock`, no HTTP attempted. | M |
| **W1-10** | **Fix the structured_logger component prefix** | `moodle-enhancement/local/sentientia_platform/classes/structured_logger.php:94` | Verified still `'component' => 'local_airpay_' . $plugin`, contradicting its own docblock. Change to `local_sentientia_`; assert `json_decode(...)->component === 'local_sentientia_cart'` in the existing test, which today asserts nothing about this field. | S |
| **W1-11** | **Fix the dead `local_airpay_core` namespace** | `sentientia_whatsapp/cli/run_whatsapp_e2e.php:124,125,127,129,203,205`; `sentientia_pwa/cli/run_push_e2e.php:158,159,267` | `\local_airpay_core\feature_flags` **exists nowhere in the repo**; the real class is `\local_sentientia_platform\feature_flags`. Both E2E harnesses fatal on run. **Root cause: `CLAUDE.md` §5 still documents the dead namespace as the canonical feature-flag pattern** — fix the doc in the same change or this recurs. | S |
| **W1-12** | **Extract + test the theme dashboard tenant scope** | `theme/sentientia/layout/dashboard.php:255-282`; NEW `theme/sentientia/classes/local/tenant_scope.php` + `tests/` | The closure is **correct** (`/%`-bounded with exact-root companion) but hand-rolled, not routed through the new shared helper, and untestable inside a 1,185-line layout. No `classes/local/` and no `tests/` directory exist. Extract, delegate to `tenant::path_descendant_filter()`, keep the four `tu/ju/tc/jc` call sites identical so ~30 widget queries are untouched. Test: `/1` excludes `/177`, `/100`, `/10`; includes `/1` and `/1/183/45`; siteadmin ⇒ `['', []]`; two fragments in one query don't collide on param names. Also fix the unscoped `count_records('local_onlineexams')` at `:522`. | M |

---

## 3. Wave 2 — needs a tunnel or a browser (blockers: vpn / browser)

Batched so each window is used once. **Do not interleave — VPN-on and VPN-off are mutually exclusive.**

### Window A — VPN ON (SSH tunnel), one session

| # | Task | What it must prove | Effort |
|---|------|--------------------|--------|
| **W2-01** | Deploy W1-02/03/07 to UAT via `tools/uat/deploy_to_uat.sh` (pass the ME tree explicitly — it aborts on tree drift, exit 3) | Erasure fix, cart fail-closed and analytics capability are live on the instance a prospect is shown | S |
| **W2-02** | On-box erasure verification: run a deletion request against a seeded persona holding skills rows; read-only SQL count | `local_sentientia_user_skills` **and** `local_sentientia_user_skill_hist` are 0 for that userid **and** the request reads `completed` | S |
| **W2-03** | `php admin/cli/upgrade.php --non-interactive` | No version-downgrade error (see the `sentientia_org` inversion in §5) | S |
| **W2-04** | Retrieve `/usr/local/bin/sentientia-logscan.sh` (root cron 06:15) into `moodle-enhancement/tools/ops/` | Our only live monitoring artifact survives a box rebuild — it is currently unversioned and on one box | S |

### Window B — VPN OFF (Chrome against UAT), one session

| # | Task | What it must prove | Effort |
|---|------|--------------------|--------|
| **W2-05** | Capture the `sentientia_users` KPI tiles, gamification leaderboard, admin dashboard KPI row into `docs/visual-evidence/2026-09-__/` | The tiles now agree with the row counts beneath them. This is the exact discrepancy two prior persona checks missed — the table was already correctly bounded, the tiles leaked | S |
| **W2-06** | Confirm the plugin-overview page shows zero "Airpay" product names | White-label claim holds on screen (29 branded titles remain in the ME tree vs 9 in top-level) | S |
| **W2-07** | Run the non-mutating gates against UAT: `render-smoke` + `a11y-smoke` only | No gate has **ever** targeted UAT — a grep of `tests/` for `academy2.airpay.ninja` returns nothing. Run only these two: other specs mutate shared persona state | S |
| **W2-08** | Qualys SSL Labs scan | Settles the one inconclusive audit finding. The 2026-09-03 probe failed for a **client-side** reason (local OpenSSL 3.5.5 refuses to offer TLS 1.0/1.1) — that was never evidence about the server. Set "do not show on the boards" | S |

---

## 4. Wave 3 — one external input each (blocker: external)

For each: the input, the owner, and the artifact we will have finished so the input is the **only** remaining step.

| # | Input needed | Owner | What we will have ready | Then |
|---|---|---|---|---|
| **W3-01** | **ANTHROPIC_API_KEY** on the UAT box + **Addendum-A cap figure signed** | Nitin → C-suite (budgets still pending approval per ADR-028 Phase 2.3) | W1-08 (no-spend guards), W1-09 (spend containment), envelope contract tests against recorded fixtures, ledger + cap enforcement | One metered canary generation, then re-run with the cap lowered below spend-to-date to prove the ceiling **denies in production** |
| **W3-02** | **reCAPTCHA v2 site+secret pair** for `academy2.airpay.ninja`, delivered via ops vault (**not email** — DB credentials were already emailed in plaintext on 2026-08-20) | Airpay IT (`PENDING-TASK-PLAN` 3.2) | Integration is **already complete and keyless by design** (`signup_form.php:119-122,134-136,153-165`) + a new per-IP signup throttle | Paste keys into Site security settings; widget appears with **zero deploys** |
| **W3-03** | **Live airpay.academy DB dump + filedir archive**; **ALB egress IP allowlist**; **UAT resize** to t3a.medium+/db.t3.medium | Cloud.in + Nitin's D-2 sizing decision | Committed `dr_restore_drill.sh`, value-level parity checks (checksums, cross-foot, filedir contenthash), `fingerprint.sql` + diff harness, `BACKUP-AND-RECOVERY-PLAN.md` with RPO/RTO as explicit TBD placeholders | Run the drill at real volume; fill in the measured RTO and Cloud.in-supplied RPO; countersign the attestation |
| **W3-04** | **~INR 2L load-generation infrastructure** | MD/Founder (security + certification envelope, undecided) | Repaired + **authenticated** k6 harness, 25k dataset seeded locally via core `tool_generator` (free), analytics cold-cache timings, results directory with the "no number unless a file produced it" rule | Execute Tier 1; publish the measured knee **even if below 25k** |
| **W3-05** | **CI artifact download** (no `gh` CLI and no GitHub token on this box) | Nitin fetches the artifact zip, or provisions a token | Stale/corrupt visual baselines deleted, masks added for non-deterministic dashboard regions, persona wiring landed | Commit Linux-generated baselines; leave `PLAYWRIGHT_VISUAL=1` on |
| **W3-06** | **Independent penetration test** | Third-party vendor, via Nitin | Every finding from our own review closed, the self-audit check API green, security posture doc accurate | External attestation — the only kind a CISO accepts |
| **W3-07** | **One real user** | Irreducible | A platform that works | Nothing we can do accelerates this |
| **W3-08** | **Certification observation window** (ISO 27001 / SOC 2 Type II) | Auditor + calendar | Controls implemented and evidenced | Months of elapsed time. Not compressible |

---

## 5. Confidence ledger

| Gap area | Today | After W1 | After W2 | After W3 | What would still be missing |
|---|---|---|---|---|---|
| Multi-tenant isolation | medium | high | high | high | Nothing engineering controls. Uniform boundary coverage is 11 of 44 plugins with a `tests/` dir today; the gate makes the covered set enforceable |
| Payment / commerce security | **low** | high | high | high | An independent pen test of the webhook |
| Privacy / DPDP erasure | **low** | high | high | high | A DPIA reviewed by counsel |
| AI spend governance | low | medium | medium | high | Nothing — W3-01 closes it with a metered canary |
| External integrations (WhatsApp, M365, marketplace) | low | medium | medium | medium | Live vendor round-trips; WhatsApp and M365 have **no live code at all** today, only builders + fixtures |
| CI enforcement | **low** | high | high | high | Nothing. This is pure engineering |
| Render / a11y / visual gates | low | medium | medium | high | Visual baselines need the CI artifact (W3-05) |
| Observability | low | medium | medium | high | A SIEM on the customer side to receive the stream |
| Backup / DR | low | medium | medium | high | Real-volume proof (W3-03); today's evidence is one un-scripted 1 MB drill, RTO 44 s, no RPO, no integrity check |
| Scale | **low** | low | medium | medium | **Stays medium even after W3-04** — a measured Tier-1 knee is not the same as sustained production load |
| Availability / SLO | low | low | low | medium | No uptime data exists. 99.5% is a target, not a measurement |
| De-brand / white-label | medium | high | high | high | Nothing |

Two rows deliberately do not reach high. Scale caps at medium because one rented test run is not production behaviour. Availability caps at medium because an SLO without a history of measurement is a promise.

---

## 6. Corrections to the record — stop carrying these as gaps

Each verified against code this session. Carrying false gaps costs real planning time: four audits have now been mis-aimed by stale claims.

1. **C1 payment-hash fix is MERGED, not pending.** `git merge-base --is-ancestor fix/airpay-payment-verification production` returns true (merge `8630ab4b2`, 2026-06-03). The fail-closed verifier is live and has had 5 passing tests since June. **`PENDING-TASK-PLAN` §3.20 is an un-actionable blocker on the cutover path** — it asks us to do something done 3½ months ago. Release it.

2. **CI *does* have a PHPUnit job.** `phpunit-52` at `ci.yml:605`. The claim that none exists is false. The real problem is one line: `continue-on-error: true` at `:620`.

3. **The five "live" cross-tenant leaks are already fixed** — in the working tree, uncommitted. A canonical helper and a machine guard now exist. The gap is not the code, it is that **nothing is committed**.

4. **The canonical tenant helper is no longer untested.** `tenant::path_descendant_filter()` has 8 dedicated tests including digit-prefix-sibling exclusion and a real-DB assertion.

5. **A path-boundary CI gate already exists** (`ci.yml:298`) and pre-commit is now 18 checks, not 17.

6. **`local/courses:manage` is a *retired* name, not a typo.** The live capability is `local/sentientia_courses:manage`. Analytics checks the old name, which no `access.php` defines — so the finding stands, but the fix is a rename plus a real `access.php`, not inventing a capability.

7. **Isolation tests are adversarial, not confirmatory.** They seed foreign-tenant rows at adjacent prefixes and assert exclusion, with the historical bug quoted in the docblocks. The one genuine exception is `badge_manager_test.php` (W1-06).

8. **The theme dashboard closure is correct.** It is `/%`-bounded with an exact-root companion. The gap is zero test coverage, not a defect.

9. **21 visual baselines are committed**, not zero. Stop reporting "baselines not seeded"; report "stale, partly corrupt (three byte-identical duplicate pairs), and never executed — `PLAYWRIGHT_VISUAL` is set in no CI run".

10. **Gate 1 executes 1 persona on CI, not 5.** Only `PLAYWRIGHT_BASE_URL`/`ADMIN_USER`/`ADMIN_PASS` are wired (`ci.yml:576-578`); the other four `test.skip()` silently. The CI gate also sets **no theme**, so it green-lights vanilla Boost.

11. **WhatsApp and M365 have no live code** — the state card describing "mock + live" clients is wrong. Both are commented-out provider blocks falling through to a mock. There is nothing to contract-test; the task is to *write* the builder and parser.

12. **`SCALE-LOAD-TEST-PLAN.md` contains invented benchmark numbers under a sign-off block**, and they have already escaped into `TRUST-TRACK-README.md` as if measured. This is the single highest-reputational-risk item in the corpus and it costs under an hour to defang. An RFP assembled from that pack today would ship fabricated figures under a signature.

13. **`CLAUDE.md` §5 documents a dead namespace** (`\local_airpay_core\feature_flags`) as the canonical pattern, which is what produced the two fatal E2E harnesses. §2/§7 also still name `airpayux` as the active theme; the shipped theme is `theme/sentientia`.

14. **`ISO-27001-2022-READINESS.md` numbers its controls in the 2013 scheme** (A.9.1.2, A.11.3.1, A.11.4.1). The 2022 equivalents are A.8.24, A.8.13, A.8.15. An assessor will flag the numbering before reading the content.

---

## 7. What "high confidence" will and will not mean

**It will mean:** every cross-tenant query routes through one tested helper, and a leak regression cannot merge green. The payment webhook cannot settle an order without a genuine secret-signed callback, and that is locked by tests rather than asserted in a document. A DPDP erasure request actually erases. AI spend cannot leave the ledger or exceed the signed cap. A restore is a committed script that emits a dated attestation with byte-level integrity proof. Every recurring bug class we have hit twice is now a mechanical gate rather than a habit of care.

**It will not mean the platform is secure.** It will mean *we have not been able to break it with our own tests*. Those are different claims, and only the second one is ours to make. Specifically:

- **No amount of our own testing substitutes for an independent penetration test.** Every finding in this plan was found by us, reviewing our own code, against our own threat model. The cart fail-open sat in the tree through a full security audit that never opened that plugin. That is exactly the failure mode an external tester exists to catch, and the honest read is that there are others we have not found.
- **No test corpus substitutes for a real user.** 13 personas on an imported backup exercise the paths we thought to exercise. Real users do things we did not model. Until someone outside this project has used Sentientia to complete real work, "production-ready" is a prediction, not an observation.
- **Scale stays medium.** One funded Tier-1 run gives us a measured knee on a defined configuration. It does not tell us what happens on day 40 of sustained load with a year of accumulated log volume.
- **Availability cannot be claimed at all** until a probe has been running long enough to produce a series. 99.5% is currently a target with zero instrumentation behind it.
- **Certification is calendar-bound.** ISO 27001 and SOC 2 Type II require observation windows. Implementing every control today does not shorten them.

The correct thing to tell the MD/Founder, CTO and CHRO after Waves 1–3: *engineering has closed everything engineering can close, each remaining item is one named external step with one named owner, and the platform has still never served a real user or been tested by anyone who does not work on it.* That is a strong position. It is not the same as "done", and the difference is the part worth being precise about.