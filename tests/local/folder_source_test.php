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
 * Tests for Folder activity CSV sources.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\folder_source
 */
final class folder_source_test extends \advanced_testcase {
    /** @var logger */
    private logger $logger;

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->logger = new logger('phpunit', true, false);
    }

    /**
     * Add a file to a Folder activity through the File API.
     *
     * @param \stdClass $folder folder module record
     * @param string $filename
     * @param int $userid file author
     * @param int $timemodified
     * @return \stored_file
     */
    private function add_file(\stdClass $folder, string $filename, int $userid, int $timemodified = 0): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($folder->cmid)->id,
            'component' => folder_source::COMPONENT,
            'filearea' => folder_source::FILEAREA,
            'itemid' => folder_source::ITEMID,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'timemodified' => $timemodified ?: time(),
        ], "username;firstname;lastname;email\n");
    }

    /**
     * Returns the cm_info of a folder.
     *
     * @param \stdClass $folder
     * @return \cm_info
     */
    private function get_cm(\stdClass $folder): \cm_info {
        return get_fast_modinfo($folder->course)->get_cm($folder->cmid);
    }

    /**
     * Returns true when the plugin log contains an entry of the given level.
     *
     * @param string $level
     * @return bool
     */
    private function has_log(string $level): bool {
        global $DB;
        return $DB->record_exists('local_csv_user_sync_log', ['runid' => 'phpunit', 'level' => $level]);
    }

    /**
     * Run a callable discarding task output.
     *
     * @param callable $callback
     * @return mixed
     */
    private function quietly(callable $callback) {
        ob_start();
        try {
            return $callback();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * A single CSV uploaded by an administrator is returned; other file types are ignored.
     */
    public function test_single_csv_is_returned(): void {
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $this->add_file($folder, 'readme.txt', get_admin()->id);
        $this->add_file($folder, 'users.CSV', get_admin()->id);

        $file = $this->quietly(fn() => folder_source::get_csv_file($this->get_cm($folder), $this->logger));
        $this->assertSame('users.CSV', $file->get_filename());
        $this->assertFalse($this->has_log('warning'));
    }

    /**
     * A folder without CSV is skipped with a warning.
     */
    public function test_folder_without_csv(): void {
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);

        $file = $this->quietly(fn() => folder_source::get_csv_file($this->get_cm($folder), $this->logger));
        $this->assertNull($file);
        $this->assertTrue($this->has_log('warning'));
    }

    /**
     * With more than one CSV the most recent one is used and a warning is logged.
     */
    public function test_most_recent_csv_is_used(): void {
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $this->add_file($folder, 'old.csv', get_admin()->id, time() - DAYSECS);
        $this->add_file($folder, 'new.csv', get_admin()->id, time());

        $file = $this->quietly(fn() => folder_source::get_csv_file($this->get_cm($folder), $this->logger));
        $this->assertSame('new.csv', $file->get_filename());
        $this->assertTrue($this->has_log('warning'));
    }

    /**
     * A CSV uploaded by a user without the capability (for example a course teacher) is rejected.
     */
    public function test_file_from_untrusted_author_is_rejected(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $this->add_file($folder, 'users.csv', $teacher->id);

        $file = $this->quietly(fn() => folder_source::get_csv_file($this->get_cm($folder), $this->logger));
        $this->assertNull($file);
        $this->assertTrue($this->has_log('error'));

        // Granting the capability in system context makes the file acceptable.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(folder_source::UPLOADCAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $teacher->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $file = $this->quietly(fn() => folder_source::get_csv_file($this->get_cm($folder), $this->logger));
        $this->assertSame('users.csv', $file->get_filename());
    }

    /**
     * A folder whose files can be managed by an untrusted user is refused, even for an administrator's file.
     */
    public function test_folder_with_untrusted_manager_is_rejected(): void {
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $this->add_file($folder, 'users.csv', get_admin()->id);
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $rejected = false;
        $file = $this->quietly(function () use ($folder, &$rejected) {
            return folder_source::get_csv_file($this->get_cm($folder), $this->logger, $rejected);
        });
        $this->assertNull($file);
        $this->assertTrue($rejected);
    }

    /**
     * Folders are returned in course page order; ids that are not folders of the course are reported.
     */
    public function test_resolve_folders(): void {
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $second = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $elsewhere = $this->getDataGenerator()->create_module('folder', ['course' => $othercourse->id]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $config = (object)[
            'sourcecourseid' => $course->id,
            'sourcefolders' => implode(',', [$second->cmid, $first->cmid, $elsewhere->cmid, $page->cmid]),
        ];
        $folders = $this->quietly(fn() => folder_source::resolve_folders($config, $this->logger));

        $this->assertSame([(int)$first->cmid, (int)$second->cmid], array_map(fn($cm) => (int)$cm->id, $folders));
        $this->assertTrue($this->has_log('error'));
    }

    /**
     * Setting choices list the folders of the configured course.
     */
    public function test_folder_choices(): void {
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id, 'name' => 'Docenti']);
        $this->assertSame([], folder_source::get_folder_choices());

        set_config('sourcecourseid', $course->id, 'local_csv_user_sync');
        $choices = folder_source::get_folder_choices();
        $this->assertArrayHasKey((int)$folder->cmid, $choices);
        $this->assertStringContainsString('Docenti', $choices[(int)$folder->cmid]);
    }
}
