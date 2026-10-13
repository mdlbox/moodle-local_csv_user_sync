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

/**
 * Admin setting for the course containing the source Folder activities.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_csv_user_sync\local;

/**
 * Course id setting validated against existing courses, showing the selected course name.
 */
class admin_setting_sourcecourse extends \admin_setting_configtext {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '', PARAM_INT, 10);
    }

    /**
     * Validate data before storage: empty, or the id of an existing course other than the site course.
     *
     * @param string $data
     * @return mixed true or error string
     */
    public function validate($data) {
        global $DB;

        $data = trim((string)$data);
        if ($data === '' || $data === '0') {
            return true;
        }

        if ((string)clean_param($data, PARAM_INT) !== $data) {
            return get_string('validateerror', 'admin');
        }

        $courseid = (int)$data;
        if ($courseid === (int)SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            return get_string('error:sourcecourseinvalid', 'local_csv_user_sync', $courseid);
        }

        return true;
    }

    /**
     * Return XHTML for this setting, with the selected course name next to the field.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $DB;

        $courseid = (int)$data;
        $course = null;
        if ($courseid > 0 && $courseid !== (int)SITEID) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
        }
        if (!$course) {
            return parent::output_html($data, $query);
        }

        $link = \html_writer::link(
            new \moodle_url('/course/view.php', ['id' => $course->id]),
            format_string($course->fullname, true, ['context' => \context_course::instance($course->id)])
        );
        $description = $this->description;
        $this->description .= \html_writer::div(get_string('settings:sourcecourse_selected', 'local_csv_user_sync', $link));
        $html = parent::output_html($data, $query);
        $this->description = $description;

        return $html;
    }
}
