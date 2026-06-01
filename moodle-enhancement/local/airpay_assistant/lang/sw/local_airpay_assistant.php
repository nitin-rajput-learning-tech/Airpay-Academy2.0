<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Msaidizi wa Kujifunza wa AI wa Airpay';
$string['enabled'] = 'Washa Msaidizi wa AI';
$string['enabled_desc'] = 'Onyesha kiputo cha chatbot ya AI kwenye kurasa zote. Ondoa tiki kuficha chatbot kwenye tovuti nzima.';
$string['privacy:metadata'] = 'Msaidizi wa AI huhifadhi kumbukumbu za mazungumzo zilizounganishwa na vitambulisho vya mtumiaji.';
$string['apikey'] = 'Ufunguo wa API wa Anthropic';
$string['apikey_desc'] = 'Ufunguo wako wa Claude API kutoka console.anthropic.com. Inahitajika ili msaidizi wa AI afanye kazi.';
$string['ratelimit'] = 'Kikomo cha maswali ya kila siku kwa mtumiaji';
$string['ratelimit_desc'] = 'Idadi ya juu ya maswali ya AI ambayo mtumiaji anaweza kuuliza kwa siku. Chaguo-msingi: 20.';
$string['assistant'] = 'Msaidizi wa Kujifunza';
$string['askme'] = 'Niulize chochote...';
$string['poweredby'] = 'Imeendeshwa na AI';
$string['ratelimited'] = 'Kikomo cha kila siku kimefikiwa. Rudi kesho!';
$string['notconfigured'] = 'Msaidizi wa AI haijasanidiwa. Wasiliana na msimamizi wako.';
$string['queriesremaining'] = 'Maswali {$a} yamebaki leo';

// Phase B0 (2026-05-14) — Lebo za a11y kwa kiputo cha mazungumzo.
$string['toggle_assistant']  = 'Fungua Msaidizi wa Kujifunza wa AI';
$string['close_assistant']   = 'Funga Msaidizi wa Kujifunza wa AI';
$string['minimize_assistant'] = 'Punguza paneli ya msaidizi';
$string['send_message']      = 'Tuma ujumbe';
$string['type_question']     = 'Andika swali lako';
$string['quick_questions']   = 'Maswali ya haraka';

// Role-aware quick-action chips (2026-06-01) — keys added for parity; refine locally.
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
