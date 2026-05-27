# Agent 4 — Voice Generator

**Stage:** B.3 of the SENTIENTIA Content Pipeline.
**Location:** `scripts/agents/agent4_voice_generator.py`
**Class of work:** Mock mode is pure local execution. Live mode is a
single ElevenLabs `text-to-speech` API call gated by `--confirm`.
**Status:** Shipped 2026-05-25 on branch `claude/sleepy-fermi-YHAeG`.

Agent 4 is stage 4 of the SOP-to-SCORM pipeline. It reads the plain-text
narration emitted by Agent 2 and synthesises a single MP3 voice-over for
the SCORM packager (Agent 5).

> Pipeline contract: each agent runs in its own session, reads from
> disk, writes to disk. Never chain agents in one process. See
> `CLAUDE.md §9` for the full pipeline. The ElevenLabs API contract is
> documented in `.claude/rules/api.md`.

---

## Usage

### Command line

```bash
# Default — offline mock mode (no API call, no cost):
python scripts/agents/agent4_voice_generator.py \
    --input  content/narrations/AML-2024-narration.txt \
    --output content/voice/AML-2024-voice.mp3

# [CONFIRM] required — live ElevenLabs call:
python scripts/agents/agent4_voice_generator.py \
    --input  content/narrations/AML-2024-narration.txt \
    --output content/voice/AML-2024-voice.mp3 \
    --confirm --voice-id pNInz6obpgDQGcFmaJgB
```

`--confirm` is the [CONFIRM] gate required by `CLAUDE.md §13` and
`.claude/rules/api.md` (ElevenLabs charges ~$0.30 per 1000 characters).
Without it the agent never opens a socket.

### Library

```python
from pathlib import Path
from scripts.agents import agent4_voice_generator as agent4

narration = Path("content/narrations/AML-narration.txt").read_text("utf-8")
agent4.validate_narration_for_voice(narration)   # size + PII guard

# Mock mode — deterministic placeholder bytes, offline.
audio = agent4.generate_mock_mp3(narration)

# Live mode — only with key + voice id.
audio = agent4.synthesise_voice(
    narration,
    api_key=os.environ["ELEVENLABS_API_KEY"],
    voice_id=os.environ["ELEVENLABS_VOICE_ID"],
)
Path("content/voice/AML-voice.mp3").write_bytes(audio)
```

---

## Input — narration text

The plain-text file from Agent 2. Before any work, Agent 4 validates:

- **Size.** `<= 2100` words (the `.claude/rules/api.md` Agent 4 ceiling;
  a hair above Agent 2's 2000-word cap to absorb rounding).
- **No PII.** A set of regexes rejects the most common PII shapes —
  email addresses, phone numbers, salary/CTC figures, employee-id-shaped
  tokens, SSN-shaped tokens, and literal "API key". This enforces the
  `CLAUDE.md §13` rule: never send employee data to ElevenLabs.

A failed guard exits 1 and posts nothing.

## Output — MP3 file

A binary `.mp3` written to the `--output` path. In live mode this is the
real ElevenLabs audio. In mock mode it is a deterministic placeholder
(see below) carrying a valid ID3v2.4 header so file-type sniffers report
`audio/mpeg`.

---

## Modes

### Mock mode (default)

`generate_mock_mp3(narration)` returns a deterministic byte string:

```
<10-byte ID3v2.4 header>
SENTIENTIA-MOCK-MP3
words=<N>
chars=<N>
cost-estimate-usd=<...>
--- narration follows ---
<the narration text>
```

This is **not playable audio** — it exists so Agent 5 has a real file on
disk during pipeline rehearsal and CI, without any API spend. Two runs
on the same narration produce byte-for-byte identical output.

### Live mode (`--confirm` required)

POSTs to `https://api.elevenlabs.io/v1/text-to-speech/{voice_id}` with
the recommended voice settings from `.claude/rules/api.md`:

```python
{
    "stability": 0.50,
    "similarity_boost": 0.75,
    "style": 0.25,
    "use_speaker_boost": True,
}
```

Defaults: `model_id = eleven_multilingual_v2`, timeout 120 s. The agent
prints the character count and the estimated USD cost to stderr **before**
posting. The `xi-api-key` header is never logged.

---

## CLI flags

| Flag | Default | Notes |
|------|---------|-------|
| `--input` / `-i` | required | Path to the narration text from Agent 2. |
| `--output` / `-o` | required | Path to write the MP3 (or mock MP3). |
| `--confirm` | off | Authorise live ElevenLabs call. Without it the agent runs offline. |
| `--voice-id` | env `ELEVENLABS_VOICE_ID` | ElevenLabs voice id (live mode only). |
| `--model-id` | `eleven_multilingual_v2` | ElevenLabs model id (live mode only). |

## Exit codes

| Code | Cause |
|------|-------|
| 0 | Audio (real or mock) written. |
| 1 | Narration empty, oversized, or contains PII-shaped tokens. |
| 2 | I/O failure (input missing, output dir not writable). |
| 3 | Live mode config or API error (missing `ELEVENLABS_API_KEY` / voice id, HTTP failure, empty audio body). |

---

## Cost model

| Input chars | Approx cost |
|-------------|-------------|
| 1,000 | ~$0.30 |
| 7,500 (≈1500-word SOP) | ~$2.25 |
| 10,500 (≈2100-word ceiling) | ~$3.15 |

`estimate_cost_usd(text)` returns `len(text) / 1000 * 0.30`. The estimate
is printed before every live call so spend is never silent.

---

## Integration with Agent 5

Agent 5 (SCORM Packager) bundles `voice.mp3` next to Agent 3's
`slides.json`. The audio plays as the module narration; slide
`speaker_notes` line up with the spoken script because both derive from
the same Agent 2 narration.

---

## Files this agent touches

| Path | Purpose |
|------|---------|
| `scripts/agents/agent4_voice_generator.py` | The agent itself. |
| `tests/agents/test_agent4.py` | Validation, PII, mock, live (mocked), CLI tests. |
| `content/voice/*-voice.mp3` | Output destination. |
| `docs/sentientia-agents/AGENT-4-VOICE-GENERATOR.md` | This document. |

## Testing

```bash
python -m pytest tests/agents/test_agent4.py -v
```

The `synthesise_voice` helper accepts an injected `post_fn` so the live
HTTP path is exercised without reaching the network. The CLI live-mode
tests assert the env-var gate exits 3 when no key is set, so CI can never
accidentally spend.

## Hard rules carried over from `CLAUDE.md`

- Never POST to ElevenLabs without `--confirm` (the [CONFIRM] gate).
- Never log the `ELEVENLABS_API_KEY` value.
- Never send PII to ElevenLabs — the input guard enforces this.
- Never chain agents in one process — disk-mediated handoff only.
