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
 * CSV template download for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

require_login();
require_sesskey();

$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$corefields = [
    'username',
    'firstname',
    'lastname',
    'email',
    'auth',
    'city',
    'country',
    'lang',
    'idnumber',
    'institution',
    'department',
    'phone1',
    'phone2',
    'address',
];

$enrolfields = [
    'course_shortname',
    'role_shortname',
    'enrol_start_date',
    'enrol_end_date',
    'suspended',
    'deleted',
];

$customheaders = [];
foreach (profile_get_custom_fields() as $field) {
    if (empty($field->shortname)) {
        continue;
    }
    $customheaders[] = 'profile_field_' . $field->shortname;
}
sort($customheaders, SORT_NATURAL | SORT_FLAG_CASE);

$headers = array_merge($corefields, $enrolfields, $customheaders);
$commentrow = array_fill(0, count($headers), '');
$row = array_fill(0, count($headers), '');
$map = array_flip($headers);

$commentrow[$map['username']] = '# ' . get_string('template:commentrow', 'local_csv_user_sync');
$commentrow[$map['course_shortname']] = get_string('template:coursehint', 'local_csv_user_sync');
$commentrow[$map['role_shortname']] = get_string('template:rolehint', 'local_csv_user_sync');
$commentrow[$map['enrol_start_date']] = get_string('template:datehintrequired', 'local_csv_user_sync');
$commentrow[$map['enrol_end_date']] = get_string('template:datehintoptional', 'local_csv_user_sync');
$commentrow[$map['suspended']] = get_string('template:suspendedhint', 'local_csv_user_sync');
$commentrow[$map['deleted']] = get_string('template:deletedhint', 'local_csv_user_sync');

$row[$map['username']] = 'demo.user';
$row[$map['firstname']] = 'Demo';
$row[$map['lastname']] = 'User';
$row[$map['email']] = 'demo.user@example.com';
$row[$map['auth']] = 'manual';
$row[$map['course_shortname']] = 'course_shortname_example';
$row[$map['role_shortname']] = 'student';
$row[$map['enrol_start_date']] = date('Y-m-d');
$row[$map['suspended']] = '0';
$row[$map['deleted']] = '0';

$delimiter = trim((string)get_config('local_csv_user_sync', 'delimiter'));
if (core_text::strlen($delimiter) !== 1) {
    $delimiter = ';';
}

$filename = 'local_csv_user_sync_template_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new moodle_exception('task:unknownerror', 'local_csv_user_sync');
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $headers, $delimiter, '"', '\\');
fputcsv($output, $commentrow, $delimiter, '"', '\\');
fputcsv($output, $row, $delimiter, '"', '\\');
fclose($output);
exit;
