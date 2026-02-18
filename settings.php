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
 * Settings for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/local/admin_setting_csvpath.php');
require_once(__DIR__ . '/classes/local/admin_setting_csvstoredfile.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_csv_user_sync',
        get_string('pluginname', 'local_csv_user_sync')
    );

    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/filesection',
        get_string('settings:filesection', 'local_csv_user_sync'),
        ''
    ));

    $settings->add(new \local_csv_user_sync\local\admin_setting_csvpath(
        'local_csv_user_sync/csvpath',
        get_string('settings:csvpath', 'local_csv_user_sync'),
        get_string('settings:csvpath_desc', 'local_csv_user_sync'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new \local_csv_user_sync\local\admin_setting_csvstoredfile(
        'local_csv_user_sync/csvstoredfile',
        get_string('settings:csvstoredfile', 'local_csv_user_sync'),
        get_string('settings:csvstoredfile_desc', 'local_csv_user_sync'),
        'csvsource',
        0,
        [
            'maxfiles' => 1,
            'subdirs' => 0,
            'accepted_types' => ['.csv', '.txt'],
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'local_csv_user_sync/encoding',
        get_string('settings:encoding', 'local_csv_user_sync'),
        get_string('settings:encoding_desc', 'local_csv_user_sync'),
        'UTF-8',
        [
            'UTF-8' => 'UTF-8',
            'ISO-8859-1' => 'ISO-8859-1',
            'Windows-1252' => 'Windows-1252',
            'auto' => get_string('settings:encoding:auto', 'local_csv_user_sync'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_csv_user_sync/delimiter',
        get_string('settings:delimiter', 'local_csv_user_sync'),
        get_string('settings:delimiter_desc', 'local_csv_user_sync'),
        ';',
        PARAM_RAW_TRIMMED
    ));

    $templateurl = new moodle_url('/local/csv_user_sync/download_template.php', ['sesskey' => sesskey()]);
    $templatelink = html_writer::link(
        $templateurl,
        get_string('settings:templatedownload', 'local_csv_user_sync')
    );
    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/template',
        get_string('settings:templateheading', 'local_csv_user_sync'),
        get_string('settings:templatedesc', 'local_csv_user_sync', $templatelink)
    ));

    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/authsection',
        get_string('settings:authsection', 'local_csv_user_sync'),
        ''
    ));

    $authoptions = [];
    $stringmanager = get_string_manager();
    foreach (get_enabled_auth_plugins() as $authname) {
        $authcomponent = 'auth_' . $authname;
        if ($stringmanager->string_exists('pluginname', $authcomponent)) {
            $authoptions[$authname] = get_string('pluginname', $authcomponent);
        } else {
            $authoptions[$authname] = $authname;
        }
    }
    if (!isset($authoptions['manual'])) {
        $authoptions['manual'] = get_string('pluginname', 'auth_manual');
    }

    $settings->add(new admin_setting_configselect(
        'local_csv_user_sync/defaultauth',
        get_string('settings:defaultauth', 'local_csv_user_sync'),
        get_string('settings:defaultauth_desc', 'local_csv_user_sync'),
        'manual',
        $authoptions
    ));

    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/emailsection',
        get_string('settings:emailsection', 'local_csv_user_sync'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_csv_user_sync/sendemail',
        get_string('settings:sendemail', 'local_csv_user_sync'),
        get_string('settings:sendemail_desc', 'local_csv_user_sync'),
        1
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_csv_user_sync/emailtemplate',
        get_string('settings:emailtemplate', 'local_csv_user_sync'),
        get_string('settings:emailtemplate_desc', 'local_csv_user_sync'),
        get_string('settings:emailtemplate_default', 'local_csv_user_sync'),
        PARAM_RAW,
        80,
        15
    ));

    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/syncsection',
        get_string('settings:syncsection', 'local_csv_user_sync'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_csv_user_sync/updateonlychanged',
        get_string('settings:updateonlychanged', 'local_csv_user_sync'),
        get_string('settings:updateonlychanged_desc', 'local_csv_user_sync'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_csv_user_sync/detailedlog',
        get_string('settings:detailedlog', 'local_csv_user_sync'),
        get_string('settings:detailedlog_desc', 'local_csv_user_sync'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_csv_user_sync/dryrun',
        get_string('settings:dryrun', 'local_csv_user_sync'),
        get_string('settings:dryrun_desc', 'local_csv_user_sync'),
        0
    ));

    $taskurl = new moodle_url('/admin/tool/task/scheduledtasks.php');
    $settings->add(new admin_setting_heading(
        'local_csv_user_sync/schedulehint',
        get_string('settings:schedulehint', 'local_csv_user_sync'),
        get_string('settings:schedulehint_desc', 'local_csv_user_sync', $taskurl->out(false))
    ));

    $ADMIN->add('localplugins', $settings);
}
