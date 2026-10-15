## A. Patterns in October 2026

Evidence note: every citation [n] resolves to section E. Where a page could not be fetched (HTTP 403, socket errors) the claim rests on a search-result summary and E says so. Vendor pages describe what vendors say they ship, not what was independently tested.

**A1. Document-to-draft generation with staged human gates**
- What it is: the author supplies a prompt and/or a source file; the tool proposes course details and an outline; the author edits the outline; only then does it generate lessons, which open in an editor for manual or AI-assisted refinement. Rise 360 runs course details -> objectives -> outline -> "Generate lessons" [29]. Mindsmith adds a storyboard step (style, tone, objectives, page outlines) before the draft [30][31]. 360Learning starts from a prompt, a file, or both, and leaves the generated course in the edit screen for review before sharing [32]. Sana converts PDFs, decks and SCORM files into editable courses [39][40].
- Why it matters for regulated L&D: an SOP-to-course pipeline is where wrong wording enters. An approval gate before the expensive generate step, and a second gate before publish, is where accountability sits. 360Learning states hard input limits (500 to 100,000 characters, text only, images not processed, speaker notes ignored by default) [32], which is the kind of constraint to show before upload, not after a failed run.
- Evidence: [29][30][31][32][39][40]. All vendor pages (vendor-marketing y).

**A2. Source-fidelity control and verbatim protection**
- What it is: a visible setting for how closely the AI follows the source versus reshaping it (Mindsmith offers the choice [30]). Rise community threads report the model deviating from a script when verbatim text was wanted, and one reply names a Magic Text Import block as the only protected route [29]. 360Learning reports it underlines parts of a document-based course that it considers non-compliant [32].
- Why it matters: compliance and SOP text often must be verbatim or traceable. A fidelity dial, a verbatim lock and a passage-level flag are the controls an auditor can reason about.
- Evidence: [29][30][32]. Community claims are user reports, not vendor statements.

**A3. AI provenance mark, explanation on demand, and revert**
- What it is: Carbon marks AI-generated content with a consistent label that opens an explainability popover; explanation is shown when requested, not by default; when a user edits AI content the AI styling is removed and a revert control restores the original AI output; the label is not an action trigger such as "regenerate" [18][19]. The CHI 2019 human-AI guidelines open with "make clear what the system can do" and "how well" [62].
- Why it matters: reviewers need to see which text a human changed and which text the model wrote; transparency duties for AI-generated content are live in the EU from 2 August 2026 according to secondary sources, with a disputed grace period for marking [63] (low confidence, not checked against the Official Journal; India has no equivalent verified in this study).
- Evidence: [18][19][62][63].

**A4. Progressive disclosure capped at two levels, with a bounded first screen**
- What it is: show frequently needed options first, move the rest to one secondary level; three or more levels disorient; staged wizards suit independent steps and fail when users must flip back and forth [1]. The same logic appears as Carbon's batch-action bar that appears only after rows are selected [20], and Metabase's advice to split a dashboard past about 10 cards [60].
- Why it matters: authoring, review and admin screens in an enterprise product are dense by nature; hiding with discipline keeps the primary task visible without removing audit detail.
- Evidence: [1][20][60].

**A5. "Assigned and due" separated from "discover and optional" on the learner home**
- What it is: Degreed's home has an Assigned Learning tab for required and priority content beside Continue Learning and Personalized Learning [38]. Docebo has a Task list widget (assigned courses, a Deadlines tab counting courses due within 90 days) and an "Overdue" state on course cards [33]; admins building mandatory-only pages with filters is a community workaround [34]; manager views filter overdue or due within 14 days [35].
- Why it matters: for compliance training the first question is "what must I finish and by when". Mixing mandatory and recommended content hides the legal obligation.
- Evidence: [33][34][35][38]. Vendor pages; [34] is a user-contributed post.

**A6. The translation workspace as grid plus context pane plus QA severities**
- What it is: source on the left and target on the right, a side pane for TM suggestions with match percentage, TM name, date and contributor, a configurable minimum match, a "request context" action, neighbouring strings, and QA checks where each type is set to Error (blocks saving) or Warning (can be overridden), often with one-click fixes [43][44][45]. memoQ shows match scores with colour-coded differences, orange warnings versus blocking errors, a QA sidebar groupable by row or category with ignore and restore, and grid filters by status [48][49][50].
- Why it matters: a two-stage human review needs per-segment status, an explicit override trail for warnings, and a way to see what is left. These editors have converged on exactly that.
- Evidence: [43]-[45][48]-[50]. Vendor documentation (vendor-marketing y but factual product docs).

**A7. Tiered quality: automated QA, human review, sampled LQA with MQM scoring**
- What it is: Phrase models LQA as a workflow step scored against MQM-based error categories with severity penalties and a pass/fail threshold [46]. Smartling describes AI first draft, automated checks, linguist review with visual context, MQM-compatible LQA, and an AI quality-estimation agent that can route "High" strings past human review [51][52][53][66]. Human translations are always saved to TM, raw unedited MT typically is not [53][66].
- Why it matters: regulated content should adopt the scoring, severity and TM-hygiene parts, and decline the "skip human review when predicted quality is high" part (my judgement, not a source claim). The routing claim comes from Smartling marketing and help pages; the exact page is not pinned in the search summary.
- Evidence: [46][51][52][53][66]. Vendor-marketing y.

**A8. Node-graph branching editors with visible validation and scenario-design discipline**
- What it is: Twine shows a link that matches no passage as a red line with a "no entry" symbol on the story map (an older release note describes an arrow ending in an X; the two sources disagree on the glyph) [55]. H5P Branching Scenario structures content as a tree with multiple branches and endings, per-ending custom feedback and score, and a branching-question-driven model [54]. Mindsmith offers a node canvas and a prompt-to-tree generator, claiming every path is validated before publishing (vendor claim) [30][31]. Practitioner guidance: plan decision points first, build depth, let consequences teach, delay feedback on high-stakes errors, provide recovery paths [56][57]; use score variables with range logic and order triggers so the score updates before navigation [58].
- Why it matters: decision-level analytics only works if every node has a stable identity, an author can prove no dead ends exist, and scoring logic is predictable.
- Evidence: [54]-[58]. [30][31][58] vendor-marketing y.

**A9. Dashboards: few cards, quantity encoded by length and position, shared filters, definitions and freshness visible**
- What it is: NN/G says length and 2D position are the strongest encodings for quantity and area is a weak one (the treemap article calls area non-preattentive) [4][64]; remove superfluous graphics (icons beside numbers make visual search harder) and put precise values in a tooltip on demand [5]. Metabase: split past about 10 cards, shared dashboard filters applied to most cards, descriptive titles and descriptions, drill-through [60]. Docebo documents what Completions Rate and Overdue Rate mean, refreshes Insights every 24 hours, and a community thread shows users doubting overdue figures when soft and hard deadlines mix [36][37].
- Why it matters: auditors and executives ask "what does this number mean and how old is it". A definition and a timestamp on each tile prevents argument.
- Evidence: [4][5][36][37][60][64]. [4][5] and [64] are search-summary reads. [36][37][60] vendor-marketing y.

**A10. WCAG 2.2 AA as the procurement baseline, with new pointer and focus rules**
- What it is: WCAG 2.2 adds 2.4.11 Focus Not Obscured (Minimum, AA), 2.5.7 Dragging Movements (AA), 2.5.8 Target Size (Minimum, AA, 24 by 24 CSS px with spacing and other exceptions), 3.2.6 Consistent Help (A), 3.3.7 Redundant Entry (A), 3.3.8 Accessible Authentication (Minimum, AA), and drops 4.1.1 [9][10][11][13]. Status messages (4.1.3, AA) must be announced without moving focus [12]. Moodle reports WCAG 2.2 AA accreditation for LMS 5.1, 5.0.3 and 4.5.7 (3 October 2025) with partial-support notes in its VPAT [27][28], and tells contributors to prefer plain HTML, give compound widgets one tab stop, and implement APG patterns for custom widgets [26].
- Why it matters: enterprise buyers ask for a VPAT. Node graphs, sortable lists and drag-to-reorder slides are exactly the widgets 2.5.7 targets.
- Evidence: [9]-[14][26]-[28]. [27][28] from search summaries.

## B. Sentientia UX principles

1. One next action, two disclosure levels: each surface names its primary task in the first screen and holds everything else one level down. Rationale: staged disclosure helps novices and experts, but three or more levels disorient [1].
2. Required work is never mixed with optional work: assigned, due and overdue learning is its own region on learner and manager views. Rationale: compliance deadlines are the learner's first question [33][38].
3. Every AI artefact is marked, explainable on request, editable and revertible, and a named human approval is recorded before learners see it. Rationale: reviewers and regulators need to separate model output from human decisions [18][19][62].
4. Fidelity to the source is a visible setting and verbatim text can be locked. Rationale: SOP and policy wording is audited, and community reports show models deviating from supplied text [29][30].
5. Accessible by construction: every drag, hover or colour cue has a keyboard or single-pointer path and a text status. Rationale: WCAG 2.2 AA is the buyer's baseline and node graphs and sortable lists are directly affected by 2.5.7 [11][12][14].
6. Consequential actions are guarded and separated: publish, delete and cut-over show specific consequences, sit apart from benign controls, and offer undo where possible. Rationale: NN/G recommends confirmation before serious or irreversible actions but not as the only protection, and undo builds confidence [6][7][8].
7. Logical direction and early localisation: use start/end rather than left/right, set dir per language span, keep media controls and numbers by the documented rules, and test RTL with real strings. Rationale: W3C and Material both treat mirroring as a layout-wide concern, with explicit exceptions [16][17][65].
8. Every number carries its definition, scope and freshness, and is drillable. Rationale: unlabelled rates (for example overdue with mixed deadline types) produce distrust, and strong encodings (length, position) make quantities readable at a glance [4][36][37].

## C. Per-surface briefs

Shared conventions for all six surfaces (not repeated below): use the project tokens (primary #0066A7, Montserrat, dark mode, reduced-motion tokens) and CSS logical properties. Montserrat is not known to cover Arabic, Urdu or Devanagari (not verified in this study), so define script-specific fallback stacks and verify line-height for Urdu. Use Carbon-style skeletons (not spinners) for table and card loads, kept brief, and never skeletonise menus, toasts or modals [22]. Where a bullet says "(inference)", it is my judgement from the cited evidence, not a source claim.

### C1. Learner home / dashboard

**User and primary task.** Employee, often on a phone, completing assigned training. Task: see what is due and resume or start it in one action.

**Layout to build.**
- Region 1, "Next up" card: course title, due date, estimated minutes, language badge, one primary button (Start or Resume).
- Region 2, "Assigned to you": list sorted by due date; each card has a text status (Overdue, Due in N days, In progress, Not started) plus an icon; secondary tabs "Continue" and "Recommended" so optional content never competes with required content [33][38].
- Region 3, "Recommended" (collapsed by default on mobile) and a small "Certificates" link.
- Persistent help and language selector in the same place on every page (3.2.6 Consistent Help) [9].
- Mobile: single column; do not use a sticky bottom bar that can cover a focused control [13].

**Adopt (5).**
1. Separate Assigned from Continue and Personalized [38].
2. Show deadline and an explicit Overdue state on the card [33].
3. Forgiveness: allow completion after expiry where policy permits (Docebo changed learning plans in October 2025 to let learners finish after enrolment expiry) and repair progress after platform faults (Duolingo reuses streak-freeze logic to repair streaks after outages) [67][42]. Reference only; Duolingo figures are self-reported.
4. Two-level disclosure: card, then course detail; no third level on the home page [1].
5. Announce asynchronous changes (for example "Progress saved", "3 items due") with role=status as whole strings [12].

**Avoid (3).**
1. Streak or loss-aversion mechanics as pressure on mandatory training. Duolingo documents the streak as a habit mechanic with reported retention gains [41]; applying it to compliance deadlines is an inference with a wellbeing risk, so keep any practice streak optional and off the Assigned region.
2. Making mandatory content reachable only through an admin-built filtered page (the Docebo community workaround) [34]; it should be a built-in region.
3. Icons placed beside numbers and decorative widgets that make scanning harder [5].

**Accessibility must-haves.** 1.4.1 and 1.4.3 (status never colour alone, contrast in light and dark); 1.4.10 Reflow; 2.4.7 Focus Visible and 2.4.11 Focus Not Obscured [13][14]; 2.5.8 Target Size 24 px minimum [10]; 4.1.3 Status Messages [12]; 3.2.6 Consistent Help [9]; 2.2.2 for any auto-rotating banner [14].

**Localisation / RTL.** Mirror the card layout with logical properties; apply dir=auto and bdi to user-generated or mixed-script titles [16]; show "Available in" language names in their own script and mark foreign-language titles with lang (3.1.2) [14]; allow for text expansion in every locale; numerals and dates localised by locale, including Indian digit grouping (not verified here).

**Empty / loading / error states.** Empty: "Nothing assigned to you" with a link to the catalogue; the empty state replaces the list, with no stray headers, so a screen reader reaches the message directly [21]. Loading: skeleton cards [22]. Error: card-level retry, last-known data with a "last updated" time, offline notice if the PWA flag is on.

**Metrics to display (learner scope only).** Assigned count; due within 7 days; overdue count; percent of assigned complete; minutes remaining; certificate expiries.

### C2. Course player (narrated slides, captions, language switch)

**User and primary task.** Learner completing a narrated module. Task: understand and finish it in their chosen language, resume where they left, pass the check.

**Layout to build.**
- Slim header: exit, course title, language switch, settings.
- Stage: slide. Directly below: caption band (captions toggle remembered per user). Control bar: play/pause, scrubber, 10-second back, speed, captions, transcript, language.
- Collapsible side panel with the slide outline and current position highlighted, modelled on Moodle's course index drawer, whose open state is a user preference [25]; a transcript tab beside it.
- Footer: Previous / Next with slide count and a text "Saved" status.
- Mobile: stage on top, outline and transcript in a bottom sheet that does not cover the focused control [13].

**Adopt (5).**
1. Synchronised captions plus a full transcript as the media alternative [14][15].
2. Language switch that keeps the slide position and swaps text, captions and audio track together (inference; pattern is the same slide ID across languages).
3. Outline drawer with current-position highlight and persistent open/closed preference [25].
4. Media controls stay left-to-right in RTL locales; directional icons such as back and forward do flip [17].
5. Honour reduced motion for slide transitions (project tokens; WCAG 2.3.3 is AAA but cheap to meet) [14].

**Avoid (3).**
1. Auto-advancing slides or auto-playing audio the learner cannot pause, stop or control (2.2.2 and 1.4.2) [14].
2. Sticky control bars or caption overlays that fully cover the focused element (2.4.11, failure F110) [13].
3. Burned-in captions inside slide images, which cannot be restyled for text spacing or translated (inference; see 1.4.12) [14].

**Accessibility must-haves.** 1.2.2 Captions (A), 1.2.3 audio description or media alternative (A), 1.2.5 audio description (AA) where visuals carry information [14]; 1.4.2 Audio Control; 1.4.12 Text Spacing for captions; 2.1.1 Keyboard with a documented shortcut set; 2.2.2; 2.4.3 and 2.4.7; 2.4.11 [13]; 2.5.8 [10]; 3.1.2 Language of Parts on caption and transcript text; 4.1.2 Name, Role, Value for every custom control; 4.1.3 for "Saved" and "Language changed to Arabic" [12].

**Localisation / RTL.** The language list is shown in native scripts. If the target language has no approved translation, fall back to the source language with a visible notice (links to surface C4 approval state). In RTL, mirror layout and the outline panel but keep the play/scrub controls left-to-right; linear progress indicators move right-to-left for most RTL languages, with Hebrew an exception, per Material [17]. Localise numerals where the locale uses different digits [17]. Do not assume one font fits Latin and Arabic-script captions.

**Empty / loading / error states.** Loading: stage skeleton plus "Loading narration" text. Buffering: spinner on the control only, captions keep timing. Audio failed: captions and transcript continue. Caption track missing or unapproved: banner naming the language and the fallback. Resume: "Continue from slide 7 or start over". Save or SCORM bookmark failure: persistent inline error with retry and no data loss message. Completion: summary with score and next assignment.

**Metrics to display.** Slide progress and time on slide; quiz score and attempts; language used; caption and transcript use (own view only; aggregate in C5).

### C3. AI authoring studio (SOP/document to course)

**User and primary task.** L&D author or SME. Task: turn a source document into a reviewed, publishable course with a defensible audit trail.

**Layout to build.**
- Top status rail with six stages: Source, Outline, Generate, Refine, Review, Publish. It is a status rail, not a locked wizard; NN/G warns that staged wizards fail when users must switch between steps [1], so every stage stays reachable.
- Left: source panel with the document and highlight-on-hover tracing from a slide back to its source passage (inference).
- Centre: outline and slide editor with drag reorder and a button alternative (2.5.7) [11].
- Right: AI panel with settings (fidelity: follow closely / reshape; tone; audience; target languages), cost estimate, run history, and a Mock/Live badge (this project's AI gateway is mock-mode until the live cap and key are approved).
- Status bar: saved state, running cost against cap, review state.
- Review stage: side-by-side diff of AI draft versus current text, a list of flagged passages, approver sign-off, and a Publish summary naming the consequences. Publish sits apart from Save draft [8].

**Adopt (5).**
1. Outline approval before generating full lessons [29][30].
2. State input limits before upload (size, text-only, speaker notes ignored) [32].
3. Fidelity setting plus verbatim lock for protected passages [29][30].
4. AI label with explanation popover on AI-generated blocks; editing removes the AI styling and offers revert [18][19].
5. Selection-scoped refinement ("rewrite this passage") with accept, edit or discard, plus prompt starters and structured controls because users struggle to articulate intent and iterate several rounds in chat [3][29][2][68].

**Avoid (3).**
1. Chat as the only control for refinement; NN/G argues for constraints and hybrid prompt-plus-GUI designs [2][3][68].
2. Losing AI settings and the generated outline on duplicate; Rise 360 copies drop them and staff advise exporting the outline first [29]. Keep run history and settings with the course version.
3. One-click generate-and-publish, or relying on a confirmation dialog alone before publishing compliance content [6][8].

**Accessibility must-haves.** 4.1.3 for generation progress and completion; 2.2.2 for streaming or auto-updating text; 2.5.7 button alternative to drag-reorder [11]; 3.3.4 Error Prevention (Legal, Financial, Data) applied to Publish [14]; 3.3.7 Redundant Entry so audience and tone are not asked twice [9]; 1.4.13 Content on Hover or Focus for the explainability popover (dismissible, hoverable, persistent); 2.4.11 for the sticky status bar [13]; 2.1.1, 4.1.2, 3.3.1, 3.3.3.

**Localisation / RTL.** Authoring UI in English and Hindi at parity. Source text pane uses dir=auto because source SOPs may be in any language [16]. Provide an RTL preview toggle on the slide canvas so authors catch layout problems before translation. Target languages are chosen here but segments are reviewed in C4.

**Empty / loading / error states.** Empty: file constraints, a sample SOP, and "describe the course" with prompt starters [3]. Loading: per-stage text progress with Cancel, announced via role=status [12]. Partial failure: retry per slide, not per course. Error: unreadable or scanned PDF (images are not processed [32]), file too large or too small, cost cap reached (explain, show who can raise it), mock-mode banner. Conflict: someone else edited this outline.

**Metrics to display.** Pre- and post-run cost and token use; source coverage (share of source passages used by at least one slide); share of AI text changed by humans; number of flagged passages open; review state; time from upload to publish.

### C4. Localisation review workspace

**User and primary task.** Bilingual reviewer (stage 1, linguistic) and compliance or SME approver (stage 2). Task: approve or fix each segment in context and know what remains.

**Layout to build.**
- Left: segment list with filters (status, QA flags, TM score band, stage) and counters [50].
- Centre: side-by-side source and target editor with the previous and next segments shown for context, even outside the filter [43]; per-segment actions Save, Approve stage 1, Request context.
- Right: tabbed context pane: TM suggestions with match percent, source, date, contributor; glossary; QA issues grouped by segment or category; comments [43][44][45][49].
- Slide preview drawer showing the slide with the target text in place (Smartling-style visual context [51]) with a mirrored RTL toggle.
- Top: stage indicator (Draft, Stage 1 reviewed, Stage 2 approved) and totals. Stage 2 includes a sampled LQA panel scoring errors by MQM-style category and severity against a threshold [46][52].
- Below 1200 px width: panes collapse to tabs rather than shrinking (1.4.10).

**Adopt (5).**
1. TM suggestions that show match percent, origin and age, with a configurable minimum match [43][45].
2. QA checks with per-type severity: Error blocks saving, Warning can be saved with an explicit override and a recorded reason; one-click fixes where safe [44].
3. A QA issue list that can be grouped by row or category, with ignore and restore, and errors, warnings and ignored items distinguished by icon and text as well as colour [49].
4. Grid filters by status (edited, pre-translated, with errors or warnings) and a visible count of what remains [50].
5. Visual context and a "request more context" action so reviewers can ask rather than guess [43][51].

**Avoid (3).**
1. Letting a quality-estimation score route compliance segments past human review (Smartling describes this for "High" strings [53][66]); for regulated content require both human stages (inference).
2. Saving raw or unreviewed machine output into the translation memory; Smartling notes human translations are always saved and raw MT typically is not [53][66], so approve before it enters TM.
3. Excluding high TM matches (for example 99 percent and above) from QA by default, as one Phrase workflow does [47]; for compliance content a 100 percent match in a changed context can still be wrong (inference), so keep context-match logic [43] and run QA on all segments.

**Accessibility must-haves.** 2.1.1 Keyboard with next/previous/approve shortcuts, and 2.1.4 Character Key Shortcuts (remappable or off) [14]; 1.4.1 (status not by colour alone); 1.3.1 and 4.1.2 grid semantics for the segment table; 3.1.2 lang attribute per source and target cell [14][16]; 4.1.3 for "QA: 2 issues", "Saved" [12]; 1.4.10 Reflow; 2.4.11 [13]; 2.5.8 [10]; 3.3.1 and 3.3.3 for QA messages with a suggestion.

**Localisation / RTL.** Set lang and dir per cell; use dir=auto where direction is unknown and bdi around placeholders, numbers and brand names so they do not scramble order [16]. QA checks should cover tag and placeholder mismatch, empty, length and spacing [44][46], and add a mixed-direction punctuation check (inference). Use logical properties so the workspace itself mirrors in an RTL UI locale [16][65]. Preview must run the real player template in RTL, not a static mock.

**Empty / loading / error states.** Empty: "All segments approved for stage 1" with a button to send to stage 2. Loading: row skeletons [22]. TM or glossary service down: continue without suggestions and say so. Stale segment: source changed after translation, badged and filtered. Save failure: keep the typed text, retry. Stage lock: show who holds the segment and why it is read-only.

**Metrics to display.** Segments by status per language; TM leverage percent by match band; open and closed QA flags by category; stage-1 to stage-2 change rate; LQA score versus threshold [46]; reviewer time per segment; language coverage per course.

### C5. Admin analytics dashboard

**User and primary task.** L&D admin, compliance manager, executive. Task: know whether mandatory training is on track, where decisions go wrong, where language coverage is missing, and what AI and voice are costing.

**Layout to build.**
- Sticky filter bar: customer/tenant scope, date range, audience, language. Filters apply to most or all cards [60]. Allow sticky only if it cannot cover a focused control [13].
- KPI row of at most five tiles: completion rate, overdue rate, assessment pass rate, language coverage, AI+voice spend versus cap. Each tile has a definition tooltip and a "data as of" stamp [36].
- Middle band: completion trend (line) and completion by tenant/department (sorted horizontal bars) [4].
- Decision-level band: per scenario node, the distribution of choices (horizontal bars) and a "most missed decisions" table that links to the node in the editor.
- Language coverage matrix: courses by languages, each cell Approved / In review / Missing, with text labels.
- Spend band: stacked bars by AI, voice and translation against the cap, with remaining headroom.
- Every card drills to a data table with export, using the Carbon data-table pattern (toolbar for search and filters, batch bar only after selection) [20].

**Adopt (5).**
1. Encode quantities with length and 2D position; reserve colour for status [4].
2. Cap a dashboard at about 10 cards and split by theme beyond that [60].
3. Shared filters, descriptive titles with a one-line description, and drill-through to records [60].
4. Show metric definitions and freshness on the tile (Docebo documents Completions Rate and Overdue Rate and its 24 hour Insights refresh) [36].
5. Detail on demand: precise values in a tooltip or table, not as extra chart ink [5]; give managers an "overdue or due in 14 days" quick filter [35].

**Avoid (3).**
1. Treemaps, pies or area encodings where users must compare values precisely [4][64].
2. A single overdue rate that mixes soft and hard deadlines without saying so; a Docebo community thread shows users doubting such figures [37].
3. Icons beside numbers and decorative chart elements [5].

**Accessibility must-haves.** 1.1.1 Non-text Content with a text alternative or a linked data table for every chart; 1.4.1 Use of Color and 1.4.11 Non-text Contrast for series and focus rings [14]; 1.4.3; 1.3.1 for table headers; 2.1.1 with chart points reachable through a table alternative; 1.4.13 for tooltips (dismissible, hoverable, persistent) [14]; 4.1.3 for "5 results returned" after filtering [12]; 1.4.10; 2.4.11 [13]; 2.5.8 [10].

**Localisation / RTL.** Localise number, date and currency formats. Tables and graphs are normally mirrored for RTL according to W3C [65]; time-based axes and media-style controls need case-by-case decisions (Material keeps clocks and media controls left-to-right) [17]. Language names in the coverage matrix in native script. Use bdi for tenant and course names that may mix scripts [16].

**Empty / loading / error states.** Empty: new tenant or period with no data, with an explanation and a link to assign training [21]. Loading: per-card skeletons [22]. Stale: if data is older than the stated refresh, tint the stamp and say so. Partial failure: one card shows an error with retry; others stay usable. Scope: cards hidden by tenant permissions are absent, not greyed. Small cohorts: suppress below a privacy threshold and say so (inference).

**Metrics to display.** Completion rate (completed divided by enrolled, define the denominator); overdue rate (define soft versus hard deadlines) [36][37]; mean time to complete; pass rate and attempts; decision-level: percent choosing each option per node, critical-wrong-decision rate, path frequency, recovery rate; language coverage (approved languages divided by required languages) and review backlog; AI and voice spend versus cap, cost per published course, mock versus live call counts.

### C6. Branching-scenario editor and player

**User and primary task.** Authors build a decision tree; learners play it. Editor task: author a scenario with consequences and prove there are no dead ends. Player task: make decisions and learn from their consequences.

**Layout to build (editor).**
- Left: outline list of nodes (a keyboard-first tree alternative to the canvas).
- Centre: canvas node graph with pan, zoom, mini-map, auto-layout, a marked Start node, and End nodes carrying score and feedback [54].
- Right: inspector for node text, media, choices and their targets, feedback, and score or variable changes [58].
- Top: validation chip (for example "2 issues: unreachable node, dead end, missing translation"), echoing Twine's broken-link marker [55]; "Play from this node".
- Bottom: variables panel.
- Starter template that asks for the single first decision before building the rest [56].

**Layout to build (player).** Scene text and media; choices as a labelled radio-style group with one Confirm; consequence area that receives focus after a choice; progress hint; a Back option for exploratory scenarios; end screen with the path taken, score and a recovery or retry route [56][57].

**Adopt (5).**
1. A visual node map where broken links and dead ends are marked on the node itself, with text as well as colour [55].
2. Tree structure with multiple branches and endings, each ending with its own feedback and score [54].
3. Variable-based scoring with range conditions, and an order of operations where the score updates before navigation [58].
4. Consequences that show rather than lecture, delayed feedback for high-stakes errors, and recovery paths to let learners notice and correct course [56][57].
5. Prompt-to-tree generation that lands in the same editable canvas and runs the same validation, with the AI mark on generated nodes [30][18] (vendor claim that every path is validated; run your own validator).

**Avoid (3).**
1. Drag-only connections and drag-only node moves; always give a select-then-act path (2.5.7) [11].
2. Branches that all converge on the same next node so choices do not matter [58].
3. Instant right/wrong popups on each decision, which invites guessing [56].

**Accessibility must-haves.** 2.5.7 Dragging Movements and 2.1.1 Keyboard (add node, link, move with arrow keys, delete with undo) [11][14]; 4.1.2 for nodes and choices; 1.1.1 with the outline list as the text alternative to the graph; 1.4.1 for edge types and states; 2.5.8 [10]; 2.4.3 and 2.4.11 with focus moved to the consequence text [13]; 2.2.1 Timing Adjustable (no timed decisions without an off switch); 3.3.4 for deleting nodes (confirm or undo) [14]; 4.1.3 for validation counts [12].

**Localisation / RTL.** Stable node IDs across languages so analytics aggregate across them. The validation chip reports missing translations per node. Draw the author canvas top-to-bottom so it is direction-neutral (inference); the player mirrors with dir. Choices and consequences expand in translation, so avoid fixed-height choice buttons.

**Empty / loading / error states.** Empty canvas: start from a template, generate from a document, or begin with one decision [56]. Loading: canvas skeleton. Validation issues: counted, listed, click to focus the node. Save conflict: show the other author and offer merge. Player: if a link is broken, show "This path is unavailable" with the scenario ID for support and log it; resume mid-scenario after a drop; offline notice.

**Metrics to display.** Editor: nodes, longest path, endings, validation issues, translation completeness per node. Analytics: choice distribution per node, path frequency, end-state distribution, time per decision, retries, critical-error rate.

## D. Rubric

Scoring is 0 to 3 per criterion; maximum 30. Score Sentientia prototypes only. No named product is scored here because competitor scores from public pages would be guesses.

| # | Criterion | 0 | 1 | 2 | 3 |
|---|-----------|---|---|---|---|
| 1 | Primary task clarity | Surface has no discernible primary task; several equal-weight actions | Task findable but competes with 3 or more peers | Primary action prominent; secondary items sit visibly lower | New user names the task and takes the first step in under 5 seconds; one primary action per screen |
| 2 | Information hierarchy | Everything same weight; no scan order | Some grouping; important items not distinguished | Clear headings, grouping and sort order; numbers readable at a glance | Strong hierarchy in light, dark and mobile; quantities encoded by length/position; no decorative clutter [4][5] |
| 3 | Progressive disclosure | All options shown, or key options hidden with unclear labels | Two levels but vague labels, or three or more levels | Two levels with obvious labelled path | Two levels max; defaults evidence-based; no duplicate routes to the same option; expert shortcuts [1] |
| 4 | Feedback and status | Actions give no visible or announced result | Visual feedback only, or colour-only status | Text status for each action; announced via role=status | Status in text, icon and announcement; progress, saved, stale and queued states all covered [12] |
| 5 | Error prevention and recovery | Destructive actions one click, no undo | Confirmation dialog only | Specific confirmation plus undo for most actions; warnings vs errors distinct | Inline prevention, Error/Warning severities with recorded overrides, undo or version restore, consequential controls separated [6][7][8][44] |
| 6 | Accessibility | Fails keyboard or contrast checks | Keyboard works for common flows; known AA failures remain | Passes WCAG 2.2 AA automated checks plus manual keyboard and screen-reader passes on primary flows | Passes AA on all flows incl. 2.5.7, 2.5.8, 2.4.11, 4.1.3; tested with assistive tech and recorded in a VPAT-style note [9]-[14] |
| 7 | Responsive | Breaks or scrolls horizontally below 1024 px | Usable on tablet; phone is cramped or hides tasks | Primary tasks complete on 375 px; reflow without loss at 320 CSS px | Task parity on phone, tablet and desktop; panes collapse to tabs; targets 24 px minimum; no covered focus [10][13][14] |
| 8 | Localisation readiness | Strings hard-coded; layout physical left/right | Strings externalised; RTL untested | RTL mirrors via logical properties; dir and lang set; fonts per script | Tested with Arabic and Urdu real content and long strings; bidi isolation for placeholders; numerals and dates localised; media controls per rules [16][17] |
| 9 | Performance | Primary content takes more than 4 s to appear; no loading state | Visible loading state, but layout jumps and slow interaction | Meets good thresholds at 75th percentile: LCP 2.5 s or less, INP 200 ms or less, CLS 0.1 or less [61] | Meets thresholds on a mid-range phone on a throttled network; skeletons brief; no skeleton on menus or toasts [22] |
| 10 | AI transparency and control | AI output indistinguishable from human content; no way to undo | AI label present; no explanation; no revert | Label, explanation on request, editable, revert to AI original [18][19] | Plus: fidelity setting, source trace, cost and mock/live status visible, recorded human approval before learner exposure, audit trail [62] |

## E. Sources

Access date for every entry: 2026-10-15. "Read" shows how the page was used: fetched = page text read in full or in part through WebFetch; search-summary = only the search-result summary was read (fetch failed or was not attempted), so treat details as unconfirmed against the page.

[1] https://www.nngroup.com/articles/progressive-disclosure/ - Progressive Disclosure (NN/G) - vendor-marketing: n - read: fetched
[2] https://www.nngroup.com/articles/generative-ui/ - Generative UI and Outcome-Oriented Design (NN/G) - n - read: search-summary (fetch failed, socket closed)
[3] https://www.nngroup.com/articles/ai-paradigm/ - AI: First New UI Paradigm in 60 Years (NN/G) - n - read: search-summary
[4] https://www.nngroup.com/articles/dashboards-preattentive/ - Dashboards: Making Charts and Graphs Easier to Understand (NN/G) - n - read: search-summary (fetch failed)
[5] https://www.nngroup.com/articles/complex-application-design/ - 8 Design Guidelines for Complex Applications (NN/G) - n - read: search-summary
[6] https://www.nngroup.com/articles/confirmation-dialog/ - Confirmation Dialogs Can Prevent User Errors (NN/G) - n - read: search-summary (fetch failed)
[7] https://www.nngroup.com/articles/user-mistakes/ - Preventing User Errors: Avoiding Conscious Mistakes (NN/G) - n - read: search-summary
[8] https://www.nngroup.com/articles/proximity-consequential-options/ - Dangerous UX: Consequential Options Close to Benign Options (NN/G) - n - read: title only; the claim used is the one in the title
[9] https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/ - What's New in WCAG 2.2 (W3C WAI) - n - read: fetched
[10] https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html - Understanding SC 2.5.8 Target Size (Minimum) (W3C) - n - read: fetched
[11] https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html - Understanding SC 2.5.7 Dragging Movements (W3C) - n - read: fetched
[12] https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html - Understanding SC 4.1.3 Status Messages (W3C) - n - read: fetched
[13] https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html - Understanding SC 2.4.11 Focus Not Obscured (Minimum) (W3C) - n - read: fetched
[14] https://www.w3.org/TR/WCAG22/ - Web Content Accessibility Guidelines 2.2 (W3C Recommendation) - n - read: fetched, first 100,000 of about 270,000 characters; levels for 1.2.2, 1.2.3, 1.2.5, 1.3.4, 1.4.1, 1.4.3, 1.4.10, 1.4.11, 1.4.12, 2.1.1, 2.2.2, 2.3.3, 2.4.3, 2.4.7 confirmed on the page; levels for criteria beyond that (3.1.x, 3.3.x, 4.1.2, 1.2.4, 1.4.2, 1.4.13, 2.1.4, 2.2.1) are from standard WCAG knowledge and were not re-read
[15] https://www.w3.org/WAI/media/av/ - Making Audio and Video Media Accessible (W3C WAI) - n - read: search-summary
[16] https://www.w3.org/International/questions/qa-html-dir - Declaring text direction (W3C Internationalization) - n - read: fetched
[17] https://m3.material.io/foundations/layout/bidirectionality-rtl - Bidirectionality and RTL (Material Design 3) - n - read: search-summary
[18] https://carbondesignsystem.com/guidelines/carbon-for-ai/ - Carbon for AI (IBM Carbon Design System) - n - read: fetched
[19] https://carbondesignsystem.com/components/ai-label/usage/ - AI label (Carbon) - n - read: search-summary
[20] https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines - Data table (Carbon) - n - read: search-summary
[21] https://www.carbondesignsystem.com/building-blocks/core/patterns/empty-states - Empty states (Carbon) - n - read: search-summary
[22] https://www.carbondesignsystem.com/building-blocks/core/patterns/loading - Loading (Carbon) - n - read: search-summary
[23] https://v10.carbondesignsystem.com/patterns/filtering/ - Filtering (Carbon v10) - n - read: search-summary (cited only for batch "Apply filters" behaviour; not used in a brief)
[24] https://polaris-react.shopify.com/components/tables/index-table - Index table (Shopify Polaris) - n - read: search-summary (not relied on in a brief)
[25] https://docs.moodle.org/503/en/Course_homepage - Course homepage (MoodleDocs, 5.3) - n - read: fetched
[26] https://moodledev.io/general/development/policies/accessibility - Accessibility policy (Moodle developer docs) - n - read: fetched
[27] https://moodledev.io/general/documentation/accessibility - Accessibility statement (Moodle developer docs) - n - read: search-summary (WCAG 2.2 AA accreditation 3 October 2025)
[28] https://docs.moodle.org/502/en/VPAT - VPAT (MoodleDocs) - n - read: search-summary
[29] https://community.articulate.com/kb/user-guides/rise-360-create-content-with-ai-assistant/1199630 - Rise 360: Create Content with AI Assistant (Articulate community guide, plus related community threads) - y (vendor page and user posts) - read: search-summary (fetch returned 403)
[30] https://www.mindsmith.ai/ai-course-creator - AI Course Creator for Interactive Training (Mindsmith) - y - read: search-summary
[31] https://help.mindsmith.ai/en/articles/12037477-editing-your-lesson-mindsmith-editor-101 - The Lesson Editor (Mindsmith help) - y - read: search-summary
[32] https://support.360learning.com/hc/en-us/articles/18756214814228-Create-a-course-using-AI - Create a course using AI (360Learning help) - y - read: search-summary (fetch returned 403)
[33] https://help.docebo.com/hc/en-us/articles/360020127159-List-of-widgets-for-custom-widget-pages - List of widgets for custom widget pages (Docebo help) - y - read: search-summary
[34] https://community.docebo.com/product-tips-tricks-3/compliance-training-homepage-widget-12263 - Compliance Training Homepage Widget (Docebo community post) - y (user-contributed) - read: search-summary
[35] https://help.docebo.com/hc/en-us/articles/29543826719378-Courses-and-Learning-Plans-dashboards-in-My-team - Courses and Learning Plans dashboards in My team (Docebo help) - y - read: search-summary
[36] https://help.docebo.com/hc/en-us/articles/24937371085970-Insights-ready-to-use-dashboards - Insights' ready-to-use dashboards (Docebo help) - y - read: search-summary
[37] https://community.docebo.com/product-q-a-7/overdue-rate-and-overdue-by-status-on-insights-dashboard-13157 - "Overdue Rate" and "Overdue by Status" on Insights dashboard (Docebo community thread) - y (user-reported, not an official explanation) - read: search-summary
[38] https://degreed.com/experience/blog/a-smarter-more-personalized-degreed-homepage/ - A Smarter, More Personalized Degreed Homepage (Degreed blog; about 18 months old per the search tool) - y - read: search-summary
[39] https://sanalabs.com/products/sana-learn/ - The future of enterprise learning (Sana Learn product page) - y - read: search-summary
[40] https://sanalabs.com/sana-learn-changelog - Sana Learn Changelog - y - read: search-summary
[41] https://blog.duolingo.com/how-duolingo-streak-builds-habit/ - The Duolingo Streak Uses Habit Research to Keep You Motivated (Duolingo blog; reference only, self-reported results) - y - read: search-summary
[42] https://blog.duolingo.com/protecting-streaks-from-site-issues/ - How we protect learner streaks from site issues (Duolingo blog; reference only) - y - read: search-summary
[43] https://support.crowdin.com/online-editor/ - Editor Overview (Crowdin docs; metadata suggests an old page) - y (product documentation) - read: search-summary
[44] https://support.crowdin.com/project-settings/qa-checks/ - QA Check Settings (Crowdin docs) - y (product documentation) - read: search-summary
[45] https://support.crowdin.com/project-settings/translation-memories/ - Translation Memory Settings (Crowdin docs) - y (product documentation) - read: search-summary
[46] https://support.phrase.com/hc/en-us/articles/5709599557020-Language-Quality-Assessment-TMS - Language Quality Assessment (TMS) (Phrase help) - y (product documentation) - read: search-summary
[47] https://phrase.com/blog/posts/translation-quality/ - The Complete Guide to Improving Translation Quality (Phrase blog; about 1,288 days old) - y - read: search-summary
[48] https://docs.memoq.com/current/en/Workspace/translation-results-list.html - Translation results pane (memoQ docs) - y (product documentation) - read: search-summary
[49] https://docs.memoq.com/current/en/webNext-help/memoQeditor-sidepanel.html - Side panel in memoQ editor (memoQ docs) - y (product documentation) - read: search-summary
[50] https://support.memoq.com/hc/en-us/articles/360010377239-Filtering-options-for-the-translation-grid - Filtering options for the translation grid (memoQ support; about 1,500 days old) - y (product documentation) - read: search-summary
[51] https://help.smartling.com/hc/en-us/articles/1260806372849-Translation-Quality-Features-Overview - Translation Quality: Features Overview (Smartling help) - y - read: search-summary
[52] https://help.smartling.com/hc/en-us/articles/49428460628507-LQA-Agent-AI-Powered-Quality-Assurance - LQA Agent: AI-Powered Quality Assurance (Smartling help) - y - read: search-summary
[53] https://help.smartling.com/hc/en-us/articles/4559072739995-Translation-Workflow-Options-Human-Translation-Machine-Translation-AI-Powered-MT - Translation Workflow Options (Smartling help) - y - read: search-summary
[54] https://h5p.org/tutorial-branching-scenario - Branching Scenario Tutorial (H5P; open-source project, forum threads in the same result set are older) - n - read: search-summary
[55] https://twinery.org/reference/en/editing-stories/navigating.html - Navigating the Story Map (Twine reference) - n - read: search-summary
[56] https://blog.cathy-moore.com/branching-scenarios-how-many-decision-points/ - Branching scenarios: How many decision points? (Cathy Moore, practitioner blog) - n - read: search-summary
[57] https://blog.cathy-moore.com/3-ways-to-help-people-learn-from-mistakes-in-branching-scenarios/ - 3 ways to help people learn from mistakes in branching scenarios (Cathy Moore) - n - read: search-summary
[58] https://blog.iconlogic.com/2026/08/how-to-create-branching-scenarios-storyline.html - How to Create Branching Scenarios in Storyline (iconlogic blog, a training vendor) - y - read: search-summary
[59] https://community.articulate.com/discussions/branching-best-practices-tips - Branching Best Practices/Tips (Articulate community forum) - n (user posts) - read: search-summary (cited only indirectly; range-condition point is in [58])
[60] https://www.metabase.com/learn/metabase-basics/querying-and-dashboards/dashboards/bi-dashboard-best-practices - BI dashboard best practices (Metabase) - y (vendor learning content) - read: search-summary
[61] https://web.dev/articles/vitals - Web Vitals (web.dev, Google) - n - read: fetched
[62] https://www.microsoft.com/en-us/research/publication/guidelines-for-human-ai-interaction/ - Guidelines for Human-AI Interaction (Microsoft Research, CHI 2019) - n - read: fetched, but the page does not list the 18 guidelines; "make clear what the system can do / how well" and the four groups come from a search summary of the HAX Toolkit pages and were not checked in the PDF
[63] https://compliancehub.wiki/eu-ai-act-article-50-transparency-digital-omnibus-2026/ - EU AI Act Article 50 transparency and the Digital Omnibus (compliance blog; search results also included other secondary blogs) - y (compliance vendor content) - read: search-summary; not checked against the Official Journal or Regulation (EU) 2026/1744; sources disagree on the grace period for marking
[64] https://www.nngroup.com/articles/treemaps/ - Treemaps: Data Visualization of Complex Hierarchies (NN/G) - n - read: search-summary
[65] https://www.w3.org/International/i18n-drafts/nav/about - About W3C Internationalization (i18n) - n - read: search-summary
[66] https://www.smartling.com/blog/how-to-assess-machine-translation-quality - How to Measure and Maintain Machine Translation Quality at Scale (Smartling blog; the search summary did not pin the quality-estimation and TM-saving claims to one page, so [53] and [66] are cited together) - y - read: search-summary
[67] https://help.docebo.com/hc/en-us/articles/19807231971474-Product-updates - Product updates (Docebo help; October 2025 learning-plan soft-deadline change) - y - read: search-summary
[68] https://www.nngroup.com/articles/designing-ai-study-guide/ - Designing AI Products and Features: Study Guide (NN/G) - n - read: search-summary
