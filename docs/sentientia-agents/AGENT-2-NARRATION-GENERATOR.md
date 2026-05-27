# Agent 2 — Narration Generator

**Stage:** B.1 of the SENTIENTIA Content Pipeline.
**Location:** `scripts/agents/agent2_narration_generator.py`
**Class of work:** Mock mode is pure local execution. Live mode is a
single Anthropic Messages API call gated by `--confirm`.
**Status:** Shipped 2026-05-25 on branch `claude/sleepy-fermi-YHAeG`.

Agent 2 is stage 2 of the SOP-to-SCORM pipeline. It consumes the JSON
emitted by Agent 1 and writes a plain-text narration script consumed by
Agent 3 (Slides) and Agent 4 (Voice).

> Pipeline contract: each agent runs in its own session, reads from
> disk, writes to disk. Never chain agents in one process. See
> `CLAUDE.md §9` for the full pipeline.

---

## Usage

### Command line

```bash
# Default — offline mock mode (no API call, no cost):
python scripts/agents/agent2_narration_generator.py \
    --input  content/parsed/AML-2024-parsed.json \
    --output content/narrations/AML-2024-narration.txt

# [CONFIRM] required — live Anthropic call:
python scripts/agents/agent2_narration_generator.py \
    --input  content/parsed/AML-2024-parsed.json \
    --output content/narrations/AML-2024-narration.txt \
    --confirm
```

`--confirm` is the [CONFIRM] gate required by `CLAUDE.md §13`. Without
it the agent never opens a socket. With it the agent reads
`ANTHROPIC_API_KEY` from the environment and POSTs to
`https://api.anthropic.com/v1/messages` once.

### Library

```python
from pathlib import Path
import json
from scripts.agents import agent2_narration_generator as agent2

parsed = json.loads(Path("content/parsed/AML-parsed.json").read_text("utf-8"))

# Mock mode — deterministic, offline.
text = agent2.generate_mock_narration(parsed)
agent2.validate_narration(text)        # raises on constraint violation
Path("content/narrations/AML-narration.txt").write_text(text, "utf-8")

# Live mode — builds the prompt + calls Claude (only with API key).
prompt = agent2.build_anthropic_prompt(parsed)
text = agent2.call_anthropic(prompt, api_key=os.environ["ANTHROPIC_API_KEY"])
```

---

## Input — Agent 1 schema

Agent 2 accepts the exact JSON emitted by Agent 1
(`docs/sentientia-agents/AGENT-1-PDF-PARSER.md`). The schema is validated
before any work begins; missing or malformed fields raise `ValueError`
and the CLI exits with code 1.

Required top-level keys: `title`, `headings`, `paragraphs`, `lists`,
`word_count`, `source_file`, `parsed_at`.

## Output — narration text file

Plain UTF-8 text. Blank lines separate paragraphs. No HTML, no markdown.

```
Welcome to Anti-Money Laundering SOP. We will walk through this together step by step.

Section: Overview. Frontline staff watch every transaction over the threshold. ...

Section: Reporting. Capture the customer identity proof. ...

That concludes Anti-Money Laundering SOP. Thank you for completing this module.
```

### Hard constraints (enforced after generation)

| Constraint | Limit | Source |
|------------|-------|--------|
| Total words | `<= 2000` | `CLAUDE.md §9` SCORM module budget |
| Words per sentence | `<= 25` | Agent 4 pacing (130 wpm voice-over) |
| Plain text only | no HTML / markdown | downstream voice / SCORM safety |

If any constraint is violated the CLI exits with code 1 and **does not**
write a partial file. The caller must re-run.

---

## Modes

### Mock mode (default)

`generate_mock_narration(parsed_dict)` builds a narration deterministically
from the Agent 1 JSON:

1. **Opening.** `Welcome to {title}. We'll walk through this together step by step.`
2. **One paragraph per heading** in input order. Each paragraph contains:
   - `Section: {heading}.` as the opener.
   - Each input paragraph reflowed into `<=25`-word sentences (long
     sentences are word-chunked at `MAX_WORDS_PER_SENTENCE` boundaries).
   - Each ordered list item rewritten as `Step N. <item>.`.
   - Each unordered list item rewritten as a standalone sentence.
3. **Closing.** `That concludes {title}. Thank you for completing this module.`
4. **Cap guard.** If the generated text overruns 2000 words, trailing
   sentences are dropped from the last paragraph until the cap fits.

Determinism guarantee: two runs on the same parsed JSON produce
byte-for-byte identical output. CI relies on this for cache stability
between Agent 2 and Agent 5.

### Live mode (`--confirm` required)

Builds a prompt from the parsed JSON using `build_anthropic_prompt(parsed)`
and POSTs it to the Anthropic Messages API. Defaults:

- model: `claude-opus-4-7`
- max_tokens: 4096
- timeout: 120 seconds

The prompt embeds the parsed JSON verbatim and enumerates every hard
constraint. The response text is then validated against the same
`validate_narration` guard used in mock mode — a non-compliant
response is rejected and the CLI exits 1 without writing.

Cost note: a single Opus 4.7 call against a ~1500-word SOP costs a
small amount of API credit (see Anthropic pricing). The agent prints
the call announcement to stderr before posting; nothing is logged to
disk that would leak the API key.

---

## CLI flags

| Flag | Default | Notes |
|------|---------|-------|
| `--input` / `-i` | required | Path to Agent 1 JSON. |
| `--output` / `-o` | required | Path to write narration text. Parent dirs are created. |
| `--confirm` | off | Authorise live Anthropic call. Without it the agent runs offline. |
| `--model` | `claude-opus-4-7` | Anthropic model id (live mode only). |
| `--max-tokens` | 4096 | Max tokens in the live response. |

## Exit codes

| Code | Cause |
|------|-------|
| 0 | Narration generated, validated, and written. |
| 1 | Input JSON invalid OR generated narration failed validation. |
| 2 | I/O failure (input missing, output dir not writable). |
| 3 | Live mode config or API error (missing `ANTHROPIC_API_KEY`, HTTP failure, empty content). |

---

## Integration with Agents 3 and 4

The narration text is the single input for both downstream agents:

- **Agent 3 (Slides)** splits paragraphs on blank lines and treats
  `Section: <X>.` prefixes as slide titles.
- **Agent 4 (Voice)** sends the whole text to ElevenLabs and saves the
  returned MP3.

Both downstream agents re-validate the narration before consuming it,
so a corrupt or oversized narration is caught twice.

---

## Files this agent touches

| Path | Purpose |
|------|---------|
| `scripts/agents/agent2_narration_generator.py` | The agent itself. |
| `tests/agents/test_agent2.py` | Schema, validation, mock, live (mocked), CLI tests. |
| `content/narrations/*-narration.txt` | Output destination. |
| `docs/sentientia-agents/AGENT-2-NARRATION-GENERATOR.md` | This document. |

## Testing

```bash
# Unit + CLI tests (hermetic — no API call, no key required):
python -m pytest tests/agents/test_agent2.py -v
```

The `call_anthropic` helper accepts an injected `post_fn` so the live
HTTP path is exercised without ever reaching the network. CI runs the
mock path end-to-end via `run_pipeline_test.py`.

## Hard rules carried over from `CLAUDE.md`

- Never POST to the Anthropic API without `--confirm` (the [CONFIRM] gate).
- Never log the `ANTHROPIC_API_KEY` value.
- Never chain agents in one process — disk-mediated handoff only.
- Never strip the 25-words-per-sentence cap; Agent 4 pacing depends on it.
