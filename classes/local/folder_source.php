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
 * Folder activity CSV sources for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use cm_info;
use context_system;
use stored_file;

/**
 * Resolves CSV files stored in Folder activities (mod_folder) of the configured source course.
 *
 * Files are always read through the Moodle File API (component mod_folder, filearea content, itemid 0),
 * never through HTTP URLs or physical paths in moodledata.
 */
class folder_source {
    /** @var string File component used by Folder activities. */
    public const COMPONENT = 'mod_folder';

    /** @var string File area used by Folder activities. */
    public const FILEAREA = 'content';

    /** @var int Item id used by Folder activities. */
    public const ITEMID = 0;

    /** @var string Capability the author of a folder CSV file must hold in system context. */
    public const UPLOADCAPABILITY = 'local/csv_user_sync:uploadsource';

    /**
     * Returns the configured source course, or null when not configured or not found.
     *
     * @param \stdClass $config plugin config
     * @return \stdClass|null
     */
    public static function get_source_course(\stdClass $config): ?\stdClass {
        global $DB;

        $courseid = (int)clean_param((string)($config->sourcecourseid ?? ''), PARAM_INT);
        if ($courseid <= 0 || $courseid === (int)SITEID) {
            return null;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        return $course ?: null;
    }

    /**
     * Returns configured folder course module ids, without duplicates.
     *
     * @param \stdClass $config plugin config
     * @return int[]
     */
    public static function get_configured_cmids(\stdClass $config): array {
        $cmids = [];
        foreach (explode(',', (string)($config->sourcefolders ?? '')) as $value) {
            $cmid = (int)clean_param(trim($value), PARAM_INT);
            if ($cmid > 0) {
                $cmids[$cmid] = $cmid;
            }
        }

        return array_values($cmids);
    }

    /**
     * Returns true when at least one folder source is configured.
     *
     * @param \stdClass $config plugin config
     * @return bool
     */
    public static function is_configured(\stdClass $config): bool {
        return !empty(self::get_configured_cmids($config));
    }

    /**
     * Choices for the folder multiselect setting: Folder activities of the configured source course.
     *
     * @return array<int,string> course module id => label
     */
    public static function get_folder_choices(): array {
        $course = self::get_source_course(get_config('local_csv_user_sync'));
        if ($course === null) {
            return [];
        }

        $choices = [];
        foreach (self::get_course_folders($course) as $cm) {
            $choices[(int)$cm->id] = get_string('settings:sourcefolders_option', 'local_csv_user_sync', (object)[
                'name' => format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]),
                'cmid' => (int)$cm->id,
            ]);
        }

        return $choices;
    }

    /**
     * Resolves the configured folders, in the order they appear on the course page.
     *
     * Configured ids that are no longer valid Folder activities of the source course are logged as errors.
     *
     * @param \stdClass $config plugin config
     * @param logger $logger
     * @return cm_info[]
     */
    public static function resolve_folders(\stdClass $config, logger $logger): array {
        $cmids = self::get_configured_cmids($config);
        if (empty($cmids)) {
            return [];
        }

        $course = self::get_source_course($config);
        if ($course === null) {
            $logger->error(get_string('error:sourcecoursemissing', 'local_csv_user_sync'));
            return [];
        }

        $folders = [];
        $wanted = array_flip($cmids);
        foreach (self::get_course_folders($course) as $cm) {
            if (isset($wanted[(int)$cm->id])) {
                $folders[] = $cm;
                unset($wanted[(int)$cm->id]);
            }
        }

        foreach (array_keys($wanted) as $missingcmid) {
            $logger->error(get_string('error:foldermissing', 'local_csv_user_sync', $missingcmid));
        }

        return $folders;
    }

    /**
     * Returns the CSV file to process for one folder.
     *
     * Rule: each folder is expected to contain exactly one CSV file. If more than one CSV is present,
     * the most recently modified one is used and the others are reported as ignored.
     * Returns null (after logging) when no usable file exists or when the file author is not allowed.
     *
     * @param cm_info $cm Folder course module
     * @param logger $logger
     * @param bool|null $rejected set to true when the folder was refused for security reasons
     * @return stored_file|null
     */
    public static function get_csv_file(cm_info $cm, logger $logger, ?bool &$rejected = null): ?stored_file {
        $rejected = false;
        $foldername = format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]);

        // Anyone able to manage the folder files decides which CSV is processed (upload, rename, move, delete),
        // so every such user must be trusted like the file author.
        $untrusted = self::get_untrusted_managers($cm);
        if (!empty($untrusted)) {
            $rejected = true;
            $logger->error(get_string('error:folderuntrustedmanagers', 'local_csv_user_sync', (object)[
                'folder' => $foldername,
                'users' => implode(', ', $untrusted),
            ]));
            return null;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($cm->context->id, self::COMPONENT, self::FILEAREA, self::ITEMID, 'filepath, filename', false);

        $csvfiles = [];
        foreach ($files as $file) {
            if (self::is_csv_file($file)) {
                $csvfiles[] = $file;
            }
        }

        if (empty($csvfiles)) {
            $logger->warning(get_string('warning:foldernocsv', 'local_csv_user_sync', $foldername));
            return null;
        }

        // Most recent first. The file id breaks ties deterministically.
        usort($csvfiles, static function (stored_file $a, stored_file $b): int {
            return [$b->get_timemodified(), $b->get_id()] <=> [$a->get_timemodified(), $a->get_id()];
        });
        $selected = array_shift($csvfiles);

        if (!empty($csvfiles)) {
            $ignored = array_map(static fn(stored_file $file): string => $file->get_filepath() . $file->get_filename(), $csvfiles);
            $logger->warning(get_string('warning:foldermultiplecsv', 'local_csv_user_sync', (object)[
                'folder' => $foldername,
                'selected' => $selected->get_filepath() . $selected->get_filename(),
                'ignored' => implode(', ', $ignored),
            ]));
        }

        if (!self::is_trusted_author($selected)) {
            $rejected = true;
            $logger->error(get_string('error:folderuntrustedauthor', 'local_csv_user_sync', (object)[
                'folder' => $foldername,
                'file' => $selected->get_filename(),
            ]));
            return null;
        }

        return $selected;
    }

    /**
     * Returns true when the Folder activity is visible to participants on the course page.
     *
     * CSV files contain personal data: source folders should be hidden from participants.
     *
     * @param cm_info $cm
     * @return bool
     */
    public static function is_exposed(cm_info $cm): bool {
        return !empty($cm->visible);
    }

    /**
     * Returns usernames of users who can manage the folder files without holding the upload capability
     * in system context (site administrators are always trusted).
     *
     * @param cm_info $cm
     * @return string[]
     */
    public static function get_untrusted_managers(cm_info $cm): array {
        $systemcontext = context_system::instance();
        $managers = get_users_by_capability($cm->context, 'mod/folder:managefiles', 'u.id, u.username');

        $untrusted = [];
        foreach ($managers as $manager) {
            if (!is_siteadmin((int)$manager->id) && !has_capability(self::UPLOADCAPABILITY, $systemcontext, (int)$manager->id)) {
                $untrusted[] = (string)$manager->username;
            }
        }
        sort($untrusted);

        return $untrusted;
    }

    /**
     * Returns true when the file author holds the upload capability in system context.
     *
     * The check is done in system context on purpose: course-level role overrides cannot grant it.
     *
     * @param stored_file $file
     * @return bool
     */
    public static function is_trusted_author(stored_file $file): bool {
        $authorid = (int)$file->get_userid();
        if ($authorid <= 0) {
            return false;
        }

        $author = \core_user::get_user($authorid, 'id, deleted, suspended');
        if (!$author || !empty($author->deleted) || !empty($author->suspended)) {
            return false;
        }

        return has_capability(self::UPLOADCAPABILITY, context_system::instance(), $authorid);
    }

    /**
     * Returns Folder activities of one course in course page order, excluding those pending deletion.
     *
     * @param \stdClass $course
     * @return cm_info[]
     */
    private static function get_course_folders(\stdClass $course): array {
        $folders = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->modname === 'folder' && empty($cm->deletioninprogress)) {
                $folders[] = $cm;
            }
        }

        return $folders;
    }

    /**
     * Returns true when the stored file has a .csv extension.
     *
     * @param stored_file $file
     * @return bool
     */
    private static function is_csv_file(stored_file $file): bool {
        return \core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'csv';
    }
}
