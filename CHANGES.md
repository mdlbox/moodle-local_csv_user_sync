# Changelog

All notable changes to `local_csv_user_sync`.

## 0.2.0 (2026101300)

### Added

- **Folder activity sources.** CSV files can be read from Folder activities (`mod_folder`) of a dedicated course.
  - New settings: `Source course ID` and `Folders to synchronize`.
  - Files are read through the Moodle File API (`mod_folder` / `content` / itemid `0`), never through HTTP URLs
    or `moodledata` paths. The course module id of each folder is stored, not a context id.
  - Folders are processed in sequence, in course page order, after the existing path/upload source. All sources
    share the same run.
  - One CSV per folder. If a folder contains several, the most recently modified file is used and the others
    are listed in a warning. A folder without CSV, or a selected folder that no longer exists, is logged and
    does not stop the other sources.
  - An unexpected error in one source is logged, the next sources are still processed, and the task is reported
    as failed at the end.
- **Account matching on username + email** (`Account matching` setting).
  - A row updates an account only when both username and email match. Emails are compared case-insensitively,
    ignoring surrounding spaces.
  - When the username belongs to an account with a different email, the row goes to `username1`, `username2`…
    An existing suffixed account with the same email is updated; otherwise the first free suffix is created.
    Suffixes freed by deleted accounts are reused.
  - Repeated rows, in the same file or in different files of a run, resolve to the same account, also in dry-run.
  - `Username only` keeps the previous behaviour.
- **Empty CSV values** setting: ignore and keep the existing value (default), or clear it. The language is never
  cleared. On update, the auth method changes only when the `auth` column is present.
- **Email conflict notification.** When `allowaccountssameemail` is disabled, rows whose email is already used by
  another account are skipped. A single summary email per run is sent to `Email conflict notification address`,
  or to all site administrators when empty. Nothing is sent in dry-run mode.
- **Roles allowed in CSV enrolments** setting (default: student, non-editing teacher, teacher).
- Capability `local/csv_user_sync:uploadsource` (system context, Manager archetype).
- Per-source log summary: rows, users created/updated, enrolments created/updated, errors, duration.
- `warning` log level.
- PHPUnit tests for account matching and suffixes, custom profile fields, empty values, email conflicts,
  protected accounts, Folder sources, the CSV reader and the task end to end.

### Fixed

- **Custom profile fields were not saved** when the field shortname contained uppercase letters (for example
  `CodiceScuola`), on both creation and update: CSV headers are lowercased, so the field was never found and was
  skipped without any message. Shortnames are now matched case-insensitively. Fields whose shortnames differ only
  by case are ignored, with a warning.
- Custom profile fields are now saved through the core profile API (`profile_save_data()`, as the core user upload
  tool does): menu option labels are converted, text length limits and unique fields are respected, and checkbox
  and date values are validated. An invalid value keeps the stored value and logs a warning.
- `user_created` / `user_updated` events are now triggered after custom fields are saved, so observers see the
  complete profile.
- Values starting with `+`, `-`, `=` or `@` are no longer prefixed with an apostrophe on import. The prefix
  corrupted data such as phone numbers (`+41 …` was stored as `'+41 …`). Values already stored with the
  apostrophe are corrected the next time the CSV provides them.
- Optional user fields are validated: `lang` must be an installed language, `country` a valid country code, and
  values longer than the database column are rejected instead of failing the insert.

### Security

- Folder CSV files are processed only when their author holds `local/csv_user_sync:uploadsource` in system
  context. Course-level overrides cannot grant it, so a teacher of the source course cannot inject accounts or
  enrolments.
- A folder is refused when any user able to manage its files (`mod/folder:managefiles`) lacks that capability.
  Otherwise such a user could rename, move or replace files and choose which CSV is processed.
- A warning is logged when a source folder is visible to participants.
- Site administrators, the guest user, deleted accounts and accounts holding `moodle/user:update` or
  `local/csv_user_sync:uploadsource` in system context are never modified by a CSV row.
- Enrolments can only assign roles that are both allowed in the settings and assignable in course context.
- Privacy API: conflict notification emails are declared as an external location.

### Changed

- Settings page reorganised: new `Source folders` and `CSV format` sections.
- The source-missing configuration error now also mentions source folders.

### Upgrade notes

- No database change. Visit `Site administration → Notifications` to install the new capability and review the
  new settings.
- **Upgraded installations keep `Username only` matching** (set by the upgrade step, to avoid creating suffixed
  duplicates of existing users). Select `Username and email` to enable the new logic. New installations use it by
  default. After switching, check that no unwanted `username1` accounts were created by rows whose email differs
  from the one stored in Moodle.
- Empty CSV values are now ignored by default. Previously an empty optional column overwrote the stored value.
- CSV enrolments are now limited to the roles selected in `Roles allowed in CSV enrolments`. Previously any role
  could be assigned.
- Grant `local/csv_user_sync:uploadsource` to the users who upload CSV files to the source folders, remove file
  management rights in the source course from everyone else, and hide the source folders from participants.

## 0.1.0 (2026022600)

- Initial release.
