# Code review bundle manifest

**Sentientia LMS: DPDP code review bundle of 20 November 2026, contents and integrity record**

| Field | Value |
|---|---|
| Prepared for | Information Security team (Airpay Payment Services) and SISA (external security assessor, under a non-disclosure agreement, NDA) |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.2 |
| Date | 21 November 2026 |
| Status | Draft for review |
| Pack | 01 DPDP and security review: 01a design and security review, 01b compliance mapping, 01c (this document) |
| Product state | Sentientia LMS is not live. UAT (academy2.airpay.ninja) holds a fresh installation with synthetic test personas and sample courses; development machines hold a copy of the production database imported for testing only (about 2,871 users). Tenants: Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania). First real user load is the production cutover, after the live-backup rehearsal (Stage B). |

Confidential: Airpay Payment Services internal. Not for external distribution, except to SISA (external security assessor) under NDA.

---

## 1. Document control

### 1.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Technical writer for the Head of Learning & Development | Bundle built and manifest issued (withdrawn) |
| 1.1 | 20 November 2026 | Technical writer for the Head of Learning & Development | Bundle rebuilt after internal review: redacted review copies of records and comments; working records and rehearsal tooling removed; substrate, AI client, theme test and three core-modification records added; UAT difference column; new hashes |
| 1.2 | 21 November 2026 | Technical writer for the Head of Learning & Development | Bundle version 1.2 after a second internal review: 11 AI client files added (rows 198 to 208, for 01a F-21, F-22 and F-28); residue removed from two certificate core-modification records (a quoted approval, a sentence on internal tool permissions, a local site address); row 10 corrected to an exact copy; new zip and manifest hashes |

### 1.2 Reviewers and sign-off

| Role | Sign-off sought |
|---|---|
| Head of Information Security | Bundle contents are adequate for the review scope in 01a section 3 |
| Lead assessor, SISA (under NDA) | Bundle received and verified against the hashes below |
| Platform engineer | Bundle matches the repository at the stated basis |

### 1.3 Related documents

| Document | Location |
|---|---|
| 01a DPDP module: technical design and security review | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` |
| 01b DPDP Act 2023 and DPDP Rules 2025: compliance mapping | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/01b-DPDP-COMPLIANCE-MAPPING.md` |
| Bundle manifest (inside the bundle) | `MANIFEST.md` at the bundle root |

---

## 2. Executive summary

- Bundle `sentientia-dpdp-code-review-2026-11-20`, **version 1.2**, contains **208 files, 43,792 lines**, plus `MANIFEST.md`. It holds the complete DPDP plugin, the privacy providers of all 57 product components, all 46 plugin schemas, the tenant-isolation, feature-flag and core-table substrate classes, the AI clients cited in 01a F-21, F-22 and F-28 (assistant chat and quick-action chips, agent, Moodle core AI bridge and its demonstration page, gateway, AI quiz client and smoke test, authoring generator, skills, translation and recommendations clients), the notice and consent code, 50 PHPUnit test files with one fixture (502 test methods by static count), and copies of ADR-035, ADR-031, the erasure rehearsal results, the draft core bug report and four core-modification records. 7 of these 8 records are redacted review copies; the 4 June 2026 substrate record is an exact copy.
- **24 files are redacted review copies.** Only comments, record text and two test assertion messages were changed, to remove personal names, working-note decision references, local folder paths and request identifiers. No executable statement changed. Section 8.2 gives the repository hash of each, so a reviewer with repository access can match it.
- Versions 1.0 and 1.1 of 20 November 2026 are **withdrawn**. Version 1.0 carried personal names, working-note references, local paths and request identifiers in records and comments; version 1.1 still quoted an informal approval and named internal tool permissions and a local site address in two certificate records, and lacked the AI clients cited in F-22. Recipients of either version, if any, are asked to destroy it.
- It contains **no** `.env` file, `config.php`, credentials, database dump, data export, log, run output or persona file (section 5).
- Integrity: the zip `sentientia-dpdp-code-review-2026-11-20.zip` (658,338 bytes, 209 entries) has SHA-256 `98ede928e18e488ecbc93039912462135f319352ba4a02ace925500a9099c833`. Every file's SHA-256 is in section 8.
- Read-only access to the repository branch can be arranged on request, for reviewers who want to build and run the tests (01a section 19).

**Ask of the reader.** Verify the zip hash on receipt, confirm receipt to the document owner, and request repository access if you will run the tests.

---

## 3. Bundle identification

| Field | Value |
|---|---|
| Bundle folder | `sentientia-dpdp-code-review-2026-11-20/` |
| Bundle version | 1.2 of 21 November 2026 (replaces 1.1 and 1.0 of 20 November 2026) |
| Zip file | `sentientia-dpdp-code-review-2026-11-20.zip` |
| Zip SHA-256 | `98ede928e18e488ecbc93039912462135f319352ba4a02ace925500a9099c833` |
| Zip size | 658,338 bytes; 209 entries (208 files plus `MANIFEST.md`), one top-level folder |
| `MANIFEST.md` SHA-256 | `255bb8b61816e8afa8c52ca26f318e8448b9842126a37578101c0444c94b69df` |
| Built | 21 November 2026 (version 1.1 on 20 November 2026), with Python `zipfile` (deflate); hashes computed with Python `hashlib` and cross-checked with `sha256sum` |
| Repository basis | The work branch at commit `4df7d4573` plus the uncommitted working tree of 20 November 2026. The 11 files added in version 1.2 are identical at `4df7d4573`, at the UAT commit and in the working tree of 21 November 2026 |
| Code on UAT | Commit `af6e65c0e`. The DPDP plugin `local_sentientia_privacy` 1.1.0 is unchanged between the UAT commit and the bundle basis |
| Files that differ from the UAT commit `af6e65c0e` (17) | `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`; `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md`; `local/sentientia_compliance_report/db/install.xml`; `local/sentientia_courses/classes/privacy/provider.php`; `local/sentientia_courses/db/install.xml`; `local/sentientia_courses/tests/certificate_issuer_test.php`; `local/sentientia_proctoring/classes/identity/verifier_factory.php`; `local/sentientia_proctoring/classes/privacy/provider.php`; `local/sentientia_proctoring/classes/task/purge_old_recordings.php`; `local/sentientia_proctoring/db/install.xml`; `local/sentientia_proctoring/tests/quiz_integration_test.php`; `local/sentientia_proctoring/tests/secure_mode_test.php`; `local/sentientia_users/classes/privacy/provider.php`; `local/sentientia_users/db/install.xml`; `local/sentientia_users/tests/external_trainer_test.php`; `local/sentientia_users/tests/privacy_provider_test.php`; `mod/quiz/accessrule/sentientia_proctoring/db/install.xml`. Behaviour a reviewer sees on UAT may differ for these files; each is marked in the "Differs from UAT" column of section 8 |
| Files that differ from commit `4df7d4573` (9, uncommitted, Built (verified on development)) | `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`; `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md`; `local/sentientia_courses/classes/privacy/provider.php`; `local/sentientia_courses/db/install.xml`; `local/sentientia_courses/tests/certificate_issuer_test.php`; `local/sentientia_users/classes/privacy/provider.php`; `local/sentientia_users/db/install.xml`; `local/sentientia_users/tests/external_trainer_test.php`; `local/sentientia_users/tests/privacy_provider_test.php` |
| Path convention | Relative to `moodle-enhancement/` in the repository (the canonical plugin tree), except `theme/sentientia/`, which sits at the repository root |

---

## 4. What was included, and why

| Group | Inclusion rule | Files | Lines |
|---|---|---|---|
| A. DPDP plugin (`local_sentientia_privacy`), complete except tests | Rule 1 | 23 | 3,136 |
| B. Privacy providers of the other 56 product components | Rule 2 | 56 | 10,632 |
| C. Schemas of the other plugins (data inventory evidence) | Rule 3 | 45 | 6,947 |
| D. Tenant isolation, flags, substrate, AI client, notices and other context code | Rules 4 and 5 | 14 | 3,217 |
| E. PHPUnit tests and fixture | Rule 7 | 51 | 15,326 |
| F. Records (7 redacted review copies, 1 exact copy) | Rule 6 | 8 | 1,380 |
| G. AI clients (added in version 1.2) | Rule 8 | 11 | 3,154 |
| **Total** | | **208** | **43,792** |

Inclusion rules in detail:

1. **DPDP plugin.** Every file under `moodle-enhancement/local/sentientia_privacy/` (its 4 test files and fixture are in group E).
2. **Privacy providers.** Every `classes/privacy/provider.php` in the canonical tree (`local`, `blocks`, `enrol`, `payment/gateway`, `mod/quiz/accessrule`) plus `theme/sentientia/classes/privacy/provider.php`: 57 in total.
3. **Schemas.** Every `db/install.xml` of our plugins (46 in total), so reviewers can check each provider against the tables it owns.
4. **Platform tenant, erasure-related and core-table classes.** `tenant.php`, the feature-flag resolver and its state class, the runtime-table list used by the privacy guard, the platform audit log, and `local/sentientia_core/classes/substrate.php`, which adds 37 columns to the core `user` table and 18 to `course` (01a F-23).
5. **Notice, consent and data-sharing code.** Static DPDP and privacy pages, the policy setup tool, the signup service; the HRMS webhook (01a F-3); the assistant AI client (01a F-22); two proctoring files (identity verifier selection and the recording purge).
6. **Records (7 redacted review copies, 1 exact copy).** ADR-035 and ADR-031; the erasure rehearsal results; the draft upstream bug report; the four core-modification records that touch privacy: the 20 May 2026 rename (privacy metadata strings), the 4 June 2026 substrate record (personal-data columns on the core `user` table), and the two 19 November 2026 certificate-verification records (public disclosure of the holder's name, enumeration, escaping).
7. **PHPUnit tests.** Every test file in our plugins whose name contains `privacy`, `erasure` or `anonymis`, or that calls a privacy provider or the Moodle privacy API, plus the platform tenant tests and the theme provider test.
8. **AI clients (version 1.2).** Every client that 01a F-21, F-22 or F-28 cites, so the findings can be checked from the bundle: `local/sentientia_ai/classes/gateway.php`; `local/sentientia_assistant/classes/agent/agent_client.php`, `classes/core_ai_bridge.php`, `classes/hook_callbacks.php` and `ai_demo.php`; `local/sentientia_aiquiz/classes/anthropic_client.php` and `cli/live_smoke.php`; `local/sentientia_authoring/classes/course_generator.php`; and the `classes/anthropic_client.php` of `local_sentientia_skillsai`, `local_sentientia_translate` and `local_sentientia_recommendations`. Pipeline Agent 2 (`scripts/agents/`) sits outside `moodle-enhancement/` and is available through repository access.

Two core-modification records change behaviour that bears on privacy: the substrate record (columns on the core `user` table) and the certificate verify-on-arrival record (public page shows the holder's name). Neither changes Moodle's privacy subsystem itself; ADR-035 deliberately edits no core file.

---

## 5. What was excluded, the redaction and the secret scan

### 5.1 Excluded

| Excluded | Reason |
|---|---|
| `.env`, Moodle `config.php`, any credentials file | Secrets |
| Database dumps, data exports, logs, rehearsal run output | Personal data (test import of a production backup) |
| Persona files and test-account records | Credentials of test accounts |
| The plugin state card and the core-modification ledger README | Working records; their substance is in 01a |
| The rehearsal tooling (`tools/privacy-erasure-rehearsal/`: README and five scripts) | The README holds a run record with request and subject identifiers from the production-backup copy, which are pseudonymous personal data of erased data principals; the scripts carry local paths. 01a section 10 summarises the results; the tooling is available through repository access |
| Moodle core and vendored plugins | Upstream code; obtain from upstream sources |
| The rest of the plugins' code | Outside the DPDP review scope; available through repository access |

### 5.2 Redaction

Every bundled file was scanned for personal names, working-note decision references, session references, local folder paths and request or subject identifiers. 24 files had matches and are redacted review copies ("Redacted" in section 8): 7 records and 17 code, schema or plugin-README files. Names became roles ("the product owner"), working-note decision codes became "a recorded product decision", local paths became "a local working folder (not distributed)" or "the local development site", and the single request identifier became "one further request". In version 1.2, records 11 and 12 were redacted further: the quoted approval in record 11 became "a recorded product decision", the sentences on internal tool permissions became "copy the file under the change process", and a local site address became `<local site>`. ADR decision numbers (for example "ADR-038 D2") were kept because ADRs are formal records. In code, only comments, XML table comments and two test assertion messages changed; every redacted PHP file passes `php -l` and every redacted XML file parses.

What remains, by design: the model name "Claude" (an Anthropic product) in AI plugin comments and defaults; code comments in the AI gateway that refer to the AI budget addendum ("Addendum A") as signed, which are engineering notes, not a record of budget status (the AI budget is pending approval); a placeholder key prefix (`sk-ant-...`) in the help text of the AI quiz smoke test; the repository file names `CLAUDE.md` and `.claude/settings.json` (engineering rules); a functional role mailbox used as the fallback privacy contact; synthetic test data (`example.com` addresses, private-range IP addresses, a sample phone number); and upstream open-source copyright attributions.

### 5.3 Secret and personal-data scan (20 November 2026)

The bundle was scanned after redaction for:

- credential-like assignments to `password`, `passwd`, `pwd`, `token`, `secret`, `api_key` or `auth_secret` with a literal value of 8 or more characters;
- private-key headers and cloud access-key patterns;
- e-mail addresses and Indian mobile-number patterns.

Results: no real credential, key or personal data of a data principal. Credential-pattern matches were fixed test values (for example `fresh-access` in a token-vault test) and, in version 1.2, a placeholder key prefix in the AI quiz smoke test help text. The version 1.2 files were scanned again on 21 November 2026 with the same patterns and for local paths and site addresses. E-mail matches were synthetic test addresses, upstream copyright attributions, an example in a code comment and the functional role mailbox. Phone matches were a test fixture value.

---

## 6. Verifying the bundle

1. Compute the zip's SHA-256 and compare it with section 3:

```bash
sha256sum sentientia-dpdp-code-review-2026-11-20.zip
```

2. Unzip, then check any file against section 8, for example:

```bash
sha256sum sentientia-dpdp-code-review-2026-11-20/local/sentientia_privacy/classes/privacy_manager.php
```

3. To check every file, compare the output of the following with the table in `MANIFEST.md`:

```bash
cd sentientia-dpdp-code-review-2026-11-20
find . -type f ! -name MANIFEST.md -print0 | sort -z | xargs -0 sha256sum
```

4. With repository access, compare a redacted review copy with the repository file by the hash in section 8.2, then diff the two: only comments, record text and the two assertion messages differ.

---

## 7. Repository access and running the code

- Read-only access to the repository work branch can be arranged on request through the document owner. Access is granted per named reviewer and removed after the engagement.
- The bundle is a reading set. Building a test site and running the tests needs the full plugin set and the vendored `tool_certificate`; 01a section 19 gives the steps and test commands for a reviewer-owned Moodle 5.3 instance (MySQL 8.4 or later, or MariaDB 11.4 or later; PostgreSQL is not supported).
- Do not load any real or imported personal data into a review instance.

---

## 8. File manifest

### 8.1 Files

The same table is in `MANIFEST.md` inside the bundle. Lines are physical lines in the file; SHA-256 is computed over the exact bytes in the bundle. "Review copy" is "Exact" when the file is byte-identical to the repository and "Redacted" when it is a redacted review copy. The last two columns say whether the repository file differs from the UAT commit and from the basis commit ("New" means the file does not exist in that commit).

| # | Relative path | Purpose | Lines | SHA-256 (bundle copy) | Review copy | Differs from UAT `af6e65c0e` | Differs from `4df7d4573` |
|---|---|---|---|---|---|---|---|
| 1 | `blocks/sentientia_cert_health/classes/privacy/provider.php` | Privacy provider of block_sentientia_cert_health (null_provider) | 20 | `eff930ad63ed5aafe9b3ad86a2e481b64b2bef08c32552a5087882d1e9edcc35` | Exact | No | No |
| 2 | `blocks/sentientia_compliance/classes/privacy/provider.php` | Privacy provider of block_sentientia_compliance (null_provider) | 24 | `92a03fe031b5f2d87c0912cff2543366453b76e73f7e131964396fd959cf6b11` | Exact | No | No |
| 3 | `blocks/sentientia_cron_health/classes/privacy/provider.php` | Privacy provider of block_sentientia_cron_health (null_provider) | 20 | `46a6b1587466cb3fbe83d659565384ca57835ca8d1760c7809715f5f67384d0a` | Exact | No | No |
| 4 | `blocks/sentientia_leaderboard/classes/privacy/provider.php` | Privacy provider of block_sentientia_leaderboard (null_provider) | 20 | `b64828b9b3b2fb3b538b954bcfffcdda2102311eaf2a7555dc221d18b175c52d` | Exact | No | No |
| 5 | `blocks/sentientia_recommendations/classes/privacy/provider.php` | Privacy provider of block_sentientia_recommendations (null_provider) | 20 | `512a1f68f25c3d78eeb5c2cc1a14fbfe5939655c20b651394402b4e2537010fa` | Exact | No | No |
| 6 | `blocks/sentientia_trainer/classes/privacy/provider.php` | Privacy provider of block_sentientia_trainer (null_provider) | 24 | `e76c4604ab27d0c180957a0d46d80f07b2870b6d8d5e5022b75aaef3d2923ddc` | Exact | No | No |
| 7 | `docs/adr/ADR-031-cross-tenant-authority.md` | ADR-031: single cross-tenant authority, fail closed (redacted review copy) | 68 | `03c4775f596ac5756950d6500958178ffb411ee22a6dd658e182c2179a2d3a9c` | Redacted | No | No |
| 8 | `docs/adr/ADR-035-privacy-erasure-completeness.md` | ADR-035: erasure completeness decision, retention options, proof, cutover gate (redacted review copy) | 351 | `eb4ed64dd40b19f79950e95349efa6375f4061d7b1cd7ccfab09ec646c6f0993` | Redacted | No | No |
| 9 | `docs/core-mods/2026-05-20-moodle-to-sentientia-rename.md` | Core-mod record touching privacy:metadata language strings (rename) (redacted review copy) | 248 | `449d61642a0e56ea7691884ac25f0f243ee2b81865054bf3ae2b7e7a49067cb7` | Redacted | No | No |
| 10 | `docs/core-mods/2026-06-04-open-substrate-ownership.md` | Core-mod record: local_sentientia_core owns the open_* columns on core user and course tables (exact copy) | 77 | `d0931f491cdd664323119984a3283130cc229a628ee1e12567b0eb00df52a3ec` | Exact | No | No |
| 11 | `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md` | Core-mod record: tool_certificate verification page shows the result on arrival; risks include the holder's name on a public page (redacted review copy) | 264 | `e03ce5f360b176a3847a26589c8d87a9dd24e39fdb9937dd593aea5afa7d11a9` | Redacted | Yes | Yes |
| 12 | `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md` | Core-mod record: tool_certificate verification result escapes the holder's and certificate names (redacted review copy) | 171 | `7d183cee633f7fa07606870598f76f62cbda01f64086e1a1081911226da4fa6e` | Redacted | Yes | Yes |
| 13 | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` | Erasure rehearsal results, counts only (redacted review copy) | 115 | `5f08e66a0b915dfca32ef759cf8b56e031deacf007144cc1a5c9e2df31caa7b1` | Redacted | No | No |
| 14 | `docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` | Draft upstream bug report for the core contextlist truncation, not filed (redacted review copy) | 86 | `3f5e3610b41b35151996a105fdad7336cb632adda3517b22813f5170445360d2` | Redacted | No | No |
| 15 | `enrol/sentientiasub/classes/privacy/provider.php` | Privacy provider of enrol_sentientiasub (metadata provider) | 150 | `6af65057c933f01fd39983810b18d4a105f17014a7cab203fe692f0b6a46e551` | Exact | No | No |
| 16 | `enrol/sentientiasub/db/install.xml` | Schema of enrol_sentientiasub (data inventory) | 38 | `fa498e85a2061ac2a7e4d8f3f83a8a61411aad7fb6dcf136e4c767f74faca148` | Exact | No | No |
| 17 | `local/sentientia_ai/classes/privacy/provider.php` | Privacy provider of local_sentientia_ai (metadata provider) | 165 | `6188605b5891b8244ab8b15f371c29ff1a7f050e5e42e89626f784431ffb8a75` | Exact | No | No |
| 18 | `local/sentientia_ai/db/install.xml` | Schema of local_sentientia_ai (data inventory) | 50 | `c22dd264d30dc1fb53fc1b2d954bcb9f98d2377a903c936a90deb497c7a3ac36` | Exact | No | No |
| 19 | `local/sentientia_aiquiz/classes/privacy/provider.php` | Privacy provider of local_sentientia_aiquiz (metadata provider) | 172 | `ee8663b859a000d5ee759da680dc585c7a967761fc4da63807cc196fce72ee0a` | Exact | No | No |
| 20 | `local/sentientia_aiquiz/db/install.xml` | Schema of local_sentientia_aiquiz (data inventory) | 144 | `09a82ce256f76edfc2d574e6b80c5a6bd9b501eb0b6078cf2bf7815e74b14eb7` | Exact | No | No |
| 21 | `local/sentientia_aiquiz/tests/anonymised_owner_test.php` | PHPUnit: anonymised_owner_test (local_sentientia_aiquiz) | 68 | `02a749c22b64e766101ded3b89d19091a2b231373aecdff1564f4bddacac5651` | Exact | No | No |
| 22 | `local/sentientia_analytics/classes/privacy/provider.php` | Privacy provider of local_sentientia_analytics (null_provider) | 20 | `2f46d99ecfd5a028ca402d59ca990686dde6be0230c17eceaf0c0c19b963e221` | Exact | No | No |
| 23 | `local/sentientia_api/classes/privacy/provider.php` | Privacy provider of local_sentientia_api (metadata provider) | 173 | `6121eb179357544110afa4317fd443f7d9438dc18dc51ec6c3107dfcdbd4b2d3` | Exact | No | No |
| 24 | `local/sentientia_api/db/install.xml` | Schema of local_sentientia_api (data inventory) | 226 | `2a73e589ee7434bd1b28a8b6c85efa69b67e5c6dc5cf8b4097db8a1d7ec22ba7` | Exact | No | No |
| 25 | `local/sentientia_api/tests/webhooks_test.php` | PHPUnit: webhooks_test (local_sentientia_api) | 324 | `f9512d96136504bd9af423beade18a35ecf78d4ce7d94fb9a726c7dc161db222` | Exact | No | No |
| 26 | `local/sentientia_assistant/classes/ai_client.php` | AI assistant chat client: gateway routing, or a direct call with the plugin's own key; builds the learner context sent to the AI provider (finding F-22) | 447 | `f7bb32070b7041c91a56a967e1d82269ace5270a359e7fb3527daa2735cc9a8d` | Exact | No | No |
| 27 | `local/sentientia_assistant/classes/privacy/provider.php` | Privacy provider of local_sentientia_assistant (metadata provider) | 197 | `eeeae7729d3245b313aa6810c64479de65d0c88f6fe81b4bb3195fbc25235683` | Exact | No | No |
| 28 | `local/sentientia_assistant/db/install.xml` | Schema of local_sentientia_assistant (data inventory) | 78 | `2c794a676ff9aafc412bf91071dbc113bef1ddd33b5f3dc879a44f933f072c21` | Exact | No | No |
| 29 | `local/sentientia_assistant/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_assistant) | 95 | `bb5ec7f398ca16f3414c85b0aaff97bf37fbe209bda1bd5b5b71587e5c2cb76d` | Exact | No | No |
| 30 | `local/sentientia_authoring/classes/privacy/provider.php` | Privacy provider of local_sentientia_authoring (metadata provider) | 376 | `393ac0eeb4acd4eb1a4a1e37f90129ddb6f8fd30b7212a1e680ab9bedabbfff9` | Exact | No | No |
| 31 | `local/sentientia_authoring/db/install.xml` | Schema of local_sentientia_authoring (data inventory) | 432 | `f30c0b173360a7d9570f3e4c1ba12e2169c120b53b3c935df16aa9d300d9f88b` | Exact | No | No |
| 32 | `local/sentientia_authoring/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_authoring) | 267 | `53a172ffa1f4d1efccd23d4626600e5ef16cf24be46434f983a1bbc8dfca83b6` | Exact | No | No |
| 33 | `local/sentientia_authoring/tests/scd_repository_test.php` | PHPUnit: scd_repository_test (local_sentientia_authoring) | 372 | `fa1249af872526f149e05e878b229960ebfee838b2c9facbcb90997fc76a040a` | Exact | No | No |
| 34 | `local/sentientia_calendar/classes/privacy/provider.php` | Privacy provider of local_sentientia_calendar (metadata provider) | 249 | `15bb187dd88d1d3905fa944bf06db302544e04b2643937dc427f279bd0b4683b` | Exact | No | No |
| 35 | `local/sentientia_calendar/db/install.xml` | Schema of local_sentientia_calendar (data inventory) | 142 | `2f25d53c7759c073526c06e50e8abf1cef087136419750eadaed71f98b7333eb` | Exact | No | No |
| 36 | `local/sentientia_calendar/tests/token_vault_test.php` | PHPUnit: token_vault_test (local_sentientia_calendar) | 526 | `4d5b4800b610c1b950844fbe00c67155fa3125443fcef4ee61ff497a45971701` | Exact | No | No |
| 37 | `local/sentientia_cart/classes/privacy/provider.php` | Privacy provider of local_sentientia_cart (metadata provider) | 657 | `baa09a5e588b3a48c8ab8cdee0487c65d8772e1734bd72f03339820f1b796c7b` | Redacted | No | No |
| 38 | `local/sentientia_cart/db/install.xml` | Schema of local_sentientia_cart (data inventory) | 347 | `8288634cec29e9c1c9defb7b0ad460710d26499af498f3b581b6c0a5115e3fce` | Exact | No | No |
| 39 | `local/sentientia_cart/tests/gst_privacy_test.php` | PHPUnit: gst_privacy_test (local_sentientia_cart) | 359 | `4f0a38d25986e7c7c2770aafacdc69fe1f957ae33439a0c794ef1d1c2cc0acdc` | Exact | No | No |
| 40 | `local/sentientia_cart/tests/imported_privacy_test.php` | PHPUnit: imported_privacy_test (local_sentientia_cart) | 217 | `0a1d00927ac8b97fb45c329966ee4e330505a64d9de1e36f923e95866cc04737` | Exact | No | No |
| 41 | `local/sentientia_catalog/classes/privacy/provider.php` | Privacy provider of local_sentientia_catalog (null_provider) | 20 | `f54e1c36f00d9a054601bbe5f6bc8764d0c6a527b61c98a315cb519c1d207937` | Exact | No | No |
| 42 | `local/sentientia_challenge/classes/privacy/provider.php` | Privacy provider of local_sentientia_challenge (metadata provider) | 203 | `1c9372628d62b3169119c1ceccf0097e8929ce6a9272663c4ce761bda036d2f9` | Exact | No | No |
| 43 | `local/sentientia_challenge/db/install.xml` | Schema of local_sentientia_challenge (data inventory) | 110 | `05b406003c30e6bc8989b136c7def58b8ecb70d5ad35000f739ca23fd5bf4da8` | Exact | No | No |
| 44 | `local/sentientia_challenge/tests/privacy/provider_test.php` | PHPUnit: provider_test (local_sentientia_challenge) | 127 | `caa42f46a10a72fa382f9cbf1752506d8b2467546a3c68e6ec0be83826b2fc69` | Exact | No | No |
| 45 | `local/sentientia_challenge/tests/privacy_anonymise_test.php` | PHPUnit: privacy_anonymise_test (local_sentientia_challenge) | 122 | `a37eddd0bb90ca8abc21a053405095fda3ba298768e01b33f67ac9e9279bfc75` | Exact | No | No |
| 46 | `local/sentientia_classroom/classes/privacy/provider.php` | Privacy provider of local_sentientia_classroom (metadata provider) | 374 | `91e76500f435119c7c295e67621861ada514e147db0eeb211c5650b1d1026e7d` | Exact | No | No |
| 47 | `local/sentientia_classroom/db/install.xml` | Schema of local_sentientia_classroom (data inventory) | 230 | `11a9bc291314ece146ca0ae0e80ed3d7c994424011c9c6040a46e65191fe3a0f` | Exact | No | No |
| 48 | `local/sentientia_classroom/tests/bizlms_import_test.php` | PHPUnit: bizlms_import_test (local_sentientia_classroom) | 1151 | `734a841443585bac80716b6ef251b2f0358bcf88acf8478d509c7b4194ad32aa` | Exact | No | No |
| 49 | `local/sentientia_classroom/tests/privacy_waitlist_test.php` | PHPUnit: privacy_waitlist_test (local_sentientia_classroom) | 264 | `957223e5a50eda4760ad9e6e8cbe60f2828d22b91314709176fc5a4893509b76` | Exact | No | No |
| 50 | `local/sentientia_compliance_report/classes/privacy/provider.php` | Privacy provider of local_sentientia_compliance_report (metadata provider) | 360 | `cfbf1493c82ffa1b30cafdc86b5de61b953b299bfbf2dc745e9c1d9d80c5186f` | Exact | No | No |
| 51 | `local/sentientia_compliance_report/db/install.xml` | Schema of local_sentientia_compliance_report (data inventory) | 106 | `3024db108f7c4e4447aa4e67d896977a705bb0a147754f01b1d56aec47e93cc6` | Exact | Yes | No |
| 52 | `local/sentientia_compliance_report/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_compliance_report) | 304 | `10dd486d5300f2ade0c3959742fb23c47c2941c367308819b6f1c5e58b1a9418` | Exact | No | No |
| 53 | `local/sentientia_content_market/classes/privacy/provider.php` | Privacy provider of local_sentientia_content_market (null_provider) | 23 | `d0af2322a00f1a452ae60241dba97790b8e10c265b0a42ee6b61c292f6d9b005` | Exact | No | No |
| 54 | `local/sentientia_content_market/db/install.xml` | Schema of local_sentientia_content_market (data inventory) | 109 | `56e96dd88f7c5cf823ed903631e8189e2372ddaeb4f4d2ce7c8bcab9bfd6d875` | Exact | No | No |
| 55 | `local/sentientia_core/classes/privacy/provider.php` | Privacy provider of local_sentientia_core (metadata provider) | 288 | `c558e2600556cfda1b1c073e8f40f617eb9aa94a04ff315c4b81ab7278224a84` | Exact | No | No |
| 56 | `local/sentientia_core/classes/substrate.php` | Tenant substrate: adds 37 open_* columns to the core user table and 18 to course at install and upgrade (finding F-23) | 158 | `20de8bcf2f627cfbc788fd6ad725ad5e3e66c2ef183a7b89ddfc7c181f635fcb` | Exact | No | No |
| 57 | `local/sentientia_core/db/install.xml` | Schema of local_sentientia_core (data inventory) | 166 | `1a1bace8a7c53896a48a5b4e247626147ea8171515fcaad1ffee143a49e347d9` | Exact | No | No |
| 58 | `local/sentientia_core/tests/admin_log_test.php` | PHPUnit: admin_log_test (local_sentientia_core) | 485 | `4842a606167f8968c5a6c4471620cedb1a28732ff5c44831e13c5b6f2cfc4b97` | Exact | No | No |
| 59 | `local/sentientia_core/tests/privacy_course_creator_test.php` | PHPUnit: privacy_course_creator_test (local_sentientia_core) | 137 | `cacd653d6a59f0e6182fd668ca52fbb8ed0572952145c71087afa10df4038572` | Exact | No | No |
| 60 | `local/sentientia_courses/classes/privacy/provider.php` | Privacy provider of local_sentientia_courses (metadata provider) | 410 | `a32d08e734e87d55d6a3942b6b4df9f7cdba021c1ddc7f0793d51086c61ddaf4` | Exact | Yes | Yes |
| 61 | `local/sentientia_courses/db/install.xml` | Schema of local_sentientia_courses (data inventory) | 319 | `6383861269c1509fdc462a7060800ab9aee4099c0fc7eb1a5730eddef5e5fe07` | Redacted | Yes | Yes |
| 62 | `local/sentientia_courses/tests/certificate_issuer_test.php` | PHPUnit: certificate_issuer_test (local_sentientia_courses) | 559 | `764f9c30f39ea61515ebc341caccf08119f4e0051888a4b67c9d3301c5d43618` | Redacted | New | New |
| 63 | `local/sentientia_emails/classes/privacy/provider.php` | Privacy provider of local_sentientia_emails (metadata provider) | 247 | `583a6e55df3d66c268348cdbd6d26684dfc00652d50db72d929f487766198916` | Exact | No | No |
| 64 | `local/sentientia_emails/db/install.xml` | Schema of local_sentientia_emails (data inventory) | 162 | `81869dcd07826bd8dc69253a9fa74c4de0ac698c3355a7f22e75b83544c17889` | Exact | No | No |
| 65 | `local/sentientia_emails/tests/privacy_actor_columns_test.php` | PHPUnit: privacy_actor_columns_test (local_sentientia_emails) | 165 | `b39a5e278bece70dcdfebf08fb6547886886c59997346fc412c6b385a69acfc4` | Exact | No | No |
| 66 | `local/sentientia_emails/tests/privacy_imported_history_test.php` | PHPUnit: privacy_imported_history_test (local_sentientia_emails) | 178 | `e0a9916ef296fcff3de79cbd78ea6e2c86954732d41fbc0c11b2a3ba063c2df9` | Exact | No | No |
| 67 | `local/sentientia_evaluation/classes/privacy/provider.php` | Privacy provider of local_sentientia_evaluation (metadata provider) | 262 | `c60a247117daba179fbe10ea2de242f33ed2049ed9430a17d3308cec31a8a071` | Exact | No | No |
| 68 | `local/sentientia_evaluation/db/install.xml` | Schema of local_sentientia_evaluation (data inventory) | 196 | `13c87ab3c268216ff527e8dc8ad8c3a4865d31c0f2c65e0184387f0150cd3d7c` | Exact | No | No |
| 69 | `local/sentientia_evaluation/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_evaluation) | 131 | `a8b8f030ba66f2ddcf220818141f42ebab84d70b587e19fde0146a4ede242922` | Exact | No | No |
| 70 | `local/sentientia_evaluation/tests/privacy_subject_test.php` | PHPUnit: privacy_subject_test (local_sentientia_evaluation) | 147 | `e962314b6bc2240d0d8f8bc22ee1f43668dd474267b06552534d2d6e40bdb0b4` | Exact | No | No |
| 71 | `local/sentientia_exams/classes/privacy/provider.php` | Privacy provider of local_sentientia_exams (metadata provider) | 177 | `854562ae634be0eae31316a6d3c2e25490b9fa90dba1bbae6ab43cec57354811` | Exact | No | No |
| 72 | `local/sentientia_exams/db/install.xml` | Schema of local_sentientia_exams (data inventory) | 64 | `df2be498eede0a20a17d73b9d1b806e4753b14ba9ccc13227ed32aec9b7e052b` | Exact | No | No |
| 73 | `local/sentientia_gamification/classes/privacy/provider.php` | Privacy provider of local_sentientia_gamification (metadata provider) | 213 | `049ea532ddffaf746b3b6801e0a37baaa800e0897b9a3720827ffb5b0a4036a8` | Exact | No | No |
| 74 | `local/sentientia_gamification/db/install.xml` | Schema of local_sentientia_gamification (data inventory) | 101 | `7298bec0adbe2a9532c84f8130fdef38d94cc2b17b6f11da7e8665c1c62d68c3` | Exact | No | No |
| 75 | `local/sentientia_integrations/classes/privacy/provider.php` | Privacy provider of local_sentientia_integrations (null_provider) | 20 | `67df285dd4a8a87fbf2614b4be7daa9e5cc1306b60e66b5dfebc323016a06275` | Exact | No | No |
| 76 | `local/sentientia_integrations/db/install.xml` | Schema of local_sentientia_integrations (data inventory) | 32 | `1fbe3a737614dc8284491af79a984fa610b2625adfc67abc2fb88f6350d61eb7` | Exact | No | No |
| 77 | `local/sentientia_integrations/webhook.php` | Inbound HRMS webhook; stores raw payload (finding F-3) | 86 | `4291eb1ddcb96a279998a881382f0d99d78ce324de01c1907522086497f25798` | Exact | No | No |
| 78 | `local/sentientia_leaderboard/classes/privacy/provider.php` | Privacy provider of local_sentientia_leaderboard (metadata provider) | 207 | `3edcbea69c1eed4ca80463093cc4bf6b52f9350632acad6b5dd7bbab644dbb1e` | Exact | No | No |
| 79 | `local/sentientia_leaderboard/db/install.xml` | Schema of local_sentientia_leaderboard (data inventory) | 246 | `4f0faeae65e8b03250f05d0016774e594f9866352f33d895e8d026952d6fd25d` | Exact | No | No |
| 80 | `local/sentientia_learningpath/classes/privacy/provider.php` | Privacy provider of local_sentientia_learningpath (metadata provider) | 328 | `aea9dcf72d7c15aff5e192dfe1edf3b09f7cfd09c171cb0c2a06514eb06a13da` | Exact | No | No |
| 81 | `local/sentientia_learningpath/db/install.xml` | Schema of local_sentientia_learningpath (data inventory) | 186 | `249a38c43edbe0e50bb94fa5e9676056da23ba0b725948cce72194e13d9866a4` | Exact | No | No |
| 82 | `local/sentientia_learningpath/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_learningpath) | 228 | `e73ee2423c462b731d42d4b5ee3b393466df307dd0bff1016ea69bcaa13da777` | Exact | No | No |
| 83 | `local/sentientia_lifecycle/classes/privacy/provider.php` | Privacy provider of local_sentientia_lifecycle (null_provider) | 20 | `8d9f679fc922ecefd9ed016d0ca0e0e66e9afcbd3e0d2ab08a187a0304fa5120` | Exact | No | No |
| 84 | `local/sentientia_live/classes/privacy/provider.php` | Privacy provider of local_sentientia_live (metadata provider) | 320 | `af0b566e2da294ed0201c28c6cc37fe80ca100ba45127982a21695d933dcffb3` | Exact | No | No |
| 85 | `local/sentientia_live/db/install.xml` | Schema of local_sentientia_live (data inventory) | 298 | `5a7bb18d48f6da68562185049215668b29d8fdd3db6a53332fb97c40ca7bd517` | Exact | No | No |
| 86 | `local/sentientia_m365/classes/privacy/provider.php` | Privacy provider of local_sentientia_m365 (metadata provider) | 206 | `a375e32a8bbbb3ed8e7f07a743087f2ef92343a300eba598fbda9eed3fb4747d` | Exact | No | No |
| 87 | `local/sentientia_m365/db/install.xml` | Schema of local_sentientia_m365 (data inventory) | 80 | `dc170c0e84820c4fca642c2cc23d0575d82f9d075b15f307e88929c0a727244d` | Exact | No | No |
| 88 | `local/sentientia_m365/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_m365) | 189 | `4e48bdca4b5521640223562b060502efb45535b3d50ea9cc9feb8692dd212511` | Exact | No | No |
| 89 | `local/sentientia_manager/classes/privacy/provider.php` | Privacy provider of local_sentientia_manager (metadata provider) | 192 | `920ba40fce64f0d50bde2bde3eed6ce01b533959d3d46b11d07e8fc48ddbb514` | Exact | No | No |
| 90 | `local/sentientia_manager/db/install.xml` | Schema of local_sentientia_manager (data inventory) | 81 | `2474a8bda93ec8dbb6a8f85d11924dac8d03438c7f40a9e4d12ae2670b4fe9e4` | Exact | No | No |
| 91 | `local/sentientia_manager/tests/privacy/provider_test.php` | PHPUnit: provider_test (local_sentientia_manager) | 159 | `f9b340935ea70db91d65ba6136c678155d4007c50a654f69d2f97c9da80e827e` | Exact | No | No |
| 92 | `local/sentientia_notifications/classes/privacy/provider.php` | Privacy provider of local_sentientia_notifications (metadata provider) | 126 | `d22e4f0e344c810e07d518204fa2f2d7ba7490e403c2ba8bbedb46a488bb5ff8` | Exact | No | No |
| 93 | `local/sentientia_notifications/db/install.xml` | Schema of local_sentientia_notifications (data inventory) | 89 | `e4afd0117b9ef6764b4dcd7dfc9cd2a60ac8f5b694e75ddd111389ab9d2e049d` | Exact | No | No |
| 94 | `local/sentientia_org/classes/privacy/provider.php` | Privacy provider of local_sentientia_org (metadata provider) | 195 | `0a8326ea9fb3a478e4dc761bfe41c9e705c8ad74b5ee87aa45a170de02c4b70c` | Exact | No | No |
| 95 | `local/sentientia_org/db/install.xml` | Schema of local_sentientia_org (data inventory) | 97 | `391a7c2c67c35bb924942e30663814d98d62578bed27a9843f201788a760b4d2` | Exact | No | No |
| 96 | `local/sentientia_org/tests/privacy_cohort_scope_test.php` | PHPUnit: privacy_cohort_scope_test (local_sentientia_org) | 145 | `640a91421070a0fe95ecc4eead5733610c6e24606c1de60698c0f5cc47588532` | Exact | No | No |
| 97 | `local/sentientia_pages/classes/privacy/provider.php` | Privacy provider of local_sentientia_pages (null_provider) | 25 | `a1e91427fb2adecc9f316247872a8128fe628f3fbc013bda9d006ca07f98b0ae` | Exact | No | No |
| 98 | `local/sentientia_pages/cli/setup_policies.php` | CLI that wires tool_policy and records acceptances for existing users (finding in 01a; must not be run as is) | 118 | `1ba5779fe1ab9d9adb7f54af8577d976a12d76e53f2631fd155019642e44e80a` | Exact | No | No |
| 99 | `local/sentientia_pages/pages/dpdp.html` | Static DPDP notice page (content under review, see 01a findings) | 89 | `97e626e2b2089f83c071e385c8aa987552dc57a9d2572485aeb8a5776cf38b54` | Exact | No | No |
| 100 | `local/sentientia_pages/pages/privacy.html` | Static privacy policy page (content under review) | 55 | `f240198ce1186dfdf2520e2023c81996216d335ec0e3da8e627fb45269e11da5` | Exact | No | No |
| 101 | `local/sentientia_platform/classes/audit_log.php` | Platform audit log helper | 329 | `cd38099f745cb409cc7bdf8bc166e1c6f8ab5d937524f95ab683a3c6948475e6` | Redacted | No | No |
| 102 | `local/sentientia_platform/classes/feature_flags.php` | Feature-flag resolver used by the ADR-035 flags | 682 | `166422202f0e3baf5ffd39d7e63347feb7c81ab730e64b3fb3f7ba40662b48b7` | Redacted | No | No |
| 103 | `local/sentientia_platform/classes/local/feature_flags_state.php` | Flag override snapshot state | 45 | `740d47a50d56a000783645de725ff644ae4a84fc223c9a1e666aa6fa890aeefc` | Exact | No | No |
| 104 | `local/sentientia_platform/classes/privacy/provider.php` | Privacy provider of local_sentientia_platform (metadata provider) | 507 | `7f25cb91a77b96e0af097bb2aba743245ffd7d83b4353186f86ccf27a96671bb` | Exact | No | No |
| 105 | `local/sentientia_platform/classes/schema/user_type_tables.php` | Runtime-created user tables listed for the privacy coverage guard | 148 | `44333884d88f58c493d0ca29f14e095e5c8087818238bbb8e2ae0510a95cf1dd` | Exact | No | No |
| 106 | `local/sentientia_platform/classes/tenant.php` | Tenant isolation helpers (ADR-031): is_cross_tenant, scope_path, require_same_tenant_user, path_descendant_filter | 467 | `47d364594d7440e2bccfb9f875280f8fa84d034722b4f70bbe3a90812c6d4b4a` | Exact | No | No |
| 107 | `local/sentientia_platform/db/install.xml` | Schema of local_sentientia_platform (data inventory) | 390 | `77d39c849652b4177b75d5a46eaf7309199a5367493116529478a504166d950b` | Redacted | No | No |
| 108 | `local/sentientia_platform/tests/cross_tenant_test.php` | PHPUnit: cross_tenant_test (local_sentientia_platform) | 211 | `ee1ff422a73cf02b23e78834d64d513caa2419f0c53a17fe0ced2e30ca92362f` | Exact | No | No |
| 109 | `local/sentientia_platform/tests/privacy_coverage_test.php` | Structural guard: fails the build when a local plugin with a user column declares null_provider or no provider | 335 | `e3dbeb77d7d334ce2c699066900d6232ec21df788a83748ee7600c9c5740980f` | Exact | No | No |
| 110 | `local/sentientia_platform/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_platform) | 418 | `05c9ea87f5a5f8b27433257860b1eedc304a94f3517aea5d43eb6e336a2b15c9` | Exact | No | No |
| 111 | `local/sentientia_platform/tests/tenant_test.php` | PHPUnit: tenant_test (local_sentientia_platform) | 438 | `f1a4aa1aa903278b948b1484c483cd82b8087cc34a69c84d68d9cae669221b85` | Exact | No | No |
| 112 | `local/sentientia_privacy/README.md` | Plugin README: request hub, supplement, what still remains | 108 | `2a80ef4ff3aa1ecc3ebb43bb12cd88fcc4bea122a3765d20b04a8bd6b7451068` | Redacted | No | No |
| 113 | `local/sentientia_privacy/classes/event/erasure_supplemented.php` | Audit event for a supplementary erasure; counts only, no personal data | 178 | `da63775ede05e44a7714965d05558a52f0ace55d3da13cd7302e1d189413ac8c` | Exact | No | No |
| 114 | `local/sentientia_privacy/classes/local/erasure_completeness.php` | ADR-035 supplement: discovery plan, truncation self-check, apply_delete and apply_export | 790 | `e3371f2dfe7decd1824b7ffc868ab36311e367dd1208dd764423d71fe04fa763` | Redacted | No | No |
| 115 | `local/sentientia_privacy/classes/local/erasure_plan.php` | ADR-035 plan value object (iterated and missed context ids per component) | 97 | `e415ba40483b1095b801552084dbb2c4d0b17d28e5d5d2e878f0d54df67c3437` | Exact | No | No |
| 116 | `local/sentientia_privacy/classes/local/erasure_plan_store.php` | Short-lived store of a discovery plan between discovery and delete | 42 | `5a2f0a3e0a56b58c9c38c54bfe0bd3b3d286077766c9b759982970ffc7050230` | Exact | No | No |
| 117 | `local/sentientia_privacy/classes/local/forced_contextlist.php` | Context list subclass used to build hole-free lists | 38 | `e12c6c6501e7a61e2008f946803b5f9c34761b04319ec7a9c63da62a7bdc0f48` | Exact | No | No |
| 118 | `local/sentientia_privacy/classes/privacy/provider.php` | Privacy provider of the DPDP plugin; hosts the ADR-035 supplementary erasure and export (flag-gated, global scope) | 407 | `8d68480738bd73aa25fc90f1280dffcc3a9a5b5f79185cc3e2e6c9a0d9844147` | Exact | No | No |
| 119 | `local/sentientia_privacy/classes/privacy_manager.php` | DPDP request hub logic: download and deletion requests, export JSON, DPDP erasure route (process_deletion Steps 0-5), admin notification | 620 | `0f9a6bafbb7ee708162492525819d4d913630ebff305d6c762be628b29651393` | Exact | No | No |
| 120 | `local/sentientia_privacy/db/access.php` | Capabilities :manage (RISK_PERSONAL, no archetype) and :view | 18 | `1fbc87a2ee4e63d8b1e03b95d49657914ab433f2988f7b2f07ed2fd0b90fd9e2` | Exact | No | No |
| 121 | `local/sentientia_privacy/db/feature_flags.php` | Two ADR-035 flags, both default OFF | 51 | `0c06869f4ccc656e79fcbc1845f7f6dd69693063c5aa5f209a0fc91a0b48a455` | Redacted | No | No |
| 122 | `local/sentientia_privacy/db/install.xml` | Schema: local_privacy_requests, local_privacy_consent_log | 58 | `2e5d60df69a8531a2c49df7ff843be51e2435e5a44ee8958ea1caa99e7cd7d29` | Exact | No | No |
| 123 | `local/sentientia_privacy/db/messages.php` | Message provider privacy_request (popup and e-mail) | 11 | `e10df8fa369c9bf0c8a1d890399490888ef4ed44e46045840843df785cb7be76` | Exact | No | No |
| 124 | `local/sentientia_privacy/db/upgrade.php` | Upgrade steps of the DPDP plugin | 6 | `211dfbe13ad9a0f1063e93d889760a2d2470d3a439b4d94f383ad272be1e1cf7` | Exact | No | No |
| 125 | `local/sentientia_privacy/index.php` | Self-service page (download, deletion request), employee notice, and the cross-tenant DPO admin panel (approve = erase, reject) | 320 | `a992cf8fbe11d4b01164256cb236b736581f4df43ac78457e0399af75f154412` | Exact | No | No |
| 126 | `local/sentientia_privacy/lang/en/local_sentientia_privacy.php` | Language strings (en) | 47 | `42cde2851589681508e48d9be4eda53c5968436ea211d60ba1c0ce016c0b8fd0` | Exact | No | No |
| 127 | `local/sentientia_privacy/lang/hi/local_sentientia_privacy.php` | Language strings (hi) | 48 | `ab31bf31ac035596f5b4c24817a1d1ae10558d450674a5113b6912fea29de01e` | Exact | No | No |
| 128 | `local/sentientia_privacy/lang/kn/local_sentientia_privacy.php` | Language strings (kn) | 12 | `672c0eb896b247997f6f1632c8c8135901ead3546be316623237b57a077def30` | Exact | No | No |
| 129 | `local/sentientia_privacy/lang/mr/local_sentientia_privacy.php` | Language strings (mr) | 12 | `a5711fdbe15b9a8f3f0773da376381d6effe5aacfe276eb9b98aa3b5b55ceee3` | Exact | No | No |
| 130 | `local/sentientia_privacy/lang/sw/local_sentientia_privacy.php` | Language strings (sw) | 12 | `13bff5ecb1c7ba8045dee9bff1abd60a91f0eccb111fa90ded2f6971aa8a6975` | Exact | No | No |
| 131 | `local/sentientia_privacy/styles.css` | Page styles | 23 | `4f1d4ddeb22d15be94fbfba547d8367fe2452a0d32b10e6a10ed4c1910072611` | Exact | No | No |
| 132 | `local/sentientia_privacy/templates/admin_panel.mustache` | DPO admin panel template | 114 | `d9442504ab656c1ad2a7224c38c7cefc85577c9d6a1b52f02942233016cd3dee` | Exact | No | No |
| 133 | `local/sentientia_privacy/templates/self_service.mustache` | Self-service template (notice text, download and delete forms, request list) | 98 | `02582eb02ae8dee0e0cef7a4a017cde2a6c53e5568abae11fe0e17c2344593c2` | Exact | No | No |
| 134 | `local/sentientia_privacy/tests/admin_scope_test.php` | PHPUnit: admin_scope_test (local_sentientia_privacy) | 118 | `16122d5f9dc99af2744ea05c1df55b5b39eab3920b8f843e4e12caf40f1b3e24` | Exact | No | No |
| 135 | `local/sentientia_privacy/tests/erasure_completeness_test.php` | PHPUnit: erasure_completeness_test (local_sentientia_privacy) | 821 | `231056f7bceccb01450623c4dbf5129e2d2aac9f36096c9c06a2434dcaa4b987` | Exact | No | No |
| 136 | `local/sentientia_privacy/tests/erasure_scope_test.php` | PHPUnit: erasure_scope_test (local_sentientia_privacy) | 223 | `9437f09c54bba3ae697c5f74a7c3993d71c5deefa7f0257b68471bf4362c8e1b` | Exact | No | No |
| 137 | `local/sentientia_privacy/tests/fixtures/failing_erasure_provider.php` | Test fixture: a provider that throws, to prove partial erasure is reported | 49 | `dbb13fb50b419a12661e01481cbf97cce5624e9d36b63bc243f04eea990baf75` | Exact | No | No |
| 138 | `local/sentientia_privacy/tests/privacy_manager_test.php` | PHPUnit: privacy_manager_test (local_sentientia_privacy) | 163 | `c96b78c70139a83b6584f92e44d7d0f58b58193fc3025939670802b0bedc7a60` | Exact | No | No |
| 139 | `local/sentientia_privacy/version.php` | Plugin version 2026111300 (1.1.0) | 26 | `a5acb0a3b037a52cb7a2c15b8e0a6f4ba69e494b0e268696103ae4a5bc1c2bad` | Exact | No | No |
| 140 | `local/sentientia_proctoring/classes/identity/verifier_factory.php` | Identity verifier selection: external face matching no longer selectable (mock only); the AWS verifier class remains in the code | 32 | `289ac8831ac6b8e037afca588729fa34f2700460119b21ea210440939a52157f` | Redacted | Yes | No |
| 141 | `local/sentientia_proctoring/classes/privacy/provider.php` | Privacy provider of local_sentientia_proctoring (metadata provider) | 237 | `899ad748ae6f039927e7c617046d8f9cb07ea6093f0aaae8732933f5785953ee` | Redacted | Yes | No |
| 142 | `local/sentientia_proctoring/classes/task/purge_old_recordings.php` | Retention task: deletes proctoring recording objects after retention_days | 151 | `c3edba995b5409b87ceb07e6c92f09cd0a72ac2b5deb803b9bdef4f2bb68d191` | Exact | Yes | No |
| 143 | `local/sentientia_proctoring/db/install.xml` | Schema of local_sentientia_proctoring (data inventory) | 180 | `7af23376a48c4a61b7df3299a9293fff8456cb1e77ee0801dbfe2bcf98dc7e14` | Exact | Yes | No |
| 144 | `local/sentientia_proctoring/tests/quiz_integration_test.php` | PHPUnit: quiz_integration_test (local_sentientia_proctoring) | 334 | `fdc27e9ddd7c0b636d6b42cf0a8b8abfb9d660b9be69b6189470498a9a413ce4` | Redacted | New | No |
| 145 | `local/sentientia_proctoring/tests/secure_mode_test.php` | PHPUnit: secure_mode_test (local_sentientia_proctoring) | 610 | `fbda8870ad9c2ef4b9c8c0ccd10d1bac721d33810011cdf018f1fb1114830b99` | Redacted | New | No |
| 146 | `local/sentientia_programs/classes/privacy/provider.php` | Privacy provider of local_sentientia_programs (metadata provider) | 307 | `f7b08cf557cb172492d75d46e8a654a67b23d3a26e61ee8205d578bc5160beff` | Exact | No | No |
| 147 | `local/sentientia_programs/db/install.xml` | Schema of local_sentientia_programs (data inventory) | 180 | `26414e9978cea32796da4cf4b7f9cae2e99129facdbe1226a4407ef4d501b782` | Exact | No | No |
| 148 | `local/sentientia_programs/tests/privacy_anonymise_test.php` | PHPUnit: privacy_anonymise_test (local_sentientia_programs) | 137 | `44d726f2cf919b24407fd723390df55566208845dc1edc86b6cab32a95380491` | Exact | No | No |
| 149 | `local/sentientia_programs/tests/privacy_history_test.php` | PHPUnit: privacy_history_test (local_sentientia_programs) | 239 | `52d565664c639a909a3c25a4234f7e16b21c36e917713c59bcbe4ce0c198d637` | Exact | No | No |
| 150 | `local/sentientia_pwa/classes/privacy/provider.php` | Privacy provider of local_sentientia_pwa (metadata provider) | 180 | `ec79646d15d1fd9cb4a0b75aadb9a44e3e317108665237f4d842f466c28a7e2d` | Exact | No | No |
| 151 | `local/sentientia_pwa/db/install.xml` | Schema of local_sentientia_pwa (data inventory) | 116 | `8d76b7d4c51db471ae71bdadd5e45b1f4df4ec5bdc6ed7f2d160edbdcd1112c3` | Exact | No | No |
| 152 | `local/sentientia_ratings/classes/privacy/provider.php` | Privacy provider of local_sentientia_ratings (metadata provider) | 285 | `2b065ade95c806389ebb5c3156e5a04ac8accdf8f0aaf6c2df64136faa14664c` | Exact | No | No |
| 153 | `local/sentientia_ratings/db/install.xml` | Schema of local_sentientia_ratings (data inventory) | 74 | `159d6341d0f9137737c0758e067542ab83f3955ca4f7e5291256b0e9b6c79df6` | Exact | No | No |
| 154 | `local/sentientia_ratings/tests/privacy/provider_test.php` | PHPUnit: provider_test (local_sentientia_ratings) | 177 | `ad1ef4c93263a9953c301e85be9883f35afd5aa2bd50f7f21aa9388cc4612444` | Exact | No | No |
| 155 | `local/sentientia_recommendations/classes/privacy/provider.php` | Privacy provider of local_sentientia_recommendations (metadata provider) | 137 | `3b333970842ea427c8674db6a0bc52a8e8f38081eeaec1898f94145153e4c2ce` | Exact | No | No |
| 156 | `local/sentientia_recommendations/db/install.xml` | Schema of local_sentientia_recommendations (data inventory) | 83 | `7a90f3efeea72a51453f76d1a6df9fcbfdfc7c0c363531ce910741888d9f130d` | Exact | No | No |
| 157 | `local/sentientia_recompletion/classes/privacy/provider.php` | Privacy provider of local_sentientia_recompletion (metadata provider) | 443 | `2e41ce1eb8ac4d3ecf18249956c9ef13ecb7a7856c59fb9cf0932277eb203e16` | Exact | No | No |
| 158 | `local/sentientia_recompletion/db/install.xml` | Schema of local_sentientia_recompletion (data inventory) | 139 | `d93164026f6e1067bb28e07d155cc0a109c254cd3588690f197e690cf45e2518` | Exact | No | No |
| 159 | `local/sentientia_recompletion/tests/privacy_anonymise_test.php` | PHPUnit: privacy_anonymise_test (local_sentientia_recompletion) | 177 | `c250345ae275b0b227fcb4f2d17e3df4319e5cbc866536b6d466690ead614e43` | Exact | No | No |
| 160 | `local/sentientia_recompletion/tests/privacy_archive_test.php` | PHPUnit: privacy_archive_test (local_sentientia_recompletion) | 333 | `66a645baed9e6fcd23fc0d94d399ba08bc32c805bc6f865a1b93ce98b7d0a732` | Exact | No | No |
| 161 | `local/sentientia_reports/classes/privacy/provider.php` | Privacy provider of local_sentientia_reports (metadata provider) | 131 | `7b2b48daf38ecc1011db25d02f899a5fa3543f7fd808a099707beed53a2a9d09` | Exact | No | No |
| 162 | `local/sentientia_reports/db/install.xml` | Schema of local_sentientia_reports (data inventory) | 36 | `1dbfcc79b1160ea52223574a1a4249f464862c1e38c6c5cddac9ada1a0efae8a` | Exact | No | No |
| 163 | `local/sentientia_request/classes/privacy/provider.php` | Privacy provider of local_sentientia_request (metadata provider) | 123 | `3ecabe154dabf779325689e50ca3ec9e02dd6aa008a1e8c6cab8270725d362db` | Exact | No | No |
| 164 | `local/sentientia_request/db/install.xml` | Schema of local_sentientia_request (data inventory) | 62 | `ed0ff5b5eed0cba8a359c5e3266d951e31abcda6d2e9e09ae823f628977cca22` | Exact | No | No |
| 165 | `local/sentientia_request/tests/imported_history_test.php` | PHPUnit: imported_history_test (local_sentientia_request) | 519 | `0ee839aae43ab895400e40e3afd63bfa9d3a8ca76d3cd72055c427787db55ae8` | Exact | No | No |
| 166 | `local/sentientia_roles/classes/privacy/provider.php` | Privacy provider of local_sentientia_roles (metadata provider) | 160 | `20078080b713eb52e6b4986537009a70a6bdead25a14757cea3ac682fdc3fd05` | Exact | No | No |
| 167 | `local/sentientia_roles/db/install.xml` | Schema of local_sentientia_roles (data inventory) | 50 | `184dcb1ea13ced34e03adeef2037855113934d3a8065f6d3eea7f5cbb4bb1a3e` | Exact | No | No |
| 168 | `local/sentientia_roles/tests/bizlms_import_test.php` | PHPUnit: bizlms_import_test (local_sentientia_roles) | 1062 | `dc68375dcfcd000ce5315da9c876a5e5b21e6988f2ca727da8e0fcc3b9d4ecf9` | Exact | No | No |
| 169 | `local/sentientia_roles/tests/privacy/provider_test.php` | PHPUnit: provider_test (local_sentientia_roles) | 151 | `d965d90458ade044a8b84ed00304d5f83cf999f99b0ec9a425fd70f95dd96887` | Exact | No | No |
| 170 | `local/sentientia_skills/classes/privacy/provider.php` | Privacy provider of local_sentientia_skills (metadata provider) | 201 | `844ca9ce3160e290a02414c1b2e8b852024fd2d15f8fcb52314fbef390603af6` | Exact | No | No |
| 171 | `local/sentientia_skills/db/install.xml` | Schema of local_sentientia_skills (data inventory) | 219 | `b340fb78fd691ad2887070796402b11b844f4f4035f54252629a40fb21f526d8` | Exact | No | No |
| 172 | `local/sentientia_skills/tests/privacy/provider_test.php` | PHPUnit: provider_test (local_sentientia_skills) | 157 | `c6bf7b7f5d39392fbeee0aa278930799c39d3ad24ccb9c2aa728c8c79dceaede` | Exact | No | No |
| 173 | `local/sentientia_skillsai/classes/privacy/provider.php` | Privacy provider of local_sentientia_skillsai (metadata provider) | 210 | `5a9af32e5d38aab1c3c56b83d8eeb27c2b48008bfd0de26e5a75442a6c83dff6` | Exact | No | No |
| 174 | `local/sentientia_skillsai/db/install.xml` | Schema of local_sentientia_skillsai (data inventory) | 297 | `9645bc969ab73a47b8e5483b1bbf6b68093439bd4a725162ba0a70363bebcc8b` | Exact | No | No |
| 175 | `local/sentientia_talent/classes/privacy/provider.php` | Privacy provider of local_sentientia_talent (metadata provider) | 239 | `21d01a6e013255247dd4575adb8a3d475233d88f13cbf0154cc1fb21f6d7d969` | Exact | No | No |
| 176 | `local/sentientia_talent/db/install.xml` | Schema of local_sentientia_talent (data inventory) | 185 | `81a16651088f3c7dbadab169a958485b15e7bf6dbcd358cd598977be22decc9e` | Exact | No | No |
| 177 | `local/sentientia_talent/tests/privacy_actor_columns_test.php` | PHPUnit: privacy_actor_columns_test (local_sentientia_talent) | 165 | `b973455bd35cf690a53c5f96f31d6a12db7a2fd5df020dca65c19de688b65a1f` | Exact | No | No |
| 178 | `local/sentientia_translate/classes/privacy/provider.php` | Privacy provider of local_sentientia_translate (metadata provider) | 146 | `70096c01a84ac231a5e4c844b70dbd2935b90b6829fc6e16a86b078c03655f3d` | Exact | No | No |
| 179 | `local/sentientia_translate/db/install.xml` | Schema of local_sentientia_translate (data inventory) | 121 | `a9a0aef0247598c0ec0a7ce3b0b3c9ddcc520b3df9c462ddc64548eb0c56eecf` | Exact | No | No |
| 180 | `local/sentientia_translate/tests/anonymised_owner_test.php` | PHPUnit: anonymised_owner_test (local_sentientia_translate) | 70 | `5cb7d1ca7da18e12cd8a8b3d1de6f20e865e5f443b29209ae80b014f9fc637ab` | Exact | No | No |
| 181 | `local/sentientia_users/classes/privacy/provider.php` | Privacy provider of local_sentientia_users (metadata provider) | 198 | `84b1bce220c9381d3044570afee87d1a7004f88d67a84d547cad3ea1f7cec13d` | Redacted | Yes | Yes |
| 182 | `local/sentientia_users/classes/signup_service.php` | Public self-registration; consent flags default OFF | 410 | `d3402bdd1da0b7acf7e17ee7954b31df6544864804b218916fad7d167850205c` | Exact | No | No |
| 183 | `local/sentientia_users/db/install.xml` | Schema of local_sentientia_users (data inventory) | 221 | `3a19c0411ba294cd03e31d7ebc2bcffa46ad4a6c09867d83a09d2a46a234778a` | Redacted | Yes | Yes |
| 184 | `local/sentientia_users/tests/external_trainer_test.php` | PHPUnit: external_trainer_test (local_sentientia_users) | 601 | `d8259b800fd2dc67d720819ca7d520b1cc04e3b10ccef4446a4574bb912e1297` | Redacted | New | New |
| 185 | `local/sentientia_users/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (local_sentientia_users) | 437 | `3a0e6a0f53360d735848894d4037cb4f5f39ffe28728f6228e2bf3ee551dc8cf` | Redacted | Yes | Yes |
| 186 | `local/sentientia_whatsapp/classes/privacy/provider.php` | Privacy provider of local_sentientia_whatsapp (metadata provider) | 214 | `f57efa44fdb9ae66ad72273284eef60719c484c415f83496a0b6d8ce53a9bd7b` | Exact | No | No |
| 187 | `local/sentientia_whatsapp/db/install.xml` | Schema of local_sentientia_whatsapp (data inventory) | 160 | `7b9c1d2d5b963868741db793f23d3c593d4798e01130972584b70443e0798968` | Exact | No | No |
| 188 | `local/sentientia_xapi/classes/privacy/provider.php` | Privacy provider of local_sentientia_xapi (metadata provider) | 188 | `19279b3d5ad3feaee6de7068aabe68689bc7681263b89709969ca710b0407ae6` | Exact | No | No |
| 189 | `local/sentientia_xapi/db/install.xml` | Schema of local_sentientia_xapi (data inventory) | 207 | `74b99bf3eef69364dc4dfe39f2c66d4bcc5f18f517b49390ecac5937dee863bd` | Exact | No | No |
| 190 | `local/sentientia_xapi/tests/lrs_store_test.php` | PHPUnit: lrs_store_test (local_sentientia_xapi) | 433 | `0fbd17ad2e0bd1e73a68e36f2e4de809172b96816303695cf78754387ae1a3d4` | Exact | No | No |
| 191 | `mod/quiz/accessrule/sentientia_proctoring/classes/privacy/provider.php` | Privacy provider of quizaccess_sentientia_proctoring (null_provider) | 22 | `9560be4e44565a62b7f30ceedb955aca42f4fbc063030e8ce2e7d7a0cc101428` | Exact | No | No |
| 192 | `mod/quiz/accessrule/sentientia_proctoring/db/install.xml` | Schema of quizaccess_sentientia_proctoring (data inventory) | 38 | `7113674fe84c7c9589a3d27ebc13468777e9cc3c72a7f374abf261655422bfee` | Exact | Yes | No |
| 193 | `payment/gateway/airpay/classes/privacy/provider.php` | Privacy provider of paygw_airpay (null_provider) | 85 | `27bf6294e1a0a965fac40774d49c4b93df1ffd14ec7707447c7993821805a999` | Exact | No | No |
| 194 | `payment/gateway/airpay/db/install.xml` | Schema of paygw_airpay (data inventory) | 60 | `722bf8a4e6c9d0df69a00d6542c267ffbbffb8dbd18fd652426d221df2e3af81` | Exact | No | No |
| 195 | `payment/gateway/airpay/tests/privacy_provider_test.php` | PHPUnit: privacy_provider_test (paygw_airpay) | 150 | `a69e2577ea16d03b1879f3a3bfc2906d300b72ba732d41bbf05ee457bdc8f0a4` | Exact | No | No |
| 196 | `theme/sentientia/classes/privacy/provider.php` | Privacy provider of theme_sentientia (metadata provider) | 116 | `01fe69e1d04bf9c4e9c8c2daa657b1fa679a68735a3034a1e510042b3f074384` | Exact | No | No |
| 197 | `theme/sentientia/tests/privacy/provider_test.php` | PHPUnit: provider_test (theme_sentientia) | 79 | `39a7707d11c9688d7ac7cc080d3c3c36efb8e2f722c07978d8fe9da47e707750` | Exact | No | No |
| 198 | `local/sentientia_ai/classes/gateway.php` | AI gateway: mock or live routing, ledger, quotas; key resolution falls back to the calling consumer's api_key (legacy_component) (findings F-21, F-22) | 341 | `8cddfbcec2af97ceda7c3b0be261e763a7ae72518730cef3cdfb5768b8e8ef5d` | Exact | No | No |
| 199 | `local/sentientia_aiquiz/classes/anthropic_client.php` | AI quiz client: refuses live calls without gateway routing (gateway_routing_required) (finding F-22) | 431 | `3f94c9a56206d925d4b6621bef2519096881c1e683b587b58f97240ff260ff04` | Exact | No | No |
| 200 | `local/sentientia_aiquiz/cli/live_smoke.php` | Administrator command-line smoke test that calls the AI quiz call_live() directly (finding F-22) | 145 | `c8c31f2eed2a3413efa3063e8b5e136df4176e79c2b0f849483cb60d7a0fcd6b` | Exact | No | No |
| 201 | `local/sentientia_assistant/ai_demo.php` | Site-administrator demonstration page for Moodle core AI providers through core_ai_bridge (01a section 13.1) | 74 | `af71dfea55a1050d2db9c29e961f992d6c68d3c6f73d612d4ab531ea7827c659` | Exact | No | No |
| 202 | `local/sentientia_assistant/classes/agent/agent_client.php` | Agentic copilot client: gateway routing, or a direct call with the assistant key when its live flag is on (finding F-22) | 357 | `fa8b45e42aef6553fce3c9980f7ee3c20f2f03f1abc1869e9a5f56a1f145cb39` | Exact | No | No |
| 203 | `local/sentientia_assistant/classes/core_ai_bridge.php` | Bridge from the assistant to Moodle core AI providers (01a section 13.1) | 169 | `d067e67fa16fc20a6a1792b6f7c1efc610a6cf4700b1c5206dd2254f79f449cb` | Exact | No | No |
| 204 | `local/sentientia_assistant/classes/hook_callbacks.php` | Assistant drawer quick-action chips that send fixed questions (finding F-28) | 153 | `57d33dd81ff7b815999b1eab89259a143b3298cf481dd658c32922b264885ba7` | Exact | No | No |
| 205 | `local/sentientia_authoring/classes/course_generator.php` | Authoring studio generator: gateway routing, or a direct call with anthropic_api_key when its live flag is on (finding F-22) | 412 | `c99d777a7c28cf4f99b1fcd25c6f7b96e31f3228d948c2d61d51f8aace52ed01` | Exact | No | No |
| 206 | `local/sentientia_recommendations/classes/anthropic_client.php` | Recommendations client: refuses live calls without gateway routing (finding F-22) | 360 | `8c7e6fbc3cdd0b2036145fcc0fdbf1e4ad192a8358d3b0aab31fe9ed63a13a95` | Exact | No | No |
| 207 | `local/sentientia_skillsai/classes/anthropic_client.php` | Skills extraction client: refuses live calls without gateway routing (finding F-22) | 379 | `cc6b5401e81bdbcad48230a1586c3cb1da06455618a3e03fd8d5fc5db04a1fb2` | Exact | No | No |
| 208 | `local/sentientia_translate/classes/anthropic_client.php` | Translation client: refuses live calls without gateway routing (finding F-22) | 333 | `220dfe7d02f53045b35e2a1e94e93ea8ae7e6be023bfbb4b4bc1db9ce003643b` | Exact | No | No |

### 8.2 Redacted review copies: repository hashes

| # | Relative path | SHA-256 of the repository file |
|---|---|---|
| 7 | `docs/adr/ADR-031-cross-tenant-authority.md` | `aedac02fe9fbb82d273490b1a90942167e34a22201140e52449f5473ca4b3249` |
| 8 | `docs/adr/ADR-035-privacy-erasure-completeness.md` | `90d176c448bd428d7902c2abdfe99fd0fc5ea1f2f0b32642fa01581f37e28886` |
| 9 | `docs/core-mods/2026-05-20-moodle-to-sentientia-rename.md` | `6e643cfbef0beee74e7ce331113385c00f0c6d2438b465e842ba9cd39fb86ede` |
| 11 | `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md` | `8243e2b854b4be80ef6d814d1abb5a0ca60a591338a265eddbceffd5b00d4dc6` |
| 12 | `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md` | `2c0ea87642c385c3fae42875f3253061c8ef40e8c549a42bbe24c74f10144eab` |
| 13 | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` | `db9443ea77cda6d737ebe53cb10b005f088857732685415cb39cc6b8de5bd630` |
| 14 | `docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` | `d983c706c067b4fd96c8859280b10f5d93f52aab70ceed79f821ce69743e29c1` |
| 37 | `local/sentientia_cart/classes/privacy/provider.php` | `9bfbab3fc9e39046cd2057f0e2fb66f33fc62ed7e3f45fadb7318626ce6fdf5d` |
| 61 | `local/sentientia_courses/db/install.xml` | `0f4fd4fb64baaefbb24cea7f4df7406515619af72e3b8f445594154e4fba11fd` |
| 62 | `local/sentientia_courses/tests/certificate_issuer_test.php` | `fd54dfcd0e49c9ef581276462f14b87c135130df17648352c396bdb74c67a3b8` |
| 101 | `local/sentientia_platform/classes/audit_log.php` | `ea7212552786493758d42eaf0e88c6e4ae37dfb629218528506d79adc6eaf84d` |
| 102 | `local/sentientia_platform/classes/feature_flags.php` | `4cfe9711b1dc942c9b9186494a50acdee59f35b433dd0a196b0048e5f68035b4` |
| 107 | `local/sentientia_platform/db/install.xml` | `93300c41b1f5198de54ff3535ee347b2acf97677fc67f650ed720c6f602e4f44` |
| 112 | `local/sentientia_privacy/README.md` | `b8a17fae9dfa47bb3e07613513358cefbb7ef6e9380b20f5054a4b0343756107` |
| 114 | `local/sentientia_privacy/classes/local/erasure_completeness.php` | `529e727a9ab01b624a5d74d9a35dbc56988bcc6217de4785e73b0b7efdb1096f` |
| 121 | `local/sentientia_privacy/db/feature_flags.php` | `78b1e51bf7019bacc1bfec81bfd4ec7cd7600a49e7accb99c4e4d89d0b20adab` |
| 140 | `local/sentientia_proctoring/classes/identity/verifier_factory.php` | `d392ce8b68a48cd6f093b451e82c3e9c2fe24b0e2559e44da710f395f1f538af` |
| 141 | `local/sentientia_proctoring/classes/privacy/provider.php` | `ee1ae82f96c2d9e45d48e767dd56d39722261dcb8768d40b0e7571b84a29e857` |
| 144 | `local/sentientia_proctoring/tests/quiz_integration_test.php` | `108dab682223e664d50c72e4f9d89e59cb28e37a07e74ec77b8654ececb8c3e5` |
| 145 | `local/sentientia_proctoring/tests/secure_mode_test.php` | `a14ebe637d687c1cadc4fa16d9703df725267e203ba788b597e95fa9fbf9a820` |
| 181 | `local/sentientia_users/classes/privacy/provider.php` | `c7cdfe13ac475042524c2353ec26668d8e8114de0f929e583fd590d9e1a78964` |
| 183 | `local/sentientia_users/db/install.xml` | `1be968e7224f92dd8c86dd4e571f28e89cff11faba312a9e795742e688bca3f8` |
| 184 | `local/sentientia_users/tests/external_trainer_test.php` | `6a8b3147a084b49588c2104591049b6d31f5443a0f77d22ef984833ca0dfd1d1` |
| 185 | `local/sentientia_users/tests/privacy_provider_test.php` | `f611184820020344644a04ae2c37b379d665dac2b2759089543cd04f2afd6af3` |

---

## 9. Asks and next steps

| # | Ask | From | By | Form |
|---|---|---|---|---|
| 1 | Verify the zip hash and confirm receipt | SISA; Information Security | Within 2 working days of receipt | E-mail to the document owner |
| 2 | Request repository access if tests will be run | SISA | At kick-off, proposed by 4 December 2026 | Named reviewers |
| 3 | Report any file that should have been excluded or further redacted | SISA; Information Security | On discovery | Immediate notice to the document owner |
| 4 | Destroy any copy of bundle versions 1.0 and 1.1 | Any recipient of those versions | On receipt of this version | Confirmation to the document owner |

---

## 10. Risks and open questions

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| Q1 | The bundle reflects uncommitted work for 9 files; the repository could move after 20 November | Platform engineer | Commit the work and tag the basis before granting repository access; the hashes here stay authoritative for the bundle |
| Q2 | The bundle is not runnable on its own | Platform engineer | Provide repository access, or a full plugin package on request |
| Q3 | 24 files are redacted review copies and so are not byte-identical to the repository | Head of Information Security | Accept the redaction for the review, or ask for the exact files through repository access; section 8.2 lets a reviewer match each copy to the repository |
| Q4 | The superseded version 1.0 and 1.1 bundles and zips still exist in the local build area | Head of L&D | Delete them after this version is accepted; never distribute them |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record |
| Bundle | The folder of files copied from the repository for this review |
| Canonical tree | `moodle-enhancement/<type>/<plugin>/`, the source of truth for plugin code |
| DPDP | Digital Personal Data Protection Act 2023 and Rules 2025 (India) |
| NDA | Non-disclosure agreement |
| Privacy provider | A plugin class that tells Moodle's privacy API what personal data the plugin holds and how to export and delete it |
| Redacted review copy | A bundled file whose comments or record text were changed to remove names, working-note references, local paths or identifiers; its repository hash is in section 8.2 |
| SHA-256 | A cryptographic hash used here to prove file integrity |
| UAT | User-acceptance test environment, academy2.airpay.ninja |

## Appendix B. References

| Item | Location |
|---|---|
| Distribution | The document owner sends the zip through Airpay's approved secure file-transfer channel to named recipients; it is not hosted at any public location |
| Bundle and zip names | `sentientia-dpdp-code-review-2026-11-20/`, `sentientia-dpdp-code-review-2026-11-20.zip` |
| Fact base | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md`, `40-data-privacy.md` |
| Python `zipfile` and `hashlib` documentation | https://docs.python.org/3/library/zipfile.html ; https://docs.python.org/3/library/hashlib.html (accessed 20 November 2026) |
