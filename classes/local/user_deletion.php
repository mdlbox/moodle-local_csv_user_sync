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
 * Account deletion requested by CSV rows (user_delete column).
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use core_text;
use core_user;

/**
 * Collects deletion requests during a run and executes them at the end, all or none.
 *
 * Rules:
 * - disabled unless local_csv_user_sync/userdeleteenabled is set;
 * - only an existing account matching both username and email exactly (email case-insensitive), never a suffixed one;
 * - protected accounts (site administrators, guest, privileged accounts) are never deleted;
 * - the CSV author must hold moodle/user:delete in system context;
 * - if more deletions than local_csv_user_sync/userdeletemax are requested in one run, none is executed
 *   and the administrators are warned;
 * - deletions use the core delete_user() API and a summary email is sent after each run.
 */
class user_deletion {
    /** @var string CSV column requesting the deletion of the account. */
    public const COLUMN = 'user_delete';

    /** @var string Capability the CSV author must hold in system context. */
    public const CAPABILITY = 'moodle/user:delete';

    /** @var int Default maximum number of deletions per run. */
    public const DEFAULT_MAX = 10;

    /** @var int Maximum number of accounts listed in one email. */
    private const MAXLISTED = 200;

    /** @var logger */
    private readonly logger $logger;

    /** @var bool */
    private readonly bool $enabled;

    /** @var int */
    private readonly int $max;

    /** @var bool */
    private readonly bool $dryrun;

    /** @var string */
    private readonly string $notifyemail;

    /** @var array<int,\stdClass> Requested deletions indexed by user id. */
    private array $queue = [];

    /** @var array<string,bool> Usernames of the requested deletions. */
    private array $pendingusernames = [];

    /**
     * Constructor.
     *
     * @param logger $logger
     * @param array<string,mixed> $config enabled, max, dryrun, notifyemail
     */
    public function __construct(logger $logger, array $config) {
        $this->logger = $logger;
        $this->enabled = !empty($config['enabled']);
        $this->max = max(0, (int)($config['max'] ?? self::DEFAULT_MAX));
        $this->dryrun = !empty($config['dryrun']);
        $this->notifyemail = (string)($config['notifyemail'] ?? '');
    }

    /**
     * Parse a 0/1 flag column. Missing or empty means false.
     *
     * @param array<string,string> $row
     * @param string $column
     * @return bool|null null when the value is invalid
     */
    public static function parse_flag(array $row, string $column): ?bool {
        $value = trim((string)($row[$column] ?? ''));
        if ($value === '' || $value === '0') {
            return false;
        }

        return $value === '1' ? true : null;
    }

    /**
     * Returns true when the author of a CSV file may request deletions.
     *
     * @param int $authorid
     * @return bool
     */
    public static function author_can_delete(int $authorid): bool {
        if ($authorid <= 0) {
            return false;
        }
        $author = core_user::get_user($authorid, 'id, deleted, suspended');
        if (!$author || !empty($author->deleted) || !empty($author->suspended)) {
            return false;
        }

        return has_capability(self::CAPABILITY, \context_system::instance(), $authorid);
    }

    /**
     * Register the deletion requested by one CSV row.
     *
     * @param array<string,string> $row
     * @param int $rownum
     * @param string $source source label, for logs and the summary email
     * @param bool $authorallowed whether the source author may request deletions
     * @return bool false when the request is refused (counted as an error)
     */
    public function request(array $row, int $rownum, string $source, bool $authorallowed): bool {
        $username = core_text::strtolower(trim((string)($row['username'] ?? '')));
        $email = trim((string)($row['email'] ?? ''));

        if (!$this->enabled) {
            $this->logger->error(get_string('error:userdeletedisabled', 'local_csv_user_sync'), $rownum, null, $username);
            return false;
        }

        if (!$authorallowed) {
            $this->logger->error(get_string('error:userdeleteauthor', 'local_csv_user_sync'), $rownum, null, $username);
            return false;
        }

        if ($username === '' || $email === '') {
            $this->logger->error(get_string('error:userdeletemissing', 'local_csv_user_sync'), $rownum, null, $username);
            return false;
        }

        // Exact match only: the requested username with the same email. Suffixed accounts are never considered.
        $user = core_user::get_user_by_username($username);
        $matches = $user && empty($user->deleted)
            && core_text::strtolower(trim((string)$user->email)) === core_text::strtolower($email);
        if (!$matches) {
            $this->logger->warning(
                get_string('warning:userdeletenomatch', 'local_csv_user_sync', $username),
                $rownum,
                null,
                $username
            );
            return true;
        }

        if (user_sync::is_protected_account($user)) {
            $this->logger->error(
                get_string('error:protectedaccount', 'local_csv_user_sync', $username),
                $rownum,
                (int)$user->id,
                $username
            );
            return false;
        }

        if (!isset($this->queue[(int)$user->id])) {
            $this->queue[(int)$user->id] = (object)[
                'userid' => (int)$user->id,
                'username' => (string)$user->username,
                'email' => (string)$user->email,
                'source' => $source,
                'row' => $rownum,
            ];
            $this->pendingusernames[(string)$user->username] = true;
        }
        $this->logger->info(
            get_string('log:userdeletequeued', 'local_csv_user_sync', $username),
            $rownum,
            (int)$user->id,
            $username
        );

        return true;
    }

    /**
     * Returns true when the account is requested for deletion in this run: other rows must not update it.
     *
     * @param string $username
     * @return bool
     */
    public function is_pending(string $username): bool {
        return isset($this->pendingusernames[$username]);
    }

    /**
     * Returns the number of requested deletions.
     *
     * @return int
     */
    public function count(): int {
        return count($this->queue);
    }

    /**
     * Execute the requested deletions, all or none, and notify.
     *
     * @return int number of deleted accounts
     */
    public function execute(): int {
        if (empty($this->queue)) {
            return 0;
        }

        $requested = $this->count();
        if ($requested > $this->max) {
            $this->logger->error(get_string('error:userdeletelimit', 'local_csv_user_sync', (object)[
                'requested' => $requested,
                'max' => $this->max,
            ]));
            if ($this->dryrun) {
                $this->logger->info(get_string('log:userdeletenotify_dryrun', 'local_csv_user_sync'));
            } else {
                $this->notify('email:userdeleteblocked_subject', 'email:userdeleteblocked_body', array_values($this->queue));
            }
            return 0;
        }

        if ($this->dryrun) {
            foreach ($this->queue as $entry) {
                $this->logger->info(
                    get_string('log:userdelete_dryrun', 'local_csv_user_sync', $entry->username),
                    $entry->row,
                    $entry->userid,
                    $entry->username
                );
            }
            $this->logger->info(get_string('log:userdeletenotify_dryrun', 'local_csv_user_sync'));
            return 0;
        }

        $deleted = [];
        foreach ($this->queue as $entry) {
            // Check again right before deleting: the account may have changed during the run.
            $user = core_user::get_user($entry->userid);
            if (!$user || !empty($user->deleted)) {
                continue;
            }
            $unchanged = (string)$user->username === $entry->username
                && core_text::strtolower(trim((string)$user->email)) === core_text::strtolower(trim($entry->email));
            if (!$unchanged) {
                $this->logger->warning(
                    get_string('warning:userdeletechanged', 'local_csv_user_sync', $entry->username),
                    $entry->row,
                    $entry->userid,
                    $entry->username
                );
                continue;
            }
            if (user_sync::is_protected_account($user)) {
                $this->logger->error(
                    get_string('error:protectedaccount', 'local_csv_user_sync', $entry->username),
                    $entry->row,
                    $entry->userid,
                    $entry->username
                );
                continue;
            }

            try {
                if (delete_user($user)) {
                    $deleted[] = $entry;
                    $this->logger->info(
                        get_string('log:userdeleted', 'local_csv_user_sync', $entry->username),
                        $entry->row,
                        $entry->userid,
                        $entry->username
                    );
                } else {
                    $this->logger->error(
                        get_string('error:userdeletefailed', 'local_csv_user_sync', $entry->username),
                        $entry->row,
                        $entry->userid,
                        $entry->username
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error(
                    get_string('error:userdeletefailed', 'local_csv_user_sync', $entry->username)
                        . ' ' . logger::sanitize_exception_message($e),
                    $entry->row,
                    $entry->userid,
                    $entry->username
                );
            }
        }

        $this->logger->info(get_string('log:userdeletesummary', 'local_csv_user_sync', (object)[
            'requested' => $requested,
            'deleted' => count($deleted),
        ]));
        if (!empty($deleted)) {
            $this->notify('email:userdeleted_subject', 'email:userdeleted_body', $deleted);
        }

        return count($deleted);
    }

    /**
     * Send a deletion email to the site administrators and the configured notification address.
     *
     * @param string $subjectkey
     * @param string $bodykey
     * @param \stdClass[] $entries
     * @return void
     */
    private function notify(string $subjectkey, string $bodykey, array $entries): void {
        global $SITE;

        $lines = [];
        foreach (array_slice($entries, 0, self::MAXLISTED) as $entry) {
            $lines[] = '- ' . get_string('email:userdelete_line', 'local_csv_user_sync', $entry);
        }
        if (count($entries) > self::MAXLISTED) {
            $lines[] = get_string('email:conflict_more', 'local_csv_user_sync', count($entries) - self::MAXLISTED);
        }

        $sitename = format_string($SITE->fullname, true, ['escape' => false]);
        $subject = get_string($subjectkey, 'local_csv_user_sync', $sitename);
        $body = get_string($bodykey, 'local_csv_user_sync', (object)[
            'sitename' => $sitename,
            'count' => count($entries),
            'max' => $this->max,
            'users' => implode("\n", $lines),
        ]);

        if (notification_mailer::send($subject, $body, $this->notifyemail, true) === 0) {
            $this->logger->error(get_string('error:userdeletenotifyfailed', 'local_csv_user_sync'));
        }
    }
}
