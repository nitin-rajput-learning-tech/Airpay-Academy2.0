"""Player chrome strings (buttons, labels) per language.

English is the base. Hindi is hand-written for the pilot so the hi path is not
pseudo-localised; every other language falls back to pseudo-localisation
("[xx] English text") in mock mode, exactly like the course content, and is
marked as machine-state in the language selector. Real chrome strings for those
languages come out of the same translation memory and review loop as course text
(ADR-034 D4), not from this file.
"""
from __future__ import annotations

UI_EN: dict[str, str] = {
    "previous": "Previous",
    "next": "Next",
    "finish": "Finish",
    "language": "Language",
    "contents": "Contents",
    "skip": "Skip to content",
    "check": "Check answers",
    "tryAgain": "Try again",
    "continueLabel": "Continue",
    "correct": "Correct",
    "incorrect": "Not quite",
    "partlyCorrect": "Partly correct",
    "score": "Score",
    "yourScore": "Your score",
    "passmark": "Pass mark",
    "passed": "Passed",
    "failed": "Not passed yet",
    "completed": "Completed",
    "inProgress": "In progress",
    "notStarted": "Not started",
    "transcript": "Transcript",
    "captionsOn": "Captions on",
    "captionsOff": "Captions off",
    "listen": "Listen to the narration",
    "slide": "Slide",
    "scenario": "Scenario",
    "quiz": "Quiz",
    "summary": "Course summary",
    "position": "Step {$a} of {$b}",
    "decisions": "Decisions made: {$a}",
    "scenarioScore": "Scenario score: {$a} of {$b}",
    "outcomeAcceptable": "Good choice",
    "outcomeRecoverable": "You can recover",
    "outcomeIrrecoverable": "Serious mistake",
    "outcomeSuccess": "Best outcome",
    "outcomePartial": "Partly handled",
    "outcomeFailure": "Scenario failed",
    "yourPath": "Your path",
    "restart": "Restart the scenario",
    "matchSelect": "Choose a match",
    "noAnswer": "No answer",
    "courseResult": "Course result",
    "blocksVisited": "Steps visited",
    "quizResult": "You scored {$a} percent",
    "fallbackLang": "Shown in {$a} (not translated yet)",
    "stateTrimmed": "Some saved progress details were trimmed to fit the LMS limit.",
    "previewBuild": "PREVIEW BUILD - not approved for learners",
    "trackingOn": "Progress is being saved",
    "ofLabel": "of",
}

UI_HI: dict[str, str] = {
    "previous": "पिछला",
    "next": "आगे",
    "finish": "समाप्त करें",
    "language": "भाषा",
    "contents": "विषय-सूची",
    "skip": "सामग्री पर जाएँ",
    "check": "उत्तर जाँचें",
    "tryAgain": "फिर से कोशिश करें",
    "continueLabel": "आगे बढ़ें",
    "correct": "सही",
    "incorrect": "थोड़ा चूक गए",
    "partlyCorrect": "आंशिक रूप से सही",
    "score": "अंक",
    "yourScore": "आपके अंक",
    "passmark": "पास होने के अंक",
    "passed": "पास",
    "failed": "अभी पास नहीं हुए",
    "completed": "पूरा हुआ",
    "inProgress": "जारी है",
    "notStarted": "शुरू नहीं हुआ",
    "transcript": "लिखित पाठ",
    "captionsOn": "कैप्शन चालू",
    "captionsOff": "कैप्शन बंद",
    "listen": "नैरेशन सुनें",
    "slide": "स्लाइड",
    "scenario": "परिदृश्य",
    "quiz": "प्रश्नोत्तरी",
    "summary": "कोर्स का सार",
    "position": "चरण {$a} / {$b}",
    "decisions": "लिए गए निर्णय: {$a}",
    "scenarioScore": "परिदृश्य के अंक: {$b} में से {$a}",
    "outcomeAcceptable": "अच्छा चुनाव",
    "outcomeRecoverable": "स्थिति सँभल सकती है",
    "outcomeIrrecoverable": "गंभीर गलती",
    "outcomeSuccess": "सबसे अच्छा नतीजा",
    "outcomePartial": "आंशिक रूप से सँभाला",
    "outcomeFailure": "परिदृश्य असफल",
    "yourPath": "आपका रास्ता",
    "restart": "परिदृश्य फिर से शुरू करें",
    "matchSelect": "मिलान चुनें",
    "noAnswer": "कोई उत्तर नहीं",
    "courseResult": "कोर्स का नतीजा",
    "blocksVisited": "देखे गए चरण",
    "quizResult": "आपको {$a} प्रतिशत अंक मिले",
    "fallbackLang": "{$a} में दिखाया गया (अभी अनुवाद नहीं हुआ)",
    "stateTrimmed": "LMS की सीमा के कारण सहेजी गई प्रगति का कुछ ब्योरा घटाया गया।",
    "previewBuild": "प्रीव्यू बिल्ड - शिक्षार्थियों के लिए स्वीकृत नहीं",
    "trackingOn": "प्रगति सहेजी जा रही है",
    "ofLabel": "में से",
}

# Endonym (English name) shown in the language selector; unknown tags show the tag itself.
LANG_NAMES: dict[str, str] = {
    "en": "English",
    "hi": "हिन्दी (Hindi)",
    "mr": "मराठी (Marathi)",
    "kn": "ಕನ್ನಡ (Kannada)",
    "ta": "தமிழ் (Tamil)",
    "te": "తెలుగు (Telugu)",
    "bn": "বাংলা (Bengali)",
    "gu": "ગુજરાતી (Gujarati)",
    "ml": "മലയാളം (Malayalam)",
    "pa": "ਪੰਜਾਬੀ (Punjabi)",
    "or": "ଓଡ଼ିଆ (Odia)",
    "as": "অসমীয়া (Assamese)",
    "ur": "اردو (Urdu)",
    "ar": "العربية (Arabic)",
    "sw": "Kiswahili (Swahili)",
}


def ui_for(lang: str, base_lang: str) -> dict[str, str]:
    """Chrome strings for one language: hand-written where available, else pseudo-localised."""
    if lang == "en" or (lang == base_lang and lang not in ("hi",)):
        return dict(UI_EN)
    if lang == "hi":
        return dict(UI_HI)
    return {key: f"[{lang}] {value}" for key, value in UI_EN.items()}
