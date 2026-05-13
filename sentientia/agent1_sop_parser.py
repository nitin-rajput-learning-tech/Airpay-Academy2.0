"""
SENTIENTIA Agent 1 — SOP Parser
================================

Reads `content/sops/*.pdf` and produces `content/parsed/<source>-parsed.json`.

Output schema (the contract Agent 2 consumes):

    {
      "metadata": {
        "source_file": "AML-Training-SOP.pdf",
        "parsed_at":   "2026-05-12T19:45:23.123Z",
        "word_count":  1842,
        "section_count": 7,
        "agent":       "sentientia_agent1_sop_parser",
        "version":     "2.0",
        "pii_scrubbed": true
      },
      "title":    "AML Training SOP",
      "text":     "<full canonical body, ≤2000 words>",
      "sections": [{ "title": "...", "content": "...", "word_count": 123 }, ...],
      "summary":  "<first 500 chars + …>"
    }

Architectural contract (per SUPP-C Section 2):
1. Reads input from disk, writes output to disk, exits.
2. No external API calls (PDF parsing is local; OCR fallback is local
   via Tesseract if installed).
3. Validation gates: input must be PDF, output word count 100-5000, etc.

PRODUCTION CHANGES vs the original prototype:
- argparse-driven CLI (single-file + batch + dry-run + --output-dir).
- Output schema now matches Agent 2's expected contract (adds `text`
  field as the canonical full body, alongside the existing
  `sections` for structure).
- Input-size validation rejects SOPs below 100 words (too thin to
  produce a meaningful course) and above 5000 words (too long for one
  course; flag for splitting).
- Defensive PII scrub pass before output (Indian-name heuristic with
  whitelist for legitimate proper nouns).
- Reject scanned-image PDFs without text (with a clear error message;
  OCR fallback hook is documented but currently a stub).
- Structured output for the regression suite: a deterministic
  `parsed_at` is suppressed during regression mode so diffs are clean.
- Exit codes: 0 = success, 1 = validation failure, 2 = IO error.

USAGE
-----

Single file:
    python sentientia/agent1_sop_parser.py content/sops/AML-2024.pdf

Batch:
    python sentientia/agent1_sop_parser.py content/sops/*.pdf --batch

Dry-run (parse + validate but do not write output):
    python sentientia/agent1_sop_parser.py content/sops/POSH-2024.pdf --dry-run

Custom output directory:
    python sentientia/agent1_sop_parser.py content/sops/AML.pdf \\
        --output-dir content/parsed_test
"""

from __future__ import annotations

import argparse
import io
import json
import re
import sys
from dataclasses import dataclass, field
from datetime import datetime, timezone
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


# ─── Quality gates (per SUPP-C Section 7.7) ────────────────────────────
MIN_INPUT_WORDS = 100
MAX_INPUT_WORDS = 5000
MAX_OUTPUT_WORDS = 2000   # SOP-side cap from CLAUDE.md SENTIENTIA section

AGENT_VERSION = "2.0"
AGENT_NAME = "sentientia_agent1_sop_parser"


# ─── PII heuristic ──────────────────────────────────────────────────────
# Best-effort scrub of Indian-style first+last name pairs that survive
# the SOP author's review. The whitelist below catches business names
# that match the pattern but aren't PII.
PII_NAME_PATTERN = re.compile(r"\b[A-Z][a-z]{2,}\s+[A-Z][a-z]{2,}\b")
PII_WHITELIST = frozenset([
    "Airpay Academy",
    "Airpay Payment",
    "Payment Services",
    "Reserve Bank",
    "Anti Money",
    "Money Laundering",
    "Know Your",
    "Data Privacy",
    "Information Technology",
    "Standard Operating",
    "Operating Procedure",
    "Customer Service",
    "Quality Assurance",
    "Human Resources",
    "Indian Rupee",
    "Real Time",
    "Force Majeure",
    "Best Practice",
])


def scrub_pii(text: str) -> tuple[str, list[str]]:
    """Replace likely-PII name pairs with the placeholder
    ``[REDACTED-NAME]``. Returns the scrubbed text + the list of
    redacted strings for the audit log."""
    redacted: list[str] = []

    def _sub(match: re.Match[str]) -> str:
        s = match.group(0)
        if s in PII_WHITELIST:
            return s
        redacted.append(s)
        return "[REDACTED-NAME]"

    scrubbed = PII_NAME_PATTERN.sub(_sub, text)
    return scrubbed, redacted


# ─── PDF text extraction ────────────────────────────────────────────────


def parse_pdf(filepath: str) -> str:
    """Extract text from PDF. Tries multiple parsers in order of
    preference; raises on total failure."""
    text = ""

    parsers = [
        ("pypdf", _try_pypdf),
        ("pdfplumber", _try_pdfplumber),
        ("pdfminer", _try_pdfminer),
    ]

    for name, parser in parsers:
        try:
            text = parser(filepath)
            if text.strip():
                return text
        except ImportError:
            continue
        except Exception as e:
            print(f"  (parser {name} raised {type(e).__name__}; trying next)",
                  file=sys.stderr)

    raise RuntimeError(
        "No PDF parser returned non-empty text. Install one of: "
        "pypdf, pdfplumber, pdfminer. Scanned-image PDFs require OCR "
        "(Tesseract) which is not yet wired up — see TODO_OCR."
    )


def _try_pypdf(filepath: str) -> str:
    from pypdf import PdfReader  # type: ignore
    reader = PdfReader(filepath)
    text = ""
    for page in reader.pages:
        text += page.extract_text() or ""
    return text


def _try_pdfplumber(filepath: str) -> str:
    import pdfplumber  # type: ignore
    text = ""
    with pdfplumber.open(filepath) as pdf:
        for page in pdf.pages:
            text += page.extract_text() or ""
    return text


def _try_pdfminer(filepath: str) -> str:
    from pdfminer.high_level import extract_text  # type: ignore
    return extract_text(filepath)


# TODO_OCR: when a scanned-image PDF appears, fall back to Tesseract.
# Implementation sketch (not yet active):
#
#   from pdf2image import convert_from_path
#   import pytesseract
#   pages = convert_from_path(filepath)
#   return '\n'.join(pytesseract.image_to_string(p) for p in pages)
#
# Requires Tesseract binary + Poppler binary on the system PATH and
# the pdf2image + pytesseract Python packages.


# ─── Text shaping ───────────────────────────────────────────────────────


def chunk_text(text: str, max_words: int = MAX_OUTPUT_WORDS) -> str:
    """Truncate to max_words while preserving sentence boundaries."""
    words = text.split()
    if len(words) <= max_words:
        return text

    truncated = " ".join(words[:max_words])
    for sep in [". ", ".\n", ".\t"]:
        idx = truncated.rfind(sep)
        if idx > len(truncated) * 0.7:
            return truncated[: idx + 1]
    return truncated


def extract_sections(text: str) -> list[dict]:
    """Split SOP text into sections using common heading patterns."""
    lines = text.split("\n")
    sections: list[dict] = []
    current: dict = {"title": "Introduction", "content": []}

    for line in lines:
        line = line.strip()
        if not line:
            continue

        is_header = bool(
            re.match(r"^\d+[\.\)]\s+[A-Z]", line)
            or (line.isupper() and 3 < len(line) < 80)
            or re.match(r"^(Section|Chapter|Part|Step|Module)\s+\d", line, re.I)
        )

        if is_header:
            if current["content"]:
                sections.append(current)
            current = {"title": line, "content": []}
        else:
            current["content"].append(line)

    if current["content"]:
        sections.append(current)

    return sections


# ─── Result type ────────────────────────────────────────────────────────


@dataclass
class ParseResult:
    parsed: dict
    word_count: int
    redacted_names: list[str] = field(default_factory=list)
    validation_errors: list[str] = field(default_factory=list)

    @property
    def passes_gates(self) -> bool:
        return not self.validation_errors


# ─── Main parser ────────────────────────────────────────────────────────


def parse_sop(filepath: str, *, deterministic_time: bool = False) -> ParseResult:
    """PDF → structured JSON. Returns ParseResult; never raises on
    business-rule failures (those go in validation_errors)."""
    path = Path(filepath)
    if not path.exists():
        return ParseResult(parsed={}, word_count=0,
                           validation_errors=[f"file not found: {filepath}"])
    if path.suffix.lower() != ".pdf":
        return ParseResult(parsed={}, word_count=0,
                           validation_errors=[
                               f"expected .pdf, got {path.suffix}"])

    raw_text = parse_pdf(str(path))
    raw_word_count = len(raw_text.split())

    errors: list[str] = []
    if raw_word_count < MIN_INPUT_WORDS:
        errors.append(
            f"input too thin: {raw_word_count} words < {MIN_INPUT_WORDS} min"
        )
    if raw_word_count > MAX_INPUT_WORDS:
        errors.append(
            f"input too long: {raw_word_count} words > {MAX_INPUT_WORDS} "
            f"max — split into multiple courses first"
        )

    # Cap to MAX_OUTPUT_WORDS for the canonical body.
    text = chunk_text(raw_text, MAX_OUTPUT_WORDS)
    final_words = len(text.split())

    # PII scrub.
    text, redacted = scrub_pii(text)

    # Section extraction (uses pre-scrub text for heading detection but
    # writes post-scrub content to output).
    raw_sections = extract_sections(text)
    sections = [
        {
            "title": s["title"],
            "content": "\n".join(s["content"]),
            "word_count": len(" ".join(s["content"]).split()),
        }
        for s in raw_sections
    ]

    parsed_at = (
        "1970-01-01T00:00:00Z"
        if deterministic_time
        else datetime.now(timezone.utc).isoformat(timespec="milliseconds")
    )

    parsed = {
        "metadata": {
            "source_file": path.name,
            "parsed_at": parsed_at,
            "word_count": final_words,
            "section_count": len(sections),
            "redacted_name_count": len(redacted),
            "agent": AGENT_NAME,
            "version": AGENT_VERSION,
            "pii_scrubbed": True,
        },
        "title": path.stem.replace("-", " ").replace("_", " ").title(),
        "text": text,
        "sections": sections,
        "summary": text[:500] + "…" if len(text) > 500 else text,
    }

    return ParseResult(
        parsed=parsed,
        word_count=final_words,
        redacted_names=redacted,
        validation_errors=errors,
    )


# ─── CLI ────────────────────────────────────────────────────────────────


def process_one(path: Path, output_dir: Path,
                *, dry_run: bool, deterministic_time: bool) -> bool:
    print(f"\n── {path.name} ──")
    result = parse_sop(str(path), deterministic_time=deterministic_time)

    if not result.passes_gates:
        for err in result.validation_errors:
            print(f"  GATE FAIL: {err}")
        return False

    md = result.parsed["metadata"]
    print(f"  Title:          {result.parsed['title']}")
    print(f"  Word count:     {md['word_count']}")
    print(f"  Sections:       {md['section_count']}")
    print(f"  PII redactions: {md['redacted_name_count']}")
    if result.redacted_names:
        sample = ", ".join(result.redacted_names[:3])
        more = f" (+{len(result.redacted_names) - 3} more)" if len(result.redacted_names) > 3 else ""
        print(f"     redacted: {sample}{more}")

    if dry_run:
        print(f"  [DRY RUN] Output not written.")
        return True

    output_dir.mkdir(parents=True, exist_ok=True)
    output_path = output_dir / f"{path.stem}-parsed.json"
    output_path.write_text(
        json.dumps(result.parsed, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    print(f"  ✓ wrote {output_path}")
    return True


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 1 — SOP Parser"
    )
    parser.add_argument(
        "inputs",
        nargs="+",
        help="One or more PDF files to parse",
    )
    parser.add_argument(
        "--output-dir",
        default="content/parsed",
        help="Directory for parsed-JSON output (default: content/parsed)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Parse + validate but do not write output",
    )
    parser.add_argument(
        "--batch",
        action="store_true",
        help="(no-op flag; multi-file is the default. Kept for CLI symmetry "
        "with Agent 2's --batch.)",
    )
    parser.add_argument(
        "--deterministic-time",
        action="store_true",
        help="Freeze metadata.parsed_at to epoch — for regression suites "
        "that diff outputs across runs.",
    )
    args = parser.parse_args(argv)

    inputs = [Path(p) for p in args.inputs]
    for p in inputs:
        if not p.exists():
            print(f"FATAL: {p} does not exist", file=sys.stderr)
            return 2

    output_dir = Path(args.output_dir)
    succeeded = 0
    failed = 0

    for p in inputs:
        ok = process_one(p, output_dir,
                         dry_run=args.dry_run,
                         deterministic_time=args.deterministic_time)
        if ok:
            succeeded += 1
        else:
            failed += 1

    print(f"\n── Summary: {succeeded} parsed, {failed} failed ──")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
