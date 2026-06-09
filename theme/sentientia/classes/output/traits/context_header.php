<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Context-header renderers.
 *
 * Extracted from `core_renderer.php` in Engineering 30 (decomposition
 * pass 5). The two methods here cooperate:
 *
 *   public context_header($headerinfo, $headinglevel): string
 *     Public entry point. Constructs the data \context_header VO
 *     from $headerinfo + page context. Walks the user-profile,
 *     module, and messaging-button branches.
 *
 *   protected render_context_header(\context_header $h): string
 *     Lower-level renderer. Takes the data VO and emits the final
 *     HTML (heading, optional avatar image, optional messaging /
 *     contact buttons, prefix line).
 *
 * Why a single trait
 * ------------------
 * The two methods are tightly coupled — `context_header()` always
 * dispatches to `render_context_header()` and never returns without
 * doing so. Splitting them across traits would leave a "rendering"
 * trait whose public surface is one method and which depends on a
 * non-portable peer. Keeping them together makes the trait
 * self-contained.
 *
 * Dependencies on `$this`
 * -----------------------
 * The methods call several inherited methods from \core_renderer:
 *   - $this->user_picture()
 *   - $this->pix_icon()
 *   - $this->heading()
 * And read inherited properties:
 *   - $this->page (the moodle_page in scope)
 *
 * Globals declared on first use rather than at function head — this
 * matches Moodle's house style and keeps the per-block scope obvious
 * at the line where the global is touched.
 *
 * @package theme_sentientia
 */
trait context_header {

    /**
     * Public entry point. Builds the data VO and renders the header.
     *
     * @param array $headerinfo Optional pre-built header data
     *                          (overrides page heading + user data)
     * @param int   $headinglevel  Which h-level for the heading
     * @return string HTML for the header bar
     */
    public function context_header($headerinfo = null, $headinglevel = 1): string {
        global $DB, $USER, $CFG, $SITE;
        require_once($CFG->dirroot . '/user/lib.php');
        $context     = $this->page->context;
        $heading     = null;
        $imagedata   = null;
        $subheader   = null;
        $userbuttons = null;

        // Make sure to use the heading if it has been set.
        if (isset($headerinfo['heading'])) {
            $heading = $headerinfo['heading'];
        } else {
            $heading = $this->page->heading;
        }

        // The user context currently has images and buttons. Other contexts may follow.
        if ((isset($headerinfo['user']) || $context->contextlevel == CONTEXT_USER)
                && $this->page->pagetype !== 'my-index') {
            if (isset($headerinfo['user'])) {
                $user = $headerinfo['user'];
            } else {
                // Look up the user information if it is not supplied.
                $user = $DB->get_record('user', ['id' => $context->instanceid]);
            }

            // If the user context is set, then use that for capability checks.
            if (isset($headerinfo['usercontext'])) {
                $context = $headerinfo['usercontext'];
            }

            // Only provide user information if the user is the current user, or a user
            // which the current user can view.
            // When checking user_can_view_profile(), either:
            //   If the page context is course, check the course context (from the page) or;
            //   If page context is NOT course, then check across all courses.
            $course = ($this->page->context->contextlevel == CONTEXT_COURSE)
                ? $this->page->course
                : null;

            if (user_can_view_profile($user, $course)) {
                // Use the user's full name if the heading isn't set.
                if (empty($heading)) {
                    $heading = fullname($user);
                }

                $imagedata = $this->user_picture($user, ['size' => 100]);

                // Check to see if we should be displaying a message button.
                if (!empty($CFG->messaging)
                        && has_capability('moodle/site:sendmessage', $context)) {
                    $userbuttons = [
                        'messages' => [
                            'buttontype' => 'message',
                            'title' => get_string('message', 'message'),
                            'url'   => new \moodle_url('/message/index.php',
                                ['id' => $user->id]),
                            'image' => 'message',
                            'linkattributes' => \core_message\helper::messageuser_link_params(
                                $user->id),
                            'page'  => $this->page,
                        ],
                    ];

                    if ($USER->id != $user->id) {
                        $iscontact = \core_message\api::is_contact($USER->id, $user->id);
                        $contacttitle     = $iscontact ? 'removefromyourcontacts'
                                                       : 'addtoyourcontacts';
                        $contacturlaction = $iscontact ? 'removecontact' : 'addcontact';
                        $contactimage     = $iscontact ? 'removecontact' : 'addcontact';
                        $userbuttons['togglecontact'] = [
                            'buttontype' => 'togglecontact',
                            'title'      => get_string($contacttitle, 'message'),
                            'url'        => new \moodle_url('/message/index.php', [
                                'user1' => $USER->id,
                                'user2' => $user->id,
                                $contacturlaction => $user->id,
                                'sesskey' => sesskey(),
                            ]),
                            'image' => $contactimage,
                            'linkattributes' =>
                                \core_message\helper::togglecontact_link_params(
                                    $user, $iscontact),
                            'page' => $this->page,
                        ];
                    }

                    $this->page->requires->string_for_js('changesmadereallygoaway',
                        'moodle');
                }
            } else {
                $heading = null;
            }
        }

        $prefix = null;
        if ($context->contextlevel == CONTEXT_MODULE) {
            if ($this->page->course->format === 'singleactivity') {
                $heading = $this->page->course->fullname;
            } else {
                $heading       = $this->page->cm->get_formatted_name();
                $imagedata     = $this->pix_icon('monologo', '', $this->page->activityname,
                    ['class' => 'activityicon']);
                $purposeclass  = plugin_supports('mod', $this->page->activityname,
                    FEATURE_MOD_PURPOSE);
                $purposeclass .= ' activityiconcontainer';
                $purposeclass .= ' modicon_' . $this->page->activityname;
                $imagedata     = \html_writer::tag('div', $imagedata,
                    ['class' => $purposeclass]);
                $prefix        = get_string('modulename', $this->page->activityname);
            }
        }

        $contextheader = new \context_header($heading, $headinglevel, $imagedata,
            $userbuttons, $prefix);
        return $this->render_context_header($contextheader);
    }

    /**
     * Lower-level renderer for a context_header value object.
     *
     * Emits the final HTML for a header bar:
     *   - optional heading
     *   - optional image (avatar or activity icon)
     *   - optional messaging / contact buttons
     *   - optional prefix line above the heading
     *
     * @param \context_header $contextheader Header data VO
     * @return string HTML for the header bar
     */
    protected function render_context_header(\context_header $contextheader) {

        // Generate the heading first and before everything else as we might
        // have to do an early return.
        $heading = "";
        if (!isset($contextheader->heading)) {
            $heading = $this->heading($this->page->heading,
                $contextheader->headinglevel, 'h2');
        } else {
            if (strlen($contextheader->heading) > 0) {
                $heading = $this->heading($contextheader->heading,
                    $contextheader->headinglevel, 'h2');
            }
        }

        // All the html stuff goes here.
        $html = \html_writer::start_div('page-context-header');

        // Image data.
        if (isset($contextheader->imagedata)) {
            // Header specific image.
            $html .= \html_writer::div($contextheader->imagedata,
                'page-header-image mr-2');
        }

        // Headings.
        if (isset($contextheader->prefix)) {
            $prefix = \html_writer::div($contextheader->prefix,
                'text-muted text-uppercase small line-height-3');
            $heading = $prefix . $heading;
        }
        $html .= \html_writer::tag('div', $heading,
            ['class' => 'page-header-headings']);

        // Buttons.
        if (isset($contextheader->additionalbuttons)) {
            $html .= \html_writer::start_div('btn-group header-button-group');
            foreach ($contextheader->additionalbuttons as $button) {
                if (!isset($button->page)) {
                    // Include js for messaging.
                    if ($button['buttontype'] === 'togglecontact') {
                        \core_message\helper::togglecontact_requirejs();
                    }
                    if ($button['buttontype'] === 'message') {
                        \core_message\helper::messageuser_requirejs();
                    }
                    $image = $this->pix_icon($button['formattedimage'], $button['title'],
                        'moodle', [
                            'class' => 'iconsmall',
                            'role'  => 'presentation',
                        ]);
                    $image .= \html_writer::span($button['title'],
                        'header-button-title');
                } else {
                    $image = \html_writer::empty_tag('img', [
                        'src'  => $button['formattedimage'],
                        'role' => 'presentation',
                    ]);
                }
                $html .= \html_writer::link($button['url'],
                    \html_writer::tag('span', $image), $button['linkattributes']);
            }
            $html .= \html_writer::end_div();
        }
        $html .= \html_writer::end_div();

        return $html;
    }
}
