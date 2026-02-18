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
 * Admin setting for CSV stored file with source exclusivity validation.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_csv_user_sync\local;

use context_user;

/**
 * CSV stored file setting that enforces "path xor upload".
 */
class admin_setting_csvstoredfile extends \admin_setting_configstoredfile {
    /**
     * Save the setting value after conflict checks.
     *
     * @param mixed $data Draft item id
     * @return string
     */
    public function write_setting($data) {
        $csvpath = trim((string)get_config('local_csv_user_sync', 'csvpath'));
        if ($csvpath !== '' && $this->draft_has_file($data)) {
            return get_string('error:csvsourceconflict', 'local_csv_user_sync');
        }

        return parent::write_setting($data);
    }

    /**
     * Returns true when the submitted draft area currently contains a file.
     *
     * @param mixed $draftitemid
     * @return bool
     */
    private function draft_has_file($draftitemid): bool {
        global $USER;

        if (!is_number($draftitemid)) {
            return false;
        }

        $draftid = (int)$draftitemid;
        if ($draftid <= 0) {
            return false;
        }

        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftid,
            'id DESC',
            false
        );

        return !empty($files);
    }
}
