<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\scd;

defined('MOODLE_INTERNAL') || die();

/**
 * Validator for the Sentientia Canonical Course Document (SCD v0.1).
 *
 * ADR-034 D1, Stage 1 slice 1 (2026-11-12). A port of the reference
 * harness's validator, pilot/lib/schema_lite.py in
 * docs/research/2026-10-15-scorm-lxp/, which is the reference semantics:
 * the same JSON Schema subset, the same rule ids, levels, pointers and,
 * where practical, the same messages. Two passes:
 *
 *  1. Structure: the schema file (schema::load()) checked with the keyword
 *     subset schema_lite supports - type, const, enum, pattern, format
 *     (uuid, date-time, uri), minimum, maximum, minItems, maxItems,
 *     uniqueItems, minProperties, required, properties, patternProperties,
 *     additionalProperties, items, local $ref and oneOf (dispatched on the
 *     `type` const when every alternative declares one). The walk records
 *     the JSON Pointer of every i18n object it meets; R01 and R07 use them.
 *  2. The x-rules R01-R12. coverage() says, per rule, what this slice checks
 *     and what waits for the compiler (slice 2): R05's state-token budget
 *     needs the compiler's state-token encoder and R08's file existence
 *     needs the compiled media set.
 *
 * Deliberate differences from the harness. Stricter or more precise:
 *  - patterns run with PCRE's D modifier, so `$` does not accept a trailing
 *    newline (Python's `$` does: "b01\n" passed the id pattern there);
 *  - pointers keep the original list index when a list also holds a
 *    non-object element (the harness re-indexes the filtered list);
 *  - const and enum never treat true as 1.
 * Less strict, by design in this slice:
 *  - in the store context R09 only reports a document that is not yet
 *    approved or published, a rejected one included
 *    (the harness always validates for compilation; CONTEXT_COMPILE matches
 *    it);
 *  - R08 checks that a media file exists only when the caller passes
 *    media_known (the harness also looks for it on disk under media_root);
 *  - R05 checks the node cap only, not the state-token budget (coverage()).
 *
 * Validation never throws for a bad document: every finding is an issue in
 * the result, and a rule that meets a structure it cannot read reports
 * "rule could not be evaluated" under its own id, as the harness does.
 *
 * @package local_sentientia_authoring
 */
class validator {

    /** Validating a document to store it (drafts are fine). */
    public const CONTEXT_STORE = 'store';

    /** Validating a document to compile a package from it (R09 enforced). */
    public const CONTEXT_COMPILE = 'compile';

    /** The twelve rule ids declared in the schema's x-rules. */
    public const RULE_IDS = ['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08', 'R09', 'R10', 'R11', 'R12'];

    /** Recommended maximum words in one slide bullet (base language). */
    private const MAX_BULLET_WORDS = 8;

    /** R05: scenarios above this node count are rejected for SCORM 1.2. */
    private const SCORM12_MAX_SCENARIO_NODES = 60;

    /** R11: SCORM 1.2 cmi.interactions.n.student_response limit. */
    private const MAX_STUDENT_RESPONSE = 255;

    /** R06: true/false option ids SCORM writes as-is. */
    private const TRUEFALSE_ID_CONVENTION = ['t', 'f', 'true', 'false', '0', '1'];

    /** R03 default when course.rtl_langs is absent. */
    private const DEFAULT_RTL_LANGS = ['ar', 'ur', 'he', 'fa'];

    /** Same as the harness's PLACEHOLDER_SHA. */
    private const PLACEHOLDER_SHA = '0000000000000000000000000000000000000000000000000000000000000000';

    /** R10 id pattern (the schema's $defs/id). */
    private const ID_RE = '/^[A-Za-z0-9_-]{1,64}$/D';

    private const UUID_RE = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';
    private const DATETIME_RE = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/D';
    private const URI_RE = '/^[A-Za-z][A-Za-z0-9+.-]*:\S+$/D';

    /** @var array The decoded schema. */
    private $schema;

    /** @var validation_result Where findings go (swapped for oneOf trials). */
    private $result;

    /** @var array Options, see validate(). */
    private $options;

    /** @var array<string,array> Resolved $ref cache. */
    private $refs = [];

    /**
     * What this slice checks for each rule, and what waits for the compiler.
     *
     * level: 'full' = the harness's checks for the rule, all of them;
     *        'partial' = part of them, `waits` says which part and why.
     *
     * @return array<string,array{level:string,checks:string,waits:string}>
     */
    public static function coverage(): array {
        return [
            'schema' => ['level' => 'full', 'waits' => '',
                'checks' => 'Required fields, types, enums, patterns, formats, bounds, unknown properties and block '
                    . 'types against schema/sentientia-course.schema.json (the schema_lite keyword subset).'],
            'R01' => ['level' => 'full', 'waits' => '',
                'checks' => 'Every i18n object carries the base language; units still to translate are counted per '
                    . 'declared language.'],
            'R02' => ['level' => 'full', 'waits' => '',
                'checks' => 'course.langs includes base_lang and has no duplicates.'],
            'R03' => ['level' => 'full', 'waits' => 'Emitting dir="rtl" per language is the compiler and player (slice 2).',
                'checks' => 'Reports which declared languages will render right-to-left (report only, as in the '
                    . 'harness: the rule cannot fail).'],
            'R04' => ['level' => 'full', 'waits' => '',
                'checks' => 'Graph integrity: every edge and info "next" resolves, the start node exists, every node '
                    . 'is reachable from start, every node can reach a terminal, decision nodes have 2 or more edges, '
                    . 'terminal nodes have none and carry terminal_outcome, info nodes use next, no cycles unless '
                    . 'allow_cycles.'],
            'R05' => ['level' => 'partial',
                'waits' => 'The worst-case cmi.suspend_data token (3,500 characters before compression) is measured '
                    . 'by the state-token encoder that ships with the compiler and player (slice 2).',
                'checks' => 'The SCORM 1.2 cap of 60 nodes per scenario (error when scorm12 is a target standard, '
                    . 'warning otherwise).'],
            'R06' => ['level' => 'full', 'waits' => '',
                'checks' => 'multichoice exactly one correct, multiresponse one or more, truefalse exactly two options '
                    . 'and one correct, match two or more pairs; option and pair ids unique within a question.'],
            'R07' => ['level' => 'full', 'waits' => '',
                'checks' => 'Protected terms present in the base text appear verbatim (or as the per-language '
                    . 'override) in every translation.'],
            'R08' => ['level' => 'partial',
                'waits' => 'That every referenced file exists is checked against the package by the compiler '
                    . '(slice 2); here only when the caller passes the registered asset paths (media_known).',
                'checks' => 'Every media path is a relative path inside the package: no leading slash, drive letter, '
                    . 'backslash or "..".'],
            'R09' => ['level' => 'full', 'waits' => '',
                'checks' => 'In the compile context: approved or published, or draft/in_review with preview. In the '
                    . 'store context a draft is allowed and only reported.'],
            'R10' => ['level' => 'full', 'waits' => '',
                'checks' => 'Block, character, node, edge, question and rubric ids are URL-safe and unique across the '
                    . 'document; option and pair ids are URL-safe; a node\'s character is declared.'],
            'R11' => ['level' => 'full', 'waits' => '',
                'checks' => 'The longest id-only student_response a question can produce stays within 255 characters.'],
            'R12' => ['level' => 'full', 'waits' => '',
                'checks' => 'course.source.pii_scan.status is clean or redacted (blocked halts generation).'],
        ];
    }

    /**
     * Validate a course document.
     *
     * @param mixed $doc     The decoded document (objects as \stdClass, see
     *                       document::decode()), or its JSON text.
     * @param array $options context   => CONTEXT_STORE (default) | CONTEXT_COMPILE
     *                       preview   => bool, compile context only (default false)
     *                       standards => string[] target standards for R05
     *                                    (default scorm12, scorm2004, cmi5)
     *                       media_known => string[]|null registered asset
     *                                    paths for R08 existence (default null:
     *                                    existence not checked)
     * @return validation_result
     * @throws \moodle_exception err_scd_invalidjson when given text that is not JSON
     */
    public static function validate($doc, array $options = []): validation_result {
        if (is_string($doc)) {
            $doc = document::decode($doc);
        }
        $validator = new self(schema::load(), $options);
        return $validator->run($doc);
    }

    /**
     * @param array $schema
     * @param array $options
     */
    private function __construct(array $schema, array $options) {
        $this->schema = $schema;
        $this->options = $options + [
            'context'     => self::CONTEXT_STORE,
            'preview'     => false,
            'standards'   => ['scorm12', 'scorm2004', 'cmi5'],
            'media_known' => null,
        ];
        $this->result = new validation_result();
    }

    /**
     * @param mixed $doc
     * @return validation_result
     */
    private function run($doc): validation_result {
        $res = $this->result;
        if (!$doc instanceof \stdClass) {
            $res->add('error', 'schema', '/', 'document root must be an object, got ' . self::describe($doc));
            $res->rulestatus['schema'] = 'fail';
            return $res;
        }
        $this->walk($doc, $this->schema, '');

        $rules = [
            'R01'    => function () use ($doc) { $this->rule_r01($doc); },
            'R02'    => function () use ($doc) { $this->rule_r02($doc); },
            'R03'    => function () use ($doc) { $this->rule_r03($doc); },
            'R04'    => function () use ($doc) { $this->rule_r04($doc); },
            'R05'    => function () use ($doc) { $this->rule_r05($doc); },
            'R06'    => function () use ($doc) { $this->rule_r06($doc); },
            'R07'    => function () use ($doc) { $this->rule_r07($doc); },
            'R08'    => function () use ($doc) { $this->rule_r08($doc); },
            'R09'    => function () use ($doc) { $this->rule_r09($doc); },
            'R10'    => function () use ($doc) { $this->rule_r10($doc); },
            'R11'    => function () use ($doc) { $this->rule_r11($doc); },
            'R12'    => function () use ($doc) { $this->rule_r12($doc); },
            'slides' => function () use ($doc) { $this->check_slides($doc); },
            'source' => function () use ($doc) { $this->check_source($doc); },
        ];
        foreach ($rules as $name => $rule) {
            try {
                $rule();
            } catch (\Throwable $e) {
                // A rule must report, never crash the run (as in the harness).
                $res->add('error', $name, '/', 'rule could not be evaluated because of structural errors: '
                    . get_class($e) . ': ' . $e->getMessage());
            }
        }
        foreach (self::RULE_IDS as $rule) {
            $levels = array_column($res->by_rule($rule), 'level');
            $res->rulestatus[$rule] = in_array('error', $levels, true) ? 'fail'
                : (in_array('warning', $levels, true) ? 'warn' : 'pass');
        }
        $res->rulestatus['schema'] = in_array('schema', array_column($res->errors(), 'rule'), true) ? 'fail' : 'pass';
        return $res;
    }

    // ---------------------------------------------------------------------
    // Pass 1: the JSON Schema subset.
    // ---------------------------------------------------------------------

    /**
     * @param mixed  $inst
     * @param array  $sub
     * @param string $ptr
     * @return void
     */
    private function walk($inst, array $sub, string $ptr): void {
        if (isset($sub['$ref'])) {
            $ref = (string) $sub['$ref'];
            if ($ref === '#/$defs/i18n') {
                $this->result->i18npointers[] = $ptr;
            }
            $this->walk($inst, $this->resolve($ref), $ptr);
            $siblings = array_diff_key($sub, ['$ref' => 1, 'description' => 1, 'default' => 1]);
            if (!empty($siblings)) {
                $this->walk($inst, $siblings, $ptr);
            }
            return;
        }
        if (array_key_exists('type', $sub) && !$this->check_type($inst, $sub['type'], $ptr)) {
            return;
        }
        if (array_key_exists('const', $sub) && !self::json_equal($inst, $sub['const'])) {
            $this->err($ptr, 'must equal ' . self::literal($sub['const']) . ', got ' . self::describe($inst));
        }
        if (array_key_exists('enum', $sub) && is_array($sub['enum'])) {
            $found = false;
            foreach ($sub['enum'] as $candidate) {
                if (self::json_equal($inst, $candidate)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->err($ptr, 'must be one of [' . implode(', ', array_map([self::class, 'literal'], $sub['enum']))
                    . '], got ' . self::describe($inst));
            }
        }
        if (is_string($inst)) {
            $this->check_string($inst, $sub, $ptr);
        }
        if (is_int($inst) || is_float($inst)) {
            if (isset($sub['minimum']) && $inst < $sub['minimum']) {
                $this->err($ptr, "must be >= {$sub['minimum']}, got {$inst}");
            }
            if (isset($sub['maximum']) && $inst > $sub['maximum']) {
                $this->err($ptr, "must be <= {$sub['maximum']}, got {$inst}");
            }
        }
        if (is_array($inst)) {
            $this->check_array($inst, $sub, $ptr);
        }
        if ($inst instanceof \stdClass) {
            $this->check_object($inst, $sub, $ptr);
        }
        if (isset($sub['oneOf']) && is_array($sub['oneOf'])) {
            $this->check_one_of($inst, $sub['oneOf'], $ptr);
        }
    }

    /**
     * Resolve a local `#/...` reference.
     *
     * @param string $ref
     * @return array
     * @throws \coding_exception for a reference the schema cannot resolve (a schema bug)
     */
    private function resolve(string $ref): array {
        if (isset($this->refs[$ref])) {
            return $this->refs[$ref];
        }
        if (strpos($ref, '#/') !== 0) {
            throw new \coding_exception("unsupported \$ref {$ref}: only local '#/' references are supported");
        }
        $node = $this->schema;
        foreach (explode('/', substr($ref, 2)) as $raw) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $raw);
            if (!is_array($node) || !array_key_exists($key, $node)) {
                throw new \coding_exception("unresolvable \$ref {$ref}");
            }
            $node = $node[$key];
        }
        if (!is_array($node)) {
            throw new \coding_exception("\$ref {$ref} does not point at a schema object");
        }
        return $this->refs[$ref] = $node;
    }

    /**
     * @param mixed        $inst
     * @param string|array $kind
     * @param string       $ptr
     * @return bool
     */
    private function check_type($inst, $kind, string $ptr): bool {
        $kinds = is_array($kind) ? $kind : [$kind];
        foreach ($kinds as $k) {
            if (self::is_type($inst, (string) $k)) {
                return true;
            }
        }
        $this->err($ptr, 'expected ' . implode(' or ', $kinds) . ', got ' . self::describe($inst));
        return false;
    }

    /**
     * @param string $inst
     * @param array  $sub
     * @param string $ptr
     * @return void
     */
    private function check_string(string $inst, array $sub, string $ptr): void {
        if (isset($sub['pattern']) && !self::matches((string) $sub['pattern'], $inst)) {
            $this->err($ptr, self::describe($inst) . " does not match pattern {$sub['pattern']}");
        }
        $format = $sub['format'] ?? null;
        if ($format === 'uuid' && !preg_match(self::UUID_RE, $inst)) {
            $this->err($ptr, self::describe($inst) . ' is not a UUID');
        } else if ($format === 'date-time' && !preg_match(self::DATETIME_RE, $inst)) {
            $this->err($ptr, self::describe($inst) . ' is not an RFC 3339 date-time (e.g. 2026-10-15T06:30:00Z)');
        } else if ($format === 'uri' && !preg_match(self::URI_RE, $inst)) {
            $this->err($ptr, self::describe($inst) . ' is not an absolute URI');
        }
    }

    /**
     * @param array  $inst
     * @param array  $sub
     * @param string $ptr
     * @return void
     */
    private function check_array(array $inst, array $sub, string $ptr): void {
        $count = count($inst);
        if (isset($sub['minItems']) && $count < $sub['minItems']) {
            $this->err($ptr, "must have at least {$sub['minItems']} item(s), has {$count}");
        }
        if (isset($sub['maxItems']) && $count > $sub['maxItems']) {
            $this->err($ptr, "must have at most {$sub['maxItems']} item(s), has {$count}");
        }
        if (!empty($sub['uniqueItems'])) {
            $seen = [];
            foreach ($inst as $index => $item) {
                $key = document::canonical($item);
                if (isset($seen[$key])) {
                    $this->err(document::join($ptr, $index),
                        'duplicate item ' . self::describe($item) . " (first at index {$seen[$key]})");
                } else {
                    $seen[$key] = $index;
                }
            }
        }
        if (isset($sub['items']) && is_array($sub['items']) && !array_is_list($sub['items'])) {
            foreach ($inst as $index => $item) {
                $this->walk($item, $sub['items'], document::join($ptr, $index));
            }
        }
    }

    /**
     * @param \stdClass $inst
     * @param array     $sub
     * @param string    $ptr
     * @return void
     */
    private function check_object(\stdClass $inst, array $sub, string $ptr): void {
        $members = get_object_vars($inst);
        if (isset($sub['minProperties']) && count($members) < $sub['minProperties']) {
            $this->err($ptr, "must have at least {$sub['minProperties']} propert(y/ies), has " . count($members));
        }
        foreach ($sub['required'] ?? [] as $key) {
            if (!property_exists($inst, (string) $key)) {
                $this->err($ptr, "missing required property '{$key}'");
            }
        }
        $props = isset($sub['properties']) && is_array($sub['properties']) ? $sub['properties'] : [];
        $patterns = isset($sub['patternProperties']) && is_array($sub['patternProperties'])
            ? $sub['patternProperties'] : [];
        foreach ($members as $key => $value) {
            $key = (string) $key;
            $child = document::join($ptr, $key);
            $matched = false;
            if (array_key_exists($key, $props) && is_array($props[$key])) {
                $matched = true;
                $this->walk($value, $props[$key], $child);
            }
            foreach ($patterns as $pattern => $pschema) {
                if (is_array($pschema) && self::matches((string) $pattern, $key)) {
                    $matched = true;
                    $this->walk($value, $pschema, $child);
                }
            }
            if (!$matched && array_key_exists('additionalProperties', $sub) && $sub['additionalProperties'] === false) {
                $allowed = $props ? implode(', ', array_map('strval', array_keys($props))) : 'pattern keys only';
                $this->err($child, "unexpected property '{$key}' (allowed: {$allowed})");
            }
        }
    }

    /**
     * oneOf: dispatch on the `type` const when every alternative declares one
     * (the block list), otherwise exactly one alternative must validate.
     *
     * @param mixed  $inst
     * @param array  $alternatives
     * @param string $ptr
     * @return void
     */
    private function check_one_of($inst, array $alternatives, string $ptr): void {
        $resolved = [];
        foreach ($alternatives as $alt) {
            $resolved[] = (is_array($alt) && isset($alt['$ref'])) ? $this->resolve((string) $alt['$ref']) : (array) $alt;
        }
        $discriminators = array_map(static function (array $alt) {
            return $alt['properties']['type']['const'] ?? null;
        }, $resolved);
        if (count(array_filter($discriminators, 'is_string')) === count($discriminators)) {
            if (!$inst instanceof \stdClass) {
                $this->err($ptr, "expected an object with a 'type' discriminator, got " . self::describe($inst));
                return;
            }
            $kind = property_exists($inst, 'type') ? $inst->type : null;
            $index = array_search($kind, $discriminators, true);
            if ($index === false) {
                $this->err($ptr, 'unknown type ' . self::describe($kind) . '; expected one of ['
                    . implode(', ', $discriminators) . ']');
                return;
            }
            $this->walk($inst, $resolved[$index], $ptr);
            return;
        }
        $passing = 0;
        $outer = $this->result;
        foreach ($resolved as $alt) {
            $this->result = new validation_result();
            $this->walk($inst, $alt, $ptr);
            if (empty($this->result->issues)) {
                $passing++;
            }
        }
        $this->result = $outer;
        if ($passing !== 1) {
            $this->err($ptr, "must match exactly one alternative, matched {$passing}");
        }
    }

    /**
     * @param string $ptr
     * @param string $message
     * @return void
     */
    private function err(string $ptr, string $message): void {
        $this->result->add('error', 'schema', $ptr, $message);
    }

    // ---------------------------------------------------------------------
    // Pass 2: the x-rules.
    // ---------------------------------------------------------------------

    /**
     * R01: every i18n object carries the base language.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r01(\stdClass $doc): void {
        $res = $this->result;
        $course = self::course($doc);
        $base = self::base_lang($course);
        $langs = self::strings(self::get($course, 'langs', []));
        foreach ($this->i18n_objects($doc) as [$pointer, $obj]) {
            if (!property_exists($obj, $base)) {
                $present = array_map('strval', array_keys(get_object_vars($obj)));
                sort($present);
                $res->add('error', 'R01', $pointer, "i18n object is missing the base language '{$base}' (present: ["
                    . implode(', ', $present) . '])');
            }
            foreach ($langs as $lang) {
                if (!property_exists($obj, $lang)) {
                    $res->pending[$lang] = ($res->pending[$lang] ?? 0) + 1;
                }
            }
        }
        foreach ($langs as $lang) {
            $res->pending[$lang] = $res->pending[$lang] ?? 0;
        }
        $pending = array_filter($res->pending);
        if ($pending) {
            $parts = [];
            foreach ($pending as $lang => $count) {
                $parts[] = "{$lang}: {$count}";
            }
            $res->add('info', 'R01', '/course/langs', 'units pending translation per language: {' . implode(', ', $parts) . '}');
        }
    }

    /**
     * R02: course.langs includes base_lang, no duplicates.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r02(\stdClass $doc): void {
        $course = self::course($doc);
        $base = self::get($course, 'base_lang');
        $langs = self::get($course, 'langs', []);
        $langs = is_array($langs) ? $langs : [];
        if (!in_array($base, $langs, true)) {
            $this->result->add('error', 'R02', '/course/langs', "must include base_lang '" . self::text($base) . "'");
        }
        $duplicates = array_keys(array_filter(array_count_values(self::strings($langs)), static function (int $n): bool {
            return $n > 1;
        }));
        if ($duplicates) {
            $duplicates = array_map('strval', $duplicates);
            sort($duplicates);
            $this->result->add('error', 'R02', '/course/langs', 'duplicate language tags: [' . implode(', ', $duplicates) . ']');
        }
    }

    /**
     * R03: report which declared languages render right-to-left.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r03(\stdClass $doc): void {
        $course = self::course($doc);
        $rtl = self::get($course, 'rtl_langs', self::DEFAULT_RTL_LANGS);
        $rtl = is_array($rtl) ? self::strings($rtl) : self::DEFAULT_RTL_LANGS;
        $declared = [];
        foreach (self::strings(self::get($course, 'langs', [])) as $lang) {
            if (in_array($lang, $rtl, true) || in_array(explode('-', $lang)[0], $rtl, true)) {
                $declared[] = $lang;
            }
        }
        $this->result->add('info', 'R03', '/course/rtl_langs', 'dir="rtl" will be emitted for: '
            . ($declared ? '[' . implode(', ', $declared) . ']' : 'none'));
    }

    /**
     * R04: scenario graph integrity.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r04(\stdClass $doc): void {
        $res = $this->result;
        foreach (self::blocks($doc) as $bi => $block) {
            if (self::get($block, 'type') !== 'scenario') {
                continue;
            }
            $bp = "/blocks/{$bi}";
            $nodeslist = self::objects(self::get($block, 'nodes', []));
            $nodes = [];
            foreach ($nodeslist as $node) {
                $id = self::get($node, 'id');
                if (is_string($id)) {
                    $nodes[$id] = $node;
                }
            }
            $start = self::get($block, 'start');
            $startok = is_string($start) && isset($nodes[$start]);
            if (!$startok) {
                $res->add('error', 'R04', "{$bp}/start", "start node '" . self::text($start)
                    . "' does not exist in this scenario");
            }
            $adjacency = [];
            $terminals = [];
            foreach ($nodeslist as $ni => $node) {
                $np = "{$bp}/nodes/{$ni}";
                $nid = self::get($node, 'id');
                $label = self::text($nid);
                $kind = self::get($node, 'kind');
                $edges = self::get($node, 'edges', []);
                $edges = is_array($edges) ? $edges : [];
                $targets = [];
                foreach ($edges as $ei => $edge) {
                    $target = $edge instanceof \stdClass ? self::get($edge, 'to') : null;
                    if (!is_string($target) || !isset($nodes[$target])) {
                        $edgelabel = $edge instanceof \stdClass ? self::text(self::get($edge, 'id')) : (string) $ei;
                        $res->add('error', 'R04', "{$np}/edges/{$ei}/to", "edge '{$edgelabel}' points at unknown node '"
                            . self::text($target) . "'");
                    } else {
                        $targets[] = $target;
                    }
                }
                $hasnext = self::truthy(self::get($node, 'next'));
                if ($kind === 'decision' && count($edges) < 2) {
                    $res->add('error', 'R04', "{$np}/edges", "decision node '{$label}' must have >= 2 edges, has "
                        . count($edges));
                }
                if ($kind === 'terminal') {
                    $terminals[] = $nid;
                    if ($edges) {
                        $res->add('error', 'R04', "{$np}/edges", "terminal node '{$label}' must have 0 edges, has "
                            . count($edges));
                    }
                    if ($hasnext) {
                        $res->add('error', 'R04', "{$np}/next", "terminal node '{$label}' must not declare 'next'");
                    }
                    if (!self::truthy(self::get($node, 'terminal_outcome'))) {
                        $res->add('error', 'R04', "{$np}/terminal_outcome", "terminal node '{$label}' requires "
                            . 'terminal_outcome (success|partial|failure)');
                    }
                }
                if ($kind === 'info') {
                    $next = self::get($node, 'next');
                    if (!self::truthy($next)) {
                        $res->add('error', 'R04', "{$np}/next", "info node '{$label}' must declare the single following "
                            . "node in 'next'");
                    } else if (!is_string($next) || !isset($nodes[$next])) {
                        $res->add('error', 'R04', "{$np}/next", "info node '{$label}' continues to unknown node '"
                            . self::text($next) . "'");
                    } else {
                        $targets[] = $next;
                    }
                    if ($edges) {
                        $res->add('error', 'R04', "{$np}/edges", "info node '{$label}' must use 'next', not edges");
                    }
                }
                if ($kind === 'decision' && $hasnext) {
                    $res->add('error', 'R04', "{$np}/next", "decision node '{$label}' must branch through edges, not 'next'");
                }
                if (is_string($nid)) {
                    $adjacency[$nid] = $targets;
                }
            }
            if (!$terminals) {
                $res->add('error', 'R04', "{$bp}/nodes", 'scenario has no terminal node');
            }
            if (!$startok) {
                continue;
            }
            $reachable = self::bfs($start, $adjacency);
            foreach (array_keys($nodes) as $nid) {
                if (!isset($reachable[$nid])) {
                    $res->add('error', 'R04', "{$bp}/nodes", "node '{$nid}' is not reachable from start '{$start}'");
                }
            }
            $reverse = [];
            foreach (array_keys($nodes) as $nid) {
                $reverse[$nid] = [];
            }
            foreach ($adjacency as $src => $targets) {
                foreach ($targets as $target) {
                    $reverse[$target][] = (string) $src;
                }
            }
            $canfinish = [];
            foreach ($terminals as $terminal) {
                if (is_string($terminal)) {
                    $canfinish += self::bfs($terminal, $reverse);
                }
            }
            foreach (array_keys($nodes) as $nid) {
                if (!isset($canfinish[$nid])) {
                    $res->add('error', 'R04', "{$bp}/nodes", "no path from node '{$nid}' reaches a terminal node");
                }
            }
            $cycle = self::find_cycle($start, $adjacency);
            if ($cycle && !self::truthy(self::get($block, 'allow_cycles', false))) {
                $res->add('error', 'R04', "{$bp}/nodes", 'cycle detected (' . implode(' -> ', $cycle)
                    . '); set allow_cycles=true to permit it');
            } else if ($cycle) {
                $res->add('warning', 'R04', "{$bp}/nodes", 'cycle permitted by allow_cycles: ' . implode(' -> ', $cycle));
            }
        }
    }

    /**
     * R05 (partial): the SCORM 1.2 node cap. The worst-case state-token
     * length needs the compiler's encoder (slice 2).
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r05(\stdClass $doc): void {
        $standards = is_array($this->options['standards']) ? $this->options['standards'] : [];
        $level = in_array('scorm12', $standards, true) ? 'error' : 'warning';
        foreach (self::blocks($doc) as $block) {
            if (self::get($block, 'type') !== 'scenario') {
                continue;
            }
            $nodes = self::get($block, 'nodes', []);
            $count = is_array($nodes) ? count($nodes) : 0;
            if ($count > self::SCORM12_MAX_SCENARIO_NODES) {
                $this->result->add($level, 'R05', '/blocks', "scenario '" . self::text(self::get($block, 'id'))
                    . "' has {$count} nodes > " . self::SCORM12_MAX_SCENARIO_NODES . '; rejected for SCORM 1.2 output');
            }
        }
        $this->result->add('info', 'R05', '/blocks', 'worst-case suspend_data token not measured here: the 3,500-character '
            . 'pre-compression budget is checked by the compiler (Stage 1 slice 2)');
    }

    /**
     * R06: question-type invariants.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r06(\stdClass $doc): void {
        $res = $this->result;
        foreach (self::blocks($doc) as $bi => $block) {
            if (self::get($block, 'type') !== 'quiz') {
                continue;
            }
            foreach (self::objects(self::get($block, 'questions', [])) as $qi => $question) {
                $qp = "/blocks/{$bi}/questions/{$qi}";
                $qid = self::text(self::get($question, 'id'));
                $kind = self::get($question, 'type');
                $options = array_values(self::objects(self::get($question, 'options', [])));
                $pairs = array_values(self::objects(self::get($question, 'pairs', [])));
                $correct = 0;
                foreach ($options as $option) {
                    if (self::get($option, 'correct') === true) {
                        $correct++;
                    }
                }
                $optionids = array_map(static function (\stdClass $o) {
                    return self::get($o, 'id');
                }, $options);
                foreach (self::duplicates($optionids) as $dup) {
                    $res->add('error', 'R06', "{$qp}/options", "option id '{$dup}' is not unique within question '{$qid}'");
                }
                $pairids = array_map(static function (\stdClass $p) {
                    return self::get($p, 'id');
                }, $pairs);
                foreach (self::duplicates($pairids) as $dup) {
                    $res->add('error', 'R06', "{$qp}/pairs", "pair id '{$dup}' is not unique within question '{$qid}'");
                }
                $n = count($options);
                if ($kind === 'multichoice') {
                    if ($n < 2) {
                        $res->add('error', 'R06', "{$qp}/options", 'multichoice needs >= 2 options');
                    }
                    if ($correct !== 1) {
                        $res->add('error', 'R06', "{$qp}/options", "multichoice must have exactly one correct option, has {$correct}");
                    }
                } else if ($kind === 'multiresponse') {
                    if ($n < 2) {
                        $res->add('error', 'R06', "{$qp}/options", 'multiresponse needs >= 2 options');
                    }
                    if ($correct < 1) {
                        $res->add('error', 'R06', "{$qp}/options", 'multiresponse must have >= 1 correct option, has 0');
                    }
                } else if ($kind === 'truefalse') {
                    if ($n !== 2) {
                        $res->add('error', 'R06', "{$qp}/options", "truefalse must have exactly 2 options, has {$n}");
                    }
                    if ($correct !== 1) {
                        $res->add('error', 'R06', "{$qp}/options", "truefalse must have exactly one correct option, has {$correct}");
                    }
                    foreach ($optionids as $oid) {
                        if (is_string($oid) && !in_array(strtolower($oid), self::TRUEFALSE_ID_CONVENTION, true)) {
                            $res->add('warning', 'R06', "{$qp}/options", "truefalse option id '{$oid}' is written as-is to "
                                . 'cmi.interactions; SCORM expects t/f (1.2) or true/false (2004)');
                        }
                    }
                } else if ($kind === 'match') {
                    if (count($pairs) < 2) {
                        $res->add('error', 'R06', "{$qp}/pairs", 'match needs >= 2 pairs, has ' . count($pairs));
                    }
                    if ($options) {
                        $res->add('warning', 'R06', "{$qp}/options", "match questions use 'pairs'; 'options' are ignored");
                    }
                }
                if (in_array($kind, ['multichoice', 'multiresponse', 'truefalse'], true) && !$options) {
                    $res->add('error', 'R06', "{$qp}/options", self::text($kind) . " question '{$qid}' has no options");
                }
            }
        }
    }

    /**
     * R07: protected terms survive translation.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r07(\stdClass $doc): void {
        $res = $this->result;
        $course = self::course($doc);
        $base = self::base_lang($course);
        $terms = array_values(array_filter(self::strings(self::get($course, 'protected_terms', [])), 'strlen'));
        $overrides = self::get($course, 'term_overrides');
        $checked = 0;
        foreach ($this->i18n_objects($doc) as [$pointer, $obj]) {
            $source = self::get($obj, $base);
            if (!is_string($source)) {
                continue;
            }
            $present = array_values(array_filter($terms, static function (string $t) use ($source): bool {
                return strpos($source, $t) !== false;
            }));
            foreach (get_object_vars($obj) as $lang => $text) {
                $lang = (string) $lang;
                if ($lang === $base || !is_string($text)) {
                    continue;
                }
                $checked++;
                $langoverrides = self::get($overrides, $lang);
                foreach ($present as $term) {
                    $override = self::get($langoverrides, $term);
                    $expected = is_string($override) ? $override : $term;
                    if (strpos($text, $expected) === false) {
                        $detail = $expected !== $term ? " (expected the {$lang} override form '{$expected}')" : '';
                        $res->add('error', 'R07', "{$pointer}/{$lang}", "protected term '{$term}' from the base text is "
                            . "missing{$detail}");
                    }
                }
            }
        }
        $res->add('info', 'R07', '/course/protected_terms', count($terms) . " protected terms checked across {$checked} translations");
    }

    /**
     * R08 (partial): media paths are relative paths inside the package;
     * existence only against a supplied asset list.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r08(\stdClass $doc): void {
        $known = $this->options['media_known'];
        $refs = self::media_refs($doc);
        foreach ($refs as [$pointer, $path]) {
            if (!self::relative_path_ok($path)) {
                $this->result->add('error', 'R08', $pointer, "media path '{$path}' must be a relative path inside the "
                    . "package (no leading slash, drive letter or '..')");
                continue;
            }
            if (is_array($known) && !in_array($path, $known, true)) {
                $this->result->add('error', 'R08', $pointer, "media file '{$path}' not found in the registered assets");
            }
        }
        $this->result->add('info', 'R08', '/blocks', count($refs) . ' media references checked'
            . (is_array($known) ? ' against ' . count($known) . ' registered assets'
                : '; file existence is verified by the compiler (Stage 1 slice 2)'));
    }

    /**
     * R09: approval before compilation.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r09(\stdClass $doc): void {
        $review = self::get(self::get($doc, 'provenance'), 'review');
        $state = self::get($review, 'state');
        $pointer = '/provenance/review/state';
        if (in_array($state, ['approved', 'published'], true)) {
            return;
        }
        $label = self::text($state);
        if ($this->options['context'] !== self::CONTEXT_COMPILE) {
            $this->result->add('info', 'R09', $pointer, "review state is '{$label}'; a learner package is compiled only "
                . 'from an approved or published document');
            return;
        }
        if (in_array($state, ['draft', 'in_review'], true)) {
            if (!empty($this->options['preview'])) {
                $this->result->add('warning', 'R09', $pointer, "review state is '{$label}'; compiling a PREVIEW build only. "
                    . 'Do not deploy to learners');
            } else {
                $this->result->add('error', 'R09', $pointer, "review state is '{$label}'; approve the document or compile "
                    . 'a preview build');
            }
            return;
        }
        $this->result->add('error', 'R09', $pointer, "review state '{$label}' cannot be compiled (needs approved or published)");
    }

    /**
     * R10: URL-safe, document-unique ids.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r10(\stdClass $doc): void {
        $res = $this->result;
        $seen = [];
        $register = function ($value, string $pointer, string $kind) use (&$seen, $res): void {
            if (!is_string($value) || !preg_match(self::ID_RE, $value)) {
                $res->add('error', 'R10', $pointer, "{$kind} id " . self::describe($value) . ' must match [A-Za-z0-9_-]{1,64}');
                return;
            }
            if (isset($seen[$value])) {
                $res->add('error', 'R10', $pointer, "{$kind} id '{$value}' is already used at {$seen[$value]}; ids must be "
                    . 'unique across the document');
                return;
            }
            $seen[$value] = $pointer;
        };
        $patternonly = function ($value, string $pointer, string $kind) use ($res): void {
            if (!is_string($value) || !preg_match(self::ID_RE, $value)) {
                $res->add('error', 'R10', $pointer, "{$kind} id " . self::describe($value) . ' must match [A-Za-z0-9_-]{1,64}');
            }
        };
        foreach (self::blocks($doc) as $bi => $block) {
            $bp = "/blocks/{$bi}";
            $register(self::get($block, 'id'), "{$bp}/id", 'block');
            $characters = self::objects(self::get($block, 'characters', []));
            $characterids = [];
            foreach ($characters as $ci => $character) {
                $register(self::get($character, 'id'), "{$bp}/characters/{$ci}/id", 'character');
                $characterids[] = self::get($character, 'id');
            }
            foreach (self::objects(self::get($block, 'nodes', [])) as $ni => $node) {
                $register(self::get($node, 'id'), "{$bp}/nodes/{$ni}/id", 'node');
                $character = self::get($node, 'character');
                if (self::truthy($character) && !in_array($character, $characterids, true)) {
                    $res->add('error', 'R10', "{$bp}/nodes/{$ni}/character", "character '" . self::text($character)
                        . "' is not declared in this scenario");
                }
                foreach (self::objects(self::get($node, 'edges', [])) as $ei => $edge) {
                    $register(self::get($edge, 'id'), "{$bp}/nodes/{$ni}/edges/{$ei}/id", 'edge');
                }
            }
            foreach (self::objects(self::get($block, 'rubric', [])) as $ri => $rubric) {
                $register(self::get($rubric, 'id'), "{$bp}/rubric/{$ri}/id", 'rubric');
            }
            foreach (self::objects(self::get($block, 'questions', [])) as $qi => $question) {
                $register(self::get($question, 'id'), "{$bp}/questions/{$qi}/id", 'question');
                foreach (self::objects(self::get($question, 'options', [])) as $oi => $option) {
                    $patternonly(self::get($option, 'id'), "{$bp}/questions/{$qi}/options/{$oi}/id", 'option');
                }
                foreach (self::objects(self::get($question, 'pairs', [])) as $pi => $pair) {
                    $patternonly(self::get($pair, 'id'), "{$bp}/questions/{$qi}/pairs/{$pi}/id", 'pair');
                }
            }
        }
        $res->add('info', 'R10', '/blocks', count($seen) . ' document-unique ids (option and pair ids are scoped to '
            . 'their question per R06)');
    }

    /**
     * R11: id-only interaction responses stay within 255 characters.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r11(\stdClass $doc): void {
        $longest = 0;
        foreach (self::blocks($doc) as $bi => $block) {
            $bp = "/blocks/{$bi}";
            $type = self::get($block, 'type');
            if ($type === 'quiz') {
                foreach (self::objects(self::get($block, 'questions', [])) as $qi => $question) {
                    $response = self::worst_case_response($question);
                    if ($response === null) {
                        continue;
                    }
                    $length = \core_text::strlen($response);
                    $longest = max($longest, $length);
                    if ($length > self::MAX_STUDENT_RESPONSE) {
                        $this->result->add('error', 'R11', "{$bp}/questions/{$qi}", "worst-case student_response is "
                            . "{$length} chars > " . self::MAX_STUDENT_RESPONSE . ' (use shorter option ids)');
                    }
                }
            }
            if ($type === 'scenario') {
                foreach (self::objects(self::get($block, 'nodes', [])) as $node) {
                    foreach (self::objects(self::get($node, 'edges', [])) as $edge) {
                        $id = self::get($edge, 'id');
                        if (is_string($id)) {
                            $longest = max($longest, \core_text::strlen($id));
                        }
                    }
                }
            }
        }
        $this->result->add('info', 'R11', '/blocks', "longest possible student_response is {$longest} chars (limit "
            . self::MAX_STUDENT_RESPONSE . '); responses carry ids only');
    }

    /**
     * R12: source PII scan status before any live model call.
     *
     * @param \stdClass $doc
     * @return void
     */
    private function rule_r12(\stdClass $doc): void {
        $source = self::get(self::course($doc), 'source');
        $status = self::get(self::get($source, 'pii_scan'), 'status');
        $pointer = '/course/source/pii_scan/status';
        if ($status === 'blocked') {
            $this->result->add('error', 'R12', $pointer, "source PII scan is 'blocked'; generation halts until the source "
                . 'is redacted');
        } else if (!in_array($status, ['clean', 'redacted'], true)) {
            $this->result->add('error', 'R12', $pointer, 'source PII scan status ' . self::describe($status)
                . " must be 'clean' or 'redacted' before any live model call");
        }
    }

    /**
     * Slides: bullets of at most 8 words in the base language (warning).
     *
     * @param \stdClass $doc
     * @return void
     */
    private function check_slides(\stdClass $doc): void {
        $base = self::base_lang(self::course($doc));
        foreach (self::blocks($doc) as $bi => $block) {
            if (self::get($block, 'type') !== 'slide') {
                continue;
            }
            $points = self::get($block, 'points', []);
            foreach (is_array($points) ? $points : [] as $pi => $point) {
                $text = self::get($point, $base);
                if (!is_string($text)) {
                    continue;
                }
                $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
                if ($words > self::MAX_BULLET_WORDS) {
                    $this->result->add('warning', 'slides', "/blocks/{$bi}/points/{$pi}", "bullet has {$words} words > "
                        . self::MAX_BULLET_WORDS . ' recommended');
                }
            }
        }
    }

    /**
     * Source: the all-zero placeholder hash is a warning. (The harness also
     * compares the hash with the source file; the file is not stored here.)
     *
     * @param \stdClass $doc
     * @return void
     */
    private function check_source(\stdClass $doc): void {
        $declared = self::get(self::get(self::course($doc), 'source'), 'sha256');
        if ($declared === self::PLACEHOLDER_SHA) {
            $this->result->add('warning', 'source', '/course/source/sha256', 'sha256 is the all-zero placeholder; record '
                . 'the real source hash before approval');
        }
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * (pointer, object) for every recorded i18n pointer that resolves to an object.
     *
     * @param \stdClass $doc
     * @return array[]
     */
    private function i18n_objects(\stdClass $doc): array {
        $out = [];
        foreach ($this->result->i18npointers as $pointer) {
            try {
                $obj = document::resolve($doc, $pointer);
            } catch (\OutOfBoundsException $e) {
                continue;
            }
            if ($obj instanceof \stdClass) {
                $out[] = [$pointer, $obj];
            }
        }
        return $out;
    }

    /**
     * (pointer, relative path) for every media reference in the document.
     *
     * @param \stdClass $doc
     * @return array[]
     */
    private static function media_refs(\stdClass $doc): array {
        $refs = [];
        $addref = static function (string $pointer, $ref) use (&$refs): void {
            if (!$ref instanceof \stdClass) {
                return;
            }
            foreach (['path', 'word_timestamps'] as $field) {
                $value = self::get($ref, $field);
                if (is_string($value)) {
                    $refs[] = [document::join($pointer, $field), $value];
                }
            }
        };
        $addset = static function (string $pointer, $set, bool $captions) use (&$refs, $addref): void {
            if (!$set instanceof \stdClass) {
                return;
            }
            foreach (get_object_vars($set) as $lang => $ref) {
                $child = document::join($pointer, (string) $lang);
                if ($captions) {
                    if (is_string($ref)) {
                        $refs[] = [$child, $ref];
                    }
                } else {
                    $addref($child, $ref);
                }
            }
        };
        foreach (self::blocks($doc) as $bi => $block) {
            $bp = "/blocks/{$bi}";
            $addref(document::join($bp, 'image'), self::get($block, 'image'));
            $addset(document::join($bp, 'audio'), self::get($block, 'audio'), false);
            $addset(document::join($bp, 'media'), self::get($block, 'media'), false);
            $addset(document::join($bp, 'captions'), self::get($block, 'captions'), true);
            foreach (self::objects(self::get($block, 'characters', [])) as $ci => $character) {
                $addref("{$bp}/characters/{$ci}/avatar", self::get($character, 'avatar'));
            }
            foreach (self::objects(self::get($block, 'nodes', [])) as $ni => $node) {
                $addset("{$bp}/nodes/{$ni}/audio", self::get($node, 'audio'), false);
                $addset("{$bp}/nodes/{$ni}/captions", self::get($node, 'captions'), true);
            }
        }
        return $refs;
    }

    /**
     * Relative path inside a package: no leading slash, backslash, drive or '..'.
     *
     * @param string $path
     * @return bool
     */
    private static function relative_path_ok(string $path): bool {
        if ($path === '' || $path[0] === '/' || strpos($path, '\\') !== false
                || in_array('..', explode('/', $path), true)) {
            return false;
        }
        return !preg_match('/^[A-Za-z]:/', $path);
    }

    /**
     * The longest compact answer a learner can give (statetoken.worst_case_answer),
     * written as the SCORM 1.2 student_response; null for an unknown type.
     *
     * @param \stdClass $question
     * @return string|null
     */
    private static function worst_case_response(\stdClass $question): ?string {
        $kind = self::get($question, 'type');
        if (!in_array($kind, ['multichoice', 'multiresponse', 'truefalse', 'match'], true)) {
            return null;
        }
        $ids = static function ($items): array {
            $out = [];
            foreach (self::objects($items) as $item) {
                $id = self::get($item, 'id', '');
                $out[] = is_scalar($id) ? (string) $id : '';
            }
            return $out;
        };
        $longest = static function (array $values): string {
            $best = '';
            foreach ($values as $value) {
                if (\core_text::strlen($value) > \core_text::strlen($best)) {
                    $best = $value;
                }
            }
            return $best;
        };
        $optionids = $ids(self::get($question, 'options', []));
        if ($kind === 'multichoice' || $kind === 'truefalse') {
            return $longest($optionids);
        }
        if ($kind === 'multiresponse') {
            return implode('[,]', $optionids);
        }
        $pairids = $ids(self::get($question, 'pairs', []));
        $longestpair = $longest($pairids);
        return implode('[,]', array_map(static function (string $pid) use ($longestpair): string {
            return "{$pid}[.]{$longestpair}";
        }, $pairids));
    }

    /**
     * Breadth-first reachability.
     *
     * @param string $start
     * @param array  $adjacency node id => target ids
     * @return array<string,bool> reached node ids (as keys)
     */
    private static function bfs(string $start, array $adjacency): array {
        $seen = [$start => true];
        $queue = [$start];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($adjacency[$current] ?? [] as $target) {
                if (!isset($seen[$target])) {
                    $seen[$target] = true;
                    $queue[] = (string) $target;
                }
            }
        }
        return $seen;
    }

    /**
     * The first cycle a depth-first walk from start meets, as a node path
     * ending on the node it started from; [] when there is none. Iterative,
     * so a long chain cannot hit a recursion limit; visits targets in the
     * same order as the harness's recursive visit().
     *
     * @param string $start
     * @param array  $adjacency
     * @return string[]
     */
    private static function find_cycle(string $start, array $adjacency): array {
        $colour = [$start => 1];
        $path = [$start];
        $stack = [[$start, 0]];
        while ($stack) {
            $top = count($stack) - 1;
            [$node, $next] = $stack[$top];
            $targets = $adjacency[$node] ?? [];
            if ($next < count($targets)) {
                $stack[$top][1] = $next + 1;
                $target = (string) $targets[$next];
                $state = $colour[$target] ?? 0;
                if ($state === 1) {
                    $from = array_search($target, $path, true);
                    return array_merge(array_slice($path, (int) $from), [$target]);
                }
                if ($state === 0) {
                    $colour[$target] = 1;
                    $path[] = $target;
                    $stack[] = [$target, 0];
                }
            } else {
                array_pop($stack);
                array_pop($path);
                $colour[$node] = 2;
            }
        }
        return [];
    }

    /**
     * @param \stdClass $doc
     * @return \stdClass
     * @throws \UnexpectedValueException when there is no course object
     */
    private static function course(\stdClass $doc): \stdClass {
        $course = self::get($doc, 'course');
        if (!$course instanceof \stdClass) {
            throw new \UnexpectedValueException("document has no 'course' object");
        }
        return $course;
    }

    /**
     * The block objects, keyed by their index in the document's list.
     *
     * @param \stdClass $doc
     * @return \stdClass[]
     * @throws \UnexpectedValueException when there is no blocks array
     */
    private static function blocks(\stdClass $doc): array {
        $blocks = self::get($doc, 'blocks');
        if (!is_array($blocks)) {
            throw new \UnexpectedValueException("document has no 'blocks' array");
        }
        return self::objects($blocks);
    }

    /**
     * The \stdClass members of a list, keyed by their original index.
     *
     * @param mixed $list
     * @return \stdClass[]
     */
    private static function objects($list): array {
        if (!is_array($list)) {
            return [];
        }
        return array_filter($list, static function ($item): bool {
            return $item instanceof \stdClass;
        });
    }

    /**
     * The string members of a list, in order.
     *
     * @param mixed $list
     * @return string[]
     */
    private static function strings($list): array {
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * The duplicated string values of a list, sorted.
     *
     * @param array $values
     * @return string[]
     */
    private static function duplicates(array $values): array {
        $counts = array_count_values(self::strings($values));
        $dups = array_map('strval', array_keys(array_filter($counts, static function (int $n): bool {
            return $n > 1;
        })));
        sort($dups);
        return $dups;
    }

    /**
     * A member of an object, or $default when the value is not an object or
     * lacks the member.
     *
     * @param mixed  $obj
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    private static function get($obj, string $key, $default = null) {
        return ($obj instanceof \stdClass && property_exists($obj, $key)) ? $obj->{$key} : $default;
    }

    /**
     * Python truthiness, as the harness tests `if node.get("next")`: null,
     * false, '', 0, 0.0, an empty list and an empty object are false, and
     * everything else - the string "0" included, a valid id - is true. PHP's
     * own (bool) cast would treat "0" as false.
     *
     * @param mixed $value
     * @return bool
     */
    private static function truthy($value): bool {
        if ($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0 || $value === []) {
            return false;
        }
        if ($value instanceof \stdClass) {
            return get_object_vars($value) !== [];
        }
        return true;
    }

    /**
     * course.base_lang, which several rules need (the harness's
     * course["base_lang"]: a missing key fails the rule, not the run).
     *
     * @param \stdClass $course
     * @return string
     * @throws \UnexpectedValueException when absent or not a string
     */
    private static function base_lang(\stdClass $course): string {
        $base = self::get($course, 'base_lang');
        if (!is_string($base)) {
            throw new \UnexpectedValueException('course.base_lang is missing or not a string');
        }
        return $base;
    }

    /**
     * Does a schema pattern match? Python's re.search semantics (unanchored
     * unless the pattern anchors), with `$` meaning end of string.
     *
     * @param string $pattern
     * @param string $subject
     * @return bool
     */
    private static function matches(string $pattern, string $subject): bool {
        return preg_match('~' . str_replace('~', '\~', $pattern) . '~uD', $subject) === 1;
    }

    /**
     * @param mixed  $value
     * @param string $kind
     * @return bool
     * @throws \coding_exception for a type name the subset does not know (a schema bug)
     */
    private static function is_type($value, string $kind): bool {
        switch ($kind) {
            case 'object':
                return $value instanceof \stdClass;
            case 'array':
                return is_array($value);
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'number':
                return is_int($value) || is_float($value);
            case 'boolean':
                return is_bool($value);
            case 'null':
                return $value === null;
        }
        throw new \coding_exception("unsupported schema type {$kind}");
    }

    /**
     * JSON equality for const/enum (scalars; true is never 1).
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private static function json_equal($a, $b): bool {
        if (is_bool($a) || is_bool($b) || $a === null || $b === null) {
            return $a === $b;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a == $b;
        }
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }
        return false;
    }

    /**
     * Short description of a value for a message (the harness's _describe()).
     *
     * @param mixed $value
     * @return string
     */
    private static function describe($value): string {
        if (is_bool($value)) {
            return 'boolean';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            $shown = \core_text::strlen($value) > 40 ? \core_text::substr($value, 0, 40) : $value;
            return "string '" . $shown . "'";
        }
        if (is_array($value)) {
            return 'array[' . count($value) . ']';
        }
        if ($value instanceof \stdClass) {
            $keys = array_slice(array_map('strval', array_keys(get_object_vars($value))), 0, 6);
            return 'object{' . implode(', ', $keys) . '}';
        }
        return gettype($value);
    }

    /**
     * A schema literal (const or enum member) for a message: strings quoted.
     *
     * @param mixed $value
     * @return string
     */
    private static function literal($value): string {
        return is_string($value) ? "'{$value}'" : (string) json_encode($value);
    }

    /**
     * A value as message text: strings as-is, anything else described.
     *
     * @param mixed $value
     * @return string
     */
    private static function text($value): string {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : self::describe($value));
    }
}
