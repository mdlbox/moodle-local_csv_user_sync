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
 * User synchronizer for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use core_user;
use core_text;
use moodle_exception;
use stdClass;

/**
 * Creates and updates Moodle users from normalized CSV rows.
 */
class user_sync {
    /** @var logger */
    private readonly logger $logger;

    /** @var array<string,mixed> */
    private readonly array $config;

    /** @var array<string,bool> */
    private array $profilefieldshortnames = [];

    /** @var array<string,bool> */
    private array $supporteduserfields = [
        'username' => true,
        'firstname' => true,
        'lastname' => true,
        'email' => true,
        'auth' => true,
        'city' => true,
        'country' => true,
        'lang' => true,
        'idnumber' => true,
        'institution' => true,
        'department' => true,
        'phone1' => true,
        'phone2' => true,
        'address' => true,
    ];

    /** @var array<string,bool> */
    private array $reservedheaders = [
        'course_shortname' => true,
        'role_shortname' => true,
        'enrol_start_date' => true,
        'enrol_end_date' => true,
        'suspended' => true,
        'deleted' => true,
        'start_date' => true,
        'end_date' => true,
    ];

    /**
     * Constructor.
     *
     * @param logger $logger
     * @param array<string,mixed> $config
     */
    public function __construct(
        logger $logger,
        array $config
    ) {
        global $CFG;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->logger = $logger;
        $this->config = $config;

        foreach (profile_get_custom_fields() as $field) {
            if (!empty($field->shortname)) {
                $this->profilefieldshortnames[(string)$field->shortname] = true;
            }
        }
    }

    /**
     * Synchronize a single user row.
     *
     * @param array<string,string> $row
     * @param int $rownum
     * @return array{status:string,userid:?int,newuser:bool,username:string}
     */
    public function sync(array $row, int $rownum): array {
        $username = core_text::strtolower(trim((string)($row['username'] ?? '')));
        $missingfields = $this->get_missing_mandatory_fields($row);
        if (!empty($missingfields)) {
            $this->logger->error(
                get_string('error:usermissingmandatoryfieldsrow', 'local_csv_user_sync', implode(', ', $missingfields)),
                $rownum,
                null,
                $username
            );
            return ['status' => 'error', 'userid' => null, 'newuser' => false, 'username' => $username];
        }

        $email = trim((string)($row['email'] ?? ''));

        if ($username !== clean_param($username, PARAM_USERNAME)) {
            $this->logger->error(get_string('error:invalidusername', 'local_csv_user_sync', $username), $rownum, null, $username);
            return ['status' => 'error', 'userid' => null, 'newuser' => false, 'username' => $username];
        }

        if (!validate_email($email)) {
            $this->logger->error(get_string('error:invalidemail', 'local_csv_user_sync', $email), $rownum, null, $username);
            return ['status' => 'error', 'userid' => null, 'newuser' => false, 'username' => $username];
        }

        if (email_is_not_allowed($email)) {
            $this->logger->error(get_string('error:forbiddenemail', 'local_csv_user_sync', $email), $rownum, null, $username);
            return ['status' => 'error', 'userid' => null, 'newuser' => false, 'username' => $username];
        }

        $userdata = $this->build_user_data($row, $username);
        $customfields = $this->extract_custom_fields($row);
        $existing = core_user::get_user_by_username($username);

        if (!$existing) {
            return $this->create_user($userdata, $customfields, $rownum, $username);
        }

        return $this->update_user($existing, $userdata, $customfields, $rownum, $username);
    }

    /**
     * Build sanitized user properties from the CSV row.
     *
     * @param array<string,string> $row
     * @param string $username
     * @return array<string,string>
     */
    private function build_user_data(array $row, string $username): array {
        $data = [
            'username' => $username,
            'firstname' => clean_param((string)$row['firstname'], PARAM_NOTAGS),
            'lastname' => clean_param((string)$row['lastname'], PARAM_NOTAGS),
            'email' => clean_param((string)$row['email'], PARAM_EMAIL),
        ];

        $auth = trim((string)($row['auth'] ?? ''));
        if ($auth === '') {
            $auth = (string)($this->config['defaultauth'] ?? 'manual');
        }
        if ($auth === '') {
            $auth = 'manual';
        }

        $enabledauth = get_enabled_auth_plugins();
        if (!in_array($auth, $enabledauth, true)) {
            $fallback = (string)($this->config['defaultauth'] ?? 'manual');
            if ($fallback === '' || !in_array($fallback, $enabledauth, true)) {
                $fallback = 'manual';
            }
            $this->logger->debug(get_string('error:invalidauthfallback', 'local_csv_user_sync', (object)[
                'auth' => $auth,
                'fallback' => $fallback,
            ]));
            $auth = $fallback;
        }
        $data['auth'] = $auth;

        foreach ($this->supporteduserfields as $field => $unused) {
            if (in_array($field, ['username', 'firstname', 'lastname', 'email', 'auth'], true)) {
                continue;
            }
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $data[$field] = clean_param((string)$row[$field], PARAM_TEXT);
        }

        return $data;
    }

    /**
     * Create one user from prepared data.
     *
     * @param array<string,string> $userdata
     * @param array<string,string> $customfields
     * @param int $rownum
     * @param string $username
     * @return array{status:string,userid:?int,newuser:bool,username:string}
     */
    private function create_user(array $userdata, array $customfields, int $rownum, string $username): array {
        global $CFG;

        $dryrun = !empty($this->config['dryrun']);
        if ($dryrun) {
            $this->logger->info(get_string('log:usercreate_dryrun', 'local_csv_user_sync', $username), $rownum, null, $username);
            return ['status' => 'created', 'userid' => null, 'newuser' => true, 'username' => $username];
        }

        try {
            $now = time();
            $randompassword = generate_password(12);

            $newuser = (object)$userdata;
            $newuser->confirmed = 1;
            $newuser->mnethostid = $CFG->mnet_localhost_id;
            $newuser->timecreated = $now;
            $newuser->timemodified = $now;
            if (empty($newuser->lang)) {
                $newuser->lang = get_newuser_language();
            }
            $newuser->password = $randompassword;

            $userid = user_create_user($newuser, true, true);

            if (!empty($customfields)) {
                profile_save_custom_fields($userid, $customfields);
            }

            try {
                $this->send_credentials_email_if_enabled($userid);
            } catch (\Throwable $e) {
                $this->logger->error(
                    get_string('error:usercreatedemailfailed', 'local_csv_user_sync', (object)[
                        'username' => $username,
                        'message' => $this->readable_exception_message($e),
                    ]),
                    $rownum,
                    $userid,
                    $username
                );
            }

            $this->logger->info(get_string('log:usercreated', 'local_csv_user_sync', $username), $rownum, $userid, $username);
            return ['status' => 'created', 'userid' => $userid, 'newuser' => true, 'username' => $username];
        } catch (\Throwable $e) {
            $this->logger->error(
                get_string('error:usercreate', 'local_csv_user_sync', $this->readable_exception_message($e)),
                $rownum,
                null,
                $username
            );
            return ['status' => 'error', 'userid' => null, 'newuser' => true, 'username' => $username];
        }
    }

    /**
     * Update one existing user from prepared data.
     *
     * @param stdClass $existing
     * @param array<string,string> $userdata
     * @param array<string,string> $customfields
     * @param int $rownum
     * @param string $username
     * @return array{status:string,userid:?int,newuser:bool,username:string}
     */
    private function update_user(
        stdClass $existing,
        array $userdata,
        array $customfields,
        int $rownum,
        string $username
    ): array {
        $updateonlychanged = !empty($this->config['updateonlychanged']);
        $dryrun = !empty($this->config['dryrun']);

        $record = (object)['id' => $existing->id];
        $hasuserchanges = false;
        foreach ($userdata as $field => $value) {
            if ($field === 'username') {
                continue;
            }

            $current = property_exists($existing, $field) ? (string)$existing->{$field} : '';
            if (!$updateonlychanged || $current !== (string)$value) {
                $record->{$field} = $value;
                if ($current !== (string)$value) {
                    $hasuserchanges = true;
                }
            }
        }

        $customtoapply = [];
        if (!empty($customfields)) {
            $currentcustom = (array)profile_user_record($existing->id);
            foreach ($customfields as $shortname => $value) {
                $current = isset($currentcustom[$shortname]) ? (string)$currentcustom[$shortname] : '';
                if (!$updateonlychanged || $current !== (string)$value) {
                    $customtoapply[$shortname] = $value;
                }
            }
        }
        $hascustomchanges = !empty($customtoapply);

        if (!$hasuserchanges && !$hascustomchanges) {
            $this->logger->debug(
                get_string('log:userunchanged', 'local_csv_user_sync', $username),
                $rownum,
                (int)$existing->id,
                $username
            );
            return ['status' => 'unchanged', 'userid' => (int)$existing->id, 'newuser' => false, 'username' => $username];
        }

        if ($dryrun) {
            $this->logger->info(
                get_string('log:userupdate_dryrun', 'local_csv_user_sync', $username),
                $rownum,
                (int)$existing->id,
                $username
            );
            return ['status' => 'updated', 'userid' => (int)$existing->id, 'newuser' => false, 'username' => $username];
        }

        try {
            if ($hasuserchanges) {
                user_update_user($record, false, true);
            }
            if ($hascustomchanges) {
                profile_save_custom_fields((int)$existing->id, $customtoapply);
            }

            $this->logger->info(
                get_string('log:userupdated', 'local_csv_user_sync', $username),
                $rownum,
                (int)$existing->id,
                $username
            );
            return ['status' => 'updated', 'userid' => (int)$existing->id, 'newuser' => false, 'username' => $username];
        } catch (\Throwable $e) {
            $this->logger->error(
                get_string('error:userupdate', 'local_csv_user_sync', $this->readable_exception_message($e)),
                $rownum,
                (int)$existing->id,
                $username
            );
            return ['status' => 'error', 'userid' => (int)$existing->id, 'newuser' => false, 'username' => $username];
        }
    }

    /**
     * Extract custom profile fields from row.
     *
     * @param array<string,string> $row
     * @return array<string,string>
     */
    private function extract_custom_fields(array $row): array {
        $customfields = [];
        foreach ($row as $header => $value) {
            $shortname = '';
            if (str_starts_with($header, 'profile_field_')) {
                $shortname = substr($header, 14);
            } else if (!isset($this->supporteduserfields[$header]) && !isset($this->reservedheaders[$header])) {
                $shortname = $header;
            }

            if ($shortname === '') {
                continue;
            }

            if (!isset($this->profilefieldshortnames[$shortname])) {
                continue;
            }

            $customfields[$shortname] = clean_param($value, PARAM_TEXT);
        }

        return $customfields;
    }

    /**
     * Send credentials email if enabled.
     *
     * @param int $userid
     * @return void
     * @throws moodle_exception
     */
    private function send_credentials_email_if_enabled(int $userid): void {
        global $SITE;

        if (empty($this->config['sendemail'])) {
            return;
        }

        $recipient = core_user::get_user($userid, '*', MUST_EXIST);
        $sender = core_user::get_support_user();
        $subject = get_string('email:newuser_subject', 'local_csv_user_sync', format_string($SITE->fullname));
        $template = trim((string)($this->config['emailtemplate'] ?? ''));
        if ($template === '') {
            $template = get_string('settings:emailtemplate_default', 'local_csv_user_sync');
        }

        $setpasswordurl = $this->build_password_setup_url($recipient);
        $loginurl = (new \moodle_url('/login/index.php'))->out(false);
        $body = strtr($template, [
            '{{firstname}}' => (string)$recipient->firstname,
            '{{lastname}}' => (string)$recipient->lastname,
            '{{username}}' => (string)$recipient->username,
            // Keep legacy placeholder as alias for backward compatibility.
            '{{password}}' => $setpasswordurl,
            '{{setpasswordurl}}' => $setpasswordurl,
            '{{sitename}}' => format_string($SITE->fullname),
            '{{loginurl}}' => $loginurl,
        ]);

        $sent = email_to_user(
            $recipient,
            $sender,
            $subject,
            $body,
            text_to_html(s($body))
        );

        if (!$sent) {
            throw new moodle_exception('error:emailsendfailed', 'local_csv_user_sync', '', $recipient->username);
        }

        $this->logger->debug(
            get_string('log:emailsent', 'local_csv_user_sync', $recipient->username),
            null,
            $userid,
            $recipient->username
        );
    }

    /**
     * Build a secure one-time URL to set or reset the user password.
     *
     * Falls back to the login page when reset links are not supported by auth.
     *
     * @param stdClass $recipient
     * @return string
     */
    private function build_password_setup_url(stdClass $recipient): string {
        global $CFG, $DB;

        $fallbackurl = (new \moodle_url('/login/index.php'))->out(false);
        if (!is_enabled_auth((string)$recipient->auth)) {
            return $fallbackurl;
        }

        $userauth = get_auth_plugin((string)$recipient->auth);
        if (!$userauth->can_reset_password()) {
            return $fallbackurl;
        }

        if (!has_capability('moodle/user:changeownpassword', \context_system::instance(), (int)$recipient->id)) {
            return $fallbackurl;
        }

        require_once($CFG->dirroot . '/login/lib.php');
        $DB->delete_records('user_password_resets', ['userid' => (int)$recipient->id]);
        $resetrecord = core_login_generate_password_reset($recipient);

        return (new \moodle_url('/login/forgot_password.php', ['token' => $resetrecord->token]))->out(false);
    }

    /**
     * Returns missing mandatory user columns for one CSV row.
     *
     * @param array<string,string> $row
     * @return array<int,string>
     */
    private function get_missing_mandatory_fields(array $row): array {
        $required = ['username', 'firstname', 'lastname', 'email'];
        $missing = [];
        foreach ($required as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Returns a safe, readable exception message.
     *
     * @param \Throwable $exception
     * @return string
     */
    private function readable_exception_message(\Throwable $exception): string {
        $message = logger::sanitize_exception_message($exception);
        if ($message !== '') {
            return $message;
        }

        return get_string('task:unknownerror', 'local_csv_user_sync');
    }
}
