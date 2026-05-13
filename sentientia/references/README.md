# SENTIENTIA Quality Regression References

Three reference courses that the SENTIENTIA pipeline is benchmarked
against on every release. The references catch drift when Claude,
ElevenLabs or Gamma silently change behaviour upstream.

Per `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` Section 7.

Each reference includes:

- A **golden parsed JSON** (what Agent 1 should produce from the SOP).
- A **golden narration** (what Agent 2 should produce — captured from
  the first known-good run, hand-reviewed).
- A **golden slides JSON** (what Agent 3 should produce).
- The reference SOP is held in `content/sops/` (encrypted at rest if
  the SOP itself is internal).

The regression suite compares each agent's output against the golden
output using a similarity threshold rather than exact match (because
Claude's output is naturally non-deterministic).

## Reference 1 — POSH compliance refresher

| Field | Value |
|---|---|
| Source SOP | `content/sops/posh-2024.pdf` (placeholder) |
| Audience | All Airpay employees, annual cycle |
| Difficulty | Regulatory, low-tolerance-for-inaccuracy |
| Target duration | 8 minutes spoken (≈1040 words at 130 wpm) |
| Why this is a reference | Highest-stakes compliance content. If SENTIENTIA degrades here, we cannot ship from the pipeline. |

## Reference 2 — Customer support playbook

| Field | Value |
|---|---|
| Source SOP | `content/sops/cs-playbook-v3.pdf` (placeholder) |
| Audience | Customer-facing teams |
| Difficulty | Conversational tone, examples-heavy |
| Target duration | 7 minutes spoken (≈910 words at 130 wpm) |
| Why this is a reference | Tests the pipeline's ability to retain the warmth and example-driven style that learners actually want. |

## Reference 3 — AML fundamentals

| Field | Value |
|---|---|
| Source SOP | `content/sops/aml-fundamentals.pdf` (placeholder) |
| Audience | All employees with payment-flow exposure |
| Difficulty | Technical content with defined terminology |
| Target duration | 10 minutes spoken (≈1300 words at 130 wpm) |
| Why this is a reference | Tests technical-vocabulary preservation. AML terminology is specific; a paraphrasing that drops "structuring" or "smurfing" loses the training value. |

## Validation thresholds

Each agent's output passes the regression if:

- Word count is within ±10% of the golden.
- Sentence-length distribution matches the golden within Kolmogorov-Smirnov
  test p > 0.05.
- Vocabulary recall (does the new output contain ≥ 80% of the golden's
  domain-specific terms?) is ≥ 80%.
- No new PII candidates detected.
- For slides: slide count within ±20%, bullet count per slide within
  ±2.

## How the regression suite runs

```
# After each agent change OR after each vendor-API change:
python sentientia/run_regression.py
```

This script (not yet built — Phase 9.5) walks each reference, runs
the relevant agent, captures the output to a timestamped diff, and
prints PASS/FAIL per metric.

## Golden output capture

The first time the SENTIENTIA pipeline produces approved output for a
reference, that output is "frozen" as the golden by copying the file
into `references/<name>/golden-<artefact>-YYYY-MM-DD.{txt,json}`.

Goldens are versioned. When a vendor API legitimately improves and we
agree the new output is better, we re-freeze the golden with a new
date suffix and document the rationale in this directory.

## Anti-goldens (negative tests)

Not yet implemented but planned: a set of **anti-references** — inputs
that the pipeline should explicitly refuse. Examples:

- An SOP containing real customer names → Agent 1 must scrub before
  Agent 2 sees it.
- An SOP under 100 words → Agent 1 must reject (too thin to produce
  meaningful learning content).
- An SOP over 5,000 words → Agent 1 must reject (too long for one
  course; needs splitting first).

Anti-goldens catch regressions where guard-rails are accidentally
removed.
