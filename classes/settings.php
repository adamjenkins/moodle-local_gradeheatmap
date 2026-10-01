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

use local_gradeheatmap\local\course_settings;
use local_gradeheatmap\local\palette;

/**
 * Resolves the settings in effect: a course's own settings override the site settings.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {
    /** @var string Smooth gradient between the colours. */
    public const MODE_GRADIENT = 'gradient';

    /** @var string One solid colour per band. */
    public const MODE_BANDS = 'bands';

    /**
     * Whether a value is a colouring mode.
     *
     * @param string $mode
     * @return bool
     */
    public static function is_valid_mode(string $mode): bool {
        return $mode === self::MODE_GRADIENT || $mode === self::MODE_BANDS;
    }

    /**
     * The site colouring mode.
     *
     * @return string One of the MODE_ constants.
     */
    public static function get_site_mode(): string {
        $mode = (string) get_config('local_gradeheatmap', 'mode');
        return self::is_valid_mode($mode) ? $mode : self::MODE_GRADIENT;
    }

    /**
     * The colouring mode in effect.
     *
     * @param int|null $courseid The course, or null for the site mode.
     * @return string One of the MODE_ constants.
     */
    public static function get_mode(?int $courseid = null): string {
        return ($courseid ? course_settings::get_mode($courseid) : null) ?? self::get_site_mode();
    }

    /**
     * The site palette.
     *
     * @return palette
     */
    public static function get_site_palette(): palette {
        return palette::from_json((string) get_config('local_gradeheatmap', 'palette')) ?? palette::default();
    }

    /**
     * The palette in effect.
     *
     * @param int|null $courseid The course, or null for the site palette.
     * @return palette
     */
    public static function get_palette(?int $courseid = null): palette {
        return ($courseid ? course_settings::get_palette($courseid) : null) ?? self::get_site_palette();
    }
}
