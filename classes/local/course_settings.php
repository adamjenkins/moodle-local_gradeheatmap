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

namespace local_gradeheatmap\local;

use local_gradeheatmap\settings;

/**
 * Heatmap settings chosen by teachers for one course: the colouring mode and the palette.
 *
 * Either may be unset, in which case the site setting applies.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings {
    /** @var string Table holding the course settings. */
    public const TABLE = 'local_gradeheatmap_course';

    /**
     * The course's own mode.
     *
     * @param int $courseid
     * @return string|null One of the settings::MODE_ constants, or null for the site mode.
     */
    public static function get_mode(int $courseid): ?string {
        $record = self::get_record($courseid);
        return ($record && settings::is_valid_mode((string) $record->mode)) ? $record->mode : null;
    }

    /**
     * The course's own palette.
     *
     * @param int $courseid
     * @return palette|null The palette, or null for the site palette.
     */
    public static function get_palette(int $courseid): ?palette {
        $record = self::get_record($courseid);
        return $record ? palette::from_json($record->palette) : null;
    }

    /**
     * Stores the course's settings; with neither set, the course row is removed.
     *
     * @param int $courseid
     * @param string|null $mode One of the settings::MODE_ constants, or null for the site mode.
     * @param palette|null $palette The palette, or null for the site palette.
     */
    public static function set(int $courseid, ?string $mode, ?palette $palette): void {
        global $DB;
        if ($mode !== null && !settings::is_valid_mode($mode)) {
            $mode = null;
        }
        if ($mode === null && $palette === null) {
            self::delete($courseid);
            return;
        }
        $record = (object) [
            'courseid' => $courseid,
            'mode' => $mode,
            'palette' => $palette ? $palette->to_json() : null,
            'timemodified' => time(),
        ];
        if ($existing = $DB->get_field(self::TABLE, 'id', ['courseid' => $courseid])) {
            $record->id = $existing;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Removes the course's settings.
     *
     * @param int $courseid
     */
    public static function delete(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Removes a course's settings when the course is deleted.
     *
     * @param \core_course\hook\before_course_deleted $hook
     */
    public static function before_course_deleted(\core_course\hook\before_course_deleted $hook): void {
        self::delete((int) $hook->course->id);
    }

    /**
     * The course's row.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    protected static function get_record(int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['courseid' => $courseid]) ?: null;
    }
}
