"""Sentientia pilot harness library (standard library only).

Modules:
    common            shared helpers (hashing, JSON pointers, UTF-8 stdio)
    statetoken        Python mirror of the compact suspend-state token grammar
    schema_lite       JSON Schema subset validator + SCD x-rules R01-R12
    pii               PII scanner (Aadhaar, PAN, mobile, email, IFSC, cards)
    translate         translation fill: mock pseudo-localisation / live request builder
    tts               narration audio: mock silent MP3 + WebVTT + word timestamps
    packager          SCORM 1.2 / SCORM 2004 4th Ed. / cmi5 package compilers
    validate_package  post-build package checks and the state budget
    report            validation-report.json / .md writers
    audit             append-only JSONL audit log with a sha256 chain
"""
