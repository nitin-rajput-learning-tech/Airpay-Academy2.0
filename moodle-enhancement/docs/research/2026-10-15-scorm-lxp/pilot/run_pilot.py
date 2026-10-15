"""Sentientia pilot harness: canonical course JSON -> SCORM 1.2 / SCORM 2004 4th Ed. / cmi5.

    python -I run_pilot.py --selftest
    python -I run_pilot.py --mode mock --preview --out out/run-mock-001
    python -I run_pilot.py --mode mock --preview --package-mode per_language --langs en,hi,ar --out out/run-mock-002
    python -I run_pilot.py --estimate

MOCK mode (the default) never touches the network. LIVE mode refuses to start unless every gate in
LIVE-RUN-CHECKLIST.md is open (ANTHROPIC_API_KEY, PILOT_SPEND_CAP_INR, --confirm, a clean PII scan, an estimate
under the cap) and then asks for a typed CONFIRM before every single request. The live path has never been run.
Standard library only; Node.js is optional (JavaScript syntax check and the headless runtime smoke).
"""
from __future__ import annotations

import argparse
import os
import subprocess
import sys
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))  # python -I does not add the script directory; this tree is trusted code

from lib import pipeline, translate  # noqa: E402
from lib.common import HarnessError, configure_utf8_stdio, parse_csv_option, read_json  # noqa: E402
from lib.packager import STANDARDS  # noqa: E402
from lib.report import write_reports  # noqa: E402

DEFAULT_COURSE = HERE / "fixtures" / "course-upi-dispute.json"
DEFAULT_SCHEMA = HERE / "schema" / "sentientia-course.schema.json"
DEFAULT_SOURCE = HERE / "fixtures" / "sample-sop-upi-dispute.md"


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="run_pilot.py", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--mode", choices=("mock", "live"), default="mock", help="mock (default, offline) or live (paid model calls; gated)")
    p.add_argument("--course", type=Path, default=DEFAULT_COURSE, help="canonical course JSON")
    p.add_argument("--schema", type=Path, default=DEFAULT_SCHEMA)
    p.add_argument("--source", type=Path, default=DEFAULT_SOURCE, help="source SOP file for the PII gate and the hash (use --no-source to skip)")
    p.add_argument("--no-source", action="store_true")
    p.add_argument("--out", type=Path, default=None, help="output directory (must be new; default out/<run id>)")
    p.add_argument("--preview", action="store_true", help="carry every requested language, labelled with its review state (non-learner build)")
    p.add_argument("--package-mode", choices=("multilingual", "per_language"), default="multilingual")
    p.add_argument("--langs", default="", help="comma-separated language tags (default: all declared)")
    p.add_argument("--standards", default=",".join(STANDARDS), help="comma-separated: scorm12,scorm2004,cmi5")
    p.add_argument("--tts", choices=("mock", "local-cmd"), default="mock")
    p.add_argument("--tts-cmd", default="", help="command template with {text_file} {out_mp3} {lang} {voice} for --tts local-cmd")
    p.add_argument("--confirm", action="store_true", help="live mode only: acknowledge [CONFIRM] for the run (each request still prompts)")
    p.add_argument("--actor", default="pilot-harness", help="actor recorded in the audit log")
    p.add_argument("--no-smoke", action="store_true", help="skip the Node.js headless runtime smoke")
    p.add_argument("--estimate", action="store_true", help="print the pre-flight translation estimate and exit (no network, no files)")
    p.add_argument("--selftest", action="store_true", help="run the unit tests and one end-to-end mock build, then exit")
    return p


def cmd_estimate(args: argparse.Namespace) -> int:
    doc = read_json(args.course)
    schema = read_json(args.schema)
    from lib.schema_lite import validate_document

    res = validate_document(doc, schema, preview=True)
    base = doc["course"]["base_lang"]
    langs = parse_csv_option(args.langs) or doc["course"]["langs"]
    targets = [l for l in langs if l != base]
    units = translate.collect_units(doc, res.i18n_pointers, base)
    estimator = translate.LiveTranslator(dict(os.environ), mode="mock", confirm=False, pii_findings=0)
    est = estimator.estimate_cost(units, targets, doc["course"].get("protected_terms", []), doc["course"].get("term_overrides", {}) or {}, base)
    print(f"{len(units)} localisable unit(s) x {len(targets)} target language(s)")
    print(f"model {est['model']}: {est['requests']} request(s), ~{est['input_tokens']:,} input / ~{est['output_tokens']:,} output tokens")
    print(f"estimated cost: USD {est['usd']} = INR {est['inr']} (prices {est['prices']}); cap in env: {est['cap_inr']}")
    print("This is an estimate from character counts; nothing was sent anywhere.")
    return 0


def cmd_selftest() -> int:
    suite = unittest.defaultTestLoader.discover(str(HERE / "tests"), pattern="test_*.py", top_level_dir=str(HERE))
    result = unittest.TextTestRunner(verbosity=1, stream=sys.stdout).run(suite)
    return 0 if result.wasSuccessful() else 1


def main(argv: list[str] | None = None) -> int:
    configure_utf8_stdio()
    args = build_parser().parse_args(argv)
    try:
        if args.selftest:
            return cmd_selftest()
        if args.estimate:
            return cmd_estimate(args)
        cfg = pipeline.RunConfig(
            course_path=args.course,
            schema_path=args.schema,
            out_dir=args.out or (HERE / "out" / pipeline._make_run_id()),
            source_path=None if args.no_source else args.source,
            mode=args.mode,
            preview=args.preview,
            package_mode=args.package_mode,
            langs=parse_csv_option(args.langs) or None,
            standards=parse_csv_option(args.standards),
            tts_engine=args.tts,
            tts_cmd=args.tts_cmd,
            confirm=args.confirm,
            actor=args.actor,
            smoke=not args.no_smoke,
            env=dict(os.environ),
        )
        result = pipeline.run(cfg)
    except HarnessError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2
    write_reports(result.out_dir, result.summary, result.ok)
    s = result.summary
    print(f"run {s['run_id']} ({s['mode']}{', preview' if s['preview'] else ''}): {len(result.packages)} package(s) in {result.out_dir}")
    for pkg, rep in zip(result.packages, result.reports):
        counts = rep.counts()
        print(f"  {pkg.path.name}: {pkg.size:,} bytes, checks pass={counts['pass']} fail={counts['fail']} warn={counts['warn']} skip={counts['skip']}")
    smoke = s["smoke"]
    print(f"  runtime smoke: {'skipped (' + smoke['skipped'] + ')' if smoke.get('ok') is None else ('PASS' if smoke['ok'] else 'FAIL')}")
    print(f"  audit: {s['audit']['entries']} entries, chain {'valid' if s['audit']['chain_valid'] else 'BROKEN'}")
    print(f"  report: {result.out_dir / 'validation-report.md'}")
    print(f"  result: {'PASS' if result.ok else 'FAIL'}")
    return 0 if result.ok else 1


if __name__ == "__main__":
    sys.exit(main())
