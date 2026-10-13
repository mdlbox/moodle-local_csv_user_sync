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
 * Email conflict notifier for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use core_user;

/**
 * Collects email conflicts during one run and sends a single summary email at the end.
 */
class conflict_notifier {
    /** @var int Maximum number of conflicts listed in one email. */
    private const MAXLISTED = 200;

    /** @var logger */
    private readonly logger $logger;

    /** @var string Configured recipient address, empty means site administrators. */
    private readonly string $recipientemail;

    /** @var bool */
    private readonly bool $dryrun;

    /** @var array<int,\stdClass> */
    private array $conflicts = [];

    /**
     * Constructor.
     *
     * @param logger $logger
     * @param string $recipientemail
     * @param bool $dryrun
     */
    public function __construct(logger $logger, string $recipientemail, bool $dryrun) {
        $this->logger = $logger;
        $this->recipientemail = trim($recipientemail);
        $this->dryrun = $dryrun;
    }

    /**
     * Records one conflict.
     *
     * @param string $source CSV source label
     * @param int $rownum
     * @param string $username username requested by the CSV row
     * @param string $email email requested by the CSV row
     * @param string[] $existingusernames accounts already using the email
     * @return void
     */
    public function add(string $source, int $rownum, string $username, string $email, array $existingusernames): void {
        $this->conflicts[] = (object)[
            'source' => $source,
            'row' => $rownum,
            'username' => $username,
            'email' => $email,
            'existing' => implode(', ', $existingusernames),
        ];
    }

    /**
     * Returns the number of recorded conflicts.
     *
     * @return int
     */
    public function count(): int {
        return count($this->conflicts);
    }

    /**
     * Sends the summary email when conflicts were recorded. Never sends in dry-run mode.
     *
     * @return void
     */
    public function send(): void {
        global $SITE;

        if (empty($this->conflicts)) {
            return;
        }

        if ($this->dryrun) {
            $this->logger->info(get_string('log:conflictnotify_dryrun', 'local_csv_user_sync', $this->count()));
            return;
        }

        $lines = [];
        foreach (array_slice($this->conflicts, 0, self::MAXLISTED) as $conflict) {
            $lines[] = '- ' . get_string('email:conflict_line', 'local_csv_user_sync', $conflict);
        }
        if ($this->count() > self::MAXLISTED) {
            $lines[] = get_string('email:conflict_more', 'local_csv_user_sync', $this->count() - self::MAXLISTED);
        }

        $sitename = format_string($SITE->fullname);
        $subject = get_string('email:conflict_subject', 'local_csv_user_sync', $sitename);
        $body = get_string('email:conflict_body', 'local_csv_user_sync', (object)[
            'sitename' => $sitename,
            'count' => $this->count(),
            'conflicts' => implode("\n", $lines),
        ]);

        $sender = core_user::get_noreply_user();
        $sent = 0;
        foreach ($this->get_recipients() as $recipient) {
            if (email_to_user($recipient, $sender, $subject, $body, text_to_html(s($body)))) {
                $sent++;
            }
        }

        if ($sent > 0) {
            $this->logger->info(get_string('log:conflictnotified', 'local_csv_user_sync', (object)[
                'count' => $this->count(),
                'recipients' => $sent,
            ]));
        } else {
            $this->logger->error(get_string('error:conflictnotifyfailed', 'local_csv_user_sync'));
        }
    }

    /**
     * Returns notification recipients: the configured address, or all site administrators.
     *
     * A configured address is not tied to an account, so the main administrator record is used
     * as a carrier with the email overridden (same approach as core badges verification emails).
     *
     * @return \stdClass[]
     */
    private function get_recipients(): array {
        if ($this->recipientemail !== '' && validate_email($this->recipientemail)) {
            $admin = get_admin();
            if (!$admin) {
                return [];
            }
            $recipient = clone $admin;
            $recipient->email = $this->recipientemail;
            return [$recipient];
        }

        return array_values(get_admins());
    }
}
