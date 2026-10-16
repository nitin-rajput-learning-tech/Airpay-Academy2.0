"""Merge the three research-agent score files into matrix/vendors.json (column order and short names fixed here).

Usage:  python -I merge_scores.py <matrix_dir>
Reads   <matrix_dir>/vendor-scores/R1-scores.json, R2a-scores.json, R2b-scores.json
Writes  <matrix_dir>/vendors.json   {"columns": [...], "tiers": {...}, "confidence": {...}, "scores": {...}}
"""
import json
import sys
from pathlib import Path

ORDER = [
    ("R1", "KREDO/Prodient", "KREDO/Prodient", "deep (report five)"),
    ("R1", "Tenneo", "Tenneo", "deep (report five)"),
    ("R1", "Invince", "Invince", "deep (report five)"),
    ("R1", "Mindsmith", "Mindsmith", "deep (report five)"),
    ("R1", "Coursebox", "Coursebox", "deep (report five)"),
    ("R2a", "Docebo", "Docebo", "deep"),
    ("R2a", "360Learning", "360Learning", "deep"),
    ("R2a", "Moodle Workplace", "Moodle Workplace", "deep"),
    ("R2a", "Sana", "Sana", "deep"),
    ("R2a", "Articulate 360", "Articulate", "deep"),
    ("R2b", "Cornerstone", "Cornerstone", "light"),
    ("R2b", "Degreed", "Degreed", "light"),
    ("R2b", "SAP SuccessFactors Learning", "SuccessFactors", "light"),
    ("R2b", "Workday Learning", "Workday Learning", "light"),
    ("R2b", "LinkedIn Learning Hub", "LinkedIn Learning", "light"),
    ("R2b", "Absorb LMS", "Absorb", "light"),
    ("R2b", "TalentLMS", "TalentLMS", "light"),
    ("R2b", "Elucidat (Create by Confirm)", "Elucidat/Create", "light"),
    ("R2b", "Synthesia", "Synthesia", "light"),
    ("R2b", "7taps", "7taps", "light"),
]


def main(matrix_dir: str) -> int:
    base = Path(matrix_dir)
    files = {k: json.loads((base / "vendor-scores" / f"{k}-scores.json").read_text(encoding="utf-8"))["vendors"] for k in ("R1", "R2a", "R2b")}
    out = {"columns": [], "tiers": {}, "confidence": {}, "scores": {}}
    for src, key, short, tier in ORDER:
        entry = files[src][key]
        out["columns"].append(short)
        out["tiers"][short] = tier
        out["confidence"][short] = entry["confidence"]
        out["scores"][short] = entry["scores"]
    (base / "vendors.json").write_text(json.dumps(out, indent=1, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")
    print(f"Wrote {base / 'vendors.json'}: {len(out['columns'])} vendors")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1]))
