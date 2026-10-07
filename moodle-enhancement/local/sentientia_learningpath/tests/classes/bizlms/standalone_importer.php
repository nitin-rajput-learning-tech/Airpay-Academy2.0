<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\copies_files;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;

/**
 * The real learningplan importer with no dependencies, for a test registry.
 *
 * The real importer depends on the org and skills features (mapping doc, section 2), which live in other
 * plugins and have their own importers. A test registry holds only the importer under test, and the registry
 * refuses a dependency it does not know. This wrapper hands every call to the real importer except
 * depends(), so the tests run the real steps, the real sources and the real writes; only the run order
 * differs. A test that needs the real list asserts it on the real class.
 *
 * The wrapper implements the copies_files marker (IDN-04) and hands allowed_file_areas() to the real importer: the
 * runner decides by instanceof, so a wrapper without it would trip on the cover copy the real importer makes.
 *
 * It lives under tests/classes/bizlms, which the registry accepts for a test importer.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class standalone_importer implements importer, copies_files {

    /** @var importer */
    private importer $inner;

    /**
     * @param importer $inner The real importer.
     */
    public function __construct(importer $inner) {
        $this->inner = $inner;
    }

    public function feature(): string {
        return $this->inner->feature();
    }

    public function component(): string {
        return $this->inner->component();
    }

    public function requires_version(): int {
        return $this->inner->requires_version();
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        return $this->inner->sources();
    }

    public function declined_tables(): array {
        return $this->inner->declined_tables();
    }

    public function target_tables(): array {
        return $this->inner->target_tables();
    }

    public function core_writes(): array {
        return $this->inner->core_writes();
    }

    public function allowed_file_areas(): array {
        return $this->inner instanceof copies_files ? $this->inner->allowed_file_areas() : [];
    }

    public function tenant_columns(): array {
        return $this->inner->tenant_columns();
    }

    public function reasons(): array {
        return $this->inner->reasons();
    }

    public function decisions(): array {
        return $this->inner->decisions();
    }

    public function atomic(): bool {
        return $this->inner->atomic();
    }

    public function steps(): array {
        return $this->inner->steps();
    }

    public function preflight(context $ctx): preflight {
        return $this->inner->preflight($ctx);
    }

    public function verify(context $ctx): array {
        return $this->inner->verify($ctx);
    }

    public function finalise(context $ctx): void {
        $this->inner->finalise($ctx);
    }
}
