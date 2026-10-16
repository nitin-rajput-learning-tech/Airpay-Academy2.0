"""Assemble STUDY.md from ordered section files and fill {{PLACEHOLDER}} snippets.

Usage:  python -I assemble_study.py <sections_dir> <snippets_dir> <out_file>

- Sections are concatenated in filename order (00-front.md, 01-method.md, ...).
- Every {{NAME}} token is replaced by the content of <snippets_dir>/NAME.md when
  that file exists; otherwise it is replaced by a visible marker so an
  unfinished placeholder can never hide in the deliverable.
- Prints the list of unresolved placeholders and exits 1 if any remain, so the
  build step fails loudly.
"""
import re
import sys
from pathlib import Path

TOKEN = re.compile(r"\{\{([A-Z0-9_]+)\}\}")


def main(sections_dir: str, snippets_dir: str, out_file: str) -> int:
    sections = sorted(Path(sections_dir).glob("*.md"))
    snippets = Path(snippets_dir)
    body = "\n".join(p.read_text(encoding="utf-8").rstrip() + "\n" for p in sections)

    unresolved: list[str] = []

    def repl(m: re.Match) -> str:
        name = m.group(1)
        snip = snippets / f"{name}.md"
        if snip.exists():
            return snip.read_text(encoding="utf-8").strip()
        unresolved.append(name)
        return f"> **[UNRESOLVED PLACEHOLDER: {name}]**"

    text = TOKEN.sub(repl, body)
    Path(out_file).write_text(text, encoding="utf-8", newline="\n")
    words = len(text.split())
    print(f"Wrote {out_file}: {len(sections)} sections, ~{words} words")
    if unresolved:
        print("Unresolved placeholders:", ", ".join(sorted(set(unresolved))))
        return 1
    return 0


if __name__ == "__main__":
    if len(sys.argv) != 4:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(sys.argv[1], sys.argv[2], sys.argv[3]))
