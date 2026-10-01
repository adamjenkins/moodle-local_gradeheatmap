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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for the grade heatmap.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_gradeheatmap extends behat_base {
    /**
     * The grader report cell of a user and grade item.
     *
     * @param string $username
     * @param string $itemname Grade item name, or "Course total" for the course total item.
     * @param string $courseshortname
     * @return string CSS selector of the cell.
     */
    protected function grade_cell_selector(string $username, string $itemname, string $courseshortname): string {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        if ($itemname === 'Course total') {
            $itemid = $DB->get_field('grade_items', 'id', ['courseid' => $courseid, 'itemtype' => 'course'], MUST_EXIST);
        } else {
            $itemid = $DB->get_field('grade_items', 'id', ['courseid' => $courseid, 'itemname' => $itemname], MUST_EXIST);
        }
        return '#u' . $userid . 'i' . $itemid;
    }

    /**
     * Waits until a grader report cell has (or lacks) a class.
     *
     * @param string $selector
     * @param string $class
     * @param bool $expected Whether the class must be present.
     */
    protected function assert_cell_class(string $selector, string $class, bool $expected): void {
        $this->spin(function () use ($selector, $class, $expected): bool {
            $cell = $this->getSession()->getPage()->find('css', $selector);
            if (!$cell) {
                throw new ExpectationException("Grade cell '{$selector}' not found", $this->getSession());
            }
            $classes = preg_split('/\s+/', (string) $cell->getAttribute('class'));
            if (in_array($class, $classes) !== $expected) {
                throw new ExpectationException("Grade cell '{$selector}' has classes '" . implode(' ', $classes) .
                    "'; expected " . ($expected ? '' : 'no ') . "class '{$class}'", $this->getSession());
            }
            return true;
        });
    }

    /**
     * Waits until a grader report cell has (or lacks) a computed background colour.
     *
     * @param string $selector
     * @param string $colour Computed colour, e.g. rgb(0, 0, 0).
     * @param bool $expected Whether the cell must have this colour.
     */
    protected function assert_cell_background(string $selector, string $colour, bool $expected): void {
        $this->spin(function () use ($selector, $colour, $expected): bool {
            $actual = $this->evaluate_script('return (function() { const cell = document.querySelector(' .
                json_encode($selector) . '); return cell ? window.getComputedStyle(cell).backgroundColor : ""; })();');
            if (($actual === $colour) !== $expected) {
                throw new ExpectationException("Grade cell '{$selector}' has background '{$actual}', expected " .
                    ($expected ? '' : 'anything but ') . "'{$colour}'", $this->getSession());
            }
            return true;
        });
    }

    /**
     * Checks that a grader report cell has a heatmap class.
     *
     * @Then the grade heatmap cell of :username for :itemname in :course should have class :class
     * @param string $username
     * @param string $itemname Grade item name, or "Course total".
     * @param string $course Course shortname.
     * @param string $class
     */
    public function cell_should_have_class(string $username, string $itemname, string $course, string $class): void {
        $this->assert_cell_class($this->grade_cell_selector($username, $itemname, $course), $class, true);
    }

    /**
     * Checks that a grader report cell lacks a heatmap class.
     *
     * @Then the grade heatmap cell of :username for :itemname in :course should not have class :class
     * @param string $username
     * @param string $itemname Grade item name, or "Course total".
     * @param string $course Course shortname.
     * @param string $class
     */
    public function cell_should_not_have_class(string $username, string $itemname, string $course, string $class): void {
        $this->assert_cell_class($this->grade_cell_selector($username, $itemname, $course), $class, false);
    }

    /**
     * Checks the computed background colour of a grader report cell.
     *
     * @Then the grade heatmap cell of :username for :itemname in :course should have background :colour
     * @param string $username
     * @param string $itemname Grade item name, or "Course total".
     * @param string $course Course shortname.
     * @param string $colour Computed colour, e.g. rgb(0, 0, 0).
     */
    public function cell_should_have_background(string $username, string $itemname, string $course, string $colour): void {
        $this->assert_cell_background($this->grade_cell_selector($username, $itemname, $course), $colour, true);
    }

    /**
     * Checks that a grader report cell does not have a computed background colour.
     *
     * @Then the grade heatmap cell of :username for :itemname in :course should not have background :colour
     * @param string $username
     * @param string $itemname Grade item name, or "Course total".
     * @param string $course Course shortname.
     * @param string $colour Computed colour, e.g. rgb(0, 0, 0).
     */
    public function cell_should_not_have_background(
        string $username,
        string $itemname,
        string $course,
        string $colour
    ): void {
        $this->assert_cell_background($this->grade_cell_selector($username, $itemname, $course), $colour, false);
    }

    /**
     * Opens the grader report preferences page of a course.
     *
     * @Given /^I am on the grader report preferences page of "(?P<course>[^"]*)"$/
     * @param string $courseshortname
     */
    public function i_am_on_the_grader_report_preferences_page(string $courseshortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $this->execute(
            'behat_general::i_visit',
            [new moodle_url('/grade/report/grader/preferences.php', ['id' => $courseid])]
        );
    }

    /**
     * Sets a colour of the palette editor on the page (browsers do not type into colour inputs).
     *
     * @When /^I set grade heatmap palette colour "(?P<number>\d+)" to "(?P<colour>#[0-9a-fA-F]{6})"$/
     * @param string $number Position of the colour, 1 for the lowest.
     * @param string $colour A #rrggbb colour.
     */
    public function i_set_grade_heatmap_palette_colour(string $number, string $colour): void {
        $input = $this->find('css', $this->palette_row_selector($number) . ' input[type="color"]');
        $this->execute_js_on_node($input, '{{ELEMENT}}.value = ' . json_encode($colour) . ';');
    }

    /**
     * Sets the start percentage of a colour of the palette editor on the page.
     *
     * @When /^I set grade heatmap palette colour "(?P<number>\d+)" to start at "(?P<percentage>[0-9.]+)"$/
     * @param string $number Position of the colour, 1 for the lowest.
     * @param string $percentage
     */
    public function i_set_grade_heatmap_palette_colour_start(string $number, string $percentage): void {
        $this->find('css', $this->palette_row_selector($number) . ' input[type="number"]')->setValue($percentage);
    }

    /**
     * CSS selector of one colour row of the palette editor.
     *
     * @param string $number Position of the colour, 1 for the lowest.
     * @return string
     */
    protected function palette_row_selector(string $number): string {
        return '[data-region="local-gradeheatmap-palette"] [data-region="rows"] > [data-region="row"]:nth-child(' .
            (int) $number . ')';
    }
}
