"""
SENTIENTIA quality regression runner.

Walks each reference under `sentientia/references/` and verifies that
the current agent versions still produce output within the documented
similarity thresholds.

Per `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` Section 7.

STATUS
------

Skeleton. The actual similarity-check logic (KS test on sentence
length distribution, term-recall measurement) is implemented; the
agent-invocation glue is stubbed because Agents 2 and 3 do not yet
have live API calls (per SUPP-C build status).

USAGE
-----

    python sentientia/run_regression.py
    python sentientia/run_regression.py --reference posh-2024
    python sentientia/run_regression.py --skip-live-api

When --skip-live-api is passed, the suite runs against the most
recently captured agent output in the reference directory rather than
re-running the agent. Useful for "did my agent code change break
the parser?" checks without burning API spend.
"""

from __future__ import annotations

import argparse
import json
import math
import re
import sys
from dataclasses import dataclass
from pathlib import Path


REFERENCE_DIR = Path(__file__).parent / "references"


# ─── Similarity metrics ─────────────────────────────────────────────────


def word_count(text: str) -> int:
    return len(text.split())


def split_sentences(text: str) -> list[str]:
    parts = re.split(r"(?<=[.!?])\s+", text.strip())
    return [p for p in parts if p.strip()]


def sentence_length_distribution(text: str) -> list[int]:
    return sorted(len(s.split()) for s in split_sentences(text))


def ks_statistic(a: list[int], b: list[int]) -> float:
    """Two-sample Kolmogorov-Smirnov statistic (no scipy dependency).

    Returns the maximum absolute difference between the empirical
    cumulative distribution functions. Lower is more similar.
    """
    if not a or not b:
        return 1.0
    combined = sorted(set(a + b))
    max_diff = 0.0
    for value in combined:
        cdf_a = sum(1 for x in a if x <= value) / len(a)
        cdf_b = sum(1 for x in b if x <= value) / len(b)
        diff = abs(cdf_a - cdf_b)
        if diff > max_diff:
            max_diff = diff
    return max_diff


def vocabulary_recall(
    candidate: str, golden: str, min_term_length: int = 5
) -> float:
    """How much of the golden's distinctive vocabulary appears in
    the candidate? Returns 0.0–1.0.

    "Distinctive" means terms longer than `min_term_length` characters
    that aren't common English filler. Good-enough proxy for
    domain-term preservation.
    """
    common = {
        "would", "should", "could", "their", "there", "these", "those",
        "which", "where", "whose", "while", "about", "after", "before",
        "again", "another", "across", "always", "between", "course",
    }
    golden_terms = {
        w.lower().strip(".,!?;:()[]\"'")
        for w in golden.split()
        if len(w) > min_term_length
    } - common
    candidate_terms = {
        w.lower().strip(".,!?;:()[]\"'")
        for w in candidate.split()
        if len(w) > min_term_length
    } - common
    if not golden_terms:
        return 1.0
    overlap = golden_terms & candidate_terms
    return len(overlap) / len(golden_terms)


# ─── Regression check ────────────────────────────────────────────────────


@dataclass
class CheckResult:
    name: str
    passed: bool
    detail: str


def check_narration(
    candidate: str, golden: str, *, word_tolerance: float = 0.10
) -> list[CheckResult]:
    """Run all narration-output checks. Returns one CheckResult per
    measurement."""
    results: list[CheckResult] = []

    gw, cw = word_count(golden), word_count(candidate)
    word_delta = abs(cw - gw) / max(gw, 1)
    results.append(CheckResult(
        name="word_count",
        passed=word_delta <= word_tolerance,
        detail=f"golden={gw} candidate={cw} delta={word_delta:.1%}",
    ))

    g_dist = sentence_length_distribution(golden)
    c_dist = sentence_length_distribution(candidate)
    ks = ks_statistic(g_dist, c_dist)
    results.append(CheckResult(
        name="sentence_distribution_ks",
        passed=ks < 0.30,  # generous; KS > 0.30 means materially different
        detail=f"ks={ks:.3f}",
    ))

    recall = vocabulary_recall(candidate, golden)
    results.append(CheckResult(
        name="vocabulary_recall",
        passed=recall >= 0.60,  # at least 60% of distinctive terms preserved
        detail=f"recall={recall:.1%}",
    ))

    # PII check — if golden has zero PII candidates, candidate should too.
    PII = re.compile(r"\b[A-Z][a-z]{2,}\s+[A-Z][a-z]{2,}\b")
    g_pii = set(PII.findall(golden))
    c_pii = set(PII.findall(candidate)) - g_pii  # only NEW hits matter
    results.append(CheckResult(
        name="no_new_pii",
        passed=len(c_pii) == 0,
        detail=f"new_candidates={len(c_pii)}",
    ))

    return results


# ─── Reference discovery ────────────────────────────────────────────────


@dataclass
class Reference:
    name: str
    directory: Path
    golden_narration: Path | None
    golden_slides: Path | None
    golden_parsed: Path | None


def discover_references(only: str | None = None) -> list[Reference]:
    """Scan `sentientia/references/` for subdirectories containing
    golden artefacts."""
    refs: list[Reference] = []
    if not REFERENCE_DIR.exists():
        return refs
    for child in sorted(REFERENCE_DIR.iterdir()):
        if not child.is_dir():
            continue
        if only and child.name != only:
            continue
        # Find the most recent golden per kind.
        narration = _latest(child.glob("golden-narration-*.txt"))
        slides = _latest(child.glob("golden-slides-*.json"))
        parsed = _latest(child.glob("golden-parsed-*.json"))
        refs.append(Reference(
            name=child.name,
            directory=child,
            golden_narration=narration,
            golden_slides=slides,
            golden_parsed=parsed,
        ))
    return refs


def _latest(files) -> Path | None:
    paths = sorted(files)
    return paths[-1] if paths else None


# ─── Main entry point ───────────────────────────────────────────────────


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA quality regression suite"
    )
    parser.add_argument(
        "--reference",
        help="Run only this named reference (default: all)",
    )
    parser.add_argument(
        "--skip-live-api",
        action="store_true",
        help="Don't re-run agents; use the most recent captured output.",
    )
    args = parser.parse_args(argv)

    refs = discover_references(only=args.reference)
    if not refs:
        print("No references found under sentientia/references/.")
        print("Hint: each reference is a subdirectory containing golden-*.{txt,json}.")
        return 0

    all_passed = True
    print(f"Running regression suite against {len(refs)} reference(s).\n")

    for ref in refs:
        print(f"── Reference: {ref.name} ──")
        if not ref.golden_narration:
            print(f"  SKIP: no golden narration found for {ref.name}")
            continue
        golden_text = ref.golden_narration.read_text(encoding="utf-8")

        # In a live run, we would invoke agent2_narration_generator here
        # against the matched parsed-SOP and capture its output as
        # "candidate". For the skeleton, use the most recent candidate
        # captured to disk (or fall back to the golden itself, which
        # always passes — a sanity check).
        candidate_path = _latest(ref.directory.glob("candidate-narration-*.txt"))
        if candidate_path:
            candidate_text = candidate_path.read_text(encoding="utf-8")
            print(f"  Using captured candidate: {candidate_path.name}")
        else:
            print("  No candidate captured. Using golden as candidate (sanity check).")
            candidate_text = golden_text

        results = check_narration(candidate_text, golden_text)
        for r in results:
            mark = "✓" if r.passed else "✗"
            print(f"  {mark} {r.name:30s} {r.detail}")
            if not r.passed:
                all_passed = False
        print()

    if all_passed:
        print("All regression checks passed.")
        return 0
    else:
        print("One or more regression checks FAILED.")
        return 1


if __name__ == "__main__":
    sys.exit(main())
