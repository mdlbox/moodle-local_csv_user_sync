# Changelog

All notable changes to `local_csv_user_sync`.

## 0.3.5 (2026110400)

### Changed

- Without `Detailed logging`, a run only logs its start, the totals, the outcome and the duration: for example
  "Synchronization finished successfully in 0.58 s." or "Synchronization finished with errors in 0.58 s.
  Errors: 2. Warnings: 1.". Per-source, per-row, warning and error lines are only written with detailed logging;
  previously only debug lines were hidden.

## 0.3.4 (2026110301)

### Fixed

- The warning about untrusted readers of a source folder listed almost every account of the site, because
  authenticated users hold `mod/folder:view`. It now lists only users who can actually read the files: active
  enrolments or `moodle/course:view`, `moodle/course:viewhiddencourses` when the source course is hidden,
  `moodle/course:viewhiddenactivities` when the folder is hidden, and availability restrictions. Suspended
  accounts are excluded.
- A new warning is logged when the course and the folder are visible and the course allows guest access or self
  enrolment, since then any user can read the CSV files.

## 0.3.3 (2026110300)

### Fixed

- Log messages containing accented characters broke on servers whose
  locale is not UTF-8 the whitespace cleanup removed one byte
  of the character, the plugin log line was not saved and the scheduled task was marked as failed because
  Moodle could not store its output. Messages are now handled as UTF-8. CSV headers are normalized the same way.

## 0.3.2 (2026110200)

### Security

- Open sessions of an account are closed when a CSV row changes its email or authentication method.
- CSV enrolments never target the site front page or the course holding the source folders (enrolling someone
  there could give them rights on the CSV files).
- A warning lists the untrusted users who can read the CSV files of a source folder: users with
  `mod/folder:view`, plus `moodle/course:viewhiddenactivities` when the folder is hidden (for example non-editing
  teachers).

### Fixed

- Disabled manual enrolment instances are no longer used: enrolments in them gave no access and no error was
  reported. A course with only disabled manual instances now reports an error, and no extra instance is created.
  Removing an enrolment (`deleted = 1`) from a disabled instance still works.
- An error while preparing the manual enrolment instance now only fails that row; previously it made the whole
  run incomplete and blocked the requested account deletions.

## 0.3.1 (2026103001)

### Added

- Each account access email sent to a new user is recorded in the Moodle logs with the new event
  `\local_csv_user_sync\event\account_email_sent` ("Account access email sent", visible in Reports → Logs),
  and always in the plugin log (previously only with detailed logging). The event never stores the email content.

### Security

- When the account access email fails, core records the whole message, including the one-time password setup
  link, in its `email_failed` event: the plugin now invalidates that link immediately. The user can still request
  a new one with "Forgotten password".

### Changed

- No access email is attempted for `nologin` accounts or when `$CFG->noemailever` is set (a warning is logged
  instead of reporting an email that was never sent).

## 0.3.0 (2026103000)

### Added

- **Account suspension** with the `user_suspend` column: `1` suspends the account and logs the user out, `0`
  reactivates it, empty keeps it as is. Disabled by default (`Allow account suspension`). A new account created
  suspended receives no access email.
- **Account deletion** with the `user_delete` column, through the core `delete_user()` API. Disabled by default
  (`Allow account deletion`).
  - Only an existing account whose username and email both match the row exactly is deleted, in every matching
    mode; suffixed accounts are never considered.
  - Protected accounts are never deleted: site administrators (whatever their authentication method), guest,
    and accounts holding `moodle/user:update` or `local/csv_user_sync:uploadsource` in system context.
  - The CSV author must hold `moodle/user:delete` in system context; for Folder sources, every user able to manage
    the folder files must hold it too. A file path source can never request deletions.
  - Deletion rows are queued and executed at the end of the run. Later rows for the same account in the run are
    skipped.
  - `Maximum deletions per run` (default 10): above it no account is deleted and the site administrators are
    warned by email.
  - No deletion is executed when a source or a row stopped with an unexpected error.
  - Right before deleting, the account must still be unprotected and keep the same username and email.
  - Summary email of deleted accounts after each run; dry-run only logs.
- Both columns are added to the downloadable CSV template only when enabled.
- Enrolment dates also accept the `DD-MM-YYYY` format (for example `20-10-2026`).

### Changed

- The `Email conflict notification address` setting is now the `Notification address` and also receives the
  account deletion emails, which are always sent to the site administrators as well.
- Privacy metadata updated for deletion notification emails.

### Upgrade notes

- No database change. The new settings are disabled: behaviour is unchanged until an administrator enables them.

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
