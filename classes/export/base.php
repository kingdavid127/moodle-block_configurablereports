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

namespace block_configurable_reports\export;

use stdClass;

/**
 * Base class for report exporters.
 *
 * An exporter can either stream the file to the browser (download) or hand back the
 * file contents / a temp file, which is what the scheduled email task needs. The
 * export/<format>/export.php entry points used by viewreport.php are thin wrappers
 * around these classes.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /** @var stdClass The final report object (name, table, calcs, fixedwidthpattern). */
    protected stdClass $report;

    /**
     * @param stdClass $report The final report object built by report_base::create_report().
     */
    public function __construct(stdClass $report) {
        $this->report = $report;
    }

    /**
     * Instantiate the exporter for a format name such as "csv" or "fixedwidth".
     *
     * @param string $format
     * @param stdClass $report
     * @return self|null Null when no exporter exists for the format.
     */
    public static function instance(string $format, stdClass $report): ?self {
        if (!preg_match('/^[a-z0-9]+$/', $format)) {
            return null;
        }
        $classname = __NAMESPACE__ . '\\' . $format;
        if (!class_exists($classname) || !is_subclass_of($classname, self::class)) {
            return null;
        }
        return new $classname($report);
    }

    /**
     * File extension without the dot.
     *
     * @return string
     */
    abstract public function get_extension(): string;

    /**
     * The complete file contents.
     *
     * @return string
     */
    abstract public function get_content(): string;

    /**
     * MIME type used when the base download() sends the content.
     *
     * @return string
     */
    protected function get_mimetype(): string {
        return 'application/download';
    }

    /**
     * Send the file to the browser and end the request.
     */
    public function download(): void {
        $this->send_download($this->get_content(), $this->get_mimetype());
    }

    /**
     * Write the file to disk.
     *
     * @param string $path
     */
    public function save_to(string $path): void {
        file_put_contents($path, $this->get_content());
    }

    /**
     * Write the file into the plugin's temp directory (inside $CFG->tempdir, so it can be
     * attached to an email) and return its path. The caller deletes it when done.
     *
     * @return string
     */
    public function save_to_temp(): string {
        $path = tempnam(make_temp_directory('block_configurable_reports'), 'export_');
        $this->save_to($path);
        return $path;
    }

    /**
     * Report name made safe for a filename.
     *
     * @return string
     */
    public function get_basename(): string {
        return clean_filename(format_string($this->report->name) ?: 'report');
    }

    /**
     * Filename offered to the browser or used inside the email zip.
     *
     * @return string
     */
    public function get_filename(): string {
        return $this->get_basename() . '.' . $this->get_extension();
    }

    /**
     * The report as a plain matrix: row 0 is the headings, then one row per record,
     * every cell stripped of markup and line breaks.
     *
     * @return array
     */
    protected function get_matrix(): array {
        $table = $this->report->table;
        $matrix = [];

        if (!empty($table->head)) {
            foreach ($table->head as $key => $heading) {
                $matrix[0][$key] = $this->clean(format_string($heading));
            }
        }
        if (!empty($table->data)) {
            foreach ($table->data as $rkey => $row) {
                foreach ($row as $key => $item) {
                    $matrix[$rkey + 1][$key] = $this->clean(format_string((string) $item));
                }
            }
        }

        return $matrix;
    }

    /**
     * Strip markup and line breaks from a cell.
     *
     * @param string $value
     * @return string
     */
    protected function clean(string $value): string {
        return str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($value))));
    }

    /**
     * Stream a string to the browser as an attachment and stop.
     *
     * @param string $content
     * @param string $mimetype
     */
    protected function send_download(string $content, string $mimetype): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        send_file($content, $this->get_filename(), 0, 0, true, true, $mimetype);
        exit;
    }

}
