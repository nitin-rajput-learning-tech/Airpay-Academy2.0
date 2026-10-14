<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * What the notification log shows of the BizLMS email history (ADR-032).
 *
 * The notifications importer (classes/bizlms/) copies BizLMS's e-mail log into
 * local_sentientia_email_log and marks every such row legacy_source = 'bizlms'. The
 * import itself has no user-visible surface. What an administrator sees of those
 * rows is a reader change, and CLAUDE.md requires a default-OFF flag for every one:
 *
 *  - sentientia.emails.imported_history.enabled   the Logs tab, its export and the
 *    dashboard tiles include imported rows, with the BizLMS columns and badges.
 *    OFF: they count and list only what Sentientia wrote, as before the import.
 *  - sentientia.emails.imported_body_detail.enabled   the View link and the detail
 *    page for one imported message. Needs the first flag as well.
 *
 * Every reader asks this class, so the rule lives in one place. A flag lookup that
 * fails (registry not loadable, cache error) reads as OFF: the conservative answer.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class imported_history {

    /** Flag: imported rows appear in the log, export and dashboard tiles. */
    public const FLAG_HISTORY = 'sentientia.emails.imported_history.enabled';

    /** Flag: the detail view of one imported message. */
    public const FLAG_BODY = 'sentientia.emails.imported_body_detail.enabled';

    /** legacy_source of an imported row. */
    public const SOURCE = 'bizlms';

    /**
     * Do the log readers include imported rows?
     *
     * @return bool
     */
    public static function history_enabled(): bool {
        return self::flag(self::FLAG_HISTORY);
    }

    /**
     * May the detail view of an imported message be used? It needs both flags: a row the list does not show must
     * not be reachable by id either.
     *
     * @return bool
     */
    public static function body_enabled(): bool {
        return self::history_enabled() && self::flag(self::FLAG_BODY);
    }

    /**
     * Does the log hold any imported row? Uses idx_legacy_source, so it is cheap on a large log.
     *
     * @return bool
     */
    public static function exist(): bool {
        global $DB;
        return $DB->record_exists_select(delivery_log::TABLE, 'legacy_source IS NOT NULL');
    }

    /**
     * SQL that keeps only what Sentientia itself wrote (the condition readers add while the flag is OFF).
     *
     * @param string $alias Table alias, or '' for none.
     * @return string
     */
    public static function native_only_sql(string $alias = ''): string {
        return ($alias === '' ? '' : $alias . '.') . 'legacy_source IS NULL';
    }

    /**
     * An imported body without anything the browser would fetch from outside when it is shown (F-65).
     *
     * An old BizLMS mail can carry a remote image or a tracking pixel. Opening the detail view of an archived message
     * must not call out to a third party, and must not tell it that, and when, an administrator read it. So before the
     * body goes to format_text(): every <img> that points off the site is replaced by the placeholder, a CSS url() that
     * points off the site becomes none, and a background attribute that does is dropped. An inline data: image and a
     * relative one stay. This is a second layer: format_text() still cleans the markup afterwards.
     *
     * @param string $html
     * @param string $placeholder What takes the place of a removed image (empty removes it silently).
     * @return string
     */
    public static function without_external_resources(string $html, string $placeholder = ''): string {
        $external = '(?:https?:)?//';
        // An <img> with an off-site URL anywhere in the tag: src, srcset, or a lazy-load attribute.
        $images = preg_replace_callback('~<img\b[^>]*>~i', static function (array $m) use ($external, $placeholder): string {
            return preg_match('~[\s"\'=,(]' . $external . '~i', $m[0]) === 1 ? $placeholder : $m[0];
        }, $html);
        $html = $images ?? $html;
        // CSS: background:url(http://...), list-style-image, @import and the like inside a style attribute or element.
        $css = preg_replace('~url\(\s*[\'"]?\s*' . $external . '[^)]*\)~i', 'none', $html);
        $html = $css ?? $html;
        // <body background="..."> and <td background="...">.
        $background = preg_replace(
            '~\sbackground\s*=\s*(?:"\s*' . $external . '[^"]*"|\'\s*' . $external . '[^\']*\'|' . $external . '[^\s>]*)~i', '', $html);
        return $background ?? $html;
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function flag(string $key): bool {
        try {
            return \local_sentientia_platform\feature_flags::is_enabled($key);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
