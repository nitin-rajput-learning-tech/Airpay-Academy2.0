"""Build the capability matrix CSV and the weighted cluster-totals table.

Usage:  python -I build_matrix.py <criteria.json> <vendors.json> <out.csv> <totals.md>

criteria.json: {"clusters": {...}, "criteria": [{"id", "cluster", "criterion",
                "sentientia": {"today", "s1", "s3", "note"}}]}
vendors.json:  {"columns": ["KREDO", ...],
                "scores": {"KREDO": {"A1": [score, "note"], ...}, ...}}
Missing vendor cells are written as empty and scored 0 in totals, so an
unscored vendor cannot look better than it is.
"""
import csv
import json
import sys
from pathlib import Path


def load(path: str) -> dict:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def main(criteria_path: str, vendors_path: str, out_csv: str, totals_md: str) -> int:
    crit = load(criteria_path)
    vend = load(vendors_path)
    clusters = crit["clusters"]
    columns = list(vend["columns"]) + ["Sentientia today", "Sentientia after Stage 1", "Sentientia after Stage 3"]

    rows = []
    for c in crit["criteria"]:
        row = {"id": c["id"], "cluster": c["cluster"], "cluster_name": clusters[c["cluster"]]["name"],
               "weight": clusters[c["cluster"]]["weight"], "criterion": c["criterion"]}
        for v in vend["columns"]:
            cell = vend["scores"].get(v, {}).get(c["id"])
            row[v] = "" if cell is None else cell[0]
            row[v + " (note)"] = "" if cell is None else cell[1]
        s = c["sentientia"]
        row["Sentientia today"] = s["today"]
        row["Sentientia after Stage 1"] = s["s1"]
        row["Sentientia after Stage 3"] = s["s3"]
        row["Sentientia (note)"] = s["note"]
        rows.append(row)

    fieldnames = list(rows[0].keys())
    with open(out_csv, "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames)
        w.writeheader()
        w.writerows(rows)

    # Weighted totals per cluster and overall, as % of maximum (3 per criterion).
    def score_of(col: str, c: dict) -> float:
        if col.startswith("Sentientia"):
            key = {"Sentientia today": "today", "Sentientia after Stage 1": "s1", "Sentientia after Stage 3": "s3"}[col]
            return float(c["sentientia"][key])
        cell = vend["scores"].get(col, {}).get(c["id"])
        return float(cell[0]) if cell else 0.0

    lines = ["| Cluster (weight) | " + " | ".join(columns) + " |",
             "|---|" + "|".join(["---"] * len(columns)) + "|"]
    overall = {col: [0.0, 0.0] for col in columns}
    for cid, cl in clusters.items():
        members = [c for c in crit["criteria"] if c["cluster"] == cid]
        maxpts = 3.0 * len(members)
        cells = []
        for col in columns:
            pts = sum(score_of(col, c) for c in members)
            pct = 100.0 * pts / maxpts if maxpts else 0.0
            overall[col][0] += pts * cl["weight"]
            overall[col][1] += maxpts * cl["weight"]
            cells.append(f"{pct:.0f}%")
        lines.append(f"| {cid} {cl['name']} ({cl['weight']}) | " + " | ".join(cells) + " |")
    lines.append("| **Weighted overall** | " + " | ".join(
        f"**{100.0 * v[0] / v[1]:.0f}%**" if v[1] else "n/a" for v in overall.values()) + " |")
    Path(totals_md).write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
    print(f"Wrote {out_csv} ({len(rows)} rows x {len(columns)} columns) and {totals_md}")
    return 0


if __name__ == "__main__":
    if len(sys.argv) != 5:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(*sys.argv[1:]))
