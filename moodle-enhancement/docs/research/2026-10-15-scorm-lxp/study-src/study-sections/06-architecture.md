## 6. Target architecture (summary of ADR-034)

```
 SOP / PDF / DOCX / PPTX / prompt / SCORM import
          │  PII scan (block | redact | clean) ─────────────── audit log (append-only, hash-chained)
          ▼
 ┌──────────────────────────────────────────────────────────────────────────────┐
 │  SENTIENTIA CANONICAL COURSE DOCUMENT (SCD) v0.1  — one JSON per course       │
 │  course · blocks[slide | scenario | quiz | video*] · i18n strings · lang_states│
 │  protected terms · reinforcement pool · delivery targets · provenance         │
 └──────────────────────────────────────────────────────────────────────────────┘
      ▲ generate (Claude via local_sentientia_ai, strict JSON, review gate)
      ▲ translate (TM first → Claude → brand post-process → two-stage human review; XLIFF in/out)
      ▲ voice (sentientia-speech: self-hosted TTS → ASR round-trip → forced alignment → VTT)
      ▲ edit (authoring studio, scenario editor, localisation workspace)
          │ compile (PHP, adhoc task; harness = reference implementation)
          ▼
   SCORM 1.2 ZIP      SCORM 2004 4th ZIP      cmi5 ZIP       Moodle course      (mobile stream*)
   static player ──── same player ──────────── same player    mod_book + quiz
   compact suspend    64k suspend              xAPI stmts
   exit=suspend       completion+success       cmi5 context
   interactions       interactions             answered stmts
          │                                           │
          ▼                                           ▼
   Moodle mod_scorm (5.3)                 local_sentientia_xapi LRS (+ customer LRS)
```
\* deferred

**Why this shape.** The report's first mandate — never let the model emit HTML or SCORM, keep a declarative intermediate — is what lets one approved course reach Moodle today (SCORM 1.2), a buyer who insists on SCORM 2004 or cmi5 tomorrow, and the LRS for decision-level analytics, without re-authoring. The static player is written once and hardened once against the Moodle runtime traps; the three runtime adapters sit behind one interface so the course logic never knows which standard is running. Localisation is a property of the document (every string is an i18n map), so packages can carry all approved languages with an in-player switcher, and XLIFF/TM/human review operate on stable ids rather than on slide decks.

**What is new versus what exists.** New: the SCD and its tables, the compiler and player, XLIFF/TM/two-stage review, the scenario editor, the speech service, the pilot harness. Reused: the AI gateway (keys, ledger, quotas, golden sets), authoring's review gate and course builder, translate's prompt builder and brand manager, xapi's statements endpoint and cmi5 tracker, the platform's flags, tenant scoping, privacy providers and audit log, the theme's tokens and app shell.

**Where it runs.** Generation and translation: Claude through the gateway (model configurable; current Sonnet generation recommended once the gateway's golden-set eval is re-run — Sonnet 5.5 is cheaper than the gateway's default Sonnet 4.6). Speech: in India, on a GPU we control (cloud Mumbai/Central India or on-prem), with a SaaS fallback that processes in an India region. Compilation and review: inside Moodle (PHP), no new runtime. Audit and ledger: Moodle DB, exportable.

**One package or one per language?** The standards research recommends one package per language for reporting parity (static manifest titles; one completion per Moodle activity). ADR-034 defaults to the opposite on purpose: one activity, one completion record, an in-player switcher and one package to approve and deploy, with `per_language` as an option. The harness builds both (`--package-mode`), and the default is revisited after the first UAT upload against Moodle's own SCORM report.

Full decisions, alternatives and verification gates: `docs/adr/ADR-034-content-pipeline-architecture.md`.

---
