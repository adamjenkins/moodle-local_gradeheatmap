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

namespace local_gradeheatmap\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use local_gradeheatmap\hook_callbacks;

/**
 * Privacy provider: the plugin stores only the per-user heatmap on/off preference.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describes the stored user preference.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(hook_callbacks::PREFERENCE, 'privacy:metadata:preference:enabled');
        return $collection;
    }

    /**
     * Exports the user's preference.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences(hook_callbacks::PREFERENCE, null, $userid);
        if ($value !== null) {
            writer::export_user_preference(
                'local_gradeheatmap',
                hook_callbacks::PREFERENCE,
                transform::yesno($value),
                get_string('privacy:metadata:preference:enabled', 'local_gradeheatmap')
            );
        }
    }
}
