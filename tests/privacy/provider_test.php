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

namespace local_gradeheatmap\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_gradeheatmap\hook_callbacks;

/**
 * Tests for the privacy provider.
 *
 * @package    local_gradeheatmap
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /**
     * The preference is declared.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_gradeheatmap'));
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame(hook_callbacks::PREFERENCE, $items[0]->get_name());
    }

    /**
     * A stored preference is exported.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(hook_callbacks::PREFERENCE, 0, $user);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $preferences = $writer->get_user_preferences('local_gradeheatmap');
        $this->assertSame(get_string('no'), $preferences->{hook_callbacks::PREFERENCE}->value);
    }

    /**
     * Nothing is exported for a user who never used the switch.
     */
    public function test_export_user_preferences_none(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences($user->id);

        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
