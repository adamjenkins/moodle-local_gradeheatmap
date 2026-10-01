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

/**
 * An ordered heatmap palette: colours from lowest to highest, each starting at a percentage.
 *
 * The first colour starts at 0%. Each colour applies from its start up to the next colour's start;
 * a colour starting at exactly 100% is therefore used only for full marks. In gradient mode the
 * colours blend between their starts, and the highest colour below 100% stays solid up to 100%.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette {
    /** @var int Fewest colours in a palette. */
    public const MIN_COLOURS = 2;

    /** @var int Most colours in a palette. */
    public const MAX_COLOURS = 20;

    /** @var array Default palette: [start percentage, colour] pairs, matching the defaults in styles.css. */
    public const DEFAULT_STOPS = [
        [0, '#f28b82'],
        [50, '#fbbc77'],
        [75, '#fde68a'],
        [95, '#8fd19e'],
        [100, '#8ab4f8'],
    ];

    /** @var array[] Stops, each ['from' => float, 'colour' => '#rrggbb']. */
    protected $stops;

    /**
     * Use one of the factory methods, which validate.
     *
     * @param array[] $stops Valid stops.
     */
    protected function __construct(array $stops) {
        $this->stops = $stops;
    }

    /**
     * The default palette.
     *
     * @return self
     */
    public static function default(): self {
        return self::from_input(array_column(self::DEFAULT_STOPS, 0), array_column(self::DEFAULT_STOPS, 1));
    }

    /**
     * Builds a palette from parallel lists of start percentages and colours (e.g. form input).
     *
     * @param array $froms Start percentages; the first is always treated as 0.
     * @param array $colours Colours as #rrggbb.
     * @return self|null The palette, or null when the input is not a valid palette.
     */
    public static function from_input(array $froms, array $colours): ?self {
        $froms = array_values($froms);
        $colours = array_values($colours);
        $count = count($colours);
        if ($count < self::MIN_COLOURS || $count > self::MAX_COLOURS || count($froms) !== $count) {
            return null;
        }
        $stops = [];
        $previous = null;
        foreach ($colours as $i => $colour) {
            $colour = strtolower(trim((string) $colour));
            if (!preg_match('/^#[0-9a-f]{6}$/', $colour)) {
                return null;
            }
            $from = $i === 0 ? 0.0 : self::parse_percentage($froms[$i]);
            if ($from === null || ($previous !== null && $from <= $previous)) {
                return null;
            }
            $stops[] = ['from' => $from, 'colour' => $colour];
            $previous = $from;
        }
        return new self($stops);
    }

    /**
     * Builds a palette from its stored JSON.
     *
     * @param string|null $json
     * @return self|null The palette, or null when the JSON is empty or not a valid palette.
     */
    public static function from_json(?string $json): ?self {
        if ($json === null || $json === '') {
            return null;
        }
        $stops = json_decode($json, true);
        if (!is_array($stops) || !array_is_list($stops)) {
            return null;
        }
        $froms = [];
        $colours = [];
        foreach ($stops as $stop) {
            if (!is_array($stop) || !array_key_exists('from', $stop) || !array_key_exists('colour', $stop)) {
                return null;
            }
            $froms[] = $stop['from'];
            $colours[] = $stop['colour'];
        }
        return self::from_input($froms, $colours);
    }

    /**
     * Parses a start percentage.
     *
     * @param mixed $value
     * @return float|null A percentage within 0-100 rounded to two decimals, or null.
     */
    protected static function parse_percentage($value): ?float {
        if (!is_numeric($value)) {
            return null;
        }
        $value = round((float) $value, 2);
        return ($value >= 0 && $value <= 100) ? $value : null;
    }

    /**
     * The JSON stored for this palette.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode($this->stops);
    }

    /**
     * The stops, lowest first.
     *
     * @return array[] Each ['from' => float, 'colour' => '#rrggbb'].
     */
    public function get_stops(): array {
        return $this->stops;
    }

    /**
     * The start percentages, lowest first.
     *
     * @return float[]
     */
    public function get_starts(): array {
        return array_column($this->stops, 'from');
    }

    /**
     * The colours, lowest first.
     *
     * @return string[]
     */
    public function get_colours(): array {
        return array_column($this->stops, 'colour');
    }

    /**
     * Whether two palettes are the same.
     *
     * @param palette $other
     * @return bool
     */
    public function equals(palette $other): bool {
        return $this->to_json() === $other->to_json();
    }
}
