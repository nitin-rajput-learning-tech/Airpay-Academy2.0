# Assessment integrity: admin guide (core quiz settings only)

**For:** L&D admins and course authors who set up hiring tests, skill assessments and skill
certifications. **Date:** 19 Nov 2026 (post-demo plan items 2.4 and B0). **Applies to:** Moodle 5.1
(local) and 5.3 (UAT); the labels below are Moodle's own. **Status:** interim, until the post-demo
assessment sessions land (B1 session 4, B2 session 7, B3 session 9, B4 session 15, B5 session 17).

**Leave "Require proctoring" at No** until B1 (session 4) is deployed and the flag
`sentientia.proctoring.enabled` is ON for your tenant (it is OFF everywhere; switching it on is Nitin's
call). While the flag is OFF the setting is not on the quiz form at all. Even with it on, B1 records tab
switches, fullscreen exits and pastes; it does not block them and uses no camera. Everything below uses
core Moodle quiz settings only and needs no flag.

**Why this matters.** Of the 235 BizLMS quizzes in the April production backup, 7 use Moodle's browser
security, none uses a password or network restriction, only 28 draw random questions from a bank, and
120 show the questions again straight after submitting. Each of those makes copying easy.

## 1. Presets

Where to set them: open the quiz (or the quiz under an Exam), then **Settings**, then **Questions**.

| Setting (Moodle label) | Hiring, walk-in | Hiring, remote screening | Skill assessment | Skill certification |
|---|---|---|---|---|
| Target level (decision D8) | Proctored (later) | Proctored (later) | Secure (later) | Proctored (later) |
| Questions page: random from a bank | Yes, one random slot per topic | Yes | Yes | Yes |
| Questions page: **Shuffle** | On | On | On | On |
| Question behaviour > **Shuffle within questions** | Yes | Yes | Yes | Yes |
| Question behaviour > **How questions behave** | Deferred feedback | Deferred feedback | Deferred feedback | Deferred feedback |
| Grade > **Attempts allowed** | 1 | 1 | 1 (or 2 with a delay) | 1 |
| Extra restrictions > **Enforced delay between 1st and 2nd attempts** | n/a | n/a | 7 days if 2 attempts | n/a |
| Timing > **Open the quiz** / **Close the quiz** | The sitting only (e.g. 10:00 to 11:30) | A 24-hour window | The assessment window | The sitting only |
| Timing > **Time limit** | 60-90 s per question | 45-60 s per question | 60-90 s per question | 60-90 s per question |
| Timing > **When time expires** | Open attempts are submitted automatically | same | same | same |
| Layout > **New page** | Every question | Every question | Every question | Every question |
| Layout > **Navigation method** (Show more) | Sequential | Sequential | Free | Sequential |
| Review options > **Immediately after the attempt** | All unticked | All unticked | All unticked | All unticked |
| Review options > **Later, while the quiz is still open** | All unticked | All unticked | All unticked | All unticked |
| Review options > **After the quiz is closed** | All unticked (HR gets results from the report) | All unticked | Maximum marks, Marks, Overall feedback | Maximum marks, Marks, Overall feedback |
| Appearance > **Show blocks during quiz attempts** | No | No | No | No |
| Extra restrictions > **Require password** | Yes, given out by the invigilator at the start, new per sitting | No | No | Yes, per sitting |
| Extra restrictions > **Require network address** | Office network only (section 3) | No | No | Office network only |
| Extra restrictions > **Browser security** | Full screen pop-up with some JavaScript security | same | same | same |

**Marks** can be ticked only when **Maximum marks** is ticked in the same column; Moodle greys it out
otherwise.

Never tick **Right answer** or **The attempt** in any review column of a quiz whose bank you will reuse:
that publishes the bank. Compliance and POSH quizzes (level Standard) take the skill-assessment column
without the time pressure; learners may get 2 or more attempts there.

## 2. Build the question bank first

1. **Course > More > Question banks**: add a shared bank for the topic (Moodle 5 keeps each quiz's own
   questions in the quiz; a shared bank lets several quizzes draw from one pool).
2. Make one **category per topic or objective**, and tag questions by difficulty (`easy`, `medium`,
   `hard`).
3. Put **at least 3 to 5 times** as many questions in each category as the quiz draws from it. A
   20-question test needs 60 to 100 questions.
4. On the quiz's **Questions** page: **Add > a random question > Switch bank** to the shared bank,
   pick the category (and a tag if you split by difficulty), set **Number of random questions**, then
   **Add random question**. Repeat per topic so every candidate gets the same blueprint with different
   questions.
5. In each multiple-choice question keep **Shuffle the choices?** ticked (it works only together with
   "Shuffle within questions").
6. Keep practice and assessment banks apart. A question used in a practice quiz is public.

## 3. Walk-in tests on office laptops (hiring, certification)

- **Require network address:** ask IT for the office's public (egress) IP address or range and enter it,
  comma-separated (for example `203.0.113.0/24`; a partial address such as `203.0.113.` also works).
  Test from one office laptop before the day. If the server sits behind a load balancer or proxy, the
  platform admin must check **Site administration > Server > HTTP > Logged IP address source** first,
  otherwise Moodle sees the proxy's address and the restriction lets everyone in or nobody.
- Remote staff on the office VPN may share the same egress address, so the IP restriction proves
  "office network", not "in the room". The password and the invigilator do the rest.
- **Require password:** a new password per sitting, written on the board when everyone is seated.
- **Browser security** opens the quiz in a full-screen pop-up and discourages copy and paste. It is easy
  to get around (another device, another browser); treat it as a deterrent only.
- An invigilator walks the room; phones in bags. Safe Exam Browser (one display, no other apps) needs
  a tested configuration on the laptops: it comes with the B3 preset in session 9, do not improvise it
  for a live hiring sitting.
- **Candidate accounts** (until B5, session 17, builds invite-only expiring accounts): create one manual
  account per candidate (name and email only, nothing else), enrol it in a hiring course that is hidden
  from the catalogue, follow HR's hiring privacy notice, and suspend the account once HR has the result.
  Never let a candidate use an employee's account.
- Extra time for a candidate who needs it: quiz **Overrides > User overrides**.

## 4. Question design (B0): make copying useless

No browser setting can see a phone running an AI chatbot. Question design and time pressure are the
controls that work today.

- **Ask for application, not recall.** Scenario questions built on Airpay's own policies, products and
  cases ("a merchant reports X; what is the first step?") cannot be looked up quickly.
- **Use numbers that change.** Calculated questions with random values give every candidate a different
  sum.
- **Write distractors from real mistakes** people make, not obviously wrong options. Avoid "all of the
  above" and "none of the above".
- **One idea per question**, short stems, no clues in the wording or the longest option.
- **Time it tight.** 60 to 90 seconds per question leaves no time to search; pilot the quiz with two
  colleagues and set the limit from their times.
- **Draw from a blueprint**, not a fixed list (section 2), so neighbours see different questions.
- **Hide feedback until close** (section 1), so early finishers cannot pass answers on.
- **Check the numbers after 30 or more attempts:** quiz **Results > Statistics**. Rewrite or retire a
  question whose **Facility index** is near 100% (everyone gets it, possibly leaked) or whose
  **Discrimination index** is low or negative (strong candidates get it wrong).
- **Retire leaked questions.** If a question appears outside the platform, move it to a "retired"
  category; do not just edit the wording.

## 5. Site default (decision D11, platform admin, one time)

Turn off review straight after an attempt for new quizzes: **Site administration > Plugins > Activity
modules > Quiz > General settings > Review options**, untick the **Immediately after the attempt**
column. Existing quizzes keep their own settings (including the 120 imported ones above) and must be
changed one by one. This is a site setting: on UAT or live it changes only on Nitin's go.

## 6. What core settings cannot do (and what will)

| Gap | Planned answer |
|---|---|
| Copy, paste and tab switches are not blocked or recorded | B1 (session 4) records them while its flag is on; B2 secure assessment mode (session 7) blocks them |
| No locked-down kiosk for remote or walk-in tests | B3 Safe Exam Browser preset (session 9) |
| No identity check or webcam record-and-review | B1 (session 4) then B4 (session 15) |
| No expiring candidate accounts with a DPDP notice | B5 (session 17) |
| Live human proctor | B6: vendor quote (Talview or Mercer Mettl), for occasional roles only |

Source of the plan and decisions: `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md`
(section B; decisions D8 to D11).
