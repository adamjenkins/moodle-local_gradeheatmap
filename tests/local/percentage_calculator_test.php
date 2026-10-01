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

use grade_grade;
use grade_item;

/**
 * Tests for the percentage calculator.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(percentage_calculator::class)]
final class percentage_calculator_test extends \advanced_testcase {
    /**
     * Loads the gradebook library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Data for test_percentage.
     *
     * @return array
     */
    public static function percentage_provider(): array {
        return [
            'zero minimum' => [5.0, 0.0, 10.0, 50.0],
            'non-zero minimum' => [15.0, 10.0, 20.0, 50.0],
            'non-zero minimum, at the minimum' => [10.0, 10.0, 20.0, 0.0],
            'negative minimum' => [0.0, -10.0, 10.0, 50.0],
            'exactly the maximum' => [20.0, 10.0, 20.0, 100.0],
            'above the maximum (unlimited grades)' => [25.0, 10.0, 20.0, 100.0],
            'just below the maximum is never 100' => [19.99999, 0.0, 20.0, 99.99],
            'just below the maximum, rounded' => [19.99, 0.0, 20.0, 99.95],
            'below the minimum is clamped' => [5.0, 10.0, 20.0, 0.0],
            'empty grade' => [null, 0.0, 100.0, null],
            'no range' => [5.0, 5.0, 5.0, null],
            'inverted range' => [5.0, 10.0, 0.0, null],
        ];
    }

    /**
     * Tests the range arithmetic.
     *
     * @param float|null $value
     * @param float $min
     * @param float $max
     * @param float|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('percentage_provider')]
    public function test_percentage(?float $value, float $min, float $max, ?float $expected): void {
        $this->assertSame($expected, percentage_calculator::percentage($value, $min, $max));
    }

    /**
     * Creates a course with one student.
     *
     * @return array [course, student]
     */
    protected function create_course_with_student(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        return [$course, $student];
    }

    /**
     * Creates a manual grade item and returns it as a grade_item.
     *
     * @param array $record
     * @return grade_item
     */
    protected function create_item(array $record): grade_item {
        $data = $this->getDataGenerator()->create_grade_item($record);
        return grade_item::fetch(['id' => $data->id]);
    }

    /**
     * Writes a grade for a manual item.
     *
     * @param grade_item $item
     * @param int $userid
     * @param float|null $value
     */
    protected function grade(grade_item $item, int $userid, ?float $value): void {
        $item->update_final_grade($userid, $value, 'test');
    }

    /**
     * Regular items with a non-zero minimum, including the exact 100% boundary.
     */
    public function test_get_percentages_value_items(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();

        $item = $this->create_item(['courseid' => $course->id, 'grademin' => 10, 'grademax' => 20]);
        $full = $this->create_item(['courseid' => $course->id, 'grademin' => 10, 'grademax' => 20]);
        $nearlyfull = $this->create_item(['courseid' => $course->id, 'grademin' => 0, 'grademax' => 20]);
        $this->grade($item, $student->id, 15);
        $this->grade($full, $student->id, 20);
        $this->grade($nearlyfull, $student->id, 19.99999);
        grade_regrade_final_grades($course->id);

        $result = percentage_calculator::get_percentages($course->id, [$student->id], true);

        $this->assertSame(50.0, $result[$student->id][$item->id]);
        $this->assertSame(100.0, $result[$student->id][$full->id]);
        $this->assertSame(99.99, $result[$student->id][$nearlyfull->id]);
    }

    /**
     * Scale grades use their position in the scale.
     */
    public function test_get_percentages_scale(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Poor,Fair,Good,Excellent']);

        $item = $this->create_item(['courseid' => $course->id, 'scaleid' => $scale->id, 'grademin' => 1, 'grademax' => 4]);
        $top = $this->create_item(['courseid' => $course->id, 'scaleid' => $scale->id, 'grademin' => 1, 'grademax' => 4]);
        $bottom = $this->create_item(['courseid' => $course->id, 'scaleid' => $scale->id, 'grademin' => 1, 'grademax' => 4]);
        $this->grade($item, $student->id, 3); // Good: position 2 of 0..3.
        $this->grade($top, $student->id, 4);
        $this->grade($bottom, $student->id, 1);
        grade_regrade_final_grades($course->id);

        $result = percentage_calculator::get_percentages($course->id, [$student->id], true);

        $this->assertSame(66.67, $result[$student->id][$item->id]);
        $this->assertSame(100.0, $result[$student->id][$top->id]);
        $this->assertSame(0.0, $result[$student->id][$bottom->id]);
    }

    /**
     * Empty grades, text items, items without a range and excluded grades are not coloured.
     */
    public function test_get_percentages_uncoloured(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $graded = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $ungraded = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $text = $this->create_item(['courseid' => $course->id, 'gradetype' => GRADE_TYPE_TEXT]);
        $norange = $this->create_item(['courseid' => $course->id, 'grademin' => 0, 'grademax' => 0]);
        $excluded = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $this->grade($graded, $student->id, 5);
        $this->grade($norange, $student->id, 0);
        $this->grade($excluded, $student->id, 5);
        $text->update_final_grade($student->id, null, 'test', 'Some feedback');
        $grade = grade_grade::fetch(['itemid' => $excluded->id, 'userid' => $student->id]);
        $grade->set_excluded(true);
        grade_regrade_final_grades($course->id);

        $result = percentage_calculator::get_percentages($course->id, [$student->id, $other->id], true);

        $this->assertSame(50.0, $result[$student->id][$graded->id]);
        $this->assertArrayNotHasKey($ungraded->id, $result[$student->id]);
        $this->assertArrayNotHasKey($text->id, $result[$student->id]);
        $this->assertArrayNotHasKey($norange->id, $result[$student->id]);
        $this->assertArrayNotHasKey($excluded->id, $result[$student->id]);
        // A user with no grades at all gets no entry (the course total has no grade either).
        $this->assertArrayNotHasKey($other->id, $result);
    }

    /**
     * Overridden grades use the overridden final grade, including on the course total.
     */
    public function test_get_percentages_overridden(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();

        $item = $this->create_item(['courseid' => $course->id, 'grademin' => 10, 'grademax' => 20]);
        $this->grade($item, $student->id, 12);
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
        $grade->set_overridden(true, false);
        $grade->finalgrade = 18;
        $grade->update();
        grade_regrade_final_grades($course->id);

        $courseitem = grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($student->id, 5, 'test');
        $coursegrade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $student->id]);
        $this->assertTrue($coursegrade->is_overridden());

        $result = percentage_calculator::get_percentages($course->id, [$student->id], true);

        $this->assertSame(80.0, $result[$student->id][$item->id]);
        // Natural aggregation of one 10-20 item gives the course total a raw range of 0-20 (min not summed).
        $expected = percentage_calculator::percentage(
            5.0,
            (float) $coursegrade->get_grade_min(),
            (float) $coursegrade->get_grade_max()
        );
        $this->assertSame($expected, $result[$student->id][$courseitem->id]);
        $this->assertSame(25.0, $result[$student->id][$courseitem->id]);
    }

    /**
     * Category and course totals are coloured from their aggregated values.
     */
    public function test_get_percentages_totals(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();
        $category = $this->getDataGenerator()->create_grade_category(['courseid' => $course->id]);

        $item1 = $this->create_item(['courseid' => $course->id, 'categoryid' => $category->id, 'grademax' => 10]);
        $item2 = $this->create_item(['courseid' => $course->id, 'categoryid' => $category->id, 'grademax' => 30]);
        $this->grade($item1, $student->id, 10);
        $this->grade($item2, $student->id, 20);
        grade_regrade_final_grades($course->id);

        $categoryitem = grade_item::fetch(['iteminstance' => $category->id, 'itemtype' => 'category']);
        $courseitem = grade_item::fetch_course_item($course->id);

        $result = percentage_calculator::get_percentages($course->id, [$student->id], true);

        // Natural aggregation: 30 of 40.
        $this->assertSame(75.0, $result[$student->id][$categoryitem->id]);
        $this->assertSame(75.0, $result[$student->id][$courseitem->id]);
    }

    /**
     * Hidden grades are coloured for viewers who can see them and left out for those who cannot.
     */
    public function test_get_percentages_hidden(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();

        $visible = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $hidden = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $locked = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $this->grade($visible, $student->id, 5);
        $this->grade($hidden, $student->id, 10);
        $this->grade($locked, $student->id, 2);
        $hidden->set_hidden(1);
        $locked->set_locked(1);
        grade_regrade_final_grades($course->id);
        $courseitem = grade_item::fetch_course_item($course->id);

        $all = percentage_calculator::get_percentages($course->id, [$student->id], true);
        $this->assertSame(100.0, $all[$student->id][$hidden->id]);
        $this->assertSame(20.0, $all[$student->id][$locked->id]);
        // Course total: 17 of 30.
        $this->assertSame(56.67, $all[$student->id][$courseitem->id]);

        $nothidden = percentage_calculator::get_percentages($course->id, [$student->id], false);
        $this->assertSame(50.0, $nothidden[$student->id][$visible->id]);
        $this->assertSame(20.0, $nothidden[$student->id][$locked->id]);
        $this->assertArrayNotHasKey($hidden->id, $nothidden[$student->id]);
        // Without the hidden item the total is 7 of 20, as the grader report shows it.
        $this->assertSame(35.0, $nothidden[$student->id][$courseitem->id]);
    }

    /**
     * Grades of other courses and other users are not returned.
     */
    public function test_get_percentages_scope(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->create_course_with_student();
        [$othercourse] = $this->create_course_with_student();
        $this->getDataGenerator()->enrol_user($student->id, $othercourse->id, 'student');
        $classmate = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $item = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        $otheritem = $this->create_item(['courseid' => $othercourse->id, 'grademax' => 10]);
        $this->grade($item, $student->id, 5);
        $this->grade($item, $classmate->id, 6);
        $this->grade($otheritem, $student->id, 7);
        grade_regrade_final_grades($course->id);
        grade_regrade_final_grades($othercourse->id);

        $result = percentage_calculator::get_percentages($course->id, [$student->id], true);

        $this->assertSame([(int) $student->id], array_keys($result));
        $this->assertArrayNotHasKey($otheritem->id, $result[$student->id]);
        $this->assertSame([], percentage_calculator::get_percentages($course->id, [], true));
    }

    /**
     * The grades of many users are fetched with a bounded number of queries.
     */
    public function test_get_percentages_bulk_queries(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = $this->create_item(['courseid' => $course->id, 'grademax' => 10]);
        }
        $userids = [];
        for ($i = 0; $i < 30; $i++) {
            $user = $generator->create_and_enrol($course, 'student');
            $userids[] = $user->id;
            foreach ($items as $item) {
                $this->grade($item, $user->id, $i % 11);
            }
        }
        grade_regrade_final_grades($course->id);

        $before = $DB->perf_get_reads();
        $result = percentage_calculator::get_percentages($course->id, $userids, true);
        $reads = $DB->perf_get_reads() - $before;

        $this->assertCount(30, $result);
        // Independent of users x items (150 cells, plus totals): items, grades, and a few cached settings.
        $this->assertLessThan(15, $reads);
    }
}
