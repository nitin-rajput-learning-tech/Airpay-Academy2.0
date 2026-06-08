<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\form;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\org_manager;

/**
 * Create / edit an organisation node (dynamic_form).
 *
 * @package    local_sentientia_org
 */
class edit_org extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $orgid    = (int) ($this->optional_param('orgid', 0, PARAM_INT));
        $parentid = (int) ($this->optional_param('parentid', 0, PARAM_INT));

        $mform->addElement('hidden', 'orgid', $orgid);
        $mform->setType('orgid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_org'));

        $mform->addElement('text', 'fullname',
            get_string('org_fullname', 'local_sentientia_org'),
            ['size' => 50, 'maxlength' => 254, 'placeholder' => 'e.g. Airpay Payment Services']);
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname',
            get_string('org_shortname', 'local_sentientia_org'),
            ['size' => 30, 'maxlength' => 100, 'placeholder' => 'e.g. AirPay_Acquiring']);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addHelpButton('shortname', 'shortname', 'local_sentientia_org');

        $mform->addElement('textarea', 'description',
            get_string('description', 'local_sentientia_org'),
            ['rows' => 2, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Hierarchy ─────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_hierarchy', get_string('heading_hierarchy', 'local_sentientia_org'));

        if ($orgid === 0) {
            // Creating new org — pick parent.
            $parents = $this->get_parent_options();
            $mform->addElement('select', 'parentid',
                get_string('parent_org', 'local_sentientia_org'), $parents);
            $mform->setType('parentid', PARAM_INT);
            $mform->setDefault('parentid', $parentid);
            $mform->addHelpButton('parentid', 'parent_org', 'local_sentientia_org');
        } else {
            // Editing — show parent as read-only (move requires separate action).
            $existing = $this->get_existing_record($orgid);
            $parent_label = $existing && (int) $existing->parentid > 0
                ? \local_sentientia_org\org_manager::get_name((int) $existing->parentid)
                : get_string('top_level_tenant', 'local_sentientia_org');
            $mform->addElement('static', 'parent_static',
                get_string('parent_org', 'local_sentientia_org'),
                format_string($parent_label));
            $mform->addElement('hidden', 'parentid', $existing->parentid ?? 0);
            $mform->setType('parentid', PARAM_INT);
        }

        // ── Branding (per-tenant) ─────────────────────────────────────
        $mform->addElement('header', 'hdr_branding', get_string('heading_branding', 'local_sentientia_org'));
        $mform->addElement('static', 'branding_help', '',
            get_string('branding_help', 'local_sentientia_org'));

        $mform->addElement('text', 'brand_color',
            get_string('brand_color', 'local_sentientia_org'),
            ['size' => 10, 'maxlength' => 20, 'placeholder' => '#0066A7']);
        $mform->setType('brand_color', PARAM_TEXT);

        $mform->addElement('text', 'button_color',
            get_string('button_color', 'local_sentientia_org'),
            ['size' => 10, 'maxlength' => 20, 'placeholder' => '#0066A7']);
        $mform->setType('button_color', PARAM_TEXT);

        $mform->addElement('text', 'hover_color',
            get_string('hover_color', 'local_sentientia_org'),
            ['size' => 10, 'maxlength' => 20, 'placeholder' => '#004d80']);
        $mform->setType('hover_color', PARAM_TEXT);

        $themes = [
            ''           => '— Default —',
            'light'      => 'Light',
            'dark'       => 'Dark',
            'auto'       => 'Auto (follow system)',
        ];
        $mform->addElement('select', 'theme_scheme',
            get_string('theme_scheme', 'local_sentientia_org'), $themes);
        $mform->setType('theme_scheme', PARAM_ALPHA);

        // ── Visibility ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_visibility', get_string('heading_visibility', 'local_sentientia_org'));

        $visibility_options = [
            1 => get_string('visible_yes', 'local_sentientia_org'),
            0 => get_string('visible_no', 'local_sentientia_org'),
        ];
        $mform->addElement('select', 'visible',
            get_string('visible', 'local_sentientia_org'), $visibility_options);
        $mform->setType('visible', PARAM_INT);
        $mform->setDefault('visible', 1);

        $mform->addElement('text', 'sortorder',
            get_string('sortorder', 'local_sentientia_org'),
            ['size' => 5, 'maxlength' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
    }

    public function validation($data, $files) {
        $errors = [];
        if (empty(trim($data['fullname'] ?? ''))) {
            $errors['fullname'] = get_string('name_required', 'local_sentientia_org');
        }
        // Validate hex colors if provided.
        foreach (['brand_color', 'button_color', 'hover_color'] as $field) {
            if (!empty($data[$field]) && !preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($data[$field]))) {
                $errors[$field] = get_string('invalid_color', 'local_sentientia_org');
            }
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $orgid = (int) $data->orgid;

        if ($orgid === 0) {
            $newid = org_manager::create($data);
            return [
                'orgid'   => $newid,
                'message' => get_string('org_created', 'local_sentientia_org'),
            ];
        } else {
            org_manager::update($orgid, $data);
            return [
                'orgid'   => $orgid,
                'message' => get_string('org_updated', 'local_sentientia_org'),
            ];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        $orgid    = (int) ($this->optional_param('orgid', 0, PARAM_INT));
        $parentid = (int) ($this->optional_param('parentid', 0, PARAM_INT));

        if ($orgid === 0) {
            $this->set_data((object) [
                'orgid'    => 0,
                'parentid' => $parentid,
                'visible'  => 1,
                'sortorder' => 0,
            ]);
            return;
        }

        $r = $this->get_existing_record($orgid);
        $this->set_data((object) [
            'orgid'        => $r->id,
            'fullname'     => $r->fullname,
            'shortname'    => $r->shortname ?? '',
            'description'  => $r->description ?? '',
            'parentid'     => $r->parentid,
            'visible'      => (int) $r->visible,
            'sortorder'    => (int) $r->sortorder,
            'brand_color'  => $r->brand_color ?? '',
            'button_color' => $r->button_color ?? '',
            'hover_color'  => $r->hover_color ?? '',
            'theme_scheme' => $r->theme_scheme ?? '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_org/admin.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_org:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Get parent options. Tenants (depth=1) and depth=2 nodes can be parents
     * for new sub-orgs. Going deeper creates a too-deep hierarchy.
     */
    private function get_parent_options(): array {
        global $DB;
        $options = [0 => get_string('top_level_tenant', 'local_sentientia_org')];
        $orgs = $DB->get_records_select('local_sentientia_org',
            'depth <= 4', null, 'depth ASC, fullname ASC',
            'id, fullname, depth');
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }

    private function get_existing_record(int $orgid) {
        global $DB;
        return $DB->get_record('local_sentientia_org', ['id' => $orgid], '*', MUST_EXIST);
    }
}
