# Supplement C — SENTIENTIA Detailed Pipeline Plan

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 7. Expands the six-agent pipeline architecture into an
agent-by-agent build plan with deliverables, validation gates, cost
model, vendor selection rationale, and rollout sequencing.

**Current state (12 May 2026):**

- `sentientia/agent1_sop_parser.py` — prototype on disk, untested at scale.
- `sentientia/agent5_scorm_packager.py` — prototype on disk, untested at scale.
- `content/sops/`, `content/parsed/`, `content/narrations/`, `content/slides/`,
  `content/voice/`, `content/scorm-output/` — directories present, empty.
- Agents 2 (Narration), 3 (Slides), 4 (Voice), 6 (Moodle Upload) — not
  built yet.
- Zero SCORM modules produced through the live pipeline.

## 1. Strategic position

SENTIENTIA is the single largest cost-saving thesis on the platform.
Section 11.2 of the master document estimates a per-course saving of
₹48,000 to ₹1.48 lakh against external-authoring benchmarks. At the
published target of ten new SCORM courses per month, the annualised
saving is ₹57.6 lakh to ₹1.77 crore — materially more than the
dedicated L&D engineer hire (Decision 13.3) costs.

The pipeline pays for the engineer hire on its own throughput basis.
Sequencing the engineer hire and the SENTIENTIA build is therefore not
a budget question; it is a calendar question.

## 2. Architectural commitments

Three commitments framed at project kickoff and held throughout the
build:

1. **Disk-artefact contract.** Each agent reads its input from disk,
   writes its output to disk, and exits. Agents do not chain at runtime.
   This makes each step independently retryable, makes failure modes
   inspectable (the artefact is on disk for forensic review), and makes
   the pipeline trivially restartable from any stage.
2. **Validation gates between every pair of agents.** A downstream agent
   refuses to run if the upstream artefact fails the documented schema /
   length / quality checks. The pipeline halts rather than silently
   produces low-quality output.
3. **Per-batch human-in-the-loop for paid vendor calls.** Agents 3
   (Gamma), 4 (ElevenLabs) and 6 (Moodle upload) require an explicit
   `[CONFIRM]` from the Head of L&D before a batch executes. The cost
   gate is enforced at the command-line, not at the agent layer.

## 3. Agent specifications

### Agent 1 — SOP Parser

**Input:** `content/sops/*.pdf` — Airpay Standard Operating Procedure
PDFs from SharePoint. Typical size 5-25 pages.

**Output:** `content/parsed/<source-name>-parsed.json` — structured
document with `title`, `sections[]`, `tables[]`, `images[]` references,
and the canonical full text.

**Build status:** Prototype script at `sentientia/agent1_sop_parser.py`
uses `pdfplumber` for text extraction and a heuristic header-detection
regex to split sections. Untested at production volume.

**Production work required:**
- Replace heuristic header detection with a more robust pattern (likely
  a small LLM-assisted pass for documents where headings aren't visually
  distinct).
- Handle scanned-image SOPs via OCR (Tesseract or a vendor API).
- Validate output JSON against a strict schema.
- Batch-mode driver script that processes a directory of PDFs in parallel
  with rate-limited disk IO.
- Output-size gate: reject parsed JSON over 2,000 words (master document
  Section 7.7 quality benchmark).

**Cost:** Zero external API. Compute is local.

**Validation gate to Agent 2:** JSON must validate against schema, have
non-empty `sections`, total `text` word count between 100 and 2,000.

### Agent 2 — Narration Generator

**Input:** `content/parsed/*-parsed.json`

**Output:** `content/narrations/<source-name>-narration.txt` — plain
text, learner-facing narration to be spoken by the voice agent.

**Build status:** Not yet built.

**Production work required:**
- Use Claude as the language model (the Anthropic API is already
  configured for documentation generation).
- Prompt template enforces the quality benchmarks: sentences ≤25 words,
  reading pace target 130 words per minute, no HTML / markdown, plain
  text only, no employee names or PII.
- Strip-PII pass before output write — defensive layer against accidental
  PII leakage to downstream agents.
- Two-pass generation: first pass produces a draft; second pass
  self-critiques against the sentence-length and pace targets and rewrites
  if necessary.

**Cost:** Anthropic Claude API at approximately ₹4 per 1,000 tokens.
A 2,000-word SOP produces approximately 800 words of narration, costing
about ₹15-20 per course.

**Validation gate to Agents 3 and 4:** Word count between 400 and 1,200.
No sentence exceeds 25 words. No HTML tags. No regex match on `[A-Z][a-z]+
[A-Z][a-z]+` (best-effort PII heuristic — flags John Smith style names).

### Agent 3 — Slides Generator

**Input:** `content/narrations/*-narration.txt`

**Output:** `content/slides/<source-name>-slides.json` — structured
slide deck with title slide, content slides (each with title + up to
five bullet points), and a closing-quiz slide.

**Build status:** Not yet built.

**Production work required:**
- Gamma HTTP API integration via the canonical `.claude/rules/api.md`
  pattern with the `[CONFIRM]` gate.
- Outline-to-deck prompt that constrains: ≤5 bullets per slide, ≤8 words
  per bullet, one image suggestion per slide (Gamma resolves images
  internally).
- Vendor failover: if Gamma is unavailable, fall back to a
  python-pptx-based local generator (no images, plain text — minimum
  viable).

**Cost:** Gamma's pricing model is per generated deck. Estimated ₹50-100
per course depending on slide count.

**Validation gate to Agent 5:** Slide count between 5 and 30. No bullet
exceeds 8 words. Title slide present.

### Agent 4 — Voice Generator

**Input:** `content/narrations/*-narration.txt`

**Output:** `content/voice/<source-name>-voice.mp3` — single MP3 file,
approximately 6 minutes for a 800-word narration at 130 wpm.

**Build status:** Not yet built.

**Production work required:**
- ElevenLabs API integration via `.claude/rules/api.md` pattern with
  `[CONFIRM]` gate. Model: `eleven_multilingual_v2`. Voice ID configured
  via `.env` (currently using a curated Indian-English voice).
- Voice settings: stability 0.50, similarity_boost 0.75, style 0.25,
  speaker boost on.
- PII assertion before send (no names, IDs, salary).
- Cost estimate logged before the call: at ₹0.30 per 1,000 characters,
  an 800-word narration is approximately ₹2 per course.

**Cost:** ElevenLabs at approximately ₹2-3 per course.

**Validation gate to Agent 5:** MP3 duration within 4-10 minutes for an
800-word input. File size > 100 KB. ID3 metadata present.

### Agent 5 — SCORM Packager

**Input:** `content/slides/*-slides.json` + `content/voice/*-voice.mp3`

**Output:** `content/scorm-output/<source-name>-scorm.zip` — valid
SCORM 1.2 package with `imsmanifest.xml` at ZIP root.

**Build status:** Prototype script at `sentientia/agent5_scorm_packager.py`.
Untested at scale.

**Production work required:**
- Validate the SCORM 1.2 manifest against the SCORM-specified XML schema.
- Generate `index.html` with embedded slide deck (or convert to slide
  images and embed via a slideshow JS), synced to the voice MP3 via
  HTML5 `<audio>` element + slide-timing JSON.
- Set `masteryscore=70` per master document Section 7.7.
- `scormdriver.js` bridge handles the API LMS handshake.
- ZIP-from-root structure check: the manifest must be at the ZIP root,
  not inside a wrapper folder. This is the most common SCORM packaging
  bug.
- Output-size gate: reject ZIPs over 50 MB (operational reason — file
  upload to Moodle gets slow above this).

**Cost:** Zero external API. Local compute.

**Validation gate to Agent 6:** SCORM ZIP passes the structural
validator (`scorm-validate` library or equivalent).

### Agent 6 — Moodle Upload

**Input:** `content/scorm-output/*-scorm.zip`

**Output:** Live course on the production Moodle with the SCORM activity
attached.

**Build status:** Not yet built.

**Production work required:**
- Call `core_files_upload` to push the ZIP into a draft file area.
- Call `core_course_create_courses` to create the course in the target
  category.
- Call `core_files_upload` and the module-instance creation flow to
  attach the SCORM activity to the new course.
- The whole flow is gated by an explicit `[CONFIRM]` per course since it
  modifies live production state.

**Cost:** Zero external API beyond Moodle's own platform.

**Validation gate (production):** After upload, the SCORM URL must
return 200 to a logged-in test user, and the SCORM driver must record
a `cmi.core.lesson_status='not attempted'` baseline event.

## 4. End-to-end orchestrator

A single command `python sentientia/run_pipeline.py <sop-pdf>` will:

1. Run Agent 1 against the SOP.
2. Run Agent 2 against the parsed JSON.
3. `[CONFIRM]` gate: print the narration, ask for approval.
4. Run Agent 3 against the narration. `[CONFIRM]` for Gamma cost.
5. Run Agent 4 against the narration. `[CONFIRM]` for ElevenLabs cost.
6. Run Agent 5 against slides + voice.
7. Validate the SCORM ZIP.
8. `[CONFIRM]` gate: ask for approval to upload to production.
9. Run Agent 6 against the SCORM ZIP.
10. Print the course URL on production.

Total wall time per course: approximately 12-20 minutes depending on
voice-generation queue depth.

## 5. Cost model per generated course

| Line item | Estimate per course |
|---|---|
| Agent 2 — Claude API (narration) | ₹15-20 |
| Agent 3 — Gamma API (slides) | ₹50-100 |
| Agent 4 — ElevenLabs API (voice) | ₹2-3 |
| Agents 1, 5, 6 — local compute | ~₹0 (electricity) |
| **Total cost per course** | **₹70-125** |

At the published target of ten new SCORM courses per month, the
annualised vendor spend is approximately ₹8,400 to ₹15,000.

Comparable external-authoring vendor benchmark: ₹50,000 to ₹1,50,000 per
course. The cost saving at scale is two to three orders of magnitude.

## 6. Multi-language rollout

The first SENTIENTIA cohort ships in English only. Once the pipeline is
proven stable, the multi-language extension is:

- **Agent 2 variant:** prompt template includes a `target_language`
  parameter. Claude generates Hindi / Marathi / Kannada / Swahili
  narration directly from the parsed JSON.
- **Agent 4 variant:** ElevenLabs `eleven_multilingual_v2` model already
  supports the four target languages.
- **Agent 5 variant:** the SCORM ZIP carries language-tagged subfolders
  (`en/`, `hi/`, `mr/`, `kn/`, `sw/`) and the manifest declares the
  multi-language structure.

Estimated effort: 8-12 hours per language for the prompt-tuning + voice
quality verification, in addition to the base SENTIENTIA build.

## 7. Quality regression suite

To detect drift (when Claude, Gamma or ElevenLabs change behaviour
silently), maintain a three-course reference suite:

1. **POSH compliance** — high-stakes regulatory content, low-tolerance
   for inaccuracy.
2. **Customer support playbook** — conversational tone, examples-heavy.
3. **AML fundamentals** — technical content with defined terminology.

Each reference course has a "golden" expected output for Agent 2 output
(within a similarity threshold). Each release of SENTIENTIA runs the
three references and flags drift. The first run after a vendor API
change is always against the reference suite, not against new content.

## 8. Build sequencing — first 90 days

Cross-references the master document Section 14.

| Week | Owner | Deliverable |
|---|---|---|
| Week 5 | Head of L&D | Agent 1 productionised — batch mode, schema validation, OCR fallback |
| Week 5 | Head of L&D | Agent 2 built — Claude-based narration with PII strip and sentence-length enforcement |
| Week 5 | Head of L&D + Compliance | First three pilot SOPs identified (POSH refresher + AML basics + Customer support playbook) |
| Week 6 | Head of L&D + Mgmt [CONFIRM] | Agent 4 built — first ElevenLabs paid run on the three pilot narrations |
| Week 6 | Head of L&D + Mgmt [CONFIRM] | Agent 3 built — first Gamma paid run on the three pilot narrations |
| Week 7 | Head of L&D | Agent 5 productionised — SCORM ZIPs produced for the three pilots |
| Week 7 | Head of L&D + Mgmt [CONFIRM] | Agent 6 built — first SENTIENTIA-generated SCORM uploaded to staging |
| Week 8 | Head of L&D | End-to-end orchestrator built |
| Week 8 | Head of L&D | Ten production SOPs processed through full pipeline; ten new SCORMs deployed to staging for QA review |
| Week 8 | Compliance Officer | QA review of the ten pilot SCORMs; approve or send back |
| Week 8 | Head of L&D | First five approved SCORMs deployed to production |

## 9. Vendor evaluation matrix

If a future decision moves SENTIENTIA off ElevenLabs / Gamma:

| Capability | Preferred | Alternative |
|---|---|---|
| Voice generation (English) | ElevenLabs `eleven_multilingual_v2` | Coqui TTS (self-hosted), AWS Polly, Microsoft Azure Speech |
| Voice generation (Hindi / regional) | ElevenLabs (multilingual model covers Hindi) | Microsoft Azure Speech (better regional-Indian-language quality), Google Cloud TTS |
| Slide generation | Gamma | python-pptx local fallback, or Tome, or Beautiful.ai |
| Language model (narration) | Claude (Anthropic) | OpenAI GPT-4, Google Gemini |
| SCORM packaging | Custom Python (sentientia/agent5) | iSpring (commercial), Articulate Storyline (commercial — defeats cost thesis) |

The architecture's vendor-agnostic seams (one HTTP-API class per agent)
mean any of the alternatives can be swapped in with under a day of work
per swap.

## 10. Risks specific to SENTIENTIA (cross-reference Supplement A)

- **V2** ElevenLabs API pricing or contract change — mitigated by the
  vendor-agnostic agent design.
- **V3** Gamma API pricing or contract change — same mitigation.
- **ST3** Generated content quality lower than vendor-authored — mitigated
  by the three-course reference suite (§7) and the per-batch `[CONFIRM]`
  gates.
- **C5** Content licence / copyright violation — SENTIENTIA-generated
  content is original (synthesised from Airpay's own SOPs); copyright
  exposure is materially lower than third-party-licensed content. But
  the SOPs themselves may contain third-party references that
  inadvertently propagate; the QA gate (week 8 Compliance review) must
  catch this.

## 11. Open decisions

| Decision | Owner | Recommended |
|---|---|---|
| Build SENTIENTIA before or after engineer hire (Decision 13.3)? | CEO + CHRO + Head of L&D | After hire. Reduces P1 (key-person) risk and accelerates throughput once the pipeline is live. |
| First pilot SOPs — choose for technical risk (regulatory) or for showcase value (high-volume training)? | Head of L&D + Compliance | Regulatory (POSH + AML). Higher stakes but higher visible value when they work. |
| Annual vendor budget ceiling — see Decision 13.2. The SENTIENTIA share of the ₹6 lakh ceiling is approximately ₹15,000 at full ten-per-month throughput. Headroom is generous. | CFO | Confirm the ₹6 lakh ceiling covers the pipeline plus the Phase 8.1 proctoring AWS spend. |
| ZEEA-tenant SCORM generation — does ZEEA's contract include access to SENTIENTIA-generated content? | Head of L&D + commercial | Treat as platform feature for ZEEA, not differentiated offering. |
| Public-tenant productisation of generated courses — does Public tenant pay separately for access to SENTIENTIA library? | CEO + Head of L&D | Defer until Public tenant reaches 1,000 paying users (Decision 13.7). |
