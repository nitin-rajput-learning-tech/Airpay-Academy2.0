<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI Quiz Generation';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_aiquiz:generate']    = 'Generate AI-drafted quiz questions';
$string['sentientia_aiquiz:review']      = 'Review AI-drafted quiz questions';
$string['sentientia_aiquiz:manage_all']  = 'Manage AI quiz drafts across all owners';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'The Sentientia LMS AI Quiz plugin stores quiz drafts and the source text used to generate them. When the live API flag is ON, source text is transmitted to Anthropic for processing.';
$string['privacy:metadata:draft']            = 'AI quiz drafts created by users.';
$string['privacy:metadata:draft:ownerid']    = 'The user who created the draft.';
$string['privacy:metadata:draft:sourcetext'] = 'The verbatim source text the user provided for generation.';
$string['privacy:metadata:draft:title']      = 'The user-supplied title of the draft.';
$string['privacy:metadata:draft:tokens']     = 'Anthropic API token usage for cost accounting.';
$string['privacy:metadata:draft:reviewed_by'] = 'The user who reviewed the draft.';
$string['privacy:metadata:draft:reviewed_at'] = 'When the draft was reviewed.';
$string['privacy:metadata:draft:timecreated']  = 'When the draft was created.';
$string['privacy:metadata:draft:timemodified'] = 'When the draft was last modified.';
$string['privacy:metadata:question']         = 'Individual AI-generated questions belonging to a draft.';
$string['privacy:metadata:question:draftid'] = 'The parent draft this question belongs to.';
$string['privacy:metadata:question:qtext']   = 'The question stem text.';
$string['privacy:metadata:question:qoptions'] = 'JSON-encoded option strings for multichoice questions.';
$string['privacy:metadata:question:reviewer_note'] = 'Reviewer comments on the question.';
$string['privacy:metadata:question:timecreated']   = 'When the question was generated.';
$string['privacy:metadata:question:timemodified']  = 'When the question was last modified.';
$string['privacy:metadata:anthropic']             = 'Anthropic Claude API — when the live API flag is enabled, source text is sent to Anthropic for quiz generation. The API call is gated behind a per-call confirmation step.';
$string['privacy:metadata:anthropic:sourcetext']  = 'The trainer-supplied source text.';
$string['privacy:metadata:anthropic:model']       = 'The Anthropic model identifier used.';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_generate'] = 'Generate AI quiz';
$string['nav_review']   = 'Review AI quiz drafts';

// ── Generate page ──────────────────────────────────────────────────
$string['generate_page_title']    = 'Generate AI quiz';
$string['generate_page_heading']  = 'Generate a quiz draft from course content';
$string['generate_intro']         = 'Paste source material (SCORM transcript, narration text, SOP excerpt) below. Sentientia LMS sends it to Anthropic Claude to generate a quiz draft. Every draft must be reviewed by a human before any question can be pushed to a course quiz.';
$string['generate_form_title']    = 'Draft title';
$string['generate_form_title_help'] = 'A short label so you can find this draft later in the review queue.';
$string['generate_form_course']   = 'Course';
$string['generate_form_course_help'] = 'Which course the resulting quiz belongs to. Select \"Site-wide / not yet assigned\" to defer this choice until review time.';
$string['generate_form_course_none'] = 'Site-wide / not yet assigned';
$string['generate_form_source']   = 'Source content';
$string['generate_form_source_help'] = 'Up to {$a} words. Remove any PII (employee names, IDs, customer data) before pasting.';
$string['generate_form_num']      = 'Number of questions to request';
$string['generate_form_num_help'] = 'Between 1 and {$a}. Claude may return fewer if the source is too short to support that many.';
$string['generate_form_model']    = 'Model';
$string['generate_form_model_help'] = 'Anthropic model identifier. Default: claude-sonnet-4-6.';
$string['generate_confirm_label'] = 'I confirm I want to call Anthropic with this content';
$string['generate_confirm_help']  = 'This is the per-call confirmation gate. When the live API flag is ON, this submission will cost real money based on the source length. Untick to abort.';
$string['generate_submit']        = 'Generate quiz draft';
$string['generate_cancel']        = 'Cancel';

// ── G.1 language picker + prompt preview ──────────────────────────
$string['generate_form_language']       = 'Quiz language';
$string['generate_form_language_help']  = 'Selects the system prompt sent to Claude. English uses the v1 baseline prompt. Hindi uses the v2-hindi prompt and asks Claude to return questions in Devanagari.';
$string['generate_form_language_en']    = 'English (v1)';
$string['generate_form_language_hi']    = 'हिन्दी / Hindi (v2-hindi)';
$string['generate_prompt_preview_summary'] = 'Preview the system prompt Claude will see (prompt version: {$a->version}, customer: {$a->customer})';
$string['generate_prompt_preview_help']    = 'This is the exact system prompt that will be sent to Anthropic if you click Generate with the live API flag ON. Mock mode produces fake questions and does not call the API. When a per-customer template is configured below, it replaces the baseline prompt body verbatim.';
$string['generate_prompt_preview_custom_badge'] = 'Custom per-customer template active';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']        = 'MOCK MODE — no live API call (set sentientia.aiquiz.live_api = ON for real generation)';
$string['mode_live_badge']        = 'LIVE API — Anthropic call billed to your account';
$string['mode_disabled_badge']    = 'Feature disabled — set sentientia.aiquiz.enabled = ON in the Switchboard';
$string['mode_no_apikey_badge']   = 'No API key configured — set local_sentientia_aiquiz | api_key in Site admin';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']       = 'The AI Quiz feature is disabled. Ask an administrator to enable sentientia.aiquiz.enabled.';
$string['err_no_capability']     = 'You do not have permission to generate AI quiz drafts.';
$string['err_source_empty']      = 'Source content is required.';
$string['err_source_too_long']   = 'Source content exceeds the configured word limit. Trim it before submitting.';
$string['err_source_contains_pii'] = 'Source content appears to contain PII (Aadhaar or PAN numbers). Redact and resubmit.';
$string['err_invalid_count']     = 'Number of questions must be between {$a->min} and {$a->max}.';
$string['err_token_cap_reached'] = 'Your account has used {$a->used} tokens today (daily soft cap is {$a->cap}). Try again tomorrow or ask an admin to raise the cap.';
$string['err_confirm_required']  = 'You must tick the confirmation checkbox to call the API.';
$string['err_api_key_not_set']   = 'No Anthropic API key is configured. Cannot make a live call.';
$string['err_api_failed']        = 'Anthropic API call failed: {$a}';
$string['err_parser_zero']       = 'Claude responded but no usable questions could be parsed. Try a longer or clearer source.';

// ── Review page ────────────────────────────────────────────────────
$string['review_page_title']     = 'Review AI quiz draft';
$string['review_page_heading']   = 'Review draft #{$a}';
$string['review_intro']          = 'Review each generated question below. Approve, edit, or reject — only approved or edited questions can be pushed to a course quiz.';
$string['review_no_draft']       = 'Draft not found or you do not have permission to view it.';
$string['review_meta_owner']     = 'Owner';
$string['review_meta_course']    = 'Course';
$string['review_meta_model']     = 'Model';
$string['review_meta_prompt']    = 'Prompt version';
$string['review_meta_tokens']    = 'Tokens used';
$string['review_meta_generated_at'] = 'Generated at';
$string['review_meta_mode']      = 'Generation mode';
$string['review_status']         = 'Status';
$string['review_status_pending']   = 'Pending generation';
$string['review_status_generated'] = 'Awaiting review';
$string['review_status_approved']  = 'Approved (ready to push)';
$string['review_status_pushed']    = 'Pushed to quiz #{$a}';
$string['review_status_rejected']  = 'Rejected';
$string['review_status_failed']    = 'Generation failed';
$string['review_question_label'] = 'Question {$a}';
$string['review_question_answer'] = 'Correct answer';
$string['review_question_explanation'] = 'Explanation';
$string['review_action_approve'] = 'Approve';
$string['review_action_edit']    = 'Edit';
$string['review_action_reject']  = 'Reject';
$string['review_action_save_edit'] = 'Save edits';
$string['review_action_cancel_edit'] = 'Cancel edit';
$string['review_q_status_generated'] = 'Not yet reviewed';
$string['review_q_status_approved']  = 'Approved';
$string['review_q_status_edited']    = 'Edited';
$string['review_q_status_rejected']  = 'Rejected';
$string['review_finalise']       = 'Finalise review';
$string['review_finalise_help']  = 'Marks this draft as fully reviewed. Status becomes \"approved\" if at least one question is approved or edited, otherwise \"rejected\".';
$string['review_push_to_quiz']   = 'Push approved questions to course quiz';
$string['review_push_disabled']  = 'Push to mod_quiz is gated behind sentientia.aiquiz.auto_push (default OFF).';
$string['review_push_success']   = 'Approved questions pushed to quiz #{$a->quizid} ({$a->count} questions).';
$string['review_no_questions']   = 'No questions were generated for this draft.';
$string['review_empty_state']    = 'You have no AI quiz drafts yet. Use the Generate page to create one.';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']           = 'Anthropic API';
$string['settings_heading_api_desc']      = 'Credentials for the Anthropic Claude API. The key is only used when both sentientia.aiquiz.enabled and sentientia.aiquiz.live_api are ON.';
$string['setting_api_key']                = 'Anthropic API key';
$string['setting_api_key_desc']           = 'Paste the API key from console.anthropic.com. Stored encrypted at rest by Moodle\'s configpasswordunmask. NEVER commit this value to source control.';
$string['setting_default_model']          = 'Default model';
$string['setting_default_model_desc']     = 'The Anthropic model used when a generator does not specify one. Recommended: claude-sonnet-4-6.';
$string['settings_heading_limits']        = 'Limits and quotas';
$string['settings_heading_limits_desc']   = 'Per-user and per-request limits to keep costs predictable.';
$string['setting_max_questions']          = 'Maximum questions per request';
$string['setting_max_questions_desc']     = 'Upper bound on questions per generation. Defaults to 10.';
$string['setting_daily_token_cap']        = 'Per-user daily token cap';
$string['setting_daily_token_cap_desc']   = 'Soft cap on tokens (input + output) a single user can spend per day. Once exceeded, generate.php returns an error until midnight.';
$string['setting_max_source_words']       = 'Maximum source words per draft';
$string['setting_max_source_words_desc']  = 'Source text is rejected if it exceeds this word count. Defaults to 4000 — about 8 pages.';

// ── G.1 per-customer prompt template settings ─────────────────────
$string['settings_heading_customer_prompts']        = 'Per-customer prompt templates';
$string['settings_heading_customer_prompts_desc']   = 'Optional override of the system prompt sent to Claude, per Sentientia LMS customer. When set, the textarea contents REPLACE the baseline v1 (English) or v2-hindi prompt body verbatim. Leave blank to use the in-code baseline. The user-message wrapper (begin/end markers + the "exactly N questions" instruction) always follows the language picker on the generate form.';
$string['setting_customer_1_prompt_template']       = 'Airpay (customer 1) — custom prompt template';
$string['setting_customer_1_prompt_template_desc']  = 'Paste a customer-specific system prompt for Airpay. Leave blank to use the v1/v2-hindi baseline. Stored under local_sentientia_platform/customer_1_aiquiz_prompt_template — read by \\local_sentientia_platform\\customer::get_customer_config(). Drafts generated while a custom template is active have their prompt_version recorded as "custom:v1" or "custom:v2-hindi".';

// ── Misc ───────────────────────────────────────────────────────────
$string['source_word_count'] = 'Word count: {$a}';
$string['tokens_used_today'] = 'Tokens used today: {$a->used} / {$a->cap}';
$string['back_to_drafts']    = 'Back to drafts list';
$string['drafts_list_title'] = 'Your AI quiz drafts';
