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

$string['csv_user_sync:uploadsource'] = 'Provide CSV files for user synchronization in source folders';
$string['email:conflict_body'] = "Hello,\n\nthe CSV user synchronization on {\$a->sitename} skipped {\$a->count} row(s) because the email address is already used by another account and the site does not allow accounts with the same email (allowaccountssameemail).\n\n{\$a->conflicts}\n\nCorrect the CSV files or the existing accounts, then wait for the next synchronization.";
$string['email:conflict_line'] = '{$a->source}, row {$a->row}: username "{$a->username}", email "{$a->email}" already used by: {$a->existing}';
$string['email:conflict_more'] = '... and {$a} more. See the synchronization log for the full list.';
$string['email:conflict_subject'] = '[{$a}] CSV user sync: email conflicts';
$string['email:newuser_subject'] = '[{$a}] Your Moodle account access';
$string['error:conflictnotifyfailed'] = 'Unable to send the email conflict notification.';
$string['error:csvheaderduplicate'] = 'Duplicate CSV header detected: {$a}.';
$string['error:csvheaderinvalid'] = 'Invalid CSV header at column {$a}.';
$string['error:csvheadernotfound'] = 'CSV header row not found.';
$string['error:csvpathnotfound'] = 'CSV path does not exist: {$a}';
$string['error:csvpathnotreadable'] = 'CSV path is not readable: {$a}';
$string['error:csvpathrequired'] = 'CSV path is required.';
$string['error:csvsourceconflict'] = 'Configuration error: set either "CSV file path" or "CSV file upload", not both.';
$string['error:csvsourcemissing'] = 'Configuration error: configure "CSV file path", upload a CSV file, or select source folders.';
$string['error:csvuploadnotfound'] = 'Uploaded CSV file not found in file storage. Upload the file again in plugin settings.';
$string['error:delimiterinvalid'] = 'Delimiter must contain exactly one character.';
$string['error:emailconflict'] = 'Row skipped: the email for "{$a->username}" is already used by another account ({$a->existing}) and accounts with the same email are not allowed.';
$string['error:emailsendfailed'] = 'Failed to send account access email to user "{$a}".';
$string['error:enrolfailed'] = 'Enrolment synchronization failed: {$a}';
$string['error:enrolheaderspair'] = 'CSV enrolment headers must include both "course_shortname" and "role_shortname".';
$string['error:enrolmissingrole'] = 'Missing role_shortname value for enrolment.';
$string['error:foldermissing'] = 'Configured source folder (course module ID {$a}) is not a Folder activity of the source course anymore. Check the plugin settings.';
$string['error:folderuntrustedauthor'] = 'Folder "{$a->folder}": file "{$a->file}" ignored because it was not uploaded by a user with the capability local/csv_user_sync:uploadsource in system context.';
$string['error:folderuntrustedmanagers'] = 'Folder "{$a->folder}" skipped: these users can manage its files but do not hold the capability local/csv_user_sync:uploadsource in system context: {$a->users}. Remove their file management rights in the source course or grant them the capability.';
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
$string['error:nosuffixavailable'] = 'No valid username with numeric suffix is available for "{$a}".';
$string['error:protectedaccount'] = 'Row skipped: account "{$a}" is a site administrator, a privileged account (system capability moodle/user:update or local/csv_user_sync:uploadsource), the guest or a deleted account, and is never modified by the synchronization.';
$string['error:rolenotcourselevel'] = 'Role "{$a}" is not allowed in CSV enrolments (see "Roles allowed in CSV enrolments") or cannot be assigned in course context.';
$string['error:sourcecourseinvalid'] = 'Course with ID {$a} does not exist or is the site front page.';
$string['error:sourcecoursemissing'] = 'Source folders are selected, but the source course is not configured or does not exist anymore.';
$string['error:unknowncourse'] = 'Course not found by shortname: {$a}';
$string['error:unknownrole'] = 'Role not found by shortname: {$a}';
$string['error:usercreate'] = 'User creation failed: {$a}';
$string['error:usercreatedemailfailed'] = 'User "{$a->username}" created, but credentials email failed: {$a->message}';
$string['error:usermissingmandatoryfields'] = 'Missing mandatory user fields. Required: username, firstname, lastname, email.';
$string['error:usermissingmandatoryfieldsrow'] = 'Missing mandatory user values in row. Empty fields: {$a}.';
$string['error:userupdate'] = 'User update failed: {$a}';
$string['error:valuetoolong'] = 'Row skipped: value too long for: {$a}.';
$string['log:conflictnotified'] = 'Email conflict notification sent ({$a->count} conflict(s), {$a->recipients} recipient(s)).';
$string['log:conflictnotify_dryrun'] = 'Dry-run: an email conflict notification would be sent ({$a} conflict(s)).';
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
$string['log:usernamesuffixed'] = 'Username "{$a->requested}" belongs to an account with a different email: using "{$a->assigned}".';
$string['log:userunchanged'] = 'User "{$a}" unchanged.';
$string['log:userupdate_dryrun'] = 'Dry-run: user "{$a}" would be updated.';
$string['log:userupdated'] = 'User "{$a}" updated.';
$string['pluginname'] = 'CSV User Sync';

$string['privacy:metadata:conflictnotification'] = 'When a CSV row is skipped because its email address is already used, the username and email from the row are sent by email to the configured conflict address or to the site administrators.';
$string['privacy:metadata:conflictnotification:email'] = 'The email address from the CSV row.';
$string['privacy:metadata:conflictnotification:username'] = 'The username from the CSV row.';
$string['privacy:metadata:core_files'] = 'The plugin stores an optional CSV source file in Moodle file storage when uploaded from settings.';
$string['privacy:metadata:local_csv_user_sync_log'] = 'Stores execution logs for CSV synchronization.';
$string['privacy:metadata:local_csv_user_sync_log:level'] = 'Log severity level.';
$string['privacy:metadata:local_csv_user_sync_log:message'] = 'Log message text.';
$string['privacy:metadata:local_csv_user_sync_log:rownum'] = 'CSV row number related to the log entry.';
$string['privacy:metadata:local_csv_user_sync_log:runid'] = 'Synchronization run identifier.';
$string['privacy:metadata:local_csv_user_sync_log:timecreated'] = 'Time when the log entry was created.';
$string['privacy:metadata:local_csv_user_sync_log:userid'] = 'The ID of the user referenced by the log row.';
$string['privacy:metadata:local_csv_user_sync_log:username'] = 'The username referenced by the log row.';
$string['settings:allowedroles'] = 'Roles allowed in CSV enrolments';
$string['settings:allowedroles_desc'] = 'Only these roles can be assigned by the role_shortname column. Keep privileged roles (for example Manager) unchecked unless strictly necessary.';
$string['settings:authsection'] = 'Authentication';
$string['settings:conflictemail'] = 'Email conflict notification address';
$string['settings:conflictemail_desc'] = 'When "Allow accounts with same email" (allowaccountssameemail) is disabled, CSV rows whose email is already used by another account are skipped. A summary of these conflicts is sent by email at the end of each run to this address. Leave empty to notify all site administrators. No email is sent in dry-run mode.';
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
$string['settings:emptyvalues'] = 'Empty CSV values';
$string['settings:emptyvalues_clear'] = 'Clear the existing value';
$string['settings:emptyvalues_desc'] = 'What happens to an existing user when an optional column (including custom profile fields) is present but empty in the CSV. Mandatory columns can never be empty, and the language is never cleared.';
$string['settings:emptyvalues_ignore'] = 'Ignore it and keep the existing value';
$string['settings:encoding'] = 'File encoding';
$string['settings:encoding:auto'] = 'Auto-detect encoding';
$string['settings:encoding_desc'] = 'Encoding used by the CSV file.';
$string['settings:filesection'] = 'CSV file';
$string['settings:foldersection'] = 'Source folders';
$string['settings:foldersection_desc'] = 'CSV files can also be read from Folder activities of a dedicated course. Each selected folder must contain one CSV file; if a folder contains more than one, the most recently modified file is used and the others are reported in the log. Folders are processed one after the other, in course page order, after the CSV file configured above (if any).<br><strong>Security:</strong> CSV files contain personal data and control accounts and enrolments. Hide the source folders from participants, and only give the capability "local/csv_user_sync:uploadsource" (system context) to trusted users: files uploaded by anyone else are rejected.';
$string['settings:matchmode'] = 'Account matching';
$string['settings:matchmode_desc'] = 'How CSV rows are matched to existing accounts. With "Username and email", a row updates an account only when both username and email match (email is case-insensitive). When the username belongs to an account with a different email, the row is assigned to "username1", "username2"... : an existing suffixed account with the same email is updated, otherwise the first free suffix is created.';
$string['settings:matchmode_username'] = 'Username only (previous behaviour)';
$string['settings:matchmode_usernameemail'] = 'Username and email';
$string['settings:parsersection'] = 'CSV format';
$string['settings:schedulehint'] = 'Task frequency';
$string['settings:schedulehint_desc'] = 'Task frequency is configured in Scheduled tasks: {$a}';
$string['settings:sendemail'] = 'Send credentials email';
$string['settings:sendemail_desc'] = 'Send username and one-time password setup link only for newly created users.';
$string['settings:sourcecourse'] = 'Source course ID';
$string['settings:sourcecourse_desc'] = 'ID of the course containing the source Folder activities. Save the settings after changing it to update the list of folders below.';
$string['settings:sourcecourse_selected'] = 'Selected course: {$a}';
$string['settings:sourcefolders'] = 'Folders to synchronize';
$string['settings:sourcefolders_desc'] = 'Folder activities of the source course to read CSV files from.';
$string['settings:sourcefolders_none'] = 'No Folder activity available. Configure the source course ID and save the settings first.';
$string['settings:sourcefolders_option'] = '{$a->name} (ID {$a->cmid})';
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
$string['task:sourcefatal'] = 'Source "{$a->source}" stopped due to an unexpected error: {$a->message}. The next sources are still processed.';
$string['task:sourcefile'] = 'Reading file "{$a}".';
$string['task:sourcefolder'] = 'Folder "{$a->name}" (ID {$a->cmid})';
$string['task:sourcepath'] = 'File path "{$a}"';
$string['task:sourcesfailed'] = 'CSV user synchronization completed with failed sources: {$a}';
$string['task:sourcestart'] = 'Processing source {$a->index}/{$a->total}: {$a->source}.';
$string['task:sourcesummary'] = 'Source "{$a->source}" completed in {$a->duration} s. Rows: {$a->rows}. Users created: {$a->userscreated}. Users updated: {$a->usersupdated}. Enrolments created: {$a->enrolmentscreated}. Enrolments updated: {$a->enrolmentsupdated}. Errors: {$a->errors}.';
$string['task:sourceupload'] = 'Uploaded file "{$a}"';
$string['task:start'] = 'Synchronization started. Dry-run: {$a}.';
$string['task:summary'] = 'Sources: {$a->sources}. Processed rows: {$a->rows}. Users created: {$a->userscreated}. Users updated: {$a->usersupdated}. Enrolments created: {$a->enrolmentscreated}. Enrolments updated: {$a->enrolmentsupdated}. Errors: {$a->errors}.';
$string['task:syncusers'] = 'Synchronize users from CSV';
$string['task:unknownerror'] = 'Unknown error';
$string['template:commentrow'] = 'Instruction row - remove this row before importing data.';
$string['template:coursehint'] = 'Course shortname';
$string['template:datehintoptional'] = 'Optional date: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY or UNIX timestamp';
$string['template:datehintrequired'] = 'Required date: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY or UNIX timestamp';
$string['template:deletedhint'] = 'Delete enrolment: 0 = no, 1 = yes';
$string['template:rolehint'] = 'Role shortname (for example: student)';
$string['template:suspendedhint'] = 'Suspend enrolment: 0 = no, 1 = yes';
$string['warning:folderexposed'] = '{$a} is visible on the course page. CSV files contain personal data: hide the folder from participants.';
$string['warning:foldermultiplecsv'] = 'Folder "{$a->folder}" contains more than one CSV file. Using the most recent one "{$a->selected}"; ignored: {$a->ignored}.';
$string['warning:foldernocsv'] = 'Folder "{$a}" contains no CSV file: skipped.';
$string['warning:invalidfieldvalue'] = 'Invalid value "{$a->value}" for field "{$a->field}": field not changed.';
$string['warning:invalidprofilevalue'] = 'Invalid value "{$a->value}" for custom profile field "{$a->field}" (for example not a menu option or too long): field not changed.';
$string['warning:profilefieldambiguous'] = 'Custom profile fields "{$a}" exist with shortnames differing only by letter case: they are ignored by the synchronization.';
$string['warning:profilevaluerejected'] = 'Value for custom profile field "{$a->field}" rejected: {$a->message}';
