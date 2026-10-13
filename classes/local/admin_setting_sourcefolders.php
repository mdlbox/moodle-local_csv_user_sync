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
 * Admin setting to select the source Folder activities.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_csv_user_sync\local;

/**
 * Multiselect of the Folder activities in the configured source course.
 *
 * Unlike the core multiselect, an empty list of choices is still displayed (with a hint) and saved,
 * so the setting never stays undefined (which would keep it on the "new settings" upgrade page).
 */
class admin_setting_sourcefolders extends \admin_setting_configmultiselect {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, [], null);
    }

    /**
     * Lazy-load the Folder activities of the source course.
     *
     * @return bool
     */
    public function load_choices() {
        if (!is_array($this->choices)) {
            $this->choices = folder_source::get_folder_choices();
        }
        return true;
    }

    /**
     * Save the selected folders. Values that are not Folder activities of the source course are discarded.
     *
     * @param mixed $data
     * @return string empty string on success, error message otherwise
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return '';
        }
        $this->load_choices();
        unset($data['xxxxx']);

        $save = [];
        foreach ($data as $value) {
            if (array_key_exists($value, $this->choices)) {
                $save[] = $value;
            }
        }

        return $this->config_write($this->name, implode(',', $save)) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Return XHTML for this setting. Shows a hint when the source course has no Folder activities.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        $this->load_choices();
        if (!empty($this->choices)) {
            return parent::output_html($data, $query);
        }

        $element = \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $this->get_full_name() . '[xxxxx]',
            'value' => '1',
        ]);
        $element .= \html_writer::div(get_string('settings:sourcefolders_none', 'local_csv_user_sync'), 'form-control-static');

        return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', null, $query);
    }
}
