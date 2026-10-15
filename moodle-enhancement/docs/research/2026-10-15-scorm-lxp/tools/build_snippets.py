"""Extract study snippets from the cited research notes in sources/ (citation numbers get a note prefix).

Usage:  python -I build_snippets.py <research_dir>
Reads   <research_dir>/sources/R1..R5 notes, <research_dir>/matrix/*
Writes  <research_dir>/study-src/snippets/<NAME>.md for the machine-extracted snippets (hand-written snippets are left alone).
"""
import json
import re
import statistics
import sys
from pathlib import Path


def section(text: str, start: str, end: str | None) -> str:
    lines = text.splitlines()
    out, on = [], False
    for line in lines:
        if line.startswith(start):
            on = True
            continue
        if on and end and line.startswith(end):
            break
        if on:
            out.append(line)
    return "\n".join(out).strip()


def prefix(text: str, tag: str) -> str:
    return re.sub(r"\[(\d{1,3})\]", lambda m: f"[{tag}:{m.group(1)}]", text)


def vendor_paragraphs(block: str, stop: str | None) -> str:
    """For every '### ' heading keep the paragraphs up to `stop` (or the next heading)."""
    out, cur = [], []
    for line in block.splitlines():
        if line.startswith("### "):
            if cur:
                out.append("\n".join(cur).strip())
            cur = ["**" + re.sub(r"^### \d+\.\s*", "", line[4:]).strip() + "**"]
            continue
        if stop and line.startswith(stop):
            out.append("\n".join(cur).strip())
            cur = []
            continue
        if cur or not cur and False:
            cur.append(line)
    if cur:
        out.append("\n".join(cur).strip())
    return "\n\n".join(p for p in out if p)


def main(base: str) -> int:
    root = Path(base)
    src = root / "sources"
    snip = root / "study-src" / "snippets"
    snip.mkdir(parents=True, exist_ok=True)
    r1 = (src / "R1-vendors-report-five.md").read_text(encoding="utf-8")
    r2a = (src / "R2a-vendors-deep-set.md").read_text(encoding="utf-8")
    r2b = (src / "R2b-vendors-light-set.md").read_text(encoding="utf-8")
    r4 = (src / "R4-standards-compliance.md").read_text(encoding="utf-8")

    def w(name: str, body: str) -> None:
        (snip / f"{name}.md").write_text(body.strip() + "\n", encoding="utf-8", newline="\n")

    # -- R1 --
    para = section(r1, "## (a) One paragraph per vendor", "## (a2)")
    table = section(r1, "## (b) Summary table", "## (c)")
    w("R1_VENDOR_SUMMARY", prefix(para, "R1") + "\n\n**Summary table (sources in `sources/R1-vendors-report-five.md`)**\n\n" + prefix(table.replace("---\n", "").strip("- \n"), "R1"))
    claims = section(r1, "## (c) Claim-check table", "## (d)")
    w("R1_CLAIM_CHECK", prefix(claims.strip("- \n"), "R1"))

    # -- R2a / R2b --
    deep = vendor_paragraphs(section(r2a, "## (a) Summaries and capture sheets", "## (b)"), "Capture sheet:")
    deep_table = section(r2a, "## (b) Summary table", "## (c)")
    w("R2_DEEP_SUMMARY", prefix(deep, "R2a") + "\n\n**Summary table**\n\n" + prefix(deep_table, "R2a"))
    light = vendor_paragraphs(section(r2b, "## (a) Vendor paragraphs", "## (b)"), None)
    light_table = section(r2b, "## (b) Summary table", "## (c)")
    w("R2_LIGHT_SUMMARY", prefix(light, "R2b") + "\n\n**Summary table**\n\n" + prefix(light_table, "R2b"))
    w("R2_MARKET_DIRECTION", prefix(section(r2a, "## (d) Market direction (2026)", "## Could not verify"), "R2a"))

    # -- R4 --
    d2 = section(r4, "### D2. DPDP / RBI / CERT-In control checklist for the content pipeline", "### D3.")
    w("R4_CONTROL_CHECKLIST", d2)
    d3 = section(r4, "### D3. Open questions for counsel / CISO", "---")
    w("R4_OPEN_QUESTIONS", d3)

    # -- matrix totals (condensed) --
    crit = json.loads((root / "matrix" / "criteria.json").read_text(encoding="utf-8"))
    ven = json.loads((root / "matrix" / "vendors.json").read_text(encoding="utf-8"))
    clusters = crit["clusters"]
    cols = ven["columns"]

    def pts(col: str, members) -> float:
        if col.startswith("S:"):
            key = col[2:]
            return sum(float(c["sentientia"][key]) for c in members)
        return sum(float(ven["scores"][col][c["id"]][0]) for c in members)

    rows = ["| Cluster (weight) | Best vendor in the set | Median vendor | Sentientia today | after Stage 1 (target) | after Stage 3 (target) |", "|---|---|---|---|---|---|"]
    tot_w = {c: 0.0 for c in cols + ["S:today", "S:s1", "S:s3"]}
    tot_max = 0.0
    for cid, cl in clusters.items():
        members = [c for c in crit["criteria"] if c["cluster"] == cid]
        mx = 3.0 * len(members)
        pct = {c: 100.0 * pts(c, members) / mx for c in cols + ["S:today", "S:s1", "S:s3"]}
        for c in tot_w:
            tot_w[c] += pts(c, members) * cl["weight"]
        tot_max += mx * cl["weight"]
        best = max(cols, key=lambda c: pct[c])
        med = statistics.median(pct[c] for c in cols)
        rows.append(f"| {cid} {cl['name']} ({cl['weight']}) | {best} {pct[best]:.0f}% | {med:.0f}% | {pct['S:today']:.0f}% | {pct['S:s1']:.0f}% | {pct['S:s3']:.0f}% |")
    overall = {c: 100.0 * tot_w[c] / tot_max for c in tot_w}
    best = max(cols, key=lambda c: overall[c])
    rows.append(f"| **Weighted overall** | **{best} {overall[best]:.0f}%** | **{statistics.median(overall[c] for c in cols):.0f}%** | **{overall['S:today']:.0f}%** | **{overall['S:s1']:.0f}%** | **{overall['S:s3']:.0f}%** |")
    ranking = sorted(cols, key=lambda c: -overall[c])
    rank_line = ", ".join(f"{c} {overall[c]:.0f}%" for c in ranking)
    conf = ven["confidence"]
    lows = sum(1 for c in cols if conf[c] == "low")
    meds = sum(1 for c in cols if conf[c] == "medium")
    w("MATRIX_TOTALS", "\n".join(rows) + f"\n\nWeighted overall, all 20 vendors, highest first: {rank_line}. Evidence confidence recorded by the researchers: {lows} vendors low, {meds} medium, none high. The full 51-criterion by 23-column matrix, with a source note for every cell, is `matrix/capability-matrix.csv`; the per-vendor notes are in `matrix/vendor-scores/`.\n")
    (root / "matrix" / "overall.json").write_text(json.dumps({"overall": overall, "ranking": ranking}, indent=1), encoding="utf-8", newline="\n")
    print("snippets written to", snip)
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1]))
