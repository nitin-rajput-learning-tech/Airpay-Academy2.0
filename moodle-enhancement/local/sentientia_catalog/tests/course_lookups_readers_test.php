<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-032 course_lookups reader fixes in the catalogue (mapping doc, section 6, "Code fixes" 1 and 2).
 *
 * 1. category_manager reads local_sentientia_course_category (the BizLMS local_custom_category read is gone), and its two
 *    list methods are bounded by the caller's tenant: a scoped caller sees the categories of their own tree only, a
 *    cross-tenant caller sees every one, a category with no tenant path is visible to cross-tenant callers only, and
 *    /10 is never mistaken for /1. The by-id lookups stay unscoped.
 * 2. catalog_manager::course_type_labels() labels a card from the EXPLODED comma list in open_identifiedas, behind the
 *    default-OFF flag sentientia.catalog.course_type_labels.enabled. OFF changes nothing.
 *
 * NOTE for the lead: the catalog version was bumped with this (2026100101), so PHPUnit must be re-initialised before it
 * runs. The categories table is created by local_sentientia_courses 2026100101.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_catalog\category_manager
 * @covers     \local_sentientia_catalog\catalog_manager::course_type_labels
 * @group      tenant_isolation
 */
final class course_lookups_readers_test extends \advanced_testcase {

    // Provisions {user}.open_path + {course}.open_path on the test DB, as the plugin's other suites do.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * A user in a tenant, or with no tenant at all.
     *
     * @param string|null $path open_path
     * @return \stdClass
     */
    private function make_user(?string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * A category row, as the import writes it.
     *
     * @param string $name
     * @param int $parent
     * @param string|null $tenantpath
     * @return int id
     */
    private function add_category(string $name, int $parent, ?string $tenantpath): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_course_category', (object) [
            'fullname' => $name, 'shortname' => strtolower(str_replace(' ', '', $name)), 'parentid' => $parent,
            'path' => '', 'depth' => $parent ? 2 : 1, 'tenant_path' => $tenantpath, 'usercreated' => 0, 'usermodified' => 0,
            'timecreated' => 1700000000, 'timemodified' => 1700000000]);
    }

    /**
     * @param \stdClass[] $categories
     * @return string[] Names, in the order returned.
     */
    private function names(array $categories): array {
        return array_values(array_map(static fn($c) => $c->fullname, $categories));
    }

    public function test_a_scoped_caller_lists_only_the_categories_of_their_own_tenant_tree(): void {
        $airpay = $this->add_category('Airpay root', 0, '/1');
        $this->add_category('Airpay child', $airpay, '/1');
        $this->add_category('Department', 0, '/1/50');
        $this->add_category('Public root', 0, '/77');
        $this->add_category('Ten', 0, '/10');
        $this->add_category('Pathless', 0, null);

        $this->setUser($this->make_user('/1/5'));
        $this->assertSame(['Airpay root', 'Department'], $this->names(category_manager::get_root_categories()),
            '/1 sees /1 and /1/50, never /10 (a sibling that shares a digit prefix), /77 or a pathless row');
        $this->assertSame(['Airpay child'], $this->names(category_manager::get_children($airpay)));

        $this->setUser($this->make_user('/77'));
        $this->assertSame(['Public root'], $this->names(category_manager::get_root_categories()));
        $this->assertSame([], category_manager::get_children($airpay), "another tenant's children are not listed");
    }

    public function test_a_cross_tenant_caller_lists_every_category_including_a_pathless_one(): void {
        $this->add_category('Airpay root', 0, '/1');
        $this->add_category('Public root', 0, '/77');
        $this->add_category('Pathless', 0, null);

        $this->setAdminUser();
        $this->assertSame(['Airpay root', 'Pathless', 'Public root'], $this->names(category_manager::get_root_categories()));
    }

    public function test_a_caller_with_no_resolvable_tenant_lists_nothing(): void {
        $this->add_category('Airpay root', 0, '/1');
        $this->add_category('Pathless', 0, null);

        $this->setUser($this->make_user(null));
        $this->assertSame([], category_manager::get_root_categories(), 'ADR-031: fail closed');
    }

    public function test_the_by_id_lookups_are_not_tenant_scoped(): void {
        $airpay = $this->add_category('Airpay root', 0, '/1');
        $child = $this->add_category('Airpay child', $airpay, '/1');

        // A course the viewer can already see names its category: the label is not hidden by the list rule.
        $this->setUser($this->make_user('/77'));
        $this->assertSame('Airpay root', category_manager::get_name($airpay));
        $this->assertSame($child, (int) category_manager::get($child)->id);
        $breadcrumb = category_manager::get_with_parent($child);
        $this->assertSame('Airpay child', $breadcrumb->name);
        $this->assertSame('Airpay root / Airpay child', $breadcrumb->full_path);
        $this->assertSame('', category_manager::get_name(0));
        $this->assertSame('', category_manager::get_name(987654), 'an id nobody has gives an empty name');
    }

    /**
     * Add the open_identifiedas column the vanilla test database lacks, and four course types: ids 1 to 3 are
     * E-Learning, Classroom and Learning Path; id 4 has an empty name.
     *
     * @return void
     */
    private function prepare_course_types(): void {
        global $DB;
        $dbman = $DB->get_manager();
        if (!isset($DB->get_columns('course')['open_identifiedas'])) {
            $dbman->add_field(new \xmldb_table('course'),
                new \xmldb_field('open_identifiedas', XMLDB_TYPE_CHAR, '255', null, null, null, null));
        }
        foreach ([1 => 'E-Learning', 2 => 'Classroom', 3 => 'Learning Path', 4 => ''] as $id => $name) {
            $DB->import_record('local_sentientia_course_type', (object) ['id' => $id, 'name' => $name,
                'shortname' => strtolower(str_replace(' ', '', $name)), 'tenant_path' => null, 'active' => 1, 'protected' => 0,
                'usercreated' => 0, 'usermodified' => 0, 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
        }
    }

    /**
     * @param string|null $identifiedas
     * @return int course id
     */
    private function course_with_types(?string $identifiedas): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $DB->set_field('course', 'open_identifiedas', $identifiedas, ['id' => $course->id]);
        return (int) $course->id;
    }

    private function set_flag(bool $on): void {
        $this->setAdminUser();
        \local_sentientia_platform\feature_flags::set(catalog_manager::FLAG_COURSE_TYPE_LABELS, 0, $on, null, 'phpunit');
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    public function test_the_course_type_flag_is_registered_and_off_by_default(): void {
        $this->resetAfterTest();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        $this->assertArrayHasKey(catalog_manager::FLAG_COURSE_TYPE_LABELS, $registry);
        $this->assertFalse((bool) $registry[catalog_manager::FLAG_COURSE_TYPE_LABELS]['default']);
    }

    public function test_with_the_flag_off_no_course_gets_a_type_label(): void {
        $this->prepare_course_types();
        $course = $this->course_with_types('1,3');
        $this->setAdminUser();
        $this->assertSame([], catalog_manager::course_type_labels([$course]));
    }

    public function test_the_label_is_built_from_the_exploded_open_identifiedas_list(): void {
        $this->prepare_course_types();
        $two = $this->course_with_types('1,3');
        $spaced = $this->course_with_types(' 3 , 1 ');
        $unknown = $this->course_with_types('99');
        $empty = $this->course_with_types('');
        $none = $this->course_with_types(null);
        $mixed = $this->course_with_types('2,x,4,3');
        $this->set_flag(true);

        $labels = catalog_manager::course_type_labels([$two, $spaced, $unknown, $empty, $none, $mixed, $two]);
        $this->assertSame('E-Learning, Learning Path', $labels[$two], 'a join on equality would lose every two-type course');
        $this->assertSame('Learning Path, E-Learning', $labels[$spaced], 'list order, whitespace ignored');
        $this->assertSame('Classroom, Learning Path', $labels[$mixed], 'a non-number and a type with no name are ignored');
        $this->assertArrayNotHasKey($unknown, $labels, 'a list that names no known type keeps the open_coursetype label');
        $this->assertArrayNotHasKey($empty, $labels);
        $this->assertArrayNotHasKey($none, $labels);
        $this->assertSame([], catalog_manager::course_type_labels([]));
    }

    public function test_the_card_shows_the_label_only_when_the_flag_is_on(): void {
        global $DB, $USER;
        $this->prepare_course_types();
        $course = $this->course_with_types('1,3');
        $DB->set_field('course', 'open_coursetype', 2, ['id' => $course]);

        $this->setAdminUser();
        $find = static function () use ($course, $USER): ?string {
            foreach (catalog_manager::get_courses((int) $USER->id)['courses'] as $card) {
                if ((int) $card['id'] === $course) {
                    return $card['type'];
                }
            }
            return null;
        };
        $this->assertSame('Classroom', $find(), 'OFF: the open_coursetype label, exactly as before');
        $this->set_flag(true);
        $this->assertSame('E-Learning, Learning Path', $find());
        $this->set_flag(false);
        $this->assertSame('Classroom', $find());
    }
}
