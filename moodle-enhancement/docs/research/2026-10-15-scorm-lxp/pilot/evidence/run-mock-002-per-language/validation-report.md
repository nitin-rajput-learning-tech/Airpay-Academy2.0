# Pilot validation report - run-20261015T155251Z

**Result: PASS** | mode `mock` | preview build: True | package mode `per_language` | speech engine `mock-silent` | network calls made: **none** | spend INR 0.0

Course document sha256 `4b8cab030ae309a4...`; generated 2026-10-15T15:53:57Z. Mock mode proves the compiler, the runtimes and the governance plumbing; it does not prove translation quality, voice quality, Moodle behaviour or LRS acceptance.

## 1. Course document: schema and rules R01-R12

| Rule | Status |
|---|---|
| R01 | PASS |
| R02 | PASS |
| R03 | PASS |
| R04 | PASS |
| R05 | PASS |
| R06 | PASS |
| R07 | PASS |
| R08 | PASS |
| R09 | PASS |
| R10 | PASS |
| R11 | PASS |
| R12 | PASS |
| schema | PASS |

0 error(s), 2 warning(s).

| Level | Rule | Where | Message |
|---|---|---|---|
| warning | slides | /blocks/1/points/1 | bullet has 10 words > 8 recommended |
| warning | slides | /blocks/2/points/0 | bullet has 9 words > 8 recommended |

State budget (R05): worst-case `cmi.suspend_data` token is **104** characters plain, 110 compressed; budgets 3500 pre-compression / 4096 SCORM 1.2 limit / 64000 SCORM 2004.

## 2. Source and PII gate

PII scan status **clean** (0 finding(s)). Scanner: Aadhaar (Verhoeff), PAN, Indian mobile, email, IFSC, card (Luhn). Values are never stored; findings are masked. Generated strings are re-scanned after translation.

## 3. Languages

| Language | Review state | Human units kept | Machine/pseudo units filled | Audio (silent in mock) | Narration characters |
|---|---|---|---|---|---|
| en | approved | - | - | 245 s | 3040 |
| hi | in_review | 33 | 57 | 227 s | 2965 |
| ar | machine | - | 90 | 250 s | 3105 |

Engine `mock-pseudo`, model `none`, prompt `pilot-translate/0.1`. In mock mode every machine-filled unit is pseudo-localised (`[xx] source text`) and is labelled as such in the player.

## 4. Packages

| Standard | File | Languages | Size | sha256 (first 16) | Checks pass/fail/warn/skip |
|---|---|---|---|---|---|
| scorm12 | sop-cs-014-upi-dispute-handling-scorm12-en.zip | 1 | 56,581 B | d9646222144d1ae8 | 21/0/0/0 |
| scorm12 | sop-cs-014-upi-dispute-handling-scorm12-hi.zip | 1 | 63,369 B | 4390a34cec9ce648 | 21/0/0/0 |
| scorm12 | sop-cs-014-upi-dispute-handling-scorm12-ar.zip | 1 | 58,145 B | f99a01709ac66cf7 | 21/0/0/0 |
| scorm2004 | sop-cs-014-upi-dispute-handling-scorm2004-en.zip | 1 | 56,794 B | 3198b440f69f72c1 | 21/0/0/0 |
| scorm2004 | sop-cs-014-upi-dispute-handling-scorm2004-hi.zip | 1 | 63,589 B | dbdb56678cce70a2 | 21/0/0/0 |
| scorm2004 | sop-cs-014-upi-dispute-handling-scorm2004-ar.zip | 1 | 58,358 B | 37966985c168901a | 21/0/0/0 |
| cmi5 | sop-cs-014-upi-dispute-handling-cmi5-en.zip | 1 | 58,761 B | 7d8e740863041f98 | 19/0/0/0 |
| cmi5 | sop-cs-014-upi-dispute-handling-cmi5-hi.zip | 1 | 65,654 B | 3c9572605290b446 | 19/0/0/0 |
| cmi5 | sop-cs-014-upi-dispute-handling-cmi5-ar.zip | 1 | 60,324 B | 62572b84a1928771 | 19/0/0/0 |

### sop-cs-014-upi-dispute-handling-scorm12-en.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 56,581 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsproject.org/xsd/imscp_rootv1p1p2 |
| S02 | PASS | schemaversion '1.2' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormtype="sco" with the correct casing |
| S06 | PASS | adlcp:masteryscore = 70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm12.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 3500 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-scorm12-hi.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 63,369 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsproject.org/xsd/imscp_rootv1p1p2 |
| S02 | PASS | schemaversion '1.2' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormtype="sco" with the correct casing |
| S06 | PASS | adlcp:masteryscore = 70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm12.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 3500 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-scorm12-ar.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 58,145 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsproject.org/xsd/imscp_rootv1p1p2 |
| S02 | PASS | schemaversion '1.2' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormtype="sco" with the correct casing |
| S06 | PASS | adlcp:masteryscore = 70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm12.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present; RTL carried: ar |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 3500 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-scorm2004-en.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 56,794 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsglobal.org/xsd/imscp_v1p1 |
| S02 | PASS | schemaversion '2004 4th Edition' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormType="sco" with the correct casing |
| S06 | PASS | imsss:minNormalizedMeasure = 0.70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm2004.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-scorm2004-hi.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 63,589 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsglobal.org/xsd/imscp_v1p1 |
| S02 | PASS | schemaversion '2004 4th Edition' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormType="sco" with the correct casing |
| S06 | PASS | imsss:minNormalizedMeasure = 0.70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm2004.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-scorm2004-ar.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | imsmanifest.xml is at the ZIP root |
| Z06 | PASS | 58,358 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <manifest> in namespace http://www.imsglobal.org/xsd/imscp_v1p1 |
| S02 | PASS | schemaversion '2004 4th Edition' |
| S03 | PASS | organizations default='ORG_01' resolves |
| S04 | PASS | 1 item(s) reference existing resources |
| S05 | PASS | adlcp:scormType="sco" with the correct casing |
| S06 | PASS | imsss:minNormalizedMeasure = 0.70 |
| S07 | PASS | launch file 'index.html' exists |
| Z04 | PASS | all 46 declared files exist |
| Z05 | PASS | every packaged file is declared |
| P01 | PASS | player assets present with only runtime-scorm2004.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present; RTL carried: ar |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-cmi5-en.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | cmi5.xml is at the ZIP root |
| Z06 | PASS | 58,761 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <courseStructure> in the cmi5 namespace |
| S02 | PASS | cmi5 course structure (schemaversion is implicit in the namespace) |
| S03 | PASS | course and AU ids are IRIs |
| S04 | PASS | 1 assignable unit(s) (one expected) |
| S05 | PASS | moveOn='CompletedAndPassed' |
| S06 | PASS | masteryScore='0.70' (expected 0.70) |
| S07 | PASS | launch url 'index.html' exists |
| P01 | PASS | player assets present with only runtime-cmi5.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-cmi5-hi.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | cmi5.xml is at the ZIP root |
| Z06 | PASS | 65,654 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <courseStructure> in the cmi5 namespace |
| S02 | PASS | cmi5 course structure (schemaversion is implicit in the namespace) |
| S03 | PASS | course and AU ids are IRIs |
| S04 | PASS | 1 assignable unit(s) (one expected) |
| S05 | PASS | moveOn='CompletedAndPassed' |
| S06 | PASS | masteryScore='0.70' (expected 0.70) |
| S07 | PASS | launch url 'index.html' exists |
| P01 | PASS | player assets present with only runtime-cmi5.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

### sop-cs-014-upi-dispute-handling-cmi5-ar.zip

| Check | Status | Detail |
|---|---|---|
| Z01 | PASS | 47 entries, no duplicates, no unsafe paths |
| Z02 | PASS | cmi5.xml is at the ZIP root |
| Z06 | PASS | 60,324 bytes (limit 52,428,800) |
| Z03 | PASS | manifest is well-formed XML without DTD or entities |
| S01 | PASS | root <courseStructure> in the cmi5 namespace |
| S02 | PASS | cmi5 course structure (schemaversion is implicit in the namespace) |
| S03 | PASS | course and AU ids are IRIs |
| S04 | PASS | 1 assignable unit(s) (one expected) |
| S05 | PASS | moveOn='CompletedAndPassed' |
| S06 | PASS | masteryScore='0.70' (expected 0.70) |
| S07 | PASS | launch url 'index.html' exists |
| P01 | PASS | player assets present with only runtime-cmi5.js |
| P03 | PASS | index.html carries lang and dir attributes (not hard-coded to English) |
| P02 | PASS | course-data.js parses (1 language(s), 6 block(s)) |
| P03 | PASS | per-language dir and UI strings present; RTL carried: ar |
| M01 | PASS | every narrated block has audio and captions in all 1 carried language(s) |
| M02 | PASS | caption files are valid WebVTT with ascending, non-overlapping cues |
| B01 | PASS | worst-case suspend token 104 chars (compressed 110), budget 64000 |
| J01 | PASS | all 4 packaged JavaScript files compile |

## 5. Headless runtime smoke (fake LMS and fake LRS)

- `sop-cs-014-upi-dispute-handling-scorm12-en.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-scorm12-hi.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-scorm12-ar.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-scorm2004-en.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-scorm2004-hi.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-scorm2004-ar.zip`: **PASS** (19/19 checks)
- `sop-cs-014-upi-dispute-handling-cmi5-en.zip`: **PASS** (13/13 checks)
- `sop-cs-014-upi-dispute-handling-cmi5-hi.zip`: **PASS** (13/13 checks)
- `sop-cs-014-upi-dispute-handling-cmi5-ar.zip`: **PASS** (13/13 checks)

## 6. Audit trail

`audit/audit.jsonl`: 26 append-only entries, hash chain valid, final sha256 `c44fb1bcc2b75fdcaaf5180df9335e1021a652c44fb78d1cf058fd61657ed251`. Each entry carries the previous entry's hash; `verify_chain()` recomputes it from the file.

## 7. What a live run would cost (pre-flight estimate; nothing was spent)

Translation to 2 language(s) with `claude-sonnet-4-6` at the harness price inputs (USD 3.0/15.0 per million input/output tokens, USD/INR 87.0): about 7,090 input and 16,794 output tokens in 4 request(s) = **INR 23.77** (this counts every language as untranslated; human-translated units are skipped in a real run). The study's Sonnet 5.5 price inputs and the whole-course model budget are in STUDY section 12.

Speech for the same narration, at list-price planning rates (STUDY section 12, sources/R3):

| Engine | Characters | INR low | INR high | Basis |
|---|---|---|---|---|
| self-hosted open engine, on-demand India-region GPU (incl. ASR QA) | 9,110 | 5.5 | 12.8 | R3 Part C: INR 5-11 per 8,000 characters |
| Sarvam Bulbul v2 / v3 (India-resident, vendor marketing) | 9,110 | 13.7 | 27.3 | R3 Part D: INR 12 / 24 per 8,000 characters |
| Azure AI Speech / Google Neural2 / Polly neural (India region where offered) | 9,110 | 12.8 | 14.6 | R3 Part D: about INR 11-13 per 8,000 characters |
| ElevenLabs (incumbent; India residency Enterprise-only) | 9,110 | 72.0 | 182.2 | R3 Part D: about INR 63-160 per 8,000 characters |

Human review is the dominant cost and is not modelled here (STUDY section 12.3).

## 8. Known limits of this build

- Fonts are not bundled in the pilot packages (the font stack falls back to system fonts; Noto subsetting is Stage 1).
- cmi5 reports the first passed/failed result only; later retries update the score but not the LRS result statement.
- The DOM player was exercised in a real browser against a stub LMS (see `pilot/evidence/`), not in Moodle; Moodle 5.3 runtime testing is Stage 1 work on UAT.
