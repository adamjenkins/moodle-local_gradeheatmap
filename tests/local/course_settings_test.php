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

use local_gradeheatmap\hook_callbacks;
use local_gradeheatmap\settings;

/**
 * Tests for the course settings (mode and palette chosen by teachers).
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_settings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(settings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class course_settings_test extends \advanced_testcase {
    /**
     * A small custom palette.
     *
     * @return palette
     */
    protected function custom_palette(): palette {
        return palette::from_input([0, 60, 100], ['#000000', '#777777', '#0000ff']);
    }

    /**
     * Course settings override the site settings, for that course only.
     */
    public function test_set_and_resolve(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        set_config('mode', settings::MODE_GRADIENT, 'local_gradeheatmap');

        $this->assertSame(settings::MODE_GRADIENT, settings::get_mode($course->id));
        $this->assertTrue(settings::get_palette($course->id)->equals(palette::default()));

        course_settings::set($course->id, settings::MODE_BANDS, $this->custom_palette());
        $this->assertSame(settings::MODE_BANDS, settings::get_mode($course->id));
        $this->assertTrue(settings::get_palette($course->id)->equals($this->custom_palette()));
        $this->assertSame(settings::MODE_GRADIENT, settings::get_mode($other->id));
        $this->assertTrue(settings::get_palette($other->id)->equals(palette::default()));

        // Mode only: the palette follows the site again.
        course_settings::set($course->id, settings::MODE_BANDS, null);
        $this->assertTrue(settings::get_palette($course->id)->equals(palette::default()));
        $this->assertSame(1, \core\di::get(\moodle_database::class)->count_records(course_settings::TABLE));

        // Neither: the row goes.
        course_settings::set($course->id, null, null);
        $this->assertSame(0, \core\di::get(\moodle_database::class)->count_records(course_settings::TABLE));
        $this->assertSame(settings::MODE_GRADIENT, settings::get_mode($course->id));
    }

    /**
     * Saving the preferences section stores valid settings and refuses an invalid palette.
     */
    public function test_save_course_settings(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(hook_callbacks::save_course_settings(
            $course->id,
            settings::MODE_BANDS,
            true,
            ['0', '60', '100'],
            ['#000000', '#777777', '#0000ff']
        ));
        $this->assertSame(settings::MODE_BANDS, course_settings::get_mode($course->id));
        $this->assertTrue(course_settings::get_palette($course->id)->equals($this->custom_palette()));

        $context = hook_callbacks::export_course_settings($course->id, $PAGE->get_renderer('core'));
        $this->assertTrue($context['usecustom']);
        $this->assertCount(3, $context['editor']['rows']);
        $this->assertFalse($context['editor']['disabled']);
        $this->assertSame([false, false, true], array_column($context['modes'], 'selected'));

        // An invalid palette changes nothing.
        $this->assertFalse(hook_callbacks::save_course_settings(
            $course->id,
            '',
            true,
            ['0', '50', '40'],
            ['#000000', '#111111', '#222222']
        ));
        $this->assertSame(settings::MODE_BANDS, course_settings::get_mode($course->id));

        // Back to the site settings; an unknown mode means the site mode.
        $this->assertTrue(hook_callbacks::save_course_settings($course->id, 'rainbow', false, [], []));
        $this->assertNull(course_settings::get_mode($course->id));
        $this->assertNull(course_settings::get_palette($course->id));
        $context = hook_callbacks::export_course_settings($course->id, $PAGE->get_renderer('core'));
        $this->assertFalse($context['usecustom']);
        $this->assertTrue($context['editor']['disabled']);
        $this->assertCount(5, $context['editor']['rows']);
    }

    /**
     * Teachers with the capability manage the settings on the grader report preferences page only.
     */
    public function test_can_manage_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $makepage = function (string $path) use ($course): \moodle_page {
            $page = new \moodle_page();
            $page->set_course($course);
            $page->set_context(\context_course::instance($course->id));
            $page->set_url(new \moodle_url($path, ['id' => $course->id]));
            return $page;
        };

        $this->setUser($editingteacher);
        $this->assertTrue(hook_callbacks::can_manage_settings($makepage(hook_callbacks::PREFERENCES_PATH)));
        $this->assertFalse(hook_callbacks::can_manage_settings($makepage(hook_callbacks::GRADER_PATH)));

        // Non-editing teachers see the heatmap but cannot change the course settings.
        $this->setUser($teacher);
        $this->assertFalse(hook_callbacks::can_manage_settings($makepage(hook_callbacks::PREFERENCES_PATH)));
        $this->assertTrue(hook_callbacks::should_load($makepage(hook_callbacks::GRADER_PATH)));
    }

    /**
     * Deleting a course deletes its settings.
     */
    public function test_course_deleted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        course_settings::set($course->id, settings::MODE_BANDS, null);
        course_settings::set($other->id, settings::MODE_BANDS, null);

        delete_course($course, false);

        $this->assertNull(course_settings::get_mode($course->id));
        $this->assertSame(settings::MODE_BANDS, course_settings::get_mode($other->id));
    }

    /**
     * Backs a course up and restores it into a new course.
     *
     * @param \stdClass $course
     * @param callable|null $tamper Called with the backup's temp directory before the restore runs.
     * @return int The new course id.
     */
    protected function backup_and_restore(\stdClass $course, ?callable $tamper = null): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        if ($tamper) {
            $tamper(make_backup_temp_directory($backupid));
        }

        $newcourseid = \restore_dbops::create_new_course('Restored', 'R' . $course->id, $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return (int) $newcourseid;
    }

    /**
     * The settings travel with a course backup into the restored course.
     */
    public function test_backup_restore(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        course_settings::set($course->id, settings::MODE_BANDS, $this->custom_palette());

        $newcourseid = $this->backup_and_restore($course);

        $this->assertSame(settings::MODE_BANDS, course_settings::get_mode($newcourseid));
        $this->assertTrue(course_settings::get_palette($newcourseid)->equals($this->custom_palette()));
    }

    /**
     * A backup carrying an unknown mode and a palette with markup restores as the site settings.
     */
    public function test_restore_rejects_tampered_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('mode', settings::MODE_GRADIENT, 'local_gradeheatmap');

        $course = $this->getDataGenerator()->create_course();
        course_settings::set($course->id, settings::MODE_BANDS, $this->custom_palette());

        $evilpalette = '[{"from":0,"colour":"red;}</style><script>alert(1)</script>"},{"from":50,"colour":"#000000"}]';
        $tamper = function (string $dir) use ($evilpalette): void {
            $file = $dir . '/course/course.xml';
            $replaced = 0;
            $xml = preg_replace_callback(
                '#<plugin_local_gradeheatmap_course>.*?</plugin_local_gradeheatmap_course>#s',
                function (array $match) use ($evilpalette): string {
                    $block = preg_replace('#<mode>.*?</mode>#s', '<mode>evil</mode>', $match[0]);
                    return preg_replace(
                        '#<palette>.*?</palette>#s',
                        '<palette>' . htmlspecialchars($evilpalette, ENT_XML1) . '</palette>',
                        $block
                    );
                },
                file_get_contents($file),
                -1,
                $replaced
            );
            // Guard against a vacuous pass: the tampered values must really be in the backup.
            $this->assertSame(1, $replaced);
            $this->assertStringContainsString('<mode>evil</mode>', $xml);
            $this->assertStringContainsString('&lt;/style&gt;&lt;script&gt;', $xml);
            file_put_contents($file, $xml);
        };

        $newcourseid = $this->backup_and_restore($course, $tamper);

        $this->assertFalse($DB->record_exists(course_settings::TABLE, ['courseid' => $newcourseid]));
        $this->assertNull(course_settings::get_mode($newcourseid));
        $this->assertNull(course_settings::get_palette($newcourseid));
        $this->assertSame(settings::MODE_GRADIENT, settings::get_mode($newcourseid));
        $this->assertTrue(settings::get_palette($newcourseid)->equals(settings::get_site_palette()));
    }
}
