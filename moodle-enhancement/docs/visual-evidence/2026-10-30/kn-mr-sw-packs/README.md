# kn / mr / sw language packs after review (27ce9fc35)

What changed: the Kannada, Marathi and Swahili strings of 41 plugins (4,429 per language, both trees). This replaces
the 2026-10-23 machine drafts with the reviewed text: a glossary-led review, the two English source fixes of
fe87b975f, then back-translation QA. The QA accepted 5 fixes: mr 3, sw 2, kn 0. Details are in the commit message and
in PROJECT-STATE 2026-10-29 / 10-30.

## Why there are no screenshots

There are no screenshots for this change. Nothing in the page layout changed, only the text of three languages. Those
languages show only where Moodle's kn/mr/sw core language packs are installed. Local XAMPP (5.1) has them; UAT and the
local 5.3 runtime do not.

The browser route was blocked on the night of 2026-10-29:
- Claude in Chrome reported "not connected", even after Nitin logged in as Meera.
- The UAT tunnel refused connections.
- Nitin suspects a new antivirus/monitoring layer and asked for nothing to be forced.

Screenshots would also have needed a logged-in local session, and no credentials are typed for that.

## What was checked instead (local XAMPP, read-only CLI)

The 123 `moodle-enhancement/` lang files of the commit were copied into `C:\xampp\htdocs\moodle5\public\`. The check
then asked Moodle's own string manager for the changed strings, with caches reset. The script is
`D:/Claude Local/kit-scratch/lang/render/strings_cli.php`.

| | kn | mr | sw |
|---|---|---|---|
| core language pack installed | yes | yes | yes |
| `local_sentientia_request:reason_help` | ... (ಕನಿಷ್ಠ 20 ಅಕ್ಷರಗಳು). | ... (किमान 20 अक्षरे). | ... (angalau herufi 20). |
| `local_sentientia_courses:privacy:metadata:courses_requests:status` | ವಿನಂತಿಯ ಸ್ಟೇಟಸ್: ಬಾಕಿ ಇದೆ, ಅನುಮೋದಿಸಲಾಗಿದೆ ಅಥವಾ ತಿರಸ್ಕರಿಸಲಾಗಿದೆ. | विनंतीचा स्टेटस: प्रलंबित, मंजूर किंवा नाकारले. | Hali ya ombi: inasubiri, imeidhinishwa au imekataliwa. |
| `local_sentientia_courses:coursecreated` | ಕೋರ್ಸ್ ಯಶಸ್ವಿಯಾಗಿ ರಚಿಸಲಾಗಿದೆ. | कोर्स तयार झाला. | Kozi imeundwa. |
| `local_sentientia_evaluation:dep_value_help` | (unchanged by QA) | (unchanged by QA) | Kwa maswali makuu ya Ndiyo/Hapana, ... |

- All 141 `local_sentientia_*` kn/mr/sw packs on the box loaded with no missing keys against English.
- Kannada keeps "ಯಶಸ್ವಿಯಾಗಿ" (successfully). That is the kn glossary's choice and a faithful translation. Only the
  mr and sw glossaries have a "drop successfully" house rule.

## Still to do (Nitin)

The UAT deploy is dry-run verified:

`tools/uat/deploy_to_uat.sh --yes --no-upgrade --prefer-me --commit 27ce9fc35`

It is 123 files, with a cache purge only. A human native-speaker read is advisable before a kn, mr or sw tenant goes
live. The file headers say they are AI-reviewed.
