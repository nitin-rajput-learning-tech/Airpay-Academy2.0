"""The pilot pipeline: validate -> PII gate -> translate fill -> speech -> compile -> validate -> report.

One function, run(), does a whole pilot run. In MOCK mode (the default) nothing leaves the
machine: pseudo-localised strings, silent audio, no network. In LIVE mode every gate in
translate.LiveTranslator must be open and a typed [CONFIRM] is required before each request;
the live path has never been executed by this study.
"""
from __future__ import annotations

import copy
import json
import subprocess
import tempfile
import zipfile
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable, Mapping

from . import pii, translate, tts
from .audit import AuditLog, verify_chain
from .common import HARNESS_NAME, HARNESS_VERSION, HarnessError, read_json, resolve_pointer, sha256_bytes, sha256_file, write_json
from .packager import STANDARDS, BuiltPackage, carried_languages, compile_package
from .schema_lite import validate_document
from .validate_package import PackageReport, node_available, validate_package

HERE = Path(__file__).resolve().parent.parent

# Speech cost reference per 1,000 characters of narration, INR. Planning values from STUDY section 12 / sources/R3
# (Part C and D, list prices captured 2026-10-15; R3 converts at about INR 95/USD, model lines in the study at INR 87).
SPEECH_COST_PER_1K_CHARS_INR: dict[str, tuple[float, float, str]] = {
    "self-hosted open engine, on-demand India-region GPU (incl. ASR QA)": (0.6, 1.4, "R3 Part C: INR 5-11 per 8,000 characters"),
    "Sarvam Bulbul v2 / v3 (India-resident, vendor marketing)": (1.5, 3.0, "R3 Part D: INR 12 / 24 per 8,000 characters"),
    "Azure AI Speech / Google Neural2 / Polly neural (India region where offered)": (1.4, 1.6, "R3 Part D: about INR 11-13 per 8,000 characters"),
    "ElevenLabs (incumbent; India residency Enterprise-only)": (7.9, 20.0, "R3 Part D: about INR 63-160 per 8,000 characters"),
}


@dataclass
class RunConfig:
    course_path: Path
    schema_path: Path
    out_dir: Path
    source_path: Path | None = None
    mode: str = "mock"  # mock | live
    preview: bool = False
    package_mode: str = "multilingual"  # multilingual | per_language
    langs: list[str] | None = None
    standards: list[str] = field(default_factory=lambda: list(STANDARDS))
    tts_engine: str = "mock"  # mock | local-cmd
    tts_cmd: str = ""
    confirm: bool = False
    actor: str = "pilot-harness"
    run_id: str = ""
    smoke: bool = True
    env: Mapping[str, str] = field(default_factory=dict)
    prompt: Callable[[str], str] | None = None  # per-request [CONFIRM] hook for live mode (defaults to input)


@dataclass
class RunResult:
    ok: bool
    out_dir: Path
    summary: dict[str, Any]
    reports: list[PackageReport] = field(default_factory=list)
    packages: list[BuiltPackage] = field(default_factory=list)
    messages: list[str] = field(default_factory=list)


class ConfirmingTranslator(translate.LiveTranslator):
    """Live translator that asks for a typed [CONFIRM] before every single request."""

    def __init__(self, *args: Any, prompt: Callable[[str], str] | None = None, audit: AuditLog | None = None, **kwargs: Any):
        super().__init__(*args, **kwargs)
        self._prompt = prompt or input
        self._audit = audit

    def send(self, request: dict[str, Any]) -> dict[str, Any]:  # pragma: no cover - exercised only with a stubbed send in tests
        body = request["body"]
        payload = json.loads(body["messages"][0]["content"])
        chars = sum(len(u["text"]) for u in payload["units"])
        question = (
            f"[CONFIRM] send {len(payload['units'])} unit(s), {chars} chars, {payload['source_lang']} -> {payload['target_lang']} "
            f"to model {body['model']} (paid call). Type CONFIRM to proceed: "
        )
        answer = self._prompt(question)
        if str(answer).strip() != "CONFIRM":
            raise translate.LiveRefused("request not confirmed; nothing was sent")
        if self._audit:
            self._audit.record("confirm", target=payload["target_lang"], units=len(payload["units"]), chars=chars, model=body["model"])
        return super().send(request)


def _now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


def _make_run_id() -> str:
    return "run-" + datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")


def _lang_tts_chars(units: list[tts.NarrationUnit]) -> dict[str, int]:
    out: dict[str, int] = {}
    for unit in units:
        out[unit.lang] = out.get(unit.lang, 0) + len(unit.text)
    return out


def _speech_cost_table(chars_by_lang: dict[str, int]) -> list[dict[str, Any]]:
    total = sum(chars_by_lang.values())
    rows = []
    for engine, (low, high, note) in SPEECH_COST_PER_1K_CHARS_INR.items():
        rows.append({"engine": engine, "characters": total, "inr_low": round(total / 1000 * low, 1), "inr_high": round(total / 1000 * high, 1), "basis": note})
    return rows


def run(cfg: RunConfig) -> RunResult:
    """Execute one pilot run. Raises HarnessError for user-facing failures; returns RunResult otherwise."""
    run_id = cfg.run_id or _make_run_id()
    out = cfg.out_dir
    out.mkdir(parents=True, exist_ok=True)
    audit_path = out / "audit" / "audit.jsonl"
    if audit_path.exists():
        raise HarnessError(f"{audit_path} already exists; choose a new --out directory (audit logs are append-only)")
    audit = AuditLog(audit_path, run_id, cfg.actor, cfg.mode)
    messages: list[str] = []

    schema = read_json(cfg.schema_path)
    raw_doc = read_json(cfg.course_path)
    doc = copy.deepcopy(raw_doc)
    course = doc["course"]
    base = course["base_lang"]
    requested = cfg.langs
    standards = list(cfg.standards)
    for std in standards:
        if std not in STANDARDS:
            raise HarnessError(f"unknown standard {std!r}; choose from {', '.join(STANDARDS)}")
    if cfg.package_mode not in ("multilingual", "per_language"):
        raise HarnessError("--package-mode must be multilingual or per_language")

    audit.record(
        "run_start", harness=HARNESS_NAME, version=HARNESS_VERSION, mode=cfg.mode, preview=cfg.preview, package_mode=cfg.package_mode,
        standards=standards, langs=requested or course["langs"], course_sha256=sha256_file(cfg.course_path), schema_sha256=sha256_file(cfg.schema_path),
        tts=cfg.tts_engine, network_calls_allowed=(cfg.mode == "live"),
    )

    # 1. structural + x-rule validation of the document as authored
    pre = validate_document(
        doc, schema, preview=cfg.preview, standards=tuple(standards), langs=requested,
        source_path=cfg.source_path,
    )
    audit.record("validate_document", stage="pre", ok=pre.ok, errors=len(pre.errors), warnings=len(pre.warnings), rules=pre.rule_status)
    if not pre.ok:
        write_json(out / "validation-errors.json", pre.as_dict())
        raise HarnessError(f"the course document failed validation ({len(pre.errors)} error(s)); see {out / 'validation-errors.json'}\n" + "\n".join(f"  {i}" for i in pre.errors[:10]))

    # 2. PII gate on the source and every string in the document, before anything could leave the machine
    findings: list[pii.PiiFinding] = []
    if cfg.source_path and cfg.source_path.is_file():
        findings += pii.scan_file(cfg.source_path)
        course["source"]["sha256"] = sha256_file(cfg.source_path)
        course["source"]["words"] = len(cfg.source_path.read_text(encoding="utf-8", errors="replace").split())
    findings += pii.scan_document(doc)
    pii_summary = pii.summarise(findings)
    status = "clean" if not findings else "blocked"
    course["source"]["pii_scan"] = {"status": status, "findings": len(findings), "scanner": "pilot-pii-regex/0.1", "scanned_at": _now()}
    audit.record("pii_scan", status=status, findings=len(findings), by_kind=pii_summary["by_kind"])
    if findings:
        write_json(out / "pii-findings.json", pii_summary)
        raise HarnessError(f"PII scan blocked the run: {len(findings)} finding(s) ({pii_summary['by_kind']}); values are masked in {out / 'pii-findings.json'}. Redact the source and re-run.")

    # 3. translation fill
    units = translate.collect_units(doc, pre.i18n_pointers, base)
    targets = [l for l in (requested or course["langs"]) if l != base]
    estimator = translate.LiveTranslator({}, mode="mock", confirm=False, pii_findings=0)
    protected = list(course.get("protected_terms", []))
    overrides = course.get("term_overrides", {}) or {}
    estimate = estimator.estimate_cost(units, targets, protected, overrides, base) if targets else None
    if cfg.mode == "live":
        translator: Any = ConfirmingTranslator(dict(cfg.env), mode="live", confirm=cfg.confirm, pii_findings=len(findings), prompt=cfg.prompt, audit=audit)
    else:
        translator = translate.MockTranslator()
    fill = translator.fill(doc, units, requested)
    audit.record("translate_fill", engine=fill.engine, model=fill.model, prompt_version=fill.prompt_version, kept=fill.kept, filled=fill.filled, spend_inr=round(fill.spend_inr, 2), requests=fill.requests_built)
    generated = []
    for pointer, per_lang in fill.provenance.items():
        try:
            obj = resolve_pointer(doc, pointer)
        except (KeyError, IndexError, ValueError, TypeError):
            continue
        for lang, origin in per_lang.items():
            if origin == "machine" and isinstance(obj.get(lang), str):
                generated.append((f"{pointer}/{lang}", obj[lang]))
    gen_findings = pii.scan_strings(generated)
    audit.record("pii_scan", stage="generated_strings", scanned=len(generated), findings=len(gen_findings))
    if gen_findings:
        raise HarnessError(f"PII scan found {len(gen_findings)} identifier(s) in generated text; the run is stopped for review")

    # 4. speech (mock silent audio, or an owner-supplied local command; no SaaS adapter in the harness)
    carried, excluded = carried_languages(doc, requested, cfg.preview)
    if base not in carried:
        raise HarnessError(f"the base language '{base}' is not approved; use --preview for a non-learner build or approve it first")
    work = out / "work"
    if cfg.tts_engine == "mock":
        engine: Any = tts.MockTts()
    elif cfg.tts_engine == "local-cmd":
        engine = tts.LocalCmdTts(cfg.tts_cmd, work)
    else:
        raise HarnessError(f"unknown --tts {cfg.tts_engine!r}; choose mock or local-cmd")
    narration = tts.narration_units(doc, carried)
    media: dict[str, bytes] = {}
    lang_audio_ms: dict[str, int] = {}
    for unit in narration:
        result = engine.synthesize(unit)
        media[result.audio_path] = result.mp3
        media[result.vtt_path] = result.vtt.encode("utf-8")
        media[result.timestamps_path] = (json.dumps(result.word_timestamps, ensure_ascii=False) + "\n").encode("utf-8")
        lang_audio_ms[unit.lang] = lang_audio_ms.get(unit.lang, 0) + result.duration_ms
        _attach_media(doc, unit, result)
    audit.record("speech", engine=engine.engine, region=engine.processing_region, units=len(narration), audio_ms_by_lang=lang_audio_ms)

    # 5. provenance + re-validation of the filled document with the media set known
    doc["provenance"]["generator"] = {
        "name": HARNESS_NAME, "version": HARNESS_VERSION, "mode": cfg.mode, "model": fill.model if cfg.mode == "live" else "none (mock pseudo-localisation)",
        "prompt_version": fill.prompt_version, "gateway_ledger_ids": [], "processing_region": engine.processing_region,
    }
    doc["provenance"]["audit_log"] = "audit/audit.jsonl"
    post = validate_document(
        doc, schema, preview=cfg.preview, standards=tuple(standards), langs=requested,
        media_known=set(media), source_path=cfg.source_path,
    )
    audit.record("validate_document", stage="post", ok=post.ok, errors=len(post.errors), warnings=len(post.warnings), rules=post.rule_status)
    write_json(out / "course.compiled.json", doc)
    if not post.ok:
        write_json(out / "validation-errors.json", post.as_dict())
        raise HarnessError(f"the filled document failed validation ({len(post.errors)} error(s)); see {out / 'validation-errors.json'}\n" + "\n".join(f"  {i}" for i in post.errors[:10]))

    # 6. compile
    player_dir = HERE / "lib" / "player"
    packages: list[BuiltPackage] = []
    for std in standards:
        folder = out / std
        if cfg.package_mode == "multilingual":
            built = compile_package(doc, post.i18n_pointers, std, carried, player_dir=player_dir, media=media, out_path=folder / f"{course['slug']}-{std}.zip", mode="multilingual", preview=cfg.preview)
            packages.append(built)
            audit.record("compile", standard=std, file=built.path.name, bytes=built.size, sha256=built.sha256, langs=built.langs)
        else:
            for lang in carried:
                built = compile_package(doc, post.i18n_pointers, std, [lang], player_dir=player_dir, media=media, out_path=folder / f"{course['slug']}-{std}-{lang}.zip", mode="per_language", preview=cfg.preview, ident_suffix=f"-{lang}")
                packages.append(built)
                audit.record("compile", standard=std, file=built.path.name, bytes=built.size, sha256=built.sha256, langs=built.langs)

    # 7. validate what shipped
    reports: list[PackageReport] = []
    for built in packages:
        rep = validate_package(built.path, built.standard, int(course.get("mastery_score", 70)), doc, run_node=True)
        reports.append(rep)
        audit.record("validate_package", file=built.path.name, ok=rep.ok, counts=rep.counts())

    smoke = _runtime_smoke(out, packages, cfg.smoke, audit)
    chain_ok, entries, chain_sha = verify_chain(audit_path)
    summary = {
        "run_id": run_id, "mode": cfg.mode, "preview": cfg.preview, "package_mode": cfg.package_mode, "tts_engine": engine.engine,
        "carried_languages": carried, "excluded_languages": excluded, "standards": standards,
        "fill": fill.as_dict(), "narration_units": len(narration), "audio_ms_by_lang": lang_audio_ms, "tts_characters_by_lang": _lang_tts_chars(narration),
        "speech_cost_table": _speech_cost_table(_lang_tts_chars(narration)), "translation_estimate": estimate, "pii": {"status": status, "findings": len(findings)},
        "document_rules": post.as_dict(), "state_budget": post.state_budget, "packages": [p.as_dict() for p in packages],
        "package_reports": [r.as_dict() for r in reports], "smoke": smoke,
        "audit": {"entries": entries, "chain_valid": chain_ok, "final_sha256": chain_sha, "file": "audit/audit.jsonl"},
        "spend_inr": round(fill.spend_inr, 2), "course_sha256": sha256_file(cfg.course_path), "generated_at": _now(),
        "lang_states": course.get("lang_states", {}), "pending_units": post.pending,
        "network_calls_made": cfg.mode == "live",
    }
    ok = post.ok and all(r.ok for r in reports) and chain_ok and (smoke.get("ok") is not False)
    return RunResult(ok=ok, out_dir=out, summary=summary, reports=reports, packages=packages, messages=messages)


def _attach_media(doc: dict[str, Any], unit: tts.NarrationUnit, result: tts.MediaResult) -> None:
    """Write the audio and caption references into the right block or scenario node."""
    sha = sha256_bytes(result.mp3)
    for block in doc["blocks"]:
        if block["id"] != unit.block_id:
            continue
        holder = block
        if unit.node_id is not None:
            holder = next(n for n in block["nodes"] if n["id"] == unit.node_id)
        holder.setdefault("audio", {})[unit.lang] = result.media_ref(sha)
        holder.setdefault("captions", {})[unit.lang] = result.vtt_path
        return


def _runtime_smoke(out: Path, packages: list[BuiltPackage], enabled: bool, audit: AuditLog) -> dict[str, Any]:
    """Headless learner simulation against each compiled package with fake LMS APIs (needs Node.js)."""
    if not enabled:
        return {"ok": None, "skipped": "disabled"}
    node = node_available()
    script = HERE / "tests" / "smoke_package.js"
    if not node or not script.is_file():
        audit.record("runtime_smoke", skipped=True, reason="node or tests/smoke_package.js not available")
        return {"ok": None, "skipped": "Node.js or tests/smoke_package.js not available"}
    results = []
    ok = True
    for built in packages:
        with tempfile.TemporaryDirectory(prefix="sp-smoke-") as tmp:
            with zipfile.ZipFile(built.path) as archive:
                archive.extractall(tmp)
            done = subprocess.run([node, str(script), tmp, built.standard], capture_output=True, timeout=120)
            text = done.stdout.decode("utf-8", errors="replace").strip()
            try:
                parsed = json.loads(text.splitlines()[-1]) if text else {"ok": False, "error": "no output"}
            except json.JSONDecodeError:
                parsed = {"ok": False, "error": text[-300:]}
            if done.returncode != 0:
                parsed["ok"] = False
                parsed.setdefault("error", done.stderr.decode("utf-8", errors="replace")[-300:])
            parsed["file"] = built.path.name
            results.append(parsed)
            ok = ok and bool(parsed.get("ok"))
    audit.record("runtime_smoke", ok=ok, packages=len(results))
    return {"ok": ok, "results": results}
