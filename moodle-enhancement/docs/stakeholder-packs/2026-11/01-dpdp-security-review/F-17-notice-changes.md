# F-17: changes to the DPDP and privacy notices (draft for Legal and DPO review)

Date: 2026-11-21. Review finding F-17 in
`01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` (this folder).

This file holds the itemised change list and the reasons for each change. It
used to sit in HTML and Mustache comments inside the served files. Those
comments went to every visitor, including visitors who were not logged in, and
the raw files could be fetched directly from the web root. That disclosed an
internal gap analysis (CWE-615). The served files now carry a one-line marker
only:

```
DRAFT - SUBJECT TO LEGAL/DPO REVIEW (F-17, 2026-11-21). Change list: moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/F-17-notice-changes.md
```

When Legal and the DPO approve the wording, remove the marker from each file
listed below and set the "Last updated" dates (the page bodies and the footer
printed by `local/sentientia_pages/index.php` both say "April 2026").

Files (each exists twice, `moodle-enhancement/local/...` and `local/...`, kept
byte-identical):

| File | Marker form |
|------|-------------|
| `local/sentientia_pages/pages/dpdp.html` | HTML comment |
| `local/sentientia_pages/pages/privacy.html` | HTML comment |
| `local/sentientia_privacy/templates/self_service.mustache` | Mustache comment |
| `local/sentientia_privacy/templates/admin_panel.mustache` | Mustache comment |

Defence in depth: `local/sentientia_pages/index.php` now strips HTML comments
from a page before it prints it, so the marker does not reach the rendered
page. The raw `.html` file is still fetchable from the web root, which is why
the served files keep only the one-line marker.

## 1. `pages/dpdp.html`

1. Badge "DPDP Act 2023 Compliant" changed to "Notice under the DPDP Act,
   2023". Compliance has not been assessed or certified.
2. Section 1: "Airpay Academy ... operates as a Data Fiduciary" changed so
   that Airpay Payment Services Pvt Ltd is named as the Data Fiduciary that
   operates Airpay Academy (matches the self-service notice). Legal to confirm.
3. Section 3 heading "Lawful Purpose (Section 4)" changed to "Grounds for
   Processing (Sections 4, 6 and 7)". Employees: "based on the employment
   contract" changed to "for purposes of employment, a legitimate use under
   Section 7(i)". Public users: consent, Sections 4(1)(a) and 6.
4. Section 4: "Right to Correction (Section 11)" changed to Section 12 (s.11
   is the right to access information; s.12 is correction and erasure).
   "Right to Erasure (Section 12)" kept. Added the right to nominate
   (Section 14), which the heading "Sections 11-14" already covered.
   Access: "a copy of all your personal data" changed to "a summary of the
   personal data we process" (what s.11(1)(a) grants). Erasure: "all
   associated data" changed to "personal data, except where we must keep it
   ... to comply with law" (s.12(3)). The employee line "Employee data
   governed by employment contracts" changed so that employees contact the
   DPO.
5. Section 4: added the route to the Data Protection Board of India after the
   grievance process (Section 13(3)).
6. Section 5: "Employees ... (Section 4, lawful purpose)" changed to
   Section 7(i).
7. Section 5: removed "Response Times: 72 hours for data access requests,
   30 days for deletion requests". No process or SLA stands behind those
   periods. Replaced with what the platform does: the export is a JSON file
   kept for 72 hours once prepared (`privacy_manager::process_download`), and
   deletion requests are reviewed by an administrator. The grievance response
   period (DPDP Rules 2025) is for the DPO to set and publish.
8. Section 6: "Encryption in transit (TLS 1.2+) and at rest" changed to
   "encrypted in transit (HTTPS)". The TLS version and encryption at rest are
   server settings that the repository does not evidence. IT to confirm
   before either is stated.
9. Section 6: removed "Regular security audits and vulnerability
   assessments". There is no audit programme on record.
10. Section 6: removed "Secure hosting on AWS infrastructure within India".
    The repository does not evidence the hosting location. IT to confirm.
11. Section 6: "multi-tenant data isolation" changed to "separation of data
    between the organisations that use the platform"; added logging of
    activity.
12. Section 7: removed "duration of your account plus 7 years", "Deleted
    within 90 days of account deletion request", "Certificates: Retained
    permanently" and "duration of employment plus 7 years per applicable
    labour laws". No retention schedule is configured or approved. Replaced
    with what an approved deletion does (see section 5 below) and a statement
    that retention periods are being set.
13. The Data Protection Officer mailbox academy@airpay.co.in is kept as
    published before. The DPO is to confirm it is the right contact (s.8(9)).

Still open for the DPO (DPDP Rules 2025, Rule 3): an itemised notice per
purpose, how to withdraw consent, and the published grievance period.

## 2. `pages/privacy.html`

1. Section 4: "encryption in transit (TLS 1.2+)" changed to "encrypted in
   transit (HTTPS)". The TLS version is a server setting the repository does
   not evidence.
2. Section 4: removed "regular security audits". There is no audit programme
   on record.
3. Section 4: removed "Data is stored on secure servers within India in
   compliance with the Digital Personal Data Protection Act (DPDP) 2023". The
   hosting location is not evidenced and compliance has not been assessed or
   certified. Added separation of data between organisations and activity
   logging, both of which the platform does.
4. Section 5: "Right of Access (Section 11): Request a copy of all your
   personal data" changed to "a summary of the personal data we process"
   (what s.11(1)(a) grants; the self-service download is described
   separately).
5. Section 5: "Right to Correction" now cites Section 12. Erasure kept at
   Section 12, with the s.12(3) exception for data kept to comply with law.
6. Section 5: added the right to nominate (Section 14) and the route to the
   Data Protection Board of India after the grievance process (s.13(3)).
7. Section 5: "processed under employment contracts (DPDP Act Section 4)"
   changed to "for purposes of employment, a legitimate use under
   Section 7(i)".
8. Section 5: removed "Response time: 72 hours for data access, 30 days for
   deletion". No process or SLA stands behind those periods. Replaced with
   what the platform does: the export file stays available for 72 hours once
   prepared (`privacy_manager::process_download`); deletion requests are
   reviewed by an administrator.
9. Section 6: removed "retained for the duration of employment plus 7 years
   for compliance" and "Account data is deleted within 90 days of
   deactivation". No retention schedule is configured or approved. Replaced
   with what an approved deletion does (see section 5 below) and a statement
   that retention periods are being set.
10. The Data Protection Officer mailbox academy@airpay.co.in is kept as
    published before. The DPO is to confirm it is the right contact (s.8(9)).

## 3. `templates/self_service.mustache` (local_sentientia_privacy)

1. Deletion section: removed "Processing typically takes 3-5 business days as
   required under DPDP Act Section 12(3)". Section 12(3) sets no period and
   nothing enforces one. It now says what happens: an administrator reviews
   the request before it is processed.
2. Notice: removed "Response time: 72 hours for data access, 30 days for
   deletion". No process or SLA stands behind those periods. The 72-hour
   availability of the prepared export file is already stated in the download
   section and is enforced in `privacy_manager::process_download`.

The grievance response period (DPDP Rules 2025) is for the DPO to set and
publish.

Not changed in this pass, for the DPO to review: the bullet list in the
"Delete My Account" section ("Anonymizes your personal information (name,
email, phone)", "Removes your learning history and gamification data",
"Preserves anonymized completion records for audit purposes"). It describes
the deletion in the same "anonymised" terms that section 5 below corrects on
the static pages, and "removes your learning history" sits badly with
"preserves completion records".

## 4. `templates/admin_panel.mustache` (local_sentientia_privacy)

1. Header: removed "Response deadline: 72 hours (data access), 30 days
   (deletion)". Replaced with: data access (Section 11) and erasure
   (Section 12) requests, response periods set by the DPO (DPDP Rules 2025).
2. Footer box: removed "DPDP Act 2023 Compliance: Data access requests
   (Section 11) must be fulfilled within 72 hours. Account deletion requests
   (Section 12) must be processed within 30 days." Sections 11 and 12 set no
   such periods. Replaced with: response periods are set and published by the
   DPO; the Act itself does not fix them.

These were the same false statutory deadlines that the first F-17 pass removed
from the self-service page and the static pages; the admin panel still showed
them to administrators.

## 5. Deletion wording on the static pages (dpdp.html section 7, privacy.html section 6)

The first F-17 draft said that on approval "the personal details on your
account are anonymised and the account is suspended", with learning records
kept "in anonymised form". That was itself an unevidenced claim:

- Until F-18, `privacy_manager::process_deletion()` Step 1 cleared only the
  names, e-mail, username, phones, address, city, description, image alt text
  and 8 of the 37 BizLMS `open_*` columns. It left the id number, institution,
  department, country, last IP, the middle/alternate/phonetic names, the
  profile URL, the profile picture, custom profile fields and 29 `open_*`
  columns (open_path, address parts, qualification, open_paymentinfo, ...).
- Step 3 marks the account deleted (core `delete_user()` since F-18; before
  that a direct `deleted = 1`), so the account is closed, not suspended.
- Learning records stay keyed to the account row. While that row still holds
  identifiers they are not "anonymised".

The wording now says only what the code does on either route (the
self-service flow for public users and the stock `tool_dataprivacy` route for
employees): the account is closed, the name, e-mail, username, phone numbers,
postal address and main employment details are removed or replaced, and
learning records such as completions and grades are kept for audit, linked to
the closed account rather than to the person's name.

Dependency: this wording relies on F-18 and F-23. The F-18 change clears every
`open_*` column and the remaining core profile columns and runs core
`delete_user()`. Until F-18 and F-23 are deployed and verified, other profile
fields (custom profile field data, the profile picture and the columns listed
above) are not yet cleared on the self-service route, and the notice must not
promise more than "main employment details". Do not widen the wording to
"all personal details" or "anonymised" before then.

Tests: `local/sentientia_pages/tests/static_notice_claims_test.php` checks
that neither page shows the unevidenced phrases, that the section citations
are right, that the draft marker is in the source and not on screen, and that
the old "anonymised" deletion wording does not come back.
