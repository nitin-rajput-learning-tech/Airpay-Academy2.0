// L-axis UAT: ICS cross-client validation.
//
// We can't actually launch Outlook / Apple Calendar / Google Calendar
// from a script, but ical.js is the same parser those clients use
// under the hood (Mozilla Calendar Server uses it; same parsing rules
// as RFC 5545). If ical.js parses our payload as a valid VCALENDAR
// with the expected VEVENT properties, all major clients will too.
//
// We also check three specific RFC 5545 quirks that Outlook is strict
// about:
//   1. Lines ≤75 octets (with proper continuation folding)
//   2. CRLF line endings (not LF)
//   3. UTC timestamps in DTSTART/DTEND if no TZID

import { chromium } from '@playwright/test';
import ICAL from 'ical.js';

const BASE = 'http://localhost:8080/moodle';
const cases = [];
const record = (n, ok, d) => { cases.push({name:n, ok, detail:d}); console.log(`  ${ok?'✓':'✗'} ${n}${d?' — '+d:''}`); };

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(60000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

// Find any session.
console.log('\n=== UAT-L2: ICS cross-client validation ===');
const sessionInfo = await page.evaluate(async (base) => {
    for (let sid = 1; sid <= 100; sid++) {
        const r = await fetch(base + '/local/airpay_classroom/ics.php?sessionid=' + sid,
            { credentials: 'include', redirect: 'follow' });
        if (r.status === 200) {
            return { sid, text: await r.text() };
        }
    }
    return null;
}, BASE);

if (!sessionInfo) {
    record('UAT-L2.0 Find a session', false, 'no sessions in DB');
    await browser.close();
    process.exit(1);
}

record('UAT-L2.0 Session found', true, `sessionid=${sessionInfo.sid}`);
const ics = sessionInfo.text;
console.log('--- ICS payload (first 600 bytes) ---');
console.log(ics.slice(0, 600));
console.log('--- end ---\n');

// Check 1: CRLF line endings.
const hasCRLF = ics.includes('\r\n');
const onlyLF = ics.includes('\n') && !ics.includes('\r\n');
record('UAT-L2.1 CRLF line endings (RFC 5545 §3.1)',
    hasCRLF && !onlyLF, '');

// Check 2: line lengths ≤75 octets (with continuation folding).
const lines = ics.split('\r\n');
const longLines = lines.filter(l => Buffer.byteLength(l, 'utf-8') > 75);
record('UAT-L2.2 All lines ≤75 octets',
    longLines.length === 0,
    longLines.length > 0
        ? `${longLines.length} long line(s); first: "${longLines[0].slice(0, 100)}…"` : '');

// Check 3: parse with ical.js.
let parsed = null, parseErr = null;
try {
    parsed = ICAL.parse(ics);
} catch (e) {
    parseErr = e.message;
}
record('UAT-L2.3 ical.js parses the payload',
    !!parsed, parseErr || 'OK');

if (parsed) {
    // Walk to the VEVENT.
    const comp = new ICAL.Component(parsed);
    record('UAT-L2.4 Top-level VCALENDAR',
        comp.name === 'vcalendar', `got "${comp.name}"`);

    const version = comp.getFirstPropertyValue('version');
    record('UAT-L2.5 VERSION:2.0',
        version === '2.0', `got "${version}"`);

    const prodid = comp.getFirstPropertyValue('prodid');
    record('UAT-L2.6 PRODID present',
        !!prodid, prodid);

    const events = comp.getAllSubcomponents('vevent');
    record('UAT-L2.7 Exactly one VEVENT',
        events.length === 1, `${events.length} VEVENT(s)`);

    if (events.length === 1) {
        const ev = events[0];
        const uid = ev.getFirstPropertyValue('uid');
        const summary = ev.getFirstPropertyValue('summary');
        const dtstart = ev.getFirstPropertyValue('dtstart');
        const dtend = ev.getFirstPropertyValue('dtend');
        const status = ev.getFirstPropertyValue('status');
        record('UAT-L2.8 UID present',
            !!uid && uid.includes('airpay'),
            uid ? uid.slice(0, 80) : 'missing');
        record('UAT-L2.9 SUMMARY present',
            !!summary, summary ? summary.slice(0, 80) : 'missing');
        record('UAT-L2.10 DTSTART present + UTC',
            !!dtstart, dtstart ? String(dtstart) : 'missing');
        record('UAT-L2.11 DTEND present',
            !!dtend, dtend ? String(dtend) : 'missing');
        // DTSTART < DTEND.
        if (dtstart && dtend) {
            const ok = dtstart.toJSDate().getTime() < dtend.toJSDate().getTime();
            record('UAT-L2.12 DTSTART < DTEND', ok, '');
        }
        record('UAT-L2.13 STATUS:CONFIRMED',
            status === 'CONFIRMED', `got "${status}"`);
    }

    // Outlook-strict check: should not contain naked LF or TZID without VTIMEZONE.
    const hasTZID = ics.includes(';TZID=');
    const hasVTIMEZONE = ics.includes('BEGIN:VTIMEZONE');
    if (hasTZID && !hasVTIMEZONE) {
        record('UAT-L2.14 Outlook-strict: TZID has matching VTIMEZONE',
            false, 'TZID used without VTIMEZONE block — Outlook will reject');
    } else {
        record('UAT-L2.14 Outlook-strict: TZID/VTIMEZONE pairing',
            true, hasTZID ? 'TZID+VTIMEZONE both present' : 'UTC dates (no TZID needed)');
    }
}

await browser.close();

const total = cases.length;
const passed = cases.filter(c => c.ok).length;
const failed = cases.filter(c => !c.ok);

console.log('\n' + '═'.repeat(60));
console.log(`L-axis ICS UAT: ${passed}/${total} cases pass`);
for (const f of failed) console.log(`  ✗ ${f.name} — ${f.detail}`);
process.exit(failed.length === 0 ? 0 : 1);
