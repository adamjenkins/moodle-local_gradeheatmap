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

/**
 * Restore support for local_gradeheatmap.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_gradeheatmap\local\course_settings;
use local_gradeheatmap\local\palette;
use local_gradeheatmap\settings;

/**
 * Restores the course's heatmap settings.
 *
 * Restored values are backup input, so both are validated again before they are stored: an unknown
 * mode or an invalid palette falls back to the site setting.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_gradeheatmap_plugin extends restore_local_plugin {
    /**
     * Declares the paths handled at course level.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('settings', $this->get_pathfor('/settings')),
        ];
    }

    /**
     * Stores the restored settings on the target course, replacing any it had.
     *
     * @param array|\stdClass $data
     */
    public function process_settings($data) {
        $data = (array) $data;
        $mode = (string) ($data['mode'] ?? '');
        course_settings::set(
            (int) $this->task->get_courseid(),
            settings::is_valid_mode($mode) ? $mode : null,
            palette::from_json(isset($data['palette']) ? (string) $data['palette'] : null)
        );
    }
}
