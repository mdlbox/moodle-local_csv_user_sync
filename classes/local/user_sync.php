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
    /** @var string Accounts are identified by username only (legacy behaviour). */
    public const MATCH_USERNAME = 'username';

    /** @var string Accounts are identified by the username + email pair, with numeric username suffixes. */
    public const MATCH_USERNAME_EMAIL = 'usernameemail';

    /** @var string Empty CSV values are ignored and existing values are kept. */
    public const EMPTY_IGNORE = 'ignore';

    /** @var string Empty CSV values clear existing values. */
    public const EMPTY_CLEAR = 'clear';

    /** @var array<string,int> Maximum lengths of the user table columns written by this plugin. */
    private const FIELD_MAXLENGTH = [
        'username' => 100,
        'firstname' => 100,
        'lastname' => 100,
        'email' => 100,
        'city' => 120,
        'idnumber' => 255,
        'institution' => 255,
        'department' => 255,
        'phone1' => 20,
        'phone2' => 20,
        'address' => 255,
    ];

    /** @var logger */
    private readonly logger $logger;

    /** @var array<string,mixed> */
    private readonly array $config;

    /** @var array<string,\profile_field_base> Custom profile fields indexed by lowercase shortname. */
    private array $profilefields = [];

    /** @var array<string,string> Usernames assigned during this run: username => normalized email. */
    private array $runusernames = [];

    /** @var array<string,string> Identities resolved during this run: requested username + email => username. */
    private array $runidentities = [];

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

        // CSV headers are normalized to lowercase, while profile field shortnames may contain uppercase letters.
        // Index fields by lowercase shortname and drop names that would be ambiguous.
        $ambiguous = [];
        foreach (profile_get_user_fields_with_data(0) as $field) {
            $shortname = (string)$field->get_shortname();
            if ($shortname === '') {
                continue;
            }
            $key = core_text::strtolower($shortname);
            if (isset($this->profilefields[$key])) {
                $ambiguous[$key] = true;
                continue;
            }
            $this->profilefields[$key] = $field;
        }
        foreach (array_keys($ambiguous) as $key) {
            unset($this->profilefields[$key]);
            $this->logger->warning(get_string('warning:profilefieldambiguous', 'local_csv_user_sync', $key));
        }
    }

    /**
     * Synchronize a single user row.
     *
     * @param array<string,string> $row
     * @param int $rownum
     * @return array{status:string,userid:?int,newuser:bool,username:string,conflict:?array}
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
            return $this->result('error', null, false, $username);
        }

        $email = trim((string)($row['email'] ?? ''));

        if ($username !== clean_param($username, PARAM_USERNAME)) {
            $this->logger->error(get_string('error:invalidusername', 'local_csv_user_sync', $username), $rownum, null, $username);
            return $this->result('error', null, false, $username);
        }

        if (!validate_email($email) || $email !== clean_param($email, PARAM_EMAIL)) {
            $this->logger->error(get_string('error:invalidemail', 'local_csv_user_sync', $email), $rownum, null, $username);
            return $this->result('error', null, false, $username);
        }

        if (email_is_not_allowed($email)) {
            $this->logger->error(get_string('error:forbiddenemail', 'local_csv_user_sync', $email), $rownum, null, $username);
            return $this->result('error', null, false, $username);
        }

        $toolong = $this->get_too_long_mandatory_fields($row, $username);
        if (!empty($toolong)) {
            $this->logger->error(
                get_string('error:valuetoolong', 'local_csv_user_sync', implode(', ', $toolong)),
                $rownum,
                null,
                $username
            );
            return $this->result('error', null, false, $username);
        }

        if ($this->get_match_mode() === self::MATCH_USERNAME_EMAIL) {
            $identity = $this->resolve_identity($username, $email);
            if ($identity === null) {
                $this->logger->error(
                    get_string('error:nosuffixavailable', 'local_csv_user_sync', $username),
                    $rownum,
                    null,
                    $username
                );
                return $this->result('error', null, false, $username);
            }
            [$existing, $targetusername] = $identity;
            if ($targetusername !== $username) {
                $this->logger->info(get_string('log:usernamesuffixed', 'local_csv_user_sync', (object)[
                    'requested' => $username,
                    'assigned' => $targetusername,
                ]), $rownum, $existing ? (int)$existing->id : null, $targetusername);
            }
        } else {
            $existing = core_user::get_user_by_username($username) ?: null;
            $targetusername = $username;
        }

        if ($existing !== null && $this->is_protected_account($existing)) {
            $this->logger->error(
                get_string('error:protectedaccount', 'local_csv_user_sync', $targetusername),
                $rownum,
                (int)$existing->id,
                $targetusername
            );
            return $this->result('error', (int)$existing->id, false, $targetusername);
        }

        $conflict = $this->find_email_conflict($email, $existing, $targetusername);
        if ($conflict !== null) {
            $this->logger->error(
                get_string('error:emailconflict', 'local_csv_user_sync', (object)[
                    'username' => $targetusername,
                    'existing' => implode(', ', $conflict),
                ]),
                $rownum,
                $existing ? (int)$existing->id : null,
                $targetusername
            );
            return $this->result('error', $existing ? (int)$existing->id : null, $existing === null, $targetusername, [
                'username' => $targetusername,
                'email' => $email,
                'existing' => $conflict,
            ]);
        }

        $isnew = $existing === null;
        $userdata = $this->build_user_data($row, $targetusername, $isnew, $rownum);
        $customfields = $this->prepare_custom_fields($row, $isnew, $rownum, $targetusername);

        if ($isnew) {
            $result = $this->create_user($userdata, $customfields, $rownum, $targetusername);
        } else {
            $result = $this->update_user($existing, $userdata, $customfields, $rownum, $targetusername);
        }

        // Only a successful (or simulated) write reserves the username for the rest of the run.
        if ($result['status'] !== 'error') {
            $this->remember_identity($username, $email, $targetusername);
        }

        return $result;
    }

    /**
     * Resolve the account matching one username + email pair.
     *
     * Order of resolution:
     * 1. the requested username with the same email: update it;
     * 2. an account "<username><N>" with the same email: update the lowest N;
     * 3. the requested username when it is free: create it;
     * 4. the first free "<username><N>" (N >= 1): create it. Gaps left by deleted accounts are reused.
     *
     * Emails are compared case-insensitively, ignoring surrounding spaces.
     *
     * @param string $username normalized requested username
     * @param string $email requested email
     * @return array{0:?stdClass,1:string}|null existing user (or null to create) and target username; null if none available
     */
    private function resolve_identity(string $username, string $email): ?array {
        global $CFG, $DB;

        $emailkey = $this->normalize_email($email);
        $identitykey = $username . "\0" . $emailkey;
        if (isset($this->runidentities[$identitykey])) {
            $target = $this->runidentities[$identitykey];
            return [core_user::get_user_by_username($target) ?: null, $target];
        }

        $pattern = '/^' . preg_quote($username, '/') . '([1-9][0-9]*)$/';

        // 1. Requested username with the same email.
        $base = core_user::get_user_by_username($username) ?: null;
        if ($base !== null && empty($base->deleted) && $this->normalize_email((string)$base->email) === $emailkey) {
            return [$base, $username];
        }
        if ($base === null && ($this->runusernames[$username] ?? null) === $emailkey) {
            return [null, $username];
        }

        // 2. Suffixed account with the same email (stored, or planned earlier in this run), lowest suffix first.
        $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND deleted = 0';
        $params = ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id];
        $samemail = [];
        foreach ($DB->get_fieldset_select('user', 'username', $select, $params) as $candidate) {
            if (preg_match($pattern, (string)$candidate, $matches)) {
                $samemail[(int)$matches[1]] = (string)$candidate;
            }
        }
        foreach ($this->runusernames as $runusername => $runemail) {
            if ($runemail === $emailkey && preg_match($pattern, (string)$runusername, $matches)) {
                $samemail[(int)$matches[1]] = (string)$runusername;
            }
        }
        if (!empty($samemail)) {
            ksort($samemail);
            $target = reset($samemail);
            return [core_user::get_user_by_username($target) ?: null, $target];
        }

        // 3. Requested username is free.
        if ($base === null && !isset($this->runusernames[$username])) {
            return [null, $username];
        }

        // 4. First free suffix. Deleted records are included because they still occupy the username.
        $select = $DB->sql_like('username', ':prefix', false, false) . ' AND mnethostid = :mnethostid';
        $params = ['prefix' => $DB->sql_like_escape($username) . '%', 'mnethostid' => $CFG->mnet_localhost_id];
        $taken = array_fill_keys($DB->get_fieldset_select('user', 'username', $select, $params), true);
        $taken += $this->runusernames;
        for ($suffix = 1; $suffix <= count($taken) + 1; $suffix++) {
            $candidate = $username . $suffix;
            if (isset($taken[$candidate])) {
                continue;
            }
            $toolong = core_text::strlen($candidate) > self::FIELD_MAXLENGTH['username'];
            if ($toolong || $candidate !== clean_param($candidate, PARAM_USERNAME)) {
                return null;
            }
            return [null, $candidate];
        }

        return null;
    }

    /**
     * Remember a resolved identity so repeated rows (in the same or in later CSV files) map to the same account,
     * including in dry-run mode where nothing is written.
     *
     * @param string $requestedusername
     * @param string $email
     * @param string $targetusername
     * @return void
     */
    private function remember_identity(string $requestedusername, string $email, string $targetusername): void {
        $emailkey = $this->normalize_email($email);
        $this->runidentities[$requestedusername . "\0" . $emailkey] = $targetusername;
        $this->runusernames[$targetusername] = $emailkey;
    }

    /**
     * Returns usernames of other accounts already using the email when duplicates are not allowed.
     *
     * Mirrors the core check (user/editadvanced_form.php): case-insensitive match on local accounts,
     * only enforced when $CFG->allowaccountssameemail is disabled.
     *
     * @param string $email
     * @param stdClass|null $existing account that will be updated, null when creating
     * @param string $targetusername
     * @return string[]|null null when there is no conflict
     */
    private function find_email_conflict(string $email, ?stdClass $existing, string $targetusername): ?array {
        global $CFG, $DB;

        if (!empty($CFG->allowaccountssameemail)) {
            return null;
        }

        // An update never changes the email in username + email mode, and does not change it when equal.
        if ($existing !== null && $this->normalize_email((string)$existing->email) === $this->normalize_email($email)) {
            return null;
        }

        $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND deleted = 0 AND id <> :userid';
        $params = [
            'email' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
            'userid' => $existing ? (int)$existing->id : 0,
        ];
        $usernames = array_values($DB->get_fieldset_select('user', 'username', $select, $params));

        // Accounts planned earlier in this run (relevant in dry-run, where nothing is written).
        $emailkey = $this->normalize_email($email);
        foreach ($this->runusernames as $runusername => $runemail) {
            if ($runemail === $emailkey && $runusername !== $targetusername && !in_array($runusername, $usernames, true)) {
                $usernames[] = $runusername;
            }
        }

        return empty($usernames) ? null : $usernames;
    }

    /**
     * Build sanitized user properties from the CSV row.
     *
     * Empty optional values are skipped in "ignore" mode, and written as empty strings in "clear" mode.
     *
     * @param array<string,string> $row
     * @param string $username
     * @param bool $isnew
     * @param int $rownum
     * @return array<string,string>
     */
    private function build_user_data(array $row, string $username, bool $isnew, int $rownum): array {
        $data = [
            'username' => $username,
            'firstname' => clean_param((string)$row['firstname'], PARAM_NOTAGS),
            'lastname' => clean_param((string)$row['lastname'], PARAM_NOTAGS),
            'email' => clean_param(trim((string)$row['email']), PARAM_EMAIL),
        ];

        // On update, auth changes only when the column is present: non-empty, or empty in clear mode.
        $auth = trim((string)($row['auth'] ?? ''));
        if ($isnew || (array_key_exists('auth', $row) && ($auth !== '' || !$this->ignore_empty_values()))) {
            $data['auth'] = $this->resolve_auth($auth);
        }

        foreach ($this->supporteduserfields as $field => $unused) {
            if (in_array($field, ['username', 'firstname', 'lastname', 'email', 'auth'], true)) {
                continue;
            }
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $value = trim((string)$row[$field]);
            if ($value === '') {
                // Language cannot be empty: an empty value always keeps the current (or default) language.
                if ($field !== 'lang' && !$isnew && !$this->ignore_empty_values()) {
                    $data[$field] = '';
                }
                continue;
            }

            $cleaned = $this->clean_user_field($field, $value);
            if ($cleaned === null) {
                $this->logger->warning(get_string('warning:invalidfieldvalue', 'local_csv_user_sync', (object)[
                    'field' => $field,
                    'value' => $value,
                ]), $rownum, null, $username);
                continue;
            }
            $data[$field] = $cleaned;
        }

        return $data;
    }

    /**
     * Clean and validate one optional user field value.
     *
     * @param string $field
     * @param string $value non-empty raw value
     * @return string|null null when the value is not valid for the field
     */
    private function clean_user_field(string $field, string $value): ?string {
        if ($field === 'lang') {
            $lang = clean_param($value, PARAM_LANG);
            return $lang === '' ? null : $lang;
        }

        if ($field === 'country') {
            $country = core_text::strtoupper($value);
            $countries = get_string_manager()->get_list_of_countries(true);
            return isset($countries[$country]) ? $country : null;
        }

        $cleaned = clean_param($value, PARAM_TEXT);
        if (isset(self::FIELD_MAXLENGTH[$field]) && core_text::strlen($cleaned) > self::FIELD_MAXLENGTH[$field]) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Resolve the authentication method for a row, falling back to the configured default.
     *
     * @param string $auth raw CSV value
     * @return string
     */
    private function resolve_auth(string $auth): string {
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

        return $auth;
    }

    /**
     * Extract, convert and validate custom profile field values from the row.
     *
     * Values are converted with the field type API (for example menu option labels) exactly like
     * the core user upload tool does, and saved later with profile_save_data().
     *
     * @param array<string,string> $row
     * @param bool $isnew
     * @param int $rownum
     * @param string $username
     * @return array<string,array{field:\profile_field_base,value:string}> indexed by form input name
     */
    private function prepare_custom_fields(array $row, bool $isnew, int $rownum, string $username): array {
        $prepared = [];
        foreach ($row as $header => $rawvalue) {
            $key = '';
            if (str_starts_with($header, 'profile_field_')) {
                $key = substr($header, 14);
            } else if (!isset($this->supporteduserfields[$header]) && !isset($this->reservedheaders[$header])) {
                $key = $header;
            }
            if ($key === '' || !isset($this->profilefields[$key])) {
                continue;
            }

            $field = $this->profilefields[$key];
            $value = trim((string)$rawvalue);
            if ($value === '') {
                if ($isnew || $this->ignore_empty_values()) {
                    continue;
                }
                // Clear mode: the field type turns an empty value into its empty/default value.
                $prepared[$field->inputname] = ['field' => $field, 'value' => ''];
                continue;
            }

            $value = clean_param($value, PARAM_TEXT);
            $converted = $this->is_valid_profile_value($field, $value) ? $value : null;
            if ($converted !== null && method_exists($field, 'convert_external_data')) {
                $converted = $field->convert_external_data($value);
            }
            if ($converted === null) {
                $this->logger->warning(get_string('warning:invalidprofilevalue', 'local_csv_user_sync', (object)[
                    'field' => $field->get_shortname(),
                    'value' => $value,
                ]), $rownum, null, $username);
                continue;
            }
            $value = (string)$converted;

            $prepared[$field->inputname] = ['field' => $field, 'value' => $value];
        }

        return $prepared;
    }

    /**
     * Type checks for profile field types that have no conversion of their own.
     *
     * - checkbox: 0 or 1;
     * - datetime: Unix timestamp, YYYY-MM-DD or YYYY-MM-DD-HH-MM-SS (the formats accepted by the field type).
     *
     * @param \profile_field_base $field
     * @param string $value non-empty value
     * @return bool
     */
    private function is_valid_profile_value(\profile_field_base $field, string $value): bool {
        $datatype = (string)($field->field->datatype ?? '');
        if ($datatype === 'checkbox') {
            return $value === '0' || $value === '1';
        }
        if ($datatype === 'datetime') {
            return ctype_digit($value)
                || preg_match('/^\d{4}-\d{1,2}-\d{1,2}(-\d{1,2}-\d{1,2}-\d{1,2})?$/', $value) === 1;
        }

        return true;
    }

    /**
     * Removes custom field values rejected by the field validation (for example unique fields).
     *
     * @param int $userid 0 for a new user
     * @param array<string,array{field:\profile_field_base,value:string}> $customfields
     * @param int $rownum
     * @param string $username
     * @return array<string,array{field:\profile_field_base,value:string}>
     */
    private function validate_custom_fields(int $userid, array $customfields, int $rownum, string $username): array {
        foreach ($customfields as $inputname => $item) {
            $usernew = (object)['id' => $userid, $inputname => $item['value']];
            $errors = $item['field']->edit_validate_field($usernew);
            if (!empty($errors)) {
                $this->logger->warning(get_string('warning:profilevaluerejected', 'local_csv_user_sync', (object)[
                    'field' => $item['field']->get_shortname(),
                    'message' => implode(' ', array_map('strval', $errors)),
                ]), $rownum, $userid ?: null, $username);
                unset($customfields[$inputname]);
            }
        }

        return $customfields;
    }

    /**
     * Save custom profile fields through the core profile API.
     *
     * @param int $userid
     * @param array<string,array{field:\profile_field_base,value:string}> $customfields
     * @return void
     */
    private function save_custom_fields(int $userid, array $customfields): void {
        if (empty($customfields)) {
            return;
        }

        $usernew = (object)['id' => $userid];
        foreach ($customfields as $inputname => $item) {
            $usernew->{$inputname} = $item['value'];
        }
        profile_save_data($usernew);
    }

    /**
     * Create one user from prepared data.
     *
     * @param array<string,string> $userdata
     * @param array<string,array{field:\profile_field_base,value:string}> $customfields
     * @param int $rownum
     * @param string $username
     * @return array{status:string,userid:?int,newuser:bool,username:string,conflict:?array}
     */
    private function create_user(array $userdata, array $customfields, int $rownum, string $username): array {
        global $CFG;

        $customfields = $this->validate_custom_fields(0, $customfields, $rownum, $username);

        $dryrun = !empty($this->config['dryrun']);
        if ($dryrun) {
            $this->logger->info(get_string('log:usercreate_dryrun', 'local_csv_user_sync', $username), $rownum, null, $username);
            return $this->result('created', null, true, $username);
        }

        try {
            $now = time();

            $newuser = (object)$userdata;
            $newuser->confirmed = 1;
            $newuser->mnethostid = $CFG->mnet_localhost_id;
            $newuser->timecreated = $now;
            $newuser->timemodified = $now;
            if (empty($newuser->lang)) {
                $newuser->lang = get_newuser_language();
            }
            $newuser->password = generate_password(12);

            // The event is triggered after custom fields are saved, so observers see the complete profile.
            $userid = (int)user_create_user($newuser, true, false);
            $this->save_custom_fields($userid, $customfields);
            \core\event\user_created::create_from_userid($userid)->trigger();

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
            return $this->result('created', $userid, true, $username);
        } catch (\Throwable $e) {
            $this->logger->error(
                get_string('error:usercreate', 'local_csv_user_sync', $this->readable_exception_message($e)),
                $rownum,
                null,
                $username
            );
            return $this->result('error', null, true, $username);
        }
    }

    /**
     * Update one existing user from prepared data.
     *
     * @param stdClass $existing
     * @param array<string,string> $userdata
     * @param array<string,array{field:\profile_field_base,value:string}> $customfields
     * @param int $rownum
     * @param string $username
     * @return array{status:string,userid:?int,newuser:bool,username:string,conflict:?array}
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
        $userid = (int)$existing->id;

        $record = (object)['id' => $userid];
        $hasuserchanges = false;
        foreach ($userdata as $field => $value) {
            if ($field === 'username') {
                continue;
            }

            $current = property_exists($existing, $field) ? (string)$existing->{$field} : '';
            if ($field === 'email' && $this->normalize_email($current) === $this->normalize_email((string)$value)) {
                // Same address with different case or spaces: keep the stored value.
                continue;
            }
            if (!$updateonlychanged || $current !== (string)$value) {
                $record->{$field} = $value;
                if ($current !== (string)$value) {
                    $hasuserchanges = true;
                }
            }
        }

        $customfields = $this->validate_custom_fields($userid, $customfields, $rownum, $username);
        $customtoapply = [];
        if (!empty($customfields)) {
            $currentcustom = (array)profile_user_record($userid, false);
            foreach ($customfields as $inputname => $item) {
                $shortname = $item['field']->get_shortname();
                $current = isset($currentcustom[$shortname]) ? (string)$currentcustom[$shortname] : '';
                $newvalue = $item['field']->edit_save_data_preprocess($item['value'], new stdClass());
                $newvalue = $newvalue === null ? (string)$item['field']->field->defaultdata : (string)$newvalue;
                if (!$updateonlychanged || $current !== $newvalue) {
                    $customtoapply[$inputname] = $item;
                }
            }
        }
        $hascustomchanges = !empty($customtoapply);

        if (!$hasuserchanges && !$hascustomchanges) {
            $this->logger->debug(
                get_string('log:userunchanged', 'local_csv_user_sync', $username),
                $rownum,
                $userid,
                $username
            );
            return $this->result('unchanged', $userid, false, $username);
        }

        if ($dryrun) {
            $this->logger->info(
                get_string('log:userupdate_dryrun', 'local_csv_user_sync', $username),
                $rownum,
                $userid,
                $username
            );
            return $this->result('updated', $userid, false, $username);
        }

        try {
            if (count((array)$record) > 1) {
                user_update_user($record, false, false);
            }
            $this->save_custom_fields($userid, $customtoapply);
            // One event for core and custom field changes, triggered once everything is saved.
            \core\event\user_updated::create_from_userid($userid)->trigger();

            $this->logger->info(
                get_string('log:userupdated', 'local_csv_user_sync', $username),
                $rownum,
                $userid,
                $username
            );
            return $this->result('updated', $userid, false, $username);
        } catch (\Throwable $e) {
            $this->logger->error(
                get_string('error:userupdate', 'local_csv_user_sync', $this->readable_exception_message($e)),
                $rownum,
                $userid,
                $username
            );
            return $this->result('error', $userid, false, $username);
        }
    }

    /**
     * Returns true for accounts that must never be modified by the synchronization.
     *
     * Besides site administrators, accounts able to manage users or to provide CSV sources at system level
     * are protected: a CSV must not be able to change their email (password reset takeover) or auth method.
     *
     * @param stdClass $user
     * @return bool
     */
    private function is_protected_account(stdClass $user): bool {
        if (!empty($user->deleted) || isguestuser($user) || is_siteadmin((int)$user->id)) {
            return true;
        }

        $systemcontext = \context_system::instance();
        return has_capability('moodle/user:update', $systemcontext, (int)$user->id)
            || has_capability(folder_source::UPLOADCAPABILITY, $systemcontext, (int)$user->id);
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
     * Returns mandatory user columns whose value exceeds the database column length.
     *
     * @param array<string,string> $row
     * @param string $username normalized username
     * @return array<int,string>
     */
    private function get_too_long_mandatory_fields(array $row, string $username): array {
        $values = [
            'username' => $username,
            'firstname' => clean_param((string)$row['firstname'], PARAM_NOTAGS),
            'lastname' => clean_param((string)$row['lastname'], PARAM_NOTAGS),
            'email' => trim((string)$row['email']),
        ];

        $toolong = [];
        foreach ($values as $field => $value) {
            if (core_text::strlen($value) > self::FIELD_MAXLENGTH[$field]) {
                $toolong[] = $field;
            }
        }

        return $toolong;
    }

    /**
     * Returns the configured account matching mode.
     *
     * @return string
     */
    private function get_match_mode(): string {
        $mode = (string)($this->config['matchmode'] ?? self::MATCH_USERNAME_EMAIL);
        return $mode === self::MATCH_USERNAME ? self::MATCH_USERNAME : self::MATCH_USERNAME_EMAIL;
    }

    /**
     * Returns true when empty CSV values must be ignored (default).
     *
     * @return bool
     */
    private function ignore_empty_values(): bool {
        return (string)($this->config['emptyvalues'] ?? self::EMPTY_IGNORE) !== self::EMPTY_CLEAR;
    }

    /**
     * Normalize an email address for comparisons: trimmed and case-insensitive.
     *
     * @param string $email
     * @return string
     */
    private function normalize_email(string $email): string {
        return core_text::strtolower(trim($email));
    }

    /**
     * Build a sync result.
     *
     * @param string $status
     * @param int|null $userid
     * @param bool $newuser
     * @param string $username
     * @param array|null $conflict email conflict details, when the row was rejected for that reason
     * @return array{status:string,userid:?int,newuser:bool,username:string,conflict:?array}
     */
    private function result(string $status, ?int $userid, bool $newuser, string $username, ?array $conflict = null): array {
        return [
            'status' => $status,
            'userid' => $userid,
            'newuser' => $newuser,
            'username' => $username,
            'conflict' => $conflict,
        ];
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
