<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI Course Recommendations';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_recommendations:view']       = 'See AI course recommendations on the dashboard';
$string['sentientia_recommendations:generate']   = 'Generate AI course recommendations for a learner';
$string['sentientia_recommendations:manage_all'] = 'Manage AI recommendation history across all learners';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'The Sentientia LMS AI Recommendations plugin stores personalised course recommendations and a short reasoning for each. When the live API flag is ON, an anonymised learner profile (role, tenant, completed course IDs, skill tags) is transmitted to Anthropic for processing.';
$string['privacy:metadata:rec']               = 'Per-learner AI-generated course recommendations.';
$string['privacy:metadata:rec:userid']        = 'The learner the recommendation is for.';
$string['privacy:metadata:rec:courseid']      = 'The recommended course.';
$string['privacy:metadata:rec:score']         = 'The confidence score Claude assigned to this recommendation.';
$string['privacy:metadata:rec:reasoning']     = 'The short rationale explaining why this course is a sensible next step.';
$string['privacy:metadata:rec:tokens']        = 'Anthropic API token usage for cost accounting.';
$string['privacy:metadata:rec:status']        = 'Lifecycle of the recommendation: active, dismissed, enrolled, expired.';
$string['privacy:metadata:rec:generated_at']  = 'When the recommendation was generated.';
$string['privacy:metadata:rec:timecreated']   = 'When the recommendation was created.';
$string['privacy:metadata:rec:timemodified']  = 'When the recommendation was last modified.';
$string['privacy:metadata:anthropic']                 = 'Anthropic Claude API — when the live API flag is enabled, an anonymised learner profile is sent to Anthropic for recommendation generation. The API call is gated behind a per-call confirmation step.';
$string['privacy:metadata:anthropic:profile_role']      = 'The learner role label (e.g. learner, manager).';
$string['privacy:metadata:anthropic:profile_completed'] = 'Course IDs the learner has completed.';
$string['privacy:metadata:anthropic:profile_skills']    = 'Skill tags currently on file for the learner.';
$string['privacy:metadata:anthropic:model']             = 'The Anthropic model identifier used.';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_generate'] = 'Generate AI recommendations';

// ── Generate page ──────────────────────────────────────────────────
$string['generate_page_title']       = 'Generate AI course recommendations';
$string['generate_page_heading']     = 'Generate personalised course recommendations';
$string['generate_intro']            = 'Select a learner and request a personalised batch of course recommendations from Anthropic Claude. Each recommendation comes with a short rationale that the learner can review on their dashboard.';
$string['generate_form_targetuser']  = 'Target learner (user ID)';
$string['generate_form_targetuser_help'] = 'The user ID of the learner this batch will be generated for. Defaults to your own user ID.';
$string['generate_form_num']         = 'Number of recommendations';
$string['generate_form_num_help']    = 'Between 1 and {$a}. Claude may return fewer if the candidate catalogue is too small.';
$string['generate_form_model']       = 'Model';
$string['generate_confirm_label']    = 'I confirm I want to call Anthropic with this learner profile';
$string['generate_confirm_help']     = 'This is the per-call confirmation gate. When the live API flag is ON, this submission will cost real money based on the profile + catalogue size. Untick to abort.';
$string['generate_submit']           = 'Generate recommendations';
$string['generate_cancel']           = 'Cancel';
$string['generate_success']          = 'Recommendation batch generated. Batch ID: {$a->batchid}. {$a->count} recommendations created in {$a->mode} mode. Tokens: {$a->tokens_in} in / {$a->tokens_out} out.';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']      = 'MOCK MODE — no live API call (set sentientia.recommendations.live_api = ON for real generation)';
$string['mode_live_badge']      = 'LIVE API — Anthropic call billed to your account';
$string['mode_disabled_badge']  = 'Feature disabled — set sentientia.recommendations.enabled = ON in the Switchboard';
$string['mode_no_apikey_badge'] = 'No API key configured — set local_sentientia_recommendations | api_key in Site admin';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']         = 'The AI Recommendations feature is disabled. Ask an administrator to enable sentientia.recommendations.enabled.';
$string['err_no_capability']       = 'You do not have permission to generate AI recommendations.';
$string['err_invalid_count']       = 'Number of recommendations must be between {$a->min} and {$a->max}.';
$string['err_confirm_required']    = 'You must tick the confirmation checkbox to call the API.';
$string['err_api_key_not_set']     = 'No Anthropic API key is configured. Cannot make a live call.';
$string['err_api_failed']          = 'Anthropic API call failed: {$a}';
$string['err_parser_zero']         = 'Claude responded but no usable recommendations could be parsed.';
$string['err_cost_cap_reached']    = 'Your customer account has used {$a->used} tokens today (daily soft cap is {$a->cap}). Try again tomorrow or ask an admin to raise the cap.';
$string['err_user_not_found']      = 'Target learner not found or has been deleted.';
$string['err_candidates_empty']    = 'No candidate courses available for this learner.';
$string['err_profile_invalid']     = 'Learner profile is invalid.';
$string['err_profile_contains_pii'] = 'Learner profile contains PII patterns (Aadhaar or PAN). Redact and resubmit.';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']                = 'Anthropic API';
$string['settings_heading_api_desc']           = 'Credentials for the Anthropic Claude API. The key is only used when both sentientia.recommendations.enabled and sentientia.recommendations.live_api are ON.';
$string['setting_api_key']                     = 'Anthropic API key';
$string['setting_api_key_desc']                = 'Paste the API key from console.anthropic.com. Stored encrypted at rest. NEVER commit this value to source control.';
$string['setting_default_model']               = 'Default model';
$string['setting_default_model_desc']          = 'The Anthropic model used when a generator does not specify one. Recommended: claude-sonnet-4-6.';
$string['setting_max_output_tokens']           = 'Maximum output tokens';
$string['setting_max_output_tokens_desc']      = 'Hard cap on Anthropic max_tokens per call. Default 2048.';
$string['settings_heading_limits']             = 'Limits and quotas';
$string['settings_heading_limits_desc']        = 'Per-batch and per-customer limits to keep costs predictable.';
$string['setting_max_recommendations']         = 'Maximum recommendations per batch';
$string['setting_max_recommendations_desc']    = 'Upper bound on recommendations per generation. Defaults to 5.';
$string['setting_max_history_items']           = 'Maximum completion history items';
$string['setting_max_history_items_desc']      = 'How many of the learner\'s most recent completions to feed into the prompt. Defaults to 50.';
$string['setting_daily_cost_cap']              = 'Per-customer daily token cap';
$string['setting_daily_cost_cap_desc']         = 'Soft cap on tokens (input + output) consumed by recommendation generation per customer per day. Once exceeded, generate.php returns an error until midnight.';
$string['settings_heading_prompt']             = 'Prompt configuration';
$string['settings_heading_prompt_desc']        = 'Prompts are versioned in code (prompt_builder::VERSION). This free-text field is informational only.';
$string['setting_prompt_template_note']        = 'Prompt template note';
$string['setting_prompt_template_note_desc']   = 'Free-form notes about the current prompt template — surfaced in the admin UI only. Does NOT override prompt_builder.';

// ── Block strings ──────────────────────────────────────────────────
$string['block_title']           = 'Recommended for you';
$string['block_empty']           = 'You have no recommendations yet. Check back later.';
$string['block_disabled']        = 'AI recommendations are currently disabled.';
$string['block_view_all']        = 'View all';
$string['block_dismiss']         = 'Not interested';
$string['block_why']             = 'Why this?';
$string['block_score']           = 'Match';

// ── Misc ───────────────────────────────────────────────────────────
$string['tokens_used_today']     = 'Tokens used today (customer-wide): {$a->used} / {$a->cap}';
$string['recommendation_card']   = 'Recommendation #{$a}';
