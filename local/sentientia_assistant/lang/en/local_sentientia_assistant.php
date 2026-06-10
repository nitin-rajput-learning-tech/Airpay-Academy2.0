<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia AI Learning Assistant';
$string['enabled'] = 'Enable AI Assistant';
$string['enabled_desc'] = 'Show the AI chatbot bubble on all pages. Uncheck to hide the chatbot site-wide.';
$string['privacy:metadata'] = 'The AI assistant stores chat logs linked to user IDs.';
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
