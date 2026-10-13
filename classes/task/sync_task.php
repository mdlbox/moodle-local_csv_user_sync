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
use local_csv_user_sync\local\conflict_notifier;
use local_csv_user_sync\local\csv_reader;
use local_csv_user_sync\local\enrol_sync;
use local_csv_user_sync\local\folder_source;
use local_csv_user_sync\local\logger;
use local_csv_user_sync\local\user_sync;

/**
 * Scheduled task that runs CSV user synchronization.
 *
 * Sources are processed sequentially: first the legacy source (file path or uploaded file), then every
 * configured Folder activity in course page order. A failing source does not stop the following ones.
 */
class sync_task extends \core\task\scheduled_task {
    /** @var string Moodle file area used for uploaded CSV source file. */
    private const CSV_FILEAREA = 'csvsource';

    /** @var int Moodle file itemid used for uploaded CSV source file. */
    private const CSV_ITEMID = 0;

    /** @var string[] Counter names. */
    private const COUNTERS = ['rows', 'userscreated', 'usersupdated', 'enrolmentscreated', 'enrolmentsupdated', 'errors'];

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
        $failedsources = [];

        try {
            $logger->info(get_string('task:start', 'local_csv_user_sync', $dryrun ? get_string('yes') : get_string('no')));

            $sources = $this->collect_sources($config, $logger);
            if (empty($sources)) {
                return;
            }

            $defaultauth = trim((string)($config->defaultauth ?? 'manual'));
            $usersync = new user_sync($logger, [
                'dryrun' => $dryrun,
                'updateonlychanged' => !isset($config->updateonlychanged) || !empty($config->updateonlychanged),
                'defaultauth' => $defaultauth !== '' ? $defaultauth : 'manual',
                'sendemail' => !empty($config->sendemail),
                'emailtemplate' => (string)($config->emailtemplate ?? ''),
                'matchmode' => (string)($config->matchmode ?? user_sync::MATCH_USERNAME_EMAIL),
                'emptyvalues' => (string)($config->emptyvalues ?? user_sync::EMPTY_IGNORE),
            ]);
            $enrolsync = new enrol_sync($logger, [
                'dryrun' => $dryrun,
                'allowedroles' => $config->allowedroles ?? null,
            ]);
            $notifier = new conflict_notifier($logger, (string)($config->conflictemail ?? ''), $dryrun);

            $totals = $this->new_counters();
            $total = count($sources);
            foreach (array_values($sources) as $index => $source) {
                $logger->info(get_string('task:sourcestart', 'local_csv_user_sync', (object)[
                    'index' => $index + 1,
                    'total' => $total,
                    'source' => $source['label'],
                ]));

                $starttime = microtime(true);
                $counters = $this->new_counters();
                try {
                    $this->process_source($source, $config, $logger, $usersync, $enrolsync, $notifier, $counters);
                } catch (\Throwable $e) {
                    $counters->errors++;
                    $failedsources[] = $source['label'];
                    $logger->error(get_string('task:sourcefatal', 'local_csv_user_sync', (object)[
                        'source' => $source['label'],
                        'message' => $this->readable_exception_message($e),
                    ]));
                }

                foreach (self::COUNTERS as $name) {
                    $totals->{$name} += $counters->{$name};
                }
                $counters->source = $source['label'];
                $counters->duration = format_float(microtime(true) - $starttime, 2);
                $logger->info(get_string('task:sourcesummary', 'local_csv_user_sync', $counters));
            }

            $totals->sources = $total;
            $logger->info(get_string('task:summary', 'local_csv_user_sync', $totals));
            $notifier->send();
        } catch (\Throwable $e) {
            $logger->error(get_string('task:fatal', 'local_csv_user_sync', $this->readable_exception_message($e)));
            throw $e;
        } finally {
            $this->release_lock($lock);
        }

        if (!empty($failedsources)) {
            // Report the failure to the task manager once every source has been attempted.
            throw new \moodle_exception('task:sourcesfailed', 'local_csv_user_sync', '', implode(', ', $failedsources));
        }
    }

    /**
     * Collect configured CSV sources, in processing order.
     *
     * The legacy source accepts exactly one of local_csv_user_sync/csvpath and local_csv_user_sync/csvstoredfile.
     * Folder sources are added after it, in course page order.
     *
     * @param \stdClass $config plugin config
     * @param logger $logger logger instance
     * @return array<int,array{type:string,label:string,path?:string,file?:\stored_file,cm?:\cm_info}>
     */
    private function collect_sources(\stdClass $config, logger $logger): array {
        $csvpath = trim((string)($config->csvpath ?? ''));
        $storedfilepath = trim((string)($config->csvstoredfile ?? ''));

        if ($csvpath !== '' && $storedfilepath !== '') {
            $logger->error(get_string('error:csvsourceconflict', 'local_csv_user_sync'));
            return [];
        }

        $sources = [];
        if ($csvpath !== '') {
            $sources[] = [
                'type' => 'path',
                'label' => get_string('task:sourcepath', 'local_csv_user_sync', basename($csvpath)),
                'path' => $csvpath,
            ];
        } else if ($storedfilepath !== '') {
            $storedfile = $this->get_stored_csv_file($storedfilepath);
            if ($storedfile === null) {
                $logger->error(get_string('error:csvuploadnotfound', 'local_csv_user_sync'));
            } else {
                $sources[] = [
                    'type' => 'upload',
                    'label' => get_string('task:sourceupload', 'local_csv_user_sync', $storedfile->get_filename()),
                    'file' => $storedfile,
                ];
            }
        }

        foreach (folder_source::resolve_folders($config, $logger) as $cm) {
            $sources[] = [
                'type' => 'folder',
                'label' => get_string('task:sourcefolder', 'local_csv_user_sync', (object)[
                    'name' => format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]),
                    'cmid' => (int)$cm->id,
                ]),
                'cm' => $cm,
            ];
        }

        if (empty($sources) && $csvpath === '' && $storedfilepath === '' && !folder_source::is_configured($config)) {
            $logger->error(get_string('error:csvsourcemissing', 'local_csv_user_sync'));
        }

        return $sources;
    }

    /**
     * Process one CSV source with the shared synchronizers.
     *
     * @param array $source source descriptor from collect_sources()
     * @param \stdClass $config plugin config
     * @param logger $logger
     * @param user_sync $usersync
     * @param enrol_sync $enrolsync
     * @param conflict_notifier $notifier
     * @param \stdClass $counters counters for this source, updated in place
     * @return void
     */
    private function process_source(
        array $source,
        \stdClass $config,
        logger $logger,
        user_sync $usersync,
        enrol_sync $enrolsync,
        conflict_notifier $notifier,
        \stdClass $counters
    ): void {
        $dryrun = !empty($config->dryrun);
        $tempcsvpath = null;

        try {
            if ($source['type'] === 'path') {
                $csvpath = $source['path'];
            } else {
                $storedfile = $source['file'] ?? null;
                if ($source['type'] === 'folder') {
                    if (folder_source::is_exposed($source['cm'])) {
                        $logger->warning(get_string('warning:folderexposed', 'local_csv_user_sync', $source['label']));
                    }
                    $rejected = false;
                    $storedfile = folder_source::get_csv_file($source['cm'], $logger, $rejected);
                    if ($storedfile === null) {
                        if ($rejected) {
                            $counters->errors++;
                        }
                        return;
                    }
                    $logger->info(get_string('task:sourcefile', 'local_csv_user_sync', $storedfile->get_filename()));
                }

                $tempcsvpath = $storedfile->copy_content_to_temp('local_csv_user_sync', 'csvsync_');
                if ($tempcsvpath === false || !is_file($tempcsvpath) || !is_readable($tempcsvpath)) {
                    $tempcsvpath = null;
                    $counters->errors++;
                    $logger->error(get_string('error:csvpathnotreadable', 'local_csv_user_sync', $storedfile->get_filename()));
                    return;
                }
                $csvpath = $tempcsvpath;
            }

            $reader = new csv_reader($csvpath, (string)($config->delimiter ?? ';'), (string)($config->encoding ?? 'UTF-8'), false);
            $validationerrors = $reader->validate_file();
            foreach ($validationerrors as $error) {
                $logger->error($error);
            }
            if (!empty($validationerrors)) {
                $counters->errors++;
                return;
            }
            $headers = $reader->get_headers();
            $headererrors = $this->validate_headers($headers);
            foreach ($headererrors as $error) {
                $logger->error($error);
            }
            if (!empty($headererrors)) {
                $counters->errors++;
                return;
            }

            foreach ($reader->get_rows() as $entry) {
                $counters->rows++;
                $rownum = (int)$entry['rownum'];
                $row = $entry['data'];

                try {
                    $userresult = $usersync->sync($row, $rownum);
                    if (!empty($userresult['conflict'])) {
                        $notifier->add(
                            $source['label'],
                            $rownum,
                            $userresult['conflict']['username'],
                            $userresult['conflict']['email'],
                            $userresult['conflict']['existing']
                        );
                    }
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
        } finally {
            if (!empty($tempcsvpath) && is_file($tempcsvpath)) {
                @unlink($tempcsvpath);
            }
        }
    }

    /**
     * Returns a new set of zeroed counters.
     *
     * @return \stdClass
     */
    private function new_counters(): \stdClass {
        return (object)array_fill_keys(self::COUNTERS, 0);
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
