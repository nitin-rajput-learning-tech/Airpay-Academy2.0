# Airpay Academy 2.0 — Production Release Package
## Version: v1.0.0-rc1 | Date: April 2026

### Package Contents

```
airpay-academy-v1.0.0-rc1/
├── theme/airpayux/          ← Custom theme (591 files)
├── plugins/
│   ├── airpay_pages/        ← Static pages (Privacy, Terms, Help, Contact)
│   ├── airpay_compliance/   ← Compliance dashboard block
│   ├── airpay_lifecycle/    ← Employee lifecycle automation
│   └── airpay_integrations/ ← Integrations hub
├── bizlms-fixes/            ← 22 patched BizLMS files
│   ├── local/*/templates/   ← jQuery AMD fixes (13 mustache files)
│   ├── local/*/lang/en/     ← Typo fixes (9 lang files)
│   └── js/                  ← Missing JS file
├── docs/                    ← All documentation (9 files)
│   ├── DEPLOYMENT-RUNBOOK.md
│   ├── PROJECT-STATE.md
│   ├── BIZLMS-ISSUES.md
│   └── *.pdf, *.xlsx
├── config/                  ← SQL config changes
│   └── post-deploy.sql
├── scripts/                 ← Deployment helper scripts
│   └── deploy.ps1
└── README.md               ← This file
```

### Prerequisites

- Moodle 4.5.x on production server
- BizLMS multi-tenant plugin installed
- PHP 8.2+, MySQL 8.0+ (or MariaDB 10.11+)
- Server file access (SSH or FTP)
- Site admin credentials

### Deployment Steps

See `docs/DEPLOYMENT-RUNBOOK.md` for the full 21-point checklist.

Quick summary:
1. **Backup** production database and theme directory
2. **Copy** `theme/airpayux/` to production `moodle/theme/`
3. **Copy** each plugin from `plugins/` to production `moodle/local/` or `moodle/blocks/`
4. **Copy** BizLMS fixes from `bizlms-fixes/` to matching production paths
5. **Run** SQL from `config/post-deploy.sql`
6. Navigate to **Site Admin > Notifications** (triggers DB upgrades)
7. **Activate** airpayux theme: Site Admin > Appearance > Themes
8. **Purge caches**: Site Admin > Development > Purge all caches
9. **Verify** using the 21-point post-deploy checklist

### Rollback

If anything breaks:
1. Restore backed-up theme directory
2. Restore backed-up database
3. Purge caches

### Tested With

- 2,871 production users across 3 tenants
- 411 real courses with SCORM content
- 7 user roles verified (Siteadmin, L&D Admin, Manager, Employee, External, ZEEA, Guest)
- 144 validation items across UI/UX, Features, and Business Logic
- Dark mode on all pages
- Mobile responsive (768px, 590px, 480px breakpoints)

### Git Reference

- Repository: nitin-rajput-learning-tech/Airpay-Academy2.0
- Branch: production
- Tag: v1.0.0-rc1
- Commit: See `git log --oneline -1 v1.0.0-rc1`
