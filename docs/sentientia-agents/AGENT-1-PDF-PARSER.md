# Agent 1 — PDF SOP Parser

**Stage:** B.0 (MVP) of the SENTIENTIA Content Pipeline.
**Location:** `scripts/agents/agent1_sop_parser.py`
**Class of work:** Pure local execution — no external API calls.
**Status:** Shipped 2026-05-24 on branch `claude/gifted-faraday-V761L`.

Agent 1 is the entry stage of the SOP-to-SCORM pipeline. It reads a
single Standard Operating Procedure PDF from `content/sops/` and writes
a structured JSON document to `content/parsed/`. Agent 2 (Narration
Generator) reads that JSON as its sole input.

> Pipeline contract: each agent runs in its own session, reads from
> disk, writes to disk. Never chain agents in one process. See
> `CLAUDE.md §9` for the full pipeline.

---

## Usage

### Command line

```bash
python scripts/agents/agent1_sop_parser.py \
    --input  content/sops/AML-2024.pdf \
    --output content/parsed/AML-2024-parsed.json
```

Both flags are required. Output directories are created on demand.

### Library

```python
from pathlib import Path
from scripts.agents import agent1_sop_parser as agent1

data = agent1.parse_pdf(Path("content/sops/AML-2024.pdf"))
# data is a dict matching the schema below; write it yourself,
# or call agent1.main([...]) for the CLI wrapper.
```

### Installing dependencies

```bash
pip install -r requirements.txt
```

`pdfplumber>=0.11` is the only runtime dependency. `reportlab` and
`pytest` are dev-only (fixture rendering + test runner).

---

## Output schema

```json
{
  "title": "Anti-Money Laundering SOP",
  "headings": [
    {"level": 1, "text": "Anti-Money Laundering SOP"},
    {"level": 2, "text": "Overview"},
    {"level": 3, "text": "Pipeline Stages"}
  ],
  "paragraphs": [
    "First body paragraph...",
    "Second body paragraph..."
  ],
  "lists": [
    {"type": "ordered",   "items": ["Step 1", "Step 2", "Step 3"]},
    {"type": "unordered", "items": ["Point A", "Point B"]}
  ],
  "word_count": 1234,
  "source_file": "AML-2024.pdf",
  "parsed_at": "2026-05-24T12:34:56Z"
}
```

### Field reference

| Field | Type | Notes |
|-------|------|-------|
| `title` | string | The first level-1 heading. Falls back to the first heading at any level, then to the first line of text. |
| `headings` | array | Each entry has `level` (1-3) and `text`. Level 1 is the largest font size found in the document, 2 is the next, 3 is the smallest above body. |
| `paragraphs` | array of strings | Whitespace-normalised body paragraphs. Headings and list items are excluded. |
| `lists` | array of objects | Each entry has `type` (`"ordered"` or `"unordered"`) and a non-empty `items` array. Consecutive items of the same type form a single list. |
| `word_count` | integer | Total words across `headings[*].text`, `paragraphs`, and `lists[*].items[*]`. Capped at 2000 — see error modes. |
| `source_file` | string | Basename of the input PDF. |
| `parsed_at` | string | UTC timestamp in `YYYY-MM-DDTHH:MM:SSZ`. Excluded from determinism checks. |

### Determinism guarantee

For a given input PDF, every field other than `parsed_at` is
byte-for-byte identical across repeated runs. This is asserted by
`test_repeated_runs_produce_same_structure` and is required by Agent 2
which fingerprints inputs to short-circuit regenerations.

---

## How structure is recovered

1. **Lines.** `pdfplumber.Page.extract_words(extra_attrs=["size", "fontname"])`
   gives a per-word token stream. Words are grouped into visual lines
   by their `top` coordinate (3-point tolerance for line-spacing jitter).
2. **Body size.** The mode of rounded font sizes is taken as the body
   size — the most common text size on the page is the body.
3. **Heading thresholds.** Up to three distinct sizes larger than body
   are picked, sorted ascending. The largest maps to level 1; the next
   to level 2; the next to level 3. Missing levels are filled with
   `inf` so they never match.
4. **List detection.** Two regexes run on each line:
   - `_UNORDERED_RE` accepts a leading bullet glyph from
     `• ‣ ▪ ◦ ● ○ ▶ ► ◆ ◇ ■ □ – — - *` followed by whitespace.
   - `_ORDERED_RE` accepts a digit, lowercase letter, uppercase letter,
     or Roman numeral followed by `.` or `)` and whitespace
     (`1.`, `2)`, `(3)`, `a.`, `B)`, `iv.`). It does **not** match a
     bare digit (so "5 million customers" is left as a paragraph).
5. **Paragraph aggregation.** Consecutive non-heading, non-list lines
   are concatenated into a single paragraph, separated by a single
   space. Blank lines, headings, or a list-type switch flush the
   buffer.

---

## Error modes

| Exit code | Cause | Message |
|-----------|-------|---------|
| 0 | Parsed successfully | `agent1: parsed X.pdf -> Y.json (N words, ...)` |
| 1 | Word cap exceeded | `agent1: validation error — SOP exceeds 2000-word cap: got <N> words in X.pdf. Split the SOP before continuing the pipeline.` |
| 1 | No extractable text | `agent1: validation error — No extractable text in X.pdf. Scanned-image PDFs require OCR (not in MVP scope).` |
| 2 | Input file missing | `agent1: I/O error — PDF not found: X.pdf` |
| 2 | Cannot write output | `agent1: I/O error writing Y.json: <OSError>` |

The library entry point (`agent1.parse_pdf`) raises:

- `FileNotFoundError` — when the input PDF does not exist.
- `ValueError` — for empty extraction and the 2000-word cap.
- `OSError` — propagated from `pdfplumber` for malformed PDFs.

When the word cap is exceeded the CLI **does not** write a partial
output file — the caller must split the SOP and re-run.

### Why 2000 words?

`CLAUDE.md §9` constrains each SCORM module to a single SOP capped at
2000 words so the downstream narration (Agent 2, ~130 wpm, ≤25-word
sentences) fits comfortably under fifteen minutes of audio. Splitting
oversized SOPs is a human decision, not a parser decision — Agent 1
fails loudly rather than truncating silently.

### Scanned PDFs

Scanned-image PDFs (PDFs whose pages are raster images of text) yield
no `extract_words` output. The MVP rejects them with a clear error so
downstream agents are never fed an empty document. OCR support is
deferred to Phase B.1.

---

## Integration with Agent 2

Agent 2 (`scripts/agents/agent2_narration_generator.py`, **not yet
built**) will consume the JSON in two passes:

1. **Section grouping.** Group `paragraphs` and `lists` under the
   nearest preceding heading. Headings with no following content are
   carried into the next section.
2. **Narration generation.** Emit a per-section narration block. The
   `title` becomes the narration's opening line; each heading becomes
   a section break; paragraphs are paraphrased into ≤25-word sentences;
   list items become one sentence each.

Agent 2 reads `word_count` to budget the narration. The 2000-word cap
on Agent 1 maps to ≈15 minutes of speech at 130 wpm — comfortably
inside the SCORM-module target of 10-20 minutes.

---

## Testing

```bash
# Run only Agent 1 tests:
python -m pytest tests/agents/test_agent1.py -v

# Run the whole tests/ tree (when more agents land):
python -m pytest tests/ -v
```

29 tests cover:
- The full output schema (every required field, JSON-round-trippable).
- Heading levels, paragraph extraction, list-type detection.
- Title-fallback when no level-1 heading is present.
- Word-cap enforcement (over the cap raises, under the cap passes).
- Regex coverage for every supported bullet glyph and ordered marker.
- Negative regex coverage (a sentence starting with a bare digit must
  not be misread as an ordered list).
- CLI exit codes for success / validation / I/O paths.
- Determinism (two runs on the same PDF agree byte-for-byte).

PDF fixtures are rendered on demand by `tests/agents/_pdf_builder.py`,
which is also the script that builds the checked-in
`content/sops/SAMPLE-SOP.pdf`. No binary fixtures are required to
commit.

---

## Sample run

```bash
$ python scripts/agents/agent1_sop_parser.py \
    --input  content/sops/SAMPLE-SOP.pdf \
    --output content/parsed/SAMPLE-SOP-parsed.json
agent1: parsed SAMPLE-SOP.pdf -> content/parsed/SAMPLE-SOP-parsed.json \
    (165 words, 5 headings, 3 paragraphs, 2 lists)
```

The corresponding JSON is checked in at
`content/parsed/SAMPLE-SOP-parsed.json` as a reference for Agent 2
builders.

---

## Files this agent touches

| Path | Purpose |
|------|---------|
| `scripts/agents/agent1_sop_parser.py` | The agent itself. |
| `tests/agents/test_agent1.py` | Unit + CLI tests. |
| `tests/agents/_pdf_builder.py` | Reportlab helper used by tests and the sample-SOP generator. |
| `content/sops/SAMPLE-SOP.pdf` | Synthetic fixture (committed). |
| `content/parsed/SAMPLE-SOP-parsed.json` | Sample output (committed for reference). |
| `requirements.txt` | `pdfplumber`, `reportlab`, `pytest` pins. |
| `docs/sentientia-agents/AGENT-1-PDF-PARSER.md` | This document. |

## Hard rules carried over from `CLAUDE.md`

- Never delete a file in `content/sops/`.
- Never POST to an external service from Agent 1 — pure local execution.
- Never chain agents in one process — one agent per session, disk-mediated handoff.
- The word cap is non-negotiable. Don't paper over it — split the SOP.
