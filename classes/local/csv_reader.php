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
 * CSV reader for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_csv_user_sync\local;

use core_text;
use Generator;
use moodle_exception;

/**
 * Streams a CSV file row by row with normalization and sanitization.
 */
class csv_reader {
    /** @var string */
    private readonly string $filepath;

    /** @var string */
    private readonly string $delimiter;

    /** @var string */
    private readonly string $encoding;

    /** @var bool */
    private readonly bool $sanitizeformulacells;

    /**
     * Constructor.
     *
     * @param string $filepath
     * @param string $delimiter
     * @param string $encoding
     * @param bool $sanitizeformulacells
     */
    public function __construct(
        string $filepath,
        string $delimiter = ';',
        string $encoding = 'UTF-8',
        bool $sanitizeformulacells = true
    ) {
        $this->filepath = $filepath;
        $this->delimiter = $delimiter;
        $this->encoding = $encoding;
        $this->sanitizeformulacells = $sanitizeformulacells;
    }

    /**
     * Validate CSV source before processing.
     *
     * @return string[] list of error messages
     */
    public function validate_file(): array {
        $errors = [];
        if ($this->filepath === '') {
            $errors[] = get_string('error:csvpathrequired', 'local_csv_user_sync');
        } else if (!file_exists($this->filepath)) {
            $errors[] = get_string('error:csvpathnotfound', 'local_csv_user_sync', $this->filepath);
        } else if (!is_file($this->filepath) || !is_readable($this->filepath)) {
            $errors[] = get_string('error:csvpathnotreadable', 'local_csv_user_sync', $this->filepath);
        }

        if ($this->delimiter === '' || core_text::strlen($this->delimiter) !== 1) {
            $errors[] = get_string('error:delimiterinvalid', 'local_csv_user_sync');
        }

        return $errors;
    }

    /**
     * Reads and returns normalized CSV headers.
     *
     * @return array<int,string>
     * @throws moodle_exception
     */
    public function get_headers(): array {
        $handle = fopen($this->filepath, 'rb');
        if ($handle === false) {
            throw new moodle_exception('error:csvpathnotreadable', 'local_csv_user_sync', '', $this->filepath);
        }

        try {
            return array_values($this->read_headers($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Reads and yields normalized CSV rows.
     *
     * @return Generator<int, array{rownum:int,data:array<string,string>}>
     * @throws moodle_exception
     */
    public function get_rows(): Generator {
        $handle = fopen($this->filepath, 'rb');
        if ($handle === false) {
            throw new moodle_exception('error:csvpathnotreadable', 'local_csv_user_sync', '', $this->filepath);
        }

        try {
            $headers = $this->read_headers($handle);
            $rownum = 1;
            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
                $rownum++;
                if ($row === [null]) {
                    continue;
                }

                $normalized = [];
                foreach ($headers as $index => $header) {
                    $value = isset($row[$index]) ? (string)$row[$index] : '';
                    $normalized[$header] = $this->clean_cell($value, true);
                }

                if ($this->is_empty_row($normalized)) {
                    continue;
                }

                yield ['rownum' => $rownum, 'data' => $normalized];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read and normalize headers.
     *
     * @param resource $handle
     * @return array<int,string>
     * @throws moodle_exception
     */
    private function read_headers($handle): array {
        $headerrow = fgetcsv($handle, 0, $this->delimiter, '"', '\\');
        if ($headerrow === false || $headerrow === [null]) {
            throw new moodle_exception('error:csvheadernotfound', 'local_csv_user_sync');
        }

        $headers = [];
        $seen = [];
        foreach ($headerrow as $index => $header) {
            $header = $this->strip_utf8_bom($this->clean_cell((string)$header, false));
            $normalized = $this->normalize_header($header);

            if ($normalized === '') {
                throw new moodle_exception('error:csvheaderinvalid', 'local_csv_user_sync', '', (string)($index + 1));
            }

            if (isset($seen[$normalized])) {
                throw new moodle_exception('error:csvheaderduplicate', 'local_csv_user_sync', '', $normalized);
            }

            $headers[$index] = $normalized;
            $seen[$normalized] = true;
        }

        return $headers;
    }

    /**
     * Returns true if all row fields are empty.
     *
     * @param array<string,string> $row
     * @return bool
     */
    private function is_empty_row(array $row): bool {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Normalize header naming to a stable snake_case key.
     *
     * @param string $header
     * @return string
     */
    private function normalize_header(string $header): string {
        $header = core_text::strtolower(trim($header));
        $header = preg_replace('/[\s\-]+/', '_', $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        return trim((string)$header, '_');
    }

    /**
     * Converts encoding and sanitizes one CSV cell.
     *
     * @param string $value
     * @param bool $sanitizeformula
     * @return string
     */
    private function clean_cell(string $value, bool $sanitizeformula): string {
        $value = $this->convert_to_utf8($value);
        $value = str_replace("\0", '', $value);
        $value = trim($value);

        if ($sanitizeformula && $this->sanitizeformulacells && preg_match('/^[=\+\-@]/', ltrim($value))) {
            // Prevent formula execution if this value is ever exported to spreadsheets later.
            $value = "'" . $value;
        }

        return $value;
    }

    /**
     * Convert one string to UTF-8 according to configured encoding.
     *
     * @param string $value
     * @return string
     */
    private function convert_to_utf8(string $value): string {
        if ($value === '') {
            return $value;
        }

        if (core_text::strtolower($this->encoding) === 'auto') {
            $detected = mb_detect_encoding($value, mb_list_encodings(), true);
            if ($detected !== false && core_text::strtolower($detected) !== 'utf-8') {
                $converted = core_text::convert($value, $detected, 'utf-8');
                return is_string($converted) ? $converted : $value;
            }
            return $value;
        }

        if (core_text::strtolower($this->encoding) === 'utf-8') {
            return $value;
        }

        $converted = core_text::convert($value, $this->encoding, 'utf-8');
        return is_string($converted) ? $converted : $value;
    }

    /**
     * Remove UTF-8 BOM if present.
     *
     * @param string $value
     * @return string
     */
    private function strip_utf8_bom(string $value): string {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
