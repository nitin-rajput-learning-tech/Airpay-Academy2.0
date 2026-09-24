<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Privacy (DPDP)';
$string['privacy:metadata'] = 'Stores privacy request records and consent logs.';
$string['messageprovider:privacy_request'] = 'DPDP privacy request notifications';
$string['myprivacy'] = 'My Privacy & Data';
$string['downloadrequested'] = 'Your data export is being prepared. You will be notified when it is ready for download.';
$string['deleterequested'] = 'Your account deletion request has been submitted. An administrator will review it within 3-5 business days.';
$string['downloaddata'] = 'Download My Data';
$string['deleteaccount'] = 'Delete My Account';
$string['requesthistory'] = 'Request History';
$string['dpdpnotice'] = 'DPDP Act 2023 Notice';

// Privacy provider (2026-08-04) — real metadata + export provider.
// Requests + consent log are retained on erasure (records of processing).
$string['privacy:metadata:privacy_requests']               = 'Data subject rights (DSR) requests lodged by or for a user';
$string['privacy:metadata:privacy_requests:userid']        = 'The user the request is about';
$string['privacy:metadata:privacy_requests:request_type']  = 'The type of request (export or deletion)';
$string['privacy:metadata:privacy_requests:status']        = 'The processing status of the request';
$string['privacy:metadata:privacy_requests:reason']        = 'The reason the user gave for the request';
$string['privacy:metadata:privacy_requests:admin_notes']   = 'Notes an administrator recorded on the request';
$string['privacy:metadata:privacy_requests:processed_by']  = 'The administrator who processed the request';
$string['privacy:metadata:privacy_requests:download_url']  = 'The (expiring) download link for a data export';
$string['privacy:metadata:privacy_requests:timecreated']   = 'When the request was lodged';
$string['privacy:metadata:privacy_requests:timeprocessed'] = 'When the request was processed';
$string['privacy:metadata:consent_log']                    = 'Log of consent grants and withdrawals, kept as proof of consent';
$string['privacy:metadata:consent_log:userid']             = 'The user who gave or withdrew consent';
$string['privacy:metadata:consent_log:consent_type']       = 'What the consent covers';
$string['privacy:metadata:consent_log:consented']          = 'Whether consent was given (1) or withdrawn (0)';
$string['privacy:metadata:consent_log:ip_address']         = 'The IP address the consent event came from';
$string['privacy:metadata:consent_log:user_agent']         = 'The browser user agent of the consent event';
$string['privacy:metadata:consent_log:timecreated']        = 'When the consent event happened';
