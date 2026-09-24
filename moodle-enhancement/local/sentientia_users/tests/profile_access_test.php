<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * N1 (UAT 2026-09-24) — who may read whose profile.
 *
 * On UAT an ordinary Airpay learner (/1) could open the full profile of ZEEA
 * (/177) and Public (/77) users by id, because profile.php checked only
 * require_login(). These tests lock in the rule that replaced that:
 *
 *   - own profile: always;
 *   - site admin: always (even with no open_path of their own);
 *   - same tenant root: allowed, including /1 vs /1/2/3 (colleague links);
 *   - different root: refused, including /1 vs /10 (the '/1%' prefix trap);
 *   - unresolvable viewer: nothing but their own profile (fail closed);
 *   - unresolvable, deleted or missing target: refused;
 *   - a missing id and an out-of-tenant id raise an IDENTICAL exception,
 *     so the refusal page cannot be used to enumerate other tenants' ids;
 *   - the edit-user dynamic form, which pre-fills a user's email, employee id
 *     and dates by id, applies the same rule to tenant editors;
 *   - the same form's supervisor autocomplete, whose label callback prints
 *     "<full name> (<email>)" for whatever id is posted in open_supervisorid,
 *     labels only users the editor may view, and renders a refused id and a
 *     missing id identically (review follow-up, 2026-09-24).
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_users\profile_access
 *
 * @group tenant_isolation
 */
final class profile_access_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Paths that must never resolve to a tenant root. */
    private const UNRESOLVABLE_PATHS = [null, '', '/', '/abc', 'abc/1', '/0'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /**
     * A live user whose open_path is exactly $path (null = column NULL).
     *
     * @param string|null $path
     * @param array $record Extra fields for the data generator (names, email).
     * @return \stdClass
     */
    private function user_at(?string $path, array $record = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $user->open_path = $path;
        return $user;
    }

    /**
     * Human-readable label for a path in assertion messages.
     *
     * @param string|null $path
     * @return string
     */
    private function label(?string $path): string {
        return $path === null ? 'NULL' : "'" . $path . "'";
    }

    /**
     * Run $fn and return the moodle_exception it throws; fail if it throws none.
     *
     * @param callable $fn
     * @return \moodle_exception
     */
    private function refusal_of(callable $fn): \moodle_exception {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('Expected a refusal, but access was granted.');
    }

    /**
     * Every observable property of a refusal, for byte-for-byte comparison.
     *
     * @param \moodle_exception $e
     * @return array
     */
    private function fingerprint(\moodle_exception $e): array {
        return [
            'class'     => get_class($e),
            'errorcode' => $e->errorcode,
            'module'    => $e->module,
            'message'   => $e->getMessage(),
            'a'         => $e->a,
            'link'      => $e->link,
            'debuginfo' => $e->debuginfo,
            'code'      => $e->getCode(),
        ];
    }

    /**
     * An id guaranteed not to belong to any user.
     *
     * @return int
     */
    private function missing_userid(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
    }

    /**
     * A non-admin holding the given capabilities at system context.
     *
     * @param string $path
     * @param string[] $caps Defaults to local/sentientia_users:edit only.
     * @return \stdClass
     */
    private function editor_at(string $path, array $caps = ['local/sentientia_users:edit']): \stdClass {
        $editor = $this->user_at($path);
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            role_change_permission($roleid, $sysctx, $cap, CAP_ALLOW);
        }
        role_assign($roleid, $editor->id, $sysctx->id);
        return $editor;
    }

    /**
     * Build the edit-user form the way core_form\external\dynamic_form::
     * execute() does for a SUBMITTED payload, and return the HTML it would
     * send back. The payload must fail validation, because that is the path
     * on which execute() re-renders the form instead of processing it.
     *
     * @param array $formdata The posted fields (userid, open_supervisorid...).
     * @return string Rendered form HTML.
     */
    private function render_submitted_edit_form(array $formdata): string {
        // confirm_sesskey() reads the request, not the ajax payload.
        $_POST['sesskey'] = sesskey();
        $formdata += [
            'sesskey' => sesskey(),
            '_qf__local_sentientia_users_form_edit_user' => 1,
        ];

        $form = new form\edit_user(null, null, 'post', '', [], true, $formdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_submitted(), 'The probe must be read as a submission');
        $this->assertFalse($form->is_validated(),
            'The probe must fail validation, the path on which dynamic_form re-renders the form');
        return $form->render();
    }

    /**
     * The <option> the supervisor autocomplete rendered for $id, with the id
     * itself masked, so the markup for two different ids can be compared.
     *
     * @param string $html Rendered form.
     * @param int $id
     * @return string
     */
    private function supervisor_option(string $html, int $id): string {
        $this->assertSame(1, preg_match('~<select[^>]*\bname="open_supervisorid"[^>]*>(.*?)</select>~s',
            $html, $select), 'The rendered form must contain the supervisor autocomplete');
        $this->assertSame(1, preg_match('~<option[^>]*\bvalue="' . $id . '"[^>]*>.*?</option>~s',
            $select[1], $option), "The posted supervisor id {$id} must be rendered as an option");
        return str_replace((string) $id, 'ID', $option[0]);
    }

    /**
     * A user whose names and email cannot occur anywhere else in a form.
     *
     * @param string|null $path
     * @param string $tag
     * @return \stdClass
     */
    private function probe_user_at(?string $path, string $tag): \stdClass {
        return $this->user_at($path, [
            'firstname' => $tag . 'probefirst',
            'lastname'  => $tag . 'probelast',
            'email'     => $tag . '.probe@example.com',
        ]);
    }

    /**
     * Assert that nothing identifying $user is in $html.
     *
     * @param \stdClass $user
     * @param string $html
     * @param string $case
     */
    private function assert_not_leaked(\stdClass $user, string $html, string $case): void {
        foreach ([$user->email, $user->firstname, $user->lastname] as $needle) {
            $this->assertStringNotContainsString($needle, $html,
                "{$case}: the rendered form must not carry '{$needle}'");
        }
    }

    // ─── Rule 1: own profile ────────────────────────────────────────────

    public function test_own_profile_is_always_viewable(): void {
        foreach (array_merge(['/1', '/1/2/3', '/77', '/177/5'], self::UNRESOLVABLE_PATHS) as $path) {
            $user = $this->user_at($path);
            $this->assertTrue(profile_access::can_view((int) $user->id, (int) $user->id),
                'A user must always see their own profile; open_path ' . $this->label($path));
        }
    }

    // ─── Rule 2: site admin ─────────────────────────────────────────────

    public function test_site_admin_can_view_every_tenant(): void {
        $admin = get_admin();
        // The stock admin has no open_path: rule 2 must win over rule 4.
        $this->assertTrue(is_siteadmin($admin->id));

        foreach (array_merge(['/1', '/1/2/3', '/77', '/177', '/10'], self::UNRESOLVABLE_PATHS) as $path) {
            $target = $this->user_at($path);
            $this->assertTrue(profile_access::can_view((int) $admin->id, (int) $target->id),
                'Site admin must see a target at ' . $this->label($path));
        }
    }

    public function test_site_admin_with_a_tenant_path_still_crosses_tenants(): void {
        global $DB;
        $admin = get_admin();
        $DB->set_field('user', 'open_path', '/1', ['id' => $admin->id]);

        $zeea = $this->user_at('/177');
        $this->assertTrue(profile_access::can_view((int) $admin->id, (int) $zeea->id));
    }

    // ─── Rule 3: same tenant root ───────────────────────────────────────

    public function test_same_tenant_root_is_viewable(): void {
        $pairs = [
            ['/1', '/1'],
            ['/1', '/1/2/3'],
            ['/1/2/3', '/1'],
            ['/1/2/3', '/1/9'],
            ['/77', '/77/4'],
            ['/177/5', '/177/6/7'],
        ];
        foreach ($pairs as [$vpath, $tpath]) {
            $viewer = $this->user_at($vpath);
            $target = $this->user_at($tpath);
            $this->assertTrue(profile_access::can_view((int) $viewer->id, (int) $target->id),
                "Viewer {$vpath} must see colleague {$tpath} (same tenant root)");
        }
    }

    public function test_cross_tenant_is_refused(): void {
        $pairs = [
            ['/1', '/177'],
            ['/1', '/77'],
            ['/177', '/1'],
            ['/77', '/1'],
            ['/77/4', '/1/2/3'],
            ['/1/2/3', '/177/5'],
        ];
        foreach ($pairs as [$vpath, $tpath]) {
            $viewer = $this->user_at($vpath);
            $target = $this->user_at($tpath);
            $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $target->id),
                "Viewer {$vpath} must NOT see {$tpath} (different tenant root)");
        }
    }

    /**
     * The '/1%' trap: a prefix match on the path string lets tenant 1 see
     * tenants 10, 100, 177... Roots are compared as integers, so it cannot.
     */
    public function test_prefix_lookalike_tenant_is_refused(): void {
        $pairs = [
            ['/1', '/10'],
            ['/1', '/10/2'],
            ['/1', '/100'],
            ['/1', '/177'],
            ['/10', '/1'],
            ['/17', '/177'],
            ['/1/2', '/12'],
        ];
        foreach ($pairs as [$vpath, $tpath]) {
            $viewer = $this->user_at($vpath);
            $target = $this->user_at($tpath);
            $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $target->id),
                "Viewer {$vpath} must NOT see {$tpath}: a shared string prefix is not a shared tenant");
        }
    }

    // ─── Rule 4: unresolvable viewer ────────────────────────────────────

    public function test_unresolvable_viewer_sees_only_their_own_profile(): void {
        $airpay = $this->user_at('/1');
        $alsounresolved = $this->user_at(null);

        foreach (self::UNRESOLVABLE_PATHS as $path) {
            $viewer = $this->user_at($path);
            $label = $this->label($path);

            $this->assertTrue(profile_access::can_view((int) $viewer->id, (int) $viewer->id),
                "Viewer at {$label} must still see their own profile");
            $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $airpay->id),
                "Viewer at {$label} must not see a /1 user (fail closed, never guess a tenant)");
            $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $alsounresolved->id),
                "Viewer at {$label} must not see another unresolvable user (two unknowns are not a match)");
        }
    }

    // ─── Rule 5: unresolvable target ────────────────────────────────────

    public function test_unresolvable_target_is_refused(): void {
        $viewer = $this->user_at('/1');
        foreach (self::UNRESOLVABLE_PATHS as $path) {
            $target = $this->user_at($path);
            $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $target->id),
                'A /1 viewer must not see a target at ' . $this->label($path));
        }
    }

    public function test_deleted_target_is_refused_to_peers_but_loadable_by_admin(): void {
        global $DB;
        $viewer = $this->user_at('/1');
        $gone = $this->user_at('/1');
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $this->assertFalse(profile_access::can_view((int) $viewer->id, (int) $gone->id),
            'A deleted colleague must be as unavailable as an id that never existed');

        $admin = get_admin();
        $this->assertTrue(profile_access::can_view((int) $admin->id, (int) $gone->id));
        $loaded = profile_access::get_viewable_user((int) $admin->id, (int) $gone->id, true);
        $this->assertSame((int) $gone->id, (int) $loaded->id);
    }

    public function test_nonsense_ids_are_refused(): void {
        $user = $this->user_at('/1');
        $this->assertFalse(profile_access::can_view(0, (int) $user->id), 'Not logged in');
        $this->assertFalse(profile_access::can_view((int) $user->id, 0));
        $this->assertFalse(profile_access::can_view((int) $user->id, -1));
        $this->assertFalse(profile_access::can_view(0, 0), 'Two zeros are not "own profile"');
    }

    // ─── No existence oracle ────────────────────────────────────────────

    public function test_missing_id_and_cross_tenant_id_refuse_identically(): void {
        global $DB;
        $viewer = $this->user_at('/1');
        $this->setUser($viewer);

        $zeea = $this->user_at('/177');
        $lookalike = $this->user_at('/10');
        $unresolved = $this->user_at(null);
        $deleted = $this->user_at('/1');
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $missing = $this->refusal_of(fn() => profile_access::get_viewable_user(
            (int) $viewer->id, $this->missing_userid()));
        $expected = $this->fingerprint($missing);

        // It is our string, properly resolved, and not core's raw-key failure.
        $this->assertSame('error_profilenotavailable', $missing->errorcode);
        $this->assertSame('local_sentientia_users', $missing->module);
        $this->assertSame(get_string('error_profilenotavailable', 'local_sentientia_users'),
            $missing->getMessage());
        $this->assertStringNotContainsString('[[', $missing->getMessage());
        $this->assertStringNotContainsString('nopermission', $missing->getMessage());
        $this->assertNotInstanceOf(\dml_missing_record_exception::class, $missing,
            'The record must not be loaded before the access check');

        $others = [
            'cross-tenant /177' => $zeea->id,
            'prefix look-alike /10' => $lookalike->id,
            'unresolvable target' => $unresolved->id,
            'deleted colleague' => $deleted->id,
        ];
        foreach ($others as $case => $targetid) {
            $refusal = $this->refusal_of(fn() => profile_access::get_viewable_user(
                (int) $viewer->id, (int) $targetid));
            $this->assertSame($expected, $this->fingerprint($refusal),
                "Refusal for {$case} must be indistinguishable from a missing id");

            $refusal = $this->refusal_of(fn() => profile_access::require_can_view(
                (int) $viewer->id, (int) $targetid));
            $this->assertSame($expected, $this->fingerprint($refusal),
                "require_can_view() for {$case} must raise the same refusal");
        }
    }

    public function test_get_viewable_user_returns_same_tenant_colleague(): void {
        $viewer = $this->user_at('/1');
        $colleague = $this->user_at('/1/2/3');

        $loaded = profile_access::get_viewable_user((int) $viewer->id, (int) $colleague->id);
        $this->assertSame((int) $colleague->id, (int) $loaded->id);
        $this->assertSame('/1/2/3', $loaded->open_path);
    }

    // ─── Edit-user dynamic form (pre-fills profile data by id) ──────────

    public function test_edit_form_refuses_out_of_tenant_and_missing_targets_identically(): void {
        $editor = $this->editor_at('/1');
        $this->setUser($editor);

        $zeea = $this->user_at('/177');
        $build = function (int $userid): void {
            new form\edit_user(null, null, 'post', '', [], true, ['userid' => $userid], true);
        };

        $cross = $this->refusal_of(fn() => $build((int) $zeea->id));
        $missing = $this->refusal_of(fn() => $build($this->missing_userid()));

        $this->assertSame('error_profilenotavailable', $cross->errorcode);
        $this->assertSame($this->fingerprint($missing), $this->fingerprint($cross),
            'The edit form must not tell a missing id from an out-of-tenant one');
    }

    public function test_edit_form_still_opens_for_same_tenant_target(): void {
        $editor = $this->editor_at('/1');
        $this->setUser($editor);
        $colleague = $this->user_at('/1/2/3');

        $form = new form\edit_user(null, null, 'post', '', [], true,
            ['userid' => (int) $colleague->id], true);
        $form->set_data_for_dynamic_submission();
        $this->assertInstanceOf(form\edit_user::class, $form);
    }

    // ─── Edit-user form: the supervisor label callback ──────────────────
    //
    // MoodleQuickForm_autocomplete::setValue() adds every posted value as an
    // option, and the label callback runs on each option at render time. So
    // posting open_supervisorid=<id> with one invalid field made the
    // re-rendered form print "<full name> (<email>)" for any id in any tenant,
    // and print no label for a missing id. The check on the form's own userid
    // does not stop it: the prober edits themselves (rule 1) or creates
    // (userid=0, no target to bound).

    public function test_supervisor_label_is_tenant_bounded_when_editing(): void {
        $editor = $this->editor_at('/1');
        $this->setUser($editor);
        $zeea = $this->probe_user_at('/177', 'zeea');
        $missingid = $this->missing_userid();

        // The editor opens their OWN record, which rule 1 always allows, and
        // blanks the email so validation fails and the form is re-rendered.
        $probe = fn(int $supervisorid): string => $this->render_submitted_edit_form([
            'userid' => (int) $editor->id,
            'open_supervisorid' => $supervisorid,
            'email' => '',
        ]);

        $crosshtml = $probe((int) $zeea->id);
        $this->assert_not_leaked($zeea, $crosshtml, 'Cross-tenant supervisor id, edit mode');
        $crossoption = $this->supervisor_option($crosshtml, (int) $zeea->id);
        $this->assertStringNotContainsString('data-html', $crossoption);

        $missingoption = $this->supervisor_option($probe($missingid), $missingid);
        $this->assertSame($missingoption, $crossoption,
            'An out-of-tenant supervisor id must render exactly like a missing one');
    }

    public function test_supervisor_label_is_tenant_bounded_when_creating(): void {
        // :create alone. check_access_for_dynamic_submission() has no target
        // to bound in create mode (userid=0), so only the callback stands in
        // the way.
        $creator = $this->editor_at('/1', ['local/sentientia_users:create']);
        $this->setUser($creator);
        $targets = [
            'ZEEA /177'      => $this->probe_user_at('/177', 'zeea'),
            'Public /77'     => $this->probe_user_at('/77', 'public'),
            'look-alike /10' => $this->probe_user_at('/10', 'lookalike'),
            'no tenant'      => $this->probe_user_at(null, 'notenant'),
        ];
        $missingid = $this->missing_userid();

        $probe = fn(int $supervisorid): string => $this->render_submitted_edit_form([
            'userid' => 0,
            'open_supervisorid' => $supervisorid,
            'email' => '',
            'firstname' => '',
            'lastname' => '',
        ]);

        $missingoption = $this->supervisor_option($probe($missingid), $missingid);
        $this->assertStringNotContainsString('data-html', $missingoption);

        foreach ($targets as $case => $target) {
            $html = $probe((int) $target->id);
            $this->assert_not_leaked($target, $html, "{$case} supervisor id, create mode");
            $this->assertSame($missingoption, $this->supervisor_option($html, (int) $target->id),
                "{$case}: must render exactly like a missing id");
        }
    }

    public function test_supervisor_label_still_renders_for_same_tenant_and_for_admin(): void {
        global $DB;
        $colleague = $this->probe_user_at('/1/2', 'colleague');
        $zeea = $this->probe_user_at('/177', 'zeea');
        $gone = $this->probe_user_at('/1', 'gone');
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $label = fn(\stdClass $u): string => $u->firstname . ' ' . $u->lastname . ' (' . $u->email . ')';

        // Same-tenant editor: a colleague keeps the label it had before.
        $editor = $this->editor_at('/1');
        $this->setUser($editor);
        $html = $this->render_submitted_edit_form([
            'userid' => (int) $editor->id,
            'open_supervisorid' => (int) $colleague->id,
            'email' => '',
        ]);
        $this->assertStringContainsString('data-html="' . $label($colleague) . '"',
            $this->supervisor_option($html, (int) $colleague->id),
            'A same-tenant supervisor must keep its "name (email)" label');

        // A deleted colleague gets no label, like a missing id.
        $html = $this->render_submitted_edit_form([
            'userid' => (int) $editor->id,
            'open_supervisorid' => (int) $gone->id,
            'email' => '',
        ]);
        $this->assert_not_leaked($gone, $html, 'Deleted same-tenant supervisor id');

        // Site admin: a user in any tenant is labelled (rule 2).
        $this->setAdminUser();
        $html = $this->render_submitted_edit_form([
            'userid' => 0,
            'open_supervisorid' => (int) $zeea->id,
            'email' => '',
            'firstname' => '',
            'lastname' => '',
        ]);
        $this->assertStringContainsString('data-html="' . $label($zeea) . '"',
            $this->supervisor_option($html, (int) $zeea->id),
            'A site admin must still see the label for a user in any tenant');
    }
}
