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

/**
 * Tests for the uninstall cleanup.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_local_gradeheatmap_uninstall')]
final class uninstall_test extends \advanced_testcase {
    /**
     * Uninstalling removes every user's heatmap switch and leaves other preferences alone.
     */
    public function test_uninstall_removes_preferences(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/gradeheatmap/db/uninstall.php');
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        set_user_preference(hook_callbacks::PREFERENCE, 0, $user1);
        set_user_preference(hook_callbacks::PREFERENCE, 1, $user2);
        set_user_preference('local_gradeheatmap_unrelated_test', 'keep', $user1);
        $this->assertEquals(2, $DB->count_records('user_preferences', ['name' => hook_callbacks::PREFERENCE]));

        $this->assertTrue(xmldb_local_gradeheatmap_uninstall());

        $this->assertEquals(0, $DB->count_records('user_preferences', ['name' => hook_callbacks::PREFERENCE]));
        $this->assertEquals(1, $DB->count_records('user_preferences', ['name' => 'local_gradeheatmap_unrelated_test']));
    }
}
