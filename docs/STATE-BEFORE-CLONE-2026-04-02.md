# State Snapshot — Before GitHub Clone
**Date:** 2026-04-02
**Action:** About to clone nitin-rajput-learning-tech/Moodle-Enhancement (production branch) to XAMPP

## Current State
- XAMPP installed at C:\xampp\ — htdocs has default files, NO Moodle deployed
- Moodle Backup folder at D:\Claude Local\Moodle Backup\ contains:
  - 01-production-codebase/html/ — Original Moodle 4.1.2 (eAbyas BizLMS, intact with git)
  - 02-enhancement-code/ — Bug fixes, modified files, 12 BizLMS blocks, new pages
  - 03-prototypes/preview/ — 23 HTML prototypes
  - 04-knowledge/ — All project intelligence files
  - 05-screenshots/ — 56 production screenshots
  - moodle-latest-501.zip — Moodle 5.0.1 download
  - "Moodle Git" folder — COPYING FROM ONEDRIVE (2.5GB, may not be complete)

## Rollback Plan
If the GitHub clone breaks things:
1. Delete C:\xampp\htdocs\moodle\ entirely
2. Copy 01-production-codebase/html/ to C:\xampp\htdocs\moodle\ (original 4.1.2)
3. That gives us a known-good Moodle 4.1.2 with BizLMS

## What We're About To Do
1. Clone github.com/nitin-rajput-learning-tech/Moodle-Enhancement (production branch)
2. Target: C:\xampp\htdocs\moodle\
3. This should give us Moodle 4.5.10+ with 22 enhancement commits
