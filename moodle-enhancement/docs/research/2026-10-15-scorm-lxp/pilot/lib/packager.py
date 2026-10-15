"""Package compilers: one canonical course document -> SCORM 1.2, SCORM 2004 4th Edition, cmi5.

Every package carries the same static player (lib/player/*) and a generated
course-data.js. Only the manifest and the runtime adapter differ per standard.
ZIPs are deterministic: sorted entries, fixed timestamps, no extra attributes, so the same
inputs always give the same sha256 (the PHP compiler's contract tests rely on this).

Layout (identical for the three standards, plus the manifest at the ZIP root):

    imsmanifest.xml | cmi5.xml
    index.html  player.css  core.js  runtime-<standard>.js  player.js
    course-data.js           window.SENTIENTIA_COURSE = {...}  (ASCII-only JSON)
    course.json              the compiled course document (UTF-8, for tooling)
    audio/<lang>/<key>.mp3   captions/<lang>/<key>.vtt   captions/<lang>/<key>.words.json

Fonts are NOT bundled in the pilot (the font stack lists Noto families and falls back to
system fonts); bundling subsetted Noto fonts is Stage 1 work (STUDY section 7.1).
"""
from __future__ import annotations

import copy
import html
import json
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any
from xml.sax.saxutils import escape, quoteattr

from . import ui_strings
from .common import HARNESS_NAME, HARNESS_VERSION, HarnessError, canonical_json, dir_for_lang, resolve_pointer, sha256_bytes
from .schema_lite import i18n_objects, media_refs

STANDARDS = ("scorm12", "scorm2004", "cmi5")
SCD_NAME = {"scorm12": "scorm12", "scorm2004": "scorm2004_4", "cmi5": "cmi5"}
STANDARD_LABEL = {"scorm12": "SCORM 1.2", "scorm2004": "SCORM 2004 4th Edition", "cmi5": "cmi5 (xAPI)"}
PLAYER_ASSETS = ("player.css", "core.js", "player.js")
ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)
MAX_PACKAGE_BYTES = 50 * 1024 * 1024  # CLAUDE.md section 8 / ADR-034 D3 size gate

NS_SCORM12 = {
    "xmlns": "http://www.imsproject.org/xsd/imscp_rootv1p1p2",
    "xmlns:adlcp": "http://www.adlnet.org/xsd/adlcp_rootv1p2",
    "xmlns:xsi": "http://www.w3.org/2001/XMLSchema-instance",
    "xsi:schemaLocation": (
        "http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd "
        "http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd "
        "http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd"
    ),
}
NS_SCORM2004 = {
    "xmlns": "http://www.imsglobal.org/xsd/imscp_v1p1",
    "xmlns:adlcp": "http://www.adlnet.org/xsd/adlcp_v1p3",
    "xmlns:adlseq": "http://www.adlnet.org/xsd/adlseq_v1p3",
    "xmlns:adlnav": "http://www.adlnet.org/xsd/adlnav_v1p3",
    "xmlns:imsss": "http://www.imsglobal.org/xsd/imsss",
    "xmlns:xsi": "http://www.w3.org/2001/XMLSchema-instance",
    "xsi:schemaLocation": (
        "http://www.imsglobal.org/xsd/imscp_v1p1 imscp_v1p1.xsd "
        "http://www.adlnet.org/xsd/adlcp_v1p3 adlcp_v1p3.xsd "
        "http://www.adlnet.org/xsd/adlseq_v1p3 adlseq_v1p3.xsd "
        "http://www.adlnet.org/xsd/adlnav_v1p3 adlnav_v1p3.xsd "
        "http://www.imsglobal.org/xsd/imsss imsss_v1p0.xsd"
    ),
}
NS_CMI5 = {
    "xmlns": "https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd",
    "xmlns:xsi": "http://www.w3.org/2001/XMLSchema-instance",
    "xsi:schemaLocation": "https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd CourseStructure.xsd",
}


@dataclass
class BuiltPackage:
    """One compiled package on disk."""

    standard: str
    path: Path
    langs: list[str]
    mode: str
    manifest_name: str
    size: int
    sha256: str
    files: list[dict[str, Any]] = field(default_factory=list)

    def as_dict(self) -> dict[str, Any]:
        return {
            "standard": self.standard,
            "file": self.path.name,
            "langs": list(self.langs),
            "package_mode": self.mode,
            "manifest": self.manifest_name,
            "size_bytes": self.size,
            "sha256": self.sha256,
            "file_count": len(self.files),
        }


# -- document restriction ---------------------------------------------------------------------------------


def restrict_document(doc: dict[str, Any], i18n_pointers: list[str], carried: list[str]) -> dict[str, Any]:
    """Deep copy of `doc` limited to the carried languages.

    Text keeps the base-language form as a fallback when the base language is not itself carried
    (per-language packages); audio and caption sets keep only carried languages.
    """
    out = copy.deepcopy(doc)
    base = out["course"]["base_lang"]
    keep_text = set(carried) | {base}
    keep_media = set(carried)
    for pointer, obj in i18n_objects(out, i18n_pointers):
        for key in [k for k in obj if k not in keep_text]:
            del obj[key]

    def trim_set(holder: dict[str, Any], name: str) -> None:
        media_set = holder.get(name)
        if isinstance(media_set, dict):
            for key in [k for k in media_set if k not in keep_media]:
                del media_set[key]

    for block in out.get("blocks", []):
        for name in ("audio", "captions", "media"):
            trim_set(block, name)
        for node in block.get("nodes", []) or []:
            for name in ("audio", "captions"):
                trim_set(node, name)
    course = out["course"]
    course["langs"] = [l for l in course["langs"] if l in keep_media]
    for name in ("lang_states", "term_overrides"):
        holder = course.get(name)
        if isinstance(holder, dict):
            for key in [k for k in holder if k not in keep_text]:
                del holder[key]
    return out


# -- manifests ---------------------------------------------------------------------------------------------


def _attrs(mapping: dict[str, str]) -> str:
    return " ".join(f"{key}={quoteattr(value)}" for key, value in mapping.items())


def _title(doc: dict[str, Any], lang: str) -> str:
    title = doc["course"]["title"]
    return title.get(lang) or title.get(doc["course"]["base_lang"]) or doc["course"]["slug"]


def _description(doc: dict[str, Any], lang: str) -> str:
    desc = doc["course"].get("description") or {}
    return desc.get(lang) or desc.get(doc["course"]["base_lang"]) or ""


def _identifier(doc: dict[str, Any], standard: str, suffix: str = "") -> str:
    return f"SENTIENTIA-{doc['course']['slug']}-{SCD_NAME[standard]}{suffix}".replace("_", "-")


def manifest_scorm12(doc: dict[str, Any], files: list[str], lang: str, ident_suffix: str = "") -> str:
    mastery = doc["course"].get("mastery_score", 70)
    title = escape(_title(doc, lang))
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        f"<manifest identifier={quoteattr(_identifier(doc, 'scorm12', ident_suffix))} version=\"1.0\" {_attrs(NS_SCORM12)}>",
        "  <metadata>",
        "    <schema>ADL SCORM</schema>",
        "    <schemaversion>1.2</schemaversion>",
        "  </metadata>",
        '  <organizations default="ORG_01">',
        '    <organization identifier="ORG_01">',
        f"      <title>{title}</title>",
        '      <item identifier="ITEM_01" identifierref="RES_01" isvisible="true">',
        f"        <title>{title}</title>",
        f"        <adlcp:masteryscore>{int(mastery)}</adlcp:masteryscore>",
        "      </item>",
        "    </organization>",
        "  </organizations>",
        "  <resources>",
        '    <resource identifier="RES_01" type="webcontent" adlcp:scormtype="sco" href="index.html">',
    ]
    lines += [f"      <file href={quoteattr(name)}/>" for name in files]
    lines += ["    </resource>", "  </resources>", "</manifest>", ""]
    return "\n".join(lines)


def manifest_scorm2004(doc: dict[str, Any], files: list[str], lang: str, ident_suffix: str = "") -> str:
    mastery = doc["course"].get("mastery_score", 70)
    scaled = f"{mastery / 100:.2f}"
    title = escape(_title(doc, lang))
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        f"<manifest identifier={quoteattr(_identifier(doc, 'scorm2004', ident_suffix))} version=\"1\" {_attrs(NS_SCORM2004)}>",
        "  <metadata>",
        "    <schema>ADL SCORM</schema>",
        "    <schemaversion>2004 4th Edition</schemaversion>",
        "  </metadata>",
        '  <organizations default="ORG_01">',
        '    <organization identifier="ORG_01" structure="hierarchical">',
        f"      <title>{title}</title>",
        '      <item identifier="ITEM_01" identifierref="RES_01" isvisible="true">',
        f"        <title>{title}</title>",
        "        <imsss:sequencing>",
        "          <imsss:objectives>",
        '            <imsss:primaryObjective objectiveID="PRIMARY_OBJ" satisfiedByMeasure="true">',
        f"              <imsss:minNormalizedMeasure>{scaled}</imsss:minNormalizedMeasure>",
        "            </imsss:primaryObjective>",
        "          </imsss:objectives>",
        "        </imsss:sequencing>",
        "      </item>",
        "    </organization>",
        "  </organizations>",
        "  <resources>",
        '    <resource identifier="RES_01" type="webcontent" adlcp:scormType="sco" href="index.html">',
    ]
    lines += [f"      <file href={quoteattr(name)}/>" for name in files]
    lines += ["    </resource>", "  </resources>", "</manifest>", ""]
    return "\n".join(lines)


def _langstrings(texts: dict[str, str], indent: str) -> list[str]:
    return [f"{indent}<langstring lang={quoteattr(lang)}>{escape(text)}</langstring>" for lang, text in texts.items() if text]


def manifest_cmi5(doc: dict[str, Any], langs: list[str], ident_suffix: str = "") -> str:
    course = doc["course"]
    base_iri = (doc.get("delivery", {}).get("xapi", {}) or {}).get("activity_iri_base") or "urn:sentientia:activity:"
    course_iri = f"{base_iri}{course['slug']}{ident_suffix}"
    au_iri = f"{course_iri}/au1"
    mastery = course.get("mastery_score", 70) / 100
    titles = {lang: _title(doc, lang) for lang in langs}
    descriptions = {lang: _description(doc, lang) for lang in langs}
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        f"<courseStructure {_attrs(NS_CMI5)}>",
        f"  <course id={quoteattr(course_iri)}>",
        "    <title>",
        *_langstrings(titles, "      "),
        "    </title>",
        "    <description>",
        *_langstrings(descriptions, "      "),
        "    </description>",
        "  </course>",
        f'  <au id={quoteattr(au_iri)} moveOn="CompletedAndPassed" masteryScore="{mastery:.2f}" launchMethod="AnyWindow">',
        "    <title>",
        *_langstrings(titles, "      "),
        "    </title>",
        "    <description>",
        *_langstrings(descriptions, "      "),
        "    </description>",
        "    <url>index.html</url>",
        "  </au>",
        "</courseStructure>",
        "",
    ]
    return "\n".join(lines)


# -- player data -------------------------------------------------------------------------------------------


def _lang_entries(doc: dict[str, Any], carried: list[str]) -> list[dict[str, Any]]:
    course = doc["course"]
    rtl = course.get("rtl_langs") or ["ar", "ur", "he", "fa"]
    states = course.get("lang_states") or {}
    entries = []
    for lang in carried:
        state = (states.get(lang) or {}).get("state", "pending")
        entries.append(
            {
                "tag": lang,
                "name": ui_strings.LANG_NAMES.get(lang, lang),
                "dir": dir_for_lang(lang, rtl),
                "state": state,
            }
        )
    return entries


def course_data_js(doc: dict[str, Any], standard: str, carried: list[str], mode: str, preview: bool, vtt: dict[str, str]) -> str:
    course = doc["course"]
    data = {
        "standard": standard,
        "packageMode": mode,
        "courseId": course["slug"],
        "baseLang": course["base_lang"],
        "mastery": course.get("mastery_score", 70),
        "preview": preview,
        "rtl": course.get("rtl_langs") or ["ar", "ur", "he", "fa"],
        "langs": _lang_entries(doc, carried),
        "ui": {lang: ui_strings.ui_for(lang, course["base_lang"]) for lang in carried},
        "vtt": vtt,
        "doc": doc,
        "build": {"generator": HARNESS_NAME, "version": HARNESS_VERSION, "document_sha256": sha256_bytes(canonical_json(doc).encode("utf-8"))},
    }
    return "window.SENTIENTIA_COURSE = " + json.dumps(data, ensure_ascii=True, separators=(",", ":"), sort_keys=True) + ";\n"


def index_html(template: str, doc: dict[str, Any], standard: str, carried: list[str], mode: str) -> str:
    first = carried[0] if mode == "per_language" else (doc["course"]["base_lang"] if doc["course"]["base_lang"] in carried else carried[0])
    rtl = doc["course"].get("rtl_langs") or ["ar", "ur", "he", "fa"]
    replacements = {
        "{{LANG}}": html.escape(first, quote=True),
        "{{DIR}}": dir_for_lang(first, rtl),
        "{{STANDARD}}": standard,
        "{{PACKAGE_MODE}}": mode,
        "{{TITLE}}": html.escape(_title(doc, first)),
        "{{RUNTIME_SCRIPT}}": f"runtime-{standard}.js",
    }
    out = template
    for token, value in replacements.items():
        out = out.replace(token, value)
    if "{{" in out:
        raise HarnessError("index.html template still contains an unreplaced {{token}}")
    return out


# -- ZIP ---------------------------------------------------------------------------------------------------


def write_zip(path: Path, files: dict[str, bytes], first: tuple[str, ...]) -> None:
    """Deterministic ZIP: manifest entries first, then the rest sorted; fixed timestamp and attributes."""
    names = [n for n in first if n in files] + sorted(n for n in files if n not in first)
    path.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in names:
            info = zipfile.ZipInfo(name, date_time=ZIP_EPOCH)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o644 << 16
            archive.writestr(info, files[name], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


# -- the compiler --------------------------------------------------------------------------------------------


def compile_package(
    doc: dict[str, Any],
    i18n_pointers: list[str],
    standard: str,
    carried: list[str],
    *,
    player_dir: Path,
    media: dict[str, bytes],
    out_path: Path,
    mode: str = "multilingual",
    preview: bool = False,
    ident_suffix: str = "",
) -> BuiltPackage:
    """Compile one package. `media` maps relative package paths to bytes (mp3, vtt, words.json)."""
    if standard not in STANDARDS:
        raise HarnessError(f"unknown standard {standard!r}; expected one of {STANDARDS}")
    if not carried:
        raise HarnessError(f"no languages to carry in the {standard} package")
    restricted = restrict_document(doc, i18n_pointers, carried)
    refs = sorted({path for _, path in media_refs(restricted)})
    missing = [path for path in refs if path not in media]
    if missing:
        raise HarnessError(f"{len(missing)} referenced media file(s) are missing from the build: {missing[:5]}")

    files: dict[str, bytes] = {}
    for name in PLAYER_ASSETS + (f"runtime-{standard}.js",):
        source = player_dir / name
        if not source.is_file():
            raise HarnessError(f"player asset missing: {source}")
        files[name] = source.read_bytes()
    files["index.html"] = index_html((player_dir / "index.html").read_text(encoding="utf-8"), restricted, standard, carried, mode).encode("utf-8")
    vtt_text = {path: media[path].decode("utf-8") for path in refs if path.endswith(".vtt")}
    files["course-data.js"] = course_data_js(restricted, standard, carried, mode, preview, vtt_text).encode("ascii")
    files["course.json"] = (json.dumps(restricted, indent=2, ensure_ascii=False) + "\n").encode("utf-8")
    for path in refs:
        files[path] = media[path]

    lead_lang = carried[0] if mode == "per_language" else restricted["course"]["base_lang"]
    declared = sorted(files)
    if standard == "scorm12":
        manifest_name, manifest = "imsmanifest.xml", manifest_scorm12(restricted, declared, lead_lang, ident_suffix)
    elif standard == "scorm2004":
        manifest_name, manifest = "imsmanifest.xml", manifest_scorm2004(restricted, declared, lead_lang, ident_suffix)
    else:
        manifest_name, manifest = "cmi5.xml", manifest_cmi5(restricted, carried, ident_suffix)
    files[manifest_name] = manifest.encode("utf-8")

    write_zip(out_path, files, first=(manifest_name,))
    data = out_path.read_bytes()
    return BuiltPackage(
        standard=standard,
        path=out_path,
        langs=list(carried),
        mode=mode,
        manifest_name=manifest_name,
        size=len(data),
        sha256=sha256_bytes(data),
        files=[{"path": name, "bytes": len(files[name]), "sha256": sha256_bytes(files[name])} for name in sorted(files)],
    )


def carried_languages(doc: dict[str, Any], requested: list[str] | None, preview: bool) -> tuple[list[str], dict[str, str]]:
    """(languages to carry, {excluded language: reason}).

    Learner builds carry only approved languages (ADR-034 D4 / rule R09 in spirit); preview builds
    carry every requested language and label each with its review state in the selector.
    """
    course = doc["course"]
    declared = list(course["langs"])
    wanted = [l for l in (requested or declared) if l in declared]
    base = course["base_lang"]
    states = course.get("lang_states") or {}
    carried: list[str] = []
    excluded: dict[str, str] = {}
    for lang in wanted:
        state = (states.get(lang) or {}).get("state", "pending")
        if preview or state == "approved":
            carried.append(lang)
        else:
            excluded[lang] = f"state '{state}' is not approved; carried only in --preview builds"
    if base in carried:
        carried.remove(base)
        carried.insert(0, base)
    return carried, excluded
