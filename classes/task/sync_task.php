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
 * Scheduled task implementation for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\task;

use context_system;
use core\lock\lock;
use core\lock\lock_config;
use local_csv_user_sync\local\csv_reader;
use local_csv_user_sync\local\enrol_sync;
use local_csv_user_sync\local\logger;
use local_csv_user_sync\local\user_sync;

/**
 * Scheduled task that runs CSV user synchronization.
 */
class sync_task extends \core\task\scheduled_task {
    /** @var string Moodle file area used for uploaded CSV source file. */
    private const CSV_FILEAREA = 'csvsource';

    /** @var int Moodle file itemid used for uploaded CSV source file. */
    private const CSV_ITEMID = 0;

    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:syncusers', 'local_csv_user_sync');
    }

    /**
     * Executes the synchronization.
     *
     * @return void
     */
    public function execute(): void {
        $lockfactory = lock_config::get_lock_factory('local_csv_user_sync');
        $lock = $lockfactory->get_lock('sync_task', 0, HOURSECS);
        if (!$lock) {
            mtrace('[local_csv_user_sync] ' . get_string('task:lockfailed', 'local_csv_user_sync'));
            return;
        }

        $config = get_config('local_csv_user_sync');
        $dryrun = !empty($config->dryrun);
        $runid = substr(hash('sha256', microtime(true) . ':' . random_int(1000, 9999)), 0, 16);
        $logger = new logger($runid, !empty($config->detailedlog), $dryrun);
        $tempcsvpath = null;

        try {
            $delimiter = (string)($config->delimiter ?? ';');
            $encoding = (string)($config->encoding ?? 'UTF-8');
            $updateonlychanged = !isset($config->updateonlychanged) || !empty($config->updateonlychanged);
            $defaultauth = trim((string)($config->defaultauth ?? 'manual'));
            if ($defaultauth === '') {
                $defaultauth = 'manual';
            }
            $emailtemplate = (string)($config->emailtemplate ?? '');
            $sendemail = !empty($config->sendemail);

            $logger->info(get_string('task:start', 'local_csv_user_sync', $dryrun ? get_string('yes') : get_string('no')));

            $source = $this->resolve_csv_source($config, $logger);
            if ($source === null) {
                return;
            }
            $csvpath = $source['filepath'];
            $tempcsvpath = $source['temppath'];

            $reader = new csv_reader($csvpath, $delimiter, $encoding, true);
            $validationerrors = $reader->validate_file();
            foreach ($validationerrors as $error) {
                $logger->error($error);
            }
            if (!empty($validationerrors)) {
                return;
            }
            $headers = $reader->get_headers();
            $headererrors = $this->validate_headers($headers);
            foreach ($headererrors as $error) {
                $logger->error($error);
            }
            if (!empty($headererrors)) {
                return;
            }

            $usersync = new user_sync($logger, [
                'dryrun' => $dryrun,
                'updateonlychanged' => $updateonlychanged,
                'defaultauth' => $defaultauth,
                'sendemail' => $sendemail,
                'emailtemplate' => $emailtemplate,
            ]);
            $enrolsync = new enrol_sync($logger, [
                'dryrun' => $dryrun,
            ]);

            $counters = (object)[
                'rows' => 0,
                'userscreated' => 0,
                'usersupdated' => 0,
                'enrolmentscreated' => 0,
                'enrolmentsupdated' => 0,
                'errors' => 0,
            ];

            foreach ($reader->get_rows() as $entry) {
                $counters->rows++;
                $rownum = (int)$entry['rownum'];
                $row = $entry['data'];

                try {
                    $userresult = $usersync->sync($row, $rownum);
                    if ($userresult['status'] === 'error') {
                        $counters->errors++;
                        continue;
                    }
                    if ($userresult['status'] === 'created') {
                        $counters->userscreated++;
                    } else if ($userresult['status'] === 'updated') {
                        $counters->usersupdated++;
                    }

                    if ($userresult['userid'] === null) {
                        if ($userresult['newuser'] && $dryrun && $enrolsync->row_has_enrolment($row)) {
                            $logger->info(
                                get_string('task:dryrunskipenrol', 'local_csv_user_sync', $userresult['username']),
                                $rownum,
                                null,
                                $userresult['username']
                            );
                        }
                        continue;
                    }

                    $enrolresult = $enrolsync->sync((int)$userresult['userid'], $row, $rownum);
                    if ($enrolresult['status'] === 'error') {
                        $counters->errors++;
                        continue;
                    }
                    if ($enrolresult['status'] === 'created') {
                        $counters->enrolmentscreated++;
                    } else if ($enrolresult['status'] === 'updated') {
                        $counters->enrolmentsupdated++;
                    }
                } catch (\Throwable $e) {
                    $counters->errors++;
                    $logger->error(
                        get_string('task:rowfatal', 'local_csv_user_sync', (object)[
                            'row' => $rownum,
                            'message' => $this->readable_exception_message($e),
                        ]),
                        $rownum
                    );
                }
            }

            $logger->info(get_string('task:summary', 'local_csv_user_sync', $counters));
        } catch (\Throwable $e) {
            $logger->error(get_string('task:fatal', 'local_csv_user_sync', $this->readable_exception_message($e)));
            throw $e;
        } finally {
            if (!empty($tempcsvpath) && is_file($tempcsvpath)) {
                @unlink($tempcsvpath);
            }
            $this->release_lock($lock);
        }
    }

    /**
     * Resolve configured CSV source.
     *
     * Exactly one source must be configured:
     * - local_csv_user_sync/csvpath
     * - local_csv_user_sync/csvstoredfile
     *
     * @param \stdClass $config plugin config
     * @param logger $logger logger instance
     * @return array{filepath:string,temppath:?string}|null
     */
    private function resolve_csv_source(\stdClass $config, logger $logger): ?array {
        $csvpath = trim((string)($config->csvpath ?? ''));
        $storedfilepath = trim((string)($config->csvstoredfile ?? ''));

        if ($csvpath !== '' && $storedfilepath !== '') {
            $logger->error(get_string('error:csvsourceconflict', 'local_csv_user_sync'));
            return null;
        }

        if ($csvpath === '' && $storedfilepath === '') {
            $logger->error(get_string('error:csvsourcemissing', 'local_csv_user_sync'));
            return null;
        }

        if ($csvpath !== '') {
            return [
                'filepath' => $csvpath,
                'temppath' => null,
            ];
        }

        $storedfile = $this->get_stored_csv_file($storedfilepath);
        if ($storedfile === null) {
            $logger->error(get_string('error:csvuploadnotfound', 'local_csv_user_sync'));
            return null;
        }

        $tempfilepath = $storedfile->copy_content_to_temp('local_csv_user_sync', 'csvsync_');
        if ($tempfilepath === false || !is_file($tempfilepath) || !is_readable($tempfilepath)) {
            $logger->error(get_string('error:csvpathnotreadable', 'local_csv_user_sync', $storedfilepath));
            return null;
        }

        return [
            'filepath' => $tempfilepath,
            'temppath' => $tempfilepath,
        ];
    }

    /**
     * Returns the uploaded CSV stored file from plugin file area.
     *
     * @param string $storedfilepath value saved by admin_setting_configstoredfile
     * @return \stored_file|null
     */
    private function get_stored_csv_file(string $storedfilepath): ?\stored_file {
        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'local_csv_user_sync',
            self::CSV_FILEAREA,
            self::CSV_ITEMID,
            'id DESC',
            false
        );
        if (empty($files)) {
            return null;
        }

        if ($storedfilepath === '') {
            return reset($files) ?: null;
        }

        foreach ($files as $file) {
            $filepath = $file->get_filepath() . $file->get_filename();
            if ($filepath === $storedfilepath) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Release lock safely.
     *
     * @param lock $lock
     * @return void
     */
    private function release_lock(lock $lock): void {
        try {
            $lock->release();
        } catch (\Throwable $e) {
            mtrace('[local_csv_user_sync] ' . get_string('task:releaselockfailed', 'local_csv_user_sync', $e->getMessage()));
        }
    }

    /**
     * Validates essential CSV headers before starting sync.
     *
     * @param array<int,string> $headers
     * @return array<int,string>
     */
    private function validate_headers(array $headers): array {
        $errors = [];
        $required = ['username', 'firstname', 'lastname', 'email'];
        $missingrequired = array_values(array_diff($required, $headers));
        if (!empty($missingrequired)) {
            $errors[] = get_string(
                'error:missingrequiredheaders',
                'local_csv_user_sync',
                implode(', ', $missingrequired)
            );
        }

        $hascourse = in_array('course_shortname', $headers, true);
        $hasrole = in_array('role_shortname', $headers, true);
        if ($hascourse xor $hasrole) {
            $errors[] = get_string('error:enrolheaderspair', 'local_csv_user_sync');
        }

        return $errors;
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
