<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English language strings — Sentientia LMS GenAI Authoring Studio.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia GenAI Authoring Studio';

// Capabilities.
$string['sentientia_authoring:generate'] = 'Generate course drafts in the Authoring Studio';
$string['sentientia_authoring:review'] = 'Review, edit and finalise generated drafts';
$string['sentientia_authoring:managetemplates'] = 'Create and edit instructional-design templates';
$string['sentientia_authoring:manage_all'] = 'Manage all drafts and templates across owners and tenants';

// Navigation.
$string['nav_studio'] = 'Authoring Studio';
$string['nav_templates'] = 'Design templates';

// Mode badges.
$string['mode_disabled_badge'] = 'Authoring Studio is disabled — turn on the sentientia.authoring.enabled feature flag.';
$string['mode_mock_badge'] = 'MOCK MODE — generation returns deterministic placeholder content. No Anthropic call is made and nothing is charged.';
$string['mode_no_apikey_badge'] = 'Live API flag is ON but no Anthropic API key is configured — generation will run in mock mode until a key is set.';
$string['mode_live_badge'] = 'LIVE MODE — generation will call Anthropic. Each run is billed per token.';

// Index page.
$string['index_page_title'] = 'Authoring Studio';
$string['index_page_heading'] = 'Authoring Studio — my course drafts';
$string['index_empty'] = 'You have no course drafts yet. Open the studio to generate your first microlearning module.';
$string['index_col_title'] = 'Title';
$string['index_col_status'] = 'Status';
$string['index_col_cards'] = 'Cards';
$string['index_col_questions'] = 'Questions';
$string['index_col_created'] = 'Created';
$string['index_col_actions'] = 'Actions';
$string['index_review_link'] = 'Review';

// Studio (generate) page.
$string['studio_page_title'] = 'Authoring Studio — generate';
$string['studio_page_heading'] = 'Generate a microlearning module';
$string['studio_intro'] = 'Turn a prompt, a pasted document, or extracted PDF text into a full microlearning module — interactive cards plus a mixed-type assessment. Every draft goes through a human-review gate before it can be published.';
$string['studio_form_title'] = 'Module title';
$string['studio_form_template'] = 'Instructional-design template';
$string['studio_form_template_help'] = 'Optional. Pick a template to shape the structure, or leave as freeform.';
$string['studio_form_template_none'] = 'Freeform (no template)';
$string['studio_form_sourcetype'] = 'Source type';
$string['studio_form_language'] = 'Output language';
$string['studio_form_language_help'] = 'Languages beyond English and Hindi are produced in English and localized via the translation plugin when available.';
$string['studio_form_source'] = 'Source content';
$string['studio_form_source_help'] = 'Paste your prompt, document text, or PDF extract here (max {$a} words). Do not paste employee PII.';
$string['studio_form_numcards'] = 'Number of cards';
$string['studio_form_numq'] = 'Number of questions';
$string['studio_form_mastery'] = 'Mastery score (%)';
$string['studio_form_model'] = 'Model';
$string['studio_confirm_label'] = 'I confirm I want to run this generation. (In live mode this calls Anthropic and is billed per token.)';
$string['studio_confirm_help'] = 'This [CONFIRM] gate is required for every generation, including mock-mode runs, so the workflow is identical in both modes.';
$string['studio_submit'] = 'Generate draft';

$string['sourcetype_prompt'] = 'Prompt';
$string['sourcetype_doc'] = 'Document text';
$string['sourcetype_pdf'] = 'PDF extract';
$string['language_en'] = 'English';
$string['language_hi'] = 'Hindi (हिन्दी)';
$string['template_builtin_suffix'] = '(built-in)';

$string['tokens_used_today'] = 'Tokens used today: {$a->used} of {$a->cap}.';
$string['source_word_count'] = '{$a} words';

// Review page.
$string['review_page_title'] = 'Authoring Studio — review';
$string['review_page_heading'] = 'Review course draft';
$string['review_meta'] = 'Status: {$a->status} · Mode: {$a->mode} · {$a->cards} cards · {$a->questions} questions · Mastery: {$a->mastery}%';
$string['review_gate_notice'] = 'Human-review gate: approve or edit at least one card before finalising. Nothing generated here is published automatically.';
$string['review_failed'] = 'Generation failed: {$a}';
$string['review_back_to_studio'] = 'Back to the studio';
$string['review_cards_heading'] = 'Interactive cards';
$string['review_questions_heading'] = 'Assessment questions';
$string['review_flip_back'] = 'Flip back:';
$string['review_narration'] = 'Narration:';
$string['review_correct_mark'] = 'correct';
$string['review_feedback_correct'] = 'Feedback (correct):';
$string['review_feedback_incorrect'] = 'Feedback (incorrect):';
$string['review_note_placeholder'] = 'Reviewer note (optional)';
$string['review_btn_approved'] = 'Approve';
$string['review_btn_edited'] = 'Mark edited';
$string['review_btn_rejected'] = 'Reject';
$string['review_finalise'] = 'Finalise review';
$string['review_finalised_approved'] = 'Review finalised — draft approved. It can now be published or voiced.';
$string['review_finalised_rejected'] = 'Review finalised — draft rejected (no cards approved).';
$string['review_voiceover_link'] = 'Voiceover this card';

$string['item_status_generated'] = 'Awaiting review';
$string['item_status_approved'] = 'Approved';
$string['item_status_edited'] = 'Edited';
$string['item_status_rejected'] = 'Rejected';

// Templates page.
$string['templates_page_title'] = 'Authoring Studio — design templates';
$string['templates_page_heading'] = 'Instructional-design templates';
$string['templates_new_button'] = 'New template';
$string['templates_new_heading'] = 'New template';
$string['templates_edit_heading'] = 'Edit template';
$string['templates_empty'] = 'No templates yet. Create one to shape how the studio structures a module.';
$string['templates_col_name'] = 'Name';
$string['templates_col_description'] = 'Description';
$string['templates_col_actions'] = 'Actions';
$string['templates_form_name'] = 'Template name';
$string['templates_form_description'] = 'Short description';
$string['templates_form_body'] = 'Template body';
$string['templates_form_body_help'] = 'Describe the structure and tone the generator should follow (card sequence, question mix, register).';
$string['templates_archive'] = 'Archive';
$string['templates_saved'] = 'Template saved.';
$string['templates_created'] = 'Template created.';
$string['templates_archived'] = 'Template archived.';

// Voiceover page.
$string['voiceover_page_title'] = 'Authoring Studio — voiceover';
$string['voiceover_page_heading'] = 'Generate TTS voiceover';
$string['voiceover_mode_mock'] = 'MOCK MODE — a deterministic placeholder is produced. No ElevenLabs call is made and nothing is charged.';
$string['voiceover_mode_live'] = 'LIVE MODE — this will call ElevenLabs. Charged per character.';
$string['voiceover_cost_estimate'] = 'Narration length: {$a->chars} characters. Estimated live cost: ${$a->cost}.';
$string['voiceover_confirm_label'] = 'I confirm I want to generate this voiceover. (In live mode this calls ElevenLabs and is billed per character.)';
$string['voiceover_confirm_help'] = 'This [CONFIRM] gate is required for every voiceover, including mock-mode runs.';
$string['voiceover_submit'] = 'Generate voiceover';
$string['voiceover_no_narration'] = 'This card has no narration script to voice.';
$string['voiceover_done_mock'] = 'Mock voiceover recorded — no ElevenLabs call was made.';
$string['voiceover_done_live'] = 'Voiceover generated.';
$string['voiceover_failed'] = 'Voiceover failed: {$a}';

// Localization strategy labels.
$string['localize_native'] = 'Generated natively in the target language.';
$string['localize_translate'] = 'Generated in English, then localized via the translation plugin.';
$string['localize_degraded'] = 'Translation plugin unavailable — output stays in English.';

// Settings.
$string['settings_heading_ai'] = 'AI generation (Anthropic)';
$string['settings_heading_ai_desc'] = 'Credentials for live course generation. Inert unless the live_api feature flag is ON and the [CONFIRM] gate is passed. With the default flags OFF, the studio runs in mock mode and these are never read.';
$string['setting_anthropic_api_key'] = 'Anthropic API key';
$string['setting_anthropic_api_key_desc'] = 'Stored securely. Never logged. Only read on a live, [CONFIRM]-gated generation.';
$string['setting_default_model'] = 'Default model';
$string['setting_default_model_desc'] = 'Anthropic model identifier used for generation (e.g. claude-sonnet-4-6).';
$string['settings_heading_tts'] = 'TTS voiceover (ElevenLabs)';
$string['settings_heading_tts_desc'] = 'Credentials for live TTS. Inert unless both the tts and live_api flags are ON and the [CONFIRM] gate is passed.';
$string['setting_elevenlabs_api_key'] = 'ElevenLabs API key';
$string['setting_elevenlabs_api_key_desc'] = 'Stored securely. Never logged. Only read on a live, [CONFIRM]-gated voiceover.';
$string['setting_elevenlabs_voice_id'] = 'ElevenLabs voice ID';
$string['setting_elevenlabs_voice_id_desc'] = 'The voice used for live TTS. Ignored in mock mode.';
$string['settings_heading_limits'] = 'Limits';
$string['settings_heading_limits_desc'] = 'Per-request and per-day ceilings that bound generation cost and module size.';
$string['setting_max_cards'] = 'Max cards per module';
$string['setting_max_cards_desc'] = 'Upper bound on the number of cards a single generation may request.';
$string['setting_max_questions'] = 'Max questions per module';
$string['setting_max_questions_desc'] = 'Upper bound on the number of questions a single generation may request.';
$string['setting_max_source_words'] = 'Max source words';
$string['setting_max_source_words_desc'] = 'Reject source content longer than this many words.';
$string['setting_daily_token_cap'] = 'Daily token cap (per user)';
$string['setting_daily_token_cap_desc'] = 'Block further generation once a user has consumed this many tokens today.';
$string['setting_default_mastery_score'] = 'Default mastery score (%)';
$string['setting_default_mastery_score_desc'] = 'Default pass threshold for a generated assessment (CLAUDE.md §8 default 70).';

// Errors.
$string['err_feature_off'] = 'The Authoring Studio is not enabled.';
$string['err_tts_off'] = 'TTS voiceover is not enabled.';
$string['err_source_empty'] = 'Source content cannot be empty.';
$string['err_source_too_long'] = 'Source content exceeds the word limit.';
$string['err_source_contains_pii'] = 'Source content appears to contain PII (Aadhaar or PAN). Remove it before generating.';
$string['err_invalid_cards'] = 'Number of cards must be between {$a->min} and {$a->max}.';
$string['err_invalid_questions'] = 'Number of questions must be between {$a->min} and {$a->max}.';
$string['err_invalid_mastery'] = 'Mastery score must be between 0 and 100.';
$string['err_confirm_required'] = 'You must tick the confirmation checkbox before proceeding.';
$string['err_token_cap_reached'] = 'Daily token cap reached ({$a->used} of {$a->cap}). Try again tomorrow.';
$string['err_api_failed'] = 'Generation call failed: {$a}';
$string['err_template_not_found'] = 'Template not found or not accessible.';
$string['err_template_builtin'] = 'Built-in templates cannot be archived or deleted.';
$string['err_draft_not_found'] = 'Draft not found or not accessible.';
$string['err_card_not_found'] = 'Card not found in this draft.';
$string['err_publish_not_approved'] = 'A draft must be reviewed and approved before it can be published.';

// Privacy.
$string['privacy:path:drafts'] = 'Course drafts';
$string['privacy:path:templates'] = 'Design templates';
$string['privacy:metadata:template'] = 'Instructional-design templates created by the user.';
$string['privacy:metadata:template:ownerid'] = 'The user who authored the template.';
$string['privacy:metadata:template:name'] = 'The template name.';
$string['privacy:metadata:template:body'] = 'The template body content.';
$string['privacy:metadata:template:timecreated'] = 'When the template was created.';
$string['privacy:metadata:template:timemodified'] = 'When the template was last modified.';
$string['privacy:metadata:draft'] = 'Course-generation drafts created by the user.';
$string['privacy:metadata:draft:ownerid'] = 'The user who created the draft.';
$string['privacy:metadata:draft:title'] = 'The draft title.';
$string['privacy:metadata:draft:sourcetext'] = 'The source content submitted for generation.';
$string['privacy:metadata:draft:model'] = 'The AI model identifier used.';
$string['privacy:metadata:draft:tokens'] = 'Token counts recorded for cost tracking.';
$string['privacy:metadata:draft:reviewed_by'] = 'The user who reviewed the draft.';
$string['privacy:metadata:draft:reviewed_at'] = 'When the review was completed.';
$string['privacy:metadata:draft:timecreated'] = 'When the draft was created.';
$string['privacy:metadata:draft:timemodified'] = 'When the draft was last modified.';
$string['privacy:metadata:card'] = 'Interactive cards generated for a draft.';
$string['privacy:metadata:card:draftid'] = 'The parent draft.';
$string['privacy:metadata:card:body'] = 'The card content.';
$string['privacy:metadata:card:reviewer_note'] = 'A reviewer note on the card.';
$string['privacy:metadata:card:timecreated'] = 'When the card was created.';
$string['privacy:metadata:card:timemodified'] = 'When the card was last modified.';
$string['privacy:metadata:question'] = 'Assessment questions generated for a draft.';
$string['privacy:metadata:question:draftid'] = 'The parent draft.';
$string['privacy:metadata:question:qtext'] = 'The question stem.';
$string['privacy:metadata:question:qoptions'] = 'The question options or pairs.';
$string['privacy:metadata:question:reviewer_note'] = 'A reviewer note on the question.';
$string['privacy:metadata:question:timecreated'] = 'When the question was created.';
$string['privacy:metadata:question:timemodified'] = 'When the question was last modified.';
$string['privacy:metadata:anthropic'] = 'Source content may be sent to Anthropic for course generation, but only when the live_api feature flag is ON (it is OFF by default).';
$string['privacy:metadata:anthropic:sourcetext'] = 'The source content sent for generation.';
$string['privacy:metadata:anthropic:model'] = 'The model the request targeted.';
$string['privacy:metadata:elevenlabs'] = 'Narration text may be sent to ElevenLabs for voiceover, but only when the live_api feature flag is ON (it is OFF by default).';
$string['privacy:metadata:elevenlabs:narration'] = 'The narration text sent for synthesis.';

// ── Course builder — gate #3 closure (2026-08-05) ───────────────────
$string['publish_to_course']       = 'Publish as course';
$string['publish_selectcategory']  = 'Course category';
$string['publish_disabled']        = 'Course publishing is disabled (flag sentientia.authoring.publish.enabled is OFF) — it flips after the ninja/staging verification.';
$string['publish_success']         = 'Course "{$a->shortname}" created (hidden) with {$a->cardcount} content card(s) and {$a->questioncount} quiz question(s). Review it, then make it visible.';
$string['publish_failed']          = 'Publish failed: {$a}';
$string['publish_course_summary']  = 'Generated by the Sentientia authoring studio from draft #{$a->draftid} (model: {$a->model}) and human-reviewed before publishing.';
$string['publish_book_name']       = 'Course content';
$string['publish_card_untitled']   = 'Card {$a}';
$string['publish_card_keypoint']   = 'Key point:';
$string['publish_card_transcript'] = 'Narration transcript';
$string['publish_quiz_name']       = 'Mastery check ({$a}% to pass)';
$string['publish_quiz_intro']      = 'Answer the questions below. You need {$a}% to pass this course.';
$string['err_publish_nocards']     = 'No approved or edited cards to publish.';
$string['err_publish_nobank']      = 'Could not resolve the course question bank.';
$string['err_publish_importcount'] = 'Question import mismatch: expected {$a->expected}, imported {$a->actual}. Nothing was published.';
$string['err_publish_badoptions']  = 'Question {$a} has fewer than two answer options.';
$string['err_publish_badanswer']   = 'Question {$a} has a correct-answer index outside its options.';
$string['err_publish_tempfile']    = 'Could not write the import work file.';
$string['err_publish_import']      = 'The question import failed. Nothing was published.';
