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

namespace local_gradeheatmap\external;

use core_external\external_api;
use grade_item;
use required_capability_exception;

/**
 * Tests for the get_percentages external function.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_percentages::class)]
final class get_percentages_test extends \core_external\tests\externallib_testcase {
    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $teacher;

    /** @var \stdClass[] */
    protected $students = [];

    /** @var grade_item */
    protected $item;

    /**
     * Creates a course with a teacher, three students and one graded item.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $data = $generator->create_grade_item(['courseid' => $this->course->id, 'grademin' => 10, 'grademax' => 20]);
        $this->item = grade_item::fetch(['id' => $data->id]);
        foreach ([12, 15, 20] as $i => $value) {
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student');
            $this->item->update_final_grade($this->students[$i]->id, $value, 'test');
        }
        grade_regrade_final_grades($this->course->id);
    }

    /**
     * Calls the function and returns the item's percentages keyed by user id.
     *
     * @param int[] $userids
     * @return array
     */
    protected function call(array $userids): array {
        $result = get_percentages::execute($this->course->id, $userids);
        $result = external_api::clean_returnvalue(get_percentages::execute_returns(), $result);
        $percentages = [];
        foreach ($result['grades'] as $grade) {
            if ($grade['itemid'] == $this->item->id) {
                $percentages[$grade['userid']] = $grade['percentage'];
            }
        }
        return $percentages;
    }

    /**
     * A teacher who can access all groups gets the percentages of every requested student.
     */
    public function test_execute_as_manager(): void {
        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'manager');
        $this->setUser($manager);

        $percentages = $this->call(array_column($this->students, 'id'));

        $this->assertEquals([
            $this->students[0]->id => 20.0,
            $this->students[1]->id => 50.0,
            $this->students[2]->id => 100.0,
        ], $percentages);
    }

    /**
     * Users who are not gradable in the course are left out, whatever the client sends.
     */
    public function test_execute_filters_users_outside_course(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($outsider->id, $othercourse->id, 'student');
        $this->setAdminUser();

        $percentages = $this->call([$this->students[0]->id, $outsider->id, $this->teacher->id]);

        $this->assertSame([(int) $this->students[0]->id], array_keys($percentages));
    }

    /**
     * In separate groups mode a teacher without accessallgroups gets only their group members.
     */
    public function test_execute_separate_groups(): void {
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->students[1]->id]);
        // The non-editing teacher archetype has no accessallgroups by default; make that explicit.
        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PROHIBIT,
            $teacherrole,
            \context_course::instance($this->course->id),
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->teacher);
        $this->assertFalse(has_capability('moodle/site:accessallgroups', \context_course::instance($this->course->id)));

        $percentages = $this->call(array_column($this->students, 'id'));

        $this->assertSame([(int) $this->students[1]->id], array_keys($percentages));
    }

    /**
     * Students cannot call the function.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->students[0]);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage(get_string('nopermissions', 'error', get_capability_string('gradereport/grader:view')));
        get_percentages::execute($this->course->id, [$this->students[0]->id]);
    }

    /**
     * A teacher without the heatmap capability cannot call the function.
     */
    public function test_execute_requires_heatmap_capability(): void {
        $teacherrole = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability(
            'local/gradeheatmap:view',
            CAP_PROHIBIT,
            $teacherrole,
            \context_course::instance($this->course->id),
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->teacher);

        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage(get_string('nopermissions', 'error', get_capability_string('local/gradeheatmap:view')));
        get_percentages::execute($this->course->id, [$this->students[0]->id]);
    }

    /**
     * The function still answers while the course needs regrading (core's graded users iterator would throw).
     */
    public function test_execute_while_regrade_pending(): void {
        $other = \grade_item::fetch(['id' => $this->getDataGenerator()->create_grade_item(
            ['courseid' => $this->course->id, 'grademax' => 10]
        )->id]);
        $other->force_regrading();
        $this->setAdminUser();

        $result = get_percentages::execute($this->course->id, array_column($this->students, 'id'));

        $this->assertIsArray($result['grades']);
    }

    /**
     * Requests spanning several filtering chunks still find every permitted user.
     */
    public function test_execute_many_users(): void {
        $this->setAdminUser();
        // 2500 ids that are not users of the course, with the real students at the end, beyond the first chunks.
        $userids = array_merge(range(9000001, 9002500), array_column($this->students, 'id'));

        $percentages = $this->call($userids);

        $this->assertEqualsCanonicalizing(array_map('intval', array_column($this->students, 'id')), array_keys($percentages));
    }

    /**
     * Requests for more users than a grader report page can show are refused.
     */
    public function test_execute_too_many_users(): void {
        $this->setAdminUser();
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/Too many users requested/');
        get_percentages::execute($this->course->id, range(1, get_percentages::MAX_USERS + 1));
    }

    /**
     * Nothing is returned while the plugin is disabled site-wide.
     */
    public function test_execute_disabled(): void {
        set_config('enabled', 0, 'local_gradeheatmap');
        $this->setAdminUser();

        $this->assertSame([], $this->call(array_column($this->students, 'id')));
    }
}
