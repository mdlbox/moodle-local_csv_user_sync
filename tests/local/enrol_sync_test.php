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
 * Tests for the enrolment synchronizer.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\enrol_sync
 */
final class enrol_sync_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Synchronize one enrolment row, discarding task output.
     *
     * @param int $userid
     * @param string $courseshortname
     * @param array $config
     * @param string $deleted
     * @return string status
     */
    private function enrol(int $userid, string $courseshortname, array $config = [], string $deleted = '0'): string {
        $sync = new enrol_sync(new logger('phpunit', true, false), $config + ['dryrun' => false]);
        ob_start();
        try {
            $result = $sync->sync($userid, [
                'course_shortname' => $courseshortname,
                'role_shortname' => 'student',
                'deleted' => $deleted,
            ], 2);
        } finally {
            ob_end_clean();
        }
        return $result['status'];
    }

    /**
     * The site front page and the source folders course are never enrolled into.
     */
    public function test_excluded_courses(): void {
        $user = $this->getDataGenerator()->create_user();
        $sourcecourse = $this->getDataGenerator()->create_course(['shortname' => 'source']);
        $course = $this->getDataGenerator()->create_course(['shortname' => 'regular']);
        $config = ['sourcecourseid' => $sourcecourse->id];

        $this->assertSame('error', $this->enrol((int)$user->id, get_site()->shortname, $config));
        $this->assertSame('error', $this->enrol((int)$user->id, 'source', $config));
        $this->assertFalse(is_enrolled(\context_course::instance($sourcecourse->id), $user));

        $this->assertSame('created', $this->enrol((int)$user->id, 'regular', $config));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user));
    }

    /**
     * A disabled manual enrolment instance is never used to enrol, but enrolments in it can still be removed.
     */
    public function test_disabled_manual_instance(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'regular']);
        $enrolled = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($enrolled->id, $course->id, 'student', 'manual');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'manual']);

        $this->assertSame('error', $this->enrol((int)$user->id, 'regular'));
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $user->id]));
        // No second manual instance was created next to the disabled one.
        $this->assertSame(1, $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']));

        $this->assertSame('updated', $this->enrol((int)$enrolled->id, 'regular', [], '1'));
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $enrolled->id]));
    }
}
