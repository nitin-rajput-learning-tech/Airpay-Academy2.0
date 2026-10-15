"""Shared helpers for the Sentientia pilot harness.

Standard library only. Everything here is either pure or plain file I/O.
"""
from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path
from typing import Any, Iterator

LANG_RE = re.compile(r"^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$")
ID_RE = re.compile(r"^[A-Za-z0-9_-]{1,64}$")
DEFAULT_RTL_LANGS: tuple[str, ...] = ("ar", "ur", "he", "fa")
HARNESS_NAME = "sentientia-pilot-harness"
HARNESS_VERSION = "0.1.0"


class HarnessError(Exception):
    """A fatal, user-facing harness error; printed without a traceback."""


def configure_utf8_stdio() -> None:
    """Switch stdout/stderr to UTF-8 (guarded; Windows consoles default to cp1252)."""
    for name in ("stdout", "stderr"):
        stream = getattr(sys, name, None)
        reconfigure = getattr(stream, "reconfigure", None)
        if reconfigure is None:
            continue
        try:
            reconfigure(encoding="utf-8", errors="replace")
        except (ValueError, OSError):
            pass


def sha256_bytes(data: bytes) -> str:
    """Hex sha256 of a byte string."""
    return hashlib.sha256(data).hexdigest()


def sha256_text(text: str) -> str:
    """Hex sha256 of a string encoded as UTF-8."""
    return sha256_bytes(text.encode("utf-8"))


def sha256_file(path: Path) -> str:
    """Hex sha256 of a file, streamed."""
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()


def canonical_json(obj: Any) -> str:
    """Deterministic, ASCII-only, minified JSON (used for hashing)."""
    return json.dumps(obj, sort_keys=True, ensure_ascii=True, separators=(",", ":"))


def pretty_json(obj: Any) -> str:
    """Readable JSON with a trailing newline; Unicode kept as-is."""
    return json.dumps(obj, indent=2, ensure_ascii=False) + "\n"


def read_json(path: Path) -> Any:
    """Read a UTF-8 JSON file, raising HarnessError with a precise message on failure."""
    try:
        text = path.read_text(encoding="utf-8")
    except OSError as exc:
        raise HarnessError(f"cannot read {path}: {exc}") from exc
    try:
        return json.loads(text)
    except json.JSONDecodeError as exc:
        raise HarnessError(
            f"{path} is not valid JSON: line {exc.lineno} column {exc.colno}: {exc.msg}"
        ) from exc


def write_text(path: Path, text: str) -> None:
    """Write UTF-8 text with LF newlines, creating parent directories."""
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8", newline="\n")


def write_bytes(path: Path, data: bytes) -> None:
    """Write bytes, creating parent directories."""
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def write_json(path: Path, obj: Any) -> None:
    """Write pretty JSON, creating parent directories."""
    write_text(path, pretty_json(obj))


def pointer_escape(token: str) -> str:
    """Escape one JSON Pointer reference token (RFC 6901)."""
    return token.replace("~", "~0").replace("/", "~1")


def pointer_unescape(token: str) -> str:
    """Unescape one JSON Pointer reference token (RFC 6901)."""
    return token.replace("~1", "/").replace("~0", "~")


def join_pointer(base: str, token: Any) -> str:
    """Append one reference token to a JSON Pointer."""
    return f"{base}/{pointer_escape(str(token))}"


def resolve_pointer(doc: Any, pointer: str) -> Any:
    """Return the value a JSON Pointer addresses (raises KeyError/IndexError when absent)."""
    node = doc
    if pointer == "":
        return node
    for raw in pointer.split("/")[1:]:
        key = pointer_unescape(raw)
        if isinstance(node, list):
            node = node[int(key)]
        else:
            node = node[key]
    return node


def walk_strings(obj: Any, pointer: str = "") -> Iterator[tuple[str, str]]:
    """Yield (json_pointer, string) for every string leaf in document order."""
    if isinstance(obj, str):
        yield pointer, obj
    elif isinstance(obj, dict):
        for key, value in obj.items():
            yield from walk_strings(value, join_pointer(pointer, key))
    elif isinstance(obj, list):
        for index, value in enumerate(obj):
            yield from walk_strings(value, join_pointer(pointer, index))


def dir_for_lang(lang: str, rtl_langs: list[str] | tuple[str, ...]) -> str:
    """'rtl' when the language (or its primary subtag) is declared right-to-left, else 'ltr'."""
    primary = lang.split("-")[0]
    return "rtl" if lang in rtl_langs or primary in rtl_langs else "ltr"


def parse_csv_option(value: str) -> list[str]:
    """Split a comma-separated CLI option, dropping blanks and duplicates (order kept)."""
    seen: list[str] = []
    for part in value.split(","):
        item = part.strip()
        if item and item not in seen:
            seen.append(item)
    return seen
