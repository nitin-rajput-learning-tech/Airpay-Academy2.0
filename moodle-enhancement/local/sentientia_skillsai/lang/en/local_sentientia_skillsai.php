<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English language strings for local_sentientia_skillsai.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Skills Intelligence (AI)';

// Capabilities.
$string['sentientia_skillsai:extract'] = 'Extract skills with AI';
$string['sentientia_skillsai:review'] = 'Review and approve extracted skills';
$string['sentientia_skillsai:manage_taxonomy'] = 'Curate the skills taxonomy and impact mappings';
$string['sentientia_skillsai:viewgaps'] = 'View skills-gap feeds';
$string['sentientia_skillsai:manage_all'] = 'Manage all extraction jobs across owners and tenants';

// Navigation.
$string['nav_extract'] = 'Extract skills';
$string['nav_taxonomy'] = 'Skills taxonomy';
$string['nav_gaps'] = 'Skills gaps';

// Queue / index page.
$string['queue_page_title'] = 'Skills extraction queue';
$string['queue_page_heading'] = 'Skills extraction queue';
$string['queue_empty'] = 'No extraction jobs yet. Click "Extract skills" to start.';
$string['col_title'] = 'Title';
$string['col_sourcekind'] = 'Source';
$string['col_status'] = 'Status';
$string['col_extracted'] = 'Skills';
$string['col_actions'] = 'Actions';
$string['action_review'] = 'Review';

// Source kinds.
$string['sourcekind_scorm'] = 'SCORM transcript';
$string['sourcekind_narration'] = 'Narration text';
$string['sourcekind_sop'] = 'SOP excerpt';
$string['sourcekind_manual'] = 'Pasted text';

// Job status labels.
$string['jobstatus_pending'] = 'Pending';
$string['jobstatus_extracted'] = 'Awaiting review';
$string['jobstatus_reviewed'] = 'Reviewed';
$string['jobstatus_failed'] = 'Failed';

// Extract page.
$string['extract_page_title'] = 'Extract skills with AI';
$string['extract_page_heading'] = 'Extract skills with AI';
$string['extract_intro'] = 'Paste a course/SCORM transcript, SOP excerpt or narration text. Claude proposes the skills it teaches; every proposal must pass human review before it joins the taxonomy.';
$string['extract_form_title'] = 'Extraction title';
$string['extract_form_course'] = 'Related course (optional)';
$string['extract_form_course_none'] = 'Not bound to a course';
$string['extract_form_sourcekind'] = 'Source type';
$string['extract_form_language'] = 'Language';
$string['extract_form_language_en'] = 'English';
$string['extract_form_language_hi'] = 'Hindi (हिन्दी)';
$string['extract_form_source'] = 'Source content';
$string['extract_form_source_help'] = 'Up to {$a} words. Do not paste personally identifiable information (employee names, IDs, salary, customer data).';
$string['extract_prompt_preview_summary'] = 'Preview the prompt Claude will see (version: {$a->version})';
$string['extract_prompt_preview_help'] = 'This is the exact system prompt that conditions the model. Review it before confirming.';
$string['extract_prompt_preview_custom_badge'] = 'Customer-specific prompt template in use';
$string['extract_confirm_label'] = 'I confirm this source contains no personal data and I authorise an AI extraction.';
$string['extract_confirm_help'] = 'A live API call costs money per token. Mock mode (default) is free and needs no confirmation to be safe, but the confirmation is always required.';
$string['extract_submit'] = 'Extract skills';
$string['extract_cancel'] = 'Cancel';

// Mode badges.
$string['mode_disabled_badge'] = 'Skills Intelligence is disabled (feature flag OFF).';
$string['mode_mock_badge'] = 'Mock mode — no Anthropic call will be made (live API flag OFF). Zero cost.';
$string['mode_no_apikey_badge'] = 'Live API flag is ON but no API key is configured — calls will fail until a key is set.';
$string['mode_live_badge'] = 'Live mode — extraction will POST to Anthropic and incur token cost.';

// Token cap.
$string['tokens_used_today'] = 'Tokens used today: {$a->used} of {$a->cap}.';
$string['source_word_count'] = '{$a} words';

// Review page.
$string['review_page_title'] = 'Review extracted skills';
$string['review_page_heading'] = 'Review extracted skills';
$string['review_intro'] = 'Approve, edit, or reject each proposed skill. Approving or editing promotes it into the per-tenant canonical taxonomy. Nothing becomes canonical without your verdict.';
$string['review_saved'] = 'Skill verdict saved.';
$string['review_finalised'] = 'Review finalised.';
$string['review_finalise_submit'] = 'Finalise review';
$string['review_job_failed'] = 'This extraction failed: {$a}';
$string['back_to_queue'] = 'Back to queue';

// Candidate fields + verdicts.
$string['cand_name'] = 'Skill name';
$string['cand_description'] = 'Description';
$string['cand_category'] = 'Category';
$string['cand_level'] = 'Teach-to level';
$string['cand_note'] = 'Reviewer note';
$string['cand_confidence'] = 'Confidence {$a}';
$string['cand_promoted'] = 'In taxonomy';
$string['candstatus_proposed'] = 'Proposed';
$string['candstatus_approved'] = 'Approved';
$string['candstatus_edited'] = 'Edited';
$string['candstatus_rejected'] = 'Rejected';
$string['verdict_approve'] = 'Approve';
$string['verdict_edit'] = 'Save edits';
$string['verdict_reject'] = 'Reject';

// Taxonomy page.
$string['nav_taxonomy_short'] = 'Taxonomy';
$string['taxonomy_page_title'] = 'Skills taxonomy';
$string['taxonomy_page_heading'] = 'Skills taxonomy and business impact';
$string['taxonomy_intro'] = 'The canonical, human-approved skills for this tenant. Map each skill to the business metrics it influences so skills gaps can be ranked by business priority.';
$string['taxonomy_empty'] = 'No approved skills yet. Approve extracted skills to build the taxonomy.';

// Impact mapping.
$string['impact_flag_off'] = 'Business-impact mapping is disabled (feature flag OFF). The taxonomy is read-only until it is enabled.';
$string['impact_metric'] = 'Business metric';
$string['impact_detail'] = 'How it influences the metric';
$string['impact_weight'] = 'Priority';
$string['impact_add'] = 'Add';
$string['impact_added'] = 'Business-impact mapping added.';
$string['impact_weight_badge'] = 'Priority {$a}';

// Gaps page.
$string['gaps_page_title'] = 'Skills gaps';
$string['gaps_page_heading'] = 'Skills gaps';
$string['gaps_summary_heading'] = 'Tenant skills-gap summary';
$string['gaps_summary_intro'] = 'Skills where learners fall short of their role requirements, ranked by business priority then the number of affected people.';
$string['gaps_summary_none'] = 'No open skills gaps. Rebuild a learner feed to populate this view.';
$string['gaps_user_heading'] = 'Skills gaps for {$a}';
$string['gaps_user_none'] = 'No open skills gaps for this learner — every role requirement is met.';
$string['gaps_rebuild_user'] = 'Rebuild this learner\'s gap feed';
$string['gaps_rebuilt'] = 'Gap feed rebuilt: {$a} open gaps.';
$string['gaps_back_summary'] = 'Back to summary';
$string['col_skill'] = 'Skill';
$string['col_required'] = 'Required';
$string['col_held'] = 'Held';
$string['col_gap'] = 'Gap';
$string['col_impact'] = 'Impact';
$string['col_affected'] = 'Affected learners';
$string['col_maxgap'] = 'Largest gap';

// Settings.
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Configure the Anthropic API key + model. The key is only used when the live-API feature flag is ON; in mock mode no key is needed.';
$string['setting_api_key'] = 'API key';
$string['setting_api_key_desc'] = 'Your Anthropic API key. Stored masked. Never committed to source control. Leave blank to stay in mock mode.';
$string['setting_default_model'] = 'Default model';
$string['setting_default_model_desc'] = 'Anthropic model identifier used for extraction (e.g. claude-sonnet-4-6).';
$string['settings_heading_limits'] = 'Limits';
$string['settings_heading_limits_desc'] = 'Per-request and per-day guardrails that protect against runaway cost.';
$string['setting_max_skills'] = 'Maximum skills per extraction';
$string['setting_max_skills_desc'] = 'The most candidate skills a single extraction will request.';
$string['setting_daily_token_cap'] = 'Daily token cap (per user)';
$string['setting_daily_token_cap_desc'] = 'When an author has consumed this many tokens today, further extractions are blocked until tomorrow.';
$string['setting_max_source_words'] = 'Maximum source words';
$string['setting_max_source_words_desc'] = 'Longer source text is rejected at paste time.';
$string['settings_heading_customer_prompts'] = 'Per-customer prompt template';
$string['settings_heading_customer_prompts_desc'] = 'An optional override for the system prompt body, applied to this customer. Leave blank to use the built-in baseline prompt.';
$string['setting_customer_1_prompt_template'] = 'Airpay extraction prompt template';
$string['setting_customer_1_prompt_template_desc'] = 'When set, this text replaces the built-in system prompt for Airpay extractions. The user-message wrapper still follows the chosen language.';

// Scheduled task.
$string['task_rebuild_gap_feed'] = 'Rebuild skills-gap feeds';

// Errors.
$string['err_feature_off'] = 'Skills Intelligence is not enabled on this site.';
$string['err_gap_feature_off'] = 'The skills-gap engine is not enabled on this site.';
$string['err_source_empty'] = 'Source content cannot be empty.';
$string['err_source_too_long'] = 'Source content exceeds the maximum word limit.';
$string['err_source_contains_pii'] = 'Source content appears to contain personally identifiable information (e.g. an Aadhaar or PAN number). Remove it before extracting.';
$string['err_confirm_required'] = 'You must tick the confirmation box before extracting.';
$string['err_token_cap_reached'] = 'Daily token cap reached ({$a->used} of {$a->cap}). Try again tomorrow.';
$string['err_api_failed'] = 'The extraction call failed: {$a}';
$string['err_job_not_found'] = 'That extraction job does not exist or you do not have access to it.';
$string['err_candidate_not_found'] = 'That candidate skill does not belong to this extraction job.';
$string['err_candidate_not_approved'] = 'Only approved or edited candidates can be promoted into the taxonomy.';

// Privacy.
$string['privacy:metadata:job'] = 'Skill-extraction jobs requested and reviewed by users.';
$string['privacy:metadata:job:ownerid'] = 'The user who requested the extraction.';
$string['privacy:metadata:job:reviewed_by'] = 'The user who reviewed the extraction.';
$string['privacy:metadata:job:title'] = 'The title of the extraction job.';
$string['privacy:metadata:job:status'] = 'The status of the extraction job.';
$string['privacy:metadata:job:timecreated'] = 'When the extraction was created.';
$string['privacy:metadata:gap'] = 'Per-user skills-gap feed rows.';
$string['privacy:metadata:gap:userid'] = 'The user whose skills gap this is.';
$string['privacy:metadata:gap:skillid'] = 'The skill the gap is on.';
$string['privacy:metadata:gap:required_level'] = 'The level the role requires.';
$string['privacy:metadata:gap:held_level'] = 'The level the user currently holds.';
$string['privacy:metadata:gap:timecreated'] = 'When the gap row was computed.';
$string['privacy:metadata:anthropic'] = 'Source learning text sent to the Anthropic Claude API for skill extraction (only when the live-API flag is ON).';
$string['privacy:metadata:anthropic:sourcetext'] = 'The learning material text submitted for extraction (screened for personal data at paste time).';
$string['privacy:metadata:anthropic:model'] = 'The Anthropic model used.';
$string['privacy:export:jobs'] = 'Extraction jobs';
$string['privacy:export:gaps'] = 'Skills gaps';
