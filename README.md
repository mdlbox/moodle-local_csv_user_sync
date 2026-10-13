# CSV User Sync (`local_csv_user_sync`)

`local_csv_user_sync` is a Moodle local plugin that synchronizes users and manual course enrolments from a CSV source.

It supports user creation and updates, enrolment creation/update/removal, dry-run mode, detailed logging, and a secure onboarding email flow for new users.

## Features

- Imports users from CSV into Moodle.
- Updates existing users (optionally only when values changed).
- Synchronizes manual enrolments by course shortname and role shortname.
- Supports enrolment suspension and deletion flags.
- Can auto-create missing manual enrolment instances for courses.
- Supports CSV source from:
  - absolute file path, or
  - uploaded file in plugin settings, and/or
  - one CSV per Folder activity (`mod_folder`) of a dedicated course, several folders processed in sequence.
- Matches accounts on the username + email pair, with numeric username suffixes for homonyms (configurable).
- Configurable handling of empty CSV values (ignore, default, or clear).
- Email conflict detection (`allowaccountssameemail`) with a summary email to administrators or a configured address.
- Supports configurable delimiter and encoding (`UTF-8`, `ISO-8859-1`, `Windows-1252`, `auto`).
- Validates CSV structure and required headers before processing.
- Supports Moodle custom profile fields from CSV.
- Provides downloadable CSV template (including custom profile fields).
- Includes dry-run mode (simulate actions without writing data).
- Uses task-level locking to prevent concurrent sync runs.
- Logs to both `mtrace` and plugin DB table (`local_csv_user_sync_log`).
- Includes GDPR privacy provider for exported/deleted user-related log data.

## Requirements

- Moodle `4.5` branch or newer (`$plugin->requires = 2024100700`).
- Plugin component: `local_csv_user_sync`.

## Installation

1. Copy the plugin into:
   - `local/csv_user_sync`
2. Visit:
   - `Site administration -> Notifications`
3. Complete database upgrade if prompted.

### Upgrading from 0.1.x

- Replace the plugin files and visit `Site administration -> Notifications`. There is no database change; the new
  capability `local/csv_user_sync:uploadsource` is installed and the new settings are shown for review.
- Account matching: new installations default to `Username and email`. **Upgraded installations keep
  `Username only`** (set by the upgrade step, to avoid creating suffixed duplicates of existing users): select
  `Username and email` in `Account matching` to enable the new logic.
- Empty CSV values are ignored by default (previously an empty optional column overwrote the stored value).
- CSV enrolments can only assign the roles selected in `Roles allowed in CSV enrolments` (default: student,
  non-editing teacher, teacher). Previously any role could be assigned.

## Running the tests

```bash
vendor/bin/phpunit --testsuite local_csv_user_sync_testsuite
```

Tests cover account matching and suffixes, custom profile fields (create and update), empty values, email
conflicts, protected accounts, Folder sources (single, none, multiple CSV, untrusted author, order, missing
folders) and the task end to end.

## Configuration

Path:

- `Site administration -> Plugins -> Local plugins -> CSV User Sync`

### 1) CSV Sources

Legacy source (at most one):

- `CSV file path` (absolute path), or
- `CSV file upload` (stored file area `csvsource`)

If both are set, the task stops with a configuration error.

Folder sources (optional, in addition to the legacy source):

- `Source course ID`: the course containing the source Folder activities. Save the settings after changing it.
- `Folders to synchronize`: the Folder activities of that course to read.

If no source at all is configured, the task stops with a configuration error.

#### Folder sources rules

- Files are read with the Moodle File API (context of the Folder activity, component `mod_folder`,
  filearea `content`, itemid `0`). HTTP URLs and physical paths in `moodledata/filedir` are never used.
  The settings store the course module id of each folder, not a context id copied from a `pluginfile.php` URL.
- Processing order: the legacy source first, then the selected folders in course page order.
  All sources share the same run: a user created by one file is found (and updated) by the next files.
- Each folder must contain **one** CSV file (extension `.csv`, subfolders included). If it contains more than one,
  the most recently modified file is used and the ignored files are listed in a warning.
- A folder with no CSV is skipped with a warning. A selected folder that was deleted or moved to another course
  is reported as an error. Neither stops the other sources.
- An unexpected error in one source is logged, the next sources are still processed, and the task is reported
  as failed at the end (so it appears in the scheduled task logs).

#### Folder sources security

CSV files contain personal data and control accounts and enrolments. Therefore:

- A CSV is processed only if the user who uploaded it holds `local/csv_user_sync:uploadsource` in **system**
  context (granted to the Manager archetype and to site administrators). The check is done in system context so
  that course-level role overrides cannot grant it: a teacher of the source course cannot inject accounts or
  enrolments.
- A folder is skipped (error) when any user able to manage its files (`mod/folder:managefiles`) lacks that
  capability: such a user could otherwise rename, move or replace files and choose which CSV is processed.
- CSV enrolments can only assign the roles selected in `Roles allowed in CSV enrolments`.
- Accounts holding `moodle/user:update` or `local/csv_user_sync:uploadsource` in system context are never
  modified by a CSV row (like site administrators), so a CSV cannot change their email or authentication.
- Hide the source folders from participants (a warning is logged when a selected folder is visible), and do not
  enrol students in the source course.

### 2) Account Matching

`Account matching` (`matchmode`):

- `Username and email` (default): a row updates an account only when **both** username and email match
  (email compared case-insensitively, surrounding spaces ignored). Resolution order for a row
  `username` + `email`:
  1. account `username` with the same email: update it;
  2. account `username<N>` with the same email: update it (lowest `N`);
  3. `username` is free: create it;
  4. otherwise create the first free `username<N>` (`N` = 1, 2, 3...). A suffix freed by a deleted account is reused.
- `Username only`: previous behaviour, the row updates the account with the same username (including its email).

| CSV username | CSV email | Existing accounts | Result |
|---|---|---|---|
| `test.user` | `test.user@example.com` | none | create `test.user` |
| `test.user` | `test.user@example.com` | same pair | update `test.user` |
| `test.user` | `pippo@example.org` | `test.user` with another email | create `test.user1` |
| `test.user` | `pippo@example.org` | `test.user1` with `pippo@example.org` | update `test.user1` |
| `test.user` | `altra@example.org` | `test.user1` with another email | create `test.user2` |

Repeated rows (in the same file or in different files of the same run) resolve to the same account, also in
dry-run mode.

Email already used by another account:

- if `allowaccountssameemail` is enabled, the account is created normally;
- if it is disabled (Moodle default), the row is skipped, logged as an error, and listed in a single summary email
  sent at the end of the run to `Email conflict notification address`, or to all site administrators when empty.
  No email is sent in dry-run mode.

Protected accounts: site administrators, the guest user and deleted accounts are never modified.

### 3) Empty Values

`Empty CSV values` (`emptyvalues`), for existing users, when an optional column is present but empty:

- `Ignore it and keep the existing value` (default);
- `Clear the existing value`.

Mandatory columns can never be empty. `lang` is never cleared. An empty `auth` keeps the current method in ignore
mode, and falls back to the default method in clear mode. For new users empty values are simply not set.

### 4) Parser Options

- `File encoding`: `UTF-8`, `ISO-8859-1`, `Windows-1252`, `auto`
- `Delimiter`: single character (default `;`)

### 5) Authentication and New User Email

- `Default authentication method`: used when CSV `auth` is empty or invalid.
- `Send credentials email`: for newly created users.
- `Email template`: supports placeholders:
  - `{{firstname}}`
  - `{{lastname}}`
  - `{{username}}`
  - `{{setpasswordurl}}`
  - `{{sitename}}`
  - `{{loginurl}}`
- Legacy compatibility:
  - `{{password}}` is still accepted as an alias for `{{setpasswordurl}}`.

### 6) Sync Behavior

- `Update only changed data`
- `Detailed logging`
- `Dry-run mode`

## CSV Format

### Header normalization

Headers are normalized before matching:

- lowercase
- spaces/hyphens converted to `_`
- non `[a-z0-9_]` characters removed
- UTF-8 BOM removed from first header

Example:

- `Course Shortname` -> `course_shortname`

### Required headers

These headers must exist:

- `username`
- `firstname`
- `lastname`
- `email`

Enrolment headers must be paired:

- `course_shortname` and `role_shortname` must both be present if either is present.

### Supported user headers

- `username`
- `firstname`
- `lastname`
- `email`
- `auth`
- `city`
- `country`
- `lang`
- `idnumber`
- `institution`
- `department`
- `phone1`
- `phone2`
- `address`

### Supported enrolment headers

- `course_shortname`
- `role_shortname`
- `enrol_start_date` (alias: `start_date`)
- `enrol_end_date` (alias: `end_date`)
- `suspended` (`0` or `1`, empty treated as `0`)
- `deleted` (`0` or `1`, empty treated as `0`)

### Custom profile fields

Two ways are accepted:

- `profile_field_<shortname>`
- `<shortname>` (if it is not a reserved/system header and matches an existing profile field)

Custom fields are written both when a user is created and when it is updated, through the core profile API
(`profile_save_data()`, the same path used by the core user upload tool):

- shortnames are matched case-insensitively (`profile_field_codicescuola` matches a field named `CodiceScuola`);
  two fields whose shortnames differ only by case are ambiguous and ignored, with a warning;
- menu fields accept the option text; text fields respect the configured maximum length;
- checkbox fields accept `0` or `1`; date fields accept a Unix timestamp, `YYYY-MM-DD` or `YYYY-MM-DD-HH-MM-SS`;
- unique fields are validated against other users;
- an invalid value is reported as a warning and the stored value is kept, the rest of the row is still processed;
- the `user_created` / `user_updated` event is triggered after custom fields are saved, so observers (for example
  cohort rules based on profile fields) see the complete profile.

### Accepted date formats

- `Y-m-d` (example `2026-02-18`)
- `d.m.Y` (example `18.02.2026`)
- `d/m/Y` (example `18/02/2026`)
- `YYYYMMDD` (example `20260218`)
- Unix timestamp (seconds, 10 digits)
- Unix timestamp (milliseconds, 13 digits)
- Empty value means `0` (no date)

## Runtime Behavior

For each source, in order: the source is announced in the log, its CSV is read, then for each row:

1. Validate mandatory user values.
2. Resolve the target account (see "Account Matching") and check email conflicts.
3. Create or update user, including custom profile fields.
4. If course shortname is present, process enrolment.

At the end of each source a summary is logged (rows, users created/updated, enrolments created/updated, errors,
duration), followed by a summary of the whole run.

Enrolment logic highlights:

- If `deleted = 1`: remove existing manual enrolment.
- If `suspended = 1`: set enrolment to suspended.
- If enrolment exists and start/end dates are empty: preserve existing dates.
- If role changes, plugin enforces exactly one manual role assignment for that instance.

If a manual enrolment instance is missing on a target course, the plugin creates one (unless dry-run).

## Security Notes

- Template download endpoint requires:
  - authenticated session
  - valid `sesskey`
  - `moodle/site:config` capability
- Plugin file serving is restricted to system context and `moodle/site:config`.
- User data from CSV is sanitized before writes.
- New user:
  - It sends a one-time password setup/reset URL (`{{setpasswordurl}}`) when supported by user auth.
  - Falls back to login URL when reset links are not available.
- Exception messages are sanitized before persistent logging to reduce sensitive path leakage.
- Folder sources: files are read only through the File API, and only when uploaded by a user holding
  `local/csv_user_sync:uploadsource` in system context (see "Folder sources security").
- Site administrators, the guest user and deleted accounts are never modified by a CSV row.
- Enrolment roles must be assignable in course context.
- Optional user fields are validated: `lang` must be an installed language, `country` a valid country code,
  and values longer than the database column are rejected.
- Email conflict notifications are sent with `email_to_user()` from the no-reply address, once per run.

## Logging and Privacy

Logs are written to:

- `mtrace` output
- `local_csv_user_sync_log` table (`runid`, level, rownum, userid, username, message, timecreated)

Privacy support:

- Plugin declares metadata and supports export/deletion for user-related records in `local_csv_user_sync_log`.

## Scheduled Task

Task class:

- `\local_csv_user_sync\task\sync_task`

Default schedule:

- random minute / random hour, daily (`R R * * *`)

Manual execution example:

```bash
php admin/cli/scheduled_task.php --execute="\\local_csv_user_sync\\task\\sync_task"
```

## Dry-run Mode

When enabled:

- no DB writes for users/enrolments
- actions are simulated and logged
- enrolment for newly "created" dry-run users is skipped because no real user ID exists

## Troubleshooting

Common checks:

- Ensure at most one of "CSV file path" and "CSV file upload" is configured, or that source folders are selected.
- For folder sources: check that each folder contains one CSV and that the uploader holds
  `local/csv_user_sync:uploadsource` in system context.
- Ensure delimiter is exactly one character.
- Ensure required headers exist.
- Ensure `course_shortname` and `role_shortname` are both present for enrolment imports.
- Ensure role shortnames and course shortnames exist in Moodle.
- Ensure uploaded file still exists in plugin file storage.
