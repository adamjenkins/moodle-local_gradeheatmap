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

namespace local_gradeheatmap;

use moodle_page;
use moodle_url;

/**
 * Tests for the hook callbacks and settings.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Builds a page for a course at a URL.
     *
     * @param \stdClass $course
     * @param string $url
     * @return moodle_page
     */
    protected function make_page(\stdClass $course, string $url): moodle_page {
        $page = new moodle_page();
        $page->set_course($course);
        $page->set_context(\context_course::instance($course->id));
        $page->set_url(new moodle_url($url, ['id' => $course->id]));
        return $page;
    }

    /**
     * The heatmap loads on the grader report for teachers only.
     */
    public function test_should_load(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $this->assertTrue(hook_callbacks::should_load($this->make_page($course, '/grade/report/grader/index.php')));
        $this->assertFalse(hook_callbacks::should_load($this->make_page($course, '/grade/report/user/index.php')));
        $this->assertFalse(hook_callbacks::should_load($this->make_page($course, '/course/view.php')));

        set_config('enabled', 0, 'local_gradeheatmap');
        $this->assertFalse(hook_callbacks::should_load($this->make_page($course, '/grade/report/grader/index.php')));
        set_config('enabled', 1, 'local_gradeheatmap');

        $this->setUser($student);
        $this->assertFalse(hook_callbacks::should_load($this->make_page($course, '/grade/report/grader/index.php')));
    }

    /**
     * The per-user switch defaults to on and follows the preference.
     */
    public function test_is_enabled_for_user(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(hook_callbacks::is_enabled_for_user());
        set_user_preference(hook_callbacks::PREFERENCE, 0);
        $this->assertFalse(hook_callbacks::is_enabled_for_user());
    }

    /**
     * The style block carries the palette in effect: the course's own, else the site's.
     */
    public function test_custom_properties_css(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $css = hook_callbacks::get_custom_properties_css($course->id);
        $this->assertStringContainsString('--local-gradeheatmap-colour-1: #f28b82;', $css);
        $this->assertStringContainsString('--local-gradeheatmap-colour-5: #8ab4f8;', $css);
        $this->assertStringNotContainsString('--local-gradeheatmap-colour-6', $css);

        \local_gradeheatmap\local\course_settings::set(
            $course->id,
            null,
            \local_gradeheatmap\local\palette::from_input(
                [0, 10, 20, 30, 40, 50, 100],
                ['#000001', '#000002', '#000003', '#000004', '#000005', '#000006', '#000007']
            )
        );
        $css = hook_callbacks::get_custom_properties_css($course->id);
        $this->assertStringContainsString('--local-gradeheatmap-colour-7: #000007;', $css);
        $this->assertSame(1, substr_count($css, '</style>'));
        // Other courses keep the site palette.
        $this->assertStringContainsString('--local-gradeheatmap-colour-1: #f28b82;', hook_callbacks::get_custom_properties_css());
    }
}
