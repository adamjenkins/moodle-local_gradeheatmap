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

use local_gradeheatmap\admin\setting_palette;
use local_gradeheatmap\output\palette_editor;

/**
 * Tests for the palette and its admin setting.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(palette::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(palette_editor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(setting_palette::class)]
final class palette_test extends \advanced_testcase {
    /**
     * The default palette is the five-colour scale with full marks on its own colour.
     */
    public function test_default(): void {
        $palette = palette::default();
        $this->assertSame([0.0, 50.0, 75.0, 95.0, 100.0], $palette->get_starts());
        $this->assertSame(['#f28b82', '#fbbc77', '#fde68a', '#8fd19e', '#8ab4f8'], $palette->get_colours());
    }

    /**
     * Data for test_from_input.
     *
     * @return array
     */
    public static function from_input_provider(): array {
        return [
            'two colours' => [[0, 100], ['#000000', '#FFFFFF'], [0.0, 100.0]],
            'first start forced to 0' => [[30, 60], ['#000000', '#ffffff'], [0.0, 60.0]],
            'decimal starts are rounded' => [[0, '33.333'], ['#000000', '#ffffff'], [0.0, 33.33]],
            'one colour' => [[0], ['#000000'], null],
            'too many colours' => [range(0, 20), array_fill(0, 21, '#000000'), null],
            'starts not rising' => [[0, 50, 50], ['#000000', '#111111', '#222222'], null],
            'start above 100' => [[0, 101], ['#000000', '#111111'], null],
            'start not a number' => [[0, 'x'], ['#000000', '#111111'], null],
            'colour not #rrggbb' => [[0, 50], ['#000000', 'red'], null],
            'injection attempt' => [[0, 50], ['#000000', '#111111;}</style><script>'], null],
            'mismatched lists' => [[0, 50, 70], ['#000000', '#111111'], null],
        ];
    }

    /**
     * Palettes are validated.
     *
     * @param array $froms
     * @param array $colours
     * @param array|null $expectedstarts Null when the input must be rejected.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('from_input_provider')]
    public function test_from_input(array $froms, array $colours, ?array $expectedstarts): void {
        $palette = palette::from_input($froms, $colours);
        if ($expectedstarts === null) {
            $this->assertNull($palette);
            return;
        }
        $this->assertSame($expectedstarts, $palette->get_starts());
        $this->assertSame(array_map('strtolower', $colours), $palette->get_colours());
    }

    /**
     * Palettes survive their JSON, and invalid JSON is rejected.
     */
    public function test_json(): void {
        $palette = palette::from_input([0, 40, 80, 100], ['#111111', '#222222', '#333333', '#444444']);
        $this->assertTrue($palette->equals(palette::from_json($palette->to_json())));
        $this->assertNull(palette::from_json(null));
        $this->assertNull(palette::from_json(''));
        $this->assertNull(palette::from_json('{"from": 0}'));
        $this->assertNull(palette::from_json('[{"from": 0, "colour": "#000000"}]'));
        $this->assertNull(palette::from_json('[{"from": 0}, {"from": 50, "colour": "#000000"}]'));
    }

    /**
     * The admin setting stores a valid submission and rejects an invalid one.
     */
    public function test_admin_setting(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $setting = new setting_palette('local_gradeheatmap/palette', 'Palette', '');

        $this->assertSame('', $setting->write_setting([
            'from' => ['0', '20', '40', '60', '80', '90', '100'],
            'colour' => ['#000000', '#111111', '#222222', '#333333', '#444444', '#555555', '#666666'],
        ]));
        $this->assertCount(7, \local_gradeheatmap\settings::get_site_palette()->get_colours());

        $error = $setting->write_setting(['from' => ['0', '50', '40'], 'colour' => ['#000000', '#111111', '#222222']]);
        $this->assertSame(get_string('invalidpalette', 'local_gradeheatmap', palette::MAX_COLOURS), $error);
        $this->assertCount(7, \local_gradeheatmap\settings::get_site_palette()->get_colours());

        // Defaults are applied as JSON.
        $this->assertSame('', $setting->write_setting($setting->get_defaultsetting()));
        $this->assertTrue(\local_gradeheatmap\settings::get_site_palette()->equals(palette::default()));
    }
}
