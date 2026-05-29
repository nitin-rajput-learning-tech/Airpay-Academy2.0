# Core-mod record — tool_certificate Hindi language pack

**Date:** 2026-05-29
**Plugin:** `admin/tool/certificate` (vendored 3rd-party, v4.5.7, MATURITY_STABLE)
**Change type:** Additive language file (no existing upstream file modified)
**Status:** STAGED, NOT YET APPLIED — pending L&D Hindi review
**Audit ref:** C10 P1 / Gap 4 — `docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md`

---

## What

A Hindi (`hi`) language pack for the vendored `tool_certificate`
plugin — the last English-only admin surface on the platform. 173
strings, full parity with `lang/en/tool_certificate.php` at v4.5.7.

## Current state: STAGED, not applied

The draft lives at
`moodle-enhancement/docs/translations/tool_certificate-hi-DRAFT.php`
and is **not** in any active `lang/` directory. Nothing reaches a
learner until the review-and-activate process in
`docs/translations/README.md` is completed by an Airpay L&D Hindi
reviewer (mandatory per CLAUDE.md §12 — compliance content).

## Why this is tracked as a core-mod

`tool_certificate` is a vendored third-party plugin we do not own.
Adding `lang/hi/tool_certificate.php` to its directory means:

1. **Upgrade fragility.** A future `tool_certificate` upgrade (e.g.
   pulling v4.6 from the upstream repo) will overwrite or remove our
   `lang/hi/` file. This record is how we know to re-apply it.
2. **String drift.** If upstream adds/renames/removes English strings,
   our Hindi pack falls out of parity. On each upgrade, re-diff
   `lang/en/tool_certificate.php` against the staged Hindi and top up.

It is the *safest possible* category of core-mod: it adds a new file
and modifies **zero** existing upstream files, so there is never a
merge conflict on upgrade — only a potential overwrite/deletion, which
this record guards against.

## Activation site (when applied)

```
admin/tool/certificate/lang/hi/tool_certificate.php   ← new file (copy of the reviewed draft)
```

No `// SENTIENTIA-CORE-MOD:` inline tag is added inside the file
because the file IS the modification (it has its own DRAFT/provenance
header). The tag convention is for edits *inside* existing upstream
files; here we add a standalone file.

## Upgrade-safety procedure

On every future `tool_certificate` upgrade:

1. `diff` the new `lang/en/tool_certificate.php` against the version
   this Hindi pack was built from (v4.5.7, 173 strings).
2. Add Hindi for any new English strings; drop any removed keys; fix
   any reworded English.
3. Re-run the L&D review for changed strings only.
4. Re-copy the reviewed Hindi into `lang/hi/`.

## Verification

- `php -l` clean on the staged draft.
- 173 `$string[...]` entries — exact parity with the en pack count.
- All `{$a...}` placeholders, HTML tags (`<br />`, `<a href>`) and the
  multi-line `linkedinorganizationid_desc` block preserved byte-for-byte
  from the English source.

## Cross-reference

- Staging location + activation steps: `docs/translations/README.md`
- The draft itself: `docs/translations/tool_certificate-hi-DRAFT.php`
- C10 investigation (Gap 4): `docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md`
