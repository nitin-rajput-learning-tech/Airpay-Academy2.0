"""validation-report.json and validation-report.md writers."""
from __future__ import annotations

from pathlib import Path
from typing import Any

from .common import write_json, write_text

STATUS_MARK = {"pass": "PASS", "fail": "FAIL", "warn": "WARN", "skip": "skip"}


def _table(headers: list[str], rows: list[list[Any]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "|" + "|".join(["---"] * len(headers)) + "|"]
    for row in rows:
        lines.append("| " + " | ".join(str(cell).replace("|", "/").replace("\n", " ") for cell in row) + " |")
    return "\n".join(lines)


def render_markdown(summary: dict[str, Any], ok: bool) -> str:
    s = summary
    out: list[str] = []
    out.append(f"# Pilot validation report - {s['run_id']}")
    out.append("")
    out.append(
        f"**Result: {'PASS' if ok else 'FAIL'}** | mode `{s['mode']}` | preview build: {s['preview']} | package mode `{s['package_mode']}` | speech engine `{s['tts_engine']}` | "
        f"network calls made: **{'yes' if s['network_calls_made'] else 'none'}** | spend INR {s['spend_inr']}"
    )
    out.append("")
    out.append(f"Course document sha256 `{s['course_sha256'][:16]}...`; generated {s['generated_at']}. Mock mode proves the compiler, the runtimes and the governance plumbing; it does not prove translation quality, voice quality, Moodle behaviour or LRS acceptance.")
    out.append("")

    rules = s["document_rules"]
    out.append("## 1. Course document: schema and rules R01-R12")
    out.append("")
    out.append(_table(["Rule", "Status"], [[k, v.upper()] for k, v in rules["rule_status"].items()]))
    out.append("")
    out.append(f"{rules['error_count']} error(s), {rules['warning_count']} warning(s).")
    warns = [i for i in rules["issues"] if i["level"] in ("warning", "error")]
    if warns:
        out.append("")
        out.append(_table(["Level", "Rule", "Where", "Message"], [[i["level"], i["rule"], i["pointer"], i["message"]] for i in warns[:25]]))
    budget = s.get("state_budget") or {}
    if budget:
        out.append("")
        out.append(
            f"State budget (R05): worst-case `cmi.suspend_data` token is **{budget.get('token_length')}** characters plain, {budget.get('compressed_length')} compressed; "
            f"budgets {budget['budgets']['scorm12_precompress_budget']} pre-compression / {budget['budgets']['scorm12_limit']} SCORM 1.2 limit / {budget['budgets']['scorm2004_limit']} SCORM 2004."
        )
    out.append("")

    out.append("## 2. Source and PII gate")
    out.append("")
    out.append(f"PII scan status **{s['pii']['status']}** ({s['pii']['findings']} finding(s)). Scanner: Aadhaar (Verhoeff), PAN, Indian mobile, email, IFSC, card (Luhn). Values are never stored; findings are masked. Generated strings are re-scanned after translation.")
    out.append("")

    out.append("## 3. Languages")
    out.append("")
    fill = s["fill"]
    states = s["lang_states"]
    rows = []
    for lang in s["carried_languages"]:
        rows.append([
            lang, (states.get(lang) or {}).get("state", "pending"), fill["kept_human_units"].get(lang, "-"), fill["filled_machine_units"].get(lang, "-"),
            f"{s['audio_ms_by_lang'].get(lang, 0) / 1000:.0f} s", s["tts_characters_by_lang"].get(lang, 0),
        ])
    out.append(_table(["Language", "Review state", "Human units kept", "Machine/pseudo units filled", "Audio (silent in mock)", "Narration characters"], rows))
    if s["excluded_languages"]:
        out.append("")
        out.append("Not carried: " + "; ".join(f"`{k}` - {v}" for k, v in s["excluded_languages"].items()))
    out.append("")
    out.append(f"Engine `{fill['engine']}`, model `{fill['model']}`, prompt `{fill['prompt_version']}`. In mock mode every machine-filled unit is pseudo-localised (`[xx] source text`) and is labelled as such in the player.")
    out.append("")

    out.append("## 4. Packages")
    out.append("")
    out.append(_table(
        ["Standard", "File", "Languages", "Size", "sha256 (first 16)", "Checks pass/fail/warn/skip"],
        [[p["standard"], p["file"], len(p["langs"]), f"{p['size_bytes']:,} B", p["sha256"][:16], "/".join(str(r["counts"][k]) for k in ("pass", "fail", "warn", "skip"))]
         for p, r in zip(s["packages"], s["package_reports"])],
    ))
    out.append("")
    for report in s["package_reports"]:
        out.append(f"### {report['file']}")
        out.append("")
        out.append(_table(["Check", "Status", "Detail"], [[c["id"], STATUS_MARK[c["status"]], c["message"]] for c in report["checks"]]))
        out.append("")

    smoke = s["smoke"]
    out.append("## 5. Headless runtime smoke (fake LMS and fake LRS)")
    out.append("")
    if smoke.get("ok") is None:
        out.append(f"Skipped: {smoke.get('skipped')}.")
    else:
        for result in smoke["results"]:
            passed = sum(1 for c in result.get("checks", []) if c["ok"])
            total = len(result.get("checks", []))
            out.append(f"- `{result['file']}`: **{'PASS' if result.get('ok') else 'FAIL'}** ({passed}/{total} checks)" + (f" - {result.get('error')}" if result.get("error") else ""))
            for c in result.get("checks", []):
                if not c["ok"]:
                    out.append(f"  - FAILED: {c['name']} ({c['detail']})")
    out.append("")

    out.append("## 6. Audit trail")
    out.append("")
    a = s["audit"]
    out.append(f"`{a['file']}`: {a['entries']} append-only entries, hash chain {'valid' if a['chain_valid'] else 'BROKEN'}, final sha256 `{a['final_sha256']}`. Each entry carries the previous entry's hash; `verify_chain()` recomputes it from the file.")
    out.append("")

    est = s.get("translation_estimate")
    out.append("## 7. What a live run would cost (pre-flight estimate; nothing was spent)")
    out.append("")
    if est:
        out.append(
            f"Translation to {len(est['per_lang'])} language(s) with `{est['model']}` at the harness price inputs "
            f"(USD {est['prices']['input_usd_per_mtok']}/{est['prices']['output_usd_per_mtok']} per million input/output tokens, USD/INR {est['prices']['usd_inr']}): "
            f"about {est['input_tokens']:,} input and {est['output_tokens']:,} output tokens in {est['requests']} request(s) = **INR {est['inr']}** (this counts every language as untranslated; "
            f"human-translated units are skipped in a real run). The study's Sonnet 5.5 price inputs and the whole-course model budget are in STUDY section 12."
        )
    else:
        out.append("No target languages requested.")
    out.append("")
    out.append("Speech for the same narration, at list-price planning rates (STUDY section 12, sources/R3):")
    out.append("")
    out.append(_table(["Engine", "Characters", "INR low", "INR high", "Basis"], [[r["engine"], f"{r['characters']:,}", r["inr_low"], r["inr_high"], r["basis"]] for r in s["speech_cost_table"]]))
    out.append("")
    out.append("Human review is the dominant cost and is not modelled here (STUDY section 12.3).")
    out.append("")
    out.append("## 8. Known limits of this build")
    out.append("")
    out.append("- Fonts are not bundled in the pilot packages (the font stack falls back to system fonts; Noto subsetting is Stage 1).")
    out.append("- cmi5 reports the first passed/failed result only; later retries update the score but not the LRS result statement.")
    out.append("- The DOM player was exercised in a real browser against a stub LMS (see `pilot/evidence/`), not in Moodle; Moodle 5.3 runtime testing is Stage 1 work on UAT.")
    out.append("")
    return "\n".join(out)


def write_reports(out_dir: Path, summary: dict[str, Any], ok: bool) -> None:
    write_json(out_dir / "validation-report.json", {"ok": ok, **summary})
    write_text(out_dir / "validation-report.md", render_markdown(summary, ok))
