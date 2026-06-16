<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay AI Learning Assistant';
$string['enabled'] = 'Enable AI Assistant';
$string['enabled_desc'] = 'Show the AI chatbot bubble on all pages. Uncheck to hide the chatbot site-wide.';
$string['privacy:metadata'] = 'The AI assistant stores chat logs linked to user IDs.';
$string['privacy:metadata:chat_log'] = 'Conversation log between the learner and the AI assistant.';
$string['privacy:metadata:chat_log:userid'] = 'The user who sent or received the message.';
$string['privacy:metadata:chat_log:role'] = 'Whether the message was from the user or the assistant.';
$string['privacy:metadata:chat_log:message'] = 'The message text.';
$string['privacy:metadata:chat_log:model'] = 'The AI model that produced the response.';
$string['privacy:metadata:chat_log:tokens_in'] = 'Input tokens consumed by the message.';
$string['privacy:metadata:chat_log:tokens_out'] = 'Output tokens produced for the message.';
$string['privacy:metadata:chat_log:timecreated'] = 'When the message was recorded.';
$string['privacy:metadata:agent_audit'] = 'Audit of every action the agentic copilot proposed on the user\'s behalf, with the authorisation outcome.';
$string['privacy:metadata:agent_audit:userid'] = 'The user whose learning the proposed action would affect.';
$string['privacy:metadata:agent_audit:costcenterid'] = 'The tenant the action was scoped to.';
$string['privacy:metadata:agent_audit:tool'] = 'The tool the assistant proposed to run.';
$string['privacy:metadata:agent_audit:args_json'] = 'The proposed arguments for the action.';
$string['privacy:metadata:agent_audit:proposed_by'] = 'Whether the action was proposed by the live model or a mock.';
$string['privacy:metadata:agent_audit:outcome'] = 'The authorisation and execution outcome.';
$string['privacy:metadata:agent_audit:detail'] = 'Human-readable detail of the outcome.';
$string['privacy:metadata:agent_audit:idempotency_key'] = 'A hash used to prevent duplicate execution of the same action.';
$string['privacy:metadata:agent_audit:timecreated'] = 'When the action was recorded.';
$string['privacy:metadata:anthropic'] = 'When the live AI is enabled, chat messages are sent to Anthropic Claude to generate a response.';
$string['privacy:metadata:anthropic:message'] = 'The chat message text sent for a response.';
$string['privacy:metadata:anthropic:model'] = 'The Claude model requested.';
$string['privacy:export:chat'] = 'AI assistant conversations';
$string['privacy:export:audit'] = 'AI copilot action history';
$string['apikey'] = 'Anthropic API Key';
$string['apikey_desc'] = 'Your Claude API key from console.anthropic.com. Required for the AI assistant to function. Costs apply per query.';
$string['ratelimit'] = 'Daily query limit per user';
$string['ratelimit_desc'] = 'Maximum number of AI queries a user can make per day. Default: 20.';
$string['assistant'] = 'Learning Assistant';
$string['askme'] = 'Ask me anything...';
$string['poweredby'] = 'Powered by AI';
$string['ratelimited'] = 'Daily limit reached. Come back tomorrow!';
$string['notconfigured'] = 'AI assistant not configured. Contact your administrator.';
$string['queriesremaining'] = '{$a} questions remaining today';

// Phase B0 (2026-05-14) — a11y labels for the chat bubble.
$string['toggle_assistant'] = 'Open AI learning assistant';
$string['close_assistant']  = 'Close AI learning assistant';
$string['minimize_assistant'] = 'Minimise assistant panel';
$string['send_message']     = 'Send message';
$string['type_question']    = 'Type your question';
$string['quick_questions']  = 'Quick questions';

// Role-aware quick-action chips (2026-06-01) — see hook_callbacks::quick_actions().
$string['qa_learn']       = 'What to learn next?';
$string['qa_learn_q']     = 'What should I learn next?';
$string['qa_deadlines']   = 'My deadlines';
$string['qa_deadlines_q'] = 'What are my deadlines?';
$string['qa_quiz']        = 'Quiz me';
$string['qa_quiz_q']      = 'Quiz me on my courses';
$string['qa_team']        = 'Team status';
$string['qa_team_q']      = 'How is my team doing?';
$string['qa_certs']       = 'My certificates';
$string['qa_certs_q']     = 'Show my certificates';

// P1.3 — Agentic Copilot (2026-06-16).
// Capabilities.
$string['sentientia_assistant:useagent']  = 'Use the agentic learning copilot';
$string['sentientia_assistant:enrol']     = 'Let the copilot self-enrol you into a course';
$string['sentientia_assistant:bookilt']   = 'Let the copilot book you onto an ILT session';
$string['sentientia_assistant:recommend'] = 'Let the copilot recommend gap-closing content';
$string['sentientia_assistant:manageall'] = 'View the agentic copilot audit log across the tenant';

// Page + panel.
$string['agent_title']             = 'Learning Copilot';
$string['agent_intro']             = 'Ask me to enrol you in a course, book an ILT session, or recommend content to close your skill gaps. I will propose an action and you confirm it.';
$string['agent_input_label']       = 'Message the learning copilot';
$string['agent_input_placeholder'] = 'e.g. Recommend courses to improve my skills';
$string['agent_disabled_notice']   = 'The agentic copilot is not enabled for your account yet. Please check back later.';
$string['agent_mode_mock']         = 'Mock mode';
$string['agent_mode_live']         = 'Live mode';
$string['agent_confirm_btn']       = 'Confirm';
$string['agent_cancel_btn']        = 'Cancel';

// Agent loop / guard outcomes (learner-facing).
$string['agent_help']              = 'I can help you enrol in courses, book ILT sessions, or recommend content. What would you like to do?';
$string['agent_unavailable']       = 'The copilot is temporarily unavailable. Please try again in a moment.';
$string['agent_denied_invalid']    = 'I could not act on that request — the details did not check out.';
$string['agent_denied_capability'] = 'You do not have permission to perform that action.';
$string['agent_denied_tenant']     = 'That action is outside your organisation and cannot be performed.';
$string['agent_noop']              = 'You are already set up for that — nothing to do.';
$string['agent_failed']            = 'Something went wrong performing that action. Please try again later.';

// Tools.
$string['tool_enrol_course']    = 'Enrol you into a course';
$string['tool_enrol_done']      = 'Done — you are now enrolled in {$a}.';
$string['tool_book_ilt']        = 'Book you onto an ILT session';
$string['tool_book_done']       = 'Done — you are booked onto the ILT session.';
$string['tool_book_full']       = 'That ILT session is full, so I could not book you.';
$string['tool_recommend']       = 'Recommend gap-closing content';
$string['tool_recommend_intro'] = 'Here are some courses that could help close your skill gaps:';
$string['tool_recommend_none']  = 'I could not find new courses to recommend right now — you are enrolled in everything relevant.';
