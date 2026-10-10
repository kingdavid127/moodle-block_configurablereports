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
 * OpenDocument spreadsheet exporter.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ods extends base {

    #[\Override]
    public function get_extension(): string {
        return 'ods';
    }

    /**
     * Fill a workbook with the report as one worksheet.
     *
     * @param \MoodleODSWorkbook $workbook
     */
    protected function fill(\MoodleODSWorkbook $workbook): void {
        $sheet = $workbook->add_worksheet($this->get_basename());
        foreach ($this->get_matrix() as $ri => $row) {
            foreach ($row as $ci => $value) {
                $sheet->write_string($ri, $ci, $value);
            }
        }
    }

    #[\Override]
    public function get_content(): string {
        $workbook = new ods_workbook('-');
        $this->fill($workbook);
        return $workbook->get_content();
    }

    #[\Override]
    public function download(): void {
        global $CFG;
        require_once($CFG->libdir . '/odslib.class.php');

        $workbook = new \MoodleODSWorkbook('-');
        $workbook->send($this->get_filename());
        $this->fill($workbook);
        $workbook->close();
        exit;
    }

}
