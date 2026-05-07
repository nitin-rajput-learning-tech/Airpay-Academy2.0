<?php
/**
 * Scheduled task: HRMS Sync.
 * Pulls employee data from Keka/HRMS API and syncs to Moodle.
 * Only runs when hrms_enable = 1 and API credentials are configured.
 *
 * @package    local_airpay_integrations
 * @copyright  2026 Airpay Payment Services
 */

namespace local_airpay_integrations\task;

defined('MOODLE_INTERNAL') || die();

class hrms_sync extends \core\task\scheduled_task {

    public function get_name() {
        return 'Airpay HRMS Employee Sync';
    }

    public function execute() {
        $enabled = get_config('local_airpay_integrations', 'hrms_enable');
        $apiurl = get_config('local_airpay_integrations', 'hrms_api_url');
        $apikey = get_config('local_airpay_integrations', 'hrms_api_key');

        if (empty($enabled) || empty($apiurl) || empty($apikey)) {
            mtrace('HRMS sync: disabled or not configured. Skipping.');
            return;
        }

        mtrace('HRMS sync: starting...');

        try {
            $employees = $this->fetch_employees($apiurl, $apikey);
            mtrace('HRMS sync: fetched ' . count($employees) . ' employees');

            $created = 0;
            $updated = 0;
            $suspended = 0;

            foreach ($employees as $emp) {
                $result = $this->sync_employee($emp);
                switch ($result) {
                    case 'created': $created++; break;
                    case 'updated': $updated++; break;
                    case 'suspended': $suspended++; break;
                }
            }

            mtrace("HRMS sync: complete. Created: $created, Updated: $updated, Suspended: $suspended");

            // Send Teams notification if enabled.
            if (class_exists('\local_airpay_integrations\teams_notifier')) {
                \local_airpay_integrations\teams_notifier::send(
                    '🔄 HRMS Sync Complete',
                    "Created: {$created}, Updated: {$updated}, Suspended: {$suspended}",
                    'good'
                );
            }
        } catch (\Exception $e) {
            mtrace('HRMS sync: ERROR — ' . $e->getMessage());
        }
    }

    /**
     * Fetch employees from HRMS API.
     * This is a scaffold — the actual API call depends on Keka's API format.
     *
     * Expected response format (adapt to actual Keka API):
     * [
     *   { "employee_id": "AP001", "first_name": "Priya", "last_name": "Singh",
     *     "email": "priya@airpay.co.in", "department": "Operations",
     *     "designation": "Associate", "manager_id": "AP050", "status": "active" }
     * ]
     */
    private function fetch_employees(string $apiurl, string $apikey): array {
        $url = rtrim($apiurl, '/') . '/employees';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            throw new \Exception("HRMS API returned HTTP $httpcode");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('HRMS API returned invalid JSON');
        }

        return $data;
    }

    /**
     * Sync a single employee record.
     * Creates user if not exists, updates fields if changed, suspends if inactive.
     *
     * @param array $emp Employee data from HRMS
     * @return string 'created', 'updated', 'suspended', or 'unchanged'
     */
    private function sync_employee(array $emp): string {
        global $DB, $CFG;

        $empid = $emp['employee_id'] ?? '';
        $email = $emp['email'] ?? '';

        if (empty($empid) || empty($email)) {
            return 'unchanged';
        }

        // Find existing user by employee ID or email.
        $user = $DB->get_record_select('user',
            "open_employeeid = :empid OR email = :email",
            ['empid' => $empid, 'email' => $email]);

        $status = $emp['status'] ?? 'active';

        if ($user) {
            // Update existing user.
            $changed = false;

            if ($status === 'inactive' || $status === 'terminated') {
                if (!$user->suspended) {
                    $user->suspended = 1;
                    $user->timemodified = time();
                    $DB->update_record('user', $user);
                    return 'suspended';
                }
                return 'unchanged';
            }

            // Update fields if changed.
            $fields = [
                'firstname' => $emp['first_name'] ?? $user->firstname,
                'lastname' => $emp['last_name'] ?? $user->lastname,
                'open_employeeid' => $empid,
                'open_designation' => $emp['designation'] ?? $user->open_designation,
            ];

            foreach ($fields as $field => $value) {
                if (isset($user->$field) && $user->$field !== $value) {
                    $user->$field = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $user->timemodified = time();
                $DB->update_record('user', $user);
                return 'updated';
            }

            return 'unchanged';
        }

        // Create new user.
        $newuser = new \stdClass();
        $newuser->username = strtolower($empid);
        $newuser->firstname = $emp['first_name'] ?? 'Employee';
        $newuser->lastname = $emp['last_name'] ?? $empid;
        $newuser->email = $email;
        $newuser->auth = 'manual';
        $newuser->confirmed = 1;
        $newuser->mnethostid = $CFG->mnet_localhost_id;
        $newuser->password = hash_internal_user_password('Airpay@' . date('Y'));
        $newuser->open_employeeid = $empid;
        $newuser->open_designation = $emp['designation'] ?? '';
        $newuser->timecreated = time();
        $newuser->timemodified = time();

        try {
            require_once($CFG->dirroot . '/user/lib.php');
            user_create_user($newuser, false, false);
            return 'created';
        } catch (\Exception $e) {
            mtrace('HRMS sync: failed to create user ' . $empid . ': ' . $e->getMessage());
            return 'unchanged';
        }
    }
}
