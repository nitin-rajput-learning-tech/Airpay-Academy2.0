// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Consent + identity verification flow.
 *
 * Pre-exam wizard. Three steps:
 *   1. Show consent modal → user clicks "I agree" → POST consent
 *   2. Show identity capture → ID upload + selfie → POST identity
 *   3. On success, kick off the in-exam runtime (proctor.js)
 *
 * Pattern: this is the user-facing pre-flight before the quiz starts.
 *
 * @module local_sentientia_proctoring/consent
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

let SESSION_ID = 0;
let QUIZ_ID = 0;

/** Kick off the consent flow for a quiz attempt. */
export const open = async (quizid) => {
    QUIZ_ID = parseInt(quizid, 10);
    if (!QUIZ_ID) return;

    // Start session.
    const r = await Ajax.call([{
        methodname: 'local_sentientia_proctoring_start_session',
        args: { quizid: QUIZ_ID }
    }])[0].catch(err => { Notification.exception(err); return null; });
    if (!r) return;
    SESSION_ID = r.sessionid;

    renderConsentStep();
};

const renderConsentStep = () => {
    const overlay = createOverlay();
    overlay.innerHTML = `
        <div class="card">
            <div class="card-body">
                <h3>Recorded exam — consent required</h3>
                <p>This exam is proctored. Please read the terms below.</p>
                <ul>
                    <li><strong>Identity verification</strong> — upload an ID photo + selfie. Match score only is retained; photos are deleted.</li>
                    <li><strong>Webcam + screen recording</strong> — your camera, microphone, and screen are recorded for the duration of the exam.</li>
                    <li><strong>Automated review</strong> — recordings are scanned by AI for suspicious behaviour. Flagged sessions get human review.</li>
                </ul>
                <p class="text-muted small">Recordings retained 90 days then deleted.</p>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="consent_check">
                    <label class="form-check-label" for="consent_check">
                        I have read and consent to these recording terms
                    </label>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-outline-secondary" id="consent_cancel">Cancel</button>
                    <button class="btn btn-primary" id="consent_proceed" disabled>Continue</button>
                </div>
            </div>
        </div>`;
    document.getElementById('consent_check').onchange = (e) => {
        document.getElementById('consent_proceed').disabled = !e.target.checked;
    };
    document.getElementById('consent_cancel').onclick = () => closeOverlay();
    document.getElementById('consent_proceed').onclick = async () => {
        await Ajax.call([{
            methodname: 'local_sentientia_proctoring_give_consent',
            args: { sessionid: SESSION_ID }
        }])[0].catch(Notification.exception);
        renderIdentityStep();
    };
};

const renderIdentityStep = () => {
    const overlay = createOverlay();
    overlay.innerHTML = `
        <div class="card">
            <div class="card-body">
                <h3>Step 1 — Identity verification</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Government ID photo</label>
                        <input type="file" id="id_photo" accept="image/*" capture="environment" class="form-control">
                        <img id="id_preview" style="display:none;max-width:100%;margin-top:0.5rem;border-radius:6px;">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Selfie</label>
                        <input type="file" id="selfie_photo" accept="image/*" capture="user" class="form-control">
                        <img id="selfie_preview" style="display:none;max-width:100%;margin-top:0.5rem;border-radius:6px;">
                    </div>
                </div>
                <div id="identity_status" class="mb-3"></div>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-outline-secondary" id="id_cancel">Cancel</button>
                    <button class="btn btn-primary" id="id_submit">Verify identity</button>
                </div>
            </div>
        </div>`;

    // Preview thumbnails.
    bindPreview('id_photo', 'id_preview');
    bindPreview('selfie_photo', 'selfie_preview');

    document.getElementById('id_cancel').onclick = () => closeOverlay();
    document.getElementById('id_submit').onclick = async () => {
        const idFile     = document.getElementById('id_photo').files[0];
        const selfieFile = document.getElementById('selfie_photo').files[0];
        if (!idFile || !selfieFile) {
            Notification.alert('Both photos required',
                'Please upload an ID photo AND a selfie.');
            return;
        }
        const status = document.getElementById('identity_status');
        status.innerHTML = '<div class="alert alert-info">Verifying...</div>';

        const [idB64, selfieB64] = await Promise.all([
            fileToBase64(idFile), fileToBase64(selfieFile)
        ]);
        const r = await Ajax.call([{
            methodname: 'local_sentientia_proctoring_submit_identity',
            args: { sessionid: SESSION_ID, id_b64: idB64, selfie_b64: selfieB64 }
        }])[0].catch(err => { Notification.exception(err); return null; });
        if (!r) return;

        if (r.passed) {
            status.innerHTML = '<div class="alert alert-success">Identity verified (score: '
                + Number(r.match_score).toFixed(1) + '%). Starting exam...</div>';
            setTimeout(() => {
                closeOverlay();
                // Kick off in-exam monitoring.
                require(['local_sentientia_proctoring/proctor'], (P) => P.start(SESSION_ID));
                // Trigger quiz form auto-submit if it exists.
                const autoForm = document.querySelector('[data-action="start-quiz"]');
                if (autoForm) autoForm.click();
            }, 1500);
        } else {
            status.innerHTML = '<div class="alert alert-danger">Verification failed: '
                + (r.error_msg || 'match score too low (' + Number(r.match_score).toFixed(1) + '%)')
                + '. Please retake.</div>';
        }
    };
};

// ── Helpers ──────────────────────────────────────────────────────────

const createOverlay = () => {
    let overlay = document.getElementById('airpay-proctor-overlay');
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.id = 'airpay-proctor-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);'
        + 'display:flex;align-items:center;justify-content:center;z-index:99999;'
        + 'padding:2rem;';
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {/* don't close on backdrop */}
    });
    const inner = document.createElement('div');
    inner.style.cssText = 'width:100%;max-width:720px;background:transparent;';
    overlay.appendChild(inner);
    document.body.appendChild(overlay);
    return inner;
};

const closeOverlay = () => {
    const overlay = document.getElementById('airpay-proctor-overlay');
    if (overlay) overlay.remove();
};

const bindPreview = (inputId, previewId) => {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    input.onchange = (e) => {
        const f = e.target.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        preview.src = url;
        preview.style.display = 'block';
    };
};

const fileToBase64 = (file) => new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => {
        const b64 = r.result.split(',')[1];  // strip data:...;base64,
        resolve(b64);
    };
    r.onerror = reject;
    r.readAsDataURL(file);
});
