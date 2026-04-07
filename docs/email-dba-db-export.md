# Email to DBA — Production Database Export Request

**To:** [DBA Name]
**From:** Nitin Rajput
**Subject:** Request: Airpay Academy Production Database Export (Read-Only)

---

Hi [DBA Name],

I need a read-only export of the Airpay Academy production database for local development and testing. This will have **zero impact on production** — it's a standard mysqldump (SELECT only).

## What I Need

**A full mysqldump of the production Moodle database:**

```bash
mysqldump -h lms-prod-db.crpst4qn6rtu.ap-south-1.rds.amazonaws.com \
  -u lmsprodbadmin -p \
  --single-transaction \
  --routines \
  --triggers \
  --no-tablespaces \
  airpayprod > airpay_academy_prod_$(date +%Y%m%d).sql
```

**OR via AWS RDS Snapshot** (preferred if easier):
1. AWS Console → RDS → Select `lms-prod-db` instance
2. Actions → Take Snapshot
3. Once ready: Export snapshot to S3 → share the .sql file with me

## Key Details

| Detail | Value |
|--------|-------|
| RDS Instance | `lms-prod-db.crpst4qn6rtu.ap-south-1.rds.amazonaws.com` |
| Database Name | `airpayprod` |
| Table Prefix | `mdl_` |
| Collation | `utf8mb4_0900_ai_ci` |
| Expected Size | Unknown — please check with `SELECT table_schema, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'airpayprod' GROUP BY table_schema;` |

## Important Notes

- **Read-only operation** — no writes to production
- `--single-transaction` ensures no locks on InnoDB tables
- Please **do NOT** include any `CREATE DATABASE` or `USE` statements — I'll import into a differently named local database
- If the dump is large (>500MB), please compress: `gzip airpay_academy_prod_*.sql`
- Share the file via OneDrive/Teams/S3 — whatever is easiest

## Why I Need This

Setting up a local development environment for the Airpay Academy upgrade project. The local instance needs production data (courses, users, enrollments) to test BizLMS blocks and theme changes accurately.

## Timeline

Whenever convenient — no rush, but sooner helps me move faster on the upgrade project.

Thanks,
Nitin
