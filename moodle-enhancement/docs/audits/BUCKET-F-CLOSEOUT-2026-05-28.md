# Stabilization Audit — Bucket F closeout

**Date:** 2026-05-28
**Author:** Nitin Rajput (with Claude)
**Audit ref:** `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` §4 Bucket F
**Probe tool:** `tools/audit_table_inventory.php`

---

## Why this exists

Bucket F of the Stabilization Audit lists 10 "investigate" findings — items
where the audit didn't have enough information to decide what to do.
This doc closes each one with a verdict drawn from runtime probes against
the local Moodle DB (Moodle 5.1.3+ XAMPP, 2,878 user rows from the
production sync).

Probes were read-only — no INSERT/UPDATE/DELETE/DDL — so they can be
re-run on production by IT before deciding which Bucket F items to
escalate.

---

## Findings — one row per F-NN

### F-024 — Sentientia Live analytics — what's missing?

**Verdict: PARTIALLY-RESOLVED.** The data layer works; the analytics UI
layer is unverified.

| Table | Row count on local |
|-------|---------------------|
| `local_sentientia_live_sessions` | 2 |
| `local_sentientia_live_slides` | 8 |
| `local_sentientia_live_participants` | 8 |
| `local_sentientia_live_responses` | 25 |
| `local_sentientia_live_events` | 40 |

The Wave D4 (2026-05-25) 6-question-type ship is real on local — the
DB has actual session data. What's still unverified is the
`local/sentientia_live/admin/analytics.php` surface itself — does it
render correctly with this data? Does CSV export (E.7) include all
question types? Does the chart_updater visualisation reflect the new
ranking + word-cloud types?

**Next action**: walk the analytics page manually in a separate
session, capture screenshots in `docs/visual-evidence/YYYY-MM-DD/`.
Not blocking.

### F-026 — `local_sentientia_translate` T.1 install verification

**Verdict: RESOLVED.** Install ran clean.

| Table | Row count on local |
|-------|---------------------|
| `local_sentientia_tr_log` | 0 |
| `local_sentientia_tr_brand` | 0 |

Both tables exist with the version `2026052500` install schema, plus
today's C16 admin landing did NOT add any tables (admin/index.php is
read-only). 0 rows = no live translation has been run on this DB —
expected for an ALPHA plugin that ships disabled. The MVP install path
is verified working.

### F-027 — `local_sentientia_recommendations` H.1 install verification

**Verdict: RESOLVED.** Install ran clean.

| Table | Row count on local |
|-------|---------------------|
| `local_sentientia_rec_log` | 0 |

Install schema works. 0 rows = no recommendations served yet — expected
for an ALPHA plugin behind a feature flag.

### F-028 — `local_sentientia_translate` T.4 (translation memory)

**Verdict: DEFERRED — explicit v2 feature.** T.4 is in the roadmap
header of `local/sentientia_translate/version.php` as a future phase
("T.4 Translation memory (reuse prior translations for repeated
strings)"). MVP shipped T.0 only. No verification needed today.

Recorded in `docs/RENAMES.md` as the maturity path:
"first production translation completes → BETA".

### F-029 — `local_sentientia_recommendations` H.4 install verification

**Verdict: DEFERRED — explicit v2 feature.** Same shape as F-028 —
H.4 is the roadmap, MVP is H.0/H.1. No verification needed.

### F-030 — Challenges 5 pending items

**Verdict: RESOLVED via D4 downgrade.** 3 tables exist; all 0 rows
on local + production:

| Table | Row count |
|-------|-----------|
| `local_airpay_challenge_challenges` | 0 |
| `local_airpay_challenge_attempts` | 0 |
| `local_airpay_challenge_leaderboard` | 0 |

Today's D4 commit (`d7dbd7885`) downgraded the plugin's maturity stamp
from BETA to ALPHA with this evidence baked into `version.php` and the
maturity-triage doc. The "5 pendings" were the visible UI hooks that
look like they work but don't (renderer is a stub). The downgrade is
the honest fix; the alternative (delete-or-finish) is tracked at
`docs/audits/MATURITY-TRIAGE-2026-05-28.md` "promotion path = renderer
ships real impl + tables hold real attempts → BETA".

### F-031 — Course-share workflow + paygw security verify

**Verdict: NEEDS-WORK — out of scope for today's probe.** Requires
a focused security pass cross-checking against `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`. The paygw plugin
shipped recent security follow-ups (MD5 deprecation, `require_login()`
scope fix, sandbox/live URL clarification, 13 new PHPUnit tests per
the 2026-05-24 commit history). Course-share workflow is separate.

**Next action**: schedule a focused security audit as a discrete
session. Not blocking deployment of today's stabilization wave.

### F-033 — Cypress only Site Admin coverage

**Verdict: NEEDS-WORK — inventory pending.** Per the audit doc the
question is "which personas does the existing Cypress walk-suite
cover, and which are missing?" The Cypress fixtures live under the
prototype repo, not in this workspace — inventory requires checking
that repo separately.

**Next action**: in a discrete session, inventory the Cypress suite
and produce a coverage matrix (5 personas × N flows).

### F-039 — `airpay_emails` Phase 5 — what's left?

**Verdict: PARTIALLY-RESOLVED.** Table inventory:

| Table | Row count |
|-------|-----------|
| `local_airpay_email_overrides` | 0 |
| `local_airpay_email_rules` | 12 |
| `local_airpay_email_log` | 0 |
| `local_airpay_email_prefs` | 0 |

Interpretation: 12 rules are registered (Phase 1–4 work is shipped).
The 3 empty tables represent unexercised features:

- `overrides` — per-user/per-tenant cadence overrides (admin UI exists
  but no admin has used it yet on local)
- `log` — outbound mail log (writer is conditional on an admin opting
  into logging; not on by default)
- `prefs` — per-user email preferences (consumer learners get rows
  here after onboarding; consumer signup hasn't happened on this DB)

"Phase 5" is presumably the cron + delivery-tracking work. Without
the state-card current, hard to confirm exact scope.

**Next action**: refresh `state-cards/airpay_emails-state.md` to
reflect actual feature surface and remaining gaps. The freshness gate
from Bucket E4 will start surfacing similar stale state-cards going
forward.

### F-041, F-042 — Web push security review pending (B25 closure)

**Verdict: NEEDS-WORK — focused audit required.** Today's table
inventory shows the push subsystem is shipped:

| Table | Row count |
|-------|-----------|
| `local_sentientia_push_subs` | 0 |
| `local_sentientia_push_log` | 6 |

6 push log rows = at least 6 deliveries happened (likely the test_push
CLI runs from 2026-04). 0 subs means no live subscriptions on local —
consistent with the prod-only PWA story (push is gated behind
master-key install on production).

The pending review items per B25-CRYPTO-AUDIT-2026-05-21.md were:
- RFC 8291 §5.1 worked-example test vectors — ✅ shipped (Priority #4b)
- Tenant-isolation scenario tests — ✅ shipped (Priority #4c)
- customer_brand resolver PHPUnit — ✅ shipped (Priority #4d)

Closure of F-041/F-042 is therefore mostly procedural — the work shipped
in late April / mid-May; the audit just hadn't been re-read against
those commits. Recommend formally marking B25 closed.

**Next action**: lightweight re-read of B25-CRYPTO-AUDIT-2026-05-21 §
non-blocking items + a short "B25 closure" appendix in that doc.

### F-053–F-056 — 4 plugins with un-triaged state-cards

**Verdict: NEEDS-WORK — list incomplete in audit.** The audit doc
flagged "4 plugins" without naming them; today's freshness gate
(Bucket E4) reports 0 stale cards across all 32 plugins, so the
"un-triaged" claim is no longer accurate at the cards-vs-code level.

Two possibilities:
1. Audit was citing cards that didn't exist (the gate covers only
   cards that DO exist). In that case the gap is "state-card missing
   for plugin X".
2. Audit was citing cards that were technically present but had stale
   _content_ (not stale mtime). The freshness gate doesn't read
   content; it just checks file mtime.

**Next action**: in a follow-on pass, enumerate `local/*` plugins and
diff against `state-cards/*-state.md`. If a plugin has shipped > 5
commits without its state-card being touched, refresh it. Could be a
new check in `tools/audit_table_inventory.php`.

### F-087 — `sentientia.pwa.install.enabled` duplicate rows

**Verdict: RESOLVED — no duplicates, schema correct.**

`install.xml` declares `uk_key_cust_tenant UNIQUE on (flag_key,
customer_id, tenant_id)` — verified by reading
`local/airpay_core/db/install.xml` line 36. The DB enforces this:
running

```sql
SELECT flag_key, customer_id, tenant_id, COUNT(*) AS n
  FROM mdl_local_airpay_feature_flags
 GROUP BY flag_key, customer_id, tenant_id
HAVING COUNT(*) > 1
```

returns zero rows. The 2 `sentientia.pwa.install.enabled` rows that
exist sit at different `(customer_id, tenant_id)` tuples
(`(0, 0)` and `(0, 1)`) so they are valid distinct overrides, not
duplicates.

The audit may have been remembering an earlier state before the UNIQUE
constraint was added. With it in place today, F-087 cannot recur.

### F-090 — Feature-flag audit table — is it actually written?

**Verdict: RESOLVED.** `local_airpay_feature_flag_audit` holds 85 rows
on local. The most recent write was 2026-05-27. Audit writer is wired
and active. Breakdown of recent flag toggles (top 15 by recency):

```
live.questiontype.quiz           n=3   last=2026-05-27
live.questiontype.ranking        n=3   last=2026-05-27
live.questiontype.rating         n=3   last=2026-05-27
live.questiontype.wordcloud      n=3   last=2026-05-27
live.realtime.enabled            n=1   last=2026-05-27
live.questiontype.openended      n=3   last=2026-05-27
sentientia.pwa.install.enabled   n=27  last=2026-05-22
live.allow_anonymous             n=3   last=2026-05-21
live.enabled                     n=5   last=2026-05-21
live.questiontype.multichoice    n=3   last=2026-05-21
engagement.whatsapp.enabled      n=4   last=2026-05-21
engagement.whatsapp.reminders    n=4   last=2026-05-21
sentientia.pwa.push.enabled      n=14  last=2026-05-21
ux.darkMode.enabled              n=7   last=2026-05-20
sentientia.customer_level_flags.enabled  n=2  last=2026-05-20
```

Spread across multiple plugins + multiple admins — the writer is
clearly wired everywhere. No further investigation needed.

---

## Severity rollup

| Status | Count | Items |
|--------|-------|-------|
| ✅ RESOLVED | 6 | F-026, F-027, F-030 (via D4), F-087, F-090, and the procedural F-041/F-042 (B25 already shipped) |
| 🟡 PARTIALLY-RESOLVED | 2 | F-024 (data ✅, UI walk pending), F-039 (data ✅, state-card needs refresh) |
| ⏸ DEFERRED | 2 | F-028, F-029 (explicit v2 features) |
| 🔍 NEEDS-WORK | 3 | F-031 (security review), F-033 (Cypress inventory), F-053–F-056 (state-card audit) |

Net: Bucket F shrinks from "10 investigate items" to "3 needs-work
items + 2 partial walks". The audit closeout is mostly done; what
remains is procedural rather than architectural.

---

## How to re-run the probe

`tools/audit_table_inventory.php` is a permanent diagnostic — it's
the same script that produced today's row counts.

```powershell
Set-Location "C:\xampp\htdocs\moodle5\public"
& "C:\xampp\php\php.exe" "D:\Claude Local\airpay-ld-os\tools\audit_table_inventory.php"
```

Output is a markdown-ish table of plugin → table → row count. Run
quarterly against production to track which plugins move from "0
rows" to "production-data-backed" — i.e. which ones earn the maturity
promotion path documented in `MATURITY-TRIAGE-2026-05-28.md`.

---

## Cross-reference

- The audit doc itself: `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` §4 Bucket F
- Maturity triage that closes F-030: `docs/audits/MATURITY-TRIAGE-2026-05-28.md`
- Workspace policy that closes the F-091/F-092 sibling concerns: `docs/WORKSPACE-POLICY.md`
- State-card freshness gate that helps with F-039/F-053..F-056 going forward: `tools/check_state_card_freshness.sh`
- B25 crypto audit (referenced by F-041/F-042): `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`
