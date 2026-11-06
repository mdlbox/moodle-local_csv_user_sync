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
 * Notification email helper for local_csv_user_sync.
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
 * Sends plugin notification emails to site administrators and/or the configured notification address.
 */
class notification_mailer {
    /**
     * Send one plain-text notification.
     *
     * @param string $subject
     * @param string $body plain text
     * @param string $address configured notification address (may be empty)
     * @param bool $alwaysadmins true to notify site administrators in addition to the configured address
     * @return int number of recipients the email was sent to
     */
    public static function send(string $subject, string $body, string $address, bool $alwaysadmins): int {
        $sender = core_user::get_noreply_user();
        $sent = 0;
        foreach (self::get_recipients($address, $alwaysadmins) as $recipient) {
            if (email_to_user($recipient, $sender, $subject, $body, text_to_html(s($body)))) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Returns the recipients: the configured address (if valid) and, when requested or when no valid address
     * is configured, all site administrators. Duplicate addresses are removed.
     *
     * A configured address is not tied to an account, so the main administrator record is used
     * as a carrier with the email overridden (same approach as core badges verification emails).
     *
     * @param string $address
     * @param bool $alwaysadmins
     * @return \stdClass[]
     */
    public static function get_recipients(string $address, bool $alwaysadmins): array {
        $address = trim($address);
        $hasaddress = $address !== '' && validate_email($address);

        $recipients = [];
        if ($hasaddress) {
            $admin = get_admin();
            if ($admin) {
                $carrier = clone $admin;
                $carrier->email = $address;
                // The function email_to_user() silently skips suspended or nologin recipients: the carrier must not inherit them.
                $carrier->auth = 'manual';
                $carrier->suspended = 0;
                $recipients[core_text::strtolower($address)] = $carrier;
            }
        }

        if ($alwaysadmins || !$hasaddress) {
            foreach (get_admins() as $admin) {
                $key = core_text::strtolower(trim((string)$admin->email));
                if (!isset($recipients[$key])) {
                    $recipients[$key] = $admin;
                }
            }
        }

        return array_values($recipients);
    }
}
