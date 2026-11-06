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
 * Logger helper for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use core_text;

/**
 * Writes logs to mtrace and plugin table.
 */
class logger {
    /** @var int Maximum number of characters stored per log message. */
    private const MAXMESSAGELENGTH = 500;

    /** @var string */
    private readonly string $runid;

    /** @var bool */
    private readonly bool $detailedlog;

    /** @var bool */
    private readonly bool $dryrun;

    /** @var bool */
    private $tableexists = false;

    /** @var array<string,int> Number of entries written per level, also when they are not displayed. */
    private array $counts = ['info' => 0, 'debug' => 0, 'warning' => 0, 'error' => 0];

    /**
     * Constructor.
     *
     * @param string $runid
     * @param bool $detailedlog
     * @param bool $dryrun
     */
    public function __construct(
        string $runid,
        bool $detailedlog,
        bool $dryrun
    ) {
        global $DB;
        $this->runid = $runid;
        $this->detailedlog = $detailedlog;
        $this->dryrun = $dryrun;
        $this->tableexists = $DB->get_manager()->table_exists('local_csv_user_sync_log');
    }

    /**
     * Returns run identifier.
     *
     * @return string
     */
    public function get_runid(): string {
        return $this->runid;
    }

    /**
     * Writes a summary line: always displayed and stored, also without detailed logging.
     *
     * @param string $message
     * @param string $level log level, info by default
     * @return void
     */
    public function summary(string $message, string $level = 'info'): void {
        $this->write($level, $message, null, null, null, true);
    }

    /**
     * Returns the number of entries written with the given level during this run.
     *
     * @param string $level
     * @return int
     */
    public function get_count(string $level): int {
        return $this->counts[$level] ?? 0;
    }

    /**
     * Returns true when detailed logging is enabled.
     *
     * @return bool
     */
    public function is_detailed(): bool {
        return $this->detailedlog;
    }

    /**
     * Writes an info line.
     *
     * @param string $message
     * @param int|null $rownum
     * @param int|null $userid
     * @param string|null $username
     * @return void
     */
    public function info(string $message, ?int $rownum = null, ?int $userid = null, ?string $username = null): void {
        $this->write('info', $message, $rownum, $userid, $username);
    }

    /**
     * Writes a debug line.
     *
     * @param string $message
     * @param int|null $rownum
     * @param int|null $userid
     * @param string|null $username
     * @return void
     */
    public function debug(string $message, ?int $rownum = null, ?int $userid = null, ?string $username = null): void {
        $this->write('debug', $message, $rownum, $userid, $username);
    }

    /**
     * Writes a warning line.
     *
     * @param string $message
     * @param int|null $rownum
     * @param int|null $userid
     * @param string|null $username
     * @return void
     */
    public function warning(string $message, ?int $rownum = null, ?int $userid = null, ?string $username = null): void {
        $this->write('warning', $message, $rownum, $userid, $username);
    }

    /**
     * Writes an error line.
     *
     * @param string $message
     * @param int|null $rownum
     * @param int|null $userid
     * @param string|null $username
     * @return void
     */
    public function error(string $message, ?int $rownum = null, ?int $userid = null, ?string $username = null): void {
        $this->write('error', $message, $rownum, $userid, $username);
    }

    /**
     * Writes one log entry.
     *
     * Without detailed logging only summary lines are displayed and stored; the other entries are only counted.
     *
     * @param string $level
     * @param string $message
     * @param int|null $rownum
     * @param int|null $userid
     * @param string|null $username
     * @param bool $summary true for a summary line
     * @return void
     */
    private function write(
        string $level,
        string $message,
        ?int $rownum = null,
        ?int $userid = null,
        ?string $username = null,
        bool $summary = false
    ): void {
        global $DB;

        $this->counts[$level] = ($this->counts[$level] ?? 0) + 1;
        if (!$summary && !$this->detailedlog) {
            return;
        }

        $message = self::sanitize_log_message($message);

        $prefix = $this->dryrun ? '[local_csv_user_sync][dry-run]' : '[local_csv_user_sync]';
        $rowsuffix = $rownum !== null ? ' [row ' . $rownum . ']' : '';
        mtrace($prefix . ' ' . $level . $rowsuffix . ': ' . $message);

        if (!$this->tableexists) {
            return;
        }

        $record = (object)[
            'runid' => $this->runid,
            'level' => $level,
            'rownum' => $rownum,
            'userid' => $userid,
            'username' => $username ?? '',
            'message' => $message,
            'timecreated' => time(),
        ];

        try {
            $DB->insert_record('local_csv_user_sync_log', $record);
        } catch (\Throwable $e) {
            // Prevent repeated DB failures in the same run.
            $this->tableexists = false;
            $errormessage = self::sanitize_exception_message($e);
            if ($errormessage === '') {
                $errormessage = get_string('task:unknownerror', 'local_csv_user_sync');
            }
            mtrace('[local_csv_user_sync] ' . get_string('log:errorpersist', 'local_csv_user_sync', $errormessage));
        }
    }

    /**
     * Sanitizes a Throwable message before writing it to logs.
     *
     * @param \Throwable $exception
     * @return string
     */
    public static function sanitize_exception_message(\Throwable $exception): string {
        return self::sanitize_log_message((string)$exception->getMessage());
    }

    /**
     * Normalizes one log message and redacts common absolute path patterns.
     *
     * @param string $message
     * @return string
     */
    public static function sanitize_log_message(string $message): string {
        if ($message === '') {
            return '';
        }

        // Patterns are matched in UTF-8 mode: without it \s follows the server locale and, for example with
        // Windows-1252, matches the second byte of "à" (0xC3 0xA0), producing invalid UTF-8 that the database rejects.
        $message = fix_utf8($message);
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? $message;
        $message = preg_replace('/(^|\s)[A-Za-z]:\\\\[^\s]+/u', '$1[path]', $message) ?? $message;
        $message = preg_replace('#(^|\s)/(?:[^/\s]+/)+[^/\s]*#u', '$1[path]', $message) ?? $message;
        $message = trim((string)(preg_replace('/\s+/u', ' ', $message) ?? $message));

        if (core_text::strlen($message) > self::MAXMESSAGELENGTH) {
            $message = core_text::substr($message, 0, self::MAXMESSAGELENGTH) . '...';
        }

        return $message;
    }
}
