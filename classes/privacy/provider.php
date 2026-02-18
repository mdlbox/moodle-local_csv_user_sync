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
 * Privacy provider for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * GDPR provider implementation.
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        $collection->add_database_table('local_cus_log', [
            'userid' => 'privacy:metadata:local_cus_log:userid',
            'username' => 'privacy:metadata:local_cus_log:username',
            'runid' => 'privacy:metadata:local_cus_log:runid',
            'level' => 'privacy:metadata:local_cus_log:level',
            'rownum' => 'privacy:metadata:local_cus_log:rownum',
            'message' => 'privacy:metadata:local_cus_log:message',
            'timecreated' => 'privacy:metadata:local_cus_log:timecreated',
        ], 'privacy:metadata:local_cus_log');

        return $collection;
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_cus_log', ['userid' => $userid])) {
            $contextlist->add_context(context_system::instance()->id);
        }

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $records = $DB->get_records('local_cus_log', ['userid' => $userid], 'timecreated ASC');
        if (!$records) {
            return;
        }

        $export = [];
        foreach ($records as $record) {
            $export[] = (object)[
                'runid' => (string)$record->runid,
                'level' => (string)$record->level,
                'rownum' => (int)$record->rownum,
                'message' => (string)$record->message,
                'timecreated' => transform::datetime((int)$record->timecreated),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_csv_user_sync')],
            (object)['logs' => $export]
        );
    }

    /**
     * Delete all user data for a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records_select('local_cus_log', 'userid IS NOT NULL AND userid > 0');
    }

    /**
     * Delete data for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_cus_log', ['userid' => $userid]);
    }

    /**
     * Add users who have data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_cus_log}
                 WHERE userid IS NOT NULL
                   AND userid > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Delete data for selected users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $DB->delete_records_list('local_cus_log', 'userid', $userids);
    }
}
