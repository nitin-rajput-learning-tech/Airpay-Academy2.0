# API Rules — Moodle REST + External APIs
# ALWAYS LOADED when working with HTTP calls, REST API, ElevenLabs, Gamma, or Azure.

---

## Confirmation Gate (Non-negotiable)

```
READ-ONLY Moodle functions  → NO confirm needed
WRITE Moodle functions      → [CONFIRM] from Nitin
ElevenLabs API              → [CONFIRM] (costs money per character)
Gamma API                   → [CONFIRM] (costs per generation)
Azure/Microsoft APIs        → [CONFIRM] (may affect org data)
Production Moodle direct    → [CONFIRM] (live users)
```

---

## Moodle REST API

### Authentication & Base Call

```python
# lib/moodle_api.py — shared client (use this pattern everywhere)
import os
import requests
from dotenv import load_dotenv

load_dotenv()  # Load from .env — NEVER hardcode

MOODLE_URL   = os.getenv('MOODLE_URL')    # https://www.airpay.academy
MOODLE_TOKEN = os.getenv('MOODLE_TOKEN')  # NEVER log this value

def call_moodle(function_name: str, params: dict = {}) -> dict | list:
    """
    Call Moodle REST API.
    Returns parsed JSON. Raises ValueError on Moodle errors.
    """
    payload = {
        'wstoken':            MOODLE_TOKEN,
        'wsfunction':         function_name,
        'moodlewsrestformat': 'json',
        **params
    }
    response = requests.post(
        f'{MOODLE_URL}/webservice/rest/server.php',
        data=payload,
        timeout=30
    )
    response.raise_for_status()
    data = response.json()

    if isinstance(data, dict) and 'exception' in data:
        raise ValueError(f"Moodle API [{function_name}]: {data.get('message', data['exception'])}")

    return data
```

### READ Functions (No Confirmation Needed)

```python
# ── COURSES ──────────────────────────────────────────────────────────
# Get all courses
courses = call_moodle('core_course_get_courses')

# Get specific courses by ID
courses = call_moodle('core_course_get_courses', {
    'options[ids][0]': 42,
    'options[ids][1]': 43,
})

# ── USERS ─────────────────────────────────────────────────────────────
# Search users by field
users = call_moodle('core_user_get_users', {
    'criteria[0][key]':   'email',
    'criteria[0][value]': 'user@airpay.in',
})
# Other searchable fields: username, idnumber, firstname, lastname, auth, confirmed

# ── ENROLMENTS ────────────────────────────────────────────────────────
# Get users enrolled in a course
enrolled = call_moodle('core_enrol_get_enrolled_users', {
    'courseid': 42,
})

# ── COMPLETION ────────────────────────────────────────────────────────
# Get activity completion status for a user in a course
status = call_moodle('core_completion_get_activities_completion_status', {
    'courseid': 42,
    'userid':   123,
})

# ── SCORM ─────────────────────────────────────────────────────────────
# Get SCORM SCOs (learning objects within a SCORM package)
scoes = call_moodle('mod_scorm_get_scorm_scoes', {
    'scormid': 7,
})
```

### WRITE Functions — [CONFIRM] REQUIRED

```python
# ── COURSE CREATION ─── [CONFIRM] before calling ──────────────────────
# ⚠ Creates live content on production server — IRREVERSIBLE without admin action
def create_course_CONFIRM(fullname: str, shortname: str, categoryid: int = 1) -> dict:
    """[CONFIRM] required. Creates a course on production Moodle."""
    return call_moodle('core_course_create_courses', {
        'courses[0][fullname]':    fullname,
        'courses[0][shortname]':   shortname,
        'courses[0][categoryid]':  categoryid,
        'courses[0][visible]':     0,  # Hidden until reviewed
        'courses[0][format]':      'topics',
        'courses[0][numsections]': 5,
    })

# ── FILE UPLOAD ─── [CONFIRM] before calling ──────────────────────────
# ⚠ Uploads file to production server
def upload_file_CONFIRM(filepath: str, filename: str, itemid: int = 0) -> dict:
    """[CONFIRM] required. Uploads file to Moodle draft file area."""
    with open(filepath, 'rb') as f:
        response = requests.post(
            f'{MOODLE_URL}/webservice/upload.php',
            files={'file_1': (filename, f)},
            data={'token': MOODLE_TOKEN, 'filearea': 'draft', 'itemid': itemid},
            timeout=120
        )
    return response.json()
```

### Error Handling Patterns

```python
try:
    result = call_moodle('core_course_get_courses')
except ValueError as e:
    # Moodle API error (invalid function, auth fail, etc.)
    print(f"Moodle error: {e}")
    # Common errors:
    # "Invalid token" → MOODLE_TOKEN wrong or expired
    # "No permissions" → token user lacks webservice permissions
    # "Function not available" → wsfunction name typo or not enabled
except requests.exceptions.Timeout:
    # Server too slow (happens on large data requests)
    print("Moodle request timed out — try with smaller batch")
except requests.exceptions.ConnectionError:
    # Server down or wrong URL
    print("Cannot reach Moodle — check MOODLE_URL and server status")
```

---

## ElevenLabs API (SENTIENTIA Agent 4)

**[CONFIRM] REQUIRED — charged per character. ~$0.30/1000 chars.**

```python
# content/voice/generate_voice.py
import os, requests
from pathlib import Path
from dotenv import load_dotenv

load_dotenv()

ELEVENLABS_API_KEY = os.getenv('ELEVENLABS_API_KEY')
ELEVENLABS_VOICE_ID = os.getenv('ELEVENLABS_VOICE_ID')

def generate_voice_CONFIRM(narration_path: str, output_path: str) -> None:
    """
    [CONFIRM] REQUIRED before calling — costs money.
    Input:  content/narrations/[course]-narration.txt (max 2000 words, ≤25 word sentences)
    Output: content/voice/[course]-voice.mp3
    """
    narration_text = Path(narration_path).read_text(encoding='utf-8').strip()

    # SECURITY: Remove any PII that might have crept in
    # Never send employee names, IDs, salary, HR case content
    assert len(narration_text) > 0, "Empty narration — check Agent 2 output"
    assert len(narration_text.split()) <= 2100, f"Narration too long: {len(narration_text.split())} words"

    url = f"https://api.elevenlabs.io/v1/text-to-speech/{ELEVENLABS_VOICE_ID}"
    headers = {
        'xi-api-key':   ELEVENLABS_API_KEY,  # NEVER log this
        'Content-Type': 'application/json',
        'Accept':       'audio/mpeg',
    }
    payload = {
        'text':       narration_text,
        'model_id':   'eleven_multilingual_v2',
        'voice_settings': {
            'stability':        0.50,  # Higher = more consistent tone
            'similarity_boost': 0.75,  # Higher = closer to reference voice
            'style':            0.25,  # Slight expressiveness for learning content
            'use_speaker_boost': True,
        },
    }

    print(f"Generating voice for: {Path(narration_path).name}")
    print(f"Estimated cost: ~${len(narration_text) / 1000 * 0.30:.2f}")

    response = requests.post(url, json=payload, headers=headers, timeout=120)
    response.raise_for_status()

    Path(output_path).write_bytes(response.content)
    print(f"Voice saved: {output_path} ({len(response.content) / 1024:.0f} KB)")

# SENTIENTIA narration constraints (Agent 2 output must meet these):
# - Max 2000 words per narration
# - Max 25 words per sentence
# - Target reading pace: 130 words per minute
# - No HTML tags, no markdown, plain text only
```

---

## SENTIENTIA Pipeline — API Chain

```
Agent 1: SOP Parser
  Input:  content/sops/*.pdf
  Output: content/parsed/*-parsed.json
  No API calls — local PDF parsing only

Agent 2: Narration Generator
  Input:  content/parsed/*-parsed.json
  Output: content/narrations/*-narration.txt
  No external API — Claude generates this

Agent 3: Slides Generator
  Input:  content/narrations/*-narration.txt
  Output: content/slides/*-slides.json
  No external API — Claude generates this

Agent 4: Voice Generator  ← [CONFIRM] REQUIRED
  Input:  content/narrations/*-narration.txt
  Output: content/voice/*-voice.mp3
  API:    ElevenLabs (cost: ~$0.30/1000 chars)

Agent 5: SCORM Packager
  Input:  content/slides/*-slides.json + content/voice/*-voice.mp3
  Output: content/scorm-output/*-scorm.zip
  No external API — local file packaging

Agent 6: Moodle Upload  ← [CONFIRM] REQUIRED
  Input:  content/scorm-output/*-scorm.zip
  API:    Moodle REST (core_files_upload + course assignment)
```

**RULE: Never chain agents. Each agent = one session. Output to disk. Next session reads from disk.**

---

## Microsoft 365 / Azure (Workstream C — Planned)

```python
# When building: use MSAL, never hardcode client secrets
from msal import ConfidentialClientApplication

app = ConfidentialClientApplication(
    client_id     = os.getenv('AZURE_CLIENT_ID'),
    client_credential = os.getenv('AZURE_CLIENT_SECRET'),  # from .env ONLY
    authority     = f"https://login.microsoftonline.com/{os.getenv('AZURE_TENANT_ID')}"
)

# Acquire token for Microsoft Graph
token = app.acquire_token_for_client(scopes=['https://graph.microsoft.com/.default'])
# Use token['access_token'] in Authorization: Bearer header

# GDPR: Azure AD user data (names, emails, roles) must NOT be:
# - Logged in any file
# - Sent to ElevenLabs or Gamma
# - Stored outside Moodle without encryption
```

---

## Environment Variables (Complete Reference)

```bash
# .env — NEVER commit. Listed in .gitignore as: .env

# === MOODLE ===
MOODLE_URL=https://www.airpay.academy
MOODLE_TOKEN=<32-char alphanumeric token from Moodle web service>

# === ELEVENLABS ===
ELEVENLABS_API_KEY=<key from elevenlabs.io dashboard>
ELEVENLABS_VOICE_ID=<voice ID, e.g. pNInz6obpgDQGcFmaJgB>

# === GAMMA (slide generation) ===
GAMMA_API_KEY=<key from gamma.app dashboard>

# === AZURE / MICROSOFT 365 ===
AZURE_CLIENT_ID=<app registration client ID>
AZURE_CLIENT_SECRET=<client secret value — rotates every 24 months>
AZURE_TENANT_ID=<tenant ID from Azure AD>

# === LOCAL DEV DB (for Python scripts accessing DB directly) ===
DB_HOST=localhost
DB_PORT=3306
DB_NAME=moodle
DB_USER=moodleuser
DB_PASS=<local dev password only>
```

**Loading in Python:**
```python
from dotenv import load_dotenv
import os
load_dotenv()  # Must be called before any os.getenv()
token = os.getenv('MOODLE_TOKEN')  # None if not set — check for None before use
assert token, "MOODLE_TOKEN not set in .env"
```

---

## Security Rules for All API Calls

```
NEVER log token values:
  ❌ print(f"Using token: {MOODLE_TOKEN}")
  ✅ print(f"Calling: {function_name}")

NEVER put keys in git:
  ❌ MOODLE_TOKEN = 'abc123'  # in any .py, .php, .js file
  ✅ MOODLE_TOKEN = os.getenv('MOODLE_TOKEN')

ALWAYS set timeouts:
  requests.post(..., timeout=30)   # 30s for most calls
  requests.post(..., timeout=120)  # 120s for file uploads/voice gen

ALWAYS validate API responses before use:
  if 'exception' in data: raise ValueError(...)
  if not isinstance(data, list): raise ValueError(...)

NEVER send PII to ElevenLabs/Gamma:
  Strip employee names, IDs, salary data from all narration/slide text
```
