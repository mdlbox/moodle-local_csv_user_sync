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
 * Event triggered when the account access email of a new user has been sent.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_csv_user_sync\event;

/**
 * Account access email sent to a user created by the CSV synchronization.
 *
 * The event never stores the email content: it contains a one-time password setup link.
 *
 * @property-read array $other no additional data
 */
class account_email_sent extends \core\event\base {
    /**
     * Create the event for one recipient.
     *
     * @param int $userid id of the user who received the email
     * @return self
     */
    public static function create_for_user(int $userid): self {
        return self::create([
            'context' => \context_user::instance($userid),
            'objectid' => $userid,
            'relateduserid' => $userid,
        ]);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'user';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:accountemailsent', 'local_csv_user_sync');
    }

    /**
     * Returns non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The CSV user synchronization sent the account access email to the user with id '$this->relateduserid'.";
    }

    /**
     * Returns the URL of the recipient profile.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->relateduserid]);
    }

    /**
     * Validate event data.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    /**
     * Object id mapping for backup and restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'user', 'restore' => 'user'];
    }

    /**
     * Other data mapping for backup and restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
