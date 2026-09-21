// Airpay Academy 2.0 — Sentientia LMS · UAT showcase deck for MD/Founder, CTO, CHRO (September 2026).
// Every figure traces to the engineering record (PROJECT-STATE, UAT-DEMO-READINESS, UAT-VALIDATION-PLAN,
// SENTIENTIA-MIGRATION-PLAN, ADR-028/029/030, PRODUCT-MATURITY-AUDIT). Sentientia is NOT live — say so.
const pptxgen = require('pptxgenjs');
const fs = require('fs');
const ICON = JSON.parse(fs.readFileSync(__dirname + '/icons.json', 'utf8'));

const NAVY = '06355C', NAVY2 = '0A4D80', PRIMARY = '0066A7', ACCENT = '1985DD', ORANGE = 'ED692B',
  WHITE = 'FFFFFF', TEXT = '1A1A2E', MUTED = '5A6070', BORDER = 'E2E6EF', GREEN = '16A34A',
  AMBER = 'D97706', ICE = 'E8F2F9', RED = 'C2410C', MINT = 'EEF7F1', SAND = 'FDF3EC';
const W = 13.333, ML = 0.6, F = 'Arial';

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';
pres.author = 'Nitin Rajput';
pres.title = 'Airpay Academy 2.0 - Sentientia LMS - UAT showcase';

let pageNo = 0;
const pg = () => ++pageNo;
const darkSlide = () => { const s = pres.addSlide(); s.background = { color: NAVY }; return s; };
const lightSlide = () => { const s = pres.addSlide(); s.background = { color: WHITE }; return s; };

function header(s, kicker, title, dark) {
  s.addText(kicker.toUpperCase(), { x: ML, y: 0.34, w: 12, h: 0.3, fontFace: F, fontSize: 12, bold: true, color: dark ? 'A9CDE8' : ACCENT, charSpacing: 2, margin: 0 });
  s.addText(title, { x: ML, y: 0.62, w: 12.1, h: 0.75, fontFace: F, fontSize: 28, bold: true, color: dark ? WHITE : TEXT, margin: 0 });
}
function footer(s, dark) {
  s.addText('Airpay Academy 2.0  ·  Sentientia LMS  ·  UAT showcase  ·  Confidential', { x: ML, y: 7.12, w: 7, h: 0.25, fontSize: 8.5, fontFace: F, color: dark ? '7FA8C9' : 'A0A9B8', margin: 0 });
  s.addText(String(pageNo), { x: 12.55, y: 7.12, w: 0.55, h: 0.25, fontSize: 8.5, fontFace: F, color: dark ? '7FA8C9' : 'A0A9B8', align: 'right', margin: 0 });
}
function iconCircle(s, icon, x, y, d, circleColor) {
  s.addShape('ellipse', { x, y, w: d, h: d, fill: { color: circleColor } });
  const pad = d * 0.26;
  s.addImage({ data: ICON[icon], x: x + pad, y: y + pad, w: d - 2 * pad, h: d - 2 * pad });
}
function card(s, x, y, w, h, fillColor) {
  s.addShape('roundRect', { x, y, w, h, rectRadius: 0.07, fill: { color: fillColor || WHITE }, line: { color: BORDER, width: 0.75 }, shadow: { type: 'outer', color: '9AA6B5', blur: 6, offset: 1, angle: 90, opacity: 0.28 } });
}
function iconCard(s, x, y, w, h, icon, title, body, circle) {
  card(s, x, y, w, h);
  iconCircle(s, icon, x + 0.16, y + 0.16, 0.44, circle || PRIMARY);
  s.addText(title, { x: x + 0.7, y: y + 0.14, w: w - 0.84, h: 0.5, fontSize: 12.5, bold: true, fontFace: F, color: TEXT, margin: 0, valign: 'middle' });
  s.addText(body, { x: x + 0.18, y: y + 0.68, w: w - 0.36, h: h - 0.82, fontSize: 11.5, fontFace: F, color: MUTED, margin: 0, valign: 'top' });
}
function stat(s, x, y, w, big, label, color) {
  s.addText(big, { x, y, w, h: 0.72, fontSize: 36, bold: true, fontFace: F, color: color || PRIMARY, align: 'center', margin: 0 });
  s.addText(label, { x, y: y + 0.72, w, h: 0.66, fontSize: 10.5, fontFace: F, color: MUTED, align: 'center', margin: 0, valign: 'top' });
}
function bullets(items, opts = {}) {
  return items.map((t, i) => ({ text: t, options: { bullet: true, breakLine: i < items.length - 1, ...opts } }));
}
function takeaway(s, text) {
  s.addText(text, { x: ML, y: 6.55, w: 12.1, h: 0.45, fontSize: 12, italic: true, fontFace: F, color: PRIMARY, margin: 0 });
}
const PNG = __dirname + '/png/';

/* 1 ─ TITLE */
{
  const s = darkSlide(); pg();
  s.addShape('ellipse', { x: 10.6, y: -1.6, w: 4.6, h: 4.6, fill: { color: NAVY2, transparency: 55 } });
  s.addShape('ellipse', { x: -1.4, y: 5.4, w: 3.6, h: 3.6, fill: { color: NAVY2, transparency: 65 } });
  s.addImage({ path: __dirname + '/logo-wide.png', x: ML, y: 0.9, w: 1.9, h: 1.1 });
  s.addText('AIRPAY  LEARNING & DEVELOPMENT  ·  LEADERSHIP SHOWCASE', { x: ML, y: 2.35, w: 10, h: 0.35, fontSize: 12.5, bold: true, fontFace: F, color: 'A9CDE8', charSpacing: 3, margin: 0 });
  s.addText('Airpay Academy 2.0', { x: ML, y: 2.75, w: 11.5, h: 1.0, fontSize: 54, bold: true, fontFace: F, color: WHITE, margin: 0 });
  s.addText('powered by Sentientia LMS - our own enterprise learning platform', { x: ML, y: 3.8, w: 11.5, h: 0.55, fontSize: 22, fontFace: F, color: 'CADCFC', margin: 0 });
  s.addText([
    { text: 'UAT showcase', options: { bold: true, color: WHITE } },
    { text: '   -   what is running on the test environment today, what it means for people, technology and risk, and what we need decided', options: { color: 'A9CDE8' } },
  ], { x: ML, y: 4.75, w: 11.8, h: 0.7, fontSize: 14.5, fontFace: F, margin: 0 });
  s.addText('For the MD & Founder, CTO and CHRO  ·  Prepared by Nitin Rajput, Head of Learning & Development  ·  September 2026', { x: ML, y: 6.35, w: 11.5, h: 0.3, fontSize: 11.5, fontFace: F, color: '7FA8C9', margin: 0 });
  s.addNotes('One-line story: the learning platform we bought has been rebuilt into a platform we own - Airpay Academy 2.0 on Sentientia LMS - and it is running on a UAT environment today with every persona validated. Be precise: it is NOT live; no real user is on it yet. Today we show it, and ask for five decisions.');
}

/* 2 ─ EXECUTIVE SUMMARY */
{
  const s = lightSlide(); pg();
  header(s, 'Executive summary', 'Built, validated on UAT, ready for the tester cohort');
  s.addText([
    { text: 'Where we are.  ', options: { bold: true, color: TEXT } },
    { text: 'Sentientia LMS - a white-label enterprise learning platform we own end to end, with Airpay Academy as its first customer - has been running on a dedicated UAT environment (academy2.airpay.ninja) since 3 September on the modern stack IT approved (Moodle 5.2, PHP 8.3, MySQL 8.4). Every persona\'s demo surface loads clean, tenant isolation and Hindi have been verified on screen, and the security audit findings that block production are down to three external items. It is not live: every user on it is a test persona. Real users load only after a passed migration rehearsal.', options: { color: MUTED } },
  ], { x: ML, y: 1.5, w: 12.1, h: 1.25, fontSize: 12.5, fontFace: F, margin: 0 });
  const y = 2.95;
  stat(s, ML, y, 2.9, '67 / 67', 'demo surfaces load clean across 11 persona walks');
  stat(s, 3.7, y, 2.9, '16 / 16', 'on-screen checks passed after the September fixes (10 + 6)', GREEN);
  stat(s, 6.8, y, 2.9, '3', 'tenants isolated on one platform (Airpay · Public · ZEEA Tanzania)');
  stat(s, 9.9, y, 2.9, '3', 'security items still open before production - all external', ORANGE);
  card(s, ML, 4.6, 12.13, 2.3, ICE);
  s.addText('WHAT WE ASK TODAY', { x: 0.85, y: 4.78, w: 6, h: 0.3, fontSize: 11, bold: true, color: PRIMARY, fontFace: F, charSpacing: 2, margin: 0 });
  s.addText([
    { text: '1.  Green-light the tester cohort: 13 personas, a two-week window, findings triaged daily', options: { breakLine: true } },
    { text: '2.  Close the three external blockers: payment-hash merge (product owner), reCAPTCHA keys + Entra SSO app + office-IP allowlist (IT)', options: { breakLine: true } },
    { text: '3.  Approve direction on the security & certification programme and the AI operating budget - spend plans already drafted', options: { breakLine: true } },
    { text: '4.  Confirm the go-live gate: a passed live-backup rehearsal, not a calendar date', options: {} },
  ], { x: 0.85, y: 5.1, w: 11.4, h: 1.7, fontSize: 12.5, fontFace: F, color: TEXT, paraSpaceAfter: 6, margin: 0 });
  footer(s);
  s.addNotes('Figures: 67/67 from the functional walk (UAT-DEMO-READINESS), 16/16 = 10 checks on 16 Sep + 6 on 17 Sep, 3 tenants, 3 open security items (C1 payment hash merge, H2 reCAPTCHA keys, M5 which rides on C1). Say clearly: not live, no real users yet.');
}

/* 3 ─ JOURNEY */
{
  const s = lightSlide(); pg();
  header(s, 'How we got here', 'Six weeks from approval to a validated UAT');
  const steps = [
    ['4 AUG', 'Roadmap signed', 'Reconciled product roadmap (ADR-028) and decision memo signed: parallel trust + product tracks, "5.2 now" as the single cutover path', GREEN],
    ['19 AUG', 'UAT infrastructure', 'CTO-approved cloud environment provisioned: Ubuntu 24.04 / PHP 8.3.6 / MySQL 8.4.9 behind a load balancer (ticket HS-20260819-79876)', GREEN],
    ['3 SEP', 'Stage A install', 'Fresh command-line install of the Sentientia 5.2 package: PASS-WITH-NOTES, 480 plugins, environment check 64 OK / 0 errors; 10 test personas + 14 courses seeded', GREEN],
    ['4 - 17 SEP', 'Hardening on real screens', 'Security findings fixed, tenant scoping tightened, Hindi parity completed, author role wired, footer and licence wording set - 16 on-screen checks passed', GREEN],
    ['NOW', 'Tester cohort', '13 personas, guided scripts, shared tracker - the UAT Phase 1 that turns our checks into your people\'s verdict', ORANGE],
    ['GATED', 'Stage B rehearsal → cutover', 'Live-backup migration rehearsal on UAT (100 % parity gate), then a same-domain cutover with history intact - only on the product owner\'s go', NAVY2],
  ];
  let y = 1.6;
  steps.forEach((st, i) => {
    s.addShape('roundRect', { x: ML, y, w: 12.13, h: 0.74, rectRadius: 0.06, fill: { color: i < 4 ? MINT : WHITE }, line: { color: BORDER, width: 0.75 } });
    s.addShape('roundRect', { x: 0.78, y: y + 0.17, w: 1.5, h: 0.4, rectRadius: 0.05, fill: { color: st[3] } });
    s.addText(st[0], { x: 0.78, y: y + 0.17, w: 1.5, h: 0.4, fontSize: 10.5, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    s.addText(st[1], { x: 2.5, y: y + 0.05, w: 3.2, h: 0.64, fontSize: 12.5, bold: true, color: TEXT, fontFace: F, margin: 0, valign: 'middle' });
    s.addText(st[2], { x: 5.8, y: y + 0.03, w: 6.75, h: 0.68, fontSize: 10.5, color: MUTED, fontFace: F, margin: 0, valign: 'middle' });
    y += 0.8;
  });
  takeaway(s, 'The live airpay.academy has stayed untouched throughout - by design. Nothing changes for today\'s learners until the rehearsal passes.');
  footer(s);
  s.addNotes('If asked "when does it go live": after the Stage B rehearsal passes and IT has production on MySQL 8.4 / PHP 8.3. Deliberately not date-promised.');
}

/* 4 ─ WHAT IT LOOKS LIKE (screens) */
{
  const s = lightSlide(); pg();
  header(s, 'On the UAT environment today', 'Airpay-branded and bilingual, built for financial services');
  s.addImage({ path: PNG + 'uat-landing-crop.png', x: ML, y: 1.55, w: 5.95, h: 3.76 });
  s.addImage({ path: PNG + 'uat-login-hi-hero.png', x: 6.75, y: 1.55, w: 2.69, h: 3.76 });
  s.addImage({ path: PNG + 'uat-courses-crop.png', x: 9.64, y: 1.55, w: 3.09, h: 1.8 });
  s.addText('Public landing page - the storefront for the Public tenant', { x: ML, y: 5.38, w: 5.95, h: 0.3, fontSize: 10, color: MUTED, fontFace: F, margin: 0 });
  s.addText('Login in Hindi - one switch, every screen', { x: 6.75, y: 5.38, w: 2.8, h: 0.3, fontSize: 10, color: MUTED, fontFace: F, margin: 0 });
  s.addText('Public catalogue with free and paid courses', { x: 9.64, y: 3.4, w: 3.09, h: 0.3, fontSize: 10, color: MUTED, fontFace: F, margin: 0 });
  card(s, 9.64, 3.8, 3.09, 1.51, ICE);
  s.addText([{ text: '3 tenants  ·  13 personas  ·  14 courses\n', options: { bold: true, color: TEXT } }, { text: 'seeded on UAT - learning paths, classrooms, an exam, a live poll and four compliance-tracked courses', options: { color: MUTED } }], { x: 9.78, y: 3.88, w: 2.85, h: 1.38, fontSize: 10, fontFace: F, margin: 0, valign: 'top' });
  card(s, ML, 5.85, 12.13, 0.95, ICE);
  s.addText([
    { text: 'Live in the room:  ', options: { bold: true, color: TEXT } },
    { text: 'the 20-minute demo path walks a learner, a manager, a trainer\'s live poll, the L&D admin and the second tenant - all on this environment, all on test data. Screens shown here are captured from UAT on 17 September.', options: { color: MUTED } },
  ], { x: 0.85, y: 5.95, w: 11.6, h: 0.75, fontSize: 11.5, fontFace: F, margin: 0, valign: 'middle' });
  footer(s);
  s.addNotes('Captured from https://academy2.airpay.ninja on 17 Sep 2026 (public pages). Logged-in screens are shown live during the demo.');
}

/* 5 ─ CHRO VIEW */
{
  const s = lightSlide(); pg();
  header(s, 'What the people function gets', 'Employees get consumer-grade learning; managers get control');
  const items = [
    ['gradcap_w', 'Learners', 'Role-aware dashboard with continue-learning, deadlines, achievements and a 7-day streak; My Courses, catalogue, certificates wall, skills self-assessment with gap radar; SCORM, quizzes and assignments in one branded player.'],
    ['language_w', 'Inclusion built in', 'Full Hindi experience - 332 theme strings and every plugin surface on the demo path verified on screen; Marathi, Kannada and Swahili packs started. Accessibility: WCAG-AA contrast, reduced-motion, screen-reader structure.'],
    ['userscog_w', 'Managers', 'Team dashboard with enrolments, completions and completion rate; compliance table with overdue drill-down; approvals for course requests; CSV export - managers see their team without asking L&D.'],
    ['shield_w', 'Compliance', 'Mandatory training with deadlines and auto-reminders (7/3/1 days), manager escalation (1/7/14 days overdue), tenant-scoped compliance matrix with Excel export, recompletion cycles for annual certifications, DPDP data-rights tooling.'],
    ['bolt_w', 'Engagement', 'Sentientia Live: in-class polls, quizzes, word clouds and Q&A with real-time results (Mentimeter-class, built in-house); gamification points, levels and leaderboards; a running town-hall poll is seeded on UAT.'],
    ['rocket_w', 'Onboarding & lifecycle', 'First-login onboarding wizard (shown in Hindi for a new joiner persona); HRMS-driven joiner / mover / leaver automation proven on the full 2,871-employee record; KeKa webhook receiver hardened.'],
  ];
  items.forEach((it, i) => {
    const cx = ML + (i % 3) * 4.1, cy = 1.6 + Math.floor(i / 3) * 2.45;
    iconCard(s, cx, cy, 3.85, 2.25, it[0], it[1], it[2]);
  });
  takeaway(s, 'Why it matters: adoption. A platform people like using turns compliance training into a learning culture - and it is the same storefront we would show a paying customer.');
  footer(s);
  s.addNotes('CHRO framing: adoption, inclusion (Hindi), manager self-service, compliance evidence. Live sessions and gamification are demoable on UAT; outbound email/WhatsApp are deliberately switched off on UAT.');
}

/* 6 ─ CTO VIEW */
{
  const s = lightSlide(); pg();
  header(s, 'What technology leadership gets', 'A modern, owned platform with engineering discipline behind it');
  const cols = [
    { title: 'Platform', icon: 'server_w', color: PRIMARY, items: ['Moodle 5.2 core (Build 20260519) on PHP 8.3.6 / MySQL 8.4.9 RDS, Apache + PHP-FPM behind an ALB - the stack IT approved for UAT', 'Sentientia layer: 46 plugins, 6 blocks, a standalone 708-file design system - every template owned', 'Multi-tenant by design: tenant isolation on the org path, verified on real data (Airpay admin 743 users, ZEEA admin 6, site admin 1,426)', 'Feature flags at 5 levels, default OFF - platform behaviour never changes by accident'] },
    { title: 'Integration & identity', icon: 'plug_w', color: NAVY2, items: ['SCIM 2.0 users + groups and signed outbound webhooks built and unit-tested (flag-OFF until a consumer exists)', 'KeKa HRMS joiner / mover / leaver webhook hardened; 24-column HRMS CSV import proven on 2,871 records', 'Entra SSO + MFA: configuration packs ready, 0 lines of code - waits on the IT app registration', 'Public REST API v1 with rate limiting and OpenAPI; LTI 1.3 scaffolding; xAPI activity capture'] },
    { title: 'Delivery discipline', icon: 'check_w', color: GREEN, items: ['Checksummed release packages (v4.2.0: 65,588 files, SHA-256 published) and a surgical deployer with pre-deploy backups', '17 pre-commit checks + CI: language parity, security scans, template leaks, contract checks; PHPUnit on every touched plugin', '30 architecture decision records + per-component state cards - any engineer or auditor can reconstruct why', 'Same-domain cutover plan with a 100 % data-parity gate (2,057-step upgrade already rehearsed error-free on production-shaped data)'] },
  ];
  cols.forEach((g, i) => {
    const cx = ML + i * 4.1;
    card(s, cx, 1.6, 3.85, 4.75);
    iconCircle(s, g.icon, cx + 0.18, 1.8, 0.5, g.color);
    s.addText(g.title, { x: cx + 0.82, y: 1.8, w: 2.9, h: 0.5, fontSize: 15, bold: true, fontFace: F, color: TEXT, margin: 0, valign: 'middle' });
    s.addText(bullets(g.items), { x: cx + 0.22, y: 2.45, w: 3.45, h: 3.8, fontSize: 11.5, fontFace: F, color: MUTED, paraSpaceAfter: 12, margin: 0, valign: 'top' });
  });
  takeaway(s, 'Licence posture: the core stays GPL v3 (Moodle); we sell hosting, implementation and service, not seats. End-user screens carry Airpay\'s private-and-confidential notice.');
  footer(s);
  s.addNotes('CTO questions to expect: stack currency (5.2/8.3/8.4 - yes on UAT; production RDS is still 8.0.44 and needs IT\'s change request), isolation proof (screen-checked 16-17 Sep), how changes ship (checksummed, backed-up, upgrade + purge), licence (GPL core, SaaS model).');
}

/* 7 ─ SECURITY */
{
  const s = lightSlide(); pg();
  header(s, 'Security posture', 'Audit findings closed fast; three external items remain');
  card(s, ML, 1.6, 5.9, 4.7, MINT);
  s.addText('FIXED AND VERIFIED ON UAT', { x: 0.85, y: 1.78, w: 5.4, h: 0.3, fontSize: 11, bold: true, color: GREEN, fontFace: F, charSpacing: 1, margin: 0 });
  s.addText(bullets([
    'C2 - cross-tenant self-enrolment closed (a Public learner now gets 404 on Airpay / ZEEA courses)',
    'H1 - signup account enumeration closed',
    'H3 - xAPI endpoint rate-limited; H4 - real-time stream connection caps (137 tests)',
    'M1 / M3 / M4 - HSTS, X-Frame-Options, nosniff, Referrer and Permissions policies; server banner hidden; config file 640; debug off',
    'Payment-verification bypass in the inherited gateway fixed fail-closed (13-test suite) - merge pending',
    'Database access moved to an app-scoped user; the superuser password delivered in plain text was rotated',
  ]), { x: 0.85, y: 2.15, w: 5.45, h: 4.0, fontSize: 11, fontFace: F, color: TEXT, paraSpaceAfter: 8, margin: 0, valign: 'top' });
  card(s, 6.85, 1.6, 5.9, 4.7, SAND);
  s.addText('STILL OPEN - ALL EXTERNAL TO ENGINEERING', { x: 7.1, y: 1.78, w: 5.4, h: 0.3, fontSize: 11, bold: true, color: RED, fontFace: F, charSpacing: 1, margin: 0 });
  const open = [
    ['C1', 'Payment hash fix branch to be merged; needs one Airpay gateway sandbox round-trip', 'Product owner'],
    ['H2', 'Production reCAPTCHA keys for public signup', 'IT'],
    ['M5', 'Rides on C1 - closes with the merge', 'Product owner'],
    ['Gate', 'Office-IP allowlist on the UAT load balancer before any real-data rehearsal', 'IT / Cloud.in'],
  ];
  let oy = 2.2;
  open.forEach(o => {
    s.addShape('roundRect', { x: 7.1, y: oy, w: 0.75, h: 0.42, rectRadius: 0.05, fill: { color: RED } });
    s.addText(o[0], { x: 7.1, y: oy, w: 0.75, h: 0.42, fontSize: 11, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    s.addText([{ text: o[1] + '  ', options: { color: TEXT } }, { text: o[2], options: { bold: true, color: PRIMARY } }], { x: 8.0, y: oy - 0.05, w: 4.6, h: 0.75, fontSize: 10.5, fontFace: F, margin: 0, valign: 'top' });
    oy += 0.9;
  });
  s.addText('Audit verdict (3 Sep): CONDITIONAL PASS for UAT, BLOCK for production until these close.', { x: 7.1, y: 5.85, w: 5.45, h: 0.4, fontSize: 10.5, italic: true, color: MUTED, fontFace: F, margin: 0 });
  takeaway(s, 'Beyond fixes: the funded trust track (CERT-In pen-test, ISO 27001, SOC 2, 25k-user load test) is what turns "secure by our word" into buyer-grade evidence - direction approval requested today.');
  footer(s);
  s.addNotes('Source: docs/security/UAT-SECURITY-POSTURE-2026-09-03.md and the validation plan. Present the open items as decisions, not gaps: C1 is a merge + sandbox test, H2 is a key from IT.');
}

/* 8 ─ SCALE & DATA */
{
  const s = darkSlide(); pg();
  header(s, 'Scale and data - what is real, what is test', 'Hardened at production scale, running on test data only', true);
  const tiles = [
    ['2,871', 'employee records in the production data copy the platform was hardened against (411 courses, 3 tenants)', 'building_w'],
    ['100 %', 'data parity in the local migration rehearsal - 32,248 completions, 11,415 certificates, 8,687 quiz attempts matched row for row', 'check_w'],
    ['13', 'test personas on UAT across 3 tenants, 14 seeded courses, learning paths, classrooms, an exam and a live poll', 'userscog_w'],
    ['0', 'errors under a 20-user load baseline on UAT; p95 under 1 second; backup + restore drill in 44 seconds', 'bolt_w'],
    ['2,057', 'upgrade steps in the 5.1 → 5.2 data migration, rehearsed error-free - the one real transform on cutover day', 'layers_w'],
    ['3,500+', 'learners on the live airpay.academy today - untouched, still on the previous platform until the rehearsal passes', 'globe_w'],
  ];
  tiles.forEach((r, i) => {
    const cx = ML + (i % 3) * 4.1, cy = 1.7 + Math.floor(i / 3) * 2.4;
    s.addShape('roundRect', { x: cx, y: cy, w: 3.85, h: 2.15, rectRadius: 0.08, fill: { color: NAVY2 }, line: { color: '1C6EA8', width: 0.75 } });
    iconCircle(s, r[2], cx + 0.22, cy + 0.24, 0.5, PRIMARY);
    s.addText(r[0], { x: cx + 0.9, y: cy + 0.14, w: 2.8, h: 0.75, fontSize: 30, bold: true, fontFace: F, color: WHITE, margin: 0 });
    s.addText(r[1], { x: cx + 0.24, y: cy + 0.98, w: 3.4, h: 1.1, fontSize: 10.5, fontFace: F, color: 'CADCFC', margin: 0, valign: 'top' });
  });
  s.addText('Two environments, one rule: the development copy holds a test import of production data; UAT holds fake personas. No real learner has used Sentientia yet.', { x: ML, y: 6.5, w: 12.1, h: 0.5, fontSize: 11.5, italic: true, fontFace: F, color: 'A9CDE8', margin: 0 });
  footer(s, true);
  s.addNotes('Sources: migration plan §3.1 (parity baseline), validation plan 2.2/2.3 (drill, load), Phase 0 provisioning. The 3,500+ figure is the live airpay.academy population, not Sentientia users.');
}

/* 9 ─ MULTI-TENANT / PRODUCT */
{
  const s = lightSlide(); pg();
  header(s, 'One platform, many organisations', 'Three tenants isolated; a second customer is a config task');
  const tenants = [
    ['Airpay internal  (/1)', 'HRMS-synced employees, supervisor hierarchy, mandatory compliance. L&D admin sees 9 users, 6 courses on UAT - and only those.', PRIMARY],
    ['Public  (/77)', 'Self-signup storefront with cart and the Airpay payment gateway (checkout stops at "Payment coming soon" on UAT).', ACCENT],
    ['ZEEA Mafunzo, Tanzania  (/177)', 'Partner tenant in Swahili; its admin sees 2 users, 4 courses and its own compliance course - nothing of Airpay\'s.', NAVY2],
  ];
  tenants.forEach((t, i) => {
    const cx = ML + i * 4.1;
    s.addShape('roundRect', { x: cx, y: 1.6, w: 3.85, h: 0.5, rectRadius: 0.06, fill: { color: t[2] } });
    s.addText(t[0], { x: cx, y: 1.6, w: 3.85, h: 0.5, fontSize: 12.5, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    card(s, cx, 2.25, 3.85, 1.55);
    s.addText(t[1], { x: cx + 0.18, y: 2.35, w: 3.5, h: 1.35, fontSize: 10.5, color: MUTED, fontFace: F, margin: 0, valign: 'top' });
  });
  card(s, ML, 4.05, 12.13, 2.3, ICE);
  iconCircle(s, 'handshake_w', 0.82, 4.3, 0.5, PRIMARY);
  s.addText('White-label by architecture', { x: 1.5, y: 4.25, w: 10.8, h: 0.5, fontSize: 14, bold: true, fontFace: F, color: TEXT, margin: 0, valign: 'middle' });
  s.addText(bullets([
    'Customer registry demonstrated in August: a fictional second customer ("Meridian Financial Services") resolves end to end with its own feature flags and isolation',
    'Branding, legal footer and every customer-name string resolve from configuration - the Airpay private-and-confidential notice is one such string',
    'Honest caveat: the multi-customer layer is early-stage - production keeps customer #1 hard-wired until a second customer is signed; the isolation that matters today is tenant-level, and that is verified',
  ]), { x: 1.5, y: 4.8, w: 10.9, h: 1.5, fontSize: 11, fontFace: F, color: MUTED, paraSpaceAfter: 6, margin: 0, valign: 'top' });
  takeaway(s, 'This is the growth option: the same build that serves Airpay can be offered to other enterprises as a hosted, managed platform.');
  footer(s);
  s.addNotes('Tenant figures are from the 16-17 Sep screen checks on UAT. Customer-N demo is on the development environment (2026-08-20). Do not oversell multi-customer - say "architecture demonstrated, productisation deferred until customer #2".');
}

/* 10 ─ ROADMAP */
{
  const s = lightSlide(); pg();
  header(s, 'Roadmap (ADR-028, signed 4 Aug)', 'Two tracks in parallel; cutover is its own gated path');
  const cols = [
    ['TRUST TRACK', ['Identity pack: Entra SSO + MFA configuration ready - execute when IT registers the app', 'CERT-In empanelled penetration test, then ISO 27001 gap closure and Stage 1', 'SOC 2 Type II observation window for global buyers', '25k-user load test tiers - published scale proof', 'Status: funded in principle (envelope ₹80-120 lakh; spend annex ₹52-109 lakh) - approval pending'], ORANGE],
    ['PRODUCT TRACK', ['Done: customer registry (2.1), skills-first home (2.2), SCIM 2.0 + webhooks (2.4), KeKa hardening', 'Blocked: live AI (2.3) - six features run in mock mode until the monthly cap and API key exist', 'Next: engagement loop (2.5), marketplace connectors Go1 + Coursera (2.6), Indic text-to-speech evaluation (2.7)', 'Funded: native mobile app (Capacitor) on the REST v1 surface; PWA hardened meanwhile'], PRIMARY],
    ['CUTOVER PATH', ['UAT Phase 1: tester cohort, findings triaged daily (now)', 'Phase 3: integrations as IT delivers keys (SSO, reCAPTCHA, SMTP via OAuth2)', 'Stage B: live-backup rehearsal on UAT with the 100 % parity gate - needs UAT resize + office-IP allowlist', 'Production prerequisites: RDS to MySQL 8.4 and PHP 8.3 (IT change requests - committed dates requested)', 'Cutover: same domain, history intact, multi-hour maintenance window sized from the rehearsal'], NAVY2],
  ];
  cols.forEach((c, i) => {
    const cx = ML + i * 4.1;
    s.addShape('roundRect', { x: cx, y: 1.6, w: 3.85, h: 0.5, rectRadius: 0.06, fill: { color: c[2] } });
    s.addText(c[0], { x: cx, y: 1.6, w: 3.85, h: 0.5, fontSize: 12.5, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    card(s, cx, 2.25, 3.85, 4.15);
    s.addText(bullets(c[1]), { x: cx + 0.18, y: 2.4, w: 3.5, h: 3.9, fontSize: 11.5, color: MUTED, fontFace: F, paraSpaceAfter: 12, margin: 0, valign: 'top' });
  });
  takeaway(s, 'Working targets, not commitments to risk: the production cutover follows a passed rehearsal and IT\'s platform upgrades, whatever the calendar says.');
  footer(s);
  s.addNotes('ADR-028 decisions: freeze rejected → parallel tracks; Q3 full trust roadmap; Q5 native app funded; Addendum A AI budget (cap TBD, key not provisioned); Addendum B "5.2 now". Phase 2.1/2.2/2.4 done; 2.3 blocked on cap + key.');
}

/* 11 ─ RISKS */
{
  const s = lightSlide(); pg();
  header(s, 'Eyes open', 'What is not yet true, and how each is handled');
  const risks = [
    ['Not live yet', 'Sentientia has never served a real user; all figures are from test data.', 'UAT tester cohort → Stage B rehearsal → gated cutover. The live platform keeps running untouched meanwhile.'],
    ['Payments stay dark at go-live', 'The gateway fix is unmerged and the Verify-API step unproven against the sandbox.', 'Commerce is disabled and verified disabled at cutover; enablement is a separate, later decision after C1 closes.'],
    ['AI is mock-mode', 'No live model call has ever been made; six features wait on a budget cap and an API key.', 'Fail-closed gateway with spend ledger and hard caps is built and tested; an unset cap means AI stays off, never unlimited.'],
    ['No certifications, no pen-test yet', 'Buyers and auditors will ask early.', 'Trust track funded in principle; VAPT first, then ISO 27001 and SOC 2 - the spend annex is ready for sign-off.'],
    ['UAT sizing is smoke-only', 't3a.small / db.t3.small (2 GB each) cannot host a live-backup rehearsal.', 'Resize to medium class before Stage B; a same-day cloud operation already flagged with DevOps.'],
    ['Small team, big surface', 'The platform was built by a very small team with AI-assisted engineering.', '30 decision records, runbooks and state cards make it reconstructible; a support-engineer hire and the vendor AMC option spread the load.'],
  ];
  risks.forEach((r, i) => {
    const cx = ML + (i % 2) * 6.25, cy = 1.6 + Math.floor(i / 2) * 1.62;
    card(s, cx, cy, 5.88, 1.45);
    s.addText(r[0], { x: cx + 0.18, y: cy + 0.08, w: 5.5, h: 0.32, fontSize: 12, bold: true, color: RED, fontFace: F, margin: 0 });
    s.addText([{ text: r[1] + '  ', options: { italic: true, color: MUTED } }, { text: r[2], options: { color: TEXT } }], { x: cx + 0.18, y: cy + 0.42, w: 5.55, h: 0.98, fontSize: 10, fontFace: F, margin: 0, valign: 'top' });
  });
  takeaway(s, 'Presenting these ourselves is the point: every item has an owner and a mitigation, and none of them is a surprise.');
  footer(s);
  s.addNotes('The key-person risk is the one leadership will feel most; the support-engineer ask (August deck) is its mitigation.');
}

/* 12 ─ DECISIONS */
{
  const s = darkSlide(); pg();
  header(s, 'To close today', 'Decisions requested from this room', true);
  const asks = [
    ['CHRO', 'Green-light the tester cohort and two-week UAT window; nominate the 13 persona testers and a daily triage owner'],
    ['CTO / IT', 'Commit dates for: production reCAPTCHA keys, Entra SSO app registration, office-IP allowlist on the UAT load balancer, UAT resize, production RDS → MySQL 8.4 and PHP 8.3'],
    ['MD & Founder', 'Approve direction on the security & certification programme (₹80-120 lakh envelope; annex ₹52-109 lakh) and the AI operating budget (monthly cap + API key)'],
    ['Product owner', 'Merge the payment-hash fix after one gateway sandbox round-trip (closes C1 and M5)'],
    ['All', 'Note the go-live gate: a passed live-backup rehearsal with 100 % data parity - not a calendar date'],
  ];
  asks.forEach((a, i) => {
    const cy = 1.7 + i * 0.95;
    s.addShape('roundRect', { x: ML, y: cy, w: 12.13, h: 0.82, rectRadius: 0.06, fill: { color: NAVY2 }, line: { color: '1C6EA8', width: 0.75 } });
    s.addShape('roundRect', { x: 0.82, y: cy + 0.2, w: 1.75, h: 0.42, rectRadius: 0.05, fill: { color: i === 4 ? ACCENT : ORANGE } });
    s.addText(a[0], { x: 0.82, y: cy + 0.2, w: 1.75, h: 0.42, fontSize: 11, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    s.addText(a[1], { x: 2.8, y: cy + 0.06, w: 9.8, h: 0.7, fontSize: 12.5, color: WHITE, fontFace: F, margin: 0, valign: 'middle' });
  });
  footer(s, true);
  s.addNotes('Items 3 asks for direction, not final numbers - the security spend annex (Aug 2026) and an AI cap estimate follow as annexes. Item 5 is a note, not an approval: it protects everyone from date pressure.');
}

/* 13 ─ DEMO PATH */
{
  const s = lightSlide(); pg();
  header(s, 'The live demo', 'Twenty minutes, seven stops, one environment');
  const stops = [
    ['1', 'Guest', 'Landing page → public catalogue: the storefront story', '3 min'],
    ['2', 'Learner (Priya)', 'Dashboard with progress and badge, open a course, SCORM walkthrough, certificates', '4 min'],
    ['3', 'Manager (Vikram)', 'Team dashboard, a report\'s progress, compliance RAG with an overdue flag, approve a request', '3 min'],
    ['4', 'Trainer (Arjun)', 'Run the live poll - the audience answers from their phones', '3 min'],
    ['5', 'L&D admin (Meera)', 'Manage Users / Courses, compliance report + Excel export, analytics, the feature Switchboard', '3 min'],
    ['6', 'Tenant isolation', 'Log in as the ZEEA admin: only ZEEA data, in its own compliance view', '2 min'],
    ['7', 'Close', 'Hindi toggle on a dashboard; the migration story - same domain, full history carried over', '2 min'],
  ];
  let y = 1.6;
  stops.forEach(st => {
    s.addShape('roundRect', { x: ML, y, w: 12.13, h: 0.62, rectRadius: 0.05, fill: { color: WHITE }, line: { color: BORDER, width: 0.75 } });
    s.addShape('ellipse', { x: 0.78, y: y + 0.11, w: 0.4, h: 0.4, fill: { color: PRIMARY } });
    s.addText(st[0], { x: 0.78, y: y + 0.11, w: 0.4, h: 0.4, fontSize: 12, bold: true, color: WHITE, align: 'center', valign: 'middle', fontFace: F, margin: 0 });
    s.addText(st[1], { x: 1.4, y, w: 2.5, h: 0.62, fontSize: 12, bold: true, color: TEXT, fontFace: F, margin: 0, valign: 'middle' });
    s.addText(st[2], { x: 4.0, y, w: 7.6, h: 0.62, fontSize: 11, color: MUTED, fontFace: F, margin: 0, valign: 'middle' });
    s.addText(st[3], { x: 11.6, y, w: 1.0, h: 0.62, fontSize: 11, bold: true, color: PRIMARY, fontFace: F, align: 'right', margin: 0, valign: 'middle' });
    y += 0.7;
  });
  takeaway(s, 'Explained, not clicked: outbound email and WhatsApp (switched off on UAT), real payments (gateway not connected), SSO/MFA (keys pending), live AI generation (mock mode).');
  footer(s);
  s.addNotes('Persona credentials are in the confidential UAT handout, never on a slide. Every stop was walked and screen-verified between 7 and 17 September.');
}

/* 14 ─ APPENDIX: SEPTEMBER FIX LOG */
{
  const s = lightSlide(); pg();
  header(s, 'Appendix', 'What changed on UAT between 4 and 17 September');
  const rows = [
    ['Area', 'Finding', 'Outcome'],
    ['Security', 'C2 cross-tenant self-enrol · H1 enumeration · H3/H4 rate limits · M1/M3/M4 headers', 'Fixed and verified on UAT (3-4 Sep)'],
    ['Realtime', 'Live-session stream buffered by the web server (F-11)', 'Fixed at the proxy; first byte in 0.05 s'],
    ['Roles', 'Course author landed on a learner shell (T-01)', 'Author role seeded on install + upgrade; authoring nav live (8 Sep)'],
    ['Tenancy', 'Admin dashboard and compliance report leaked other tenants\' figures and filters', 'Every widget, filter and export tenant-scoped; screen-verified (16-17 Sep)'],
    ['Language', 'English fragments and escaped characters on dashboards, login, filters, reports', 'Theme 332/332 + plugin packs at parity; 16 on-screen checks passed'],
    ['Content', 'SCORM untested on the new stack', 'Real SCORM 1.2 package plays; files serve correctly'],
    ['Branding', 'Footer carried an open-source licence badge', 'Replaced with Airpay\'s private-and-confidential notice (en + hi)'],
    ['Ops', 'Backup/restore and load never measured on UAT', 'Restore drill 44 s; 20-user baseline 0 errors, p95 ≤ 1 s; daily log-scan'],
  ];
  let y = 1.6;
  rows.forEach((r, i) => {
    const head = i === 0, h = head ? 0.42 : 0.56;
    s.addShape('rect', { x: ML, y, w: 12.13, h, fill: { color: head ? NAVY : (i % 2 ? 'F7FAFD' : WHITE) }, line: { color: BORDER, width: 0.5 } });
    const xs = [0.72, 2.25, 8.0], ws = [1.45, 5.65, 4.6];
    r.forEach((c, j) => s.addText(c, { x: xs[j], y: y + 0.04, w: ws[j], h: h - 0.08, fontSize: head ? 11 : 10, bold: head || j === 0, color: head ? WHITE : (j === 0 ? TEXT : MUTED), fontFace: F, margin: 0, valign: 'middle' }));
    y += h;
  });
  s.addText('Every row has a commit, a checksummed deploy record and, where it is visual, a screenshot folder under docs/visual-evidence. Versions on UAT (17 Sep): theme 2026090805, courses 1.11.5, compliance report 1.0.2, gamification 1.0.3, content market 1.0.2.', { x: ML, y: y + 0.15, w: 12.1, h: 0.6, fontSize: 10, italic: true, color: MUTED, fontFace: F, margin: 0 });
  footer(s);
  s.addNotes('Backup slide for the CTO. One further theme fix (Hindi login placeholders) is committed and awaits the next deployment window.');
}

pres.writeFile({ fileName: __dirname + '/Airpay-Academy-2.0-Sentientia-LMS-UAT-Showcase-2026-09.pptx' }).then(() => console.log('deck written, slides:', pageNo));
