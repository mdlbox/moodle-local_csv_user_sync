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

namespace local_csv_user_sync\task;

use local_csv_user_sync\local\folder_source;

/**
 * Integration tests for the synchronization task with Folder sources.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\task\sync_task
 */
final class sync_task_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Set up a source course and default plugin config.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        set_config('sourcecourseid', $this->course->id, 'local_csv_user_sync');
        set_config('sendemail', 0, 'local_csv_user_sync');
        set_config('dryrun', 0, 'local_csv_user_sync');
        set_config('delimiter', ';', 'local_csv_user_sync');
        set_config('encoding', 'UTF-8', 'local_csv_user_sync');
    }

    /**
     * Create a hidden Folder activity containing one CSV uploaded by the administrator.
     *
     * @param string $content CSV content
     * @return \stdClass folder module record
     */
    private function create_folder_with_csv(string $content): \stdClass {
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $this->course->id, 'visible' => 0]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($folder->cmid)->id,
            'component' => folder_source::COMPONENT,
            'filearea' => folder_source::FILEAREA,
            'itemid' => folder_source::ITEMID,
            'filepath' => '/',
            'filename' => 'users.csv',
            'userid' => get_admin()->id,
        ], $content);

        return $folder;
    }

    /**
     * Execute the task, discarding its output.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        try {
            (new sync_task())->execute();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Folders are processed in sequence and a missing folder does not stop the others.
     */
    public function test_folders_are_processed_in_sequence(): void {
        $first = $this->create_folder_with_csv(
            "username;firstname;lastname;email;city\n" .
            "test.user;Test;User;test.user@example.com;Lugano\n" .
            "second.user;Second;User;second.user@example.com;Locarno\n"
        );
        $second = $this->create_folder_with_csv(
            "username;firstname;lastname;email;city\n" .
            "test.user;Test;User;test.user@example.com;Bellinzona\n" .
            "test.user;Test;User;pippo@example.org;Mendrisio\n"
        );
        set_config('sourcefolders', implode(',', [$second->cmid, 999999, $first->cmid]), 'local_csv_user_sync');

        $this->run_task();

        // The second folder comes later on the course page: its values win.
        $this->assertSame('Bellinzona', \core_user::get_user_by_username('test.user')->city);
        $this->assertSame('Locarno', \core_user::get_user_by_username('second.user')->city);
        $this->assertSame('pippo@example.org', \core_user::get_user_by_username('test.user1')->email);
    }

    /**
     * Email conflicts are reported in one email to the configured address.
     */
    public function test_email_conflicts_are_notified(): void {
        set_config('allowaccountssameemail', 0);
        set_config('conflictemail', 'conflicts@example.com', 'local_csv_user_sync');
        $this->getDataGenerator()->create_user(['username' => 'other.user', 'email' => 'pippo@example.org']);
        $folder = $this->create_folder_with_csv(
            "username;firstname;lastname;email\n" .
            "test.user;Test;User;pippo@example.org\n"
        );
        set_config('sourcefolders', $folder->cmid, 'local_csv_user_sync');

        $sink = $this->redirectEmails();
        $this->run_task();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertFalse(\core_user::get_user_by_username('test.user'));
        $this->assertCount(1, $messages);
        $this->assertSame('conflicts@example.com', $messages[0]->to);
        $this->assertStringContainsString('other.user', quoted_printable_decode($messages[0]->body));
    }

    /**
     * Deletions requested in a folder are executed at the end of the run; later rows for the account are skipped.
     */
    public function test_user_delete_column(): void {
        set_config('userdeleteenabled', 1, 'local_csv_user_sync');
        set_config('userdeletemax', 5, 'local_csv_user_sync');
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $kept = $this->getDataGenerator()->create_user(['username' => 'kept.user', 'email' => 'kept.user@example.com']);
        $first = $this->create_folder_with_csv(
            "username;firstname;lastname;email;user_delete\n" .
            "test.user;Test;User;test.user@example.com;1\n" .
            "kept.user;Kept;User;someone.else@example.org;1\n"
        );
        $second = $this->create_folder_with_csv(
            "username;firstname;lastname;email;city\n" .
            "test.user;Test;User;test.user@example.com;Lugano\n"
        );
        set_config('sourcefolders', implode(',', [$first->cmid, $second->cmid]), 'local_csv_user_sync');

        $sink = $this->redirectEmails();
        $this->run_task();
        $sink->close();

        $this->assertNotEmpty(\core_user::get_user($user->id)->deleted);
        // The email does not match exactly: the account is kept.
        $this->assertEmpty(\core_user::get_user($kept->id)->deleted);
    }

    /**
     * A folder whose files can be managed by a user without moodle/user:delete cannot request deletions,
     * while its other rows are still processed.
     */
    public function test_user_delete_refused_when_a_folder_manager_cannot_delete(): void {
        set_config('userdeleteenabled', 1, 'local_csv_user_sync');
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $folder = $this->create_folder_with_csv(
            "username;firstname;lastname;email;user_delete\n" .
            "test.user;Test;User;test.user@example.com;1\n" .
            "other.user;Other;User;other.user@example.com;\n"
        );
        set_config('sourcefolders', $folder->cmid, 'local_csv_user_sync');

        // A teacher of the source course who may provide CSV files, but not delete accounts.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(folder_source::UPLOADCAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->run_task();

        $this->assertEmpty(\core_user::get_user($user->id)->deleted);
        $this->assertNotFalse(\core_user::get_user_by_username('other.user'));
    }

    /**
     * A file path source can never request deletions.
     */
    public function test_user_delete_refused_for_file_path_source(): void {
        set_config('userdeleteenabled', 1, 'local_csv_user_sync');
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $path = make_request_directory() . '/users.csv';
        file_put_contents($path, "username;firstname;lastname;email;user_delete\ntest.user;Test;User;test.user@example.com;1\n");
        set_config('csvpath', $path, 'local_csv_user_sync');

        $this->run_task();

        $this->assertEmpty(\core_user::get_user($user->id)->deleted);
    }

    /**
     * Nothing is written and no email is sent in dry-run mode.
     */
    public function test_dry_run_writes_nothing(): void {
        set_config('dryrun', 1, 'local_csv_user_sync');
        $folder = $this->create_folder_with_csv(
            "username;firstname;lastname;email\n" .
            "test.user;Test;User;test.user@example.com\n"
        );
        set_config('sourcefolders', $folder->cmid, 'local_csv_user_sync');

        $sink = $this->redirectEmails();
        $this->run_task();
        $this->assertSame(0, $sink->count());
        $sink->close();

        $this->assertFalse(\core_user::get_user_by_username('test.user'));
    }
}
