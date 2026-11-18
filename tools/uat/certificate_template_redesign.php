<?php
// certificate_template_redesign.php - rebuild a tool_certificate template into the Sentientia
// "Course Completion" design (A4 landscape, Brand Book 2026-06), idempotently.
//
// Why: the 'UAT Course Completion' template that tools/uat/provision_test_users.php creates puts
// its text elements on refpoint 1 (top CENTRE) with posx=20, so every line is centred on x=20 mm
// and the title falls off the left edge; it also has no frame, no logo and no course name.
//
// What --apply does (tool_certificate's own API: template / page / element classes, the element
// file area, and the issue custom-field handler):
//   1. ensures the issue custom field 'coursefullname' exists (the same shortname mod_coursecertificate
//      registers, so the two stay compatible) - the 'program' element reads the course name from it;
//   2. leaves exactly ONE page on the template, A4 landscape 297 x 210 mm, margins 0;
//   3. replaces the page's elements with the design below (nothing changes when they already match).
//
//   seq element    name                      what
//   1   image      Frame (background)        generated SVG, isbackground: blue-tint margin, white card,
//                                            #0066A7 0.8 mm frame inset 8 mm + hairline, corner accents,
//                                            title divider, name rule, signature-style rules
//   2   image      Logo                      airpay academy logo, 46 mm wide, top centre
//   3   text       Title                     'Certificate of Completion'      freesansb 34 #0066A7
//   4   text       Intro                     'This is to certify that'        freesans 13 #1A1A2E
//   5   userfield  Learner name              fullname                         freesansb 30 #1A1A2E
//   6   text       Completion line           'has successfully completed'     freesans 13 #1A1A2E
//   7   program    Course name               issue field coursefullname       freesansb 20 #0066A7
//   8   date       Date of issue             issue date, strftimedate         freesansb 13 #1A1A2E
//   9   text       Date label                'DATE OF ISSUE'                  freesans 8 #5A6070
//   10  code       Certificate code          code only                        freesansb 13 #1A1A2E
//   11  text       Code label                'CERTIFICATE CODE'               freesans 8 #5A6070
//   12  code       QR code                   QR to the verification page, 24 mm, bottom centre
//   13  text       Verification              'Scan the QR code, or enter the certificate code at
//                                            <wwwroot>/admin/tool/certificate/index.php, to verify ...'
//   Every text element is centred with refpoint 1 (posx = the CENTRE of the line, width = the box),
//   so nothing can fall off the page. Montserrat is not a TCPDF font on the site (no custom font
//   directory), so the standard sans 'freesans' is used.
//
// Issued PDFs are STORED (file area tool_certificate/issues, one <code>.pdf per issue, made at issue
// time and served from storage), so existing issues keep the old look until regenerated:
//   --regenerate-issues  (with --apply) rebuilds every issue file of the template through
//                        template::create_issue_file($issue, true) - the call behind the plugin's own
//                        "Regenerate issue file" action - and first back-fills the empty course-name
//                        field from the issue's course. No issue is created and no message is sent.
//
// Usage (UAT: run ON the box as the web user; local: MOODLE_CONFIG=C:/xampp/htdocs/moodle5/config.php):
//   sudo -u www-data php certificate_template_redesign.php                       # dry run (default)
//   sudo -u www-data php certificate_template_redesign.php --apply               # rebuild the design
//   sudo -u www-data php certificate_template_redesign.php --apply --regenerate-issues
//   ... --name='Other template'   --id=42   (pick the template; --id wins over --name)
//   ... --logo=/path/logo.png     (default: theme/sentientia/pix/brand/academy-logo.png)
//   ... --sample-pdf=/tmp/s.pdf --sample-user=vp_learner1 [--sample-course=ID]
//       renders the CURRENT design for that user into a PDF file. The issue row it needs is written
//       inside a transaction that is rolled back: nothing is issued, stored, logged or messaged.
//
// Sentientia LMS / Airpay Payment Services 2026 - GPL v3 or later.

define('CLI_SCRIPT', true);

$configfile = getenv('MOODLE_CONFIG') ?: '/var/www/html/moodle5.2/config.php';
require($configfile);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false, 'dry-run' => false, 'apply' => false, 'name' => 'UAT Course Completion', 'id' => 0,
    'regenerate-issues' => false, 'logo' => '', 'sample-pdf' => '', 'sample-user' => '', 'sample-course' => 0,
], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(' ', $unrecognised) . '  (see --help)');
}
if ($options['help']) {
    $lines = file(__FILE__);
    foreach (array_slice($lines, 1, 80) as $l) {
        if (strpos($l, '//') !== 0) {
            break;
        }
        $t = substr($l, 3);
        cli_write($t === '' ? "\n" : $t);
    }
    exit(0);
}
if ($options['apply'] && $options['dry-run']) {
    cli_error('--apply and --dry-run are mutually exclusive (dry run is the default).');
}
$APPLY = !empty($options['apply']);
$REGEN = !empty($options['regenerate-issues']);

if (!class_exists('\tool_certificate\template')) {
    cli_error('tool_certificate is not installed on this site.');
}
\core\session\manager::set_user(get_admin());

// ----------------------------------------------------------------------------------------------
// Element plugins on the site.
// ----------------------------------------------------------------------------------------------
$enabled = \tool_certificate\element_helper::get_enabled_plugins();
$all = array_keys(\core_component::get_plugin_list('certificateelement'));
cli_writeln('certificateelement_* on this site: ' . implode(', ', array_map(function ($p) use ($enabled) {
    return $p . (isset($enabled[$p]) ? '' : ' (DISABLED)');
}, $all)));
foreach (['image', 'text', 'userfield', 'program', 'date', 'code'] as $need) {
    if (!isset($enabled[$need])) {
        cli_error("The design needs certificateelement_{$need}, which is missing or disabled here.");
    }
}

// ----------------------------------------------------------------------------------------------
// Template.
// ----------------------------------------------------------------------------------------------
if ((int) $options['id'] > 0) {
    $rec = $DB->get_record('tool_certificate_templates', ['id' => (int) $options['id']]);
    if (!$rec) {
        cli_error('No tool_certificate template with id ' . (int) $options['id'] . '.');
    }
} else {
    $recs = $DB->get_records('tool_certificate_templates', ['name' => $options['name']], 'id');
    if (!$recs) {
        cli_error("No tool_certificate template named '{$options['name']}' (use --name= or --id=).");
    }
    if (count($recs) > 1) {
        cli_error(count($recs) . " templates are named '{$options['name']}' (ids " . implode(', ', array_keys($recs)) .
            '). Pick one with --id=.');
    }
    $rec = reset($recs);
}
$template = \tool_certificate\template::instance(0, $rec);
$tplcontext = $template->get_context();
cli_writeln("Template id={$rec->id} '" . $template->get_name() . "' context={$tplcontext->id} shared={$rec->shared}");

// ----------------------------------------------------------------------------------------------
// The design.
// ----------------------------------------------------------------------------------------------
const CR_W = 297;           // A4 landscape, mm.
const CR_H = 210;
const CR_BLUE = '#0066A7';  // Brand Book 2026-06 primary.
const CR_INK = '#1A1A2E';   // Text.
const CR_MUTED = '#5A6070'; // Secondary text (labels).

/**
 * The frame, drawn once as a full-page SVG background (vector, a few KB).
 *
 * @return string
 */
function cr_frame_svg(): string {
    // Centred decorations use x = 149, the same centre as the text elements (posx is an integer).
    $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="297mm" height="210mm" viewBox="0 0 297 210">
  <rect x="0" y="0" width="297" height="210" fill="#EEF4FB"/>
  <polygon points="0,0 70,0 0,52" fill="#0066A7"/>
  <polygon points="0,0 40,0 0,30" fill="#1985DD"/>
  <polygon points="297,210 227,210 297,158" fill="#0066A7"/>
  <polygon points="297,210 257,210 297,180" fill="#1985DD"/>
  <rect x="8" y="8" width="281" height="194" fill="#FFFFFF" stroke="#0066A7" stroke-width="0.8"/>
  <rect x="10.5" y="10.5" width="276" height="189" fill="none" stroke="#0066A7" stroke-width="0.25"/>
  <polygon points="10.5,10.5 36,10.5 10.5,29" fill="#0066A7"/>
  <polygon points="286.5,199.5 261,199.5 286.5,181" fill="#0066A7"/>
  <line x1="123" y1="68.5" x2="145" y2="68.5" stroke="#0066A7" stroke-width="0.5"/>
  <line x1="153" y1="68.5" x2="175" y2="68.5" stroke="#0066A7" stroke-width="0.5"/>
  <polygon points="149,65.8 151.7,68.5 149,71.2 146.3,68.5" fill="#ED692B"/>
  <line x1="64" y1="101.5" x2="234" y2="101.5" stroke="#BABABA" stroke-width="0.3"/>
  <line x1="42" y1="167.5" x2="104" y2="167.5" stroke="#0066A7" stroke-width="0.4"/>
  <line x1="193" y1="167.5" x2="255" y2="167.5" stroke="#0066A7" stroke-width="0.4"/>
</svg>
SVG;
    return $svg;
}

/**
 * The full element list for the page, in sequence order.
 *
 * @param string $logocontent
 * @param string $logoname
 * @param array $logoinfo ['width' => px, 'height' => px]
 * @return array
 */
function cr_design(string $logocontent, string $logoname, array $logoinfo): array {
    global $CFG;
    $txt = function (string $name, string $text, string $font, int $size, string $colour, int $x, int $y, int $w): array {
        return ['element' => 'text', 'name' => $name, 'data' => $text, 'font' => $font, 'fontsize' => $size,
            'colour' => $colour, 'posx' => $x, 'posy' => $y, 'width' => $w, 'refpoint' => 1];
    };
    $els = [];
    $els[] = ['element' => 'image', 'name' => 'Frame (background)', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => 0,
        'data' => json_encode(['width' => 0, 'height' => 0, 'isbackground' => true]),
        'file' => ['name' => 'sentientia-certificate-frame.svg', 'content' => cr_frame_svg()]];
    if ($logocontent !== '') {
        $lw = 46;
        $lh = (int) round($lw * $logoinfo['height'] / max(1, $logoinfo['width']));
        $els[] = ['element' => 'image', 'name' => 'Logo', 'posx' => (int) round((CR_W - $lw) / 2), 'posy' => 17, 'width' => 0,
            'refpoint' => 0, 'data' => json_encode(['width' => $lw, 'height' => $lh, 'isbackground' => false]),
            'file' => ['name' => $logoname, 'content' => $logocontent]];
    }
    $els[] = $txt('Title', 'Certificate of Completion', 'freesansb', 34, CR_BLUE, 149, 47, 250);
    $els[] = $txt('Intro', 'This is to certify that', 'freesans', 13, CR_INK, 149, 77, 200);
    $els[] = ['element' => 'userfield', 'name' => 'Learner name', 'data' => 'fullname', 'font' => 'freesansb', 'fontsize' => 30,
        'colour' => CR_INK, 'posx' => 149, 'posy' => 87, 'width' => 250, 'refpoint' => 1];
    $els[] = $txt('Completion line', 'has successfully completed', 'freesans', 13, CR_INK, 149, 107, 200);
    $els[] = ['element' => 'program', 'name' => 'Course name', 'data' => json_encode(['display' => 'coursefullname']),
        'font' => 'freesansb', 'fontsize' => 20, 'colour' => CR_BLUE, 'posx' => 149, 'posy' => 117, 'width' => 220,
        'refpoint' => 1];
    $els[] = ['element' => 'date', 'name' => 'Date of issue',
        'data' => json_encode(['dateitem' => -1, 'dateformat' => 'strftimedate']), 'font' => 'freesansb', 'fontsize' => 13,
        'colour' => CR_INK, 'posx' => 73, 'posy' => 160, 'width' => 70, 'refpoint' => 1];
    $els[] = $txt('Date label', 'DATE OF ISSUE', 'freesans', 8, CR_MUTED, 73, 169, 70);
    $els[] = ['element' => 'code', 'name' => 'Certificate code', 'data' => json_encode(['display' => 1]), 'font' => 'freesansb',
        'fontsize' => 13, 'colour' => CR_INK, 'posx' => 224, 'posy' => 160, 'width' => 70, 'refpoint' => 1];
    $els[] = $txt('Code label', 'CERTIFICATE CODE', 'freesans', 8, CR_MUTED, 224, 169, 70);
    $els[] = ['element' => 'code', 'name' => 'QR code', 'data' => json_encode(['display' => 4]), 'font' => 'freesans',
        'fontsize' => 10, 'colour' => '#000000', 'posx' => 149, 'posy' => 147, 'width' => 24, 'refpoint' => 1];
    $verify = (new moodle_url('/admin/tool/certificate/index.php'))->out(false);
    $els[] = $txt('Verification', 'Scan the QR code, or enter the certificate code at ' . $verify . ', to verify this certificate.',
        'freesans', 8, CR_MUTED, 149, 185, 250);
    return $els;
}

/**
 * Logo: --logo, else the theme's brand logo, else the site logo setting. Returns [content, name, info].
 *
 * @param string $override
 * @return array
 */
function cr_logo(string $override): array {
    global $CFG;
    $candidates = $override !== '' ? [$override] : [
        $CFG->dirroot . '/theme/sentientia/pix/brand/academy-logo.png',
        $CFG->dirroot . '/theme/sentientia/pix/ap-academy-logo.png',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path) && ($info = @getimagesize($path))) {
            return [file_get_contents($path), 'logo-' . basename($path), ['width' => $info[0], 'height' => $info[1]], $path];
        }
    }
    if ($override !== '') {
        cli_error("--logo={$override} is not a readable image.");
    }
    // Site logo (Appearance > Logos).
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_system::instance()->id, 'core_admin', 'logo', 0, 'itemid', false);
    if ($file = reset($files)) {
        $info = $file->get_imageinfo();
        if ($info) {
            return [$file->get_content(), 'logo-' . $file->get_filename(), $info, 'site logo setting'];
        }
    }
    return ['', '', [], ''];
}

/**
 * Compares the page's current elements with the design.
 *
 * @param \tool_certificate\element[] $current
 * @param array $design
 * @param \context $ctx
 * @return string[] differences (empty = up to date)
 */
function cr_diff(array $current, array $design, \context $ctx): array {
    $diffs = [];
    $current = array_values($current);
    if (count($current) !== count($design)) {
        $diffs[] = 'element count ' . count($current) . ' -> ' . count($design);
    }
    $fs = get_file_storage();
    foreach ($design as $i => $want) {
        $have = $current[$i] ?? null;
        if (!$have) {
            $diffs[] = '#' . ($i + 1) . " missing {$want['element']} '{$want['name']}'";
            continue;
        }
        $rec = $have->to_record();
        $rec->data = $have->get_data();
        if ($rec->element !== $want['element'] || $rec->name !== $want['name']) {
            $diffs[] = '#' . ($i + 1) . " {$rec->element} '{$rec->name}' -> {$want['element']} '{$want['name']}'";
            continue;
        }
        foreach ($want as $key => $value) {
            if ($key === 'file') {
                $files = $fs->get_area_files($ctx->id, 'tool_certificate', 'element', $have->get_id(), 'id', false);
                $f = reset($files);
                if (!$f || count($files) !== 1 || $f->get_contenthash() !== sha1($value['content'])
                        || $f->get_filename() !== $value['name']) {
                    $diffs[] = '#' . ($i + 1) . " '{$want['name']}' file differs";
                }
                continue;
            }
            if ((string) ($rec->$key ?? '') !== (string) $value) {
                $diffs[] = '#' . ($i + 1) . " '{$want['name']}' {$key}: '" . shorten_text((string) ($rec->$key ?? ''), 40) .
                    "' -> '" . shorten_text((string) $value, 40) . "'";
            }
        }
    }
    return $diffs;
}

/**
 * Course full name for an issue (courseid, or a BizLMS 'course' module id), formatted for the PDF.
 *
 * @param \stdClass $issue
 * @return string
 */
function cr_issue_course_name(\stdClass $issue): string {
    global $DB;
    $courseid = (int) ($issue->courseid ?? 0);
    if (!$courseid && ($issue->moduletype ?? '') === 'course') {
        $courseid = (int) ($issue->moduleid ?? 0);
    }
    if ($courseid <= 0 || $courseid == SITEID || !($course = $DB->get_record('course', ['id' => $courseid], 'id, fullname'))) {
        return '';
    }
    return format_string($course->fullname, true, ['context' => context_course::instance($course->id), 'escape' => false]);
}

/**
 * Current value of the coursefullname field for an issue ('' when absent).
 *
 * @param int $issueid
 * @return string
 */
function cr_issue_field_value(int $issueid): string {
    $handler = \tool_certificate\customfield\issue_handler::create();
    foreach ($handler->get_instance_data($issueid, true) as $data) {
        if ($data->get_field()->get('shortname') === 'coursefullname') {
            return trim((string) $data->get_value());
        }
    }
    return '';
}

// ----------------------------------------------------------------------------------------------
// Current state + plan.
// ----------------------------------------------------------------------------------------------
[$logocontent, $logoname, $logoinfo, $logosource] = cr_logo((string) $options['logo']);
cli_writeln('Logo: ' . ($logosource !== '' ? "{$logosource} ({$logoinfo['width']}x{$logoinfo['height']} px)"
    : 'NONE FOUND - the design is applied without a logo'));
$design = cr_design($logocontent, $logoname, $logoinfo);

$pages = $template->get_pages();
cli_writeln('Pages now: ' . count($pages));
foreach ($pages as $p) {
    $pr = $p->to_record();
    cli_writeln("  page {$pr->id}: {$pr->width} x {$pr->height} mm, margins {$pr->leftmargin}/{$pr->rightmargin}");
    foreach ($p->get_elements() as $e) {
        $r = $e->to_record();
        cli_writeln(sprintf('    #%d %-9s %-24s x=%s y=%s w=%s ref=%s font=%s/%s %s', $r->sequence, $r->element, "'{$r->name}'",
            $r->posx, $r->posy, $r->width, $r->refpoint, $r->font, $r->fontsize, $r->colour));
    }
}
$issuecount = $DB->count_records('tool_certificate_issues', ['templateid' => $template->get_id()]);
$filecount = $DB->count_records_sql(
    "SELECT COUNT(f.id) FROM {files} f JOIN {tool_certificate_issues} i ON i.id = f.itemid
      WHERE f.component = 'tool_certificate' AND f.filearea = 'issues' AND f.filename <> '.' AND i.templateid = :t",
    ['t' => $template->get_id()]);
cli_writeln("Issues of this template: {$issuecount} (stored PDF files: {$filecount})");

$handler = \tool_certificate\customfield\issue_handler::create();
$fieldexists = (bool) $handler->find_field_by_shortname('coursefullname');

$pagechanges = [];
$first = $pages ? reset($pages) : null;
if (count($pages) > 1) {
    $pagechanges[] = 'delete ' . (count($pages) - 1) . ' extra page(s)';
}
if (!$first) {
    $pagechanges[] = 'create the page';
} else {
    $fr = $first->to_record();
    if ((int) $fr->width !== CR_W || (int) $fr->height !== CR_H || (int) $fr->leftmargin !== 0 || (int) $fr->rightmargin !== 0) {
        $pagechanges[] = "page {$fr->width}x{$fr->height} m{$fr->leftmargin}/{$fr->rightmargin} -> " . CR_W . 'x' . CR_H . ' m0/0';
    }
}
$elementdiffs = $first ? cr_diff($first->get_elements(), $design, $tplcontext) : ['no page yet'];
$uptodate = !$pagechanges && !$elementdiffs && $fieldexists;

cli_writeln('');
cli_writeln('Plan:');
cli_writeln('  issue field coursefullname: ' . ($fieldexists ? 'present' : 'CREATE'));
foreach ($pagechanges as $c) {
    cli_writeln("  page: {$c}");
}
if ($elementdiffs) {
    cli_writeln('  elements: REBUILD (' . count($elementdiffs) . ' difference(s)):');
    foreach (array_slice($elementdiffs, 0, 30) as $d) {
        cli_writeln("    {$d}");
    }
    if (count($elementdiffs) > 30) {
        cli_writeln('    ... ' . (count($elementdiffs) - 30) . ' more');
    }
} else {
    cli_writeln('  elements: up to date (' . count($design) . ')');
}
if ($REGEN) {
    cli_writeln("  regenerate: {$issuecount} issue file(s), back-filling an empty course name where the course is known");
}

// ----------------------------------------------------------------------------------------------
// Apply.
// ----------------------------------------------------------------------------------------------
if ($APPLY && !$fieldexists) {
    $handler->ensure_field_exists('coursefullname', 'text', get_string('fullnamecourse'), true, get_string('fullnamecourse'));
    \tool_certificate\customfield\issue_handler::reset_caches();
    $handler = \tool_certificate\customfield\issue_handler::create();
    if (!$handler->find_field_by_shortname('coursefullname')) {
        cli_error('Could not create the issue custom field coursefullname.');
    }
    cli_writeln('Created issue custom field coursefullname.');
}

if ($APPLY && ($pagechanges || $elementdiffs)) {
    $fs = get_file_storage();
    $tx = $DB->start_delegated_transaction();
    $pages = $template->get_pages();
    $first = $pages ? array_shift($pages) : null;
    foreach ($pages as $extra) {
        $template->delete_page($extra->get_id());
    }
    if (!$first) {
        $first = $template->new_page();
    }
    $first->save((object) ['width' => CR_W, 'height' => CR_H, 'leftmargin' => 0, 'rightmargin' => 0]);
    $template = \tool_certificate\template::instance($template->get_id());
    $first = \tool_certificate\page::instance($first->get_id());
    if ($elementdiffs) {
        foreach ($first->get_elements() as $el) {
            $template->delete_element($el->get_id());
        }
        foreach ($design as $spec) {
            $el = \tool_certificate\element::instance(0, (object) ['pageid' => $first->get_id(), 'element' => $spec['element']]);
            $data = $spec;
            unset($data['file'], $data['element']);
            $el->save((object) $data);
            if (!empty($spec['file'])) {
                $fs->create_file_from_string([
                    'contextid' => $tplcontext->id, 'component' => 'tool_certificate', 'filearea' => 'element',
                    'itemid' => $el->get_id(), 'filepath' => '/', 'filename' => $spec['file']['name'],
                ], $spec['file']['content']);
            }
        }
    }
    $tx->allow_commit();
    $template = \tool_certificate\template::instance($template->get_id());
    \tool_certificate\event\template_updated::create_from_template($template)->trigger();
    cli_writeln('Applied: ' . count($design) . ' elements on page ' . $first->get_id() . '.');
} else if ($APPLY) {
    cli_writeln('Nothing to change: the template already has this design.');
} else {
    cli_writeln($uptodate ? 'DRY RUN: already up to date.' : 'DRY RUN: nothing written. Re-run with --apply.');
}

// ----------------------------------------------------------------------------------------------
// Regenerate stored issue files.
// ----------------------------------------------------------------------------------------------
if ($REGEN && !$APPLY) {
    $missing = 0;
    $rs = $DB->get_recordset('tool_certificate_issues', ['templateid' => $template->get_id()], 'id');
    foreach ($rs as $issue) {
        if ($fieldexists && cr_issue_field_value($issue->id) !== '') {
            continue;
        }
        if (cr_issue_course_name($issue) !== '') {
            $missing++;
        }
    }
    $rs->close();
    cli_writeln("DRY RUN regenerate: {$issuecount} file(s) would be rebuilt, {$missing} course name(s) back-filled.");
} else if ($REGEN) {
    $template = \tool_certificate\template::instance($template->get_id());
    $handler = \tool_certificate\customfield\issue_handler::create();
    $done = 0;
    $filled = 0;
    $failed = 0;
    $rs = $DB->get_recordset('tool_certificate_issues', ['templateid' => $template->get_id()], 'id');
    foreach ($rs as $issue) {
        try {
            if (cr_issue_field_value($issue->id) === '' && ($name = cr_issue_course_name($issue)) !== '') {
                $handler->save_additional_data($issue, ['coursefullname' => $name]);
                $filled++;
            }
            // As external\issues::regenerate_issue_file(): new file, refreshed userfullname, event.
            $template->create_issue_file($issue, true);
            if ($user = $DB->get_record('user', ['id' => $issue->userid])) {
                $issuedata = @json_decode((string) $issue->data, true) ?: [];
                $issuedata['userfullname'] = fullname($user);
                $DB->set_field('tool_certificate_issues', 'data', json_encode($issuedata), ['id' => $issue->id]);
            }
            \tool_certificate\event\certificate_regenerated::create_from_issue($issue)->trigger();
            $done++;
        } catch (\Throwable $e) {
            $failed++;
            cli_writeln("  issue {$issue->id}: FAILED " . $e->getMessage());
        }
    }
    $rs->close();
    cli_writeln("Regenerated {$done} issue file(s), back-filled {$filled} course name(s), {$failed} failure(s).");
}

// ----------------------------------------------------------------------------------------------
// Sample PDF (rolled back: nothing is issued, stored or sent).
// ----------------------------------------------------------------------------------------------
if ($options['sample-pdf'] !== '') {
    if ($options['sample-user'] === '') {
        cli_error('--sample-pdf needs --sample-user=<username>.');
    }
    $user = $DB->get_record('user', ['username' => $options['sample-user'], 'deleted' => 0], '*', MUST_EXIST);
    $course = (int) $options['sample-course'] > 0
        ? $DB->get_record('course', ['id' => (int) $options['sample-course']], 'id, fullname', MUST_EXIST) : null;
    $template = \tool_certificate\template::instance($template->get_id());
    $pdf = null;
    $tx = $DB->start_delegated_transaction();
    try {
        $issue = (object) [
            'userid' => $user->id, 'templateid' => $template->get_id(),
            'code' => \tool_certificate\certificate::generate_code($user->id), 'emailed' => 0, 'timecreated' => time(),
            'expires' => null, 'component' => 'tool_certificate', 'courseid' => $course ? $course->id : null, 'archived' => 0,
            'data' => json_encode(['userfullname' => fullname($user)]),
        ];
        $issue->id = $DB->insert_record('tool_certificate_issues', $issue);
        if ($course) {
            \tool_certificate\customfield\issue_handler::create()->save_additional_data($issue,
                ['coursefullname' => cr_issue_course_name($issue)]);
        }
        $pdf = $template->generate_pdf(false, $issue, true);
    } finally {
        $sampleonly = new \Exception('sample only - rolled back');
        try {
            $tx->rollback($sampleonly);
        } catch (\Exception $e) {
            if ($e !== $sampleonly) {
                throw $e;
            }
            // Expected: rollback() rethrows what it was given. Nothing was kept.
        }
    }
    if (!$pdf) {
        cli_error('The sample PDF came back empty.');
    }
    file_put_contents($options['sample-pdf'], $pdf);
    cli_writeln("Sample PDF (current design, nothing issued): {$options['sample-pdf']} (" . strlen($pdf) . ' bytes)');
}
exit(0);
