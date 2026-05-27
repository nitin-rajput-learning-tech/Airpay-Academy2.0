# Agent 3 — Slides Generator

**Stage:** B.2 of the SENTIENTIA Content Pipeline.
**Location:** `scripts/agents/agent3_slides_generator.py`
**Class of work:** Pure local execution — **no external API calls, no
[CONFIRM] gate.**
**Status:** Shipped 2026-05-25 on branch `claude/sleepy-fermi-YHAeG`.

Agent 3 is stage 3 of the SOP-to-SCORM pipeline. It reads the plain-text
narration emitted by Agent 2 and writes a structured slides JSON document
consumed by Agent 5 (SCORM Packager).

> Pipeline contract: each agent runs in its own session, reads from
> disk, writes to disk. Never chain agents in one process. See
> `CLAUDE.md §9` for the full pipeline.

---

## Usage

### Command line

```bash
python scripts/agents/agent3_slides_generator.py \
    --input  content/narrations/AML-2024-narration.txt \
    --output content/slides/AML-2024-slides.json
```

Both flags are required. Output directories are created on demand.

### Library

```python
from pathlib import Path
from scripts.agents import agent3_slides_generator as agent3

narration = Path("content/narrations/AML-narration.txt").read_text("utf-8")
payload = agent3.generate_slides(narration, source_file="AML-narration.txt")
# payload is a dict matching the schema below.
```

---

## Output schema

```json
{
  "title": "Anti-Money Laundering SOP",
  "slide_count": 12,
  "slides": [
    {
      "index": 1,
      "title": "Welcome to this SOP",
      "bullets": [
        "We will walk through this together"
      ],
      "speaker_notes": "Welcome to Anti-Money Laundering SOP. We will walk through this together step by step."
    }
  ],
  "source_file": "AML-2024-narration.txt",
  "generated_at": "2026-05-25T12:34:56Z"
}
```

### Field reference

| Field | Type | Notes |
|-------|------|-------|
| `title` | string | Deck title. Prefers `Welcome to <X>` from the first paragraph, else the first slide's title. `<= 8` words. |
| `slide_count` | integer | Number of slides. Equals `len(slides)`. |
| `slides` | array | One entry per slide (see below). |
| `slides[].index` | integer | 1-based, contiguous, unique. |
| `slides[].title` | string | `<= 8` words. From a `Section: <X>.` prefix if present, else the first sentence trimmed to 8 words. |
| `slides[].bullets` | array of strings | `<= 5` bullets, each `<= 8` words, terminal punctuation stripped. |
| `slides[].speaker_notes` | string | The full narration paragraph for this slide (verbatim). |
| `source_file` | string | Basename of the input narration file. |
| `generated_at` | string | UTC timestamp in `YYYY-MM-DDTHH:MM:SSZ`. |

### Constraints (all enforced)

| Constraint | Limit |
|------------|-------|
| Slide count | `1 <= slide_count <= 30` (target band 10-15) |
| Title words | `<= 8` |
| Bullets per slide | `<= 5` |
| Words per bullet | `<= 8` |
| Speaker notes | non-empty |

If any constraint is violated after generation, `generate_slides`
raises `ValueError` and the CLI exits 1 without writing a file.

---

## How slides are built

1. **Paragraphs.** The narration is split on blank-line boundaries into
   paragraphs. Each paragraph is the seed of one slide and becomes the
   slide's `speaker_notes` verbatim.
2. **Rebalancing to the 10-15 band.**
   - *Too few paragraphs:* the longest paragraph is split at its
     sentence midpoint, repeatedly, until the deck reaches the target
     minimum (or no paragraph can be split further).
   - *Too many paragraphs:* the shortest adjacent pair is merged,
     repeatedly, until the deck fits the target maximum. A hard cap of
     30 slides is enforced regardless.
3. **Title.**
   - If the paragraph opens with `Section: <X>.` the title is `<X>`
     (Agent 2 emits this prefix per heading).
   - Otherwise the first sentence is trimmed to 8 words.
   - Empty titles fall back to `Slide N`.
4. **Bullets.** Each remaining sentence becomes one bullet, trimmed to
   8 words with terminal punctuation removed. Capped at 5 bullets; the
   rest are dropped from the slide face but remain in `speaker_notes`.

### Determinism

For a given narration the output is byte-for-byte identical across runs
(except `generated_at`). The rebalancing algorithm is deterministic —
longest/shortest selection breaks ties by lowest index.

---

## Error modes

| Exit code | Cause | Message |
|-----------|-------|---------|
| 0 | Slides generated | `agent3: wrote Y.json (N slides, deck title '...')` |
| 1 | Empty narration | `agent3: validation error — narration text is empty` |
| 1 | HTML in narration | `agent3: validation error — narration contains HTML tags ...` |
| 1 | Constraint violation | `agent3: validation error — slide N <constraint> ...` |
| 2 | Input file missing | `agent3: I/O error — narration not found: X.txt` |
| 2 | Cannot write output | `agent3: I/O error writing Y.json: <OSError>` |

---

## Integration with Agent 5

Agent 5 (SCORM Packager, **not yet built**) will consume `slides.json`
alongside Agent 4's `voice.mp3`:

- `title` -> SCORM manifest organisation title.
- `slides[].title` + `slides[].bullets` -> on-screen slide content.
- `slides[].speaker_notes` -> per-slide audio cue / caption track.
- `slide_count` -> manifest item count.

---

## Files this agent touches

| Path | Purpose |
|------|---------|
| `scripts/agents/agent3_slides_generator.py` | The agent itself. |
| `tests/agents/test_agent3.py` | Schema, rebalancing, constraint, CLI tests. |
| `content/slides/*-slides.json` | Output destination. |
| `docs/sentientia-agents/AGENT-3-SLIDES-GENERATOR.md` | This document. |

## Testing

```bash
python -m pytest tests/agents/test_agent3.py -v
```

Tests cover the output schema, every field constraint, paragraph
rebalancing (split-up and merge-down), `Section:` title extraction, and
the CLI exit-code matrix.

## Hard rules carried over from `CLAUDE.md`

- Pure local execution — Agent 3 never opens a socket.
- Never chain agents in one process — disk-mediated handoff only.
- Never exceed 5 bullets per slide or 8 words per bullet/title — the
  C-suite prototype design system depends on these caps.
