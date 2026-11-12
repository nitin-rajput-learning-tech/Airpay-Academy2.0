# Humanizer trial: one section of a decision brief

**Date:** 2026-11-12
**Skill:** `.claude/skills/humanizer/SKILL.md` (blader/humanizer at commit `b48c832f0f809c1b5658edbb838c2521b72f2413`, release 4.0.0)
**Text:** `moodle-enhancement/docs/cutover/NITIN-DECISION-BRIEF-2026-10-14.md`, section 5 ("After the Stage B rehearsal"), lines 43 to 49.
**The original file was not changed.** This page only shows a before and after for you to judge.

## Why this section

The brief's opening paragraph was read first. It has two lists of three (what is done; who must answer), but both
are real lists, and one sentence describes the page's own order. The skill treats each of those as too weak to act on
alone, so it would leave the paragraph almost as it is.

Section 5 has more to work on. It also has the most facts per line (three row counts, two internal ids, a finding
number), so it tests the main risk named in `PLAN.md`: that a rewrite loses or bends a fact.

## Before (verbatim)

```markdown
## 5. After the Stage B rehearsal (nothing to answer yet)

- **Q7** one written batch accepting the skipped-row counts. On the April copy these are
  `learningplan:orphan_course` 4, `ratings:orphan_item` 87 and `ratings:unknown_area` 195 (194 of them are
  vulnerability-scanner probe rows with no real user, F-41). Every skipped row stays in the BizLMS tables.
  **Recommendation: accept**, once Stage B confirms the same counts on the final backup.
- **Q8** only if Stage B shows programs or learning plans whose tenant was guessed from their creator.
```

## What the skill flagged

| Pattern (skill section) | Where | Action |
|---|---|---|
| §19 Bold as decoration: every list item opens with a bold label, and the recommendation carries its own bold label | `**Q7**`, `**Q8**`, `**Recommendation: accept**` | Bold removed in the rewrite. See question 1 below. |
| §11 Missing subject or verb (weak alone; here it comes with §19) | "Q7 one written batch accepting...", "Q8 only if Stage B shows..." | Each item is now a full sentence. |
| Not a listed pattern: an unclear reference | "194 of them" could mean 194 of all skipped rows or 194 of the 195 `unknown_area` rows | Made explicit: 194 of the 195. The 2026-11-12 decisions page (B1) confirms this reading. |
| §6 Forced triad | the three row counts | Kept. They are three real counts. |
| §10 Hyphenated pairs | "skipped-row counts", "vulnerability-scanner probe rows" | Kept. Hyphen before the noun is correct. |

## After

```markdown
## 5. After the Stage B rehearsal (nothing to answer yet)

- Q7 asks you to accept the skipped-row counts in one written batch. On the April copy they are
  `learningplan:orphan_course` 4, `ratings:orphan_item` 87 and `ratings:unknown_area` 195; 194 of the 195 are
  vulnerability-scanner probe rows with no real user (F-41). Every skipped row stays in the BizLMS tables.
  Recommended answer: accept, once Stage B confirms the same counts on the final backup.
- Q8 needs an answer only if Stage B shows programs or learning plans whose tenant was guessed from their creator.
```

## Fact check (step 3 of the skill)

| Fact in the original | In the rewrite |
|---|---|
| Q7 is one written batch accepting the skipped-row counts | Kept |
| `learningplan:orphan_course` 4, `ratings:orphan_item` 87, `ratings:unknown_area` 195 | Kept, same ids and numbers |
| 194 probe rows, no real user, finding F-41 | Kept; now says 194 of the 195 |
| Every skipped row stays in the BizLMS tables | Kept |
| Recommendation: accept, on condition that Stage B confirms the same counts on the final backup | Kept, same condition |
| Q8 arises only if Stage B shows programs or learning plans whose tenant was guessed from their creator | Kept |
| Heading: nothing to answer yet | Kept |

No fact, number, id or condition was dropped or changed. The only change in meaning is the "194 of them" reading,
which is now explicit.

## Assessment

- **What it improved.** Two terse fragments became sentences, and one unclear reference became exact. The section is
  about as long as before.
- **What it found little of.** Our briefs are already written plainly, so most of the skill's 26 patterns (contrasts,
  closers, sales words, dashes) did not appear. Older documents, the Product Guide in particular, are a better test of
  how much it saves.
- **Where it pulls against our house style.** The skill removes bold labels. In a decision brief, the bold
  "Recommendation" label is how you find the answer quickly. Taking it out of one section makes that section differ
  from the rest of the brief.

## Questions for you

1. Should decision briefs keep the bold Q-number and the bold "Recommendation" label even when the humanizer runs? If
   yes, that becomes one line in the skill's local-scope note.
2. Is the "after" text acceptable as it stands, without another rewrite? That is the Stage 1 measure in `PLAN.md`.
