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

namespace local_gradeheatmap;

use context_course;
use core\hook\output\before_footer_html_generation;
use core\hook\output\before_http_headers;
use core\hook\output\before_standard_head_html_generation;
use local_gradeheatmap\local\course_settings;
use local_gradeheatmap\local\palette;
use local_gradeheatmap\output\palette_editor;
use moodle_page;
use moodle_url;

/**
 * Hook callbacks: the heatmap on the grader report, and the course settings on its preferences page.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string User preference holding the per-user on/off switch. */
    public const PREFERENCE = 'local_gradeheatmap_enabled';

    /** @var string The grader report. */
    public const GRADER_PATH = '/grade/report/grader/index.php';

    /** @var string The grader report preferences page. */
    public const PREFERENCES_PATH = '/grade/report/grader/preferences.php';

    /** @var string Name of the hidden field marking a submission of the course settings section. */
    public const FORM_MARKER = 'local_gradeheatmap_settings';

    /** @var string Name of the mode select. */
    public const FORM_MODE = 'local_gradeheatmap_mode';

    /** @var string Name of the "use a custom palette" checkbox. */
    public const FORM_USECUSTOM = 'local_gradeheatmap_usecustom';

    /** @var string Field name prefix of the palette editor. */
    public const FORM_PALETTE = 'local_gradeheatmap_palette';

    /**
     * Adds the colours in effect as CSS custom properties on the grader report.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        if (!self::should_load($page)) {
            return;
        }
        $hook->add_html(self::get_custom_properties_css((int) $page->course->id));
    }

    /**
     * Saves the course settings section when the grader report preferences form is submitted.
     *
     * Core's preferences page saves its own fields and ignores ours; this runs afterwards, when the
     * page header is printed, and only for a submission that carries the section's marker field.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        $page = $hook->renderer->get_page();
        if (!self::can_manage_settings($page) || !optional_param(self::FORM_MARKER, 0, PARAM_BOOL) || !data_submitted()) {
            return;
        }
        require_sesskey();
        $submitted = (array) data_submitted();
        [$froms, $colours] = palette_editor::read_submission($submitted[self::FORM_PALETTE] ?? []);
        $saved = self::save_course_settings(
            (int) $page->course->id,
            optional_param(self::FORM_MODE, '', PARAM_ALPHA),
            optional_param(self::FORM_USECUSTOM, 0, PARAM_BOOL),
            $froms,
            $colours
        );
        if (!$saved) {
            \core\notification::error(get_string('invalidpalette', 'local_gradeheatmap', palette::MAX_COLOURS));
        }
    }

    /**
     * Grader report: renders the toggle and loads the heatmap. Preferences page: renders the course settings section.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        if (self::should_load($page)) {
            $hook->add_html($hook->renderer->render_from_template('local_gradeheatmap/toggle', [
                'checked' => self::is_enabled_for_user(),
            ]));
            $courseid = (int) $page->course->id;
            $page->requires->js_call_amd('local_gradeheatmap/heatmap', 'init', [[
                'courseid' => $courseid,
                'enabled' => self::is_enabled_for_user(),
                'mode' => settings::get_mode($courseid),
                'starts' => settings::get_palette($courseid)->get_starts(),
            ]]);
        } else if (self::can_manage_settings($page)) {
            $hook->add_html($hook->renderer->render_from_template(
                'local_gradeheatmap/course_settings',
                self::export_course_settings((int) $page->course->id, $hook->renderer)
            ));
            $page->requires->js_call_amd('local_gradeheatmap/preferences', 'init');
        }
    }

    /**
     * Whether the current user has the heatmap switched on.
     *
     * @return bool
     */
    public static function is_enabled_for_user(): bool {
        return (bool) get_user_preferences(self::PREFERENCE, 1);
    }

    /**
     * Whether the page is the grader report of a course and the user may see the heatmap there.
     *
     * @param moodle_page $page
     * @return bool
     */
    public static function should_load(moodle_page $page): bool {
        $context = self::get_course_context($page, self::GRADER_PATH);
        return $context && has_all_capabilities(
            ['local/gradeheatmap:view', 'gradereport/grader:view', 'moodle/grade:viewall'],
            $context
        );
    }

    /**
     * Whether the page is the grader report preferences page and the user may change the course settings.
     *
     * @param moodle_page $page
     * @return bool
     */
    public static function can_manage_settings(moodle_page $page): bool {
        $context = self::get_course_context($page, self::PREFERENCES_PATH);
        return $context && has_all_capabilities(
            ['local/gradeheatmap:manage', 'gradereport/grader:view'],
            $context
        );
    }

    /**
     * The course context of a page at the given path, when the plugin is active there.
     *
     * @param moodle_page $page
     * @param string $path Moodle path of the page (one of the _PATH constants).
     * @return context_course|null
     */
    protected static function get_course_context(moodle_page $page, string $path): ?context_course {
        if (during_initial_install() || !get_config('local_gradeheatmap', 'enabled')) {
            return null;
        }
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        // Gate on the URL rather than the page type, which other pages can share.
        if (!$page->has_set_url() || !$page->url->compare(new moodle_url($path), URL_MATCH_BASE)) {
            return null;
        }
        $context = $page->context;
        if (!$context instanceof context_course || empty($page->course->id) || $page->course->id == SITEID) {
            return null;
        }
        return $context;
    }

    /**
     * Saves the submitted course settings.
     *
     * @param int $courseid
     * @param string $mode A mode, or '' for the site mode.
     * @param bool $usecustom Whether the course uses its own palette.
     * @param array $froms Submitted start percentages.
     * @param array $colours Submitted colours.
     * @return bool False when the custom palette is invalid (nothing is saved then).
     */
    public static function save_course_settings(
        int $courseid,
        string $mode,
        bool $usecustom,
        array $froms,
        array $colours
    ): bool {
        $palette = null;
        if ($usecustom) {
            $palette = palette::from_input($froms, $colours);
            if (!$palette) {
                return false;
            }
        }
        course_settings::set($courseid, settings::is_valid_mode($mode) ? $mode : null, $palette);
        return true;
    }

    /**
     * Template context for the course settings section.
     *
     * @param int $courseid
     * @param \renderer_base $output
     * @return array
     */
    public static function export_course_settings(int $courseid, \renderer_base $output): array {
        $coursemode = course_settings::get_mode($courseid);
        $sitemodelabel = get_string('mode_' . settings::get_site_mode(), 'local_gradeheatmap');
        $modes = [['value' => '', 'label' => get_string('sitedefault', 'local_gradeheatmap', $sitemodelabel),
            'selected' => $coursemode === null]];
        foreach ([settings::MODE_GRADIENT, settings::MODE_BANDS] as $mode) {
            $modes[] = ['value' => $mode, 'label' => get_string('mode_' . $mode, 'local_gradeheatmap'),
                'selected' => $coursemode === $mode];
        }
        $coursepalette = course_settings::get_palette($courseid);
        $editor = palette_editor::for_palette(
            self::FORM_PALETTE,
            $coursepalette ?? settings::get_site_palette(),
            $coursepalette === null
        );
        return [
            'marker' => self::FORM_MARKER,
            'modename' => self::FORM_MODE,
            'modes' => $modes,
            'usecustomname' => self::FORM_USECUSTOM,
            'usecustom' => $coursepalette !== null,
            'editor' => $editor->export_for_template($output),
        ];
    }

    /**
     * Builds the style block setting the palette colours in effect as CSS custom properties.
     *
     * The colours are validated #rrggbb values (see palette), so they are safe inside a style block.
     *
     * @param int|null $courseid The course whose own palette applies, or null for the site palette.
     * @return string
     */
    public static function get_custom_properties_css(?int $courseid = null): string {
        $declarations = '';
        foreach (settings::get_palette($courseid)->get_colours() as $i => $colour) {
            $declarations .= '--local-gradeheatmap-colour-' . ($i + 1) . ": {$colour};";
        }
        return '<style>body.path-grade-report-grader{' . $declarations . '}</style>';
    }
}
