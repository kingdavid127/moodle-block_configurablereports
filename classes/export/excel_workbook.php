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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * A core Excel workbook that can also be saved to a file instead of streamed.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excel_workbook extends \MoodleExcelWorkbook {

    /**
     * Write the workbook to disk without sending any headers.
     *
     * @param string $path
     */
    public function save_to(string $path): void {
        foreach ($this->objspreadsheet->getAllSheets() as $sheet) {
            $sheet->setSelectedCells('A1');
        }
        $this->objspreadsheet->setActiveSheetIndex(0);

        IOFactory::createWriter($this->objspreadsheet, $this->type)->save($path);
    }

}
