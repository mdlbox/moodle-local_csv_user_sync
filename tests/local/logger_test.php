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
 * Tests for the plugin logger.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\logger
 */
final class logger_test extends \advanced_testcase {
    /**
     * Multibyte characters survive sanitization whatever the server locale.
     */
    public function test_sanitize_keeps_multibyte_characters(): void {
        $message = "Cartella \"Unità amministrativa 1\"\tcittà";

        $sanitized = logger::sanitize_log_message($message);

        $this->assertSame('Cartella "Unità amministrativa 1" città', $sanitized);
        $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
    }

    /**
     * Without detailed logging only summary lines are stored; the other entries are only counted.
     */
    public function test_compact_logging_stores_only_summary(): void {
        global $DB;
        $this->resetAfterTest();
        $logger = new logger('phpunit', false, false);

        ob_start();
        $logger->summary('Started');
        $logger->info('Row processed', 2);
        $logger->warning('Folder visible');
        $logger->error('Row failed', 3);
        ob_end_clean();

        $messages = $DB->get_fieldset_select('local_csv_user_sync_log', 'message', 'runid = ?', ['phpunit']);
        $this->assertSame(['Started'], $messages);
        $this->assertSame(1, $logger->get_count('warning'));
        $this->assertSame(1, $logger->get_count('error'));
    }

    /**
     * Invalid UTF-8 never reaches the database.
     */
    public function test_sanitize_fixes_invalid_utf8(): void {
        $this->assertTrue(mb_check_encoding(logger::sanitize_log_message("Unit\xC3 test"), 'UTF-8'));
    }
}
