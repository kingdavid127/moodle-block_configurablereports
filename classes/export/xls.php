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
 * Excel (xlsx) exporter.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xls extends base {

    #[\Override]
    public function get_extension(): string {
        return 'xlsx';
    }

    /**
     * Fill a workbook with the report as one worksheet.
     *
     * @param \MoodleExcelWorkbook $workbook
     */
    protected function fill(\MoodleExcelWorkbook $workbook): void {
        $sheet = $workbook->add_worksheet($this->get_basename());
        foreach ($this->get_matrix() as $ri => $row) {
            foreach ($row as $ci => $value) {
                $sheet->write_string($ri, $ci, $value);
            }
        }
    }

    #[\Override]
    public function save_to(string $path): void {
        $workbook = new excel_workbook('-');
        $this->fill($workbook);
        $workbook->save_to($path);
    }

    #[\Override]
    public function get_content(): string {
        $path = $this->save_to_temp();
        $content = (string) file_get_contents($path);
        @unlink($path);
        return $content;
    }

    #[\Override]
    public function download(): void {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($this->get_filename());
        $this->fill($workbook);
        $workbook->close();
        exit;
    }

}
