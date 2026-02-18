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
 * English strings for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @category    string
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['email:newuser_subject'] = '[{$a}] Your Moodle account access';
$string['error:csvheaderduplicate'] = 'Duplicate CSV header detected: {$a}.';
$string['error:csvheaderinvalid'] = 'Invalid CSV header at column {$a}.';
$string['error:csvheadernotfound'] = 'CSV header row not found.';
$string['error:csvpathnotfound'] = 'CSV path does not exist: {$a}';
$string['error:csvpathnotreadable'] = 'CSV path is not readable: {$a}';
$string['error:csvpathrequired'] = 'CSV path is required.';
$string['error:csvsourceconflict'] = 'Configuration error: set either "CSV file path" or "CSV file upload", not both.';
$string['error:csvsourcemissing'] = 'Configuration error: configure "CSV file path" or upload a CSV file.';
$string['error:csvuploadnotfound'] = 'Uploaded CSV file not found in file storage. Upload the file again in plugin settings.';
$string['error:delimiterinvalid'] = 'Delimiter must contain exactly one character.';
$string['error:emailsendfailed'] = 'Failed to send account access email to user "{$a}".';
$string['error:enrolfailed'] = 'Enrolment synchronization failed: {$a}';
$string['error:enrolheaderspair'] = 'CSV enrolment headers must include both "course_shortname" and "role_shortname".';
$string['error:enrolmissingrole'] = 'Missing role_shortname value for enrolment.';
$string['error:forbiddenemail'] = 'Email domain not allowed: {$a}';
$string['error:invalidauthfallback'] = 'Unknown auth method "{$a->auth}". Fallback to "{$a->fallback}".';
$string['error:invaliddate'] = 'Invalid date format for "{$a->field}" with value "{$a->value}".';
$string['error:invaliddateorder'] = 'Enrolment end date cannot be earlier than start date.';
$string['error:invalidemail'] = 'Invalid email address: {$a}';
$string['error:invalidflag'] = 'Invalid value for "{$a->field}": "{$a->value}". Allowed values: 0 or 1.';
$string['error:invalidusername'] = 'Invalid username: {$a}';
$string['error:manualenrolmissing'] = 'Manual enrolment instance is missing for course "{$a}".';
$string['error:manualenrolmissingglobal'] = 'Manual enrolment plugin is not available.';
$string['error:missingrequiredheaders'] = 'Missing required CSV headers: {$a}';
$string['error:unknowncourse'] = 'Course not found by shortname: {$a}';
$string['error:unknownrole'] = 'Role not found by shortname: {$a}';
$string['error:usercreate'] = 'User creation failed: {$a}';
$string['error:usercreatedemailfailed'] = 'User "{$a->username}" created, but credentials email failed: {$a->message}';
$string['error:usermissingmandatoryfields'] = 'Missing mandatory user fields. Required: username, firstname, lastname, email.';
$string['error:usermissingmandatoryfieldsrow'] = 'Missing mandatory user values in row. Empty fields: {$a}.';
$string['error:userupdate'] = 'User update failed: {$a}';
$string['log:emailsent'] = 'Account access email sent to "{$a}".';
$string['log:enrolcreated'] = 'Enrolment created for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolcreated_dryrun'] = 'Dry-run: enrolment would be created for user ID {$a->userid} in course "{$a->course}".';
$string['log:enroldeleteabsent'] = 'No manual enrolment found to delete for user ID {$a->userid} in course "{$a->course}".';
$string['log:enroldeleted'] = 'Enrolment deleted for user ID {$a->userid} in course "{$a->course}".';
$string['log:enroldeleted_dryrun'] = 'Dry-run: enrolment would be deleted for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolsuspended'] = 'Enrolment suspended for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolsuspended_dryrun'] = 'Dry-run: enrolment would be suspended for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolunchanged'] = 'Enrolment unchanged for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolupdated'] = 'Enrolment updated for user ID {$a->userid} in course "{$a->course}".';
$string['log:enrolupdated_dryrun'] = 'Dry-run: enrolment would be updated for user ID {$a->userid} in course "{$a->course}".';
$string['log:errorpersist'] = 'Unable to persist plugin log row: {$a}';
$string['log:manualinstancecreated'] = 'Manual enrolment instance created for course "{$a}".';
$string['log:manualinstancecreated_dryrun'] = 'Dry-run: manual enrolment instance would be created for course "{$a}".';
$string['log:usercreate_dryrun'] = 'Dry-run: user "{$a}" would be created.';
$string['log:usercreated'] = 'User "{$a}" created.';
$string['log:userunchanged'] = 'User "{$a}" unchanged.';
$string['log:userupdate_dryrun'] = 'Dry-run: user "{$a}" would be updated.';
$string['log:userupdated'] = 'User "{$a}" updated.';
$string['pluginname'] = 'CSV User Sync';

$string['privacy:metadata:core_files'] = 'The plugin stores an optional CSV source file in Moodle file storage when uploaded from settings.';
$string['privacy:metadata:local_cus_log'] = 'Stores execution logs for CSV synchronization.';
$string['privacy:metadata:local_cus_log:level'] = 'Log severity level.';
$string['privacy:metadata:local_cus_log:message'] = 'Log message text.';
$string['privacy:metadata:local_cus_log:rownum'] = 'CSV row number related to the log entry.';
$string['privacy:metadata:local_cus_log:runid'] = 'Synchronization run identifier.';
$string['privacy:metadata:local_cus_log:timecreated'] = 'Time when the log entry was created.';
$string['privacy:metadata:local_cus_log:userid'] = 'The ID of the user referenced by the log row.';
$string['privacy:metadata:local_cus_log:username'] = 'The username referenced by the log row.';
$string['settings:authsection'] = 'Authentication';
$string['settings:csvpath'] = 'CSV file path';
$string['settings:csvpath_desc'] = 'Absolute path to the CSV file to process. Use this or "CSV file upload", not both.';
$string['settings:csvpathinline_fail'] = 'Path not reachable';
$string['settings:csvpathinline_ok'] = 'Path reachable';
$string['settings:csvpathstatus'] = 'CSV path status';
$string['settings:csvpathstatus_empty'] = 'No path configured yet.';
$string['settings:csvpathstatus_fail'] = 'File is not reachable or not readable: {$a}';
$string['settings:csvpathstatus_ok'] = 'File is reachable and readable: {$a}';
$string['settings:csvstoredfile'] = 'CSV file upload';
$string['settings:csvstoredfile_desc'] = 'Upload the CSV file with drag and drop. Use this or "CSV file path", not both.';
$string['settings:defaultauth'] = 'Default authentication method';
$string['settings:defaultauth_desc'] = 'Used when the CSV auth column is empty or invalid. Default: manual.';
$string['settings:delimiter'] = 'Delimiter';
$string['settings:delimiter_desc'] = 'Single-character CSV delimiter. Default: semicolon (;).';
$string['settings:detailedlog'] = 'Detailed logging';
$string['settings:detailedlog_desc'] = 'If enabled, debug information is included in task output and plugin log table.';
$string['settings:dryrun'] = 'Dry-run mode';
$string['settings:dryrun_desc'] = 'If enabled, no data is written to Moodle. Operations are only simulated and logged.';
$string['settings:emailsection'] = 'New user email';
$string['settings:emailtemplate'] = 'Email template';
$string['settings:emailtemplate_default'] = "Hello {{firstname}},\n\nan account has been created for you on {{sitename}}.\n\nUsername: {{username}}\nSet password URL: {{setpasswordurl}}\nLogin URL: {{loginurl}}\n\nFor security reasons, this link is one-time and expires automatically.";
$string['settings:emailtemplate_desc'] = 'Available placeholders: {{firstname}}, {{lastname}}, {{username}}, {{setpasswordurl}}, {{sitename}}, {{loginurl}}. Legacy {{password}} is supported as alias for {{setpasswordurl}}.';
$string['settings:encoding'] = 'File encoding';
$string['settings:encoding:auto'] = 'Auto-detect encoding';
$string['settings:encoding_desc'] = 'Encoding used by the CSV file.';
$string['settings:filesection'] = 'CSV file';
$string['settings:schedulehint'] = 'Task frequency';
$string['settings:schedulehint_desc'] = 'Task frequency is configured in Scheduled tasks: {$a}';
$string['settings:sendemail'] = 'Send credentials email';
$string['settings:sendemail_desc'] = 'Send username and one-time password setup link only for newly created users.';
$string['settings:syncsection'] = 'Sync behavior';
$string['settings:templatedesc'] = 'Download a sample CSV with all supported fields (including custom profile fields): {$a}';
$string['settings:templatedownload'] = 'Download CSV template';
$string['settings:templateheading'] = 'CSV template';
$string['settings:updateonlychanged'] = 'Update only changed data';
$string['settings:updateonlychanged_desc'] = 'If enabled, users are updated only when at least one value has changed.';
$string['task:dryrunskipenrol'] = 'Dry-run: enrolment skipped for new user "{$a}" because the user does not exist yet.';
$string['task:fatal'] = 'Synchronization aborted due to an unexpected error: {$a}';
$string['task:lockfailed'] = 'Task skipped because another synchronization is already running.';
$string['task:releaselockfailed'] = 'Unable to release task lock: {$a}';
$string['task:rowfatal'] = 'Row {$a->row} skipped due to unexpected error: {$a->message}';
$string['task:start'] = 'Synchronization started. Dry-run: {$a}.';
$string['task:summary'] = 'Processed rows: {$a->rows}. Users created: {$a->userscreated}. Users updated: {$a->usersupdated}. Enrolments created: {$a->enrolmentscreated}. Enrolments updated: {$a->enrolmentsupdated}. Errors: {$a->errors}.';
$string['task:syncusers'] = 'Synchronize users from CSV';
$string['task:unknownerror'] = 'Unknown error';
$string['template:commentrow'] = 'Instruction row - remove this row before importing data.';
$string['template:coursehint'] = 'Course shortname';
$string['template:datehintoptional'] = 'Optional date: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY or UNIX timestamp';
$string['template:datehintrequired'] = 'Required date: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY or UNIX timestamp';
$string['template:deletedhint'] = 'Delete enrolment: 0 = no, 1 = yes';
$string['template:rolehint'] = 'Role shortname (for example: student)';
$string['template:suspendedhint'] = 'Suspend enrolment: 0 = no, 1 = yes';
