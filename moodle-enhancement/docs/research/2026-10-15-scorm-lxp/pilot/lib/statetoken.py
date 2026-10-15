"""Compact suspend-state token: the Python mirror of lib/player/core.js.

The player (core.js) and this module implement the same grammar; test_harness.py
cross-checks the two encoders when Node is available.

Grammar (version v1, every section separated by "|"):

    token     := "v1" "|" lang "|" blockIndex "|" bitmask "|" scenarios "|" quizzes
    bitmask   := base64url(bytes) without padding; bit i = byte[i >> 3] & (1 << (i & 7))
    scenarios := scenario (";" scenario)* | ""
    scenario  := scenarioId ":" edgeId ("," edgeId)*
    quizzes   := quiz (";" quiz)* | ""
    quiz      := quizId ["!"] "=" answer ("," answer)*       ("!" = submitted)
    answer    := questionId ":" typeChar value
    typeChar  := "c" multichoice | "m" multiresponse | "t" truefalse | "x" match
    value     := optionId                      (c, t)
               | optionId ("+" optionId)*      (m)
               | leftId "." rightId ("+" ...)* (x)

Ids are URL-safe ([A-Za-z0-9_-]) by schema rule R10, so the delimiters never
collide with content. A token starting with "z:" is the deflate-raw compressed
form of a plain token, base64url-encoded without padding.
"""
from __future__ import annotations

import base64
import zlib
from typing import Any

TOKEN_VERSION = "v1"
COMPRESSED_PREFIX = "z:"
SCORM12_LIMIT = 4096
SCORM12_TARGET = 4000
SCORM12_PRECOMPRESS_BUDGET = 3500
SCORM2004_LIMIT = 64000


def bitmask_encode(visited: list[bool]) -> str:
    """Encode a list of booleans as base64url bytes, LSB-first within each byte."""
    if not visited:
        return ""
    raw = bytearray((len(visited) + 7) // 8)
    for index, flag in enumerate(visited):
        if flag:
            raw[index >> 3] |= 1 << (index & 7)
    return base64.urlsafe_b64encode(bytes(raw)).decode("ascii").rstrip("=")


def bitmask_decode(text: str, count: int) -> list[bool]:
    """Decode a bitmask produced by bitmask_encode into exactly `count` booleans."""
    if not text:
        return [False] * count
    padded = text + "=" * (-len(text) % 4)
    raw = base64.urlsafe_b64decode(padded.encode("ascii"))
    return [bool(raw[i >> 3] & (1 << (i & 7))) if (i >> 3) < len(raw) else False for i in range(count)]


def encode_answer(answer: dict[str, Any]) -> str:
    """Encode one answer {"type": c|m|t|x, "value": ...} to its compact value form."""
    kind = answer["type"]
    value = answer["value"]
    if kind in ("c", "t"):
        return kind + str(value)
    if kind == "m":
        return kind + "+".join(str(v) for v in value)
    if kind == "x":
        return kind + "+".join(f"{left}.{right}" for left, right in value)
    raise ValueError(f"unknown answer type {kind!r}")


def decode_answer(text: str) -> dict[str, Any]:
    """Inverse of encode_answer."""
    kind, body = text[:1], text[1:]
    if kind in ("c", "t"):
        return {"type": kind, "value": body}
    if kind == "m":
        return {"type": kind, "value": [v for v in body.split("+") if v]}
    if kind == "x":
        pairs = []
        for item in body.split("+"):
            if item:
                left, _, right = item.partition(".")
                pairs.append([left, right])
        return {"type": kind, "value": pairs}
    raise ValueError(f"unknown answer type {kind!r}")


def encode_scenarios(scenarios: dict[str, list[str]]) -> str:
    """Encode {scenarioId: [edgeIds]} (scenarios with an empty path are omitted)."""
    parts = [f"{sid}:{','.join(path)}" for sid, path in scenarios.items() if path]
    return ";".join(parts)


def encode_quizzes(quizzes: dict[str, dict[str, Any]]) -> str:
    """Encode {quizId: {"submitted": bool, "answers": {questionId: answer}}}."""
    parts = []
    for qid, quiz in quizzes.items():
        answers = quiz.get("answers", {})
        submitted = bool(quiz.get("submitted"))
        if not answers and not submitted:
            continue
        body = ",".join(f"{question}:{encode_answer(answer)}" for question, answer in answers.items())
        parts.append(f"{qid}{'!' if submitted else ''}={body}")
    return ";".join(parts)


def encode_state(state: dict[str, Any]) -> str:
    """Encode a state dict into the plain v1 token."""
    return "|".join(
        [
            TOKEN_VERSION,
            str(state.get("lang", "")),
            str(int(state.get("block_index", 0))),
            bitmask_encode(list(state.get("visited", []))),
            encode_scenarios(state.get("scenarios", {})),
            encode_quizzes(state.get("quizzes", {})),
        ]
    )


def decode_state(token: str, block_count: int) -> dict[str, Any]:
    """Decode a plain v1 token (raises ValueError for compressed or malformed tokens)."""
    if token.startswith(COMPRESSED_PREFIX):
        raise ValueError("token is compressed; call decompress_token first")
    parts = token.split("|")
    if len(parts) != 6 or parts[0] != TOKEN_VERSION:
        raise ValueError("malformed state token")
    scenarios: dict[str, list[str]] = {}
    for entry in parts[4].split(";"):
        if entry:
            sid, _, path = entry.partition(":")
            scenarios[sid] = [e for e in path.split(",") if e]
    quizzes: dict[str, dict[str, Any]] = {}
    for entry in parts[5].split(";"):
        if not entry:
            continue
        head, _, body = entry.partition("=")
        submitted = head.endswith("!")
        qid = head[:-1] if submitted else head
        answers: dict[str, Any] = {}
        for item in body.split(","):
            if item:
                question, _, value = item.partition(":")
                answers[question] = decode_answer(value)
        quizzes[qid] = {"submitted": submitted, "answers": answers}
    return {
        "lang": parts[1],
        "block_index": int(parts[2] or 0),
        "visited": bitmask_decode(parts[3], block_count),
        "scenarios": scenarios,
        "quizzes": quizzes,
    }


def compress_token(token: str) -> str:
    """deflate-raw + base64url (no padding) with the "z:" prefix; mirrors CompressionStream('deflate-raw')."""
    compressor = zlib.compressobj(level=9, wbits=-15)
    data = compressor.compress(token.encode("utf-8")) + compressor.flush()
    return COMPRESSED_PREFIX + base64.urlsafe_b64encode(data).decode("ascii").rstrip("=")


def decompress_token(token: str) -> str:
    """Inverse of compress_token (plain tokens are returned unchanged)."""
    if not token.startswith(COMPRESSED_PREFIX):
        return token
    body = token[len(COMPRESSED_PREFIX):]
    raw = base64.urlsafe_b64decode((body + "=" * (-len(body) % 4)).encode("ascii"))
    return zlib.decompress(raw, wbits=-15).decode("utf-8")


def _path_length(edge_ids: list[str]) -> int:
    return sum(len(e) for e in edge_ids) + max(0, len(edge_ids) - 1)


def longest_path_edges(block: dict[str, Any]) -> list[str]:
    """Edge ids of the path from `start` to a terminal with the longest token footprint.

    Info nodes are followed through `next` without adding an edge. Cycles are cut at
    the first repeated node so the function terminates on any graph.
    """
    nodes = {node["id"]: node for node in block.get("nodes", [])}
    memo: dict[str, list[str]] = {}

    def best(node_id: str, on_stack: set[str]) -> list[str]:
        if node_id in memo:
            return memo[node_id]
        node = nodes.get(node_id)
        if node is None or node_id in on_stack:
            return []
        on_stack.add(node_id)
        candidate: list[str] = []
        for edge in node.get("edges", []) or []:
            path = [edge["id"]] + best(edge["to"], on_stack)
            if _path_length(path) > _path_length(candidate):
                candidate = path
        if node.get("next"):
            path = best(node["next"], on_stack)
            if _path_length(path) > _path_length(candidate):
                candidate = path
        on_stack.discard(node_id)
        memo[node_id] = candidate
        return candidate

    return best(block.get("start", ""), set())


def worst_case_answer(question: dict[str, Any]) -> dict[str, Any]:
    """The longest compact answer a learner could produce for one question."""
    kind = question.get("type")
    option_ids = [str(o.get("id", "")) for o in question.get("options", []) or []]
    pair_ids = [str(p.get("id", "")) for p in question.get("pairs", []) or []]
    longest_option = max(option_ids, key=len, default="")
    longest_pair = max(pair_ids, key=len, default="")
    if kind == "multichoice":
        return {"type": "c", "value": longest_option}
    if kind == "truefalse":
        return {"type": "t", "value": longest_option}
    if kind == "multiresponse":
        return {"type": "m", "value": option_ids}
    if kind == "match":
        return {"type": "x", "value": [[pid, longest_pair] for pid in pair_ids]}
    raise ValueError(f"unknown question type {kind!r}")


def worst_case_state(doc: dict[str, Any], langs: list[str]) -> dict[str, Any]:
    """Build the heaviest state the compiled document can produce."""
    blocks = doc.get("blocks", [])
    longest_lang = max(langs, key=len, default=str(doc.get("course", {}).get("base_lang", "en")))
    scenarios: dict[str, list[str]] = {}
    quizzes: dict[str, dict[str, Any]] = {}
    for block in blocks:
        if block.get("type") == "scenario":
            scenarios[block["id"]] = longest_path_edges(block)
        elif block.get("type") == "quiz":
            answers = {q["id"]: worst_case_answer(q) for q in block.get("questions", [])}
            quizzes[block["id"]] = {"submitted": True, "answers": answers}
    return {
        "lang": longest_lang,
        "block_index": max(0, len(blocks) - 1),
        "visited": [True] * len(blocks),
        "scenarios": scenarios,
        "quizzes": quizzes,
    }


def worst_case_report(doc: dict[str, Any], langs: list[str]) -> dict[str, Any]:
    """Worst-case token length and its breakdown, compared with every budget."""
    state = worst_case_state(doc, langs)
    token = encode_state(state)
    compressed = compress_token(token)
    scenario_nodes = {
        block["id"]: len(block.get("nodes", []))
        for block in doc.get("blocks", [])
        if block.get("type") == "scenario"
    }
    return {
        "token_length": len(token),
        "compressed_length": len(compressed),
        "sections": {
            "lang": state["lang"],
            "block_index": state["block_index"],
            "bitmask": bitmask_encode(state["visited"]),
            "scenarios": encode_scenarios(state["scenarios"]),
            "quizzes": encode_quizzes(state["quizzes"]),
        },
        "longest_scenario_paths": {sid: path for sid, path in state["scenarios"].items()},
        "scenario_nodes": scenario_nodes,
        "budgets": {
            "scorm12_precompress_budget": SCORM12_PRECOMPRESS_BUDGET,
            "scorm12_target": SCORM12_TARGET,
            "scorm12_limit": SCORM12_LIMIT,
            "scorm2004_limit": SCORM2004_LIMIT,
        },
        "fits_scorm12_precompress": len(token) <= SCORM12_PRECOMPRESS_BUDGET,
        "fits_scorm12_limit": len(token) <= SCORM12_LIMIT,
        "fits_scorm2004": len(token) <= SCORM2004_LIMIT,
        "token": token,
    }
