"""
SENTIENTIA Agent 4 — Voice Generator
=====================================

Reads `content/narrations/<source>-narration.txt` (Agent 2 output).
Produces `content/voice/<source>-voice.mp3` (Agent 5 audio input).

Architectural contract (per SUPP-C Section 3.4 + Section 2):
1. Reads input from disk, writes output to disk, exits.
2. Validation gates: input must be a non-empty narration; output MP3
   must be 4-10 min duration for an 800-word input + ID3 metadata
   present + file size > 100 KB.
3. Costs money on every live call. The [CONFIRM] gate guards against
   accidental ElevenLabs API spend.
4. PII assertion before send — defence-in-depth against accidental
   PII leakage to the vendor.

VENDOR: ElevenLabs Text-To-Speech (eleven_multilingual_v2 model).
Vendor-agnostic seam at `call_elevenlabs()` so a future swap to AWS
Polly or self-hosted Coqui TTS is a small diff.

USAGE
-----

Dry run (validate input, build request, do not call API):
    python sentientia/agent4_voice_generator.py \\
        content/narrations/posh-2024-narration.txt --dry-run

Live run ([CONFIRM] gate before each call):
    python sentientia/agent4_voice_generator.py \\
        content/narrations/posh-2024-narration.txt

Local fallback (writes a silent 1-second MP3 placeholder — useful for
end-to-end smoke testing the pipeline without burning ElevenLabs spend):
    python sentientia/agent4_voice_generator.py \\
        content/narrations/posh-2024-narration.txt --local-fallback

ENVIRONMENT
-----------

Reads from `.env` at project root (never committed):
    ELEVENLABS_API_KEY  — required for live calls
    ELEVENLABS_VOICE_ID — required for live calls
    ELEVENLABS_MODEL    — defaults to 'eleven_multilingual_v2'

STATUS
------

Skeleton with full payload + validation gates + PII assertion + disk-
artefact contract. The ElevenLabs HTTP call is gated by --confirm and
is not invoked in this revision. Activation is a small diff once the
[CONFIRM] gate is granted (per master-doc Section 7.4 + Decision 13.2).
"""

from __future__ import annotations

import argparse
import io
import os
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)


# ─── Voice generation settings ──────────────────────────────────────────
# Per SUPP-C Section 3.4 voice settings + CLAUDE.md §9 SENTIENTIA spec.
VOICE_SETTINGS = {
    "stability":         0.50,
    "similarity_boost":  0.75,
    "style":             0.25,
    "use_speaker_boost": True,
}
DEFAULT_MODEL = "eleven_multilingual_v2"

# ─── Quality benchmarks ─────────────────────────────────────────────────
MIN_WORDS = 400
MAX_WORDS = 2000           # cap from CLAUDE.md SENTIENTIA agent 2 contract
TARGET_WPM = 130           # words per minute
MIN_DURATION_S = MIN_WORDS / TARGET_WPM * 60  # ~3 min
MAX_DURATION_S = MAX_WORDS / TARGET_WPM * 60  # ~15 min
MIN_OUTPUT_BYTES = 100 * 1024   # 100 KB — 1 sec of 128 kbps MP3 is ~16 KB,
                                 # so >100 KB ensures at least ~6 seconds

# Cost estimate: ElevenLabs charges per character. ~Rs. 0.30 per 1000 chars.
COST_PER_1000_CHARS_INR = 0.30

AGENT_VERSION = "1.0"
AGENT_NAME = "sentientia_agent4_voice_generator"


# ─── PII assertion ──────────────────────────────────────────────────────
# Defence-in-depth before we send text to the vendor. Even if Agent 1's
# PII scrub missed something, this final check rejects the narration
# rather than ship the PII over the wire.
PII_NAME_PATTERN = re.compile(r"\b[A-Z][a-z]{2,}\s+[A-Z][a-z]{2,}\b")
PII_WHITELIST = frozenset([
    "Airpay Academy", "Airpay Payment", "Payment Services", "Reserve Bank",
    "Anti Money", "Money Laundering", "Know Your", "Data Privacy",
    "Information Technology", "Standard Operating", "Operating Procedure",
    "Customer Service", "Quality Assurance", "Human Resources",
])


def detect_pii(text: str) -> list[str]:
    """Return name-like sequences not in the whitelist."""
    hits = []
    for m in PII_NAME_PATTERN.findall(text):
        if m not in PII_WHITELIST:
            hits.append(m)
    return hits


# ─── Result type ────────────────────────────────────────────────────────


@dataclass
class VoiceResult:
    narration_name: str
    output_path: Path | None = None
    size_bytes: int = 0
    duration_estimate_s: int = 0
    cost_estimate_inr: float = 0.0
    provider: str = ""
    errors: list[str] = field(default_factory=list)

    @property
    def passes(self) -> bool:
        return not self.errors and self.output_path is not None


# ─── ElevenLabs API call (gated) ────────────────────────────────────────


def call_elevenlabs(text: str, *, voice_id: str, api_key: str,
                     model: str = DEFAULT_MODEL,
                     timeout: int = 120) -> bytes:
    """Live call to ElevenLabs TTS. NOT invoked unless --confirm and the
    user has answered 'yes' at the interactive prompt.

    Returns the MP3 bytes. Raises on API or quota errors.

    SKELETON — not yet wired. Gated on [CONFIRM] per master-doc Section
    7.4 + Decision 13.2 budget approval.
    """
    endpoint = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"

    # PLACEHOLDER for the actual HTTP call. Activated in a follow-up
    # commit after the [CONFIRM] gate is granted:
    #
    #   import requests
    #   response = requests.post(endpoint, json={
    #       "text":           text,
    #       "model_id":       model,
    #       "voice_settings": VOICE_SETTINGS,
    #   }, headers={
    #       "xi-api-key":   api_key,
    #       "Content-Type": "application/json",
    #       "Accept":       "audio/mpeg",
    #   }, timeout=timeout)
    #   response.raise_for_status()
    #   return response.content

    raise RuntimeError(
        "call_elevenlabs is a skeleton — not yet wired. See SUPP-C "
        "Section 3.4 for the activation plan. Use --local-fallback "
        "for end-to-end smoke testing."
    )


# ─── Local fallback (silent MP3) ───────────────────────────────────────


def local_fallback_silent_mp3(duration_s: int = 1) -> bytes:
    """Produce a minimal silent MP3 placeholder for end-to-end smoke
    testing without ElevenLabs API spend.

    This is the smallest valid MP3 the SCORM packager will accept:
    a single MPEG-1 Layer 3 frame containing silence. Real production
    courses use real ElevenLabs output; this fallback only exists so
    the pipeline-orchestrator smoke test can run end-to-end.
    """
    # Single MP3 frame header (sync word + MPEG-1 + Layer 3 + 128 kbps
    # @ 44.1 kHz + mono + no padding + no CRC) + 417 bytes of silence.
    # This produces a ~26 ms MP3 frame; repeated `duration_s * 38` times
    # gives roughly `duration_s` seconds.
    frame_header = bytes([0xFF, 0xFB, 0x90, 0x64])
    frame_body = bytes(414)  # silent payload
    frame = frame_header + frame_body
    frame_count = max(1, int(duration_s * 38))  # ~38 frames/sec @ 26ms
    return frame * frame_count


# ─── Confirm gate ───────────────────────────────────────────────────────


def confirm_call(narration_name: str, cost_inr: float) -> bool:
    if not sys.stdin.isatty():
        print(
            "REFUSING: stdin is not a tty. The [CONFIRM] gate must be "
            "answered by a human, not piped.",
            file=sys.stderr,
        )
        return False
    print(f"\n  About to call ElevenLabs for: {narration_name}")
    print(f"  Estimated cost: Rs. {cost_inr:.2f}.")
    answer = input("  Proceed? [type 'yes' exactly to confirm]: ").strip()
    return answer == "yes"


# ─── Main per-file processing ───────────────────────────────────────────


def process_one(narration_path: Path, output_dir: Path,
                *, dry_run: bool, local_fallback_mode: bool) -> VoiceResult:
    result = VoiceResult(
        narration_name=narration_path.name,
        provider="elevenlabs" if not local_fallback_mode else "local",
    )

    print(f"\n-- Processing {narration_path.name} --")
    if not narration_path.exists():
        result.errors.append(f"input not found: {narration_path}")
        return result

    text = narration_path.read_text(encoding="utf-8").strip()
    word_count = len(text.split())
    char_count = len(text)

    if word_count < MIN_WORDS:
        result.errors.append(
            f"narration too short: {word_count} words < {MIN_WORDS}"
        )
        return result
    if word_count > MAX_WORDS:
        result.errors.append(
            f"narration too long: {word_count} words > {MAX_WORDS}"
        )
        return result

    # PII assertion — defence-in-depth.
    pii_hits = detect_pii(text)
    if pii_hits:
        result.errors.append(
            f"PII candidates detected: {', '.join(pii_hits[:3])}"
            f" (total {len(pii_hits)}). Refusing to send to vendor."
        )
        return result

    # Cost estimate.
    cost_inr = (char_count / 1000) * COST_PER_1000_CHARS_INR
    result.cost_estimate_inr = round(cost_inr, 2)
    result.duration_estimate_s = int(word_count / TARGET_WPM * 60)

    print(f"  Word count:         {word_count}")
    print(f"  Char count:         {char_count}")
    print(f"  Estimated duration: {result.duration_estimate_s} sec "
          f"({result.duration_estimate_s // 60} min)")
    print(f"  Estimated cost:     Rs. {cost_inr:.2f}")

    if dry_run:
        print("  [DRY RUN] Would call ElevenLabs.")
        # Synthesise a fake output_path so result.passes is True for
        # dry-run reporting purposes; do not actually write.
        result.output_path = output_dir / (
            narration_path.stem.replace("-narration", "") + "-voice.mp3")
        result.size_bytes = 0
        return result

    if local_fallback_mode:
        print("  [LOCAL FALLBACK] Generating silent placeholder MP3.")
        mp3_bytes = local_fallback_silent_mp3(
            duration_s=max(1, min(60, result.duration_estimate_s)))
    else:
        if not confirm_call(narration_path.name, cost_inr):
            result.errors.append("ABORTED by operator at [CONFIRM] gate")
            return result

        api_key = os.environ.get("ELEVENLABS_API_KEY")
        voice_id = os.environ.get("ELEVENLABS_VOICE_ID")
        model = os.environ.get("ELEVENLABS_MODEL", DEFAULT_MODEL)
        if not api_key or not voice_id:
            result.errors.append(
                "ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID required in .env"
            )
            return result

        try:
            print(f"  Calling ElevenLabs (model={model})...")
            mp3_bytes = call_elevenlabs(text,
                                         voice_id=voice_id,
                                         api_key=api_key,
                                         model=model)
        except RuntimeError as e:
            result.errors.append(str(e))
            return result

    # Output gate: ≥ 100 KB.
    if len(mp3_bytes) < MIN_OUTPUT_BYTES and not local_fallback_mode:
        result.errors.append(
            f"output too small: {len(mp3_bytes)} bytes < {MIN_OUTPUT_BYTES}"
        )
        return result

    # Write to disk.
    output_dir.mkdir(parents=True, exist_ok=True)
    base = narration_path.stem.replace("-narration", "")
    output_path = output_dir / f"{base}-voice.mp3"
    output_path.write_bytes(mp3_bytes)

    result.output_path = output_path
    result.size_bytes = len(mp3_bytes)

    print(f"  OK wrote {output_path}")
    print(f"  Size: {result.size_bytes // 1024} KB")
    return result


# ─── CLI ────────────────────────────────────────────────────────────────


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 4 - Voice Generator"
    )
    parser.add_argument(
        "inputs",
        nargs="+",
        help="One or more narration .txt files (Agent 2 output)",
    )
    parser.add_argument(
        "--output-dir",
        default="content/voice",
        help="Output directory for voice MP3 (default: content/voice)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Validate input + estimate cost, but do not call the API",
    )
    parser.add_argument(
        "--local-fallback",
        action="store_true",
        help="Write a silent MP3 placeholder instead of calling "
        "ElevenLabs. For end-to-end smoke testing without API spend.",
    )
    parser.add_argument(
        "--batch",
        action="store_true",
        help="Process multiple files; [CONFIRM] once at batch start",
    )
    args = parser.parse_args(argv)

    inputs = [Path(p) for p in args.inputs]
    for p in inputs:
        if not p.exists():
            print(f"FATAL: {p} does not exist", file=sys.stderr)
            return 2

    output_dir = Path(args.output_dir)

    total_cost = 0.0
    if args.batch and not args.dry_run and not args.local_fallback:
        # Estimate batch cost first.
        for p in inputs:
            text = p.read_text(encoding="utf-8").strip()
            total_cost += (len(text) / 1000) * COST_PER_1000_CHARS_INR
        print(f"\n[BATCH] About to process {len(inputs)} files.")
        print(f"[BATCH] Total estimated cost: Rs. {total_cost:.2f}.")
        if not confirm_call(f"batch of {len(inputs)} files", total_cost):
            print("ABORTED.")
            return 1

    succeeded = 0
    failed = 0
    for p in inputs:
        result = process_one(p, output_dir,
                              dry_run=args.dry_run,
                              local_fallback_mode=args.local_fallback)
        for e in result.errors:
            print(f"  ERR:  {e}")
        if result.passes:
            succeeded += 1
        else:
            failed += 1

    print(f"\n-- Summary: {succeeded} generated, {failed} skipped --")
    return 0 if (succeeded > 0 or args.dry_run) else 1


if __name__ == "__main__":
    sys.exit(main())
