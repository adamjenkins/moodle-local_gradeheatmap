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
 * Admin settings.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_gradeheatmap\settings as heatmapsettings;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_gradeheatmap', new lang_string('pluginname', 'local_gradeheatmap'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_gradeheatmap/enabled',
            new lang_string('enabled', 'local_gradeheatmap'),
            new lang_string('enabled_desc', 'local_gradeheatmap'),
            1
        ));

        $settings->add(new admin_setting_configselect(
            'local_gradeheatmap/mode',
            new lang_string('mode', 'local_gradeheatmap'),
            new lang_string('mode_desc', 'local_gradeheatmap'),
            heatmapsettings::MODE_GRADIENT,
            [
                heatmapsettings::MODE_GRADIENT => new lang_string('mode_gradient', 'local_gradeheatmap'),
                heatmapsettings::MODE_BANDS => new lang_string('mode_bands', 'local_gradeheatmap'),
            ]
        ));

        $settings->add(new \local_gradeheatmap\admin\setting_palette(
            'local_gradeheatmap/palette',
            new lang_string('palette', 'local_gradeheatmap'),
            new lang_string('palette_desc', 'local_gradeheatmap')
        ));
    }
}
