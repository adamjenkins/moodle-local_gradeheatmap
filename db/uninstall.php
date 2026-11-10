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
 * Uninstall cleanup.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes the per-user heatmap switch, which core's uninstall leaves in user_preferences.
 *
 * Core drops the plugin's tables and config itself.
 *
 * @return bool
 */
function xmldb_local_gradeheatmap_uninstall() {
    global $DB;
    $DB->delete_records('user_preferences', ['name' => \local_gradeheatmap\hook_callbacks::PREFERENCE]);
    return true;
}
