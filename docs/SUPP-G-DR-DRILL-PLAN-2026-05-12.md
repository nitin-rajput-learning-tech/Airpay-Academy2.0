# Supplement G — Disaster Recovery Drill Plan

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 11.6 (Risk Register). Operationalises mitigation for risks
**I2** (Database backup not exercised in real restore drill) and **I3**
(Disaster recovery cold-site procedure undefined).

The risk register supplement (SUPP-A) rates both I2 and I3 as
**H/M** residuals — high severity, medium likelihood, not yet
exercised. This document defines the drill that converts those to
**H/L** post-mitigation.

## 1. Recovery objectives

Two industry-standard metrics define the disaster recovery posture.

| Metric | Target | Definition | How we'll measure |
|---|---|---|---|
| **RTO** — Recovery Time Objective | 4 hours | Maximum time from incident declaration to platform back online | Wall-clock from drill start to verified-functional drill |
| **RPO** — Recovery Point Objective | 24 hours | Maximum data loss acceptable, measured backwards from incident | Time gap between last verified backup and incident |

Setting RTO at 4 hours and RPO at 24 hours reflects the platform's
operating reality: L&D is critical but not real-time-critical.
Statutory training completion records have a seven-year archival
obligation, so RPO of 24 hours means we lose at most one day's worth
of new completions (acceptable; learners re-mark within a week
without operational disruption).

Tightening RTO below 4 hours requires either active-active
replication (significant capex) or warm-standby (moderate capex). Both
are deferred until the Public tenant grows past 2,000 paying users
when the commercial impact justifies the investment.

## 2. Failure scenarios covered

The drill is designed to exercise four distinct failure scenarios.

### Scenario A — Database corruption

**Trigger:** A bad migration, a runaway query, or storage-layer
corruption renders the production database unrecoverable.

**Drill action:** Restore the most recent verified DB backup to a
new database. Verify the platform comes back online against the
restored database. Verify that no row corruption survives the
restore.

**Success criterion:** Platform online within RTO. Data loss ≤ RPO.

### Scenario B — Application server loss

**Trigger:** The production EC2 instance / VM is destroyed (hardware
failure, accidental termination, malicious actor).

**Drill action:** Provision a new application server. Install Apache +
PHP. Pull the production codebase from GitHub `production` branch.
Point the new server at the (intact) production database. Verify the
platform comes back online.

**Success criterion:** Platform online within RTO. Zero data loss
because the database was untouched.

### Scenario C — File storage corruption

**Trigger:** The `moodledata` directory (uploaded files, SCORM
packages, course backups) is corrupted or accidentally deleted.

**Drill action:** Restore the file backup from S3. Verify file
integrity via manifest comparison. Verify the platform serves the
restored files correctly.

**Success criterion:** Files online within RTO. Specific file count
matches the manifest within tolerance (allow for ≤ 0.01% drift due
to in-flight uploads at backup time).

### Scenario D — Region-level outage (cold-site activation)

**Trigger:** The AWS region hosting production is unavailable for an
extended period.

**Drill action:** Activate the cold-site environment in a different
AWS region. Restore database + files from cross-region backup. Point
DNS to the cold site. Verify the platform comes back online.

**Success criterion:** Platform online in cold site within 8 hours
(relaxed RTO acknowledges cross-region complexity). Data loss within
RPO.

The cold-site environment is a Capex item (₹1.5 lakh setup per
Supplement E) currently deferred per Decision 13.1 (production
hosting strategy). It becomes mandatory if the Public tenant grows to
commercial scale.

## 3. Drill cadence

| Drill | Frequency | Owner | Scenarios exercised |
|---|---|---|---|
| Tabletop walkthrough | Quarterly | Head of L&D + IT lead | All four scenarios discussed; runbook reviewed |
| Live drill — Scenarios A + B | Annually | IT (with L&D observer) | DB restore + app-server replacement, full RTO timing |
| Live drill — Scenario C | Annually | IT | File-restore from S3 |
| Live drill — Scenario D | Biennially (once every 2 years) | IT + cold-site infrastructure | Full cross-region activation |

The first live drill is scheduled for **week 3-4 of the 90-day plan**
(post-cutover stabilisation period), per master document Section 14.

## 4. Backup verification protocol

Backups must be verified, not just produced. The verification protocol
runs after every backup and reports to a dashboard.

### Daily backups (automated)

```
Production database → daily SQL dump → S3 (encrypted, versioned)
Production moodledata → daily incremental rsync → S3 (encrypted)
```

Verification:
1. After the dump finishes, run `mysql --dry-run` against the backup
   file to verify it parses cleanly.
2. Compute SHA-256 hash of the dump file; store in a backup-manifest
   table.
3. Compute total file count and total size of `moodledata` from the
   backup; compare against production filecount via a SELECT on
   `mdl_files`.
4. Log success/failure to `mdl_local_airpay_core_audit_log` (or
   equivalent).
5. Alert if either step 1 or 2 fails.

### Weekly verification drill (automated)

Every Saturday morning, a script:
1. Picks the most recent daily backup.
2. Spins up a temporary RDS instance from the backup.
3. Runs a smoke check (count rows in `mdl_user`, verify ≥ 2,500 users).
4. Verifies the Phase 8.x smoke tables exist with expected row counts
   (e.g. `local_airpay_cart_history` ≥ some threshold).
5. Terminates the temporary instance.
6. Logs the verification result.

A green weekly verification gives the operator confidence that the
daily backups are restorable. Without this loop, "we have backups"
is faith, not fact.

## 5. Drill checklist — Scenario A (DB corruption)

The full live drill checklist for the first major scenario. Total
estimated time: 60-90 minutes.

```
□ Pre-drill: snapshot the staging environment as the rollback point
□ Pre-drill: clock-in time recorded (T+0)
□ Pre-drill: backup-manifest table queried for most recent verified backup
□ Pre-drill: 10-minute team standup — drill plan confirmed, comms paused

Phase 1 — Decision (5 min from T+0)
□ Incident commander declares the drill scenario
□ Affected systems identified (just the staging DB for the drill)
□ Comms template sent to the L&D team (drill mode, not real outage)

Phase 2 — Containment (10 min from T+5)
□ Application server placed in maintenance mode
□ Database connections drained
□ Decision made: restore-in-place or restore-to-new-instance

Phase 3 — Restore (30 min from T+15)
□ Latest verified backup pulled from S3 to a new RDS instance
□ Backup SHA verified against the manifest
□ Restore initiated
□ Restore completion confirmed
□ Application server pointed at the restored DB
□ Maintenance mode lifted

Phase 4 — Verification (15 min from T+45)
□ Site front-page loads (HTTP 200)
□ Login as test user succeeds
□ Phase 7 multi-role UAT runs against the restored platform
□ Acceptance: ≥ 80/85 pass (allow 5-case slack for drill-specific
  flakiness)
□ Audit log queried for any anomalous events during the drill

Phase 5 — Communications (5 min from T+60)
□ Drill complete message sent to L&D team
□ Final clock-out time recorded (T+ ≤ 4h target)
□ RTO measurement: actual minutes from T+0 to platform-functional

Phase 6 — Post-drill review (next business day)
□ Retrospective with IT + L&D
□ What went well / what would have failed in a real incident
□ Runbook updated with any procedural fixes discovered
□ Next drill scheduled
```

## 6. Drill-day playbook — Who does what

| Role | Owner | Responsibility during drill |
|---|---|---|
| Incident Commander | IT lead | Declares scenario, calls phase transitions, manages clock |
| Database operator | IT engineer | Executes restore commands, verifies output |
| Application operator | IT engineer | Maintenance mode, application restart |
| Platform owner | Head of L&D | Functional verification, UAT execution |
| Communications | Head of L&D | Comms template send/receive |
| Observer | Department head (rotating) | Watches the drill, captures issues for the retrospective |
| Bystander mode | Compliance Officer | Confirms the drill itself doesn't trigger any compliance flags |

## 7. Backup retention policy

Aligned with the seven-year statutory hold for RBI-regulated payment-
service-provider records.

| Backup tier | Retention | Storage tier |
|---|---|---|
| Daily DB dumps | 30 days | S3 Standard |
| Daily DB dumps (archive) | 90 days | S3 Standard-IA |
| Weekly DB snapshots (consolidated) | 1 year | S3 Glacier Instant Retrieval |
| Monthly DB snapshots (consolidated) | 7 years | S3 Glacier Deep Archive |
| File backups (moodledata) | Same tiers as DB | S3 Standard → Glacier |
| Audit log exports | 7 years minimum | S3 Glacier Deep Archive |

Encryption: all tiers use AWS S3 SSE-KMS with the Airpay-corporate-
managed KMS key.

## 8. Restore-from-Glacier scenario

Recovering from a Glacier-tiered backup is slower than from Standard.
Plan accordingly.

| Glacier tier | Retrieval time | Use case |
|---|---|---|
| S3 Standard-IA | Milliseconds | Recent backups (90-day window) |
| Glacier Instant Retrieval | Milliseconds | Yearly snapshots |
| Glacier Flexible Retrieval | 3-5 hours | (Not used by us) |
| Glacier Deep Archive | 12-48 hours | Compliance audit scenarios — RBI inspector asks for a 5-year-old completion record |

The drill above exercises the S3-Standard scenario. A separate annual
drill should exercise a Glacier Deep Archive retrieval to ensure the
end-to-end compliance-audit response path works.

## 9. Cold-site environment specification

Required when the platform commits to RTO < 8 hours in a region-
failure scenario. Currently deferred per Decision 13.1.

```
Primary region: ap-south-1 (Mumbai)
Cold-site region: ap-southeast-1 (Singapore)

Cold-site components:
  - VPC + subnets pre-provisioned
  - RDS read-replica with cross-region replication (RPO ~5 min)
  - S3 cross-region replication for moodledata
  - DNS failover via Route53 with health checks
  - Application server AMI pre-built and refreshed monthly
  - Identical security groups + IAM roles
```

Estimated standing cost: ₹15,000-25,000 per month (mostly RDS standby
+ cross-region replication bandwidth). Activation cost: ₹0 (already
provisioned).

The cold-site cost is excluded from the SUPP-E expected-case budget;
adding it would reduce the net cash-positive position by ~₹3 lakh
per year.

## 10. Documentation outputs

Each drill produces three artefacts that go into the audit pack:

1. **Drill log** — timestamped action log of every step. Stored in
   `docs/dr-drills/YYYY-MM-DD-scenario-A-log.md`.
2. **Retrospective document** — what happened, what was learned, what
   procedure changed. Stored in
   `docs/dr-drills/YYYY-MM-DD-scenario-A-retro.md`.
3. **Runbook diff** — every change to `PHASE-8-DEPLOYMENT-RUNBOOK.md`
   or `MOODLE5-UPGRADE-RUNBOOK.md` resulting from the drill, committed
   to git with the drill date in the commit message.

The audit pack for an external auditor (e.g. ISO 27001 reviewer if
that certification is ever pursued) is the union of these three
artefacts across the last two years.

## 11. Open decisions

| Decision | Owner | Recommendation |
|---|---|---|
| Cold-site activation criterion | CTO + Head of L&D | Activate when Public tenant > 2,000 paying users OR ZEEA contract requires it explicitly |
| Backup encryption key rotation policy | IT | Annual rotation aligned with KMS best practice |
| Drill schedule sync with audit cycles | Compliance Officer | Schedule the annual live drill in February ahead of the typical April-June audit window |
| Who has the authority to declare a real incident? | CTO + Head of L&D | Either; with documented escalation paths in both directions |
