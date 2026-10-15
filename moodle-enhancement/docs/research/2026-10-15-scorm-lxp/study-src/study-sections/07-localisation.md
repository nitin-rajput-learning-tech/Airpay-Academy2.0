## 7. Localisation at thirteen-plus languages

### 7.1 The language set and what each needs

| Tag | Language | Script | Direction | Learner population at Airpay (indicative) | Moodle UI pack | Notes |
|---|---|---|---|---|---|---|
| en | English (India) | Latin | LTR | all | core | base language of every SCD |
| hi | Hindi | Devanagari | LTR | largest | 47/47 plugins | 100% parity today; conversational register per `TRANSLATION-REVIEW-INSTRUCTIONS.md` |
| mr | Marathi | Devanagari | LTR | Maharashtra (HQ) | 12/47 | shares Devanagari fonts and shaping with Hindi |
| kn | Kannada | Kannada | LTR | Bengaluru teams | 12/47 | complex conjuncts; line-height 1.6+ |
| ta | Tamil | Tamil | LTR | Chennai | core only | long words; avoid narrow buttons |
| te | Telugu | Telugu | LTR | Hyderabad | core only | |
| bn | Bengali | Bengali | LTR | Kolkata, NE | core only | also used for Assamese fallback fonts |
| gu | Gujarati | Gujarati | LTR | Ahmedabad | core only | |
| ml | Malayalam | Malayalam | LTR | Kerala | core only | very long compound words |
| pa | Punjabi | Gurmukhi | LTR | North | core only | |
| or | Odia | Odia | LTR | East | core only | |
| as | Assamese | Bengali-Assamese | LTR | NE | core partial | two extra letters vs Bengali |
| ur | Urdu | Perso-Arabic (Nastaliq) | **RTL** | North, Hyderabad | core | Nastaliq needs Noto Nastaliq Urdu; numerals policy |
| ar | Arabic | Arabic (Naskh) | **RTL** | Gulf customers (future) | core | first non-Indian sales language |
| sw | Swahili | Latin | LTR | ZEEA (Tanzania) | 12/47 | retained; not a beachhead |

Fonts bundled in packages (offline-safe): Noto Sans for Latin, Noto Sans Devanagari/Kannada/Tamil/Telugu/Bengali/Gujarati/Malayalam/Gurmukhi/Oriya, Noto Nastaliq Urdu, Noto Naskh Arabic (SIL OFL; subsetting per package keeps the ZIP small). The theme adds the same families for chrome.

### 7.2 Pipeline per language (what happens to one string)

1. **Segment** — every i18n map in the SCD is one segment; id = JSON Pointer (stable across edits); inline formatting becomes placeholder tokens `{1}…{/1}`; protected terms (Airpay, UPI, RBI, OTP, UTR, Aadhaar, product names) are marked `translate="no"`.
2. **Translation memory** — exact-hash hits (100%) are applied; fuzzy hits (≥ 75%) are offered to the translator; TM is per customer (`local_sentientia_tm`), exportable as TMX.
3. **Machine draft** — remaining segments go to Claude through the gateway with the script-aware system prompt from `local_sentientia_translate` (names the target script, pins strict JSON, forbids translating numbers/dates/code), then the deterministic brand post-processor applies per-language script renderings. State → `machine`.
4. **QA checks (automatic)** — placeholder parity, numbers/dates/currency untouched, protected terms present, length vs `maxwidth` for UI-constrained strings, trailing punctuation, script sanity (Latin leakage), empty target. Failures are flagged, never silently fixed.
5. **Stage 1 — translator** — side-by-side workspace (source locked, target editable, TM/glossary/QA/comments/audio tabs), tokenised expiring link for external translators; Save → `in_translation`, Submit → `translated`.
6. **Stage 2 — SME reviewer** — reads the course in the target language page by page (player preview), comments per segment, Submit & approve → `in_review` → `approved`; Reassign with feedback → `reassigned` (counter +1) and the translator link reopens.
7. **Admin sign-off** — locks the approval timestamp; only `approved` languages are carried into learner packages (compile rule R09); `machine` languages may be carried only into preview packages.
8. **Voice** — after approval, narration segments are synthesised (self-hosted engine; fallback if the language has not passed the speech gate), ASR round-trip checked, aligned, captioned; the reviewer can re-synthesise a segment with a pronunciation override.
9. **XLIFF** — any language can be exported as XLIFF 1.2 for an agency or CAT tool and re-imported with tag-parity repair; the review states travel with it.

UI strings (the 47 plugins' lang packs) use the same TM and the same two stages, which is how kn/mr/sw move from 12/47 to 47/47 and how ta/te/bn/gu/ml/pa/or/as/ur/ar get packs: machine draft → language champion review → CI parity gate.

### 7.3 Right-to-left done properly (ur, ar)

- `dir="rtl"` on the package root and on the theme `<html>` for RTL languages; logical CSS properties (`margin-inline-start`, `inset-inline-end`) in the player and the app shell; the theme's `bootstrap-rtl.scss` is the base for chrome.
- Mirror directional icons only (chevrons, back/forward, progress direction); do not mirror symmetric or media icons (play, pause, search, user).
- Numerals: Urdu learners read Western-Arabic digits in payments contexts; keep digits Latin in both languages unless a customer glossary says otherwise; never let translation change amounts.
- Bidi isolation for protected Latin terms inside RTL text (`<bdi>`/`unicode-bidi: isolate`) so "UPI" and "₹12,400" do not scramble the line.
- Prototype 04 (localisation workspace) shows an RTL live preview; prototype 02 (player) demonstrates mirrored chrome. Visual evidence for both themes is part of Stage 2's RTL pass.

### 7.4 Reviewer capacity: the number that decides the language count

Assume a 12-minute course: ≈ 1,500 narration words + ≈ 700 words of slide, scenario and quiz text ≈ 2,200 source words ≈ 180 segments.

| Activity | Rate (planning value) | Per course per language |
|---|---|---|
| Stage 1 post-editing a machine draft (translator) | 800–1,200 words/hour for post-editing vs 250–400 for translation from scratch (see `sources/R4-standards-compliance.md`, MTPE studies) | 2–3 h |
| Stage 2 SME review in the player (reviewer) | ~1,500 words/hour reading + comments | 1.5–2 h |
| Admin sign-off, QA triage, re-synthesis spot checks | | 0.5 h |
| Audio spot-listen (reviewer, 20% of segments) | | 0.5 h |
| **Total human time** | | **≈ 5–6 h per language per course** |

At 13 languages that is 65–80 reviewer-hours per course. Fifty courses a year means ~3,500 hours — two full-time equivalents spread over per-language pools. Planning consequences: (a) languages are enabled per customer by demand, not all thirteen for every course; (b) compliance courses get both stages; low-stakes content may later use a single review stage (per-customer flag, after the TM shows reuse ≥ 40%); (c) the TM makes the second course in a language 30–50% cheaper (regulatory boilerplate repeats); (d) pools: internal SMEs for compliance (Hindi, Marathi first), language champions from regional teams, and a vendor agency per language with the XLIFF path; (e) the admin analytics show queue depth, segments/hour and SLA per language so capacity is managed, not discovered.

### 7.5 Quality control beyond review

- Golden sets per language (20 segments with approved references) re-scored on every prompt or model change with chrF/COMET-style metrics in the gateway's eval harness; a drop blocks promotion.
- Human MQM-style spot audits quarterly per language (accuracy, terminology, fluency, locale conventions).
- Terminology: the protected-term list is a glossary with per-language renderings; new terms proposed by translators are approved by the SME.
- Register: the existing instruction set (conversational, loanwords where people actually use them, short UI strings, placeholders untouched) is the style guide for all languages.

---
