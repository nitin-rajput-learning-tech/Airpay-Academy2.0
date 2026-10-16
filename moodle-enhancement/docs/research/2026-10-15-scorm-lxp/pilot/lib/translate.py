"""Translation fill for the canonical course document.

MOCK  (default) deterministic pseudo-localisation with zero network use. Every
      string a human already wrote is kept; every missing (unit, language) pair
      becomes "[xx] " + source. Protected terms stay verbatim (per-language
      override forms from course.term_overrides are applied) and placeholders
      such as {$a} are untouched because the source text is copied as-is.

LIVE  builds the exact Anthropic Messages API request (raw HTTP, urllib, no SDK
      because the harness is stdlib-only) and refuses to send unless every
      precondition holds: --mode live, --confirm, ANTHROPIC_API_KEY,
      PILOT_SPEND_CAP_INR, a clean PII scan and a pre-estimated cost under the
      cap. The HTTP call is implemented in LiveTranslator.send() and is never
      exercised by the pilot runs or the tests.

Price inputs (all overridable through the environment, all PLACEHOLDERS to be
confirmed against the current price list before a live run):
    ANTHROPIC_INPUT_USD_PER_MTOK   default 3.0   (claude-sonnet-4-6 list price, input)
    ANTHROPIC_OUTPUT_USD_PER_MTOK  default 15.0  (claude-sonnet-4-6 list price, output)
    USD_INR                        default 87.0
    ANTHROPIC_MAX_TOKENS           default 16000
    ANTHROPIC_MODEL                default claude-sonnet-4-6
"""
from __future__ import annotations

import json
import re
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from typing import Any, Mapping

from .common import HarnessError, resolve_pointer

API_URL = "https://api.anthropic.com/v1/messages"
API_VERSION = "2023-06-01"
DEFAULT_MODEL = "claude-sonnet-4-6"
PROMPT_VERSION = "pilot-translate/0.1"
PLACEHOLDER_RE = re.compile(r"\{\$?[A-Za-z0-9_.]+\}|%[sd]|\{\{[^}]+\}\}")
INDIC_ARABIC_RANGES = (
    (0x0600, 0x06FF), (0x0750, 0x077F), (0x0900, 0x0DFF), (0xFB50, 0xFDFF), (0xFE70, 0xFEFF),
)
MAX_SOURCE_CHARS_PER_REQUEST = 6000

PRICE_DEFAULTS = {
    "ANTHROPIC_INPUT_USD_PER_MTOK": 3.0,
    "ANTHROPIC_OUTPUT_USD_PER_MTOK": 15.0,
    "USD_INR": 87.0,
    "ANTHROPIC_MAX_TOKENS": 16000,
}
# Output-token multiplier per target script (PLACEHOLDER: Indic and Arabic scripts
# tokenise 2-3x heavier than Latin text; confirm with /v1/messages/count_tokens).
OUTPUT_FACTOR_LATIN = 1.2
OUTPUT_FACTOR_NON_LATIN = 2.5
NON_LATIN_TARGETS = {"hi", "mr", "kn", "ta", "te", "bn", "gu", "ml", "pa", "or", "as", "ur", "ar", "fa", "he", "ne", "sa"}


@dataclass(frozen=True)
class Unit:
    """One localisable string: the JSON Pointer of its i18n object and the base-language text."""

    pointer: str
    source: str


@dataclass
class FillResult:
    """What the fill step did, per language, plus per-unit provenance."""

    engine: str
    model: str
    prompt_version: str
    kept: dict[str, int] = field(default_factory=dict)
    filled: dict[str, int] = field(default_factory=dict)
    provenance: dict[str, dict[str, str]] = field(default_factory=dict)
    spend_inr: float = 0.0
    requests_built: int = 0

    def as_dict(self) -> dict[str, Any]:
        return {
            "engine": self.engine,
            "model": self.model,
            "prompt_version": self.prompt_version,
            "kept_human_units": dict(self.kept),
            "filled_machine_units": dict(self.filled),
            "spend_inr": self.spend_inr,
            "requests_built": self.requests_built,
        }


class LiveRefused(HarnessError):
    """Raised when the live path refuses to run; the message lists every unmet precondition."""


def collect_units(doc: dict[str, Any], i18n_pointers: list[str], base_lang: str) -> list[Unit]:
    """Units in document order; pointers whose object lacks the base language are skipped (R01 reports them)."""
    units: list[Unit] = []
    for pointer in i18n_pointers:
        try:
            obj = resolve_pointer(doc, pointer)
        except (KeyError, IndexError, ValueError, TypeError):
            continue
        if isinstance(obj, dict) and isinstance(obj.get(base_lang), str):
            units.append(Unit(pointer, obj[base_lang]))
    return units


def is_non_latin_target(lang: str) -> bool:
    return lang.split("-")[0] in NON_LATIN_TARGETS


def pseudo_localise(text: str, lang: str, protected_terms: list[str], overrides: Mapping[str, Mapping[str, str]]) -> str:
    """Deterministic pseudo-translation: language tag prefix + source with override forms applied."""
    out = text
    for term, form in (overrides.get(lang) or {}).items():
        if term in protected_terms and term in out:
            out = out.replace(term, form)
    return f"[{lang}] {out}"


def _target_langs(doc: dict[str, Any], langs: list[str] | None) -> list[str]:
    course = doc["course"]
    base = course["base_lang"]
    targets = [l for l in (langs or course["langs"]) if l != base]
    return targets


class MockTranslator:
    """Fills every missing (unit, language) pair with pseudo-localised text."""

    engine = "mock-pseudo"

    def fill(self, doc: dict[str, Any], units: list[Unit], langs: list[str] | None = None) -> FillResult:
        course = doc["course"]
        base = course["base_lang"]
        protected = list(course.get("protected_terms", []))
        overrides = course.get("term_overrides", {}) or {}
        result = FillResult(engine=self.engine, model="none", prompt_version=PROMPT_VERSION)
        targets = _target_langs(doc, langs)
        for unit in units:
            obj = resolve_pointer(doc, unit.pointer)
            result.provenance.setdefault(unit.pointer, {})[base] = "human"
            for lang in targets:
                if isinstance(obj.get(lang), str):
                    result.kept[lang] = result.kept.get(lang, 0) + 1
                    result.provenance[unit.pointer][lang] = "human"
                    continue
                obj[lang] = pseudo_localise(unit.source, lang, protected, overrides)
                result.filled[lang] = result.filled.get(lang, 0) + 1
                result.provenance[unit.pointer][lang] = "machine"
        _mark_lang_states(doc, targets, result, engine=self.engine)
        return result


def _mark_lang_states(doc: dict[str, Any], targets: list[str], result: FillResult, engine: str) -> None:
    """Languages without a human-owned state become 'machine'; existing human states are preserved."""
    states = doc["course"].setdefault("lang_states", {})
    for lang in targets:
        if result.filled.get(lang, 0) == 0:
            continue
        current = states.get(lang)
        if not isinstance(current, dict) or current.get("state") in (None, "pending", "machine"):
            states[lang] = {"state": "machine", "engine": engine}


def build_system_prompt(src: str, tgt: str, protected_terms: list[str], overrides: Mapping[str, str]) -> str:
    """Strict-JSON translation instructions listing every protected term."""
    term_lines = []
    for term in protected_terms:
        if term in overrides:
            term_lines.append(f'- "{term}" -> write exactly "{overrides[term]}"')
        else:
            term_lines.append(f'- "{term}" (keep exactly as written)')
    terms_block = "\n".join(term_lines) if term_lines else "- (none)"
    return (
        "You are a professional translator for corporate e-learning in India.\n"
        f"Translate every unit from '{src}' to '{tgt}'.\n"
        "Reply with ONLY a JSON object of the form "
        '{"translations":[{"id":"<unit id>","text":"<translation>"}]} '
        "covering every unit id exactly once. No prose, no markdown fences.\n"
        "Protected terms (never translate or transliterate unless an exact form is given):\n"
        f"{terms_block}\n"
        "Keep placeholders such as {$a}, {name}, %s and {{var}} byte-for-byte untouched.\n"
        "Keep numbers, quotation marks around dialogue, and the register of the source.\n"
        "Do not add, omit or summarise information."
    )


def chunk_units(units: list[Unit], max_chars: int = MAX_SOURCE_CHARS_PER_REQUEST) -> list[list[Unit]]:
    """Group units so each request carries at most `max_chars` of source text."""
    batches: list[list[Unit]] = []
    current: list[Unit] = []
    size = 0
    for unit in units:
        if current and size + len(unit.source) > max_chars:
            batches.append(current)
            current, size = [], 0
        current.append(unit)
        size += len(unit.source)
    if current:
        batches.append(current)
    return batches


class LiveTranslator:
    """Builds Messages API requests and (only when every gate is open) sends them with urllib."""

    engine = "anthropic-messages"

    def __init__(self, env: Mapping[str, str], *, mode: str, confirm: bool, pii_findings: int):
        self.env = env
        self.mode = mode
        self.confirm = confirm
        self.pii_findings = pii_findings
        self.model = env.get("ANTHROPIC_MODEL", DEFAULT_MODEL)
        self.max_tokens = int(env.get("ANTHROPIC_MAX_TOKENS", PRICE_DEFAULTS["ANTHROPIC_MAX_TOKENS"]))
        self.input_usd = float(env.get("ANTHROPIC_INPUT_USD_PER_MTOK", PRICE_DEFAULTS["ANTHROPIC_INPUT_USD_PER_MTOK"]))
        self.output_usd = float(env.get("ANTHROPIC_OUTPUT_USD_PER_MTOK", PRICE_DEFAULTS["ANTHROPIC_OUTPUT_USD_PER_MTOK"]))
        self.usd_inr = float(env.get("USD_INR", PRICE_DEFAULTS["USD_INR"]))
        cap = env.get("PILOT_SPEND_CAP_INR")
        self.cap_inr = float(cap) if cap not in (None, "") else None

    def build_request(self, units: list[Unit], src: str, tgt: str, protected_terms: list[str], overrides: Mapping[str, str]) -> dict[str, Any]:
        """The exact HTTP request: url, headers (key redacted in logs) and JSON body."""
        payload = {
            "source_lang": src,
            "target_lang": tgt,
            "units": [{"id": u.pointer, "text": u.source} for u in units],
        }
        body = {
            "model": self.model,
            "max_tokens": self.max_tokens,
            "system": build_system_prompt(src, tgt, protected_terms, overrides),
            "messages": [{"role": "user", "content": json.dumps(payload, ensure_ascii=False)}],
        }
        headers = {
            "x-api-key": self.env.get("ANTHROPIC_API_KEY", ""),
            "anthropic-version": API_VERSION,
            "content-type": "application/json",
        }
        return {"url": API_URL, "headers": headers, "body": body}

    def estimate_cost(self, units: list[Unit], targets: list[str], protected_terms: list[str], overrides: Mapping[str, Mapping[str, str]], src: str) -> dict[str, Any]:
        """chars/4 token estimate per request; output scaled per target script."""
        per_lang: dict[str, dict[str, float]] = {}
        total_in = total_out = 0.0
        requests = 0
        for tgt in targets:
            factor = OUTPUT_FACTOR_NON_LATIN if is_non_latin_target(tgt) else OUTPUT_FACTOR_LATIN
            lang_in = lang_out = 0.0
            for batch in chunk_units(units):
                request = self.build_request(batch, src, tgt, protected_terms, overrides.get(tgt, {}))
                prompt_chars = len(request["body"]["system"]) + len(request["body"]["messages"][0]["content"])
                source_chars = sum(len(u.source) for u in batch)
                lang_in += prompt_chars / 4
                lang_out += (source_chars * factor) / 4 + 40 * len(batch)  # 40 tokens of JSON scaffolding per unit
                requests += 1
            per_lang[tgt] = {"input_tokens": round(lang_in), "output_tokens": round(lang_out)}
            total_in += lang_in
            total_out += lang_out
        usd = total_in / 1e6 * self.input_usd + total_out / 1e6 * self.output_usd
        return {
            "model": self.model,
            "requests": requests,
            "input_tokens": round(total_in),
            "output_tokens": round(total_out),
            "usd": round(usd, 4),
            "inr": round(usd * self.usd_inr, 2),
            "cap_inr": self.cap_inr,
            "prices": {"input_usd_per_mtok": self.input_usd, "output_usd_per_mtok": self.output_usd, "usd_inr": self.usd_inr},
            "per_lang": per_lang,
        }

    def preconditions(self, estimate: dict[str, Any]) -> list[str]:
        """Every reason the live path must refuse (empty list = allowed)."""
        reasons: list[str] = []
        if self.mode != "live":
            reasons.append("--mode live is not set")
        if not self.confirm:
            reasons.append("--confirm is not set ([CONFIRM] is required for every live run)")
        if not self.env.get("ANTHROPIC_API_KEY"):
            reasons.append("ANTHROPIC_API_KEY is not set in the environment")
        if self.cap_inr is None:
            reasons.append("PILOT_SPEND_CAP_INR is not set in the environment")
        elif estimate["inr"] > self.cap_inr:
            reasons.append(f"estimated cost INR {estimate['inr']} exceeds PILOT_SPEND_CAP_INR {self.cap_inr}")
        if self.pii_findings:
            reasons.append(f"PII scan reported {self.pii_findings} finding(s); redact the source before any live model call")
        return reasons

    def fill(self, doc: dict[str, Any], units: list[Unit], langs: list[str] | None = None) -> FillResult:
        """Refuses unless every gate is open; otherwise translates batch by batch under the spend cap."""
        course = doc["course"]
        base = course["base_lang"]
        protected = list(course.get("protected_terms", []))
        overrides = course.get("term_overrides", {}) or {}
        targets = _target_langs(doc, langs)
        result = FillResult(engine=self.engine, model=self.model, prompt_version=PROMPT_VERSION)
        missing_by_lang = {
            tgt: [u for u in units if not isinstance(resolve_pointer(doc, u.pointer).get(tgt), str)] for tgt in targets
        }
        estimate = self._estimate_for(missing_by_lang, protected, overrides, base)
        reasons = self.preconditions(estimate)
        if reasons:
            raise LiveRefused("live translation refused:\n  - " + "\n  - ".join(reasons))
        for tgt, missing in missing_by_lang.items():
            kept = len(units) - len(missing)
            if kept:
                result.kept[tgt] = kept
            for batch in chunk_units(missing):
                request = self.build_request(batch, base, tgt, protected, overrides.get(tgt, {}))
                result.requests_built += 1
                response = self.send(request)
                translations = parse_translations(response, {u.pointer for u in batch})
                result.spend_inr += self._actual_cost_inr(response)
                if self.cap_inr is not None and result.spend_inr > self.cap_inr:
                    raise LiveRefused(f"spend INR {result.spend_inr:.2f} exceeded PILOT_SPEND_CAP_INR {self.cap_inr}; aborting")
                for unit in batch:
                    resolve_pointer(doc, unit.pointer)[tgt] = translations[unit.pointer]
                    result.provenance.setdefault(unit.pointer, {})[tgt] = "machine"
                    result.filled[tgt] = result.filled.get(tgt, 0) + 1
        _mark_lang_states(doc, targets, result, engine=self.model)
        return result

    def _estimate_for(self, missing_by_lang: dict[str, list[Unit]], protected: list[str], overrides: Mapping[str, Mapping[str, str]], src: str) -> dict[str, Any]:
        total = {"model": self.model, "requests": 0, "input_tokens": 0, "output_tokens": 0, "usd": 0.0, "inr": 0.0, "cap_inr": self.cap_inr, "per_lang": {}}
        for tgt, missing in missing_by_lang.items():
            part = self.estimate_cost(missing, [tgt], protected, overrides, src)
            total["requests"] += part["requests"]
            total["input_tokens"] += part["input_tokens"]
            total["output_tokens"] += part["output_tokens"]
            total["usd"] = round(total["usd"] + part["usd"], 4)
            total["inr"] = round(total["inr"] + part["inr"], 2)
            total["per_lang"][tgt] = part["per_lang"].get(tgt, {})
        total["prices"] = {"input_usd_per_mtok": self.input_usd, "output_usd_per_mtok": self.output_usd, "usd_inr": self.usd_inr}
        return total

    def _actual_cost_inr(self, response: dict[str, Any]) -> float:
        usage = response.get("usage", {})
        usd = usage.get("input_tokens", 0) / 1e6 * self.input_usd + usage.get("output_tokens", 0) / 1e6 * self.output_usd
        return usd * self.usd_inr

    def send(self, request: dict[str, Any]) -> dict[str, Any]:
        """POST the request with urllib and return the parsed JSON response.

        Not executed by the pilot runs or the tests: there is no key and no [CONFIRM].
        """
        data = json.dumps(request["body"], ensure_ascii=False).encode("utf-8")
        http_request = urllib.request.Request(request["url"], data=data, headers=request["headers"], method="POST")
        try:
            with urllib.request.urlopen(http_request, timeout=180) as handle:  # noqa: S310 - fixed https endpoint
                return json.loads(handle.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")[:500]
            raise HarnessError(f"Anthropic API returned HTTP {exc.code}: {detail}") from exc
        except urllib.error.URLError as exc:
            raise HarnessError(f"cannot reach the Anthropic API: {exc.reason}") from exc


def parse_translations(response: dict[str, Any], expected_ids: set[str]) -> dict[str, str]:
    """Extract {id: text} from a Messages API response, insisting on a complete strict-JSON answer."""
    if response.get("stop_reason") not in (None, "end_turn"):
        raise HarnessError(f"translation response stopped with {response.get('stop_reason')!r}; retry with a smaller batch")
    text = "".join(block.get("text", "") for block in response.get("content", []) if block.get("type") == "text").strip()
    if text.startswith("```"):
        text = text.strip("`")
        text = text[text.find("{"):]
    try:
        parsed = json.loads(text)
    except json.JSONDecodeError as exc:
        raise HarnessError(f"translation response is not strict JSON: {exc.msg}") from exc
    translations = {item["id"]: item["text"] for item in parsed.get("translations", []) if isinstance(item, dict)}
    missing = expected_ids - set(translations)
    if missing:
        raise HarnessError(f"translation response is missing {len(missing)} unit(s): {sorted(missing)[:5]}")
    return translations
