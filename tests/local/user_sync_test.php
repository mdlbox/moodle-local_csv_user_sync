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

namespace local_csv_user_sync\local;

/**
 * Tests for the user synchronizer.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\user_sync
 */
final class user_sync_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/profile/lib.php');
    }

    /**
     * Build a synchronizer with test defaults.
     *
     * @param array $config overrides
     * @return user_sync
     */
    private function get_sync(array $config = []): user_sync {
        $logger = new logger('phpunit', true, !empty($config['dryrun']));
        return new user_sync($logger, $config + [
            'dryrun' => false,
            'updateonlychanged' => true,
            'defaultauth' => 'manual',
            'sendemail' => false,
            'emailtemplate' => '',
            'matchmode' => user_sync::MATCH_USERNAME_EMAIL,
            'emptyvalues' => user_sync::EMPTY_IGNORE,
        ]);
    }

    /**
     * Run one row, discarding task output.
     *
     * @param user_sync $sync
     * @param array $row
     * @return array
     */
    private function sync_row(user_sync $sync, array $row): array {
        ob_start();
        try {
            return $sync->sync($row + ['firstname' => 'Test', 'lastname' => 'User'], 2);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Create the "codicescuola" text profile field.
     *
     * @return void
     */
    private function create_codice_field(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'shortname' => 'codicescuola',
            'name' => 'Codice scuola',
            'datatype' => 'text',
        ]);
    }

    /**
     * Returns the user record by username, or false.
     *
     * @param string $username
     * @return \stdClass|false
     */
    private function get_user(string $username) {
        return \core_user::get_user_by_username($username);
    }

    /**
     * The scenarios of the specification table, in sequence.
     */
    public function test_username_email_pairs_and_suffixes(): void {
        $sync = $this->get_sync();

        // No account: create test.user.
        $result = $this->sync_row($sync, ['username' => 'test.user', 'email' => 'test.user@example.com']);
        $this->assertSame('created', $result['status']);
        $this->assertSame('test.user', $result['username']);

        // Same pair: update test.user.
        $result = $this->sync_row($this->get_sync(), [
            'username' => 'test.user', 'email' => 'test.user@example.com', 'city' => 'Lugano',
        ]);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('test.user', $result['username']);
        $this->assertSame('Lugano', $this->get_user('test.user')->city);

        // Same username, other email: create test.user1, the original account is untouched.
        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'pippo@example.org']);
        $this->assertSame('created', $result['status']);
        $this->assertSame('test.user1', $result['username']);
        $this->assertSame('test.user@example.com', $this->get_user('test.user')->email);
        $this->assertSame('pippo@example.org', $this->get_user('test.user1')->email);

        // Account test.user1 exists with the same email: update it.
        $result = $this->sync_row($this->get_sync(), [
            'username' => 'test.user', 'email' => 'pippo@example.org', 'city' => 'Bellinzona',
        ]);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('test.user1', $result['username']);
        $this->assertSame('Bellinzona', $this->get_user('test.user1')->city);

        // Account test.user1 exists with another email: create test.user2.
        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'altra@example.org']);
        $this->assertSame('created', $result['status']);
        $this->assertSame('test.user2', $result['username']);
    }

    /**
     * A suffix freed by a deleted account is reused; occupied suffixes are skipped.
     */
    public function test_free_suffix_is_reused(): void {
        $generator = $this->getDataGenerator();
        $generator->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $deleted = $generator->create_user(['username' => 'test.user1', 'email' => 'old@example.org']);
        $generator->create_user(['username' => 'test.user2', 'email' => 'other@example.org']);
        delete_user($deleted);

        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'pippo@example.org']);
        $this->assertSame('created', $result['status']);
        $this->assertSame('test.user1', $result['username']);

        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'new@example.org']);
        $this->assertSame('test.user3', $result['username']);
    }

    /**
     * An existing suffixed account with the same email is updated instead of filling a free suffix.
     */
    public function test_existing_suffixed_account_with_same_email_is_preferred(): void {
        $generator = $this->getDataGenerator();
        $generator->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $generator->create_user(['username' => 'test.user2', 'email' => 'pippo@example.org']);

        $result = $this->sync_row($this->get_sync(), [
            'username' => 'test.user', 'email' => 'pippo@example.org', 'city' => 'Locarno',
        ]);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('test.user2', $result['username']);
        $this->assertFalse($this->get_user('test.user1'));
    }

    /**
     * Emails are compared case-insensitively and the stored address is not rewritten.
     */
    public function test_email_comparison_is_case_insensitive(): void {
        $user = $this->getDataGenerator()->create_user([
            'username' => 'test.user', 'email' => 'Test.User@EXAMPLE.com',
            'firstname' => 'Test', 'lastname' => 'User',
        ]);

        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'test.user@example.com']);
        $this->assertSame('unchanged', $result['status']);
        $this->assertSame((int)$user->id, $result['userid']);
        $this->assertSame('Test.User@EXAMPLE.com', $this->get_user('test.user')->email);
    }

    /**
     * Repeated rows map to the same account, also in dry-run where nothing is written.
     */
    public function test_repeated_rows_in_dry_run(): void {
        $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $sync = $this->get_sync(['dryrun' => true]);

        $first = $this->sync_row($sync, ['username' => 'test.user', 'email' => 'pippo@example.org']);
        $again = $this->sync_row($sync, ['username' => 'test.user', 'email' => 'PIPPO@example.org']);
        $other = $this->sync_row($sync, ['username' => 'test.user', 'email' => 'altra@example.org']);

        $this->assertSame('test.user1', $first['username']);
        $this->assertSame('test.user1', $again['username']);
        $this->assertSame('test.user2', $other['username']);
        $this->assertFalse($this->get_user('test.user1'));
    }

    /**
     * When accounts with the same email are not allowed, the row is skipped and reported as a conflict.
     */
    public function test_email_already_used_by_other_account_is_a_conflict(): void {
        set_config('allowaccountssameemail', 0);
        $this->getDataGenerator()->create_user(['username' => 'other.user', 'email' => 'pippo@example.org']);

        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'Pippo@example.org']);
        $this->assertSame('error', $result['status']);
        $this->assertSame(['other.user'], $result['conflict']['existing']);
        $this->assertFalse($this->get_user('test.user'));
    }

    /**
     * When accounts with the same email are allowed, the account is created.
     */
    public function test_email_already_used_is_allowed_by_site_setting(): void {
        set_config('allowaccountssameemail', 1);
        $this->getDataGenerator()->create_user(['username' => 'other.user', 'email' => 'pippo@example.org']);

        $result = $this->sync_row($this->get_sync(), ['username' => 'test.user', 'email' => 'pippo@example.org']);
        $this->assertSame('created', $result['status']);
        $this->assertNull($result['conflict']);
    }

    /**
     * Custom profile fields are saved on creation and on update, including shortnames with uppercase letters
     * and menu fields.
     */
    public function test_custom_profile_fields_on_create_and_update(): void {
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['shortname' => 'CodiceScuola', 'name' => 'Codice scuola', 'datatype' => 'text']);
        $generator->create_custom_profile_field([
            'shortname' => 'sede', 'name' => 'Sede', 'datatype' => 'menu', 'param1' => "Lugano\nBellinzona", 'defaultdata' => '',
        ]);

        $row = ['username' => 'test.user', 'email' => 'test.user@example.com'];
        $result = $this->sync_row($this->get_sync(), $row + [
            'profile_field_codicescuola' => 'S100',
            'profile_field_sede' => 'Lugano',
        ]);
        $this->assertSame('created', $result['status']);
        $profile = profile_user_record($result['userid'], false);
        $this->assertSame('S100', $profile->CodiceScuola);
        $this->assertSame('Lugano', $profile->sede);

        // Update with only custom field changes.
        $result = $this->sync_row($this->get_sync(), $row + ['profile_field_codicescuola' => 'S200', 'sede' => 'Bellinzona']);
        $this->assertSame('updated', $result['status']);
        $profile = profile_user_record($result['userid'], false);
        $this->assertSame('S200', $profile->CodiceScuola);
        $this->assertSame('Bellinzona', $profile->sede);

        // Same values: nothing to update.
        $result = $this->sync_row($this->get_sync(), $row + ['profile_field_codicescuola' => 'S200', 'sede' => 'Bellinzona']);
        $this->assertSame('unchanged', $result['status']);

        // Invalid menu option: the stored value is kept.
        $this->sync_row($this->get_sync(), $row + ['profile_field_sede' => 'Zurigo']);
        $this->assertSame('Bellinzona', profile_user_record($result['userid'], false)->sede);
    }

    /**
     * Empty values are ignored by default.
     */
    public function test_empty_values_are_ignored_by_default(): void {
        $this->create_codice_field();
        $user = $this->getDataGenerator()->create_user([
            'username' => 'test.user', 'email' => 'test.user@example.com',
            'firstname' => 'Test', 'lastname' => 'User', 'city' => 'Lugano', 'auth' => 'manual',
        ]);
        profile_save_custom_fields($user->id, ['codicescuola' => 'S100']);

        $result = $this->sync_row($this->get_sync(), [
            'username' => 'test.user', 'email' => 'test.user@example.com',
            'city' => '', 'auth' => '', 'profile_field_codicescuola' => '',
        ]);
        $this->assertSame('unchanged', $result['status']);
        $this->assertSame('Lugano', $this->get_user('test.user')->city);
        $this->assertSame('S100', profile_user_record($user->id, false)->codicescuola);
    }

    /**
     * Empty values clear existing values when configured.
     */
    public function test_empty_values_clear_when_configured(): void {
        $this->create_codice_field();
        $user = $this->getDataGenerator()->create_user([
            'username' => 'test.user', 'email' => 'test.user@example.com',
            'firstname' => 'Test', 'lastname' => 'User', 'city' => 'Lugano',
        ]);
        profile_save_custom_fields($user->id, ['codicescuola' => 'S100']);

        $result = $this->sync_row($this->get_sync(['emptyvalues' => user_sync::EMPTY_CLEAR]), [
            'username' => 'test.user', 'email' => 'test.user@example.com',
            'city' => '', 'profile_field_codicescuola' => '',
        ]);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('', $this->get_user('test.user')->city);
        $this->assertSame('', (string)profile_user_record($user->id, false)->codicescuola);
    }

    /**
     * In clear mode the authentication method is kept when the CSV has no auth column.
     */
    public function test_clear_mode_keeps_auth_without_auth_column(): void {
        $this->getDataGenerator()->create_user([
            'username' => 'test.user', 'email' => 'test.user@example.com',
            'firstname' => 'Test', 'lastname' => 'User', 'auth' => 'nologin', 'city' => 'Lugano',
        ]);

        $this->sync_row($this->get_sync(['emptyvalues' => user_sync::EMPTY_CLEAR]), [
            'username' => 'test.user', 'email' => 'test.user@example.com', 'city' => 'Locarno',
        ]);
        $user = $this->get_user('test.user');
        $this->assertSame('nologin', $user->auth);
        $this->assertSame('Locarno', $user->city);
    }

    /**
     * Accounts able to manage users at system level are protected like site administrators.
     */
    public function test_privileged_account_is_protected(): void {
        global $DB;

        $manager = $this->getDataGenerator()->create_user(['username' => 'boss', 'email' => 'boss@example.com']);
        $managerroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $result = $this->sync_row($this->get_sync(['matchmode' => user_sync::MATCH_USERNAME]), [
            'username' => 'boss', 'email' => 'attacker@example.org',
        ]);
        $this->assertSame('error', $result['status']);
        $this->assertSame('boss@example.com', $this->get_user('boss')->email);
    }

    /**
     * Site administrators are never modified.
     */
    public function test_site_admin_is_protected(): void {
        $admin = get_admin();

        $result = $this->sync_row($this->get_sync(), [
            'username' => $admin->username, 'email' => $admin->email, 'firstname' => 'Changed', 'lastname' => 'Changed',
        ]);
        $this->assertSame('error', $result['status']);
        $this->assertSame($admin->firstname, \core_user::get_user($admin->id)->firstname);
    }

    /**
     * The legacy username-only mode still updates the email of the account with the same username.
     */
    public function test_username_only_mode_keeps_previous_behaviour(): void {
        set_config('allowaccountssameemail', 0);
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);

        $result = $this->sync_row($this->get_sync(['matchmode' => user_sync::MATCH_USERNAME]), [
            'username' => 'test.user', 'email' => 'pippo@example.org',
        ]);
        $this->assertSame('updated', $result['status']);
        $this->assertSame((int)$user->id, $result['userid']);
        $this->assertSame('pippo@example.org', $this->get_user('test.user')->email);
    }
}
