<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle user <-> SCIM User representation (RFC 7643 §4.1) and PATCH
 * application (RFC 7644 §3.5.2).
 *
 * The SCIM `id` is the Moodle user id (stable, unique). `externalId` lives in
 * the per-client mapping table. Only the attributes IdPs actually drive are
 * mapped: userName, name.givenName/familyName, displayName, emails, active,
 * externalId. Unknown attributes/paths are ignored, never fatal.
 *
 * @package local_sentientia_api
 */
class user_resource {

    /**
     * @param \stdClass   $user       Row from {user}
     * @param string|null $externalid Mapped externalId (may be null)
     * @param string      $baseurl    SCIM base URL (no trailing slash)
     * @return array
     */
    public static function to_scim(\stdClass $user, ?string $externalid, string $baseurl): array {
        $out = [
            'schemas'  => [response::SCHEMA_USER],
            'id'       => (string) $user->id,
            'userName' => (string) $user->username,
            'name'     => [
                'givenName'  => (string) $user->firstname,
                'familyName' => (string) $user->lastname,
                'formatted'  => trim($user->firstname . ' ' . $user->lastname),
            ],
            'displayName' => trim($user->firstname . ' ' . $user->lastname),
            'active'   => empty($user->suspended) && empty($user->deleted),
            'emails'   => [[
                'value'   => (string) $user->email,
                'type'    => 'work',
                'primary' => true,
            ]],
            'meta' => [
                'resourceType' => 'User',
                'created'      => self::iso((int) $user->timecreated),
                'lastModified' => self::iso((int) ($user->timemodified ?: $user->timecreated)),
                'location'     => $baseurl . '/Users/' . (int) $user->id,
                'version'      => 'W/"' . (int) ($user->timemodified ?: $user->timecreated) . '"',
            ],
        ];
        if ($externalid !== null && $externalid !== '') {
            $out['externalId'] = $externalid;
        }
        return $out;
    }

    /**
     * Normalise a SCIM User body (POST/PUT) into internal fields.
     *
     * @param array $body
     * @param bool  $require Require userName + email (POST)
     * @return array{username:?string,email:?string,firstname:?string,lastname:?string,active:?bool,externalid:?string}
     * @throws scim_exception
     */
    public static function from_scim(array $body, bool $require): array {
        $email = null;
        if (!empty($body['emails']) && is_array($body['emails'])) {
            $primary = null;
            foreach ($body['emails'] as $e) {
                if (!is_array($e) || empty($e['value'])) {
                    continue;
                }
                if (!empty($e['primary'])) {
                    $primary = (string) $e['value'];
                    break;
                }
                $primary = $primary ?? (string) $e['value'];
            }
            $email = $primary;
        }
        $username = isset($body['userName']) ? trim((string) $body['userName']) : null;
        // Entra commonly sends userName as the UPN; fall back to it as email when emails is absent.
        if ($email === null && $username !== null && validate_email($username)) {
            $email = $username;
        }
        $name = is_array($body['name'] ?? null) ? $body['name'] : [];
        $firstname = isset($name['givenName']) ? trim((string) $name['givenName']) : null;
        $lastname  = isset($name['familyName']) ? trim((string) $name['familyName']) : null;
        if (($firstname === null || $lastname === null) && !empty($body['displayName'])) {
            $parts = preg_split('/\s+/', trim((string) $body['displayName']), 2);
            $firstname = $firstname ?? ($parts[0] ?? null);
            $lastname  = $lastname ?? ($parts[1] ?? $parts[0] ?? null);
        }
        $active = array_key_exists('active', $body) ? self::to_bool($body['active']) : null;
        $externalid = isset($body['externalId']) ? \core_text::substr(trim((string) $body['externalId']), 0, 191) : null;

        if ($require) {
            if ($username === null || $username === '') {
                throw new scim_exception(400, 'userName is required.', 'invalidValue');
            }
            if ($email === null || !validate_email($email)) {
                throw new scim_exception(400, 'A valid primary email is required.', 'invalidValue');
            }
        } else if ($email !== null && !validate_email($email)) {
            throw new scim_exception(400, 'emails.value is not a valid address.', 'invalidValue');
        }
        return [
            'username' => $username, 'email' => $email, 'firstname' => $firstname,
            'lastname' => $lastname, 'active' => $active, 'externalid' => $externalid,
        ];
    }

    /**
     * Apply a PatchOp document to a working SCIM representation and return the
     * normalised field changes (same shape as from_scim, nulls = untouched).
     *
     * @param array $ops  The "Operations" array
     * @return array{username:?string,email:?string,firstname:?string,lastname:?string,active:?bool,externalid:?string,ignored:string[]}
     * @throws scim_exception
     */
    public static function apply_patch(array $ops): array {
        $changes = ['username' => null, 'email' => null, 'firstname' => null, 'lastname' => null,
                    'active' => null, 'externalid' => null, 'ignored' => []];
        foreach ($ops as $op) {
            if (!is_array($op)) {
                throw new scim_exception(400, 'Malformed PatchOp operation.', 'invalidSyntax');
            }
            $kind = strtolower((string) ($op['op'] ?? ''));
            if (!in_array($kind, ['add', 'replace', 'remove'], true)) {
                throw new scim_exception(400, 'Unsupported PATCH op "' . $kind . '".', 'invalidSyntax');
            }
            $path  = isset($op['path']) ? trim((string) $op['path']) : '';
            $value = $op['value'] ?? null;

            if ($path === '') {
                // No path: value is an object of attribute => value.
                if (!is_array($value)) {
                    throw new scim_exception(400, 'PATCH without path requires an object value.', 'invalidValue');
                }
                foreach ($value as $attr => $v) {
                    self::apply_one($changes, (string) $attr, $v, $kind);
                }
                continue;
            }
            self::apply_one($changes, $path, $value, $kind);
        }
        return $changes;
    }

    /**
     * @param array  $changes (by ref)
     * @param string $path
     * @param mixed  $value
     * @param string $kind
     * @return void
     */
    private static function apply_one(array &$changes, string $path, $value, string $kind): void {
        $p = strtolower(preg_replace('/^urn:ietf:params:scim:schemas:core:2\.0:user:/i', '', $path));
        $p = preg_replace('/\s+/', ' ', $p);
        switch (true) {
            case $p === 'active':
                $changes['active'] = $kind === 'remove' ? false : self::to_bool($value);
                return;
            case $p === 'username':
                if ($kind !== 'remove' && is_scalar($value)) {
                    $changes['username'] = trim((string) $value);
                }
                return;
            case $p === 'externalid':
                $changes['externalid'] = $kind === 'remove' ? '' : \core_text::substr(trim((string) $value), 0, 191);
                return;
            case $p === 'name.givenname':
                $changes['firstname'] = $kind === 'remove' ? '' : trim((string) $value);
                return;
            case $p === 'name.familyname':
                $changes['lastname'] = $kind === 'remove' ? '' : trim((string) $value);
                return;
            case $p === 'name':
                if (is_array($value)) {
                    if (isset($value['givenName'])) {
                        $changes['firstname'] = trim((string) $value['givenName']);
                    }
                    if (isset($value['familyName'])) {
                        $changes['lastname'] = trim((string) $value['familyName']);
                    }
                }
                return;
            case $p === 'displayname':
                if ($kind !== 'remove' && is_scalar($value)) {
                    $parts = preg_split('/\s+/', trim((string) $value), 2);
                    $changes['firstname'] = $changes['firstname'] ?? ($parts[0] ?? null);
                    $changes['lastname']  = $changes['lastname'] ?? ($parts[1] ?? null);
                }
                return;
            case $p === 'emails' || (bool) preg_match('/^emails(\[.*\])?(\.value)?$/', $p):
                if ($kind === 'remove') {
                    return; // Never blank an email from a remove op.
                }
                if (is_array($value)) {
                    // Either a list of email objects or one object.
                    $list = isset($value['value']) ? [$value] : $value;
                    foreach ($list as $e) {
                        if (is_array($e) && !empty($e['value'])) {
                            $changes['email'] = trim((string) $e['value']);
                            if (!empty($e['primary'])) {
                                break;
                            }
                        }
                    }
                } else if (is_scalar($value)) {
                    $changes['email'] = trim((string) $value);
                }
                return;
            default:
                $changes['ignored'][] = $path;
        }
    }

    /**
     * SCIM booleans arrive as true/false, "True"/"False", 1/0.
     *
     * @param mixed $v
     * @return bool
     */
    public static function to_bool($v): bool {
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    /**
     * @param int $ts
     * @return string ISO-8601 UTC
     */
    private static function iso(int $ts): string {
        return gmdate('Y-m-d\TH:i:s\Z', $ts > 0 ? $ts : time());
    }
}
