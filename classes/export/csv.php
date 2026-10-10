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

/**
 * CSV exporter, using the site's configured delimiter.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv extends base {

    #[\Override]
    public function get_extension(): string {
        return 'csv';
    }

    /**
     * A core CSV writer loaded with the report.
     *
     * @return \csv_export_writer
     */
    protected function get_writer(): \csv_export_writer {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $delimiter = get_config('block_configurable_reports', 'csvdelimiter');
        $writer = new \csv_export_writer("$delimiter", '"', 'application/download', true);
        $writer->set_filename(format_string($this->report->name) ?: 'report');
        foreach ($this->get_matrix() as $row) {
            $writer->add_data($row);
        }
        return $writer;
    }

    #[\Override]
    public function get_content(): string {
        return (string) $this->get_writer()->print_csv_data(true);
    }

    #[\Override]
    public function download(): void {
        $this->get_writer()->download_file();
        exit;
    }

}
