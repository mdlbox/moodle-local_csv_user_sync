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
 * Admin setting for CSV path with inline status icon.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_csv_user_sync\local;

/**
 * CSV path setting with validation and inline status icon.
 */
class admin_setting_csvpath extends \admin_setting_configtext {
    /**
     * Validate data before storage.
     *
     * @param string $data
     * @return mixed
     */
    public function validate($data) {
        $data = trim((string)$data);
        if ($data === '') {
            return true;
        }

        if (!file_exists($data)) {
            return get_string('error:csvpathnotfound', 'local_csv_user_sync', $data);
        }

        if (!is_file($data) || !is_readable($data)) {
            return get_string('error:csvpathnotreadable', 'local_csv_user_sync', $data);
        }

        return true;
    }

    /**
     * Return XHTML for this setting.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        $context = (object) [
            'size' => $this->size,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $data,
            'forceltr' => $this->get_force_ltr(),
            'readonly' => $this->is_readonly(),
            'data' => [],
            'maxcharacter' => false,
        ];

        $element = $OUTPUT->render_from_template('core_admin/setting_configtext', $context);
        $path = trim((string)$data);

        if ($path !== '') {
            $isreachable = is_file($path) && is_readable($path);
            $label = $isreachable
                ? get_string('settings:csvpathinline_ok', 'local_csv_user_sync')
                : get_string('settings:csvpathinline_fail', 'local_csv_user_sync');
            $symbol = $isreachable ? "\u{2713}" : "\u{2717}";
            $class = $isreachable ? 'text-success' : 'text-danger';

            $statusicon = \html_writer::span(
                $symbol,
                'ms-2 fw-bold ' . $class,
                [
                    'title' => $label,
                    'aria-label' => $label,
                    'role' => 'img',
                ]
            );
            $element .= $statusicon;
        }

        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', $default, $query);
    }
}
