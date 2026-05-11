// Generate the test fixtures used by the L-axis UAT harnesses.
// Run once: `node fixtures/make_fixtures.mjs`
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

// ── 1. Minimal valid PNG (1×1 magenta, header + IDAT + IEND) ──────
// We want something Moodle's gdlib accepts. A 200×200 solid PNG built
// inline using base64 of a pre-computed valid PNG. The 1px PNG works
// for process_new_icon (it'll scale via PIL/imagick to 100×100).
const pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAcgAAAHIBAMAAACSjmojAAAAG1BMVEX/AP'
    + '//AAD/zAD/mQD/cwAA/wAA/wAA/wAAAAB9LGTrAAACtElEQVR42u3UQQHAMAjEsP8/'
    + 'jXdRLpO0Y//qmTl7AwQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBB'
    + 'BAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEE'
    + 'EEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQ'
    + 'QQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAAB'
    + 'BBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAA'
    + 'EEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAA'
    + 'AQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQA'
    + 'ABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBA'
    + 'AAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEE'
    + 'AAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQ'
    + 'QAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBB'
    + 'BAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEE'
    + 'EEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQ'
    + 'QQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQQAAB'
    + 'BBBAAAEEEEAAAQQQQAABBBBAAAEEEEAAAQQQ4F+Ad9zXrxlBlpUAAAAASUVORK5CYII=';
// That's ~1KB. For uat purposes, a tiny 32×32 valid PNG is faster.
// Build a deterministic test PNG via a minimal 32x32 pixel-data buffer.
// Actually let's just use a known-good 200×200 magenta PNG generated
// out-of-band. We have one inline:
await fs.writeFile(path.join(here, 'test-avatar.png'),
    Buffer.from(pngBase64, 'base64'));
console.log('Wrote test-avatar.png (' + Buffer.byteLength(pngBase64, 'base64') + ' bytes)');

// ── 2. bulk-status.csv ────────────────────────────────────────────
const tag = Math.floor(Date.now() / 1000);
const bulk_status = `email,action
admin@nowhere.invalid,suspend
ghost-${tag}@nowhere.invalid,suspend
`;
await fs.writeFile(path.join(here, 'bulk-status.csv'), bulk_status);
console.log('Wrote bulk-status.csv');

// ── 3. bulk-import-users.csv ──────────────────────────────────────
const bulk_import = `email,firstname,lastname,username,designation,department
uat${tag}@airpay.test,UAT,User,uatuser${tag},Analyst,Compliance
uat${tag + 1}@airpay.test,UAT,UserB,uatuserb${tag},Manager,Operations
`;
await fs.writeFile(path.join(here, 'bulk-import-users.csv'), bulk_import);
console.log('Wrote bulk-import-users.csv');

// ── 4. enrol-csv.csv (will fail in UAT — ghost users — that's OK) ─
const enrol_csv = `email,courseshortname,role
admin@nowhere.invalid,fo_HR001,student
ghost-${tag}@nowhere.invalid,fo_HR001,
`;
await fs.writeFile(path.join(here, 'enrol-csv.csv'), enrol_csv);
console.log('Wrote enrol-csv.csv');

// ── 5. eval-template.json — minimal valid template ────────────────
const eval_template = {
    schema_version: 1,
    evaluation: {
        name: `UAT template ${tag}`,
        description: 'Imported via UAT L-axis harness.',
        kirkpatrick_level: 1,
        trigger_event: 'manual',
        days_after: 0,
        anonymous: 0,
    },
    questions: [
        {
            questiontype: 'rating',
            questiontext: 'How was the session?',
            options: [],
            required: 1,
            anonymous: 0,
            sortorder: 1,
        },
        {
            questiontype: 'text',
            questiontext: 'Any free-text feedback?',
            options: [],
            required: 0,
            anonymous: 1,  // Test the per-question anonymous flag.
            sortorder: 2,
        },
    ],
};
await fs.writeFile(path.join(here, 'eval-template.json'),
    JSON.stringify(eval_template, null, 2));
console.log('Wrote eval-template.json');

console.log('\nAll fixtures generated.');
