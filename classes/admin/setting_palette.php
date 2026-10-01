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

namespace local_gradeheatmap\admin;

use local_gradeheatmap\local\palette;
use local_gradeheatmap\output\palette_editor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting holding the site palette, stored as JSON.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_palette extends \admin_setting {
    /**
     * Creates the setting with the default palette as its default.
     *
     * @param string $name Setting name, including the plugin prefix.
     * @param \lang_string|string $visiblename
     * @param \lang_string|string $description
     */
    public function __construct(string $name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, palette::default()->to_json());
    }

    /**
     * The stored JSON.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Validates and stores the submitted palette.
     *
     * @param mixed $data The editor's submission, or a JSON string (e.g. when defaults are applied).
     * @return string Empty on success, else an error message.
     */
    public function write_setting($data) {
        if (is_string($data)) {
            $palette = palette::from_json($data);
        } else {
            [$froms, $colours] = palette_editor::read_submission($data);
            $palette = palette::from_input($froms, $colours);
        }
        if (!$palette) {
            return get_string('invalidpalette', 'local_gradeheatmap', palette::MAX_COLOURS);
        }
        return $this->config_write($this->name, $palette->to_json()) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Renders the palette editor.
     *
     * @param mixed $data The stored JSON, or the rejected submission when redisplaying an error.
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        if (is_array($data)) {
            [$froms, $colours] = palette_editor::read_submission($data);
            $rows = [];
            foreach ($colours as $i => $colour) {
                $rows[] = ['from' => $froms[$i] ?? '', 'colour' => $colour];
            }
            $editor = new palette_editor($this->get_full_name(), $rows);
        } else {
            $palette = palette::from_json((string) $data) ?? palette::default();
            $editor = palette_editor::for_palette($this->get_full_name(), $palette);
        }
        $element = $OUTPUT->render_from_template('local_gradeheatmap/palette_editor', $editor->export_for_template($OUTPUT));
        $PAGE->requires->js_call_amd('local_gradeheatmap/palette_editor', 'init');

        $default = [];
        foreach (palette::default()->get_stops() as $stop) {
            $default[] = format_float($stop['from'], -1) . '%: ' . $stop['colour'];
        }
        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            false,
            '',
            implode(', ', $default),
            $query
        );
    }
}
