"""Post-build package validation (CLAUDE.md section 8 gates, extended by ADR-034 D3).

Reads the finished ZIP only (never the build directory), so what is validated is what ships.
Checks are grouped by id so the report can show a pass/fail matrix per package:

    Z01 zip integrity, unique names, safe paths           S06 mastery score present and equal to the course's
    Z02 manifest at the ZIP root                          S07 launch file exists
    Z03 manifest is well-formed XML (no DTD/entities)     P01 player files and the right runtime adapter
    Z04 every manifest file exists in the ZIP             P02 course-data.js parses and matches the standard
    Z05 every ZIP file (but the manifest) is declared     P03 every carried language: dir, lang, UI strings
    Z06 size within 50 MB                                 M01 narrated blocks have audio + captions per language
    S01 root element and namespaces for the standard      M02 captions are valid WebVTT with ascending cues
    S02 schema version string                             B01 worst-case suspend token within the budget
    S03 default organisation resolves                     J01 JavaScript parses (when Node is available)
    S04 item -> resource references resolve
    S05 scormtype / scormType attribute and casing
"""
from __future__ import annotations

import json
import re
import shutil
import subprocess
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from . import statetoken
from .common import HarnessError
from .packager import MAX_PACKAGE_BYTES, PLAYER_ASSETS, STANDARD_LABEL
from .schema_lite import media_refs

NS = {
    "scorm12": "http://www.imsproject.org/xsd/imscp_rootv1p1p2",
    "scorm12_adlcp": "http://www.adlnet.org/xsd/adlcp_rootv1p2",
    "scorm2004": "http://www.imsglobal.org/xsd/imscp_v1p1",
    "scorm2004_adlcp": "http://www.adlnet.org/xsd/adlcp_v1p3",
    "imsss": "http://www.imsglobal.org/xsd/imsss",
    "cmi5": "https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd",
}
VTT_TIME = re.compile(r"(\d{2,}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2,}):(\d{2}):(\d{2})\.(\d{3})")
DATA_PREFIX = "window.SENTIENTIA_COURSE = "


@dataclass
class Check:
    id: str
    status: str  # pass | fail | warn | skip
    message: str

    def as_dict(self) -> dict[str, str]:
        return {"id": self.id, "status": self.status, "message": self.message}


@dataclass
class PackageReport:
    standard: str
    file: str
    checks: list[Check] = field(default_factory=list)

    def add(self, check_id: str, status: str, message: str) -> None:
        self.checks.append(Check(check_id, status, message))

    @property
    def ok(self) -> bool:
        return not any(c.status == "fail" for c in self.checks)

    def counts(self) -> dict[str, int]:
        out = {"pass": 0, "fail": 0, "warn": 0, "skip": 0}
        for check in self.checks:
            out[check.status] += 1
        return out

    def as_dict(self) -> dict[str, Any]:
        return {"standard": self.standard, "file": self.file, "ok": self.ok, "counts": self.counts(), "checks": [c.as_dict() for c in self.checks]}


def _local(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def _ns_of(tag: str) -> str:
    return tag[1:].split("}", 1)[0] if tag.startswith("{") else ""


def _unsafe_path(name: str) -> bool:
    return name.startswith(("/", "\\")) or "\\" in name or ".." in name.split("/") or (len(name) > 1 and name[1] == ":")


def node_available() -> str | None:
    return shutil.which("node")


def validate_package(zip_path: Path, standard: str, mastery_expected: int, doc_for_budget: dict[str, Any] | None = None, *, run_node: bool = True) -> PackageReport:
    """Run every check against one ZIP. Never raises for a bad package; failures are reported."""
    report = PackageReport(standard=standard, file=zip_path.name)
    try:
        archive = zipfile.ZipFile(zip_path)
    except (zipfile.BadZipFile, OSError) as exc:
        report.add("Z01", "fail", f"cannot open ZIP: {exc}")
        return report
    with archive:
        infos = archive.infolist()
        names = [i.filename for i in infos]
        bad = archive.testzip()
        dup = sorted({n for n in names if names.count(n) > 1})
        unsafe = [n for n in names if _unsafe_path(n)]
        if bad or dup or unsafe:
            report.add("Z01", "fail", f"integrity problem: corrupt={bad}, duplicates={dup[:3]}, unsafe={unsafe[:3]}")
        else:
            report.add("Z01", "pass", f"{len(names)} entries, no duplicates, no unsafe paths")
        manifest_name = "cmi5.xml" if standard == "cmi5" else "imsmanifest.xml"
        if manifest_name in names:
            report.add("Z02", "pass", f"{manifest_name} is at the ZIP root")
        else:
            nested = [n for n in names if n.endswith(manifest_name)]
            report.add("Z02", "fail", f"{manifest_name} is not at the ZIP root" + (f" (found at {nested[0]})" if nested else ""))
            return report
        size = zip_path.stat().st_size
        report.add("Z06", "pass" if size <= MAX_PACKAGE_BYTES else "fail", f"{size:,} bytes (limit {MAX_PACKAGE_BYTES:,})")

        raw = archive.read(manifest_name)
        if re.search(rb"<!DOCTYPE|<!ENTITY", raw, re.IGNORECASE):
            report.add("Z03", "fail", "manifest declares a DTD or entity; refused (XML entity expansion risk)")
            return report
        # stdlib ElementTree is not hardened against entity expansion; the DOCTYPE/ENTITY refusal above is the mitigation
        try:
            root = ET.fromstring(raw)  # noqa: S314
            report.add("Z03", "pass", "manifest is well-formed XML without DTD or entities")
        except ET.ParseError as exc:
            report.add("Z03", "fail", f"manifest is not well-formed XML: {exc}")
            return report

        declared = _declared_files(root, standard)
        launch = _check_structure(report, root, standard, mastery_expected, names, declared)
        if standard == "cmi5":
            declared = {launch} if launch else set()
        else:
            missing = sorted(d for d in declared if d not in names)
            report.add("Z04", "fail" if missing else "pass", f"{len(missing)} declared file(s) missing from the ZIP: {missing[:3]}" if missing else f"all {len(declared)} declared files exist")
            extra = sorted(n for n in names if n != manifest_name and n not in declared)
            report.add("Z05", "fail" if extra else "pass", f"{len(extra)} packaged file(s) not declared in the manifest: {extra[:3]}" if extra else "every packaged file is declared")
        _check_player(report, archive, names, standard, doc_for_budget, run_node)
    return report


def _declared_files(root: ET.Element, standard: str) -> set[str]:
    if standard == "cmi5":
        return set()
    return {f.get("href") or "" for f in root.iter() if _local(f.tag) == "file"}


def _check_structure(report: PackageReport, root: ET.Element, standard: str, mastery: int, names: list[str], declared: set[str]) -> str | None:
    """S01-S07; returns the launch href."""
    if standard == "cmi5":
        return _check_cmi5(report, root, mastery, names)
    ns = NS[standard]
    adl = NS[f"{standard}_adlcp"]
    root_ok = _local(root.tag) == "manifest" and _ns_of(root.tag) == ns
    report.add("S01", "pass" if root_ok else "fail", f"root <manifest> in namespace {ns}" if root_ok else f"root is {root.tag}, expected <manifest> in {ns}")
    schemaversion = next((e.text or "" for e in root.iter() if _local(e.tag) == "schemaversion"), "")
    expected = "1.2" if standard == "scorm12" else "2004 4th Edition"
    report.add("S02", "pass" if schemaversion == expected else "fail", f"schemaversion '{schemaversion}'" + ("" if schemaversion == expected else f" (expected '{expected}')"))
    orgs = next((e for e in root.iter() if _local(e.tag) == "organizations"), None)
    default = orgs.get("default") if orgs is not None else None
    org_ids = {e.get("identifier") for e in root.iter() if _local(e.tag) == "organization"}
    report.add("S03", "pass" if default in org_ids else "fail", f"organizations default='{default}' resolves" if default in org_ids else f"organizations default='{default}' does not match any organization {sorted(i for i in org_ids if i)}")
    resources = {e.get("identifier"): e for e in root.iter() if _local(e.tag) == "resource"}
    items = [e for e in root.iter() if _local(e.tag) == "item"]
    refs_ok = bool(items) and all(i.get("identifierref") in resources for i in items)
    report.add("S04", "pass" if refs_ok else "fail", f"{len(items)} item(s) reference existing resources" if refs_ok else "an item has a missing or unknown identifierref")
    attr_name = "scormtype" if standard == "scorm12" else "scormType"
    wrong_case = "scormType" if standard == "scorm12" else "scormtype"
    launch = None
    scorm_ok = bool(resources)
    for res in resources.values():
        value = res.get(f"{{{adl}}}{attr_name}")
        if value != "sco" or res.get(f"{{{adl}}}{wrong_case}") is not None:
            scorm_ok = False
        launch = res.get("href")
    report.add("S05", "pass" if scorm_ok else "fail", f"adlcp:{attr_name}=\"sco\" with the correct casing" if scorm_ok else f"resource must use adlcp:{attr_name}=\"sco\" (and not {wrong_case})")
    if standard == "scorm12":
        found = [e.text for e in root.iter() if _local(e.tag) == "masteryscore"]
        ok = found == [str(mastery)]
        report.add("S06", "pass" if ok else "fail", f"adlcp:masteryscore = {mastery}" if ok else f"adlcp:masteryscore is {found}, expected [{mastery}]")
    else:
        found = [e.text for e in root.iter() if _local(e.tag) == "minNormalizedMeasure"]
        expected_scaled = f"{mastery / 100:.2f}"
        ok = found == [expected_scaled]
        report.add("S06", "pass" if ok else "fail", f"imsss:minNormalizedMeasure = {expected_scaled}" if ok else f"minNormalizedMeasure is {found}, expected [{expected_scaled}]")
    report.add("S07", "pass" if launch and launch in names else "fail", f"launch file '{launch}' exists" if launch and launch in names else f"launch file '{launch}' is missing from the ZIP")
    return launch


def _check_cmi5(report: PackageReport, root: ET.Element, mastery: int, names: list[str]) -> str | None:
    ns = NS["cmi5"]
    report.add("S01", "pass" if _local(root.tag) == "courseStructure" and _ns_of(root.tag) == ns else "fail", "root <courseStructure> in the cmi5 namespace" if _local(root.tag) == "courseStructure" else f"root is {root.tag}")
    report.add("S02", "pass", "cmi5 course structure (schemaversion is implicit in the namespace)")
    course = next((e for e in root.iter() if _local(e.tag) == "course"), None)
    aus = [e for e in root.iter() if _local(e.tag) == "au"]
    ids_ok = course is not None and ":" in (course.get("id") or "") and aus and all(":" in (a.get("id") or "") for a in aus)
    report.add("S03", "pass" if ids_ok else "fail", "course and AU ids are IRIs" if ids_ok else "course/AU id is missing or not an IRI")
    report.add("S04", "pass" if len(aus) == 1 else "fail", f"{len(aus)} assignable unit(s) (one expected)")
    au = aus[0] if aus else None
    move_on = au.get("moveOn") if au is not None else None
    report.add("S05", "pass" if move_on == "CompletedAndPassed" else "fail", f"moveOn='{move_on}'")
    ms = au.get("masteryScore") if au is not None else None
    try:
        ok = ms is not None and abs(float(ms) - mastery / 100) < 0.005 and 0 <= float(ms) <= 1
    except ValueError:
        ok = False
    report.add("S06", "pass" if ok else "fail", f"masteryScore='{ms}' (expected {mastery / 100:.2f})")
    url = next((e.text for e in au.iter() if _local(e.tag) == "url"), None) if au is not None else None
    report.add("S07", "pass" if url and url in names else "fail", f"launch url '{url}' exists" if url and url in names else f"launch url '{url}' is missing from the ZIP")
    return url


def _parse_course_data(text: str) -> dict[str, Any]:
    if not text.startswith(DATA_PREFIX) or not text.rstrip().endswith(";"):
        raise ValueError("course-data.js does not have the expected assignment form")
    return json.loads(text[len(DATA_PREFIX):].rstrip().rstrip(";"))


def _check_player(report: PackageReport, archive: zipfile.ZipFile, names: list[str], standard: str, doc_for_budget: dict[str, Any] | None, run_node: bool) -> None:
    runtime = f"runtime-{standard}.js"
    others = [f"runtime-{s}.js" for s in ("scorm12", "scorm2004", "cmi5") if s != standard]
    need = list(PLAYER_ASSETS) + [runtime, "index.html", "course-data.js"]
    missing = [n for n in need if n not in names]
    stray = [n for n in others if n in names]
    if missing or stray:
        report.add("P01", "fail", f"player files missing={missing}, wrong-standard runtimes present={stray}")
    else:
        report.add("P01", "pass", f"player assets present with only {runtime}")
    index = archive.read("index.html").decode("utf-8", errors="replace") if "index.html" in names else ""
    if re.search(r"<html[^>]*\blang=\"[^\"]+\"", index) and re.search(r"<html[^>]*\bdir=\"(ltr|rtl)\"", index):
        report.add("P03", "pass", "index.html carries lang and dir attributes (not hard-coded to English)")
    else:
        report.add("P03", "fail", "index.html lacks a lang or dir attribute on <html>")
    scripts = re.findall(r"<script src=\"([^\"]+)\"", index)
    unresolved = [s for s in scripts if s not in names]
    if unresolved:
        report.add("P01", "fail", f"index.html references missing script(s): {unresolved}")
    if "course-data.js" not in names:
        return
    try:
        data = _parse_course_data(archive.read("course-data.js").decode("ascii"))
    except (ValueError, UnicodeDecodeError) as exc:
        report.add("P02", "fail", f"course-data.js cannot be parsed: {exc}")
        return
    if data.get("standard") != standard:
        report.add("P02", "fail", f"course-data.js says standard '{data.get('standard')}', package is {standard}")
    else:
        report.add("P02", "pass", f"course-data.js parses ({len(data.get('langs', []))} language(s), {len(data['doc']['blocks'])} block(s))")
    langs = data.get("langs", [])
    rtl = set(data.get("rtl", []))
    bad_dir = [l["tag"] for l in langs if (l["tag"].split("-")[0] in rtl or l["tag"] in rtl) != (l["dir"] == "rtl")]
    no_ui = [l["tag"] for l in langs if l["tag"] not in data.get("ui", {})]
    if bad_dir or no_ui:
        report.add("P03", "fail", f"direction wrong for {bad_dir}; no UI strings for {no_ui}")
    else:
        rtl_carried = [l["tag"] for l in langs if l["dir"] == "rtl"]
        report.add("P03", "pass", "per-language dir and UI strings present" + (f"; RTL carried: {', '.join(rtl_carried)}" if rtl_carried else ""))
    _check_media(report, archive, names, data)
    if doc_for_budget is not None:
        budget = statetoken.worst_case_report(data["doc"], [l["tag"] for l in langs])
        limit = statetoken.SCORM12_PRECOMPRESS_BUDGET if standard == "scorm12" else statetoken.SCORM2004_LIMIT
        ok = budget["token_length"] <= limit
        report.add("B01", "pass" if ok else "fail", f"worst-case suspend token {budget['token_length']} chars (compressed {budget['compressed_length']}), budget {limit}")
    if run_node:
        _check_js(report, archive, names)


def _check_media(report: PackageReport, archive: zipfile.ZipFile, names: list[str], data: dict[str, Any]) -> None:
    doc = data["doc"]
    carried = [l["tag"] for l in data["langs"]]
    missing: list[str] = []
    for block in doc["blocks"]:
        holders: list[tuple[str, dict[str, Any], bool]] = []
        if block.get("type") == "slide" and (block.get("narration") or {}):
            holders.append((block["id"], block, True))
        elif block.get("type") == "scenario":
            for node in block.get("nodes", []):
                if node.get("prompt"):
                    holders.append((f"{block['id']}.{node['id']}", node, True))
        for label, holder, _ in holders:
            for lang in carried:
                audio = (holder.get("audio") or {}).get(lang)
                caption = (holder.get("captions") or {}).get(lang)
                if not audio or audio.get("path") not in names:
                    missing.append(f"{label}/{lang}/audio")
                if not caption or caption not in names:
                    missing.append(f"{label}/{lang}/captions")
    if missing:
        report.add("M01", "fail", f"{len(missing)} narrated unit(s) lack audio or captions, e.g. {missing[:3]}")
    else:
        report.add("M01", "pass", f"every narrated block has audio and captions in all {len(carried)} carried language(s)")
    bad_vtt: list[str] = []
    for path in sorted({p for _, p in media_refs(doc) if p.endswith(".vtt")}):
        if path not in names:
            continue
        text = archive.read(path).decode("utf-8", errors="replace")
        if not text.startswith("WEBVTT"):
            bad_vtt.append(f"{path}: missing WEBVTT header")
            continue
        last_end = -1
        for match in VTT_TIME.finditer(text):
            g = [int(x) for x in match.groups()]
            start = ((g[0] * 60 + g[1]) * 60 + g[2]) * 1000 + g[3]
            end = ((g[4] * 60 + g[5]) * 60 + g[6]) * 1000 + g[7]
            if end <= start or start < last_end:
                bad_vtt.append(f"{path}: cue out of order at {match.group(0)}")
                break
            last_end = end
    report.add("M02", "fail" if bad_vtt else "pass", f"{len(bad_vtt)} caption file(s) invalid: {bad_vtt[:2]}" if bad_vtt else "caption files are valid WebVTT with ascending, non-overlapping cues")


JS_COMPILE = (
    "const fs=require('fs'),vm=require('vm');const bad=[];"
    "for(const f of process.argv.slice(1)){try{new vm.Script(fs.readFileSync(f,'utf8'),{filename:f});}"
    "catch(e){bad.push(require('path').basename(f)+': '+e.message);}}"
    "console.log(JSON.stringify(bad));process.exit(bad.length?1:0);"
)


def _check_js(report: PackageReport, archive: zipfile.ZipFile, names: list[str]) -> None:
    """One Node process compiles every packaged .js file (syntax only; nothing is executed)."""
    node = node_available()
    if not node:
        report.add("J01", "skip", "Node.js not found; JavaScript syntax not checked")
        return
    scripts = [n for n in names if n.endswith(".js") and not _unsafe_path(n)]
    with tempfile.TemporaryDirectory(prefix="sp-js-") as tmp:
        paths = []
        for name in scripts:
            target = Path(tmp) / name
            target.write_bytes(archive.read(name))
            paths.append(str(target))
        done = subprocess.run([node, "-e", JS_COMPILE, *paths], capture_output=True, timeout=120)
    try:
        failures = json.loads(done.stdout.decode("utf-8", errors="replace").strip().splitlines()[-1])
    except (json.JSONDecodeError, IndexError):
        failures = [f"node produced no result (exit {done.returncode})"]
    report.add("J01", "fail" if failures else "pass", f"JavaScript syntax errors: {failures}" if failures else f"all {len(scripts)} packaged JavaScript files compile")


def label(standard: str) -> str:
    return STANDARD_LABEL.get(standard, standard)
