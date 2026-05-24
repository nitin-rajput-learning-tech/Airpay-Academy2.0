# Airpay Academy — Translation Review Instructions for Claude Cowork

## What This Is

You have a CSV file (`airpay_translations.csv`) containing **337 UI strings** from Airpay Academy (a Moodle-based corporate Learning Management System). Each row has:

| Column | Description |
|--------|-------------|
| **Plugin** | Which Moodle plugin the string belongs to (e.g., `local_airpay_catalog`) |
| **String Key** | The Moodle string identifier (e.g., `continuelearning`) — DO NOT change this |
| **English** | The source English text |
| **Hindi (hi)** | Current Hindi translation — needs review |
| **Marathi (mr)** | Current Marathi translation — needs review |
| **Swahili (sw)** | Current Swahili translation — needs review |
| **Kannada (kn)** | Current Kannada translation — needs review |

## Your Task

Review and improve ALL translations in columns D-G (Hindi, Marathi, Swahili, Kannada) following these rules:

### Translation Style Guide

1. **Conversational, not bookish** — Write how people actually talk in daily life, NOT formal/literary/textbook language
   - ❌ Hindi bookish: "कृपया अपना उपयोगकर्ता नाम प्रविष्ट करें"
   - ✅ Hindi conversational: "अपना यूज़रनेम डालें"
   
2. **Use common loanwords** — If people commonly use the English word in daily speech, keep it (transliterated)
   - ✅ "डैशबोर्ड" (dashboard), "प्रोफाइल" (profile), "लॉगिन" (login), "OTP", "API"
   - ❌ Don't force-translate technical terms that nobody uses in the local language
   
3. **Keep it short** — UI strings appear in buttons, labels, and menus. Shorter is better.
   - ❌ "कृपया और अधिक पाठ्यक्रम लोड करें"
   - ✅ "और लोड करें"

4. **Preserve placeholders** — Never translate `{$a}`, `{$a->gaps}`, `{$a->total}`, `{$a->percentage}`, `{$a->met}` — these are Moodle variables that get replaced with numbers at runtime
   - ✅ "आज {$a} सवाल बाकी हैं"
   - ❌ "आज कुछ सवाल बाकी हैं" (lost the variable!)

5. **Context awareness** — These strings appear in an LMS (Learning Management System). Context:
   - "Course" = an online training course (not a meal course or a race course)
   - "Module" = a section within a course
   - "Enroll" = register/sign up for a course
   - "Streak" = consecutive days of activity (gamification concept)
   - "Badge" = a digital achievement reward
   - "Compliance" = mandatory regulatory training
   - "Leaderboard" = ranking of top performers by points

### Language-Specific Notes

**Hindi (hi)**
- Target audience: Indian corporate employees (Airpay Payment Services, Mumbai)
- Mix of formal and informal — professional but friendly
- Hinglish is acceptable for tech terms (डैशबोर्ड, प्रीव्यू, एक्सपोर्ट)
- Use Devanagari script throughout

**Marathi (mr)**
- Target audience: Airpay employees in Maharashtra
- Professional Marathi with common English loanwords
- Similar tone to Hindi but in Marathi grammar/vocabulary
- Use Devanagari script throughout

**Swahili (sw)**
- Target audience: ZEEA employees in Tanzania
- Standard Swahili (Kiswahili sanifu) — not slang
- Professional but approachable — like how a Tanzanian office worker would speak
- Reference: The existing Airpay Tanzania website (airpay.tz) uses this tone

**Kannada (kn)**
- Target audience: Future expansion — Airpay employees in Karnataka
- Standard Kannada — professional tone
- Use Kannada script throughout
- Transliterate tech terms when no common Kannada equivalent exists

### What to Check For

For each row, verify:

1. **Accuracy** — Does the translation match the English meaning?
2. **Tone** — Is it conversational, not bookish?
3. **Completeness** — Is ANY translation missing (empty cell)? Fill it in.
4. **Placeholders** — Are `{$a}`, `{$a->name}` etc. preserved exactly?
5. **Length** — Is the translation unreasonably longer than English? Shorten if possible.
6. **Consistency** — Same English term should translate the same way everywhere:
   - "Course" → same word in all rows
   - "Enroll" → same word in all rows
   - "Dashboard" → same word in all rows
   - "Compliance" → same word in all rows
   - "Badge" → same word in all rows

### Plugin Context Guide

| Plugin | What it does | User sees it on |
|--------|-------------|-----------------|
| `local_airpay_catalog` | Course browsing & search | Catalog page — carousels, search, filters |
| `local_airpay_gamification` | Points, badges, streaks, leaderboard | Dashboard sidebar, profile |
| `local_airpay_compliance_report` | Mandatory training tracking | Compliance dashboard (admin + managers) |
| `local_airpay_skills` | Skill gap analysis | Skills page, profile |
| `local_airpay_notifications` | Bell alerts & notification center | Navbar dropdown |
| `local_airpay_privacy` | DPDP data rights (download, delete) | Privacy self-service page |
| `local_airpay_assistant` | AI chatbot (Claude-powered) | Floating chat bubble |
| `local_airpay_analytics` | Learning analytics dashboard | Analytics page (admin) |
| `local_airpay_pages` | Static pages (privacy, terms, help) | Footer links |
| `local_airpay_emails` | Email template management | Admin email panel |
| `theme_airpayux` | Theme UI labels, settings | Navbar, login page, footer, admin settings |

### Strings You Can Skip (Admin-Only)

These string keys are admin-facing settings descriptions — lower priority, can be translated loosely or skipped:
- Any key ending in `_desc` (e.g., `autoenrol_desc`, `ratelimit_desc`)
- `privacy:metadata` strings
- `presetfiles_desc`, `rawscss_desc`, `rawscsspre_desc`
- `settingsheading`, `settingsdesc`

Focus your quality effort on **user-facing strings** — buttons, labels, messages, and navigation text that learners see daily.

## Output Format

Return the same CSV structure with your corrections applied. If a translation is correct, leave it as-is. Only change cells that need improvement.

Mark changed cells by adding `[REVIEWED]` at the start of the cell so we can track what was human-verified vs machine-generated:
- `[REVIEWED] और लोड करें` — means you reviewed and corrected this
- If the original was already correct, just add `[OK]`: `[OK] कोर्स कैटलॉग`

## How to Process

1. Go through the CSV row by row
2. Read the English string
3. Check all 4 translations against the style guide
4. Fix any issues (bookish language, missing placeholders, wrong meaning, too long)
5. Mark each cell as [REVIEWED] (changed) or [OK] (verified correct)
6. Return the complete updated CSV

Start with Hindi (column D) since it's the most critical for Airpay India, then Marathi, then Swahili (for ZEEA Tanzania), then Kannada.
