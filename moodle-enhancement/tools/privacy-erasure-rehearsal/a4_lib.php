<?php
// A4 rehearsal: shared helpers for a4_rehearsal.php and a4_verify.php.
//
// Loaded AFTER C:/xampp/htdocs/moodle5/config.php. Local XAMPP copy only.
// Prints and records ids and counts only: never names, e-mails or other personal data.
//
// Nothing in this file writes to the database except a4_set_readonly_session(), which changes the
// session's transaction mode (no data) and runs one zero-row self-test statement that the read-only
// mode must refuse (see the function). a4_manifest_write() writes a local JSON file under state/.

defined('MOODLE_INTERNAL') || die();

/** Events whose appearance in the standard log after the run started means a send or a user deletion happened. */
const A4_SEND_EVENTS = [
    '\\core\\event\\notification_sent',
    '\\core\\event\\message_sent',
    '\\core\\event\\user_deleted',
    '\\core\\event\\email_failed',
];

/** Progress lines printed by \core_privacy\manager through text_progress_trace (privacy/lang/en: trace:*). */
const A4_TRACE_RE = '/^\s*(Processing .+\(\d+\/\d+\)|Pre-processing .+\(\d+\/\d+\)|Fetching data from \d+ components|'
    . 'Performing removal of|Performing primary export|Performing related export|Complete\s*$)/';

/**
 * Captured output that means a send was attempted, or that a real event observer ran. With $CFG->debug forced to
 * DEBUG_DEVELOPER and debugdisplay on (a4_apply_kill_switches), email_to_user() prints "Not sending email due to
 * $CFG->noemailever" (lib/moodlelib.php:5627) and message_send() prints "Attempt to send msg" for an unknown provider.
 * A message_send() for a known provider returns silently at the disable check (lib/messagelib.php:71-74): that
 * attempt leaves no trace (see README, blind spots).
 */
const A4_SEND_ATTEMPT_RE = '/Not sending email due to|Attempt to send msg|Exception encountered in event observer|'
    . 'Can not execute event observer/';

/**
 * PHP functions every A4 script requires to be disabled on the php command line: raw HTTP (libcurl), sockets,
 * PHP mail and subprocesses. Moodle's \curl and \core\http_client refuse the URL in curl_security_helper before they
 * reach curl_exec(), so blocking curl_exec() only stops raw curl_init() callers.
 */
const A4_PHP_DISABLED = [
    'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect',
    'mail', 'mb_send_mail', 'imap_mail', 'proc_open', 'popen', 'exec', 'shell_exec', 'system', 'passthru',
];

/** The php flags that every A4 command must carry (README "How to run"). */
const A4_PHP_FLAGS = '-d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,'
    . 'socket_connect,mail,mb_send_mail,imap_mail,proc_open,popen,exec,shell_exec,system,passthru '
    . '-d allow_url_fopen=0 -d SMTP=127.0.0.1 -d smtp_port=9';

/**
 * Replaces \tool_dataprivacy\manager_observer, which calls message_send() once per DPO for every provider failure
 * (admin/tool/dataprivacy/classes/manager_observer.php:45-74). This one only records the failure in memory.
 * The try/catch, debugging() and "continue with the next component" behaviour stay in \core_privacy\manager
 * (privacy/classes/manager.php:609-634) and are unchanged.
 */
class a4_recording_observer implements \core_privacy\manager_observer {
    /** @var string discovery | delete */
    public $phase = '';
    /** @var int */
    public $requestid = 0;
    /** @var array */
    public $failures = [];

    public function handle_component_failure($e, $component, $interface, $methodname, array $params) {
        $this->failures[] = [
            'requestid' => $this->requestid,
            'phase' => $this->phase,
            'component' => (string)$component,
            'method' => (string)$methodname,
            'class' => is_object($e) ? get_class($e) : gettype($e),
            'errorcode' => ($e instanceof \moodle_exception) ? $e->errorcode : null,
            'message' => is_object($e) ? a4_trunc($e->getMessage(), 300) : '',
            'at' => is_object($e) ? a4_relpath($e->getFile()) . ':' . $e->getLine() : '',
        ];
    }

    /** Return and clear the recorded failures. */
    public function take(): array {
        $f = $this->failures;
        $this->failures = [];
        return $f;
    }
}

/**
 * The only event observer while an A4 script runs. a4_event_recorder::install() swaps the dispatcher's observer table
 * (\core\event\manager::$allobservers, lib/classes/event/manager.php:47) for one internal observer on
 * \core\event\base. No real observer (tool_log, tool_monitor, webhooks, e-mail rules, WhatsApp, ...) can run, and
 * because the recorder is internal it runs at once, also inside a transaction (manager.php:129-144), so the
 * pre-commit check sees every event. This path should fire none: any recorded event is a tripwire.
 */
class a4_event_recorder {
    /** @var array */
    public static $events = [];

    public static function record(\core\event\base $event): void {
        self::$events[] = ['eventname' => (string)$event->eventname, 'contextid' => (int)$event->contextid,
            'crud' => (string)$event->crud];
    }

    public static function take(): array {
        $e = self::$events;
        self::$events = [];
        return $e;
    }

    private static function prop(): \ReflectionProperty {
        $p = new \ReflectionProperty(\core\event\manager::class, 'allobservers');
        $p->setAccessible(true);
        return $p;
    }

    private static function observer(): \stdClass {
        $o = new \stdClass();
        $o->callable = 'a4_event_recorder::record';
        $o->priority = 0;
        $o->internal = true;
        $o->includefile = null;
        $o->plugintype = 'a4';
        $o->plugin = 'rehearsal';
        return $o;
    }

    /** True while the observer table is exactly the recorder (a cache reset would reload the real observers). */
    public static function intact(): bool {
        $v = self::prop()->getValue();
        return is_array($v) && array_keys($v) === ['\\core\\event\\base'] && count($v['\\core\\event\\base']) === 1
            && (($v['\\core\\event\\base'][0]->callable ?? '') === 'a4_event_recorder::record');
    }

    /** Swap in the recorder, prove it with one self-test event, return facts. Aborts on any failure. */
    public static function install(): array {
        $all = \core\event\manager::get_all_observers();
        $n = 0;
        $ext = 0;
        foreach ($all as $list) {
            foreach ($list as $o) {
                $n++;
                if (empty($o->internal)) {
                    $ext++;
                }
            }
        }
        self::prop()->setValue(null, ['\\core\\event\\base' => [self::observer()]]);
        if (!self::intact()) {
            a4_abort('event recorder: the observer table could not be replaced');
        }
        // Self-test: one read-only event (course_viewed on the front page). Only the recorder can receive it.
        self::$events = [];
        \core\event\course_viewed::create(['context' => \context_course::instance(SITEID)])->trigger();
        $got = self::take();
        if (count($got) !== 1 || $got[0]['eventname'] !== '\\core\\event\\course_viewed' || !self::intact()) {
            a4_abort('event recorder self-test failed (' . count($got) . ' event(s) recorded)');
        }
        return ['observers_replaced' => $n, 'external_observers_replaced' => $ext, 'event_classes' => count($all),
            'recorder' => 'internal observer on \\core\\event\\base; every event is recorded and trips',
            'selftest' => 'course_viewed recorded once, no real observer ran'];
    }
}

/** Raised by the rehearsal when a tripwire fires. */
class a4_tripwire_exception extends \Exception {
    /** @var array */
    public $trips;

    public function __construct(array $trips) {
        $this->trips = $trips;
        parent::__construct('TRIPWIRE: ' . implode('; ', $trips));
    }
}

/** Raised in --txn=request when a provider failed (discovery or delete): the whole request is rolled back. */
class a4_provider_failure extends \Exception {
    /** @var array */
    public $failures;

    public function __construct(array $failures) {
        $this->failures = $failures;
        parent::__construct('provider failure(s): ' . count($failures));
    }
}

/** Thrown to leave a request untouched (outcome already set by the caller). */
class a4_skip extends \Exception {
}

/** Append-only JSON-lines writer. */
class a4_jsonl {
    /** @var resource */
    private $fh;
    /** @var string */
    public $path;

    public function __construct(string $path) {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            a4_abort("cannot create folder for $path");
        }
        $this->fh = fopen($path, 'ab');
        if (!$this->fh) {
            a4_abort("cannot open $path for writing");
        }
        $this->path = $path;
    }

    public function write(array $rec): void {
        $rec = ['ts' => gmdate('Y-m-d\TH:i:s\Z')] + $rec;
        fwrite($this->fh, json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
        fflush($this->fh);
    }
}

function a4_abort(string $msg, int $code = 2): void {
    fwrite(STDERR, "A4 ABORT: $msg\n");
    exit($code);
}

function a4_out(string $line): void {
    fwrite(STDOUT, $line . "\n");
}

function a4_trunc(string $s, int $n): string {
    $s = preg_replace('/\s+/', ' ', $s);
    return (strlen($s) > $n) ? substr($s, 0, $n) . '...' : $s;
}

function a4_relpath(string $file): string {
    global $CFG;
    $f = str_replace('\\', '/', $file);
    $root = str_replace('\\', '/', $CFG->dirroot);
    return (strpos($f, $root) === 0) ? substr($f, strlen($root) + 1) : basename($f);
}

/** True for an absolute Windows (C:/..., C:\...) or POSIX path. */
function a4_is_absolute(string $p): bool {
    return (bool)preg_match('#^([A-Za-z]:[\\\\/]|/)#', $p);
}

/**
 * Every A4 script must run with raw HTTP, sockets, PHP mail and subprocesses disabled on the php command line
 * (A4_PHP_FLAGS). This is checked, not trusted: abort unless each function is gone, allow_url_fopen is off and the
 * PHP mail SMTP target is the dead local port (covers mail()-based paths disable_functions cannot reach, such as
 * error_log() type 1). Called right after config.php (this file needs MOODLE_INTERNAL), before any other work: the
 * Moodle bootstrap itself sends nothing.
 */
function a4_require_hardened_php(): array {
    $still = [];
    foreach (A4_PHP_DISABLED as $f) {
        if (function_exists($f)) {
            $still[] = $f;
        }
    }
    $urlfopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    $smtp = (string)ini_get('SMTP');
    $port = (string)ini_get('smtp_port');
    if ($still || $urlfopen || $smtp !== '127.0.0.1' || $port !== '9') {
        fwrite(STDERR, "A4 ABORT: php must run with the A4 network/mail guard flags. Still available: "
            . ($still ? implode(',', $still) : 'none') . '; allow_url_fopen=' . ($urlfopen ? 'on' : 'off')
            . "; SMTP=$smtp:$port.\nRun: php " . A4_PHP_FLAGS . " <script> [options]\n");
        exit(2);
    }
    return ['disabled_functions' => count(A4_PHP_DISABLED), 'allow_url_fopen' => 'off', 'smtp' => '127.0.0.1:9 (dead)'];
}

/**
 * Refuse to run anywhere but the local XAMPP copy, and only with e-mail hard-off.
 * Returns non-secret facts for the report.
 */
function a4_guard_environment(): array {
    global $CFG;
    $host = strtolower((string)parse_url($CFG->wwwroot, PHP_URL_HOST));
    if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
        a4_abort('wwwroot is not localhost: this script runs on the local XAMPP copy only');
    }
    if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', (string)$CFG->dbhost)) {
        a4_abort('dbhost is not local');
    }
    if ($CFG->prefix !== 'mdl_') {
        a4_abort('prefix is not mdl_ (the phpu_ PHPUnit prefix must never be touched)');
    }
    if ($CFG->dbname !== 'moodle') {
        a4_abort('dbname is not moodle');
    }
    if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
        a4_abort('PHPUNIT_TEST is set');
    }
    if (empty($CFG->noemailever)) {
        a4_abort('$CFG->noemailever is not true');
    }
    return [
        'wwwroot_host' => $host,
        'dbtype' => $CFG->dbtype,
        'prefix' => $CFG->prefix,
        'moodle_release' => $CFG->release ?? '',
        'noemailever' => true,
        'noemailever_in_config_php' => !empty($CFG->config_php_settings['noemailever']),
        'php' => PHP_VERSION,
    ];
}

/**
 * Dry-run guard: switch this DB session to READ ONLY, prove it with a zero-row self-test, return facts.
 *
 * The self-test statement is "UPDATE {tool_dataprivacy_request} SET status = status WHERE id < 0". It matches no row
 * (ids are positive) and assigns a column to itself, so it cannot change data even if the guard were off. With the
 * guard on, MariaDB must refuse it (error 1792, "read only transaction"). If it is NOT refused, the dry run aborts.
 */
function a4_set_readonly_session(bool $selftest = true): array {
    global $DB;
    $DB->execute('SET SESSION TRANSACTION READ ONLY');
    $ro = a4_readonly_state();
    if ($ro !== 1) {
        a4_abort('READ ONLY session guard not active (tx_read_only=' . var_export($ro, true) . ')');
    }
    $facts = ['tx_read_only' => $ro, 'selftest' => 'skipped'];
    if ($selftest) {
        try {
            $DB->execute('UPDATE {tool_dataprivacy_request} SET status = status WHERE id < 0');
            a4_abort('READ ONLY self-test: the zero-row UPDATE was accepted; refusing to continue');
        } catch (\dml_exception $e) {
            $msg = $e->getMessage() . ' ' . ($e->debuginfo ?? '');
            if (stripos($msg, 'READ ONLY') === false && strpos($msg, '1792') === false) {
                a4_abort('READ ONLY self-test failed for an unexpected reason: ' . a4_trunc($e->getMessage(), 200));
            }
            $facts['selftest'] = 'refused as expected (read only transaction)';
        }
    }
    return $facts;
}

/** 1 when the session is read-only, 0 when not, -1 when the variable cannot be read. */
function a4_readonly_state(): int {
    global $DB;
    try {
        return (int)$DB->get_field_sql('SELECT @@SESSION.tx_read_only');
    } catch (\Throwable $e) {
        try {
            return (int)$DB->get_field_sql('SELECT @@SESSION.transaction_read_only');
        } catch (\Throwable $e2) {
            return -1;
        }
    }
}

/**
 * In-process kill switches (this PHP process only; nothing is written to config tables). They do not reach other
 * processes on the site (web requests, another CLI): see README "Concurrency".
 * Returns facts for the report. Aborts if a self-checked switch does not take effect.
 */
function a4_apply_kill_switches(): array {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/message/lib.php');
    $facts = [];

    // 1. E-mail. Already true from config.php:44 (checked by a4_guard_environment). Read at call time by
    // email_to_user() (lib/moodlelib.php:5627, before PHPMailer is built). PHP mail() is disabled on the command line.
    $CFG->noemailever = true;
    $facts['email'] = 'noemailever=true (config.php and in-process); PHP mail disabled and SMTP dead (php flags)';

    // 2. Moodle notifications / messages. message_send() returns before writing any row when
    // get_config('message')->{component_name}_disable is set (lib/messagelib.php:71-74).
    // get_config() merges $CFG->forced_plugin_settings at call time (lib/moodlelib.php:1091-1099).
    if (!isset($CFG->forced_plugin_settings) || !is_array($CFG->forced_plugin_settings)) {
        $CFG->forced_plugin_settings = [];
    }
    foreach (['message', 'tool_monitor', 'logstore_standard', 'logstore_database', 'local_sentientia_pwa',
            'local_sentientia_whatsapp'] as $plugin) {
        if (!isset($CFG->forced_plugin_settings[$plugin]) || !is_array($CFG->forced_plugin_settings[$plugin])) {
            $CFG->forced_plugin_settings[$plugin] = [];
        }
    }
    $providers = $DB->get_records('message_providers', null, 'id', 'id, component, name');
    foreach ($providers as $p) {
        $CFG->forced_plugin_settings['message'][$p->component . '_' . $p->name . '_disable'] = 1;
    }
    $prefs = get_message_output_default_preferences();
    $notdisabled = [];
    foreach ($providers as $p) {
        $key = $p->component . '_' . $p->name . '_disable';
        if (empty($prefs->{$key})) {
            $notdisabled[] = $p->component . '/' . $p->name;
        }
    }
    if ($notdisabled) {
        a4_abort('message kill switch did not take effect for: ' . implode(', ', $notdisabled));
    }
    $facts['message_providers_disabled'] = count($providers);
    $facts['message_selfcheck'] = [
        'tool_dataprivacy/notifyexceptions' => !empty($prefs->tool_dataprivacy_notifyexceptions_disable),
        'tool_dataprivacy/datarequestprocessingresults' => !empty($prefs->tool_dataprivacy_datarequestprocessingresults_disable),
        'moodle/failedtaskmaxdelay' => !empty($prefs->moodle_failedtaskmaxdelay_disable),
    ];

    // 3. Mobile push (airnotifier). The access key and URL are CORE settings ($CFG->airnotifieraccesskey,
    // message/output/airnotifier/settings.php:48; read at classes/manager.php:198 and message_output_airnotifier.php:129),
    // not plugin settings. Blank them in-process and prove the manager sees the system as unconfigured.
    $CFG->airnotifieraccesskey = '';
    $CFG->airnotifierurl = '';
    if ((new \message_airnotifier_manager())->is_system_configured()) {
        a4_abort('airnotifier kill switch did not take effect');
    }
    // PWA web push and WhatsApp/SMS keys (plugin settings, names checked in vapid_key_manager.php:36-41,
    // whatsapp_client.php:132, sms_client.php:91).
    $CFG->forced_plugin_settings['local_sentientia_pwa']['vapid_public_b64url'] = '';
    $CFG->forced_plugin_settings['local_sentientia_pwa']['vapid_private_b64url'] = '';
    $CFG->forced_plugin_settings['local_sentientia_pwa']['vapid_private_pem'] = '';
    $CFG->forced_plugin_settings['local_sentientia_whatsapp']['karix_api_key'] = '';
    $CFG->forced_plugin_settings['local_sentientia_whatsapp']['msg91_api_key'] = '';
    $facts['channel_keys_forced_empty'] = ['airnotifier url + access key (core settings, self-checked)',
        'PWA VAPID keys', 'WhatsApp/SMS API keys'];

    // 4. Event monitor (tool_monitor): off in-process. (No observer can run anyway: a4_event_recorder.)
    $CFG->forced_plugin_settings['tool_monitor']['enablemonitor'] = 0;

    // 5. Log stores. logstore_standard writes each event at once instead of every 50 (buffered_writer.php:87-92),
    // so a check after a request sees every row. logstore_database never opens its external connection
    // (store.php:75-79 returns early on an empty dbdriver; its privacy provider calls get_extdb()).
    $CFG->forced_plugin_settings['logstore_standard']['buffersize'] = 1;
    $CFG->forced_plugin_settings['logstore_database']['dbdriver'] = '';
    $CFG->forced_plugin_settings['logstore_database']['dbhost'] = '';
    $facts['logstores'] = 'logstore_standard buffersize=1; logstore_database dbdriver/dbhost empty';

    // 6. HTTP through Moodle's \curl and \core\http_client: the curl security helper reads $CFG on every check.
    // A port allowlist of "1" blocks every http/https URL (ports 80/443 inferred). The host blocklist is cleared
    // in-process ON PURPOSE: IP-range rules make the helper do a DNS lookup for every domain it checks, and domain
    // wildcards cannot express "all hosts"; with no host rules the check itself makes no network call.
    $CFG->curlsecurityallowedport = '1';
    $CFG->curlsecurityblockedhosts = '';
    // Second layer: a dead proxy for \curl and \core\http_client (set before any of them is constructed).
    $CFG->proxyhost = '127.0.0.1';
    $CFG->proxyport = 9;
    $CFG->proxytype = 'HTTP';
    $CFG->proxybypass = '';
    $helper = new \core\files\curl_security_helper();
    $blockedip = $helper->url_is_blocked('https://192.0.2.1/');
    $helper = new \core\files\curl_security_helper();
    $blockedlocal = $helper->url_is_blocked('http://127.0.0.1:8080/');
    if (!$blockedip || !$blockedlocal) {
        a4_abort('HTTP kill switch self-check failed');
    }
    $facts['http'] = 'curlsecurityallowedport=1, blockedhosts cleared (no DNS), dead proxy 127.0.0.1:9; self-check blocked';

    // 7. Raw curl_init() (bypasses Moodle): curl_exec/curl_multi_exec are disabled on the command line
    // (a4_require_hardened_php). The proxy variables below are a second, untested layer.
    foreach (['http_proxy', 'https_proxy', 'HTTPS_PROXY', 'ALL_PROXY', 'all_proxy'] as $v) {
        putenv("$v=http://127.0.0.1:9");
    }
    putenv('NO_PROXY=');
    putenv('no_proxy=');
    $facts['raw_curl'] = 'curl_exec/curl_multi_exec disabled (php flags); env proxies 127.0.0.1:9 as a second layer';

    // 8. Make send attempts visible: debugging() prints into the captured request output (weblib.php debugging(),
    // CLI branch), where A4_SEND_ATTEMPT_RE trips on them. config.php has debug 0 and debugdisplay 0.
    $CFG->debug = DEBUG_DEVELOPER;
    $CFG->debugdisplay = 1;
    $facts['debug'] = 'debug=DEBUG_DEVELOPER, debugdisplay=1 in-process (send attempts print and trip)';

    return $facts;
}

/** Tripwire tables that exist on this site: explicit list from the maps and reviews plus any outbound-looking table. */
function a4_tripwire_tables(): array {
    global $DB;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $explicit = [
        'notifications', 'message_popup_notifications', 'messages', 'message_conversations', 'message_email_messages',
        'task_adhoc',
        'tool_dataprivacy_request', 'tool_dataprivacy_rqst_ctxlst', 'tool_dataprivacy_contextlist',
        'tool_dataprivacy_ctxlst_ctx',
        'local_sentientia_send_log', 'local_sentientia_push_log', 'local_sentientia_api_whdel',
        'local_sentientia_notif_log', 'local_sentientia_email_log', 'local_sentientia_lb_notify_log',
        'local_compliance_email_log', 'local_emaillogs', 'local_privacy_requests',
        'sms_messages', 'forum_queue', 'events_queue', 'events_queue_handlers',
        // Added from the leaks review: tables that queue or record sends.
        'local_sentientia_evaluation_assign', 'local_sentientia_evaluation_triggers', 'badge_issued',
        'tool_certificate_issues', 'local_sentientia_points_log', 'local_sentientia_user_badges', 'event',
        'reportbuilder_schedule', 'block_ls_schedule', 'local_sentientia_integration_log',
        'local_sentientia_ai_ledger', 'local_sentientia_courses_remind_sent', 'local_sentientia_exams_remind_sent',
        'local_sentientia_api_whsub', 'local_sentientia_api_log', 'forum_digests',
        // logstore_standard_log is NOT counted here (2.6M rows): its max id is in every snapshot (logstore_maxid,
        // and the all-table max ids), and send/user_deleted events are counted since the start.
    ];
    $all = $DB->get_tables(false);
    $tables = [];
    foreach ($explicit as $t) {
        if (isset($all[$t])) {
            $tables[$t] = 'explicit';
        }
    }
    foreach ($all as $t) {
        if (isset($tables[$t])) {
            continue;
        }
        if (preg_match('/(outbox|whdel|_queue$|_queue_|send_log|push_log|notif_log|notify_log|email_log|emaillog|sms_messages|_spool)/', $t)) {
            $tables[$t] = 'pattern';
        }
    }
    foreach (array_keys($tables) as $t) {
        $cols = $DB->get_columns($t);
        if (!isset($cols['id'])) {
            unset($tables[$t]);
        }
    }
    ksort($tables);
    return $cache = $tables;
}

/** Every mdl_ table that has an id column (from information_schema, intersected with Moodle's table list). */
function a4_all_id_tables(): array {
    global $DB, $CFG;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $names = $DB->get_fieldset_sql("SELECT table_name FROM information_schema.columns
                                     WHERE table_schema = DATABASE() AND column_name = 'id'");
    $known = $DB->get_tables(false);
    $out = [];
    foreach ($names as $n) {
        $n = strtolower((string)$n);
        if (strpos($n, $CFG->prefix) !== 0) {
            continue;
        }
        $t = substr($n, strlen($CFG->prefix));
        if (isset($known[$t]) && preg_match('/^[a-z0-9_]+$/', $t)) {
            $out[$t] = true;
        }
    }
    $out = array_keys($out);
    sort($out);
    return $cache = $out;
}

/** MAX(id) of every given table, in UNION ALL batches (an index lookup per table). */
function a4_maxids(array $tables): array {
    global $DB;
    $out = [];
    foreach (array_chunk($tables, 100) as $chunk) {
        $parts = [];
        foreach ($chunk as $t) {
            $parts[] = "SELECT '$t' AS t, COALESCE(MAX(id), 0) AS m FROM {" . $t . "}";
        }
        foreach ($DB->get_records_sql(implode(' UNION ALL ', $parts)) as $r) {
            $out[$r->t] = (int)$r->m;
        }
    }
    ksort($out);
    return $out;
}

/** COUNT(*) of every given table (start and end of a run only: a full scan on the large tables). */
function a4_counts(array $tables): array {
    global $DB;
    $out = [];
    foreach (array_chunk($tables, 50) as $chunk) {
        $parts = [];
        foreach ($chunk as $t) {
            $parts[] = "SELECT '$t' AS t, COUNT(1) AS n FROM {" . $t . "}";
        }
        foreach ($DB->get_records_sql(implode(' UNION ALL ', $parts)) as $r) {
            $out[$r->t] = (int)$r->n;
        }
    }
    ksort($out);
    return $out;
}

/**
 * Checksums of the state a send could be re-armed through without inserting a row: scheduled and ad hoc task timing
 * and state, and every config and plugin setting. Only the checksums leave the database.
 */
function a4_checksums(): array {
    global $DB;
    $q = [
        'task_scheduled' => "SELECT COUNT(1) AS n, COALESCE(SUM(CRC32(CONCAT_WS('|', id, classname, nextruntime,
                                    disabled, lastruntime, faildelay, timestarted))), 0) AS c FROM {task_scheduled}",
        'task_adhoc' => "SELECT COUNT(1) AS n, COALESCE(SUM(CRC32(CONCAT_WS('|', id, classname, nextruntime, faildelay,
                                    timestarted, userid))), 0) AS c FROM {task_adhoc}",
        'config' => "SELECT COUNT(1) AS n, COALESCE(SUM(CRC32(CONCAT_WS('|', id, name, value))), 0) AS c FROM {config}",
        'config_plugins' => "SELECT COUNT(1) AS n, COALESCE(SUM(CRC32(CONCAT_WS('|', id, plugin, name, value))), 0) AS c
                               FROM {config_plugins}",
    ];
    $out = [];
    foreach ($q as $k => $sql) {
        $r = $DB->get_record_sql($sql);
        $out[$k] = $r->n . ':' . $r->c;
    }
    return $out;
}

/**
 * Snapshot. Light part (every request): rows and max id of the tripwire tables, task and user checks, cron start.
 * Full part ($full): MAX(id) of every mdl_ table with an id column and the state checksums.
 */
function a4_snapshot(?int $logbaseline = null, bool $full = false): array {
    global $DB;
    $s = ['tables' => []];
    foreach (a4_tripwire_tables() as $t => $why) {
        $r = $DB->get_record_sql('SELECT COUNT(1) AS n, COALESCE(MAX(id), 0) AS maxid FROM {' . $t . '}');
        $s['tables'][$t] = ['count' => (int)$r->n, 'maxid' => (int)$r->maxid];
    }
    $s['task_adhoc_dataprivacy'] = $DB->count_records_select('task_adhoc',
        $DB->sql_like('classname', ':c', false), ['c' => '%dataprivacy%']);
    $likes = [];
    $params = [];
    foreach (['notif', 'message', 'mail', 'send', 'push', 'webhook', 'whatsapp', 'sms'] as $i => $w) {
        $likes[] = $DB->sql_like('classname', ':m' . $i, false);
        $params['m' . $i] = '%' . $w . '%';
    }
    $s['task_adhoc_messaging'] = $DB->count_records_select('task_adhoc', implode(' OR ', $likes), $params);
    $s['task_running'] = $DB->count_records_select('task_adhoc', 'timestarted IS NOT NULL AND timestarted > 0')
        + $DB->count_records_select('task_scheduled', 'timestarted IS NOT NULL AND timestarted > 0');
    $s['lastcronstart'] = (int)$DB->get_field('config_plugins', 'value', ['plugin' => 'tool_task', 'name' => 'lastcronstart']);
    $s['user_live'] = $DB->count_records('user', ['deleted' => 0]);
    $s['logstore_maxid'] = (int)$DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {logstore_standard_log}');
    if ($logbaseline !== null) {
        [$in, $p] = $DB->get_in_or_equal(A4_SEND_EVENTS, SQL_PARAMS_NAMED, 'ev');
        $p['lb'] = $logbaseline;
        $s['log_send_events_since_start'] = $DB->count_records_select('logstore_standard_log',
            "id > :lb AND eventname $in", $p);
    }
    if ($full) {
        $s['maxids'] = a4_maxids(a4_all_id_tables());
        $s['checksums'] = a4_checksums();
    }
    return $s;
}

/**
 * Compare snapshots. Trips when a tripwire table's max id grows past the baseline or its row count grows past the
 * previous snapshot, a messaging/dataprivacy task appears, a task starts running, cron starts, the live-user count
 * changes, a send/user_deleted event was logged since the start, and (full snapshots) when ANY mdl_ table's max id
 * grows past the baseline (allowlist $allowinsert, empty by default: this path inserts nothing) or a state checksum
 * changes.
 */
function a4_tripwire_compare(array $base, array $prev, array $now, array $allowinsert = []): array {
    $trips = [];
    foreach ($now['tables'] as $t => $v) {
        $b = $base['tables'][$t] ?? ['count' => 0, 'maxid' => 0];
        $p = $prev['tables'][$t] ?? $b;
        if ($v['maxid'] > $b['maxid'] && empty($allowinsert[$t])) {
            $trips[] = "$t.maxid {$b['maxid']} -> {$v['maxid']}";
        }
        if ($v['count'] > $p['count'] && empty($allowinsert[$t])) {
            $trips[] = "$t.count {$p['count']} -> {$v['count']}";
        }
    }
    foreach (['task_adhoc_dataprivacy', 'task_adhoc_messaging'] as $k) {
        if (($now[$k] ?? 0) > ($prev[$k] ?? 0)) {
            $trips[] = "$k {$prev[$k]} -> {$now[$k]}";
        }
    }
    if (($now['task_running'] ?? 0) > ($base['task_running'] ?? 0)) {
        $trips[] = "task_running {$base['task_running']} -> {$now['task_running']} (cron or a task runner is active)";
    }
    if (($now['lastcronstart'] ?? 0) != ($base['lastcronstart'] ?? 0)) {
        $trips[] = "tool_task lastcronstart changed (cron started during the run)";
    }
    if (!empty($GLOBALS['A4_REQUIRE_MAINT']) && !is_file($GLOBALS['A4_REQUIRE_MAINT'])) {
        $trips[] = 'climaintenance.html is gone (maintenance mode ended during the run; web traffic can reach the site)';
    }
    if (($now['user_live'] ?? 0) != ($base['user_live'] ?? 0)) {
        $trips[] = "user_live {$base['user_live']} -> {$now['user_live']}";
    }
    if (!empty($now['log_send_events_since_start'])) {
        $trips[] = "logstore send/user_deleted events since start: {$now['log_send_events_since_start']}";
    }
    if (isset($now['maxids'], $base['maxids'])) {
        foreach ($now['maxids'] as $t => $m) {
            if (!array_key_exists($t, $base['maxids'])) {
                $trips[] = "new table $t";
            } else if ($m > $base['maxids'][$t] && empty($allowinsert[$t])) {
                $trips[] = "all-tables: $t.maxid {$base['maxids'][$t]} -> $m";
            }
        }
    }
    if (isset($now['checksums'], $base['checksums'])) {
        foreach ($now['checksums'] as $k => $c) {
            if (($base['checksums'][$k] ?? null) !== $c) {
                $trips[] = "checksum $k changed";
            }
        }
    }
    return array_values(array_unique($trips));
}

/** Differences between two snapshots (any direction), for the summary. */
function a4_snapshot_delta(array $a, array $b): array {
    $d = [];
    foreach ($b['tables'] as $t => $v) {
        $o = $a['tables'][$t] ?? ['count' => 0, 'maxid' => 0];
        if ($o['count'] != $v['count'] || $o['maxid'] != $v['maxid']) {
            $d[$t] = ['count' => [$o['count'], $v['count']], 'maxid' => [$o['maxid'], $v['maxid']]];
        }
    }
    foreach (['task_adhoc_dataprivacy', 'task_adhoc_messaging', 'task_running', 'user_live', 'lastcronstart'] as $k) {
        if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
            $d[$k] = [$a[$k] ?? null, $b[$k] ?? null];
        }
    }
    if (isset($a['maxids'], $b['maxids'])) {
        foreach ($b['maxids'] as $t => $m) {
            if (($a['maxids'][$t] ?? null) !== $m) {
                $d['maxid:' . $t] = [$a['maxids'][$t] ?? null, $m];
            }
        }
    }
    if (isset($a['checksums'], $b['checksums']) && $a['checksums'] !== $b['checksums']) {
        $d['checksums'] = [$a['checksums'], $b['checksums']];
    }
    return $d;
}

/** Ids of rows added since $basemax in every table whose max id grew (at most 1000 per table). Ids only. */
function a4_new_row_ids(array $basemax, array $nowmax): array {
    global $DB;
    $out = [];
    foreach ($nowmax as $t => $m) {
        $b = $basemax[$t] ?? 0;
        if ($m > $b) {
            $out[$t] = array_map('intval', array_keys($DB->get_records_sql(
                'SELECT id FROM {' . $t . '} WHERE id > :b ORDER BY id', ['b' => $b], 0, 1000)));
        }
    }
    return $out;
}

/** Request counts by type.creationmethod.status, plus total and max id. */
function a4_request_status_distribution(): array {
    global $DB;
    $rows = $DB->get_records_sql("SELECT CONCAT(type, '.', creationmethod, '.', status) AS k,
                                         type, creationmethod, status, COUNT(1) AS n
                                    FROM {tool_dataprivacy_request}
                                GROUP BY type, creationmethod, status");
    $dist = [];
    foreach ($rows as $r) {
        $dist["t{$r->type}.c{$r->creationmethod}.s{$r->status}"] = (int)$r->n;
    }
    ksort($dist);
    return [
        'by_type_cm_status' => $dist,
        'total' => $DB->count_records('tool_dataprivacy_request'),
        'maxid' => (int)$DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {tool_dataprivacy_request}'),
    ];
}

/**
 * Summarise a contextlist_collection (works for plain and approved collections). Ids and counts only.
 * The counts are the ITERATED contexts (foreach, as api.php:1214 and the providers that iterate see them); 'ids' is
 * get_contextids() (the full list). Since 2026-11-13 (bucketing change) every contextlist is iterated through a CLONE:
 * contextlist_base::current() unsets the id of a context that no longer exists (contextlist_base.php:130-133), which
 * would leave a key hole in the list the stock code receives next. A clone has its own copy of the id array, so the
 * lists handed to api.php and to the providers are exactly what discovery produced. Every contextlist and the
 * collection are still rewound afterwards (position 0, as the real task passes them).
 */
function a4_collection_summary($collection, bool $withids = false): array {
    $by = [];
    $unique = [];
    $ids = [];
    $total = 0;
    foreach ($collection as $cl) {
        $comp = $cl->get_component();
        $n = 0;
        $copy = clone $cl;
        foreach ($copy as $ctx) {
            if (!$ctx) {
                continue;   // current() returns null when every remaining id is a missing context (:139-141).
            }
            $n++;
            $unique[$ctx->id] = (int)$ctx->contextlevel;
        }
        $cl->rewind();
        $by[$comp] = ($by[$comp] ?? 0) + $n;
        $total += $n;
        if ($withids) {
            $ids[$comp] = array_values(array_map('intval', $cl->get_contextids()));
        }
    }
    $collection->rewind();
    $levels = [];
    foreach ($unique as $lvl) {
        $levels[$lvl] = ($levels[$lvl] ?? 0) + 1;
    }
    ksort($by);
    ksort($levels);
    ksort($ids);
    $out = [
        'components' => count($by),
        'contexts_total' => $total,
        'unique_contexts' => count($unique),
        'unique_by_level' => $levels,
        'by_component' => $by,
    ];
    if ($withids) {
        $out['ids'] = $ids;
    }
    return $out;
}

/**
 * Per component of a contextlist_collection: count($contextlist), the full list (get_contextids()) and the ids a
 * foreach visits. Ids and counts only. Call it before anything iterates the original lists (it reads
 * get_contextids() first, then iterates a CLONE, so the lists the stock code receives are not touched).
 *
 * Why full and iterated differ (core bug, stock Moodle 5.1, 5.3 and upstream main, not fixed here: ADR-033):
 * contextlist_base::set_contextids() (privacy/classes/local/request/contextlist_base.php:65-67) is array_unique()
 * without a reindex, so a provider whose discovery returns a context id twice (contextlist::add_from_sql() over rows,
 * e.g. logstore_standard provider.php:76-88, report_stats provider.php:94-117) leaves key holes, and valid()
 * (:169-171, isset($this->contextids[$this->iteratorposition])) ends every foreach at the first hole. count() (:186-188)
 * and get_contextids() (:74-76) still hold the full list. api.php:1210-1233 builds the approved list by iterating, so
 * the approval of such a component is truncated and its delete never runs in the later contexts.
 *
 * Returns [component => ['count' => n, 'full' => ids, 'iterated' => ids, 'truncated' => ids (full, not iterated,
 * context exists), 'missing' => ids (the iteration dropped them: the context no longer exists)]].
 */
function a4_contextlist_detail($collection): array {
    $out = [];
    foreach ($collection as $cl) {
        $comp = $cl->get_component();
        $full = array_values(array_map('intval', $cl->get_contextids()));
        $count = count($cl);
        $copy = clone $cl;
        $it = [];
        foreach ($copy as $ctx) {
            if ($ctx) {
                $it[] = (int)$ctx->id;
            }
        }
        // current() unsets the id of a missing context in the copy (contextlist_base.php:130-133): those are not
        // iterator truncation.
        $missing = array_values(array_diff($full, array_map('intval', $copy->get_contextids())));
        $out[$comp] = ['count' => $count, 'full' => $full, 'iterated' => $it,
            'truncated' => array_values(array_diff($full, $it, $missing)), 'missing' => $missing];
    }
    $collection->rewind();
    ksort($out);
    return $out;
}

/**
 * Report fields from a4_contextlist_detail() of the discovery collection and (when there is one) the approved
 * collection. Truncation is EXPECTED stock behaviour: recorded, never a trip.
 *   discovery_count / approved_count            component => count($contextlist)
 *   discovery_full_ids / approved_full_ids      component => get_contextids() (full list)
 *   discovery_iterated_ids / approved_iterated_ids  component => ids a foreach visits (what api.php:1214 and the
 *                                               iterating providers see)
 *   iterator_truncated                          ['discovery' => component => ids, 'approved' => ...] (non-empty only)
 *   iterator_missing_ctx                        same shape: ids the iteration dropped because the context is gone
 *   truncation                                  per-request totals
 *   excluded_by_registry                        from FULL lists: sum over components of count(full discovery) -
 *                                               count(full approved). It contains the iterator truncation: the
 *                                               registry only ever sees the iterated list (api.php:1214-1225).
 *   excluded_by_registry_by_component           the same per component (non-zero only)
 *   excluded_by_registry_iterated               the figure recorded before 2026-11-13: iterated discovery minus
 *                                               approved = what the data registry itself excluded
 */
function a4_truncation_fields(array $disc, ?array $appr): array {
    $f = [];
    $sides = ['discovery' => $disc];
    if ($appr !== null) {
        $sides['approved'] = $appr;
    }
    $trunc = ['discovery' => [], 'approved' => []];
    $miss = ['discovery' => [], 'approved' => []];
    foreach ($sides as $side => $d) {
        $f[$side . '_count'] = array_map(fn($x) => $x['count'], $d);
        $f[$side . '_full_ids'] = array_map(fn($x) => $x['full'], $d);
        $f[$side . '_iterated_ids'] = array_map(fn($x) => $x['iterated'], $d);
        foreach ($d as $comp => $x) {
            if ($x['truncated']) {
                $trunc[$side][$comp] = $x['truncated'];
            }
            if ($x['missing']) {
                $miss[$side][$comp] = $x['missing'];
            }
        }
    }
    $f['iterator_truncated'] = array_filter($trunc);
    if (array_filter($miss)) {
        $f['iterator_missing_ctx'] = array_filter($miss);
    }
    $lost = fn(array $m) => array_sum(array_map('count', $m));
    $f['truncation'] = [
        'discovery_components' => count($trunc['discovery']), 'discovery_contexts_lost' => $lost($trunc['discovery']),
        'approved_components' => count($trunc['approved']), 'approved_contexts_lost' => $lost($trunc['approved']),
        'missing_ctx' => $lost($miss['discovery']) + $lost($miss['approved']),
    ];
    if ($appr !== null) {
        $byc = [];
        $iter = 0;
        foreach ($disc as $comp => $x) {
            $n = $x['count'] - ($appr[$comp]['count'] ?? 0);
            if ($n !== 0) {
                $byc[$comp] = $n;
            }
            $iter += count($x['iterated']) - count($appr[$comp]['iterated'] ?? []);
        }
        $f['excluded_by_registry'] = array_sum($byc);
        $f['excluded_by_registry_by_component'] = $byc;
        $f['excluded_by_registry_iterated'] = $iter;
    }
    return $f;
}

/** mtrace() wrapper (set as $CFG->mtrace_wrapper): collects the privacy manager's trace instead of printing it. */
function a4_mtrace_capture($string, $eol = PHP_EOL) {
    $GLOBALS['A4_MTRACE_BUF'] = ($GLOBALS['A4_MTRACE_BUF'] ?? '') . $string . $eol;
}

/** Split captured output into the privacy manager's progress lines (counted) and everything else (kept). */
function a4_split_output(string $out): array {
    $trace = 0;
    $other = [];
    foreach (preg_split('/\R/', $out) as $line) {
        if (trim($line) === '') {
            continue;
        }
        if (preg_match(A4_TRACE_RE, $line)) {
            $trace++;
        } else {
            $other[] = $line;
        }
    }
    return [$trace, $other];
}

/** Lines of captured output that mean a send attempt or a real observer (A4_SEND_ATTEMPT_RE). */
function a4_send_attempt_lines(string $out): array {
    $hits = [];
    foreach (preg_split('/\R/', $out) as $line) {
        if (preg_match(A4_SEND_ATTEMPT_RE, $line)) {
            $hits[] = a4_trunc($line, 160);
        }
    }
    return $hits;
}

/**
 * The tail of process_data_request_task::execute() (admin/tool/dataprivacy/classes/task/process_data_request_task.php
 * :160-298), run exactly as written for a DELETE request, except that the four send calls (:241 email_to_user,
 * :244 message_send, :287 email_to_user, :289 message_send) and :297 delete_user() are replaced by recorders.
 * Everything else runs for real: the message object, the renderer, both template renders and the same api calls
 * for every condition. Returns what would have been sent (no names or addresses).
 *
 * @param \stdClass $request The request record as the task holds it (to_record() at :67), dpo set as approval sets it.
 * @param \stdClass $foruser The subject's user row (:88).
 * @param bool $deleteuser :153 $deleteuser.
 */
function a4_message_tail(\stdClass $request, \stdClass $foruser, bool $deleteuser): array {
    global $PAGE, $SITE;
    $would = [];
    $cond = [];

    // :161-178 Create message to notify the user regarding the processing results.
    $message = new \core\message\message();
    $message->courseid = $SITE->id;
    $message->component = 'tool_dataprivacy';
    $message->name = 'datarequestprocessingresults';
    if (empty($request->dpo)) {
        $fromuser = \core_user::get_noreply_user();
        $cond['from'] = 'noreply';
    } else {
        $fromuser = \core_user::get_user($request->dpo);
        $message->replyto = $fromuser->email;
        $message->replytoname = fullname($fromuser);
        $cond['from'] = 'dpo';
    }
    $message->userfrom = $fromuser;

    // :180-185
    $typetext = null;
    $messagetextdata = [
        'username' => fullname($foruser),
    ];
    $output = $PAGE->get_renderer('tool_dataprivacy');
    $emailonly = false;
    $notifyuser = true;
    // :188-226, DELETE branch only (every A4 request is type DELETE; the export branch needs the export file).
    switch ($request->type) {
        case \tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE:
            $typetext = get_string('requesttypedelete', 'tool_dataprivacy');
            $message->notification = 0;
            $messagetextdata['message'] = get_string('resultdeleted', 'tool_dataprivacy',
                format_string($SITE->fullname, true, ['context' => \context_system::instance()]));
            $emailonly = true;
            break;
        default:
            throw new \coding_exception('A4 message tail: only DELETE requests are rehearsed');
    }

    // :228-235
    $subject = get_string('datarequestemailsubject', 'tool_dataprivacy', $typetext);
    $message->subject = $subject;
    $message->fullmessageformat = FORMAT_HTML;
    $message->userto = $foruser;
    $messagehtml = $output->render_from_template('tool_dataprivacy/data_request_results_email', $messagetextdata);
    $message->fullmessage = html_to_text($messagehtml);
    $message->fullmessagehtml = $messagehtml;
    $rendered = strlen($messagehtml);

    // :237-250 Send message to the user involved.
    $cond['notifyuser'] = $notifyuser;
    $cond['emailonly'] = $emailonly;
    $cond['foruser_deleted'] = !empty($foruser->deleted);
    if ($notifyuser) {
        if ($emailonly) {
            if (!$foruser->deleted) {
                $would[] = 'task:241 email_to_user(subject)';            // RECORDER instead of email_to_user().
            }
        } else {
            $would[] = 'task:244 message_send(subject)';                  // RECORDER instead of message_send().
        }
    }

    // :252-293 Send to requester as well in some circumstances.
    $cond['subject_is_requester'] = ($foruser->id == $request->requestedby);
    if ($foruser->id != $request->requestedby) {
        $sendtorequester = false;
        switch ($request->type) {
            case \tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE:
                // :268-269, same short-circuit: can_create_data_request_for_user() only when not a site DPO.
                $isdpo = \tool_dataprivacy\api::is_site_dpo($request->requestedby);
                $cond['requester_is_site_dpo'] = $isdpo;
                $sendtorequester = !$isdpo &&
                        \tool_dataprivacy\api::can_create_data_request_for_user($request->userid, $request->requestedby);
                break;
            default:
                throw new \coding_exception('A4 message tail: only DELETE requests are rehearsed');
        }
        $cond['send_to_requester'] = $sendtorequester;
        if ($sendtorequester) {
            $requestedby = \core_user::get_user($request->requestedby);
            $message->userto = $requestedby;
            $messagetextdata['username'] = fullname($requestedby);
            $messagehtml = $output->render_from_template('tool_dataprivacy/data_request_results_email', $messagetextdata);
            $message->fullmessage = html_to_text($messagehtml);
            $message->fullmessagehtml = $messagehtml;
            if ($emailonly) {
                $would[] = 'task:287 email_to_user(requester)';           // RECORDER instead of email_to_user().
            } else {
                $would[] = 'task:289 message_send(requester)';            // RECORDER instead of message_send().
            }
        }
    }

    // :295-298
    $cond['deleteuser'] = $deleteuser;
    if ($deleteuser) {
        $would[] = 'task:297 delete_user(subject)';                       // RECORDER instead of delete_user().
    }
    return ['would' => $would, 'conditions' => $cond, 'rendered_bytes' => $rendered];
}

/**
 * Blast-radius specs: tables the A4-path providers write. {U} is replaced by a comma list of integer user ids.
 * mode delete: only the subject's rows may go (total drop must equal subject drop, no growth).
 * mode keep:   the row count must not change at all.
 * mode anon:   the row count must not change, and the rows whose column holds 'value' may grow by at most the
 *              subject's rows in that column before.
 * 'only' => components: a per-request check runs only when one of them is in the approved collection (only approved
 *   providers run, and these large tables are written by that provider alone). The run-level baseline (a4_verify)
 *   covers every spec over the whole table.
 * 'total' => 'logctx': the per-request total of logstore_standard_log counts the contexts approved for tool_log (its
 *   only delete is "userid = :userid AND contextid IN (approved)", moodle_database_export_and_delete.php:113-120).
 * 'total' => 'quizscope': the per-request total of a question table counts the usages of every attempt in the quizzes
 *   the subject attempted (quiz_delete_user_attempts deletes per quiz and user, mod/quiz/lib.php:351-362).
 * 'subjsql': a full COUNT query for the subject rows (an indexed UNION where an OR would scan the table).
 */
function a4_blast_specs(): array {
    $quba = "SELECT qza.uniqueid FROM {quiz_attempts} qza WHERE qza.userid IN ({U})";
    // Steps of the subject's quiz usages plus steps the subject made elsewhere, as a derived table (an IN over a
    // UNION runs as a dependent subquery per row in MariaDB).
    $qas = "SELECT qas.id FROM {question_attempt_steps} qas
              JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
              JOIN {quiz_attempts} qza ON qza.uniqueid = qa.questionusageid
             WHERE qza.userid IN ({U})
            UNION SELECT qas2.id FROM {question_attempt_steps} qas2 WHERE qas2.userid IN ({U})";
    $quiz = ['only' => ['mod_quiz'], 'total' => 'quizscope'];
    $s = [];
    $d = function(string $t, string $subj, array $extra = []) use (&$s) {
        $s[] = ['t' => $t, 'mode' => 'delete', 'subj' => $subj] + $extra;
    };
    $d('tool_certificate_issues', 'x.userid IN ({U})');
    $d('files', '', ['subjsql' => "SELECT COUNT(1) FROM (
            SELECT f1.id FROM {files} f1 WHERE f1.userid IN ({U})
            UNION SELECT f2.id FROM {files} f2 WHERE f2.component = 'tool_certificate' AND f2.filearea = 'issues'
                AND f2.itemid IN (SELECT ci.id FROM {tool_certificate_issues} ci WHERE ci.userid IN ({U}))
            UNION SELECT f3.id FROM {files} f3 WHERE f3.component = 'grade' AND f3.filearea = 'feedback'
                AND f3.itemid IN (SELECT gg.id FROM {grade_grades} gg WHERE gg.userid IN ({U}))
            UNION SELECT f4.id FROM {files} f4 WHERE f4.component = 'grade' AND f4.filearea = 'historyfeedback'
                AND f4.itemid IN (SELECT gh.id FROM {grade_grades_history} gh WHERE gh.userid IN ({U}))
            UNION SELECT f5.id FROM {files} f5 JOIN ($qas) s5 ON s5.id = f5.itemid WHERE f5.component = 'question'
            UNION SELECT f6.id FROM {files} f6 WHERE f6.contextid IN
                (SELECT c.id FROM {context} c WHERE c.contextlevel = 30 AND c.instanceid IN ({U}))
        ) u"]);
    $d('logstore_standard_log', 'x.userid IN ({U})', ['only' => ['tool_log'], 'total' => 'logctx']);
    $d('grade_grades', 'x.userid IN ({U})');
    $d('grade_grades_history', 'x.userid IN ({U})');
    $d('quiz_attempts', 'x.userid IN ({U})');
    $d('quiz_grades', 'x.userid IN ({U})');
    $d('question_usages', "x.id IN ($quba)", $quiz + ['quiztotal' =>
        'SELECT COUNT(1) FROM {question_usages} x JOIN {quiz_attempts} qz ON qz.uniqueid = x.id WHERE qz.quiz IN ({Q})']);
    $d('question_attempts', "x.questionusageid IN ($quba)", $quiz + ['quiztotal' =>
        'SELECT COUNT(1) FROM {question_attempts} x JOIN {quiz_attempts} qz ON qz.uniqueid = x.questionusageid
          WHERE qz.quiz IN ({Q})']);
    $d('question_attempt_steps', '', $quiz + ['subjsql' => "SELECT COUNT(1) FROM ($qas) u", 'quiztotal' =>
        'SELECT COUNT(1) FROM {question_attempt_steps} x JOIN {question_attempts} qa1 ON qa1.id = x.questionattemptid
           JOIN {quiz_attempts} qz ON qz.uniqueid = qa1.questionusageid WHERE qz.quiz IN ({Q})']);
    $d('question_attempt_step_data', '', $quiz + ['subjsql' =>
        "SELECT COUNT(1) FROM {question_attempt_step_data} d JOIN ($qas) u ON u.id = d.attemptstepid", 'quiztotal' =>
        'SELECT COUNT(1) FROM {question_attempt_step_data} x JOIN {question_attempt_steps} st ON st.id = x.attemptstepid
           JOIN {question_attempts} qa2 ON qa2.id = st.questionattemptid
           JOIN {quiz_attempts} qz ON qz.uniqueid = qa2.questionusageid WHERE qz.quiz IN ({Q})']);
    $d('scorm_attempt', 'x.userid IN ({U})');
    $d('scorm_scoes_value', 'x.attemptid IN (SELECT sa.id FROM {scorm_attempt} sa WHERE sa.userid IN ({U}))',
        ['only' => ['mod_scorm']]);
    $d('course_completions', 'x.userid IN ({U})');
    $d('course_modules_completion', 'x.userid IN ({U})');
    $d('course_modules_viewed', 'x.userid IN ({U})');
    $d('course_completion_crit_compl', 'x.userid IN ({U})');
    $d('stats_user_daily', 'x.userid IN ({U})');
    $d('stats_user_weekly', 'x.userid IN ({U})');
    $d('stats_user_monthly', 'x.userid IN ({U})');
    $d('favourite', 'x.userid IN ({U})');
    $d('user_preferences', 'x.userid IN ({U})');
    $d('sessions', 'x.userid IN ({U})');
    $d('user_devices', 'x.userid IN ({U})');
    $d('user_password_history', 'x.userid IN ({U})');
    $d('user_lastaccess', 'x.userid IN ({U})');
    $d('user_password_resets', 'x.userid IN ({U})');
    $d('course_request', 'x.requester IN ({U})');
    $d('notifications', 'x.useridfrom IN ({U}) OR x.useridto IN ({U})');
    $d('messages', 'x.useridfrom IN ({U})');
    $d('message_conversation_members', 'x.userid IN ({U})');
    $d('message_user_actions', 'x.userid IN ({U})');
    $d('message_contacts', 'x.userid IN ({U}) OR x.contactid IN ({U})');
    $d('messageinbound_messagelist', 'x.userid IN ({U})');
    $d('lti_submission', 'x.userid IN ({U})');
    $d('local_sentientia_learningpath_users', 'x.userid IN ({U})');
    $d('local_sentientia_lp_course_status', 'x.userid IN ({U})');
    $d('local_sentientia_lp_adaptive_log', 'x.userid IN ({U})');
    $d('local_sentientia_org_member', 'x.userid IN ({U})');
    $d('local_sentientia_users_logindays', 'x.userid IN ({U})');
    $a = function(string $t, string $col, $value) use (&$s) {
        $s[] = ['t' => $t, 'mode' => 'anon', 'subj' => "x.$col IN ({U})", 'col' => $col, 'value' => $value];
    };
    $a('local_sentientia_org_member', 'managerid', 0);
    $a('local_sentientia_learningpath_users', 'enrolledby', 0);
    $a('local_sentientia_users_sync_runs', 'usercreated', 0);
    $a('local_sentientia_users_sync_errors', 'modified_by', 0);
    $a('local_sentientia_users_transcript', 'userid', 0);
    $a('local_sentientia_users_transcript', 'usercreated', 0);
    $a('local_sentientia_users_transcript', 'usermodified', 0);
    $k = function(string $t, string $subj) use (&$s) {
        $s[] = ['t' => $t, 'mode' => 'keep', 'subj' => $subj];
    };
    $k('user', 'x.id IN ({U})');
    $k('context', 'x.contextlevel = 30 AND x.instanceid IN ({U})');
    $k('role_assignments', 'x.userid IN ({U})');
    $k('user_enrolments', 'x.userid IN ({U})');
    $k('tool_dataprivacy_request', 'x.userid IN ({U})');
    $k('local_sentientia_request', 'x.userid IN ({U})');
    $k('course', 'x.id < 0');
    $k('course_modules', 'x.id < 0');
    $k('grade_items', 'x.id < 0');
    return $s;
}

/**
 * Counts for the blast specs. $userids: the subject (per request) or a set (run baseline).
 * $scope (per request): ['components' => approved component names, 'logctx' => approved tool_log context ids,
 * 'quizids' => quizzes the subject attempted, taken before the deletion]. null = every spec over the whole table.
 * Returns [key => counts].
 */
function a4_blast_counts(array $userids, ?array $scope = null): array {
    global $DB;
    static $tables = null;
    if ($tables === null) {
        $tables = $DB->get_tables(false);
    }
    $u = implode(',', array_map('intval', $userids ?: [0]));
    $out = [];
    $specs = a4_blast_specs();
    // Rows the subject owns, per table: the subject condition of that table's delete spec. An anon spec on the same
    // table (org_member.managerid, learningpath_users.enrolledby) sees those rows deleted, not anonymised; without
    // this the total drop tripped as "row count changed" (canary request 504, 2026-11-13).
    $owncond = [];
    foreach ($specs as $sp) {
        if ($sp['mode'] === 'delete' && empty($sp['subjsql']) && $sp['subj'] !== '') {
            $owncond[$sp['t']] = $sp['subj'];
        }
    }
    foreach ($specs as $sp) {
        $key = $sp['t'] . ':' . $sp['mode'] . (isset($sp['col']) ? ':' . $sp['col'] : '');
        if (!isset($tables[$sp['t']])) {
            $out[$key] = ['missing_table' => true];
            continue;
        }
        if ($scope !== null && !empty($sp['only']) && !array_intersect($sp['only'], $scope['components'] ?? [])) {
            $out[$key] = ['skipped' => 'component not approved'];
            continue;
        }
        $from = '{' . $sp['t'] . '} x';
        $subjsql = !empty($sp['subjsql']) ? str_replace('{U}', $u, $sp['subjsql'])
            : 'SELECT COUNT(1) FROM ' . $from . ' WHERE ' . str_replace('{U}', $u, $sp['subj']);
        $t0 = microtime(true);
        try {
            $totalsql = 'SELECT COUNT(1) FROM ' . $from;
            $scopename = 'table';
            if ($scope !== null && ($sp['total'] ?? '') === 'logctx') {
                $ctx = array_map('intval', $scope['logctx'] ?? []) ?: [0];
                $totalsql .= ' WHERE x.contextid IN (' . implode(',', $ctx) . ')';
                $scopename = 'approved tool_log contexts';
            } else if ($scope !== null && ($sp['total'] ?? '') === 'quizscope') {
                $q = array_map('intval', $scope['quizids'] ?? []) ?: [0];
                $totalsql = str_replace('{Q}', implode(',', $q), $sp['quiztotal']);
                $scopename = 'usages of the quizzes the subject attempted';
            }
            $total = (int)$DB->count_records_sql($totalsql);
            $nsubj = (int)$DB->count_records_sql($subjsql);
            $rec = ['mode' => $sp['mode'], 'total' => $total, 'subj' => $nsubj, 'scope' => $scopename];
            if ($sp['mode'] === 'anon') {
                $rec['anon'] = (int)$DB->count_records_sql('SELECT COUNT(1) FROM ' . $from . ' WHERE x.'
                    . $sp['col'] . ' = :v', ['v' => $sp['value']]);
                if (isset($owncond[$sp['t']])) {
                    // Anonymised-looking rows that belong to the subject (deleted with them, not anonymised).
                    $own = str_replace('{U}', $u, $owncond[$sp['t']]);
                    $rec['anon_owned'] = (int)$DB->count_records_sql('SELECT COUNT(1) FROM ' . $from . ' WHERE x.'
                        . $sp['col'] . ' = :v AND (' . $own . ')', ['v' => $sp['value']]);
                    // Rows that point at the subject AND belong to the subject (self-enrolment: enrolledby = userid).
                    // They are deleted, never anonymised, so they must not count as allowance for anonymisation.
                    $rec['subj_owned'] = (int)$DB->count_records_sql('SELECT COUNT(1) FROM ' . $from . ' WHERE ('
                        . str_replace('{U}', $u, $sp['subj']) . ') AND (' . $own . ')');
                    $rec['owned_by'] = $sp['t'] . ':delete';
                }
            }
        } catch (\dml_exception $e) {
            $rec = ['error' => a4_trunc($e->getMessage(), 160)];
        }
        $rec['secs'] = round(microtime(true) - $t0, 3);
        $out[$key] = $rec;
    }
    return $out;
}

/** Per-request blast scope: approved components, approved tool_log contexts, the subject's quizzes (before deletion). */
function a4_blast_scope(int $userid, array $approvedids): array {
    global $DB;
    return [
        'components' => array_keys($approvedids),
        'logctx' => $approvedids['tool_log'] ?? [],
        'quizids' => isset($approvedids['mod_quiz']) ? array_map('intval', $DB->get_fieldset_sql(
            'SELECT DISTINCT quiz FROM {quiz_attempts} WHERE userid = :u', ['u' => $userid])) : [],
    ];
}

/** Compare per-request blast counts (before/after one subject's deletion). Returns trip strings. */
function a4_blast_compare(array $before, array $after): array {
    $trips = [];
    foreach ($before as $k => $b) {
        $a = $after[$k] ?? null;
        if (!isset($b['total']) || !isset($a['total'])) {
            continue;
        }
        if (($b['scope'] ?? '') !== ($a['scope'] ?? '')) {
            continue;
        }
        $dtotal = $b['total'] - $a['total'];
        $dsubj = $b['subj'] - $a['subj'];
        switch ($b['mode']) {
            case 'delete':
                if ($a['total'] > $b['total']) {
                    $trips[] = "blast $k: rows added ({$b['total']} -> {$a['total']})";
                } else if ($dtotal !== $dsubj) {
                    $trips[] = "blast $k: removed $dtotal row(s) but only $dsubj belonged to the subject";
                }
                break;
            case 'keep':
                if ($dtotal !== 0) {
                    $trips[] = "blast $k: row count changed ({$b['total']} -> {$a['total']})";
                }
                break;
            case 'anon':
                // The total may fall by exactly the subject's own rows, which the same table's delete spec removed.
                $ownkey = !empty($b['owned_by']) ? $b['owned_by'] : null;
                $owned = ($ownkey !== null && isset($before[$ownkey]['subj'], $after[$ownkey]['subj']))
                    ? $before[$ownkey]['subj'] - $after[$ownkey]['subj'] : 0;
                // Newly anonymised rows among the rows the subject does not own.
                $growth = isset($b['anon_owned'], $a['anon_owned'])
                    ? ($a['anon'] - $a['anon_owned']) - ($b['anon'] - $b['anon_owned'])
                    : $a['anon'] - $b['anon'];
                $allowance = $b['subj'] - ($b['subj_owned'] ?? 0);
                if ($dtotal !== $owned) {
                    $trips[] = "blast $k: row count changed ({$b['total']} -> {$a['total']}); the subject's own rows "
                        . "removed were $owned";
                } else if ($growth > $allowance) {
                    $trips[] = "blast $k: $growth row(s) newly anonymised but the subject had $allowance (excluding rows they own)";
                }
                break;
        }
    }
    return $trips;
}

/** Run-level baseline for a4_verify: per blast spec, total and rows of the given subject set. */
function a4_blast_baseline(array $subjectids): array {
    $c = a4_blast_counts($subjectids, null);
    $out = [];
    foreach ($c as $k => $v) {
        if (isset($v['total'])) {
            $out[$k] = ['mode' => $v['mode'], 'total' => $v['total'], 'a4' => $v['subj'],
                'nonsubject' => $v['total'] - $v['subj']] + (isset($v['anon']) ? ['anon' => $v['anon']] : [])
                + (isset($v['anon_owned']) ? ['anon_owned' => $v['anon_owned'], 'a4_owned' => $v['subj_owned'],
                    'owned_by' => $v['owned_by']] : []);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

/**
 * Must-be-empty checks for processed subjects (a4_verify; flat counts also for the run's start baseline). Each check is
 * the data the named provider's delete_data_for_user() removes (code read 2026-11-13; line numbers are the local 5.1
 * XAMPP files. On the local 5.3 runtime the course, completion, stats, log and scorm providers are byte-identical;
 * mod/quiz's delete is the same code at other line numbers; core_grades' delete also removes grade_outcomes_modules
 * rows for module contexts (not checked here); tool_certificate is not installed there).
 *
 * Entry: [table, where, component] or [table, where, component, bucket]. In 'where' (unqualified columns = the table
 * itself): {U} = subject ids; {I} = the tool_certificate issue ids recorded in the run's start line (0 when none).
 *
 * Without 'bucket' (not context-scoped): one flat count, as before 2026-11-13. {U} = the processed subjects whose request
 * approved the component (every processed subject for a report line without approved_ids). Any row is a VIOLATION.
 *
 * With 'bucket' (context-scoped, 2026-11-13): {U} = ALL processed subjects. For each row the query returns the subject
 * ('subj') and the id of the context the provider's delete keys on ('ctx'). Where the delete keys on the row's raw
 * contextid (tool_log, certificate files), 'ctx' is that raw id and 'gone' is the SQL condition "the context row no
 * longer exists" (ctx_missing flag), so a context removed after the run cannot turn a failure into another bucket.
 * Elsewhere 'ctx' comes from a context join and is NULL when the context no longer exists (= ctx_missing).
 * The SQL runs over alias x = the table's rows matching 'where', plus the aliases defined in 'join'. Each row is then
 * bucketed against that subject's lists for the component in its report line (a4_residue_bucket()), in this order:
 *   failure       ctx is in the approved list, ITERATED or FULL: the delete was asked to run for that context and the
 *                 row is still there. The only VIOLATION here. (FULL as well since the 2026-11-13 review: tool_log,
 *                 report_stats, mod_scorm, core_grades and tool_certificate delete over get_contextids()/get_contexts(),
 *                 the whole list, not the iterator. Stock api.php builds approved lists without holes, so full ==
 *                 iterated; a4_verify reports any approved-list hole separately as a violation.)
 *   not_approved_unbucketed_pre_guard  report lines written before 2026-11-13 (no full lists, e.g. the canary):
 *                 every row whose ctx is not in approved_ids. Not a violation.
 *   undiscovered  the context no longer exists (ctx_missing), or ctx is not in the subject's full discovery list for
 *                 the component. Stock behaviour, measured (e.g. the core_course discovery gap).
 *   truncated     ctx is in the FULL discovery list but was not iterated (contextlist_base iterator bug, see
 *                 a4_contextlist_detail()): stock behaviour, measured.
 *   registry      ctx is in the iterated discovery list and not approved (data registry). Not a violation in itself,
 *                 but impossible while defaults_set is false (api.php:1208-1225 then approves every iterated
 *                 context): a4_verify reports such rows as a violation (registry_bucket_impossible).
 * 'ref' records where the ctx expression was checked against the provider's code.
 */
function a4_must_be_empty_specs(): array {
    $syscx = 'LEFT JOIN {context} cs ON cs.contextlevel = ' . CONTEXT_SYSTEM;
    $coursecx = fn(string $col) => 'LEFT JOIN {context} c ON c.contextlevel = ' . CONTEXT_COURSE
        . " AND c.instanceid = $col";
    $modcx = fn(string $mod, string $col) => "JOIN {modules} m ON m.name = '$mod'
        LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = $col
        LEFT JOIN {context} c ON c.contextlevel = " . CONTEXT_MODULE . ' AND c.instanceid = cm.id';
    $itemcx = 'LEFT JOIN {grade_items} gi ON gi.id = x.itemid ' . $coursecx('gi.courseid');
    $lp = 'local_sentientia_learningpath';
    $certref = 'admin/tool/certificate/classes/privacy/provider.php:78-80 discovery = the system context when the user '
        . 'holds an issue; :186-199 delete runs for the system context only: delete_area_files(system ctx id, '
        . "'tool_certificate', 'issues', issue id) for each of the user's issues, then the issue rows WHERE userid. "
        . 'ctx = the system context (issue rows) / the file row\'s own contextid (files)';
    $gradesref = 'grade/classes/privacy/provider.php:776-802: course ids of the approved COURSE contexts (get_contexts()) '
        . '-> grade item ids of those courses (:1164-1171) -> grade_grades and grade_grades_history WHERE itemid AND userid. '
        . 'ctx = the course context of the grade item\'s course';
    $quizref = 'mod/quiz/classes/privacy/provider.php:383-421: per approved MODULE context (foreach), '
        . 'get_coursemodule_from_id(quiz) then quiz_delete_user_attempts() (mod/quiz/lib.php:351-362: quiz_attempts and '
        . 'quiz_grades WHERE quiz AND userid). ctx = the module context of the quiz\'s course module';
    $statsref = 'report/stats/classes/privacy/provider.php:225-234: approved COURSE contexts (get_contexts()) -> '
        . 'delete_stats(instanceid, userid) (:263-269: stats_user_daily/weekly/monthly WHERE courseid AND userid). '
        . 'ctx = the course context of courseid. Discovery :94-117 runs add_from_sql three times: duplicate ids, so the '
        . 'approval is truncated (contextlist_base.php:66 + :170)';
    $stats = ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $coursecx('x.courseid'), 'ref' => $statsref];
    return [
        ['tool_certificate_issues', 'userid IN ({U})', 'tool_certificate',
            ['subj' => 'x.userid', 'ctx' => 'cs.id', 'join' => $syscx, 'ref' => $certref]],
        // Root cause 3 (2026-11-13): this check used "itemid IN ({I})", and {I} holds the issues of ALL A4 subjects, so
        // it counted the files of issues that unprocessed subjects still hold (155 rows on the canary). Now: files of an
        // A4 issue whose issue row is gone, or files of an issue a processed subject still holds. Subject = the issue's
        // owner (LEFT JOIN); for a gone issue, the owner recorded in the start line (cert_issue_owners), else the row is
        // counted as a failure (subject unresolved). The delete removes files in the system context only (:195).
        ['files', "component = 'tool_certificate' AND filearea = 'issues' AND ((itemid IN ({I}) AND itemid NOT IN "
            . "(SELECT ci.id FROM {tool_certificate_issues} ci)) OR itemid IN (SELECT ci2.id FROM "
            . "{tool_certificate_issues} ci2 WHERE ci2.userid IN ({U})))", 'tool_certificate',
            // ctx = the file row's raw contextid (2026-11-13 review): the delete keys on the context id, not on a
            // context row, so a context removed after the run must not move a leftover file out of failure.
            ['subj' => 'ci.userid', 'ctx' => 'x.contextid', 'gone' => 'c.id IS NULL', 'issue' => 'x.itemid',
                'ref' => $certref,
                'join' => 'LEFT JOIN {tool_certificate_issues} ci ON ci.id = x.itemid LEFT JOIN {context} c ON c.id = x.contextid']],
        ['quiz_attempts', 'userid IN ({U})', 'mod_quiz',
            ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $modcx('quiz', 'x.quiz'), 'ref' => $quizref]],
        ['quiz_grades', 'userid IN ({U})', 'mod_quiz',
            ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $modcx('quiz', 'x.quiz'), 'ref' => $quizref]],
        ['scorm_attempt', 'userid IN ({U})', 'mod_scorm',
            ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $modcx('scorm', 'x.scormid'),
                'ref' => 'mod/scorm/classes/privacy/provider.php:292-323: MODULE contexts of the approved list '
                . '(get_contexts()) -> scorm_delete_tracks(cm instance, null, userid) for each (mod/scorm/locallib.php:'
                . '2570-2599: scorm_attempt WHERE scormid AND userid). ctx = the module context of the scorm\'s course module']],
        ['grade_grades', 'userid IN ({U})', 'core_grades',
            ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $itemcx, 'ref' => $gradesref]],
        ['grade_grades_history', 'userid IN ({U})', 'core_grades',
            ['subj' => 'x.userid', 'ctx' => 'CASE WHEN gi.id IS NULL THEN cu.id ELSE c.id END',
                'join' => $itemcx . ' LEFT JOIN {context} cu ON cu.contextlevel = ' . CONTEXT_USER
                    . ' AND cu.instanceid = x.userid',
                'ref' => $gradesref . '; history rows whose grade item is gone are deleted only through the subject\'s own '
                    . 'USER context (:777-779 -> delete_orphan_historical_grades :853-861): ctx = that user context']],
        ['course_completions', 'userid IN ({U})', 'core_course',
            ['subj' => 'x.userid', 'ctx' => 'c.id', 'join' => $coursecx('x.course'),
                'ref' => 'course/classes/privacy/provider.php:255-264: per approved COURSE context (foreach) '
                . 'delete_completion(user, course id) (completion/classes/privacy/provider.php:233-277; :276 '
                . 'course_completions WHERE course AND userid). ctx = the course context of course. Discovery '
                . '(course/classes/privacy/provider.php:70-84 with completion :93-109) only finds courses with completion '
                . 'criteria the user touched: the other rows are never deleted (core gap) and land in undiscovered']],
        // Narrowed 2026-11-13 to what the delete targets: before, every cmc row of the subject in an existing course.
        ['course_modules_completion', 'userid IN ({U}) AND coursemoduleid IN (SELECT ccc.moduleinstance FROM '
            . '{course_completion_criteria} ccc WHERE ccc.moduleinstance IS NOT NULL)', 'core_course',
            ['subj' => 'x.userid', 'ctx' => 'c.id',
                'join' => 'LEFT JOIN (SELECT ccm.moduleinstance, MIN(ccm.course) AS course FROM {course_completion_criteria} '
                    . 'ccm WHERE ccm.moduleinstance IS NOT NULL GROUP BY ccm.moduleinstance) cr ON cr.moduleinstance = '
                    . 'x.coursemoduleid ' . $coursecx('cr.course'),
                'ref' => 'completion/classes/privacy/provider.php:251-260 (from core_course :260): deletes the cmc rows of '
                . 'modules that are completion criteria of the approved course (ccc.course = course AND cmc.coursemoduleid '
                . '= ccc.moduleinstance). Rows of modules that are no criterion are never targeted by core_course and are '
                . 'not counted. ctx = the course context of the criterion\'s course']],
        // ctx = the row's raw contextid (2026-11-13 review): the delete is "contextid IN get_contextids()", so a
        // context removed after the run must not move a leftover row out of failure; c.id IS NULL is ctx_missing.
        ['logstore_standard_log', 'userid IN ({U})', 'tool_log',
            ['subj' => 'x.userid', 'ctx' => 'x.contextid', 'gone' => 'c.id IS NULL',
                'join' => 'LEFT JOIN {context} c ON c.id = x.contextid',
                'ref' => 'admin/tool/log/classes/local/privacy/moodle_database_export_and_delete.php:113-120: userid = '
                . 'subject AND contextid IN the approved get_contextids() (relateduserid/realuserid rows are kept by design '
                . 'and not in this check). ctx = the row\'s raw contextid. Discovery: admin/tool/log/store/standard/classes/'
                . 'privacy/provider.php:76-88, one id per log row: duplicates, so the approval is truncated']],
        ['stats_user_daily', 'userid IN ({U})', 'report_stats', $stats],
        ['stats_user_weekly', 'userid IN ({U})', 'report_stats', $stats],
        ['stats_user_monthly', 'userid IN ({U})', 'report_stats', $stats],
        [$lp . '_users', 'userid IN ({U}) OR enrolledby IN ({U})', 'local_sentientia_learningpath'],
        ['local_sentientia_lp_adaptive_log', 'userid IN ({U})', 'local_sentientia_learningpath'],
        ['local_sentientia_lp_course_status', 'userid IN ({U}) OR usercreated IN ({U}) OR usermodified IN ({U})',
            'local_sentientia_learningpath'],
        [$lp, 'usercreated IN ({U}) OR usermodified IN ({U})', 'local_sentientia_learningpath'],
        [$lp . '_courses', 'usercreated IN ({U}) OR usermodified IN ({U})', 'local_sentientia_learningpath'],
        ['local_sentientia_request', "userid IN ({U}) AND (reason IS NULL OR reason <> '(redacted)')",
            'local_sentientia_request'],
        ['local_sentientia_request', "decided_by_userid IN ({U}) AND (decision_note IS NULL OR decision_note <> '(redacted)')",
            'local_sentientia_request'],
        ['local_sentientia_request', "legacy_source IS NOT NULL AND decision_note IS NOT NULL
            AND decision_note <> '(redacted)' AND (userid IN ({U}) OR approver_userid IN ({U}) OR decided_by_userid IN ({U}))",
            'local_sentientia_request'],
        ['local_sentientia_users_sync_runs', 'usercreated IN ({U})', 'local_sentientia_users'],
        ['local_sentientia_users_sync_errors', 'modified_by IN ({U})', 'local_sentientia_users'],
        ['local_sentientia_users_transcript', 'userid IN ({U}) OR usercreated IN ({U}) OR usermodified IN ({U})',
            'local_sentientia_users'],
        ['local_sentientia_users_logindays', 'userid IN ({U})', 'local_sentientia_users'],
        ['local_sentientia_org_member', 'userid IN ({U}) OR managerid IN ({U})', 'local_sentientia_core'],
        ['user_lastaccess', 'userid IN ({U})', 'core_user'],
        ['user_password_history', 'userid IN ({U})', 'core_user'],
        ['user_password_resets', 'userid IN ({U})', 'core_user'],
        ['user_devices', 'userid IN ({U})', 'core_user'],
        ['sessions', 'userid IN ({U})', 'core_user'],
        ['course_request', 'requester IN ({U})', 'core_user'],
        ['messages', 'useridfrom IN ({U})', 'core_message'],
        ['notifications', 'useridfrom IN ({U}) OR useridto IN ({U})', 'core_message'],
        ['messageinbound_messagelist', 'userid IN ({U})', 'tool_messageinbound'],
    ];
}

/** Result key of a must-be-empty spec. */
function a4_mbe_key(string $t, string $where, string $component): string {
    return "$t [$component] " . a4_trunc($where, 80);
}

/** {U} / {I} substitution (integers only; an empty list becomes 0, which matches no id). */
function a4_mbe_sql(string $where, array $subjects, array $issueids): string {
    return str_replace(['{U}', '{I}'], [implode(',', array_map('intval', $subjects ?: [0])),
        implode(',', array_map('intval', $issueids ?: [0]))], $where);
}

/**
 * Flat must-be-empty counts. $subjectsfor($component) returns the subject ids a check applies to. $flatonly: skip the
 * context-scoped specs (a4_verify buckets those with a4_must_be_empty_buckets()). The run's start baseline counts every
 * spec flat over all A4 subjects (the context-scoped ones without the context filter they had before 2026-11-13).
 * Returns [key => ['subjects' => n, 'rows_left' => n] | skipped | error | missing_table].
 */
function a4_must_be_empty_counts(callable $subjectsfor, array $issueids, bool $flatonly = false): array {
    global $DB;
    $tables = $DB->get_tables(false);
    $out = [];
    foreach (a4_must_be_empty_specs() as $spec) {
        [$t, $where, $component] = $spec;
        if ($flatonly && !empty($spec[3])) {
            continue;
        }
        $key = a4_mbe_key($t, $where, $component);
        if (!isset($tables[$t])) {
            $out[$key] = ['missing_table' => true];
            continue;
        }
        $subjects = array_values(array_map('intval', $subjectsfor($component)));
        if (!$subjects) {
            $out[$key] = ['skipped' => "no subject had $component approved"];
            continue;
        }
        $sql = a4_mbe_sql($where, $subjects, $issueids);
        $t0 = microtime(true);
        try {
            $out[$key] = ['table' => $t, 'component' => $component, 'subjects' => count($subjects),
                'rows_left' => (int)$DB->count_records_sql('SELECT COUNT(1) FROM {' . $t . '} WHERE ' . $sql),
                'secs' => round(microtime(true) - $t0, 2)];
        } catch (\dml_exception $e) {
            $out[$key] = ['table' => $t, 'component' => $component, 'error' => a4_trunc($e->getMessage(), 160)];
        }
    }
    return $out;
}

/** The residue bucket names, in classification order (a4_must_be_empty_specs()). */
const A4_RESIDUE_BUCKETS = ['failure', 'truncated', 'registry', 'undiscovered', 'not_approved_unbucketed_pre_guard'];

/**
 * Per-subject lists for the bucketing, from one execute report line. A line written since 2026-11-13 has the full
 * lists; an older one (the canary) has approved_ids only and is marked pre_guard.
 */
function a4_subject_lists_from_report(array $r): array {
    $ints = function($m): array {
        $o = [];
        foreach ((array)$m as $c => $ids) {
            $o[(string)$c] = array_map('intval', (array)$ids);
        }
        return $o;
    };
    if (!isset($r['discovery_full_ids'])) {
        return ['pre_guard' => true, 'appr_iter' => $ints($r['approved_ids'] ?? [])];
    }
    return [
        'pre_guard' => false,
        'disc_full' => $ints($r['discovery_full_ids']),
        'disc_iter' => $ints($r['discovery_iterated_ids'] ?? []),
        'appr_full' => $ints($r['approved_full_ids'] ?? ($r['approved_ids'] ?? [])),
        'appr_iter' => $ints($r['approved_iterated_ids'] ?? ($r['approved_ids'] ?? [])),
    ];
}

/**
 * Bucket of one leftover row (a4_must_be_empty_specs() has the order and meaning). $l: subject lists, $c: component,
 * $ctx: the context id the delete keys on (null when it comes from a context join and the context is gone), $ctxgone:
 * the context row no longer exists (raw-contextid specs).
 * failure is checked FIRST and against the approved list ITERATED or FULL (2026-11-13 review): five of the seven
 * context-scoped components delete over the whole list (get_contextids()/get_contexts()), so a context in the full
 * approved list that the iterator never reached is still a context the delete was asked to clear.
 */
function a4_residue_bucket(array $l, string $c, ?int $ctx, bool $ctxgone = false): string {
    $in = fn(string $k) => $ctx !== null && in_array($ctx, $l[$k][$c] ?? [], true);
    if ($in('appr_iter') || $in('appr_full')) {
        return 'failure';
    }
    if (!empty($l['pre_guard'])) {
        return 'not_approved_unbucketed_pre_guard';
    }
    if ($ctx === null || $ctxgone || !$in('disc_full')) {
        return 'undiscovered';
    }
    if (!$in('disc_iter')) {
        return 'truncated';
    }
    return 'registry';
}

/**
 * Bucketed must-be-empty checks (the specs with a 'bucket' entry), over every processed subject.
 * $lists: subjectid => a4_subject_lists_from_report(). $issueowners: tool_certificate issue id => owner, the union of
 * cert_issue_owners over every execute start line read (a4_verify; empty for start lines older than 2026-11-13).
 * Returns [key => ['table', 'component', 'subjects', 'rows', 'buckets' => [bucket => rows], 'ctx_missing' (bucketed
 * rows whose context no longer exists), 'ctx_missing_by_bucket', 'failure_subject_unresolved', 'subject_not_processed',
 * 'by_subject' => [subjectid => [bucket => rows]], 'failure_rows' => sample of [subject, ctx, rows, ctx_gone?], 'ref',
 * 'secs'] | skipped | error | missing_table].
 */
function a4_must_be_empty_buckets(array $lists, array $issueids, array $issueowners = []): array {
    global $DB;
    $tables = $DB->get_tables(false);
    $subjects = array_map('intval', array_keys($lists));
    sort($subjects);
    $out = [];
    foreach (a4_must_be_empty_specs() as $spec) {
        if (empty($spec[3])) {
            continue;
        }
        [$t, $where, $c, $b] = $spec;
        $key = a4_mbe_key($t, $where, $c);
        if (!isset($tables[$t])) {
            $out[$key] = ['missing_table' => true];
            continue;
        }
        if (!$subjects) {
            $out[$key] = ['skipped' => 'no processed subject'];
            continue;
        }
        // GROUP BY repeats the expressions: MySQL/MariaDB resolve a GROUP BY name against the FROM columns first.
        $group = [$b['subj'], $b['ctx']];
        if (!empty($b['issue'])) {
            $group[] = $b['issue'];
        }
        // 'gone' (raw-contextid specs): an aggregate, so it is valid under ONLY_FULL_GROUP_BY on MySQL and MariaDB;
        // every row of a group has the same contextid, so MAX() is that context's flag.
        $gone = !empty($b['gone']) ? 'MAX(CASE WHEN ' . $b['gone'] . ' THEN 1 ELSE 0 END)' : '0';
        $sql = 'SELECT ' . $b['subj'] . ' AS subj, ' . $b['ctx'] . ' AS ctx, '
            . (!empty($b['issue']) ? $b['issue'] : '0') . ' AS issue, ' . $gone . ' AS ctxgone, COUNT(1) AS n
                  FROM (SELECT * FROM {' . $t . '} WHERE ' . a4_mbe_sql($where, $subjects, $issueids) . ') x
                  ' . $b['join'] . '
              GROUP BY ' . implode(', ', $group);
        $res = ['table' => $t, 'component' => $c, 'bucketed' => true, 'subjects' => count($subjects), 'rows' => 0,
            'buckets' => array_fill_keys(A4_RESIDUE_BUCKETS, 0), 'ctx_missing' => 0, 'ctx_missing_by_bucket' => [],
            'failure_subject_unresolved' => 0, 'subject_not_processed' => 0, 'by_subject' => [], 'failure_rows' => [],
            'ref' => $b['ref']];
        $t0 = microtime(true);
        try {
            $rs = $DB->get_recordset_sql($sql);
            foreach ($rs as $r) {
                $n = (int)$r->n;
                $ctx = ($r->ctx === null) ? null : (int)$r->ctx;
                $ctxgone = ($ctx === null) || ((int)$r->ctxgone === 1);
                $uid = ($r->subj === null) ? (int)($issueowners[(int)$r->issue] ?? 0) : (int)$r->subj;
                $res['rows'] += $n;
                if ($uid === 0) {
                    // A file of an A4 issue whose issue row is gone and whose owner no start line records: the issue
                    // row was deleted and the file was not. Counted as a failure (conservative).
                    $bucket = 'failure';
                    $res['failure_subject_unresolved'] += $n;
                } else if (!isset($lists[$uid])) {
                    // An A4 issue of a subject this run did not process: not this delete's row.
                    $res['subject_not_processed'] += $n;
                    continue;
                } else {
                    $bucket = a4_residue_bucket($lists[$uid], $c, $ctx, $ctxgone);
                }
                $res['buckets'][$bucket] += $n;
                $res['by_subject'][$uid][$bucket] = ($res['by_subject'][$uid][$bucket] ?? 0) + $n;
                if ($ctxgone) {
                    // Counted for bucketed rows only. Mostly undiscovered; any in failure is a context the approved
                    // list held that is gone now (raw-contextid specs keep those in failure).
                    $res['ctx_missing'] += $n;
                    $res['ctx_missing_by_bucket'][$bucket] = ($res['ctx_missing_by_bucket'][$bucket] ?? 0) + $n;
                }
                if ($bucket === 'failure' && count($res['failure_rows']) < 200) {
                    $res['failure_rows'][] = ['subject' => $uid, 'ctx' => $ctx, 'rows' => $n] + ($ctxgone ? ['ctx_gone' => true] : []);
                }
            }
            $rs->close();
        } catch (\dml_exception $e) {
            $res = ['table' => $t, 'component' => $c, 'bucketed' => true, 'error' => a4_trunc($e->getMessage(), 160)];
        }
        $res['secs'] = round(microtime(true) - $t0, 2);
        if (isset($res['by_subject'])) {
            ksort($res['by_subject']);
        }
        $out[$key] = $res;
    }
    return $out;
}

/**
 * Residue kept by design, per component and normalised context key ('system', 'own_user' or '*'): a provider that
 * still reports these after a successful delete is not a failed delete. Each has a companion must-be-empty check.
 */
const A4_RESIDUE_BY_DESIGN = [
    'core_user' => ['own_user' => 'user row and user context kept (delete_user_data removes non-vital data only, '
        . 'user/classes/privacy/provider.php:283+); discovery = context exists (:195-203)'],
    'tool_dataprivacy' => ['own_user' => 'discovery = user context exists (provider.php:106-116); delete is empty (:215)'],
    'tool_messageinbound' => ['own_user' => 'discovery always adds the user context (provider.php:78-85)'],
    'local_sentientia_request' => ['system' => 'rows kept, text redacted (provider.php:104-122); discovery = any row '
        . 'with userid/approver/decider (:41-44)'],
    'tool_log' => ['*' => 'discovery includes relateduserid/realuserid rows (logstore_standard provider.php:76-88), '
        . 'which the delete keeps by design (moodle_database_export_and_delete.php:113-120)'],
];

/**
 * Components whose discovery reports exactly the data their delete removes: still being reported for a processed
 * subject (and not for the control ids) means the delete did not work. Moodle core components with a wider discovery
 * (actor and history columns that delete_data_for_user() keeps) are checked through a4_must_be_empty_specs() instead.
 */
function a4_discovery_exact(string $component): bool {
    if ($component === 'local_sentientia_request') {
        return false;   // Keeps redacted rows by design.
    }
    return $component === 'tool_certificate' || strpos($component, 'local_sentientia_') === 0;
}

/** Table.column pairs for the residual-data counts: the census "requested" list plus every non-zero census hit. */
function a4_residual_targets(): array {
    return [
        ['logstore_standard_log', 'userid'], ['logstore_standard_log', 'relateduserid'],
        ['user_enrolments', 'userid'], ['role_assignments', 'userid'], ['course_completions', 'userid'],
        ['grade_grades', 'userid'], ['files', 'userid'], ['user_preferences', 'userid'], ['user_info_data', 'userid'],
        ['sessions', 'userid'],
        ['notifications', 'useridto'],
        ['question_attempt_steps', 'userid'], ['grade_grades_history', 'userid'], ['block_ls_modtimestats', 'userid'],
        ['course_modules_completion', 'userid'], ['course_modules_viewed', 'userid'],
        ['course_completion_crit_compl', 'userid'], ['block_ls_coursetimestats', 'userid'], ['block_devicels', 'userid'],
        ['block_recentlyaccesseditems', 'userid'], ['tool_dataprivacy_request', 'userid'],
        ['tool_certificate_issues', 'userid'], ['quiz_attempts', 'userid'], ['quiz_grades', 'userid'],
        ['scorm_attempt', 'userid'], ['stats_user_monthly', 'userid'], ['stats_user_weekly', 'userid'],
        ['stats_user_daily', 'userid'], ['favourite', 'userid'], ['event', 'userid'],
        ['paygw_airpay_errorlog', 'userid'], ['paygw_airpay', 'userid'], ['paygw_course_enrolmentlog', 'userid'],
        ['my_pages', 'userid'],
        ['local_emaillogs', 'to_userid'], ['local_userdata', 'userid'], ['local_rating', 'userid'],
        ['local_sentientia_org_member', 'managerid'], ['local_like', 'userid'],
        ['local_sentientia_learningpath_users', 'userid'], ['local_sentientia_request', 'userid'],
        // Added (fidelity review): local_sentientia_users legacy tables and the request actor columns.
        ['local_sentientia_org_member', 'userid'],
        ['local_sentientia_request', 'approver_userid'], ['local_sentientia_request', 'decided_by_userid'],
        ['local_sentientia_users_sync_runs', 'usercreated'], ['local_sentientia_users_sync_errors', 'modified_by'],
        ['local_sentientia_users_transcript', 'userid'], ['local_sentientia_users_transcript', 'usercreated'],
        ['local_sentientia_users_transcript', 'usermodified'], ['local_sentientia_users_logindays', 'userid'],
    ];
}

/** Rows and distinct subjects per table.column for the given user ids. Read-only. */
function a4_residual_counts(array $userids): array {
    global $DB;
    $out = [];
    if (!$userids) {
        return $out;
    }
    $all = $DB->get_tables(false);
    [$in, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'u');
    foreach (a4_residual_targets() as [$t, $c]) {
        $key = "$t.$c";
        if (!isset($all[$t])) {
            $out[$key] = ['missing_table' => true];
            continue;
        }
        $cols = $DB->get_columns($t);
        if (!isset($cols[$c])) {
            $out[$key] = ['missing_column' => true];
            continue;
        }
        $t0 = microtime(true);
        $r = $DB->get_record_sql("SELECT COUNT(1) AS nrows, COUNT(DISTINCT $c) AS nsubj FROM {" . $t . "} WHERE $c $in",
            $params);
        $out[$key] = ['rows' => (int)$r->nrows, 'subjects' => (int)$r->nsubj, 'secs' => round(microtime(true) - $t0, 2)];
    }
    return $out;
}

/** Non-empty value counts on the user rows of the given ids that are marked deleted. Counts only. */
function a4_user_row_residue(array $userids): array {
    global $DB;
    if (!$userids) {
        return [];
    }
    $cols = $DB->get_columns('user');
    $fields = ['username', 'firstname', 'lastname', 'email', 'phone1', 'phone2', 'institution', 'department',
        'address', 'city', 'country', 'lastip', 'idnumber', 'description', 'picture', 'firstaccess', 'lastaccess',
        'currentlogin'];
    foreach (array_keys($cols) as $name) {
        if (preg_match('/^open_[a-z0-9_]+$/', $name)) {
            $fields[] = $name;
        }
    }
    $sel = ['COUNT(1) AS n'];
    foreach ($fields as $f) {
        if (!isset($cols[$f]) || !preg_match('/^[a-z0-9_]+$/', $f)) {
            continue;
        }
        $sel[] = "SUM(CASE WHEN COALESCE(CAST($f AS CHAR), '') NOT IN ('', '0') THEN 1 ELSE 0 END) AS {$f}_set";
    }
    $sel[] = "SUM(CASE WHEN username LIKE '%@%' THEN 1 ELSE 0 END) AS username_with_at";
    $sel[] = "SUM(CASE WHEN email LIKE '%@%' THEN 1 ELSE 0 END) AS email_with_at";
    [$in, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'u');
    $r = $DB->get_record_sql('SELECT ' . implode(', ', $sel) . " FROM {user} WHERE deleted = 1 AND id $in", $params);
    $out = [];
    foreach ((array)$r as $k => $v) {
        $out[$k] = (int)$v;
    }
    return $out;
}

/** Census baseline (all 515 subjects) from the data-census map's raw JSON, if present. */
function a4_load_census_baseline(string $path): array {
    if (!is_readable($path)) {
        return [];
    }
    $d = json_decode((string)file_get_contents($path), true);
    if (!is_array($d)) {
        return [];
    }
    $out = ['residuals' => [], 'userrow' => $d['s2_deleted_userrow_residue'] ?? null, 'counts' => $d['s6_counts'] ?? null];
    foreach (['s5_requested', 's5_local', 's5_core_sweep', 's5_messaging'] as $k) {
        foreach ($d[$k] ?? [] as $r) {
            $out['residuals'][$r['table'] . '.' . $r['column']] = [
                'rows' => (int)$r['rows'], 'subjects' => (int)$r['subjects_with_rows'],
            ];
        }
    }
    return $out;
}

/** All subject user ids of the A4 request set (type DELETE, creationmethod AUTO), whatever their status now. */
function a4_all_a4_subject_ids(): array {
    global $DB;
    return array_map('intval', $DB->get_fieldset_select('tool_dataprivacy_request', 'userid',
        'type = :t AND creationmethod = :c', ['t' => \tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE,
            'c' => \tool_dataprivacy\data_request::DATAREQUEST_CREATION_AUTO]));
}

/** tool_certificate issue ids held by the given users (ids only; for the files check in a4_verify). */
function a4_cert_issue_ids(array $userids): array {
    global $DB;
    if (!$userids || !$DB->get_manager()->table_exists('tool_certificate_issues')) {
        return [];
    }
    [$in, $p] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'ci');
    return array_map('intval', $DB->get_fieldset_select('tool_certificate_issues', 'id', "userid $in", $p));
}

/**
 * tool_certificate issue id => owner user id for the given users (ids only). Recorded in the start line
 * (cert_issue_owners) so that a4_verify can give a file row of an issue that the run deleted to its subject.
 */
function a4_cert_issue_owners(array $userids): array {
    global $DB;
    if (!$userids || !$DB->get_manager()->table_exists('tool_certificate_issues')) {
        return [];
    }
    [$in, $p] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'co');
    $out = [];
    $rs = $DB->get_recordset_select('tool_certificate_issues', "userid $in", $p, 'id', 'id, userid');
    foreach ($rs as $r) {
        $out[(int)$r->id] = (int)$r->userid;
    }
    $rs->close();
    return $out;
}

/** Request ids this script has touched, from its journal (used by --resume). */
function a4_journal_ids(string $path): array {
    $ids = [];
    if (!is_readable($path)) {
        return $ids;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if (is_array($r) && !empty($r['requestid'])) {
            $ids[(int)$r['requestid']] = true;
        }
    }
    return $ids;
}

/** tool_dataprivacy registry and filter settings the deletion result depends on (api.php:1206-1222). */
function a4_registry_facts(): array {
    return [
        'defaults_set' => \tool_dataprivacy\data_registry::defaults_set(),
        'requireallenddatesforuserdeletion' => (string)get_config('tool_dataprivacy', 'requireallenddatesforuserdeletion'),
        'allowfiltering' => (string)get_config('tool_dataprivacy', 'allowfiltering'),
        'automaticdatadeletionapproval' => (string)get_config('tool_dataprivacy', 'automaticdatadeletionapproval'),
        'dporoles_set' => trim((string)get_config('tool_dataprivacy', 'dporoles')) !== '',
    ];
}

/**
 * Files whose code decides what an execute run does: every component's classes/privacy/provider.php, the dataprivacy
 * task path, the privacy manager, the send and event code, and the helpers the delete paths call
 * (maps/provider-delete-paths.md section 8 plus the core helpers the reviews named).
 */
function a4_manifest_paths(): array {
    global $CFG;
    $paths = [];
    $components = [];
    foreach (\core_component::get_component_list() as $type => $list) {
        foreach ($list as $comp => $dir) {
            if ($dir && is_file("$dir/classes/privacy/provider.php")) {
                $rel = a4_relpath("$dir/classes/privacy/provider.php");
                $paths[$rel] = true;
                $components[$comp] = $rel;
            }
        }
    }
    if (is_file("$CFG->libdir/classes/privacy/provider.php")) {
        $paths['lib/classes/privacy/provider.php'] = true;
        $components['core'] = 'lib/classes/privacy/provider.php';
    }
    $admin = $CFG->admin;
    $helpers = [
        "$admin/tool/dataprivacy/classes/api.php", "$admin/tool/dataprivacy/classes/task/process_data_request_task.php",
        "$admin/tool/dataprivacy/classes/data_request.php", "$admin/tool/dataprivacy/classes/data_registry.php",
        "$admin/tool/dataprivacy/classes/expired_contexts_manager.php", "$admin/tool/dataprivacy/classes/manager_observer.php",
        "$admin/tool/dataprivacy/classes/external/data_request_exporter.php", "$admin/tool/dataprivacy/lib.php",
        "$admin/tool/dataprivacy/templates/data_request_results_email.mustache",
        'privacy/classes/manager.php', 'privacy/classes/local/request/helper.php',
        'privacy/classes/local/request/contextlist_base.php', 'privacy/classes/local/request/approved_contextlist.php',
        'privacy/classes/local/request/contextlist_collection.php', 'privacy/classes/local/request/contextlist.php',
        'lib/messagelib.php', 'lib/moodlelib.php', 'lib/weblib.php', 'lib/filelib.php', 'lib/classes/event/manager.php',
        'lib/classes/event/base.php', 'lib/classes/files/curl_security_helper.php', 'lib/classes/cron.php',
        'lib/filestorage/file_storage.php', 'lib/filestorage/stored_file.php', 'lib/filestorage/file_system.php',
        'lib/filestorage/file_system_filedir.php',
        'mod/quiz/lib.php', 'mod/quiz/locallib.php', 'mod/scorm/lib.php', 'mod/scorm/locallib.php',
        'question/engine/lib.php', 'question/engine/datalib.php',
        "$admin/tool/log/classes/local/privacy/moodle_database_export_and_delete.php",
        "$admin/tool/log/store/database/classes/log/store.php",
        'message/output/airnotifier/classes/manager.php',
        'local/sentientia_cart/classes/invoicer.php', 'local/sentientia_cart/classes/credit_notes.php',
        'local/sentientia_cart/classes/flags.php', 'local/sentientia_cart/classes/gst_settings.php',
        'local/sentientia_cart/classes/imported_history.php', 'local/sentientia_cart/classes/receipts.php',
        'local/sentientia_cart/classes/docseq.php', 'local/sentientia_cart/classes/doc_view.php',
        'local/sentientia_core/classes/admin_log.php', 'local/sentientia_classroom/classes/waitlist_manager.php',
        'local/sentientia_users/classes/legacy_history.php', 'local/sentientia_whatsapp/classes/preference_manager.php',
        'local/sentientia_proctoring/classes/task/purge_old_recordings.php',
        // The class is local_sentientia_platform\schema\user_type_tables (classes/schema/); the path below was
        // classes/user_type_tables.php until 2026-11-13, which hashed nothing ("missing" matched "missing").
        'local/sentientia_platform/classes/schema/user_type_tables.php', 'local/sentientia_authoring/classes/scd/repository.php',
        'local/sentientia_privacy/classes/privacy_manager.php', 'local/sentientia_privacy/index.php',
    ];
    foreach ($helpers as $h) {
        // A wrong helper path would hash to null on both sides and always "match"; refuse it instead.
        if (!is_file($CFG->dirroot . '/' . $h)) {
            a4_abort("manifest helper path does not exist: $h (fix the list in a4_manifest_paths())");
        }
        $paths[$h] = true;
    }
    $paths = array_keys($paths);
    sort($paths);
    ksort($components);
    return [$paths, $components];
}

/** Build the manifest: sha1 of every path (null when absent), the provider component map and the registry state. */
function a4_manifest_build(): array {
    global $CFG;
    [$paths, $components] = a4_manifest_paths();
    $files = [];
    foreach ($paths as $p) {
        $full = $CFG->dirroot . '/' . $p;
        $files[$p] = is_file($full) ? sha1_file($full) : null;
    }
    return ['generated' => gmdate('Y-m-d\TH:i:s\Z'), 'moodle_release' => $CFG->release ?? '',
        'registry' => a4_registry_facts(), 'components' => $components, 'files' => $files];
}

/** Differences between a stored manifest and the current one (paths only). */
function a4_manifest_diff(array $stored, array $now): array {
    $d = [];
    foreach ($now['files'] as $p => $h) {
        if (!array_key_exists($p, $stored['files'] ?? [])) {
            $d[] = "new file in scope: $p";
        } else if ($stored['files'][$p] !== $h) {
            $d[] = ($h === null ? 'removed: ' : 'changed: ') . $p;
        }
    }
    foreach (array_keys($stored['files'] ?? []) as $p) {
        if (!array_key_exists($p, $now['files'])) {
            $d[] = "no longer in scope: $p";
        }
    }
    if (($stored['registry']['defaults_set'] ?? null) !== $now['registry']['defaults_set']) {
        $d[] = 'tool_dataprivacy data registry defaults_set changed: '
            . var_export($stored['registry']['defaults_set'] ?? null, true) . ' -> ' . var_export($now['registry']['defaults_set'], true);
    }
    if (($stored['registry']['requireallenddatesforuserdeletion'] ?? null) !== $now['registry']['requireallenddatesforuserdeletion']) {
        $d[] = 'tool_dataprivacy requireallenddatesforuserdeletion changed';
    }
    return $d;
}

function a4_manifest_write(string $path, array $m): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        a4_abort("cannot create folder for $path");
    }
    if (file_put_contents($path, json_encode($m, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
        a4_abort("cannot write $path");
    }
}

/**
 * Backup gate for --execute: the dump must be a complete mysqldump of database moodle, recent and large, with the key
 * tables and no phpu_ table. One streaming pass computes sha256 and looks for the CREATE TABLE lines.
 */
function a4_check_backup(string $path): array {
    $min = 1024 * 1024 * 1024;
    if (!a4_is_absolute($path) || !is_file($path)) {
        a4_abort('--backup must be an absolute path to the mysqldump file', 1);
    }
    $size = filesize($path);
    if ($size < $min) {
        a4_abort("--backup is $size bytes; a complete dump of the mdl_ tables is over 1 GB", 1);
    }
    if (filemtime($path) < time() - 48 * 3600) {
        a4_abort('--backup is older than 48 h', 1);
    }
    $fh = fopen($path, 'rb');
    $head = (string)fread($fh, 2048);
    if (!preg_match('/^-- (MariaDB|MySQL) dump/m', $head) || !preg_match('/Database: moodle\b/', $head)) {
        a4_abort('--backup does not start like a mysqldump of database moodle', 1);
    }
    fseek($fh, max(0, $size - 1024));
    $tail = (string)fread($fh, 1024);
    if (strpos($tail, '-- Dump completed on') === false) {
        a4_abort('--backup has no "-- Dump completed on" footer: the dump is incomplete', 1);
    }
    rewind($fh);
    $needles = ['CREATE TABLE `mdl_tool_dataprivacy_request`' => false, 'CREATE TABLE `mdl_tool_certificate_issues`' => false,
        'CREATE TABLE `mdl_logstore_standard_log`' => false];
    $phpu = false;
    $ctx = hash_init('sha256');
    $carry = '';
    while (!feof($fh)) {
        $chunk = (string)fread($fh, 8 * 1024 * 1024);
        hash_update($ctx, $chunk);
        $hay = $carry . $chunk;
        foreach ($needles as $n => $found) {
            if (!$found && strpos($hay, $n) !== false) {
                $needles[$n] = true;
            }
        }
        if (!$phpu && strpos($hay, 'CREATE TABLE `phpu_') !== false) {
            $phpu = true;
        }
        $carry = substr($hay, -64);
    }
    fclose($fh);
    $missing = array_keys(array_filter($needles, fn($f) => !$f));
    if ($missing) {
        a4_abort('--backup lacks: ' . implode(', ', $missing), 1);
    }
    if ($phpu) {
        a4_abort('--backup contains phpu_ tables: dump the mdl_ tables only', 1);
    }
    return ['path' => $path, 'bytes' => $size, 'mtime' => date('c', filemtime($path)),
        'sha256' => hash_final($ctx), 'header' => 'mysqldump of moodle', 'footer' => 'Dump completed', 'key_tables' => 3];
}
