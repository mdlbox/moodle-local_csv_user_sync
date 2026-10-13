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

namespace local_csv_user_sync\local;

/**
 * Tests for the CSV reader.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\csv_reader
 */
final class csv_reader_test extends \advanced_testcase {
    /**
     * Values starting with + or - (phone numbers, codes) are imported unchanged.
     */
    public function test_values_are_not_prefixed_on_import(): void {
        $path = make_request_directory() . '/users.csv';
        file_put_contents($path, "username;phone1;department\ntest.user;+41 79 123 45 67;-5\n");

        $rows = iterator_to_array((new csv_reader($path, ';', 'UTF-8'))->get_rows(), false);

        $this->assertCount(1, $rows);
        $this->assertSame('+41 79 123 45 67', $rows[0]['data']['phone1']);
        $this->assertSame('-5', $rows[0]['data']['department']);
    }
}
