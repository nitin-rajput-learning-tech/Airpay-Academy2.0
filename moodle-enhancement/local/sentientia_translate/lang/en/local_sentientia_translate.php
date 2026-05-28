<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI Content Translation';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_translate:translate']     = 'Translate content with Anthropic Claude';
$string['sentientia_translate:manage_brands'] = 'Manage per-customer brand-name overrides';
$string['sentientia_translate:manage_all']    = 'Manage translation history across all owners';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'The Sentientia LMS AI Translation plugin stores source text and its translations. When the live API flag is ON, source text is transmitted to Anthropic for processing.';
$string['privacy:metadata:tr']               = 'Content translations created by users.';
$string['privacy:metadata:tr:ownerid']       = 'The user who created the translation.';
$string['privacy:metadata:tr:sourcetext']    = 'The verbatim source text provided for translation.';
$string['privacy:metadata:tr:translatedtext'] = 'The translated output.';
$string['privacy:metadata:tr:targetlang']    = 'The target language code.';
$string['privacy:metadata:tr:title']         = 'The user-supplied title of the translation.';
$string['privacy:metadata:tr:tokens']        = 'Anthropic API token usage for cost accounting.';
$string['privacy:metadata:tr:timecreated']   = 'When the translation was created.';
$string['privacy:metadata:tr:timemodified']  = 'When the translation was last modified.';
$string['privacy:metadata:anthropic']             = 'Anthropic Claude API — when the live API flag is enabled, source text is sent to Anthropic for translation. The API call is gated behind a per-call confirmation step.';
$string['privacy:metadata:anthropic:sourcetext']  = 'The source text to translate.';
$string['privacy:metadata:anthropic:targetlang']  = 'The target language code requested.';
$string['privacy:metadata:anthropic:model']       = 'The Anthropic model identifier used.';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_translate'] = 'Translate content';

// ── Target languages ───────────────────────────────────────────────
$string['lang_hi'] = 'Hindi (हिन्दी)';
$string['lang_mr'] = 'Marathi (मराठी)';
$string['lang_kn'] = 'Kannada (ಕನ್ನಡ)';
$string['lang_sw'] = 'Swahili (Kiswahili)';

// ── Translate page ─────────────────────────────────────────────────
$string['translate_page_title']   = 'Translate content';
$string['translate_page_heading'] = 'Translate course content with AI';
$string['translate_intro']        = 'Paste English source content, choose a target language, and Anthropic Claude produces a translation in the native script. Brand names are preserved per your customer overrides. Review the side-by-side diff before saving.';
$string['form_title']             = 'Translation title';
$string['form_targetlang']        = 'Target language';
$string['form_source']            = 'Source content (English)';
$string['form_source_help']       = 'Up to {$a} words. Remove any PII (employee names, IDs, customer data) before pasting.';
$string['form_model']             = 'Model';
$string['form_confirm_label']     = 'I confirm I want to call Anthropic with this content';
$string['form_confirm_help']      = 'This is the per-call confirmation gate. When the live API flag is ON, this submission will cost real money based on the source length. Untick to abort.';
$string['form_submit']            = 'Translate';
$string['form_cancel']            = 'Cancel';

// ── Diff page ──────────────────────────────────────────────────────
$string['diff_heading']      = 'Review translation';
$string['diff_meta']         = 'Target language: {$a->lang} | Brand substitutions applied: {$a->brands} | Mode: {$a->mode}';
$string['diff_source']       = 'Source (English)';
$string['diff_translation']  = 'Translation';
$string['action_save']       = 'Save translation';
$string['action_discard']    = 'Discard';
$string['back_to_translate'] = 'Back to translate';
$string['saved_notice']      = 'Translation saved.';
$string['discarded_notice']  = 'Translation discarded.';

// ── Status ─────────────────────────────────────────────────────────
$string['status_label']      = 'Status';
$string['status_pending']    = 'Pending';
$string['status_translated'] = 'Translated (awaiting review)';
$string['status_saved']      = 'Saved';
$string['status_failed']     = 'Failed';
$string['status_discarded']  = 'Discarded';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']      = 'MOCK MODE — no live API call (set sentientia.translate.live_api = ON for real translation)';
$string['mode_live_badge']      = 'LIVE API — Anthropic call billed to your account';
$string['mode_disabled_badge']  = 'Feature disabled — set sentientia.translate.enabled = ON in the Switchboard';
$string['mode_no_apikey_badge'] = 'No API key configured — set local_sentientia_translate | api_key in Site admin';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']        = 'The AI Translation feature is disabled. Ask an administrator to enable sentientia.translate.enabled.';
$string['err_no_capability']      = 'You do not have permission to translate content.';
$string['err_source_empty']       = 'Source content is required.';
$string['err_source_too_long']    = 'Source content exceeds the configured word limit. Trim it before submitting.';
$string['err_source_contains_pii'] = 'Source content appears to contain PII (Aadhaar or PAN numbers). Redact and resubmit.';
$string['err_unsupported_lang']   = 'Unsupported target language. Choose Hindi, Marathi, Kannada or Swahili.';
$string['err_confirm_required']   = 'You must tick the confirmation checkbox to call the API.';
$string['err_api_key_not_set']    = 'No Anthropic API key is configured. Cannot make a live call.';
$string['err_api_failed']         = 'Anthropic API call failed: {$a}';
$string['err_cost_cap_reached']   = 'Your customer account has used {$a->used} tokens today (daily soft cap is {$a->cap}). Try again tomorrow or ask an admin to raise the cap.';
$string['err_row_not_found']      = 'Translation not found or you do not have permission to view it.';

// ── Brand overrides page ───────────────────────────────────────────
$string['brands_page_title']     = 'Brand-name overrides';
$string['brands_page_heading']   = 'Brand-name overrides';
$string['brands_intro']          = 'Configure how brand names render in each target language. When a brand has an override for a language, every occurrence in the translation is replaced with the target-script form. Brands without an override are preserved verbatim.';
$string['brands_protected_label'] = 'Always preserved (verbatim unless overridden)';
$string['brands_empty']          = 'No brand overrides configured yet. Add one below.';
$string['brands_add_heading']    = 'Add a brand override';
$string['brand_source']          = 'Brand (English)';
$string['brand_lang']            = 'Language';
$string['brand_target']          = 'Target-script rendering';
$string['brand_add']             = 'Add override';
$string['brand_delete']          = 'Delete';
$string['brand_saved']           = 'Brand override saved.';
$string['brand_invalid']         = 'Invalid brand override. Source, target and a supported language are all required.';
$string['brand_deleted']         = 'Brand override deleted.';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']                = 'Anthropic API';
$string['settings_heading_api_desc']           = 'Credentials for the Anthropic Claude API. The key is only used when both sentientia.translate.enabled and sentientia.translate.live_api are ON.';
$string['setting_api_key']                     = 'Anthropic API key';
$string['setting_api_key_desc']                = 'Paste the API key from console.anthropic.com. Stored encrypted at rest. NEVER commit this value to source control.';
$string['setting_default_model']               = 'Default model';
$string['setting_default_model_desc']          = 'The Anthropic model used when a translator does not specify one. Recommended: claude-sonnet-4-6.';
$string['setting_max_output_tokens']           = 'Maximum output tokens';
$string['setting_max_output_tokens_desc']      = 'Hard cap on Anthropic max_tokens per call. Default 8192 (translations can be long).';
$string['settings_heading_limits']             = 'Limits and quotas';
$string['settings_heading_limits_desc']        = 'Per-request and per-customer limits to keep costs predictable.';
$string['setting_max_source_words']            = 'Maximum source words per translation';
$string['setting_max_source_words_desc']       = 'Source text is rejected if it exceeds this word count. Defaults to 4000 — about 8 pages.';
$string['setting_daily_cost_cap']              = 'Per-customer daily token cap';
$string['setting_daily_cost_cap_desc']         = 'Soft cap on tokens (input + output) consumed by translation per customer per day. Once exceeded, translate.php returns an error until midnight.';
$string['settings_heading_prompt']             = 'Prompt configuration';
$string['settings_heading_prompt_desc']        = 'Prompts are versioned in code (prompt_builder::VERSION). This free-text field is informational only.';
$string['setting_prompt_template_note']        = 'Prompt template note';
$string['setting_prompt_template_note_desc']   = 'Free-form notes about the current prompt template — surfaced in the admin UI only. Does NOT override prompt_builder.';

// ── Misc ───────────────────────────────────────────────────────────
$string['source_word_count'] = 'Word count: {$a}';
$string['tokens_used_today'] = 'Tokens used today (customer-wide): {$a->used} / {$a->cap}';

// ── C16 admin landing/queue UI (Bucket C / 2026-05-28) ─────────────
$string['admin_index_title']     = 'AI translation';
$string['admin_index_intro']     = 'Manage AI-driven content translations. Submit new translation jobs, review pending diffs, and audit past activity.';
$string['admin_index_flag_off_notice'] = 'The AI translation feature flag (sentientia.translate.enabled) is currently OFF. Translations can still be reviewed but no new Anthropic calls will run.';
$string['admin_index_queue']     = 'Recent translations';
$string['admin_index_empty']     = 'No translations match the current filters.';
$string['admin_index_truncated'] = 'Showing the 25 most recent rows. Refine the filters to narrow further.';
$string['admin_index_quicknav']  = 'Quick navigation';
$string['admin_index_link_translate']      = 'New translation';
$string['admin_index_link_translate_desc'] = 'Paste English source content and run a translation job into Hindi, Marathi, Kannada or Swahili.';
$string['admin_index_link_brands']         = 'Brand override map';
$string['admin_index_link_brands_desc']    = 'Manage per-customer brand-name substitutions (e.g. "Airpay" preserved verbatim or rendered in the target script).';
$string['admin_index_link_settings']       = 'Translation settings';
$string['admin_index_link_settings_desc']  = 'Anthropic API key, default model, per-customer daily token cap and source-word limits.';

$string['stats_total']   = 'Total translations';
$string['stats_pending'] = 'Pending / awaiting review';
$string['stats_saved']   = 'Saved (accepted)';
$string['stats_failed']  = 'Failed';

$string['filter_status'] = 'Status:';
$string['filter_lang']   = 'Target language:';
$string['filter_all']    = 'All';
$string['filter_apply']  = 'Apply';
$string['filter_reset']  = 'Reset';

$string['col_title']   = 'Title';
$string['col_lang']    = 'Target';
$string['col_status']  = 'Status';
$string['col_tokens']  = 'Tokens (in + out)';
$string['col_created'] = 'Created';
$string['col_actions'] = 'Actions';

$string['action_review'] = 'Review diff';
$string['action_open']   = 'Open';
