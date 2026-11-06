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
 * Tests for account deletion requested by CSV rows.
 *
 * @package     local_csv_user_sync
 * @category    test
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_csv_user_sync\local\user_deletion
 */
final class user_deletion_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build a deletion handler.
     *
     * @param array $config overrides
     * @return user_deletion
     */
    private function get_deletion(array $config = []): user_deletion {
        $logger = new logger('phpunit', true, !empty($config['dryrun']));
        return new user_deletion($logger, $config + ['enabled' => true, 'max' => 10, 'dryrun' => false, 'notifyemail' => '']);
    }

    /**
     * Run a callable discarding task output.
     *
     * @param callable $callback
     * @return mixed
     */
    private function quietly(callable $callback) {
        ob_start();
        try {
            return $callback();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Request the deletion of one account, discarding task output.
     *
     * @param user_deletion $deletion
     * @param string $username
     * @param string $email
     * @param bool $authorallowed
     * @return bool
     */
    private function request(user_deletion $deletion, string $username, string $email, bool $authorallowed): bool {
        $row = ['username' => $username, 'email' => $email];
        return $this->quietly(fn() => $deletion->request($row, 2, 'test', $authorallowed));
    }

    /**
     * Returns true when the account is marked as deleted.
     *
     * @param int $userid
     * @return bool
     */
    private function is_deleted(int $userid): bool {
        return !empty(\core_user::get_user($userid)->deleted);
    }

    /**
     * Deletion is refused while disabled.
     */
    public function test_disabled_deletion_is_refused(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $deletion = $this->get_deletion(['enabled' => false]);

        $accepted = $this->request($deletion, 'test.user', 'test.user@example.com', true);
        $this->quietly(fn() => $deletion->execute());

        $this->assertFalse($accepted);
        $this->assertFalse($this->is_deleted((int)$user->id));
    }

    /**
     * An exact username + email match is deleted at the end, and a summary email is sent.
     */
    public function test_exact_match_is_deleted_and_notified(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'Test.User@example.com']);
        $deletion = $this->get_deletion();

        $accepted = $this->request($deletion, 'TEST.USER', 'test.user@EXAMPLE.com', true);
        $this->assertTrue($accepted);
        $this->assertTrue($deletion->is_pending('test.user'));
        $this->assertFalse($this->is_deleted((int)$user->id));

        $sink = $this->redirectEmails();
        $deleted = $this->quietly(fn() => $deletion->execute());
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(1, $deleted);
        $this->assertTrue($this->is_deleted((int)$user->id));
        $this->assertNotEmpty($messages);
    }

    /**
     * A different email never deletes the account, nor a suffixed account with that email.
     */
    public function test_no_match_without_exact_email(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $suffixed = $this->getDataGenerator()->create_user(['username' => 'test.user1', 'email' => 'other@example.org']);
        $deletion = $this->get_deletion();

        $this->request($deletion, 'test.user', 'other@example.org', true);
        $this->quietly(fn() => $deletion->execute());

        $this->assertSame(0, $deletion->count());
        $this->assertFalse($this->is_deleted((int)$user->id));
        $this->assertFalse($this->is_deleted((int)$suffixed->id));
    }

    /**
     * Protected accounts and unauthorised authors are refused.
     */
    public function test_protected_account_and_author_are_refused(): void {
        $admin = get_admin();
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $deletion = $this->get_deletion();

        $adminrequest = $this->request($deletion, $admin->username, $admin->email, true);
        $authorrequest = $this->request($deletion, 'test.user', 'test.user@example.com', false);
        $this->quietly(fn() => $deletion->execute());

        $this->assertFalse($adminrequest);
        $this->assertFalse($authorrequest);
        $this->assertFalse($this->is_deleted((int)$admin->id));
        $this->assertFalse($this->is_deleted((int)$user->id));
    }

    /**
     * A site administrator with a non-manual authentication method is protected too
     * (core delete_user() only refuses manual administrators).
     */
    public function test_admin_with_external_auth_is_protected(): void {
        global $CFG;
        $admin = $this->getDataGenerator()->create_user([
            'username' => 'sso.admin', 'email' => 'sso.admin@example.com', 'auth' => 'nologin',
        ]);
        set_config('siteadmins', $CFG->siteadmins . ',' . $admin->id);
        $deletion = $this->get_deletion();

        $accepted = $this->request($deletion, 'sso.admin', 'sso.admin@example.com', true);
        $this->quietly(fn() => $deletion->execute());

        $this->assertFalse($accepted);
        $this->assertFalse($this->is_deleted((int)$admin->id));
    }

    /**
     * Above the maximum per run nothing is deleted and the administrators are warned.
     */
    public function test_limit_blocks_every_deletion(): void {
        $first = $this->getDataGenerator()->create_user(['username' => 'first.user', 'email' => 'first@example.com']);
        $second = $this->getDataGenerator()->create_user(['username' => 'second.user', 'email' => 'second@example.com']);
        $deletion = $this->get_deletion(['max' => 1]);

        $this->request($deletion, 'first.user', 'first@example.com', true);
        $this->request($deletion, 'second.user', 'second@example.com', true);

        $sink = $this->redirectEmails();
        $deleted = $this->quietly(fn() => $deletion->execute());
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(0, $deleted);
        $this->assertFalse($this->is_deleted((int)$first->id));
        $this->assertFalse($this->is_deleted((int)$second->id));
        $this->assertCount(1, $messages);
        $this->assertSame(get_admin()->email, $messages[0]->to);
    }

    /**
     * Dry-run never deletes nor sends emails.
     */
    public function test_dry_run(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'test.user', 'email' => 'test.user@example.com']);
        $deletion = $this->get_deletion(['dryrun' => true]);

        $this->request($deletion, 'test.user', 'test.user@example.com', true);
        $sink = $this->redirectEmails();
        $this->quietly(fn() => $deletion->execute());
        $this->assertSame(0, $sink->count());
        $sink->close();

        $this->assertFalse($this->is_deleted((int)$user->id));
    }

    /**
     * The flag parser accepts only 0, 1 and empty.
     */
    public function test_parse_flag(): void {
        $this->assertFalse(user_deletion::parse_flag([], user_deletion::COLUMN));
        $this->assertFalse(user_deletion::parse_flag([user_deletion::COLUMN => ' '], user_deletion::COLUMN));
        $this->assertFalse(user_deletion::parse_flag([user_deletion::COLUMN => '0'], user_deletion::COLUMN));
        $this->assertTrue(user_deletion::parse_flag([user_deletion::COLUMN => '1'], user_deletion::COLUMN));
        $this->assertNull(user_deletion::parse_flag([user_deletion::COLUMN => 'yes'], user_deletion::COLUMN));
    }
}
