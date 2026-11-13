# DRAFT upstream bug report: privacy contextlist iteration stops at duplicate context ids

**Status: draft, NOT filed.** Filing on the public Moodle tracker is Nitin's call. It contains no data from
Sentientia or Airpay; the reproduction is synthetic.

Found 2026-11-13 by the A4 local erasure rehearsal (`kit-scratch/1113/a4/`), confirmed by two independent
read-only investigations and a deterministic reproduction. No existing MDL issue was found (tracker searches
for "contextlist", "approved_contextlist", "iteratorposition", 2026-11-13). Upstream `main` has the same code.

---

**Summary:** Privacy: `contextlist_base` iteration stops at the first duplicate context id, so data
deletion requests silently skip contexts.

**Component:** Privacy. **Affects:** 4.x to 5.1, 5.3 and main (code unchanged).

**Description.**
`\core_privacy\local\request\contextlist_base::set_contextids()` stores `array_unique($contextids)`.
`array_unique()` keeps the original keys, so a list with a duplicate id has a hole in its keys, for example
`[1, 1, 2]` becomes `[0 => 1, 2 => 2]`. The iterator uses a position counter and
`valid()` returns `isset($this->contextids[$this->iteratorposition])`, so `foreach` stops at position 1 and
never reaches context 2. `count()` and `get_contextids()` still report every context.

`contextlist::add_from_sql()` keeps duplicates from the provider SQL (it joins
`{context}` to the provider query, one row per provider row), so any provider whose discovery SQL returns
the same context id twice is affected. Examples in core: `logstore_standard` (`SELECT l.contextid FROM
{logstore_standard_log} l WHERE l.userid = ... OR ...`, one row per log entry), `report_stats`, `mod_quiz`
and `mod_scorm` (all four seen truncating in practice).

`tool_dataprivacy\api::get_approved_contextlist_collection_for_collection()` builds the approved list by
iterating each discovered contextlist (`foreach ($contextlist as $context)`). For a delete request the
approved list is therefore truncated, `delete_data_for_user()` runs only for the contexts before the first
hole, and the request still ends as "Deleted" with no error. The user's data in the remaining contexts
(for example most of their log entries) is not erased.

**Steps to reproduce (unit level):**
```php
$list = new \core_privacy\local\request\approved_contextlist($user, 'tool_log', [$ctx1->id, $ctx1->id, $ctx2->id]);
$seen = [];
foreach ($list as $context) {
    $seen[] = $context->id;
}
// Expected: count($seen) === count($list) === 2. Actual: $seen === [$ctx1->id], count($list) === 2.
```

**Steps to reproduce (site level):** a user with log entries in two or more contexts, where the logstore
discovery returns a context id more than once before another context. Create and approve a delete request
for that user, run the task, then count `logstore_standard_log` rows with that `userid`: rows remain in
the contexts after the first duplicate.

**Expected:** every discovered context is approved and deleted.
**Actual:** iteration stops at the first key hole; later contexts are skipped silently.

**Suggested fix:** reindex after de-duplication:
```php
protected function set_contextids(array $contextids) {
    $this->contextids = array_values(array_unique($contextids));
}
```
Related: `current()` and `get_contexts()` also `unset()` entries by position when a context no longer
exists, which leaves new holes; `get_contexts()` unsets by `$this->iteratorposition` instead of the failing
key. Consider iterating over `array_values()` or reindexing after each unset. A regression test: the
synthetic list above must iterate `count()` contexts, and the same through `add_from_sql()` with duplicate
rows.

---

## For Nitin (not part of the report)

- **Effect on Sentientia (local copy of production data, stock path):** in the pending A4 requests the
  approval silently dropped 3,325 contexts in 223 of 507 requests: tool_log 3,279 (222 requests),
  report_stats 20 (17), mod_quiz 13 (10), mod_scorm 13 (1). About 15,000 log rows of the deleted subjects
  stay after "Deleted". Figures are in the A4 rehearsal report.
- **The fix is a one-line core edit.** ADR-033 says no edits to existing core files on 5.3, so applying it
  locally, on UAT or at cutover needs your decision (amend ADR-033 with a core-mods record), or we wait for
  an upstream fix. Until then every erasure through the stock tool_dataprivacy flow needs a residue check
  afterwards.
- **Filing upstream** is public posting: your call (it can be filed from your own tracker account).
