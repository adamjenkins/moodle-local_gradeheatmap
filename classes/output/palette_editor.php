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

namespace local_gradeheatmap\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_gradeheatmap\local\palette;

/**
 * The palette editor: a list of colours from lowest to highest, each with its start percentage.
 *
 * Used both by the site setting and by the course settings on the grader report preferences page.
 * Submits two parallel arrays, <name>[from][] and <name>[colour][].
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette_editor implements renderable, templatable {
    /** @var string Form field name prefix. */
    protected $name;

    /** @var array[] Rows, each ['from' => mixed, 'colour' => string]. */
    protected $rows;

    /** @var bool Whether the editor starts disabled. */
    protected $disabled;

    /**
     * Creates an editor.
     *
     * @param string $name Form field name prefix.
     * @param array[] $rows Rows to show, each ['from' => mixed, 'colour' => string].
     * @param bool $disabled Whether the inputs start disabled.
     */
    public function __construct(string $name, array $rows, bool $disabled = false) {
        $this->name = $name;
        $this->rows = $rows;
        $this->disabled = $disabled;
    }

    /**
     * Creates an editor showing a palette.
     *
     * @param string $name Form field name prefix.
     * @param palette $palette
     * @param bool $disabled Whether the inputs start disabled.
     * @return self
     */
    public static function for_palette(string $name, palette $palette, bool $disabled = false): self {
        return new self($name, $palette->get_stops(), $disabled);
    }

    /**
     * Reads the two parallel arrays the editor submits.
     *
     * @param mixed $data The submitted value of the editor's name: ['from' => [...], 'colour' => [...]].
     * @return array [froms, colours], both lists of strings.
     */
    public static function read_submission($data): array {
        $data = is_array($data) ? $data : [];
        $clean = function ($values): array {
            if (!is_array($values)) {
                return [];
            }
            return array_values(array_map(fn($value): string => is_scalar($value) ? trim((string) $value) : '', $values));
        };
        return [$clean($data['from'] ?? []), $clean($data['colour'] ?? [])];
    }

    /**
     * Template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach (array_values($this->rows) as $i => $row) {
            $colour = strtolower((string) ($row['colour'] ?? ''));
            $rows[] = [
                'name' => $this->name,
                'number' => $i + 1,
                'from' => $i === 0 ? 0 : (is_numeric($row['from'] ?? null) ? (float) $row['from'] : ''),
                'colour' => preg_match('/^#[0-9a-f]{6}$/', $colour) ? $colour : '#000000',
                'first' => $i === 0,
                'disabled' => $this->disabled,
            ];
        }
        return [
            'name' => $this->name,
            'rows' => $rows,
            'newrow' => [
                'name' => $this->name,
                'number' => 0,
                'from' => '',
                'colour' => '#ffffff',
                'first' => false,
                'disabled' => $this->disabled,
            ],
            'disabled' => $this->disabled,
            'mincolours' => palette::MIN_COLOURS,
            'maxcolours' => palette::MAX_COLOURS,
        ];
    }
}
