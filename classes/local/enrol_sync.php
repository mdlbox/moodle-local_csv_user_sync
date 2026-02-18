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
 * Enrolment synchronizer for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use context_course;
use core_text;
use stdClass;

/**
 * Creates and updates manual course enrolments from CSV rows.
 */
class enrol_sync {
    /** @var logger */
    private readonly logger $logger;

    /** @var array<string,mixed> */
    private readonly array $config;

    /** @var array<string,stdClass|null> */
    private array $coursecache = [];

    /** @var array<string,stdClass|null> */
    private array $rolecache = [];

    /** @var array<int,stdClass|null> */
    private array $instancecache = [];

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
        require_once($CFG->libdir . '/enrollib.php');
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Returns true when row has at least one course shortname value.
     *
     * @param array<string,string> $row
     * @return bool
     */
    public function row_has_enrolment(array $row): bool {
        return $this->value_from_row($row, ['course_shortname']) !== '';
    }

    /**
     * Synchronize one enrolment row for one user.
     *
     * @param int $userid
     * @param array<string,string> $row
     * @param int $rownum
     * @return array{status:string}
     */
    public function sync(int $userid, array $row, int $rownum): array {
        global $DB;

        $courseshortname = $this->value_from_row($row, ['course_shortname']);
        if ($courseshortname === '') {
            return ['status' => 'skipped'];
        }

        $deleted = $this->parse_binary_flag($this->value_from_row($row, ['deleted']));
        if ($deleted === null) {
            $this->logger->error(
                get_string('error:invalidflag', 'local_csv_user_sync', (object)[
                    'field' => 'deleted',
                    'value' => $this->value_from_row($row, ['deleted']),
                ]),
                $rownum,
                $userid
            );
            return ['status' => 'error'];
        }

        $suspended = $this->parse_binary_flag($this->value_from_row($row, ['suspended']));
        if ($suspended === null) {
            $this->logger->error(
                get_string('error:invalidflag', 'local_csv_user_sync', (object)[
                    'field' => 'suspended',
                    'value' => $this->value_from_row($row, ['suspended']),
                ]),
                $rownum,
                $userid
            );
            return ['status' => 'error'];
        }

        $course = $this->get_course($courseshortname);
        if (!$course) {
            $this->logger->error(get_string('error:unknowncourse', 'local_csv_user_sync', $courseshortname), $rownum, $userid);
            return ['status' => 'error'];
        }

        $instance = $this->get_or_create_manual_instance((int)$course->id, (string)$course->shortname);
        if (!$instance) {
            $this->logger->error(
                get_string('error:manualenrolmissing', 'local_csv_user_sync', $course->shortname),
                $rownum,
                $userid
            );
            return ['status' => 'error'];
        }

        $existingue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$existingue) {
            $existingmanual = $this->find_existing_manual_enrolment((int)$course->id, $userid);
            if ($existingmanual !== null) {
                $instance = $existingmanual['instance'];
                $existingue = $existingmanual['userenrolment'];
            }
        }

        $context = context_course::instance((int)$course->id);
        $suspensiononly = $suspended && !$deleted && !empty($existingue);
        $rolechanged = false;
        $roleshortname = $this->value_from_row($row, ['role_shortname']);
        $role = null;
        if (!$deleted && !$existingue && $roleshortname === '') {
            $this->logger->error(get_string('error:enrolmissingrole', 'local_csv_user_sync'), $rownum, $userid);
            return ['status' => 'error'];
        }

        if (!$deleted && $roleshortname !== '') {
            $role = $this->get_role($roleshortname);
            if (!$role) {
                $this->logger->error(get_string('error:unknownrole', 'local_csv_user_sync', $roleshortname), $rownum, $userid);
                return ['status' => 'error'];
            }
        }

        if (!$deleted && !$existingue && !$role) {
            $this->logger->error(get_string('error:enrolmissingrole', 'local_csv_user_sync'), $rownum, $userid);
            return ['status' => 'error'];
        }

        $timestart = 0;
        $timeend = 0;
        if ($suspensiononly) {
            // For suspension actions, preserve current enrolment dates.
            $timestart = (int)$existingue->timestart;
            $timeend = (int)$existingue->timeend;
        } else if (!$deleted) {
            $startdatevalue = $this->value_from_row($row, ['enrol_start_date', 'start_date']);
            $enddatevalue = $this->value_from_row($row, ['enrol_end_date', 'end_date']);
            if (
                $existingue
                && trim($startdatevalue) === ''
                && trim($enddatevalue) === ''
            ) {
                // Keep existing dates when not provided for an already enrolled user.
                $timestart = (int)$existingue->timestart;
                $timeend = (int)$existingue->timeend;
            } else {
                $timestart = $this->parse_date($startdatevalue);
                if ($timestart === null) {
                    $this->logger->error(
                        get_string('error:invaliddate', 'local_csv_user_sync', (object)[
                            'field' => 'enrol_start_date',
                            'value' => $startdatevalue,
                        ]),
                        $rownum,
                        $userid
                    );
                    return ['status' => 'error'];
                }

                $timeend = $this->parse_date($enddatevalue);
                if ($timeend === null) {
                    $this->logger->error(
                        get_string('error:invaliddate', 'local_csv_user_sync', (object)[
                            'field' => 'enrol_end_date',
                            'value' => $enddatevalue,
                        ]),
                        $rownum,
                        $userid
                    );
                    return ['status' => 'error'];
                }
            }

            if ($timeend > 0 && $timestart > 0 && $timeend < $timestart) {
                $this->logger->error(get_string('error:invaliddateorder', 'local_csv_user_sync'), $rownum, $userid);
                return ['status' => 'error'];
            }
        }

        if ($role) {
            $rolechanged = !$this->has_exact_manual_role($context, $userid, (int)$instance->id, (int)$role->id);
        }
        $targetstatus = $suspended ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
        $statuschangedtosuspended = $suspended && (!$existingue || (int)$existingue->status !== ENROL_USER_SUSPENDED);
        $enrolmentdatachanged = $existingue
            && (
                (int)$existingue->timestart !== $timestart
                || (int)$existingue->timeend !== $timeend
                || (int)$existingue->status !== $targetstatus
            );

        $status = 'unchanged';
        if ($deleted && $existingue) {
            $status = 'updated';
        } else if (!$deleted && !$existingue) {
            $status = 'created';
        } else if (!$deleted && ($enrolmentdatachanged || $rolechanged)) {
            $status = 'updated';
        }

        if (!empty($this->config['dryrun'])) {
            $this->log_dryrun_result(
                $status,
                $userid,
                (string)$course->shortname,
                $rownum,
                $deleted,
                $statuschangedtosuspended
            );
            return ['status' => $status];
        }

        try {
            $plugin = enrol_get_plugin('manual');
            if (!$plugin) {
                $this->logger->error(get_string('error:manualenrolmissingglobal', 'local_csv_user_sync'), $rownum, $userid);
                return ['status' => 'error'];
            }

            if ($deleted) {
                if (!$existingue) {
                    $params = (object)['userid' => $userid, 'course' => $course->shortname];
                    $this->logger->debug(
                        get_string('log:enroldeleteabsent', 'local_csv_user_sync', $params),
                        $rownum,
                        $userid
                    );
                    return ['status' => 'unchanged'];
                }

                $plugin->unenrol_user($instance, $userid);
                $params = (object)['userid' => $userid, 'course' => $course->shortname];
                $this->logger->info(
                    get_string('log:enroldeleted', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
                return ['status' => 'updated'];
            }

            if (!$existingue) {
                // Role assignment is handled explicitly below to avoid component ambiguity.
                $plugin->enrol_user($instance, $userid, null, $timestart, $timeend, $targetstatus);
            } else if ($enrolmentdatachanged) {
                // Do not call enrol_user() for unchanged enrolments: manual plugin would resend welcome email.
                $plugin->update_user_enrol($instance, $userid, $targetstatus, $timestart, $timeend);
            }

            $rolefixed = false;
            if ($role) {
                $rolefixed = $this->ensure_manual_role(
                    $context,
                    $userid,
                    (int)$instance->id,
                    (int)$role->id
                );
            }
            if ($status === 'unchanged' && $rolefixed) {
                $status = 'updated';
            }

            if ($status === 'created') {
                $params = (object)['userid' => $userid, 'course' => $course->shortname];
                $this->logger->info(
                    get_string('log:enrolcreated', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
            } else if ($status === 'updated') {
                $params = (object)['userid' => $userid, 'course' => $course->shortname];
                if ($statuschangedtosuspended) {
                    $this->logger->info(
                        get_string('log:enrolsuspended', 'local_csv_user_sync', $params),
                        $rownum,
                        $userid
                    );
                    return ['status' => $status];
                }

                $this->logger->info(
                    get_string('log:enrolupdated', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
            } else {
                $params = (object)['userid' => $userid, 'course' => $course->shortname];
                $this->logger->debug(
                    get_string('log:enrolunchanged', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
            }

            return ['status' => $status];
        } catch (\Throwable $e) {
            $message = logger::sanitize_exception_message($e);
            if ($message === '') {
                $message = get_string('task:unknownerror', 'local_csv_user_sync');
            }
            $this->logger->error(get_string('error:enrolfailed', 'local_csv_user_sync', $message), $rownum, $userid);
            return ['status' => 'error'];
        }
    }

    /**
     * Parse date field. Empty means no date (0).
     *
     * @param string $value
     * @return int|null null if invalid
     */
    private function parse_date(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            if (core_text::strlen($value) === 8) {
                $date = \DateTimeImmutable::createFromFormat('Ymd|', $value);
                if ($date instanceof \DateTimeImmutable) {
                    return $date->getTimestamp();
                }
                return null;
            }

            if (core_text::strlen($value) === 10) {
                return (int)$value;
            }

            if (core_text::strlen($value) === 13) {
                return (int)floor(((int)$value) / 1000);
            }

            return null;
        }

        $formats = ['Y-m-d|', 'd.m.Y|', 'd/m/Y|'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }

            $errors = \DateTimeImmutable::getLastErrors();
            if (
                $errors === false
                || (
                    (int)$errors['warning_count'] === 0
                    && (int)$errors['error_count'] === 0
                )
            ) {
                return $date->getTimestamp();
            }
        }

        return null;
    }

    /**
     * Parse 0/1 flag from CSV. Empty value means 0 (false).
     *
     * @param string $value
     * @return bool|null Null when the value is invalid.
     */
    private function parse_binary_flag(string $value): ?bool {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return false;
        }

        if ($value === '1') {
            return true;
        }

        return null;
    }

    /**
     * Read first non-empty value for key candidates.
     *
     * @param array<string,string> $row
     * @param string[] $keys
     * @return string
     */
    private function value_from_row(array $row, array $keys): string {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            return trim((string)$row[$key]);
        }
        return '';
    }

    /**
     * Load course by shortname with cache.
     *
     * @param string $shortname
     * @return stdClass|null
     */
    private function get_course(string $shortname): ?stdClass {
        global $DB;

        $cachekey = core_text::strtolower($shortname);
        if (array_key_exists($cachekey, $this->coursecache)) {
            return $this->coursecache[$cachekey];
        }

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
        $this->coursecache[$cachekey] = $course ?: null;
        return $this->coursecache[$cachekey];
    }

    /**
     * Load role by shortname with cache.
     *
     * @param string $shortname
     * @return stdClass|null
     */
    private function get_role(string $shortname): ?stdClass {
        global $DB;

        $cachekey = core_text::strtolower($shortname);
        if (array_key_exists($cachekey, $this->rolecache)) {
            return $this->rolecache[$cachekey];
        }

        $role = $DB->get_record('role', ['shortname' => $shortname], '*', IGNORE_MISSING);
        $this->rolecache[$cachekey] = $role ?: null;
        return $this->rolecache[$cachekey];
    }

    /**
     * Get or create a manual enrolment instance for a course.
     *
     * @param int $courseid
     * @param string $courseshortname
     * @return stdClass|null
     */
    private function get_or_create_manual_instance(int $courseid, string $courseshortname): ?stdClass {
        global $DB;

        if (array_key_exists($courseid, $this->instancecache)) {
            return $this->instancecache[$courseid];
        }

        foreach (enrol_get_instances($courseid, false) as $instance) {
            if ($instance->enrol === 'manual') {
                $this->instancecache[$courseid] = $instance;
                return $instance;
            }
        }

        if (!empty($this->config['dryrun'])) {
            $this->logger->info(
                get_string('log:manualinstancecreated_dryrun', 'local_csv_user_sync', $courseshortname)
            );
            $this->instancecache[$courseid] = (object)[
                'id' => 0,
                'courseid' => $courseid,
                'enrol' => 'manual',
            ];
            return $this->instancecache[$courseid];
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            $this->instancecache[$courseid] = null;
            return null;
        }

        $course = get_course($courseid);
        $instanceid = $plugin->add_default_instance($course);
        if (!$instanceid) {
            $this->instancecache[$courseid] = null;
            return null;
        }

        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $this->logger->info(get_string('log:manualinstancecreated', 'local_csv_user_sync', $courseshortname));
        $this->instancecache[$courseid] = $instance;
        return $instance;
    }

    /**
     * Finds an existing manual user enrolment across manual instances in one course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array{instance:stdClass,userenrolment:stdClass}|null
     */
    private function find_existing_manual_enrolment(int $courseid, int $userid): ?array {
        global $DB;

        foreach (enrol_get_instances($courseid, false) as $candidate) {
            if ($candidate->enrol !== 'manual') {
                continue;
            }

            $userenrolment = $DB->get_record(
                'user_enrolments',
                ['enrolid' => $candidate->id, 'userid' => $userid],
                '*',
                IGNORE_MISSING
            );
            if ($userenrolment) {
                return [
                    'instance' => $candidate,
                    'userenrolment' => $userenrolment,
                ];
            }
        }

        return null;
    }

    /**
     * Returns true if user has exactly one manual role and it matches target role.
     *
     * @param context_course $context
     * @param int $userid
     * @param int $instanceid
     * @param int $targetroleid
     * @return bool
     */
    private function has_exact_manual_role(context_course $context, int $userid, int $instanceid, int $targetroleid): bool {
        $manualroles = [];
        foreach (get_user_roles($context, $userid, false) as $roleassignment) {
            if ($roleassignment->component === 'enrol_manual' && (int)$roleassignment->itemid === $instanceid) {
                $manualroles[] = (int)$roleassignment->roleid;
            }
        }

        if (count($manualroles) !== 1) {
            return false;
        }

        return $manualroles[0] === $targetroleid;
    }

    /**
     * Replace current manual role assignment for one instance with the target role.
     *
     * @param context_course $context
     * @param int $userid
     * @param int $instanceid
     * @param int $roleid
     * @return void
     */
    private function set_manual_role(context_course $context, int $userid, int $instanceid, int $roleid): void {
        role_unassign_all([
            'userid' => $userid,
            'contextid' => $context->id,
            'component' => 'enrol_manual',
            'itemid' => $instanceid,
        ]);
        role_assign($roleid, $userid, $context->id, 'enrol_manual', $instanceid);
    }

    /**
     * Ensure user has expected manual role in the enrolment instance.
     *
     * @param context_course $context
     * @param int $userid
     * @param int $instanceid
     * @param int $roleid
     * @return bool True when role assignment had to be updated.
     */
    private function ensure_manual_role(context_course $context, int $userid, int $instanceid, int $roleid): bool {
        if ($this->has_exact_manual_role($context, $userid, $instanceid, $roleid)) {
            return false;
        }

        $this->set_manual_role($context, $userid, $instanceid, $roleid);
        return true;
    }

    /**
     * Emit dry-run enrolment result log.
     *
     * @param string $status
     * @param int $userid
     * @param string $courseshortname
     * @param int $rownum
     * @param bool $deleted
     * @param bool $suspended
     * @return void
     */
    private function log_dryrun_result(
        string $status,
        int $userid,
        string $courseshortname,
        int $rownum,
        bool $deleted = false,
        bool $suspended = false
    ): void {
        $params = (object)['userid' => $userid, 'course' => $courseshortname];
        if ($deleted) {
            if ($status === 'updated') {
                $this->logger->info(
                    get_string('log:enroldeleted_dryrun', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
                return;
            }

            $this->logger->debug(
                get_string('log:enroldeleteabsent', 'local_csv_user_sync', $params),
                $rownum,
                $userid
            );
            return;
        }

        if ($status === 'created') {
            $this->logger->info(
                get_string('log:enrolcreated_dryrun', 'local_csv_user_sync', $params),
                $rownum,
                $userid
            );
            return;
        }

        if ($status === 'updated') {
            if ($suspended) {
                $this->logger->info(
                    get_string('log:enrolsuspended_dryrun', 'local_csv_user_sync', $params),
                    $rownum,
                    $userid
                );
                return;
            }

            $this->logger->info(
                get_string('log:enrolupdated_dryrun', 'local_csv_user_sync', $params),
                $rownum,
                $userid
            );
            return;
        }

        $this->logger->debug(
            get_string('log:enrolunchanged', 'local_csv_user_sync', $params),
            $rownum,
            $userid
        );
    }
}
