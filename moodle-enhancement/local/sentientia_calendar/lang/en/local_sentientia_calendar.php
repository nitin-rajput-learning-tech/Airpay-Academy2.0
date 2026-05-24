<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings for local_sentientia_calendar.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Calendar Sync';

// Navigation.
$string['nav_label'] = 'Calendar subscription';

// User-facing subscription page.
$string['page_title']     = 'Calendar subscription';
$string['page_heading']   = 'Subscribe to your learning calendar';
$string['page_intro']     = 'Add your Sentientia LMS course deadlines, classroom sessions, and exam dates to Outlook, Google Calendar, or Apple Calendar. The link below is personal to you — keep it private.';
$string['events_heading'] = 'What\'s included';
$string['events_courses']   = 'Course completion deadlines for every course you\'re enrolled in';
$string['events_classroom'] = 'Classroom (instructor-led training) session start and end times';
$string['events_exams']     = 'Exam close-dates for the next 90 days';

// Subscription URL widget.
$string['copy_label']        = 'Copy subscription URL';
$string['copied_label']      = 'Copied!';
$string['security_note']     = 'Treat this URL like a password — anyone who has it can read your learning calendar. Use "Regenerate" below if you ever paste it somewhere by accident.';

// Regenerate.
$string['regenerate_label']   = 'Regenerate URL';
$string['regenerate_help']    = 'Invalidates the current URL and issues a fresh one. You\'ll need to update the subscription in every calendar client you\'ve already added.';
$string['regenerate_success'] = 'Subscription URL regenerated. The old link no longer works.';

// How-to.
$string['how_to_heading'] = 'How to subscribe';
$string['how_to_outlook'] = 'Outlook on the web: Calendar ▶ Add calendar ▶ Subscribe from web ▶ paste the URL ▶ name it "Sentientia" ▶ Import. Desktop Outlook: File ▶ Account Settings ▶ Internet Calendars ▶ New ▶ paste the URL.';
$string['how_to_google']  = 'Google Calendar: Other calendars ▶ + ▶ From URL ▶ paste the URL ▶ Add calendar.';
$string['how_to_apple']   = 'Apple Calendar (macOS): File ▶ New Calendar Subscription ▶ paste the URL ▶ Subscribe. iOS: Settings ▶ Calendar ▶ Accounts ▶ Add Account ▶ Other ▶ Add Subscribed Calendar.';

// Errors.
$string['error_flag_off']        = 'Calendar sync is not currently enabled for your account. Contact your administrator.';
$string['error_token_collision'] = 'Could not generate a unique calendar token after multiple attempts. Please try again.';

// Scheduled tasks.
$string['task_purge_old_tokens'] = 'Sentientia Calendar — purge revoked tokens';

// Capabilities.
$string['sentientia_calendar:subscribe']  = 'Manage own calendar subscription URL';
$string['sentientia_calendar:manage_all'] = 'Manage any user\'s calendar subscription tokens';

// Privacy.
$string['privacy:metadata'] = 'Sentientia LMS Calendar Sync stores one secret subscription token per user. Calendar clients fetch the user\'s personal feed using this token. No course content or third-party data is stored — only the token and audit metadata (last-used time, IP, count).';
$string['privacy:metadata:token']                = 'The personal calendar subscription token issued to each user.';
$string['privacy:metadata:token:userid']         = 'The user the token belongs to.';
$string['privacy:metadata:token:token']          = 'The 64-character random token (functionally a credential).';
$string['privacy:metadata:token:last_used_at']   = 'When the token was last used to fetch the calendar feed.';
$string['privacy:metadata:token:last_used_ip']   = 'IP address of the last successful fetch (for abuse forensics).';
$string['privacy:metadata:token:use_count']      = 'Total successful fetch count.';
$string['privacy:metadata:token:timecreated']    = 'When the token was first issued.';
$string['privacy:metadata:token:timemodified']   = 'When the token was last modified (regenerated or revoked).';
