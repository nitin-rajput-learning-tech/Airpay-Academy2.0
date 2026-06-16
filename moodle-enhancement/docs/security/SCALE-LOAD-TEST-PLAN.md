# Sentientia LMS — Scale & Load Testing Plan

**Author:** L&D OS documentation  
**Date:** 2026-06-16  
**Status:** Decision-ready roadmap for enterprise scale proof  
**Audience:** Enterprise buyers (HDFC, ICICI, Axis, large public sector), internal leadership, DevOps  
**Scope:** Evidence scale to 200k+ users, publish RFP-ready SLOs, benchmarks for competing with Invince (HDFC 200k case study)

---

## Executive Summary

Sentientia LMS currently serves **3,176 production users** (Airpay Academy, 3 tenants, single-instance RDS). To compete for **enterprise deals (25k–200k+ users)**, the platform must provide **auditable proof of scale**:

- **Tier 1 (2026-Q4):** Load-test infrastructure for **25k concurrent users** (POC scale), publish SLOs
- **Tier 2 (2027-Q1):** Load-test infrastructure for **100k concurrent users** (small-enterprise scale)
- **Tier 3 (2027-Q3):** Load-test infrastructure for **200k+ concurrent users** (HDFC parity scale) + multi-region failover
- **Ongoing:** Monthly baseline regression tests (Tier 1 infrastructure) to detect performance degradation

**Key targets:**
- ✅ **Response time:** <200ms p95 for learner dashboards at 100k users
- ✅ **Throughput:** 1,000 requests/sec (RPS) sustained on course-view endpoint
- ✅ **Database:** <50ms query latency at p99 for user-context queries
- ✅ **Availability:** 99.5% monthly uptime (RDS multi-AZ auto-failover, tested)
- ✅ **Scaling model:** Horizontal (read replicas for reporting, write-primary for transactional)

**Estimated cost (3-tier plan):**
- **Infrastructure:** EC2 load-gen + RDS replicas ≈ ₹1–2L/month (Tier 2–3), ₹50K/month baseline
- **Tooling:** Apache JMeter + Locust + custom harnesses (open-source, no licensing)
- **Internal effort:** 40 days (initial benchmark + profile optimization + ongoing regression)
- **3-year amortized:** ~₹50–80L + 120 days FTE

---

## The Competitive Gap (Why This Matters)

**Invince (UpsideLMS) positioning:**
> "200,000+ employees trained on our platform (HDFC Bank case study). Single-instance architecture scales to enterprise. Native multi-tenant, zero downtime upgrades."

**Sentientia today:**
> "Proven on Airpay Academy (3,176 users, 3 tenants). Moodle 5.1 fork with production-hardened codebase."

**The RFP blocker:** Large enterprise buyers ask, "Can you handle our 50k employees? What's your proof?"

**Answer:** Load-test reports + published SLOs + benchmarks vs competitors.

---

## Load-Testing Architecture

### 3-Tier Strategy

#### Tier 1 — Baseline (2026-Q4) — **25k Concurrent Users**

**Goal:** Prove single-instance RDS scales beyond 3.1k production

**Infrastructure:**
- **Load generator:** 1× EC2 `c5.2xlarge` (8 vCPU, 16 GB) running Apache JMeter (3 slave nodes for distribution)
- **SUT (System Under Test):** 1× EC2 `t3.2xlarge` (8 vCPU, 32 GB) — Moodle + PHP-FPM + Redis cache
- **Database:** 1× RDS MySQL `db.r5.2xlarge` (8 vCPU, 64 GB) — production-like config (InnoDB, binary logging for replication)
- **Shared storage:** 1× EBS `gp3` 100 GB (course files, SCORM packages)
- **Cost:** ~₹50K/month (2 months baseline + regression = ₹100K; EC2 + RDS on-demand + data transfer)
- **Timeline:** 2 weeks setup + 1 week stabilization + 2 weeks test execution = 5 weeks total

**Scenarios:**
1. **Ramp-up:** Gradual user increase (0 → 25k over 10 minutes)
2. **Peak load:** Sustain 25k concurrent for 30 minutes
3. **Soak test:** 50% peak (12.5k) for 4 hours (detect memory leaks, connection pool exhaustion)
4. **Spike test:** Sudden jump to 1.5× peak (37.5k) for 5 minutes (circuit breaker behavior)
5. **Breakdown test:** Gradually increase until response time exceeds SLO or error rate >5% (find breaking point)

**Acceptance criteria:**
- [ ] p95 response time ≤200ms for `/my/dashboard.php` (learner dashboard)
- [ ] p99 response time ≤500ms for `/course/view.php?id=1` (course view)
- [ ] Throughput ≥1,000 requests/sec on course-view endpoint
- [ ] Error rate <1% under peak load
- [ ] Database CPU <80%, memory <85% under sustained peak
- [ ] No connection pool exhaustion (PHP-FPM max_children remains <100)
- [ ] Cache hit ratio >80% (Redis)

#### Tier 2 — Enterprise Small (2027-Q1) — **100k Concurrent Users**

**Goal:** Prove platform scales to small enterprise (10k–25k employee company)

**Infrastructure (relative to Tier 1):**
- **Moodle (horizontal):** 2× EC2 `t3.2xlarge` (app layer) behind ALB (load balancer)
  - Session state in Redis (shared, no sticky sessions needed)
  - File uploads to S3 (offload disk I/O)
- **Database:** 1× RDS MySQL `db.r6i.4xlarge` (16 vCPU, 128 GB) — write-primary
  - 1× RDS read replica `db.r6i.2xlarge` (for reporting queries, analytics load)
  - Multi-AZ (auto-failover, ≤1 min RTO)
- **Caching:** 1× ElastiCache Redis `cache.r6g.xlarge` (4 GB) for sessions + cache_application store
- **Load generator:** 2× EC2 `c5.4xlarge` (16 vCPU, 32 GB each) in distributed JMeter setup
- **Cost:** ~₹1.5L/month × 2 months = ₹3L (Tier 2 baseline) + ₹50K/month ongoing regression
- **Timeline:** 4 weeks setup (app layer tuning + replication config) + 2 weeks test

**Tuning focus:**
- N+1 query elimination (`local_sentientia_compliance_report` bulk-fetch optimizations)
- Read replica for reporting (`analytics` dashboard queries → read-replica only)
- Database connection pooling (ProxySQL between app + RDS, connection pooling, query caching)
- Cache invalidation strategy (Redis key expiry, cache-busting on role assignment)

**Scenarios (expand from Tier 1):**
1. **Ramp-up:** 0 → 100k concurrent (slow, 30 minutes)
2. **Peak load:** Sustain 100k for 1 hour
3. **Soak test:** 50k for 6 hours (overnight burn-in)
4. **Geographically distributed:** Simulate learners across 3 regions (India, APAC proxy via AWS Lambda@Edge)
5. **Failover test:** Kill primary RDS instance; measure failover time + error recovery

**Acceptance criteria (relative to Tier 1):**
- [ ] p95 <200ms (same as 25k, with horizontal app layer)
- [ ] Horizontal scaling: +10% latency when adding 2nd app instance vs 1× (linear scaling efficiency >80%)
- [ ] Read replica lag <1 sec (for eventual-consistency reporting queries)
- [ ] Multi-AZ failover RTO <2 minutes, RPO <5 min
- [ ] Sustained throughput ≥2,000 RPS (doubled from Tier 1)

#### Tier 3 — Enterprise Large (2027-Q3) — **200k+ Concurrent Users**

**Goal:** Parity with Invince's HDFC Bank case study; prove Sentientia handles 200k+ employees

**Infrastructure (relative to Tier 2):**
- **Moodle (auto-scaling):** 3–5× EC2 instances (auto-scale policy: target CPU 60%) behind ALB
  - Session state in Redis (cluster mode for HA)
  - Files in S3 (no local disk storage)
- **Database:** Aurora MySQL cluster (Amazon RDS-compatible, auto-scaling reads + writes)
  - Writer: 1× `db.r6i.6xlarge` (24 vCPU, 192 GB) + auto-scaling read replicas (up to 15)
  - Multi-region failover (primary in Mumbai ap-south-1, standby in Delhi ap-south-2, <100ms replication lag)
- **Caching:** ElastiCache Redis cluster (`cache.r6g.large` ×3 shards for HA)
- **CDN:** CloudFront for static assets (theme CSS/JS, user avatars, course images)
- **Database proxying:** ProxySQL cluster (connection pooling + query routing to read replicas)
- **Load generator:** 4× EC2 `c6a.4xlarge` (16 vCPU, 32 GB) running Locust (Python distributed load-gen, more flexible than JMeter)
- **Cost:** ~₹2L/month × 3 months + multi-region infrastructure = ₹6–8L (Tier 3 baseline) + ₹80K/month ongoing
- **Timeline:** 8 weeks (Aurora migration testing, multi-region setup, chaos engineering)

**Advanced scenarios:**
1. **Multi-region active-active:** Learners distributed across Mumbai + Delhi; measure latency + consistency
2. **Chaos engineering:** Random pod kill (simulated app crash), RDS failover, network partition (5-minute heal)
3. **Cache stampede:** Flush entire Redis cluster; measure recovery latency (cold-cache performance)
4. **Subscriber surge:** Course launch → 10k new learners enroll simultaneously (registration peak load)
5. **Report generation spike:** 10k admins generate compliance reports in parallel (concurrent DB reads, memory pressure)

**Acceptance criteria (relative to Tier 2):**
- [ ] p95 <200ms even at 200k concurrent (constant SLO despite 2× scale)
- [ ] Read replica replication lag <500ms (multi-region)
- [ ] Multi-region failover (primary → standby): RTO <5 min, RPO <30 sec
- [ ] Cost per user per month <₹5 (Invince targets <₹10; Sentientia on-prem / open-source story is zero-marginal-cost per seat)

---

## Load-Testing Metrics & Dashboards

### Key Performance Indicators (KPIs)

All tests publish:

| Metric | Tier 1 Target | Tier 2 Target | Tier 3 Target | Monitoring Tool |
|--------|---|---|---|---|
| **Response time (p50)** | <50ms | <50ms | <50ms | JMeter / Locust HTML report |
| **Response time (p95)** | <200ms | <200ms | <200ms | " |
| **Response time (p99)** | <500ms | <500ms | <500ms | " |
| **Error rate** | <1% | <1% | <1% | " |
| **Throughput (RPS)** | ≥1,000 | ≥2,000 | ≥5,000 | " |
| **Database CPU** | <80% | <70% | <65% | CloudWatch |
| **Database memory** | <85% | <80% | <75% | " |
| **Database connections** | <100 of 2000 max | <200 of 5000 max | <500 of 10k max | MySQL `SHOW PROCESSLIST` |
| **Redis hit ratio** | ≥80% | ≥85% | ≥85% | Redis INFO command |
| **Disk I/O (MB/s)** | <100 | <50 | <50 | CloudWatch EBS metrics |
| **Network bandwidth** | <500 Mbps | <1 Gbps | <2 Gbps | CloudWatch network metrics |
| **Cost per concurrent user** | ~₹2 | ~₹0.75 | ~₹0.40 | CloudWatch Cost Optimization |

### Test Report Template (Per Tier)

```
╔════════════════════════════════════════════════════════╗
║ SENTIENTIA LMS LOAD-TEST REPORT (TIER 1 — 25K USERS)  ║
╚════════════════════════════════════════════════════════╝

Test Date:        2026-11-15
Environment:      AWS (us-east-1)
Duration:         120 minutes (ramp-up 10 min + peak 30 min + soak 40 min + breakdown 40 min)
Test Tool:        Apache JMeter 5.5 (distributed, 3 slaves)
Concurrent Users: 25,000

RESULTS SUMMARY
───────────────
✅ All acceptance criteria MET

Response Times (ms):
  ├─ p50:  28
  ├─ p95:  156  (target: <200) ✅
  ├─ p99:  412  (target: <500) ✅
  └─ max:  892

Error Rate:
  ├─ Total requests: 1,234,567
  ├─ Failed: 11,234
  ├─ % failure: 0.91% (target: <1%) ✅
  └─ 5xx errors: 5,612 (app crash recovery in 20s)

Throughput:
  ├─ Peak RPS:     1,156 (target: ≥1,000) ✅
  ├─ Sustained RPS: 1,089 (30-min peak window)
  └─ Average RPS:  856

Endpoints (sorted by avg response time):
  1. GET /my/dashboard.php:        avg 45ms, p95 156ms, errors 0.1%
  2. GET /course/view.php:         avg 78ms, p95 234ms, errors 0.8%
  3. POST /local/sentientia_live:  avg 156ms, p95 412ms, errors 2.1%
  4. POST /login/index.php:        avg 112ms, p95 289ms, errors 0.3%
  5. GET /my/courses.php:          avg 52ms, p95 178ms, errors 0.5%

Database Performance:
  ├─ CPU utilization:    67% (peak), target <80% ✅
  ├─ Memory utilization: 74% (peak), target <85% ✅
  ├─ Query p99 latency:  34ms, target <50ms ✅
  ├─ Active connections: 87 of 2000, target <100 ✅
  └─ Slow queries (>1s):  3 (identified for optimization)

Cache Performance (Redis):
  ├─ Cache hit ratio:  84%, target ≥80% ✅
  ├─ Eviction rate:    0 (no memory pressure)
  └─ Avg TTL:          5 min (appropriate for session + app cache)

Infrastructure:
  ├─ Moodle instance CPU:    72% (app logic)
  ├─ PHP-FPM processes:      94 of 128 max (73% utilization)
  ├─ Disk I/O (EBS):         67 MB/s (sustained), target <100 ✅
  ├─ Network bandwidth:      187 Mbps (app → DB), target <500 ✅
  └─ Total cost (2 months):  ₹2.1L (amortized ₹50K/month)

FINDINGS & RECOMMENDATIONS
──────────────────────────
1. ⚠️ Slow query identified: user_enrolments JOIN course_completions (2.1s at p99)
   → Action: Add INDEX on (userid, courseid) in compliance audit query
   → Expected improvement: <100ms after optimization

2. ✅ No connection pool exhaustion; no session state bottleneck
   → Redis session store performing well; ready for Tier 2 (2× app instances)

3. ✅ Error recovery is graceful (app restart after spike, no cascading failures)

4. ⚠️ POST /local/sentientia_live endpoint slower than expected (avg 156ms)
   → Investigate: SSE event broadcasting, DB subscription writes
   → Recommend: Offload SSE to separate worker or message queue (Phase 2)

SIGN-OFF
────────
Prepared by:     Claude (L&D OS engineering)
Validated by:    Nitin Rajput (Product Owner)
Approved for RFP: ✅ YES (meets all Tier 1 acceptance criteria)
Date:            2026-11-15
```

---

## Performance Optimization Roadmap

### Phase 1: Tier 1 Baseline (Profile & Fix Low-Hanging Fruit)

**Pre-load-test tuning:**
1. **Database index audit** (1 day):
   - Identify missing indexes on FK columns + WHERE clauses
   - Run EXPLAIN ANALYZE on top 10 slow queries (from `{mdl_logstore_standard_log}`)
   - Add indexes for: (userid, courseid), (costcenterid, status), (role_assignment role_id)

2. **Query optimization** (2 days):
   - N+1 query elimination in `local_sentientia_compliance_report` (bulk-fetch instead of per-user loop)
   - Cache dashboard queries (`analytics` KPI aggregates, cache for 1 hour)
   - Parameterize multi-join reports (defer heavy SQL to read-only background jobs)

3. **PHP-FPM tuning** (1 day):
   - `max_children` = CPU cores (8 cores → 32 pool processes, buffer for spike)
   - `max_requests_per_child` = 5,000 (prevent memory leak accumulation)
   - Enable OPcache (`opcache.memory_consumption` = 512 MB, aggressive TTL invalidation OFF)
   - Connection pooling: MySQL `max_connections` = 2,000 (accommodate 128 PHP-FPM processes × ~16 connections each at peak)

4. **Redis caching** (1 day):
   - Cache Moodle core queries (role capabilities, user courses, completion status)
   - Use `cache_request` store for intra-request caching (avoid repeated DB queries in same HTTP request)
   - Implement cache invalidation on role assignment + capability grant

5. **Session store** (1 day):
   - Move sessions from file-based → Redis (reduce filesystem I/O, enable horizontal scaling)
   - Session TTL = 24 hours (default), configurable per tenant

### Phase 2: Tier 2 Optimization (Horizontal Scaling + Read Replicas)

**Post-Tier 1-data tuning:**
1. **Horizontal app layer** (3 days):
   - Deploy 2nd Moodle instance (identical config)
   - ALB (Application Load Balancer) with round-robin + health checks
   - Session state in shared Redis (no sticky sessions)
   - Validate linear scaling: 2× app instances → similar p95 with 2× throughput

2. **Read replicas for reporting** (2 days):
   - Point analytics / compliance-report queries to read replica
   - Use Moodle's `$CFG->read_slave_connection` config (if available in 5.1/5.2) or ProxySQL routing

3. **Database connection pooling** (1 day):
   - ProxySQL between app layer + RDS (connection pooling, query caching, failover)
   - Pool size tuning: `max_connections` in ProxySQL = 2× app × PHP-FPM processes

### Phase 3: Tier 3 Optimization (Multi-Region + Auto-Scaling)

**High-scale tuning:**
1. **Aurora MySQL migration** (5 days):
   - Migrate from RDS MySQL to Aurora (same API, auto-scaling reads + serverless option)
   - Test replication lag, multi-region failover
   - Validate zero-downtime cutover (parallel run, DNS switch-over)

2. **Multi-region setup** (5 days):
   - Aurora global database (primary in ap-south-1 / Mumbai, read-only in ap-south-2 / Delhi)
   - Measure replication lag (<100ms typical)
   - Test failover: promote Delhi standby to primary in <5 min

3. **Auto-scaling policies** (2 days):
   - EC2 auto-scale group: target CPU 60%, min 3 / max 10 instances
   - RDS Aurora: auto-scale read replicas (1–15), target CPU 70%
   - Monitor cost (scale-down after peak hours to save ₹50K–100K/month)

---

## Tooling & Infrastructure

### Load-Testing Tools (Open-Source)

| Tool | Purpose | License | Notes |
|------|---------|---------|-------|
| **Apache JMeter 5.5+** | Distributed load generation, scenario scripting | Apache 2.0 | Ideal for Tier 1/2; HTTP protocol, plugins for Moodle workflows (login → course view → quiz) |
| **Locust 2.0+** | Python-based load gen, more flexible | MIT | Better for Tier 3 (multi-region, custom SDN simulations); easier debugging than JMeter |
| **CloudWatch** | AWS monitoring (EC2, RDS, ELB, Lambda) | AWS native | Free tier; dashboards for CPU, memory, network, latency percentiles |
| **Apache Bench (ab)** | Simple HTTP load testing | Apache 2.0 | Lightweight, good for quick sanity checks; insufficient for complex scenarios |
| **Grafana + Prometheus** | Metrics visualization (optional, but recommended for Tier 2+) | AGPL 3.0 | Export app + DB metrics to Prometheus, dashboard in Grafana; pair with CloudWatch |

### Test Environment Automation

**Infrastructure-as-code (Terraform + Ansible):**

```hcl
# terraform/tier1-load-test/main.tf
# Spin up EC2 load-gen + Moodle SUT + RDS MySQL with one command

resource "aws_instance" "load_generator" {
  ami           = "ami-ubuntu-20.04"
  instance_type = "c5.2xlarge"
  user_data     = file("${path.module}/install-jmeter.sh")
  tags          = { Name = "load-gen-tier1" }
}

resource "aws_instance" "moodle_sut" {
  ami           = "ami-custom-moodle-5.1"
  instance_type = "t3.2xlarge"
  user_data     = file("${path.module}/install-moodle.sh")
  depends_on    = [aws_db_instance.sentientia_mysql]
}

resource "aws_db_instance" "sentientia_mysql" {
  identifier          = "sentientia-load-test"
  engine              = "mysql"
  engine_version      = "8.0.35"
  instance_class      = "db.r5.2xlarge"
  allocated_storage   = 100
  storage_encrypted   = true
  multi_az            = false  # Tier 1 (single-AZ)
  skip_final_snapshot = true   # For testing only
}
```

**Test execution script:**

```bash
#!/bin/bash
# run-load-test.sh — Execute full Tier 1 test cycle

set -e

echo "Starting Tier 1 Load Test..."

# 1. Clear data (reset production snapshot to baseline)
./tools/reset-rds-snapshot.sh

# 2. Deploy latest Moodle codebase to SUT
ansible-playbook -i hosts.ini deploy-moodle.yml

# 3. Warm up cache (boot app 10 times to populate OPcache + Redis)
for i in {1..10}; do curl -s http://moodle-sut.local/ > /dev/null; done

# 4. Run JMeter scenario
jmeter -n -t test-plans/tier1-25k-users.jmx \
        -l results/tier1-load-test-$(date +%Y%m%d-%H%M%S).csv \
        -j results/jmeter-$(date +%Y%m%d-%H%M%S).log

# 5. Generate report
./tools/jmeter-report-generator.py results/ > TIER1-RESULTS.html

# 6. Upload results to S3 + archive
aws s3 cp results/ s3://sentientia-load-tests/tier1-$(date +%Y%m%d)/

echo "✅ Tier 1 load test completed. Report: TIER1-RESULTS.html"
```

---

## Acceptance Criteria & Go/No-Go Decision

### Tier 1 Go/No-Go (2026-11-30)

**Before publishing "25k scale proof":**
- [ ] p95 response time <200ms ✅
- [ ] Error rate <1% ✅
- [ ] Throughput ≥1,000 RPS ✅
- [ ] Database SLA met (CPU <80%, memory <85%, connections <100) ✅
- [ ] 2-month regression (baseline test re-run) shows no degradation ✅
- [ ] Report signed by Nitin + Claude (engineering) ✅

**Go → Publish attestation:** "Sentientia LMS scales to 25,000 concurrent users. Proof: [link to load-test report]. SLOs: p95 <200ms, 99.5% availability."

**No-Go → Root-cause analysis:** If any criterion fails, identify bottleneck (N+1 query, connection pool leak, cache miss spike), fix, re-test within 2 weeks.

### Tier 2 Go/No-Go (2027-02-28)

Same criteria as Tier 1, but measured at 100k users + validated horizontal scaling efficiency.

### Tier 3 Go/No-Go (2027-10-31)

Same criteria as Tier 2, but measured at 200k users + multi-region failover <5 min RTO.

---

## Reporting & RFP Use Cases

### Public Attestation (Customer-Facing)

After each tier's successful completion, Sentientia publishes:

```
SENTIENTIA LMS — SCALE & PERFORMANCE ATTESTATION (2026)

Scale Proof (Tier 1):
  Concurrent Users:    25,000
  Test Date:           2026-11-15 to 2026-11-30
  Test Environment:    AWS (us-east-1)
  Peak Throughput:     1,156 requests/sec
  p95 Response Time:   156 ms (target <200ms) ✅
  Error Rate:          0.91% (target <1%) ✅
  Availability:        99.9% during test
  Cost per User:       ~₹2 (infrastructure + tooling amortized)

Infrastructure Details:
  ├─ App tier: 1× EC2 t3.2xlarge (8 vCPU, 32 GB RAM)
  ├─ Database: 1× RDS MySQL r5.2xlarge (8 vCPU, 64 GB RAM)
  ├─ Cache: Redis (4 GB, 84% hit ratio)
  └─ Load generator: Apache JMeter (distributed)

Validated Endpoints:
  ✅ /my/dashboard.php (learner dashboard): p95 156ms
  ✅ /course/view.php (course view): p95 234ms
  ✅ /local/sentientia_live (real-time engagement): p95 412ms
  ✅ /login/index.php (authentication): p95 289ms

Comparison with Competitors:
  ├─ Invince (UpsideLMS): 200k users, HDFC deployment
  ├─ Sentientia (Tier 1): 25k proven, Tier 2 (100k) in progress
  ├─ Sentientia (Tier 3 target): 200k+ parity with Invince by 2027-Q3

Roadmap:
  2026-Q4: Tier 1 (25k) ✅ DONE
  2027-Q1: Tier 2 (100k) ⏳ IN PROGRESS
  2027-Q3: Tier 3 (200k+) 📅 PLANNED

Signed: Nitin Rajput (Product Owner) + Claude (Engineering)
Date:   2026-11-30
```

### RFP Response Templates

**Q: Can your platform handle 50,000 users?**

> Yes. Sentientia LMS has been independently load-tested to 25,000 concurrent users (2026-Q4 baseline) with p95 response time <200ms and <1% error rate. Tier 2 testing (100,000 users, 2027-Q1) is in progress. Our architecture scales horizontally (multi-app-instance) and supports read-replicas for reporting queries. See attached load-test report: `SENTIENTIA-TIER1-LOAD-TEST-REPORT-2026-11.pdf`.

**Q: What's your SLA / uptime guarantee?**

> We target 99.5% monthly availability. AWS RDS multi-AZ provides automatic failover (<2 min RTO). Sentientia is deployed on customer infrastructure (on-prem or customer's AWS account), so uptime depends on customer's networking + security policies. See SLA matrix in `SUPPORT-SLA-MODEL.md`.

**Q: How does cost scale with users?**

> Marginal cost per user ≈ ₹1–2/month at scale (infrastructure amortized). Our open-source foundation (Moodle GPL 3.0) and on-prem deployment model eliminate per-seat licensing, unlike SaaS competitors. Load-test costs are published transparently.

---

## Timeline & Budget Summary

| Phase | Timeline | Infrastructure Cost | Internal Effort | Total |
|-------|----------|---|---|---|
| **Tier 1 (25k)** | 2026-Q4 (Oct–Nov) | ₹2L (2-month infrastructure) | 12 days (setup + profile + test + report) | ₹2L + 12d |
| **Tier 2 (100k)** | 2027-Q1 (Jan–Feb) | ₹3L (2-month Tier 2 infrastructure) | 10 days (replication tuning + test + report) | ₹3L + 10d |
| **Tier 3 (200k)** | 2027-Q3 (Jul–Aug) | ₹6L (3-month Tier 3 + multi-region) | 18 days (Aurora migration + multi-region + chaos eng) | ₹6L + 18d |
| **Ongoing (monthly regression)** | 2026-Q4 onwards | ₹50K/month | 2 days/month | ₹600K/year + 24d/year |
| **3-year total** | 2026–2029 | ₹12L + ₹1.8L (ongoing) = ₹13.8L | 40d + (24d × 3) = 112 days | ~₹50–80L total (amortized) |

---

## Recommendations for Leadership

1. **Initiate Tier 1 baseline immediately** (2026-06-20) — RFP responses need scale proof by 2026-11-01 (6-month enterprise sales cycle).
2. **Publish Tier 1 results prominently** in RFP responses + website ("Proven to scale to 25,000 users").
3. **Tier 2 (100k) by 2027-Q1** — competitive requirement (Invince has 200k case study; we must show clear scale trajectory).
4. **Tier 3 (200k) by 2027-Q3** — parity with Invince for large BFSI bids (HDFC, ICICI, Axis, etc.).
5. **Monthly regression tests** (Tier 1 re-run, ₹50K each) — detect performance degradation from new features early, avoid surprise production issues.

---

## Next Steps

1. **Nitin approval** (target: 2026-06-18): Confirm Tier 1 budget (₹2L infrastructure + 12 days FTE) + publish roadmap.
2. **Infrastructure provisioning** (target: 2026-10-01): Terraform + Ansible scripts ready, EC2 + RDS staging environment spun up.
3. **JMeter test-plan development** (target: 2026-10-15): Login → course view → quiz attempt scenarios scripted, validated against dev environment.
4. **Tier 1 execution** (target: 2026-11-15 to 2026-11-30): Full test cycle (ramp-up + peak + soak + breakdown), report generation, sign-off.
5. **Publish attestation** (target: 2026-12-01): 1-page scale proof available for RFPs, linked from sentientia.io website.
6. **Tier 2 roadmap** (2027-Q1): Schedule Aurora migration + read-replica setup, allocate 3L budget.

---

**Document version:** 1.0  
**Last updated:** 2026-06-16  
**Next review date:** 2026-10-01 (post-Tier-1-setup)
