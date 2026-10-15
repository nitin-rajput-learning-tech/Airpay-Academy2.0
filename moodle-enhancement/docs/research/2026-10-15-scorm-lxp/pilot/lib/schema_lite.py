"""schema_lite: a JSON Schema (2020-12) subset validator plus the SCD x-rules R01-R12.

Supported keywords: type, const, enum, pattern, format (uuid, date-time, uri),
minimum, maximum, minItems, maxItems, uniqueItems, minProperties, required,
properties, patternProperties, additionalProperties, items, $ref (local only) and
oneOf. When every oneOf alternative declares a `type` const the instance's own
`type` value picks the alternative (discriminator dispatch); otherwise exactly one
alternative must validate.

The x-rules are implemented as explicit functions so every failure carries the
rule id, the JSON pointer and a precise message.
"""
from __future__ import annotations

import re
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Any, Callable

from . import statetoken
from .common import DEFAULT_RTL_LANGS, ID_RE, canonical_json, join_pointer, resolve_pointer, sha256_file

UUID_RE = re.compile(r"^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$")
DATETIME_RE = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$")
URI_RE = re.compile(r"^[A-Za-z][A-Za-z0-9+.-]*:\S+$")
PLACEHOLDER_SHA = "0" * 64

RULE_IDS = tuple(f"R{n:02d}" for n in range(1, 13))
MAX_BULLET_WORDS = 8
SCORM12_MAX_SCENARIO_NODES = 60
TRUEFALSE_ID_CONVENTION = ("t", "f", "true", "false", "0", "1")


@dataclass
class Issue:
    """One validation finding."""

    level: str  # error | warning | info
    rule: str  # schema | R01..R12 | langs | source | slides
    pointer: str
    message: str

    def as_dict(self) -> dict[str, str]:
        return asdict(self)

    def __str__(self) -> str:
        return f"[{self.level.upper()}] {self.rule} {self.pointer}: {self.message}"


@dataclass
class ValidationResult:
    """Everything the harness learns from validating one document."""

    issues: list[Issue] = field(default_factory=list)
    i18n_pointers: list[str] = field(default_factory=list)
    pending: dict[str, int] = field(default_factory=dict)
    state_budget: dict[str, Any] = field(default_factory=dict)
    rule_status: dict[str, str] = field(default_factory=dict)

    @property
    def errors(self) -> list[Issue]:
        return [i for i in self.issues if i.level == "error"]

    @property
    def warnings(self) -> list[Issue]:
        return [i for i in self.issues if i.level == "warning"]

    @property
    def ok(self) -> bool:
        return not self.errors

    def add(self, level: str, rule: str, pointer: str, message: str) -> None:
        self.issues.append(Issue(level, rule, pointer or "/", message))

    def by_rule(self, rule: str) -> list[Issue]:
        return [i for i in self.issues if i.rule == rule]

    def as_dict(self) -> dict[str, Any]:
        return {
            "ok": self.ok,
            "error_count": len(self.errors),
            "warning_count": len(self.warnings),
            "rule_status": dict(self.rule_status),
            "pending_units_per_lang": dict(self.pending),
            "issues": [i.as_dict() for i in self.issues],
        }


def _describe(value: Any) -> str:
    if isinstance(value, bool):
        return "boolean"
    if value is None:
        return "null"
    if isinstance(value, int):
        return "integer"
    if isinstance(value, float):
        return "number"
    if isinstance(value, str):
        return f"string {value[:40]!r}" if len(value) > 40 else f"string {value!r}"
    if isinstance(value, list):
        return f"array[{len(value)}]"
    if isinstance(value, dict):
        return f"object{{{', '.join(list(value)[:6])}}}"
    return type(value).__name__


def _is_type(value: Any, kind: str) -> bool:
    if kind == "object":
        return isinstance(value, dict)
    if kind == "array":
        return isinstance(value, list)
    if kind == "string":
        return isinstance(value, str)
    if kind == "integer":
        return isinstance(value, int) and not isinstance(value, bool)
    if kind == "number":
        return isinstance(value, (int, float)) and not isinstance(value, bool)
    if kind == "boolean":
        return isinstance(value, bool)
    if kind == "null":
        return value is None
    raise ValueError(f"unsupported schema type {kind!r}")


class _Ctx:
    __slots__ = ("issues", "i18n_pointers")

    def __init__(self) -> None:
        self.issues: list[Issue] = []
        self.i18n_pointers: list[str] = []


class SchemaLite:
    """Validate an instance against the JSON Schema subset described in the module docstring."""

    def __init__(self, schema: dict[str, Any]):
        if not isinstance(schema, dict):
            raise ValueError("schema must be a JSON object")
        self.schema = schema

    def resolve(self, ref: str) -> dict[str, Any]:
        """Resolve a local `#/...` reference."""
        if not ref.startswith("#/"):
            raise ValueError(f"unsupported $ref {ref!r}: only local '#/' references are supported")
        node: Any = self.schema
        for raw in ref[2:].split("/"):
            key = raw.replace("~1", "/").replace("~0", "~")
            if not isinstance(node, dict) or key not in node:
                raise ValueError(f"unresolvable $ref {ref!r}")
            node = node[key]
        if not isinstance(node, dict):
            raise ValueError(f"$ref {ref!r} does not point at a schema object")
        return node

    def validate(self, instance: Any) -> tuple[list[Issue], list[str]]:
        """Return (issues, json pointers of every i18n object encountered)."""
        ctx = _Ctx()
        self._validate(instance, self.schema, "", ctx)
        return ctx.issues, ctx.i18n_pointers

    # -- keyword handlers -------------------------------------------------

    def _err(self, ctx: _Ctx, pointer: str, message: str) -> None:
        ctx.issues.append(Issue("error", "schema", pointer or "/", message))

    def _validate(self, inst: Any, sub: dict[str, Any], ptr: str, ctx: _Ctx) -> None:
        if "$ref" in sub:
            ref = sub["$ref"]
            if ref == "#/$defs/i18n":
                ctx.i18n_pointers.append(ptr)
            self._validate(inst, self.resolve(ref), ptr, ctx)
            siblings = {k: v for k, v in sub.items() if k not in ("$ref", "description", "default")}
            if siblings:
                self._validate(inst, siblings, ptr, ctx)
            return
        if "type" in sub and not self._check_type(inst, sub["type"], ptr, ctx):
            return
        if "const" in sub and inst != sub["const"]:
            self._err(ctx, ptr, f"must equal {sub['const']!r}, got {_describe(inst)}")
        if "enum" in sub and inst not in sub["enum"]:
            self._err(ctx, ptr, f"must be one of {sub['enum']}, got {_describe(inst)}")
        if isinstance(inst, str):
            self._check_string(inst, sub, ptr, ctx)
        if isinstance(inst, (int, float)) and not isinstance(inst, bool):
            if "minimum" in sub and inst < sub["minimum"]:
                self._err(ctx, ptr, f"must be >= {sub['minimum']}, got {inst}")
            if "maximum" in sub and inst > sub["maximum"]:
                self._err(ctx, ptr, f"must be <= {sub['maximum']}, got {inst}")
        if isinstance(inst, list):
            self._check_array(inst, sub, ptr, ctx)
        if isinstance(inst, dict):
            self._check_object(inst, sub, ptr, ctx)
        if "oneOf" in sub:
            self._check_one_of(inst, sub["oneOf"], ptr, ctx)

    def _check_type(self, inst: Any, kind: Any, ptr: str, ctx: _Ctx) -> bool:
        kinds = kind if isinstance(kind, list) else [kind]
        if any(_is_type(inst, k) for k in kinds):
            return True
        self._err(ctx, ptr, f"expected {' or '.join(kinds)}, got {_describe(inst)}")
        return False

    def _check_string(self, inst: str, sub: dict[str, Any], ptr: str, ctx: _Ctx) -> None:
        pattern = sub.get("pattern")
        if pattern and not re.search(pattern, inst):
            self._err(ctx, ptr, f"{inst!r} does not match pattern {pattern}")
        fmt = sub.get("format")
        if fmt == "uuid" and not UUID_RE.match(inst):
            self._err(ctx, ptr, f"{inst!r} is not a UUID")
        elif fmt == "date-time" and not DATETIME_RE.match(inst):
            self._err(ctx, ptr, f"{inst!r} is not an RFC 3339 date-time (e.g. 2026-10-15T06:30:00Z)")
        elif fmt == "uri" and not URI_RE.match(inst):
            self._err(ctx, ptr, f"{inst!r} is not an absolute URI")

    def _check_array(self, inst: list[Any], sub: dict[str, Any], ptr: str, ctx: _Ctx) -> None:
        if "minItems" in sub and len(inst) < sub["minItems"]:
            self._err(ctx, ptr, f"must have at least {sub['minItems']} item(s), has {len(inst)}")
        if "maxItems" in sub and len(inst) > sub["maxItems"]:
            self._err(ctx, ptr, f"must have at most {sub['maxItems']} item(s), has {len(inst)}")
        if sub.get("uniqueItems"):
            seen: dict[str, int] = {}
            for index, item in enumerate(inst):
                key = canonical_json(item)
                if key in seen:
                    self._err(ctx, join_pointer(ptr, index), f"duplicate item {item!r} (first at index {seen[key]})")
                else:
                    seen[key] = index
        if "items" in sub and isinstance(sub["items"], dict):
            for index, item in enumerate(inst):
                self._validate(item, sub["items"], join_pointer(ptr, index), ctx)

    def _check_object(self, inst: dict[str, Any], sub: dict[str, Any], ptr: str, ctx: _Ctx) -> None:
        if "minProperties" in sub and len(inst) < sub["minProperties"]:
            self._err(ctx, ptr, f"must have at least {sub['minProperties']} propert(y/ies), has {len(inst)}")
        for key in sub.get("required", []):
            if key not in inst:
                self._err(ctx, ptr, f"missing required property '{key}'")
        props = sub.get("properties", {})
        patterns = sub.get("patternProperties", {})
        for key, value in inst.items():
            child = join_pointer(ptr, key)
            matched = False
            if key in props:
                matched = True
                self._validate(value, props[key], child, ctx)
            for pattern, pschema in patterns.items():
                if re.search(pattern, key):
                    matched = True
                    self._validate(value, pschema, child, ctx)
            if not matched and sub.get("additionalProperties") is False:
                self._err(ctx, child, f"unexpected property '{key}' (allowed: {', '.join(props) or 'pattern keys only'})")

    def _check_one_of(self, inst: Any, alternatives: list[dict[str, Any]], ptr: str, ctx: _Ctx) -> None:
        resolved = [self.resolve(a["$ref"]) if "$ref" in a else a for a in alternatives]
        discriminators = [
            a.get("properties", {}).get("type", {}).get("const") for a in resolved
        ]
        if all(isinstance(d, str) for d in discriminators):
            if not isinstance(inst, dict):
                self._err(ctx, ptr, f"expected an object with a 'type' discriminator, got {_describe(inst)}")
                return
            kind = inst.get("type")
            if kind not in discriminators:
                self._err(ctx, ptr, f"unknown type {kind!r}; expected one of {discriminators}")
                return
            self._validate(inst, resolved[discriminators.index(kind)], ptr, ctx)
            return
        passing = []
        for index, alt in enumerate(resolved):
            trial = _Ctx()
            self._validate(inst, alt, ptr, trial)
            if not trial.issues:
                passing.append(index)
        if len(passing) != 1:
            self._err(ctx, ptr, f"must match exactly one alternative, matched {len(passing)}")


# -- document-level helpers ------------------------------------------------------


def _course(doc: dict[str, Any]) -> dict[str, Any]:
    course = doc.get("course")
    if not isinstance(course, dict):
        raise ValueError("document has no 'course' object")
    return course


def _blocks(doc: dict[str, Any]) -> list[dict[str, Any]]:
    blocks = doc.get("blocks")
    if not isinstance(blocks, list):
        raise ValueError("document has no 'blocks' array")
    return [b for b in blocks if isinstance(b, dict)]


def i18n_objects(doc: dict[str, Any], pointers: list[str]) -> list[tuple[str, dict[str, Any]]]:
    """(pointer, object) for every recorded i18n pointer that resolves to an object."""
    out = []
    for pointer in pointers:
        try:
            obj = resolve_pointer(doc, pointer)
        except (KeyError, IndexError, ValueError, TypeError):
            continue
        if isinstance(obj, dict):
            out.append((pointer, obj))
    return out


def media_refs(doc: dict[str, Any]) -> list[tuple[str, str]]:
    """(pointer, relative path) for every media reference the document carries."""
    refs: list[tuple[str, str]] = []

    def add_media_ref(pointer: str, ref: Any) -> None:
        if isinstance(ref, dict):
            if isinstance(ref.get("path"), str):
                refs.append((join_pointer(pointer, "path"), ref["path"]))
            if isinstance(ref.get("word_timestamps"), str):
                refs.append((join_pointer(pointer, "word_timestamps"), ref["word_timestamps"]))

    def add_set(pointer: str, media_set: Any, is_captions: bool) -> None:
        if not isinstance(media_set, dict):
            return
        for lang, ref in media_set.items():
            child = join_pointer(pointer, lang)
            if is_captions:
                if isinstance(ref, str):
                    refs.append((child, ref))
            else:
                add_media_ref(child, ref)

    for bi, block in enumerate(_blocks(doc)):
        bp = f"/blocks/{bi}"
        add_media_ref(join_pointer(bp, "image"), block.get("image"))
        add_set(join_pointer(bp, "audio"), block.get("audio"), False)
        add_set(join_pointer(bp, "media"), block.get("media"), False)
        add_set(join_pointer(bp, "captions"), block.get("captions"), True)
        for ci, character in enumerate(block.get("characters", []) or []):
            if isinstance(character, dict):
                add_media_ref(f"{bp}/characters/{ci}/avatar", character.get("avatar"))
        for ni, node in enumerate(block.get("nodes", []) or []):
            if isinstance(node, dict):
                add_set(f"{bp}/nodes/{ni}/audio", node.get("audio"), False)
                add_set(f"{bp}/nodes/{ni}/captions", node.get("captions"), True)
    return refs


# -- x-rules -------------------------------------------------------------------


def _rule_r01(doc: dict[str, Any], res: ValidationResult) -> None:
    course = _course(doc)
    base = course["base_lang"]
    langs = [l for l in course.get("langs", []) if isinstance(l, str)]
    for pointer, obj in i18n_objects(doc, res.i18n_pointers):
        if base not in obj:
            res.add("error", "R01", pointer, f"i18n object is missing the base language '{base}' (present: {sorted(obj)})")
        for lang in langs:
            if lang not in obj:
                res.pending[lang] = res.pending.get(lang, 0) + 1
    for lang in langs:
        res.pending.setdefault(lang, 0)
    pending = {k: v for k, v in res.pending.items() if v}
    if pending:
        res.add("info", "R01", "/course/langs", f"units pending translation per language: {pending}")


def _rule_r02(doc: dict[str, Any], res: ValidationResult) -> None:
    course = _course(doc)
    base = course.get("base_lang")
    langs = course.get("langs", [])
    if base not in langs:
        res.add("error", "R02", "/course/langs", f"must include base_lang '{base}'")
    duplicates = sorted({l for l in langs if langs.count(l) > 1})
    if duplicates:
        res.add("error", "R02", "/course/langs", f"duplicate language tags: {duplicates}")


def _rule_r03(doc: dict[str, Any], res: ValidationResult) -> None:
    course = _course(doc)
    rtl = course.get("rtl_langs", list(DEFAULT_RTL_LANGS))
    declared_rtl = [l for l in course.get("langs", []) if l in rtl or l.split("-")[0] in rtl]
    res.add("info", "R03", "/course/rtl_langs", f"dir=\"rtl\" will be emitted for: {declared_rtl or 'none'}")


def _rule_r04(doc: dict[str, Any], res: ValidationResult) -> None:
    for bi, block in enumerate(_blocks(doc)):
        if block.get("type") != "scenario":
            continue
        bp = f"/blocks/{bi}"
        nodes_list = [n for n in block.get("nodes", []) if isinstance(n, dict)]
        nodes = {n.get("id"): n for n in nodes_list}
        start = block.get("start")
        if start not in nodes:
            res.add("error", "R04", f"{bp}/start", f"start node '{start}' does not exist in this scenario")
        adjacency: dict[str, list[str]] = {}
        terminals: list[str] = []
        for ni, node in enumerate(nodes_list):
            np_ = f"{bp}/nodes/{ni}"
            nid = node.get("id")
            kind = node.get("kind")
            edges = node.get("edges", []) or []
            targets: list[str] = []
            for ei, edge in enumerate(edges):
                target = edge.get("to") if isinstance(edge, dict) else None
                if target not in nodes:
                    res.add("error", "R04", f"{np_}/edges/{ei}/to", f"edge '{edge.get('id') if isinstance(edge, dict) else ei}' points at unknown node '{target}'")
                else:
                    targets.append(target)
            if kind == "decision" and len(edges) < 2:
                res.add("error", "R04", f"{np_}/edges", f"decision node '{nid}' must have >= 2 edges, has {len(edges)}")
            if kind == "terminal":
                terminals.append(nid)
                if edges:
                    res.add("error", "R04", f"{np_}/edges", f"terminal node '{nid}' must have 0 edges, has {len(edges)}")
                if node.get("next"):
                    res.add("error", "R04", f"{np_}/next", f"terminal node '{nid}' must not declare 'next'")
                if not node.get("terminal_outcome"):
                    res.add("error", "R04", f"{np_}/terminal_outcome", f"terminal node '{nid}' requires terminal_outcome (success|partial|failure)")
            if kind == "info":
                nxt = node.get("next")
                if not nxt:
                    res.add("error", "R04", f"{np_}/next", f"info node '{nid}' must declare the single following node in 'next'")
                elif nxt not in nodes:
                    res.add("error", "R04", f"{np_}/next", f"info node '{nid}' continues to unknown node '{nxt}'")
                else:
                    targets.append(nxt)
                if edges:
                    res.add("error", "R04", f"{np_}/edges", f"info node '{nid}' must use 'next', not edges")
            if kind == "decision" and node.get("next"):
                res.add("error", "R04", f"{np_}/next", f"decision node '{nid}' must branch through edges, not 'next'")
            adjacency[nid] = targets
        if not terminals:
            res.add("error", "R04", f"{bp}/nodes", "scenario has no terminal node")
        if start not in nodes:
            continue
        reachable = _bfs(start, adjacency)
        for nid in nodes:
            if nid not in reachable:
                res.add("error", "R04", f"{bp}/nodes", f"node '{nid}' is not reachable from start '{start}'")
        reverse: dict[str, list[str]] = {nid: [] for nid in nodes}
        for src, targets in adjacency.items():
            for target in targets:
                reverse.setdefault(target, []).append(src)
        can_finish: set[str] = set()
        for terminal in terminals:
            can_finish |= _bfs(terminal, reverse)
        for nid in nodes:
            if nid not in can_finish:
                res.add("error", "R04", f"{bp}/nodes", f"no path from node '{nid}' reaches a terminal node")
        cycle = _find_cycle(start, adjacency)
        if cycle and not block.get("allow_cycles", False):
            res.add("error", "R04", f"{bp}/nodes", f"cycle detected ({' -> '.join(cycle)}); set allow_cycles=true to permit it")
        elif cycle:
            res.add("warning", "R04", f"{bp}/nodes", f"cycle permitted by allow_cycles: {' -> '.join(cycle)}")


def _bfs(start: str, adjacency: dict[str, list[str]]) -> set[str]:
    seen = {start}
    queue = [start]
    while queue:
        current = queue.pop(0)
        for target in adjacency.get(current, []):
            if target not in seen:
                seen.add(target)
                queue.append(target)
    return seen


def _find_cycle(start: str, adjacency: dict[str, list[str]]) -> list[str]:
    colour: dict[str, int] = {}
    stack: list[str] = []

    def visit(node: str) -> list[str]:
        colour[node] = 1
        stack.append(node)
        for target in adjacency.get(node, []):
            state = colour.get(target, 0)
            if state == 1:
                return stack[stack.index(target):] + [target]
            if state == 0:
                found = visit(target)
                if found:
                    return found
        stack.pop()
        colour[node] = 2
        return []

    return visit(start)


def _rule_r05(doc: dict[str, Any], res: ValidationResult, standards: tuple[str, ...], langs: list[str]) -> None:
    report = statetoken.worst_case_report(doc, langs)
    res.state_budget = {k: v for k, v in report.items() if k != "token"}
    wants_scorm12 = "scorm12" in standards
    level = "error" if wants_scorm12 else "warning"
    for sid, count in report["scenario_nodes"].items():
        if count > SCORM12_MAX_SCENARIO_NODES:
            res.add(level, "R05", f"/blocks", f"scenario '{sid}' has {count} nodes > {SCORM12_MAX_SCENARIO_NODES}; rejected for SCORM 1.2 output")
    length = report["token_length"]
    budget = report["budgets"]["scorm12_precompress_budget"]
    if length > budget:
        res.add(level, "R05", "/blocks", f"worst-case suspend_data token is {length} chars > {budget} pre-compression budget for SCORM 1.2")
    res.add(
        "info",
        "R05",
        "/blocks",
        f"worst-case suspend_data token: {length} chars plain ({report['compressed_length']} compressed); "
        f"budgets 3,500 pre-compression / 4,096 SCORM 1.2 / 64,000 SCORM 2004",
    )


def _rule_r06(doc: dict[str, Any], res: ValidationResult) -> None:
    for bi, block in enumerate(_blocks(doc)):
        if block.get("type") != "quiz":
            continue
        for qi, question in enumerate(block.get("questions", []) or []):
            if not isinstance(question, dict):
                continue
            qp = f"/blocks/{bi}/questions/{qi}"
            kind = question.get("type")
            options = [o for o in (question.get("options") or []) if isinstance(o, dict)]
            pairs = [p for p in (question.get("pairs") or []) if isinstance(p, dict)]
            correct = sum(1 for o in options if o.get("correct") is True)
            option_ids = [o.get("id") for o in options]
            for dup in sorted({i for i in option_ids if option_ids.count(i) > 1}):
                res.add("error", "R06", f"{qp}/options", f"option id '{dup}' is not unique within question '{question.get('id')}'")
            pair_ids = [p.get("id") for p in pairs]
            for dup in sorted({i for i in pair_ids if pair_ids.count(i) > 1}):
                res.add("error", "R06", f"{qp}/pairs", f"pair id '{dup}' is not unique within question '{question.get('id')}'")
            if kind == "multichoice":
                if len(options) < 2:
                    res.add("error", "R06", f"{qp}/options", "multichoice needs >= 2 options")
                if correct != 1:
                    res.add("error", "R06", f"{qp}/options", f"multichoice must have exactly one correct option, has {correct}")
            elif kind == "multiresponse":
                if len(options) < 2:
                    res.add("error", "R06", f"{qp}/options", "multiresponse needs >= 2 options")
                if correct < 1:
                    res.add("error", "R06", f"{qp}/options", "multiresponse must have >= 1 correct option, has 0")
            elif kind == "truefalse":
                if len(options) != 2:
                    res.add("error", "R06", f"{qp}/options", f"truefalse must have exactly 2 options, has {len(options)}")
                if correct != 1:
                    res.add("error", "R06", f"{qp}/options", f"truefalse must have exactly one correct option, has {correct}")
                for oid in option_ids:
                    if isinstance(oid, str) and oid.lower() not in TRUEFALSE_ID_CONVENTION:
                        res.add("warning", "R06", f"{qp}/options", f"truefalse option id '{oid}' is written as-is to cmi.interactions; SCORM expects t/f (1.2) or true/false (2004)")
            elif kind == "match":
                if len(pairs) < 2:
                    res.add("error", "R06", f"{qp}/pairs", f"match needs >= 2 pairs, has {len(pairs)}")
                if options:
                    res.add("warning", "R06", f"{qp}/options", "match questions use 'pairs'; 'options' are ignored")
            if kind in ("multichoice", "multiresponse", "truefalse") and not options:
                res.add("error", "R06", f"{qp}/options", f"{kind} question '{question.get('id')}' has no options")


def _rule_r07(doc: dict[str, Any], res: ValidationResult) -> None:
    course = _course(doc)
    base = course["base_lang"]
    terms = [t for t in course.get("protected_terms", []) if isinstance(t, str) and t]
    overrides = course.get("term_overrides", {}) or {}
    checked = 0
    for pointer, obj in i18n_objects(doc, res.i18n_pointers):
        source = obj.get(base)
        if not isinstance(source, str):
            continue
        present = [t for t in terms if t in source]
        for lang, text in obj.items():
            if lang == base or not isinstance(text, str):
                continue
            checked += 1
            for term in present:
                expected = (overrides.get(lang) or {}).get(term, term)
                if expected not in text:
                    detail = f" (expected the {lang} override form '{expected}')" if expected != term else ""
                    res.add("error", "R07", f"{pointer}/{lang}", f"protected term '{term}' from the base text is missing{detail}")
    res.add("info", "R07", "/course/protected_terms", f"{len(terms)} protected terms checked across {checked} translations")


def _relative_path_ok(path: str) -> bool:
    if not path or path.startswith(("/", "\\")) or "\\" in path or ".." in path.split("/"):
        return False
    return not re.match(r"^[A-Za-z]:", path)


def _rule_r08(doc: dict[str, Any], res: ValidationResult, media_root: Path | None, media_known: set[str]) -> None:
    refs = media_refs(doc)
    for pointer, path in refs:
        if not _relative_path_ok(path):
            res.add("error", "R08", pointer, f"media path {path!r} must be a relative path inside the package (no leading slash, drive letter or '..')")
            continue
        if path in media_known:
            continue
        if media_root is not None and (media_root / path).is_file():
            continue
        res.add("error", "R08", pointer, f"media file {path!r} not found (looked in {media_root or 'the compiled media set'})")
    res.add("info", "R08", "/blocks", f"{len(refs)} media references checked")


def _rule_r09(doc: dict[str, Any], res: ValidationResult, preview: bool) -> None:
    review = doc.get("provenance", {}).get("review", {}) if isinstance(doc.get("provenance"), dict) else {}
    state = review.get("state") if isinstance(review, dict) else None
    pointer = "/provenance/review/state"
    if state in ("approved", "published"):
        return
    if state in ("draft", "in_review"):
        if preview:
            res.add("warning", "R09", pointer, f"review state is '{state}'; compiling a PREVIEW build only (--preview). Do not deploy to learners")
        else:
            res.add("error", "R09", pointer, f"review state is '{state}'; approve the document or re-run with --preview for a non-learner build")
        return
    res.add("error", "R09", pointer, f"review state '{state}' cannot be compiled (needs approved or published)")


def _rule_r10(doc: dict[str, Any], res: ValidationResult) -> None:
    seen: dict[str, str] = {}

    def register(value: Any, pointer: str, kind: str) -> None:
        if not isinstance(value, str) or not ID_RE.match(value):
            res.add("error", "R10", pointer, f"{kind} id {value!r} must match [A-Za-z0-9_-]{{1,64}}")
            return
        if value in seen:
            res.add("error", "R10", pointer, f"{kind} id '{value}' is already used at {seen[value]}; ids must be unique across the document")
            return
        seen[value] = pointer

    def check_pattern_only(value: Any, pointer: str, kind: str) -> None:
        if not isinstance(value, str) or not ID_RE.match(value):
            res.add("error", "R10", pointer, f"{kind} id {value!r} must match [A-Za-z0-9_-]{{1,64}}")

    for bi, block in enumerate(_blocks(doc)):
        bp = f"/blocks/{bi}"
        register(block.get("id"), f"{bp}/id", "block")
        characters = {c.get("id") for c in (block.get("characters") or []) if isinstance(c, dict)}
        for ci, character in enumerate(block.get("characters") or []):
            if isinstance(character, dict):
                register(character.get("id"), f"{bp}/characters/{ci}/id", "character")
        for ni, node in enumerate(block.get("nodes") or []):
            if not isinstance(node, dict):
                continue
            register(node.get("id"), f"{bp}/nodes/{ni}/id", "node")
            if node.get("character") and node["character"] not in characters:
                res.add("error", "R10", f"{bp}/nodes/{ni}/character", f"character '{node['character']}' is not declared in this scenario")
            for ei, edge in enumerate(node.get("edges") or []):
                if isinstance(edge, dict):
                    register(edge.get("id"), f"{bp}/nodes/{ni}/edges/{ei}/id", "edge")
        for ri, rubric in enumerate(block.get("rubric") or []):
            if isinstance(rubric, dict):
                register(rubric.get("id"), f"{bp}/rubric/{ri}/id", "rubric")
        for qi, question in enumerate(block.get("questions") or []):
            if not isinstance(question, dict):
                continue
            register(question.get("id"), f"{bp}/questions/{qi}/id", "question")
            for oi, option in enumerate(question.get("options") or []):
                if isinstance(option, dict):
                    check_pattern_only(option.get("id"), f"{bp}/questions/{qi}/options/{oi}/id", "option")
            for pi, pair in enumerate(question.get("pairs") or []):
                if isinstance(pair, dict):
                    check_pattern_only(pair.get("id"), f"{bp}/questions/{qi}/pairs/{pi}/id", "pair")
    res.add("info", "R10", "/blocks", f"{len(seen)} document-unique ids (option and pair ids are scoped to their question per R06)")


def _rule_r11(doc: dict[str, Any], res: ValidationResult) -> None:
    longest = 0
    for bi, block in enumerate(_blocks(doc)):
        bp = f"/blocks/{bi}"
        if block.get("type") == "quiz":
            for qi, question in enumerate(block.get("questions") or []):
                if not isinstance(question, dict) or question.get("type") not in ("multichoice", "multiresponse", "truefalse", "match"):
                    continue
                answer = statetoken.worst_case_answer(question)
                if answer["type"] == "m":
                    response = "[,]".join(answer["value"])
                elif answer["type"] == "x":
                    response = "[,]".join(f"{l}[.]{r}" for l, r in answer["value"])
                else:
                    response = str(answer["value"])
                longest = max(longest, len(response))
                if len(response) > 255:
                    res.add("error", "R11", f"{bp}/questions/{qi}", f"worst-case student_response is {len(response)} chars > 255 (use shorter option ids)")
        if block.get("type") == "scenario":
            for ni, node in enumerate(block.get("nodes") or []):
                for ei, edge in enumerate((node.get("edges") or []) if isinstance(node, dict) else []):
                    if isinstance(edge, dict) and isinstance(edge.get("id"), str):
                        longest = max(longest, len(edge["id"]))
    res.add("info", "R11", "/blocks", f"longest possible student_response is {longest} chars (limit 255); responses carry ids only")


def _rule_r12(doc: dict[str, Any], res: ValidationResult) -> None:
    source = _course(doc).get("source", {})
    status = source.get("pii_scan", {}).get("status") if isinstance(source, dict) else None
    pointer = "/course/source/pii_scan/status"
    if status == "blocked":
        res.add("error", "R12", pointer, "source PII scan is 'blocked'; generation halts until the source is redacted")
    elif status not in ("clean", "redacted"):
        res.add("error", "R12", pointer, f"source PII scan status {status!r} must be 'clean' or 'redacted' before any live model call")


def _check_slides(doc: dict[str, Any], res: ValidationResult) -> None:
    base = _course(doc)["base_lang"]
    for bi, block in enumerate(_blocks(doc)):
        if block.get("type") != "slide":
            continue
        for pi, point in enumerate(block.get("points") or []):
            text = point.get(base) if isinstance(point, dict) else None
            if isinstance(text, str) and len(text.split()) > MAX_BULLET_WORDS:
                res.add("warning", "slides", f"/blocks/{bi}/points/{pi}", f"bullet has {len(text.split())} words > {MAX_BULLET_WORDS} recommended")


def _check_langs(doc: dict[str, Any], res: ValidationResult, langs: list[str] | None) -> None:
    declared = _course(doc).get("langs", [])
    for lang in langs or []:
        if lang not in declared:
            res.add("error", "langs", "/course/langs", f"requested language '{lang}' is not declared in course.langs {declared}")


def _check_source(doc: dict[str, Any], res: ValidationResult, source_path: Path | None) -> None:
    source = _course(doc).get("source", {})
    declared = source.get("sha256") if isinstance(source, dict) else None
    if declared == PLACEHOLDER_SHA:
        res.add("warning", "source", "/course/source/sha256", "sha256 is the all-zero placeholder; record the real source hash before approval")
    if source_path is not None and source_path.is_file() and isinstance(declared, str) and declared != PLACEHOLDER_SHA:
        actual = sha256_file(source_path)
        if actual != declared:
            res.add("warning", "source", "/course/source/sha256", f"declared sha256 does not match {source_path.name} ({actual[:12]}...)")


def validate_document(
    doc: Any,
    schema: dict[str, Any],
    *,
    preview: bool = False,
    standards: tuple[str, ...] = ("scorm12", "scorm2004", "cmi5"),
    langs: list[str] | None = None,
    media_root: Path | None = None,
    media_known: set[str] | None = None,
    source_path: Path | None = None,
) -> ValidationResult:
    """Structural validation followed by every x-rule; never raises for a bad document."""
    res = ValidationResult()
    if not isinstance(doc, dict):
        res.add("error", "schema", "/", f"document root must be an object, got {_describe(doc)}")
        return res
    issues, pointers = SchemaLite(schema).validate(doc)
    res.issues.extend(issues)
    res.i18n_pointers = pointers
    requested = list(langs) if langs else None

    def effective_langs() -> list[str]:
        declared = [l for l in _course(doc).get("langs", []) if isinstance(l, str)]
        return requested or declared

    rules: list[tuple[str, Callable[[], None]]] = [
        ("R01", lambda: _rule_r01(doc, res)),
        ("R02", lambda: _rule_r02(doc, res)),
        ("R03", lambda: _rule_r03(doc, res)),
        ("R04", lambda: _rule_r04(doc, res)),
        ("R05", lambda: _rule_r05(doc, res, standards, effective_langs())),
        ("R06", lambda: _rule_r06(doc, res)),
        ("R07", lambda: _rule_r07(doc, res)),
        ("R08", lambda: _rule_r08(doc, res, media_root, media_known or set())),
        ("R09", lambda: _rule_r09(doc, res, preview)),
        ("R10", lambda: _rule_r10(doc, res)),
        ("R11", lambda: _rule_r11(doc, res)),
        ("R12", lambda: _rule_r12(doc, res)),
        ("slides", lambda: _check_slides(doc, res)),
        ("langs", lambda: _check_langs(doc, res, requested)),
        ("source", lambda: _check_source(doc, res, source_path)),
    ]
    for name, func in rules:
        try:
            func()
        except Exception as exc:  # noqa: BLE001 - a rule must report, never crash the run
            res.add("error", name, "/", f"rule could not be evaluated because of structural errors: {type(exc).__name__}: {exc}")
    for rule in RULE_IDS:
        levels = {i.level for i in res.by_rule(rule)}
        res.rule_status[rule] = "fail" if "error" in levels else ("warn" if "warning" in levels else "pass")
    res.rule_status["schema"] = "fail" if any(i.rule == "schema" for i in res.errors) else "pass"
    return res
