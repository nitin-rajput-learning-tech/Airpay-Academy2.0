# Airpay Academy — UI/UX Design Manifesto

**Date:** 2026-05-14
**Companion to:** `PLATFORM-EVOLUTION-ROADMAP-2026-2027.md`
**Scope:** Every pixel a learner, manager, L&D admin, or super admin sees.

---

## 0. The bar

The Head of L&D said this is "the biggest delivery of my corporate career." That sets the bar.

**Not the bar:** "Better than the BizLMS we forked."
**Not the bar:** "Looks more modern than Moodle out-of-the-box."
**The bar:** When someone opens Airpay Academy on their iPad on the way to work, the moment feels like opening Linear, Notion, or Things-3 — not like opening a corporate training portal from 2015.

This manifesto is the standard everything is measured against. If a pull request makes a screen feel more like a 2015 Moodle theme and less like Linear, it doesn't ship.

---

## 1. Design principles (the 7 we never break)

### 1.1 Mobile is the canonical surface
Phones are the primary device for ~70% of learners (Airpay = India market = consumer-grade plans, commute time, intermittent network). Every feature is **mobile-first** — designed for thumb-reach on a 5.8" screen, expanded to tablet + laptop, **never the other way around**.

If a feature can't work on a phone, it doesn't ship until it can. Exception: super-admin configuration surfaces (3 people max; laptop OK).

### 1.2 Touch-first, even on laptops
Click targets minimum **44×44 pt** (Apple HIG) everywhere. No hover-only interactions. Drag-drop is a progressive enhancement, not the primary input. Long-press is a contextual-menu trigger, not the primary action.

### 1.3 Content is the interface
Course thumbnails, learner avatars, instructor faces, badge icons — these are the UI. Chrome (navigation, headers, tabs) gets smaller; content gets larger. Every page asks: "what's the one thing this screen is for?" and makes it the visual focus.

### 1.4 Motion is meaning, not decoration
Animations exist when they explain a change of state (item moved, card flipped, page transitioned). Decorative parallax, marquees, autoplay video on hover — never. 200ms ease-out is the default for state changes; 400ms for layout shifts; instant for input feedback.

### 1.5 Empty states are scenes
A blank "no courses yet" screen is a missed opportunity. Every empty state has illustration + 1-sentence orientation + 1 primary CTA. The CTA tells the user the next 30 seconds.

### 1.6 Voice is human, not corporate
"You completed AML Foundations 🎉" beats "Course completion has been recorded successfully." We borrow from Mailchimp's mascot-era voice book — warm, lightly playful, honest. We never use the word "leverage" or "synergize."

For compliance content specifically, voice gets more formal — regulators expect it. Tone shifts contextually, not globally.

### 1.7 Accessibility is a constraint, not a feature
WCAG 2.2 AA is the floor (we're at 2.1 AA today). Every screen designed mobile-first survives screen-reader navigation, keyboard-only operation, and 200% browser zoom. Colour-blindness palettes (Deuteranopia + Tritanopia) tested on every dashboard.

---

## 2. Visual identity

### 2.1 Colour roles (semantic, not decorative)

```
Brand
  primary       #0066A7  Airpay blue       — CTAs, links, active nav
  primary-dark  #004D7F  hover/pressed     — interactive feedback
  primary-tint  #E6F1F8  surface backgrounds for primary actions
  accent        #0F7A73  teal              — secondary actions, badges, tags
  accent-tint   #E0F1EF                    — accent surface

Semantic (status)
  success       #15803D  (small-text safe contrast on #f8f9fc surface)
  warning       #B45309
  danger        #B91C1C
  info          #2563EB

Neutrals (8-step scale)
  gray-50  #FAFAFA  page background
  gray-100 #F5F5F5  card divider, subtle surface
  gray-200 #E7E7E7  borders
  gray-400 #A3A3A3  disabled text, placeholder
  gray-600 #525252  body text muted
  gray-900 #171717  body text default
  black     #000000  high-emphasis labels only
```

**The colour-blind safe palette** for status badges uses dark variants — green `#15803D` (not `#16a34a`), amber `#B45309` (not `#d97706`), red `#B91C1C` (not `#dc2626`). These hit 4.5:1 contrast for small text on `#f8f9fc` backgrounds (verified during Sprint B / Engineering 21).

### 2.2 Typography

```
Display    Montserrat 700/800  -1% letter-spacing  for hero titles
Heading    Montserrat 600       0% letter-spacing  H1-H3
Body       Inter 400/500        normal             content
Mono       JetBrains Mono       normal             code, IDs, ticket numbers
Numeric    Inter Tabular        normal             metrics, scores
```

Why mix two families: Montserrat reads beautifully at headline sizes but it's geometric and tiring at body. Inter is the body workhorse — designed for screens, optimised at every size.

Numeric (tabular figures) means numbers in dashboards have equal-width glyphs — column alignment without monospace pain.

### 2.3 Spacing — the 4pt grid

Every margin, padding, and gap is a multiple of 4px. The full scale:

```
2pt  4pt  8pt  12pt  16pt  24pt  32pt  48pt  64pt  96pt
```

This is the same grid Apple, Stripe, Linear, and Notion use. It's not a stylistic choice — it's mechanical sympathy with how humans perceive visual rhythm.

### 2.4 Elevation (shadow) — restrained

Only 4 elevation levels exist. Anything more is showing off.

```
shadow-flat   none                                  cards on cards
shadow-1      0 1px 2px rgba(0,0,0,0.04)            cards, inputs
shadow-2      0 4px 8px rgba(0,0,0,0.06)            elevated cards on hover
shadow-3      0 8px 24px rgba(0,0,0,0.10)           modals, dropdowns
shadow-4      0 24px 48px rgba(0,0,0,0.16)          full-screen overlays
```

### 2.5 Border radius — soft but not playful

```
2pt   small chips
6pt   buttons, inputs, small cards (default)
12pt  large cards, modals
24pt  hero illustration containers
pill  status pills, avatars
```

We don't use 0px corners (too engineering-feel) or 100px corners (too consumer-app).

---

## 3. Responsive breakpoints

Six breakpoints, each tied to a real device class. NOT "small / medium / large" — those are meaningless on a 13" laptop with 1440px display.

```
mobile-s   < 380px   Galaxy S series, iPhone SE  (smallest supported)
mobile     < 590px   iPhone Pro, Pixel
tablet-s   < 768px   iPad mini portrait, foldable inner
tablet     < 992px   iPad portrait, iPad Air landscape, surface
laptop     < 1280px  13" laptops at 100%/125% zoom
desktop    < 1600px  external monitors, 14-16" at 100%
wide       ≥ 1600px  ultrawide, 4K (max content width caps at 1440px)
```

**Layout shifts at each breakpoint** (not just font-size scaling):
- `mobile-s` → `mobile`: 1-column, full-bleed images, hamburger nav
- `tablet-s` → `tablet`: 2-column where helpful, persistent left nav appears, side sheets replace bottom sheets
- `laptop` → `desktop`: 3-column for content lists, sidebar pinned, hover states active
- `wide`: 4-column dashboards, content area capped at 1440px (centred), persistent right sidebar for context

---

## 4. Component patterns — the canonical set

We don't write CSS for every screen. We compose from a curated component library. Naming and behaviour borrowed from shadcn/ui + Linear + Notion conventions.

### 4.1 Navigation
- **Top bar** (chromebar): logo + breadcrumbs + universal search + AI assistant button + avatar
- **Side nav** (drawer on mobile, pinned on laptop+): vertical list with section dividers
- **Tab bar** (mobile bottom, laptop top): 3-5 primary destinations per surface
- **Command palette** (Cmd+K everywhere): power-user shortcut to any action
- **Breadcrumbs** (laptop+ only): path from root, last 3 levels max

### 4.2 Content surfaces
- **Card** — the fundamental unit. 12pt radius, shadow-1, padding 16-24pt
- **Sheet** — bottom sheet on mobile, side drawer on tablet+, modal on laptop+ for the same content
- **Modal** — disruptive, full-screen on mobile, centred 600pt on laptop+
- **Tooltip** — hover (laptop+) or long-press (touch); never essential information
- **Popover** — anchored, non-blocking, for secondary actions
- **Skeleton loader** — every async card has a matching skeleton; never a spinner

### 4.3 Forms
- **Input** — 44pt height, label above, inline error below, autofocus first field on modals
- **Select / combobox** — native picker on mobile, searchable list on tablet+
- **Date picker** — native on mobile, custom calendar on laptop+
- **File upload** — drag-drop on laptop+, picker on mobile
- **Multi-step** — progress dots on mobile, side stepper on tablet+
- **Inline edit** — click to edit on laptop+, edit button on touch

### 4.4 Feedback
- **Toast** — bottom-centre on mobile, top-right on laptop+; auto-dismiss 4s; one at a time
- **Banner** — full-width strip; dismissible; for system-level state (offline, maintenance)
- **Alert** — in-context, never auto-dismiss; for warnings and errors
- **Confetti** — first 3 course completions only, per user; never spam

### 4.5 Data display
- **Table** — laptop+ only. Mobile fallback is a card list. NEVER horizontal-scroll tables on mobile
- **List** — primary mobile pattern. Tap row = drill-down, swipe-left = quick action
- **Stats card** — single number + label + delta arrow + trend sparkline
- **Charts** — Recharts/Visx vibe; small inline sparklines on cards, full charts on `/analytics` pages

### 4.6 Specialty (LMS-specific)
- **Course card** — thumbnail (16:9) + title + category chip + duration + progress ring; tap entire card
- **Lesson card** — vertical thumbnail (9:16 for video-heavy) + title + chapter list collapsed
- **Streak widget** — flame icon + day count + "keep it going" CTA
- **Skill chip** — icon + name + level badge (L1-L5); colour by category
- **Badge** — circular, illustrated, with subtle gradient; only show on profile + completion screens
- **Compliance pill** — green/amber/red with status text; never colour-only
- **Cohort presence** — avatar stack + "Mira + 8 others learning this now"

---

## 5. Motion language

### 5.1 Durations (tokens)

```
instant   0ms    input feedback (button press, checkbox tick)
quick     150ms  small element state changes
default   250ms  most transitions (open, close, fade)
slow      400ms  layout-affecting transitions
deliberate 700ms celebration moments only (confetti, badge earn)
```

### 5.2 Easing

```
ease-out         cubic(0.16, 1, 0.3, 1)    incoming elements
ease-in          cubic(0.7, 0, 0.84, 0)    outgoing elements
ease-in-out      cubic(0.5, 0, 0.5, 1)     bidirectional
spring-soft      mass=1, stiffness=180     emphatic incoming
```

### 5.3 The motion taxonomy

| Motion | When | Token |
|--------|------|-------|
| Fade in | Toast appears, card content loads | `quick + ease-out` |
| Slide up | Bottom sheet opens | `default + spring-soft` |
| Slide from right | Side drawer opens (tablet+) | `default + ease-out` |
| Crossfade | Tab switch, page transition | `default + ease-in-out` |
| Scale in | Modal opens, popover anchors | `quick + ease-out` |
| Pulse | Badge earned (one-time) | `deliberate + spring-soft` |
| Shake | Form validation error | `quick + ease-in-out` |

**Never use:** parallax scroll, autoplay video carousels, bounce-on-press buttons (feels 2014).

### 5.4 Reduced motion

`prefers-reduced-motion: reduce` users get instant transitions (0ms) for everything except essential state feedback. We test this on every PR.

---

## 6. Voice and copy

### 6.1 The 8 voice rules

1. **Use "you" and "your"** — never "the user"
2. **Active voice** — "We've sent the certificate" not "The certificate has been sent"
3. **One idea per sentence** — split it
4. **No jargon unless it's the user's** — "course completion" yes, "completion event upserted" no
5. **Front-load the verb** — "Continue learning" not "It's time to continue learning"
6. **Numbers, not adjectives** — "3 days left" not "soon"
7. **Lighter for learners, formal for compliance** — context shifts the dial
8. **Never apologise for failures we can't explain** — "Something went wrong" is OK; "We're so sorry, our system is having difficulties" is corporate fluff

### 6.2 The microcopy catalogue (examples)

```
Empty state — no enrolled courses
TITLE:    "Let's get started"
BODY:     "Browse the catalogue to find your first course."
CTA:      "Browse courses"

Empty state — no overdue
TITLE:    "Nothing to chase today"
BODY:     "Your team is on top of their training. Nice."

Loading state — analytics
LABEL:    "Crunching numbers…"  (≤ 2s)
LABEL:    "Still going — large dataset"  (> 2s)
LABEL:    "Almost there"  (> 5s)

Error — generic
TITLE:    "Something went sideways"
BODY:     "We've logged it. Try again in a moment, or contact support."
CTA:      "Try again"

Success — course completed
TITLE:    "🎉 You did it"
BODY:     "{course} is now in your completed list. Certificate on its way to your email."
CTA:      "Find another course"
```

### 6.3 Localisation

Strings localised at the source. We support English (canonical), Hindi, Marathi, Kannada, Swahili today. New strings ship in English first; translations get queued for the next translation review cycle.

**Hindi-specific note:** No transliterated Hinglish in formal UI. "Login karein" = no. "लॉगिन करें" = yes. Mixed-script copy reads sloppy and disrespects native readers.

---

## 7. Reference apps (what good looks like)

Whenever a design question feels ambiguous, look at how these apps solve it:

### 7.1 Mobile-native enterprise feel
- **Linear** — keyboard shortcuts, command palette, sub-100ms interactions
- **Things 3** — animation tokens, empty states, sound design
- **Cron / Notion Calendar** — date-heavy UI on mobile
- **Slack mobile** — chat, presence, push notification UX
- **GitHub Mobile** — surface-density, side drawer, sheet patterns

### 7.2 Cross-device parity
- **Notion** — same data, three layouts (mobile / tablet / desktop)
- **Figma** — touch-aware on iPad, hover-rich on desktop
- **Stripe Dashboard** — laptop-first that still works on mobile

### 7.3 Onboarding + empty states
- **Loom** — first-run tour, then disappears
- **Linear** — illustrated empty states with one clear CTA
- **Webflow Cloneables** — copy that frames the next 30 seconds

### 7.4 Data density (admin surfaces)
- **Retool** — building admin UIs without sacrificing taste
- **Plaid Dashboard** — high-information density, clean
- **Cloudflare Dashboard** — settings + monitoring + audit logs

### 7.5 Habit + engagement
- **Duolingo** — streak mechanics, sound, celebration moments
- **Strava** — social-presence without becoming Facebook
- **Headspace** — gentle daily nudge, never aggressive

### 7.6 Anti-references (DON'T LOOK LIKE THIS)
- **Most Moodle themes** (literally — we are forking away from this)
- **SAP SuccessFactors** (enterprise dead-zone)
- **Workday** (information without delight)
- **Cornerstone OnDemand** (functional but joyless)

---

## 8. The "iPad mode" — a specific commitment

iPad/tablet is where corporate training actually happens (manager doing 1:1s, L&D admin reviewing a learning path while on a call). We commit to iPad as a **first-class device**, not a "shrunk laptop."

Specific iPad commitments:
1. **Multitask split-view** support — every layout works at 50% width
2. **Apple Pencil** annotations on PDFs and certificates (Phase Γ)
3. **Two-finger gestures** — pinch to zoom in dashboards, two-finger pan to scroll
4. **Drag-drop** from app to app (e.g. drag a learner from Manager dashboard into a Learning Path)
5. **Hover-cursor support** (Magic Keyboard) — different from touch
6. **Stage Manager-friendly** window sizes

---

## 9. Accessibility commitments

WCAG 2.2 AA is the floor. Specifics:

- **Colour contrast 4.5:1** for body text, 3:1 for large text + icons
- **Focus rings** visible on every interactive element (1.4.11)
- **Skip-to-main-content link** on every page (2.4.1)
- **Form labels** never disappear into placeholders (3.3.2)
- **Touch targets** 44×44pt minimum (2.5.5)
- **Drag without alternative** never the only way to do something (2.5.7)
- **Animation pause** for >5s animations (2.2.2)
- **Captions** auto-generated for every video, instructor-editable (1.2.2)
- **Transcripts** searchable, time-indexed (1.2.3)
- **Screen reader** tested via VoiceOver on iOS + NVDA on Windows monthly

We audit accessibility via axe-core CI gate (already shipped for the cron_health + cert_health blocks; expand to every screen in Phase Z).

---

## 10. What this manifesto does NOT cover

- Marketing site / public-facing pages (different brand surface, separate guidelines)
- Email templates (covered in `airpay_emails` plugin docs)
- Internal Slack bots or CLI tools (developer surfaces, separate standards)
- PDF certificate layouts (covered by `tool_certificate` template editor)

---

## 11. How this manifesto gets enforced

1. **Storybook** — every component documented with stories; PR review checks against Storybook stories first
2. **Design tokens published** — `theme/airpayux/tokens.json` + CSS custom properties; designers + developers consume the same source
3. **Figma library synced** — designer's component library matches `tokens.json` 1:1
4. **a11y CI gate** — axe-core runs on every PR for every changed page
5. **Visual regression tests** — Percy or Chromatic snapshots; UI changes need approval
6. **The 30-second test** — for any new screen, ask: "What would Linear do?" If we can't articulate the difference between our screen and a hypothetical Linear version, redesign.

---

**This manifesto is a living document. It evolves as we learn. But every change requires a PR with rationale — we don't drift the standard accidentally.**
