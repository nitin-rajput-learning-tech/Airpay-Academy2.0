<?php
/**
 * KeKa HRMS Client — syncs employees, departments, handles JML events.
 *
 * Authentication: OAuth 2.0 with API key.
 * Endpoints: Core HR (employees, departments, groups, exit).
 * Webhooks: employee.hired, employee.terminated, employee.transferred.
 *
 * @package    local_airpay_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_integrations;

defined('MOODLE_INTERNAL') || die();

class keka_client {

    /** KeKa API base URL. */
    private string $base_url;

    /** OAuth access token. */
    private ?string $access_token = null;

    public function __construct() {
        $this->base_url = get_config('local_airpay_integrations', 'keka_base_url') ?: 'https://api.keka.com';
    }

    /**
     * Authenticate with KeKa using API key → get access token.
     */
    public function authenticate(): bool {
        $api_key = get_config('local_airpay_integrations', 'keka_api_key');
        $client_id = get_config('local_airpay_integrations', 'keka_client_id');
        $client_secret = get_config('local_airpay_integrations', 'keka_client_secret');

        if (empty($api_key) && empty($client_id)) {
            return false;
        }

        // Method 1: API Key token generation.
        if (!empty($api_key)) {
            $response = $this->http_post('/connect/token', [
                'grant_type' => 'kekaapi',
                'scope'      => 'kekaapi',
                'api_key'    => $api_key,
            ], 'form');

            if ($response && isset($response['access_token'])) {
                $this->access_token = $response['access_token'];
                return true;
            }
        }

        // Method 2: OAuth client credentials.
        if (!empty($client_id) && !empty($client_secret)) {
            $response = $this->http_post('/connect/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'scope'         => 'kekaapi',
            ], 'form');

            if ($response && isset($response['access_token'])) {
                $this->access_token = $response['access_token'];
                return true;
            }
        }

        return false;
    }

    /**
     * Get all employees from KeKa.
     *
     * @param int $page Page number (1-based)
     * @param int $pagesize Page size
     * @return array {data: [{id, firstName, lastName, email, department, ...}], hasMore: bool}
     */
    public function get_employees(int $page = 1, int $pagesize = 100): array {
        return $this->http_get('/v1/hris/employees', [
            'pageNumber' => $page,
            'pageSize'   => $pagesize,
        ]);
    }

    /**
     * Get single employee by ID.
     */
    public function get_employee(string $employee_id): ?array {
        return $this->http_get("/v1/hris/employees/{$employee_id}");
    }

    /**
     * Get departments from KeKa.
     */
    public function get_departments(): array {
        return $this->http_get('/v1/hris/departments') ?: [];
    }

    /**
     * Get locations from KeKa.
     */
    public function get_locations(): array {
        return $this->http_get('/v1/hris/locations') ?: [];
    }

    /**
     * Sync employees from KeKa → Moodle.
     * Creates new users, updates existing, suspends terminated.
     *
     * @return array {created: int, updated: int, suspended: int, errors: int}
     */
    public function sync_employees(): array {
        global $DB;

        if (!$this->authenticate()) {
            return ['created' => 0, 'updated' => 0, 'suspended' => 0, 'errors' => 1,
                    'error_message' => 'Authentication failed'];
        }

        $stats = ['created' => 0, 'updated' => 0, 'suspended' => 0, 'errors' => 0];
        $page = 1;

        do {
            $result = $this->get_employees($page, 100);
            $employees = $result['data'] ?? $result ?? [];

            if (empty($employees) || !is_array($employees)) {
                break;
            }

            foreach ($employees as $emp) {
                try {
                    $this->sync_single_employee($emp, $stats);
                } catch (\Exception $e) {
                    $stats['errors']++;
                    debugging('KeKa sync error: ' . $e->getMessage());
                }
            }

            $page++;
            $hasmore = $result['hasMore'] ?? (count($employees) >= 100);
        } while ($hasmore && $page <= 100); // Safety limit.

        return $stats;
    }

    /**
     * Sync a single employee record.
     */
    private function sync_single_employee(array $emp, array &$stats): void {
        global $DB, $CFG;

        $email = strtolower(trim($emp['email'] ?? $emp['workEmail'] ?? ''));
        if (empty($email)) {
            $stats['errors']++;
            return;
        }

        // Map KeKa fields to Moodle.
        $userdata = [
            'email'              => $email,
            'username'           => $email,
            'firstname'          => $emp['firstName'] ?? '',
            'lastname'           => $emp['lastName'] ?? '',
            'open_employeeid'    => $emp['employeeNumber'] ?? $emp['id'] ?? '',
            'open_designation'   => $emp['jobTitle'] ?? $emp['designation'] ?? '',
            'open_location'      => $emp['location'] ?? '',
            'open_employmenttype' => $emp['employmentType'] ?? '',
        ];

        // Map department to costcenter path.
        $deptname = $emp['department'] ?? '';
        if (!empty($deptname)) {
            $costcenter = $DB->get_record_select('local_costcenter',
                $DB->sql_like('fullname', ':name'), ['name' => '%' . $DB->sql_like_escape($deptname) . '%'],
                'id, path', IGNORE_MULTIPLE);
            if ($costcenter) {
                $userdata['open_path'] = $costcenter->path;
            }
        }

        // Check if user exists.
        $existing = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

        // Handle terminated employees.
        $status = $emp['status'] ?? $emp['employeeStatus'] ?? 'active';
        $is_terminated = in_array(strtolower($status), ['inactive', 'terminated', 'exited', 'relieved']);

        if ($existing) {
            if ($is_terminated && !$existing->suspended) {
                // Leaver: suspend the account.
                $DB->set_field('user', 'suspended', 1, ['id' => $existing->id]);
                $DB->set_field('user', 'timemodified', time(), ['id' => $existing->id]);
                $stats['suspended']++;
            } else if (!$is_terminated) {
                // Mover: update fields.
                $needsupdate = false;
                foreach ($userdata as $field => $value) {
                    if (!empty($value) && isset($existing->$field) && $existing->$field !== $value) {
                        $existing->$field = $value;
                        $needsupdate = true;
                    }
                }
                if ($needsupdate) {
                    $existing->timemodified = time();
                    $DB->update_record('user', $existing);
                    $stats['updated']++;
                }
            }
        } else if (!$is_terminated) {
            // Joiner: create new user.
            $newuser = (object)array_merge([
                'auth'           => 'manual',
                'confirmed'      => 1,
                'mnethostid'     => 1,
                'password'       => hash_internal_user_password(random_string(16)),
                'timecreated'    => time(),
                'timemodified'   => time(),
            ], $userdata);

            $userid = $DB->insert_record('user', $newuser);

            // Trigger lifecycle auto-enrolment.
            if (file_exists($CFG->dirroot . '/local/airpay_lifecycle/classes/observer.php')) {
                $event = \core\event\user_created::create([
                    'objectid' => $userid,
                    'context'  => \context_system::instance(),
                ]);
                $event->trigger();
            }

            $stats['created']++;
        }
    }

    /**
     * Handle incoming webhook from KeKa.
     *
     * @param string $event_type  Event type (employee.hired, employee.terminated, employee.transferred)
     * @param array  $payload     Webhook payload
     * @return array {success: bool, message: string}
     */
    public static function handle_webhook(string $event_type, array $payload): array {
        $client = new self();

        switch ($event_type) {
            case 'employee.hired':
                // Fetch full employee data and create user.
                $emp_id = $payload['employeeId'] ?? $payload['id'] ?? '';
                if (empty($emp_id)) {
                    return ['success' => false, 'message' => 'Missing employee ID'];
                }
                if (!$client->authenticate()) {
                    return ['success' => false, 'message' => 'Auth failed'];
                }
                $emp = $client->get_employee($emp_id);
                if ($emp) {
                    $stats = ['created' => 0, 'updated' => 0, 'suspended' => 0, 'errors' => 0];
                    $client->sync_single_employee($emp, $stats);
                    return ['success' => true, 'message' => "Joiner processed: created={$stats['created']}"];
                }
                return ['success' => false, 'message' => 'Employee not found'];

            case 'employee.terminated':
            case 'employee.exited':
                $email = $payload['email'] ?? $payload['workEmail'] ?? '';
                if (!empty($email)) {
                    global $DB;
                    $user = $DB->get_record('user', ['email' => strtolower($email), 'deleted' => 0]);
                    if ($user) {
                        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
                        return ['success' => true, 'message' => "Leaver suspended: {$email}"];
                    }
                }
                return ['success' => false, 'message' => 'User not found for termination'];

            case 'employee.transferred':
            case 'employee.updated':
                // Re-sync the employee.
                $emp_id = $payload['employeeId'] ?? $payload['id'] ?? '';
                if (!empty($emp_id) && $client->authenticate()) {
                    $emp = $client->get_employee($emp_id);
                    if ($emp) {
                        $stats = ['created' => 0, 'updated' => 0, 'suspended' => 0, 'errors' => 0];
                        $client->sync_single_employee($emp, $stats);
                        return ['success' => true, 'message' => "Mover processed: updated={$stats['updated']}"];
                    }
                }
                return ['success' => false, 'message' => 'Transfer sync failed'];

            default:
                return ['success' => false, 'message' => "Unknown event: {$event_type}"];
        }
    }

    /**
     * HTTP GET request to KeKa API.
     */
    private function http_get(string $endpoint, array $params = []): ?array {
        $url = $this->base_url . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->access_token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode === 200 && $response) ? json_decode($response, true) : null;
    }

    /**
     * HTTP POST request to KeKa API.
     */
    private function http_post(string $endpoint, array $data, string $type = 'json'): ?array {
        $url = $this->base_url . $endpoint;

        $ch = curl_init($url);
        $headers = ['Accept: application/json'];

        if ($type === 'form') {
            $body = http_build_query($data);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $body = json_encode($data);
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->access_token) {
            $headers[] = 'Authorization: Bearer ' . $this->access_token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode >= 200 && $httpcode < 300 && $response) ? json_decode($response, true) : null;
    }
}
