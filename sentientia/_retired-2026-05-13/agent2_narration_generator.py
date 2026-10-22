"""
SENTIENTIA Agent 2 — Narration Generator
========================================

Reads `content/parsed/*-parsed.json` (output of Agent 1).
Produces `content/narrations/<source-name>-narration.txt`.

Architectural contract (per SUPP-C Section 2):
1. Reads input from disk, writes output to disk, exits.
2. Validation gates on input + output enforce the SENTIENTIA quality
   benchmarks (sentence ≤25 words, ≤2,000 words total, 130 wpm pace).
3. Costs money on every live call. The [CONFIRM] gate prevents
   accidental API spend.

USAGE
-----

Dry run (no API call, just validation + prompt preview):
    python sentientia/agent2_narration_generator.py \\
        content/parsed/posh-2024-parsed.json --dry-run

Live run ([CONFIRM] prompt before each call):
    python sentientia/agent2_narration_generator.py \\
        content/parsed/posh-2024-parsed.json

Batch:
    python sentientia/agent2_narration_generator.py \\
        content/parsed/*.json --batch

ENVIRONMENT
-----------

Reads from `.env` at project root (never committed):
    ANTHROPIC_API_KEY   — Claude API key
    ANTHROPIC_MODEL     — defaults to 'claude-sonnet-4-5-20250929'

STATUS
------

Skeleton with full prompt template + validation gates + disk-artefact
contract. The Anthropic API call is gated by --confirm and not invoked
in this revision (commit 7bcd6a25f / next). The structure is here so
that when the [CONFIRM] gate is granted (per master-doc Section 7.4
and Decision 13.2), the live integration is a small diff.
"""

from __future__ import annotations

import argparse
import io
import json
import os
import re
import sys
from dataclasses import dataclass
from pathlib import Path

# Force UTF-8 stdout/stderr on Windows (cp1252 default crashes on our
# em-dash + check-mark progress glyphs).
if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)

# Anthropic SDK is imported only when actually needed (keeps the
# skeleton runnable without the SDK installed for dry-run mode).

# ─── Quality benchmarks (per SUPP-C Section 7.7) ────────────────────────
MAX_NARRATION_WORDS  = 2000
MIN_NARRATION_WORDS  = 400
MAX_SENTENCE_WORDS   = 25
TARGET_WPM           = 130  # Words per minute; informs length checks
MIN_DURATION_SECONDS = (MIN_NARRATION_WORDS / TARGET_WPM) * 60  # ≈3 min
MAX_DURATION_SECONDS = (MAX_NARRATION_WORDS / TARGET_WPM) * 60  # ≈15 min

# ─── PII heuristic (defensive layer per SUPP-C) ─────────────────────────
# Reject narrations containing what looks like an Indian first name +
# last name pair. Best-effort; the SOP-side PII strip in Agent 1 is the
# primary defence. This catches accidental name leaks that survive.
PII_NAME_PATTERN = re.compile(r"\b[A-Z][a-z]{2,}\s+[A-Z][a-z]{2,}\b")
# Whitelist of business / system terms that match the pattern but
# aren't PII (e.g. proper nouns the platform actually uses).
PII_WHITELIST = {
    "Airpay Academy",
    "Payment Services",
    "Reserve Bank",
    "Anti Money",
    "Money Laundering",
    "Know Your",
    "Data Privacy",
    "Information Technology",
    "Standard Operating",
    "Operating Procedure",
}


@dataclass(frozen=True)
class NarrationResult:
    """The output of one narration-generation run."""

    text: str
    word_count: int
    longest_sentence_words: int
    pii_hits: tuple[str, ...]
    estimated_duration_seconds: int

    @property
    def passes_gates(self) -> bool:
        return (
            MIN_NARRATION_WORDS <= self.word_count <= MAX_NARRATION_WORDS
            and self.longest_sentence_words <= MAX_SENTENCE_WORDS
            and not self.pii_hits
        )

    def gate_failures(self) -> list[str]:
        out: list[str] = []
        if self.word_count < MIN_NARRATION_WORDS:
            out.append(f"too short: {self.word_count} words < {MIN_NARRATION_WORDS}")
        if self.word_count > MAX_NARRATION_WORDS:
            out.append(f"too long: {self.word_count} words > {MAX_NARRATION_WORDS}")
        if self.longest_sentence_words > MAX_SENTENCE_WORDS:
            out.append(
                f"sentence too long: {self.longest_sentence_words} words "
                f"> {MAX_SENTENCE_WORDS}"
            )
        if self.pii_hits:
            out.append(f"PII candidates: {', '.join(self.pii_hits[:5])}")
        return out


# ─── Prompt template ────────────────────────────────────────────────────


NARRATION_PROMPT_TEMPLATE = """You are a learning-content writer for Airpay
Academy. Your task is to convert the following Standard Operating
Procedure into a learner-facing narration script.

CONSTRAINTS (strict, all must hold):

1. Plain text only. No Markdown. No HTML.
2. {min_words}–{max_words} words total.
3. No sentence longer than {max_sent} words. Re-write long sentences
   as two short ones.
4. Reading pace target: {wpm} words per minute. The narration should
   feel unhurried.
5. NO names of specific employees, candidates, or third parties. Use
   generic role descriptions ("the manager", "the candidate") instead.
6. NO references to specific cases, customer accounts, transaction IDs,
   or other identifying details.
7. Indian English spelling and conventions throughout.
8. Address the learner in second person ("you"). Avoid first person.
9. End with a brief summary that previews the assessment questions
   the learner will face.

TOPIC METADATA:
  Title: {title}
  Estimated duration: {est_minutes} minutes
  Audience: Airpay employees

SOURCE MATERIAL:

{source_text}

OUTPUT: respond with ONLY the narration script text. No introduction.
No commentary. No closing remarks. Just the script that the
voice-generation agent will read aloud."""


def build_prompt(parsed: dict) -> str:
    """Build the Claude prompt from a parsed-SOP JSON dict."""
    title = parsed.get("title", "(untitled)")
    source = parsed.get("text", "")
    if not source.strip():
        raise ValueError("parsed JSON has empty 'text' field")

    est_minutes = max(3, min(15, len(source.split()) // TARGET_WPM))

    return NARRATION_PROMPT_TEMPLATE.format(
        min_words=MIN_NARRATION_WORDS,
        max_words=MAX_NARRATION_WORDS,
        max_sent=MAX_SENTENCE_WORDS,
        wpm=TARGET_WPM,
        title=title,
        est_minutes=est_minutes,
        source_text=source,
    )


# ─── Validation gates ───────────────────────────────────────────────────


def split_sentences(text: str) -> list[str]:
    """Lightweight sentence splitter.

    Splits on '.', '!', '?' followed by whitespace. Not perfect for edge
    cases but sufficient for measuring sentence length distribution.
    """
    parts = re.split(r"(?<=[.!?])\s+", text.strip())
    return [p for p in parts if p.strip()]


def detect_pii(text: str) -> tuple[str, ...]:
    """Return any name-like sequences not in the whitelist."""
    matches = PII_NAME_PATTERN.findall(text)
    hits: list[str] = []
    for m in matches:
        if m in PII_WHITELIST:
            continue
        hits.append(m)
    return tuple(hits)


def evaluate_narration(text: str) -> NarrationResult:
    """Run all gates and return a NarrationResult."""
    words = text.split()
    word_count = len(words)
    sentences = split_sentences(text)
    longest = max(
        (len(s.split()) for s in sentences),
        default=0,
    )
    duration = int(word_count / TARGET_WPM * 60)
    return NarrationResult(
        text=text,
        word_count=word_count,
        longest_sentence_words=longest,
        pii_hits=detect_pii(text),
        estimated_duration_seconds=duration,
    )


# ─── Anthropic API call (live, gated by [CONFIRM]) ─────────────────────


@dataclass
class ClaudeCallResult:
    """Result of one live Claude call. Includes the response text plus
    token-usage metadata for budget tracking."""
    text: str
    input_tokens: int
    output_tokens: int
    cache_creation_input_tokens: int = 0
    cache_read_input_tokens: int = 0
    model: str = ""
    stop_reason: str = ""

    @property
    def estimated_cost_inr(self) -> float:
        """Rough INR cost. Claude Sonnet input ~Rs 0.25 per 1k input
        tokens; output ~Rs 1.25 per 1k output tokens (Mar 2026 rate).
        Cache reads are 10x cheaper than fresh input."""
        fresh_input = self.input_tokens - self.cache_read_input_tokens
        cost = (
            fresh_input * 0.00025
            + self.cache_creation_input_tokens * 0.0003125  # 25% premium
            + self.cache_read_input_tokens * 0.000025       # 10x discount
            + self.output_tokens * 0.00125
        )
        # 83 INR per USD reference rate (per master doc cover page).
        return round(cost * 83, 2)


def call_claude(prompt: str, model: str,
                 max_tokens: int = 3500,
                 max_retries: int = 3,
                 backoff_ms: tuple[int, ...] = (500, 1500)) -> ClaudeCallResult:
    """
    Live call to Claude. NOT invoked unless --confirm and the user
    has answered 'yes' at the interactive prompt.

    Returns a ClaudeCallResult with the narration text + token usage
    metadata for budget tracking. Raises on persistent API errors after
    exhausting retries.

    Phase 9.6 hardening:
    - Exponential-backoff retry on 5xx + RateLimit + Overloaded errors
      (mirrors the AWS Rekognition retry pattern in Agent 4 area).
    - Captures input_tokens + output_tokens from `message.usage` for
      structured logging and the actual-vs-estimated cost reconciliation
      that the quarterly vendor-budget review needs.
    """
    # Imported lazily so dry-run mode doesn't require the SDK.
    import anthropic  # type: ignore

    client = anthropic.Anthropic()  # reads ANTHROPIC_API_KEY from env

    last_error: Exception | None = None
    for attempt in range(1, max_retries + 1):
        try:
            message = client.messages.create(
                model=model,
                max_tokens=max_tokens,
                messages=[{"role": "user", "content": prompt}],
            )
            parts = message.content
            if not parts:
                raise RuntimeError("Empty response from Claude")
            block = parts[0]
            if not hasattr(block, "text"):
                raise RuntimeError(
                    f"Unexpected response block type: {type(block).__name__}"
                )

            usage = getattr(message, "usage", None)
            return ClaudeCallResult(
                text=block.text,  # type: ignore[union-attr]
                input_tokens=getattr(usage, "input_tokens", 0) if usage else 0,
                output_tokens=getattr(usage, "output_tokens", 0) if usage else 0,
                cache_creation_input_tokens=getattr(usage,
                    "cache_creation_input_tokens", 0) if usage else 0,
                cache_read_input_tokens=getattr(usage,
                    "cache_read_input_tokens", 0) if usage else 0,
                model=model,
                stop_reason=getattr(message, "stop_reason", "") or "",
            )

        except anthropic.RateLimitError as e:        # 429
            last_error = e
        except anthropic.APIStatusError as e:        # 5xx
            if 500 <= e.status_code < 600:
                last_error = e
            else:
                raise
        except anthropic.APIConnectionError as e:    # network blip
            last_error = e

        # Retryable error path.
        if attempt < max_retries:
            sleep_ms = backoff_ms[min(attempt - 1, len(backoff_ms) - 1)]
            print(f"    (retry {attempt}/{max_retries - 1} after {sleep_ms}ms — "
                  f"{type(last_error).__name__})")
            import time
            time.sleep(sleep_ms / 1000)
            continue

    raise RuntimeError(
        f"Claude call failed after {max_retries} attempts: {last_error}"
    )


def confirm_call(parsed_filename: str) -> bool:
    """Interactive [CONFIRM] gate. Refuses 'yes' from stdin redirects
    by checking that stdin is a tty (defensive against pipelines)."""
    if not sys.stdin.isatty():
        print(
            "REFUSING: stdin is not a tty. The [CONFIRM] gate must be answered "
            "by a human, not piped from another process.",
            file=sys.stderr,
        )
        return False
    print(f"\n  About to call Anthropic Claude for: {parsed_filename}")
    print(f"  Estimated cost: ~₹15-20 per generated narration.")
    answer = input("  Proceed? [type 'yes' exactly to confirm]: ").strip()
    return answer == "yes"


# ─── Main entry point ───────────────────────────────────────────────────


def process_one(parsed_path: Path, output_dir: Path, dry_run: bool) -> bool:
    """Process a single parsed-SOP JSON file.

    Returns True if the output narration was written, False otherwise.
    """
    print(f"\n── Processing {parsed_path.name} ──")
    parsed = json.loads(parsed_path.read_text(encoding="utf-8"))

    title = parsed.get("title", parsed_path.stem)
    source_words = len(parsed.get("text", "").split())
    print(f"  Title: {title}")
    print(f"  Source word count: {source_words}")

    if source_words < 100:
        print(f"  SKIP: source too short ({source_words} words < 100)")
        return False
    if source_words > 5000:
        print(f"  SKIP: source too long ({source_words} words > 5,000)")
        return False

    prompt = build_prompt(parsed)
    print(f"  Prompt built. Total length: {len(prompt):,} chars.")

    if dry_run:
        print("  [DRY RUN] Would call Claude with the prompt above.")
        print("  [DRY RUN] No API call. Exiting without writing output.")
        return False

    if not confirm_call(parsed_path.name):
        print("  ABORTED by operator at [CONFIRM] gate.")
        return False

    model = os.environ.get("ANTHROPIC_MODEL", "claude-sonnet-4-5-20250929")
    print(f"  Calling Claude (model={model})...")
    claude_result = call_claude(prompt, model=model)
    narration = claude_result.text

    # Log actual token usage + cost (vs. estimate).
    print(f"  Tokens: input={claude_result.input_tokens:,} "
          f"output={claude_result.output_tokens:,} "
          f"cache_read={claude_result.cache_read_input_tokens:,}")
    print(f"  Actual cost: Rs.{claude_result.estimated_cost_inr:.2f}")
    print(f"  Stop reason: {claude_result.stop_reason}")

    result = evaluate_narration(narration)
    print(f"  Word count: {result.word_count}")
    print(f"  Longest sentence: {result.longest_sentence_words} words")
    print(f"  Estimated duration: {result.estimated_duration_seconds // 60} min")
    print(f"  PII candidates: {len(result.pii_hits)}")

    if not result.passes_gates:
        print("  GATE FAILURE:")
        for fail in result.gate_failures():
            print(f"    - {fail}")
        print("  Output rejected. Source narration NOT written to disk.")
        return False

    output_path = output_dir / f"{parsed_path.stem}-narration.txt"
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(narration, encoding="utf-8")
    print(f"  ✓ Narration written to {output_path}")
    return True


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 2 — Narration Generator"
    )
    parser.add_argument(
        "inputs",
        nargs="+",
        help="One or more parsed-SOP JSON files (output of Agent 1)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Validate inputs and build prompts, but do not call the API",
    )
    parser.add_argument(
        "--batch",
        action="store_true",
        help="Process multiple files; ask [CONFIRM] once at the start of the batch",
    )
    parser.add_argument(
        "--output-dir",
        default="content/narrations",
        help="Directory to write narrations to (default: content/narrations)",
    )
    args = parser.parse_args(argv)

    inputs = [Path(p) for p in args.inputs]
    for p in inputs:
        if not p.exists():
            print(f"FATAL: {p} does not exist", file=sys.stderr)
            return 2
        if p.suffix != ".json":
            print(f"FATAL: {p} is not a .json file", file=sys.stderr)
            return 2

    output_dir = Path(args.output_dir)

    if args.batch and not args.dry_run:
        print(f"\n[BATCH] About to process {len(inputs)} files.")
        print(f"[BATCH] Estimated total cost: ₹{15 * len(inputs)}–{20 * len(inputs)}.")
        if not confirm_call(f"batch of {len(inputs)} files"):
            print("ABORTED.")
            return 1
        # Subsequent per-file calls won't re-prompt in batch mode.
        # (Implementation detail: pass a 'batch_confirmed' flag through;
        # left as a TODO for the live-integration commit.)

    written = 0
    skipped = 0
    for p in inputs:
        ok = process_one(p, output_dir, args.dry_run)
        if ok:
            written += 1
        else:
            skipped += 1

    print(f"\n── Summary: {written} written, {skipped} skipped ──")
    return 0 if written or args.dry_run else 1


if __name__ == "__main__":
    sys.exit(main())
