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
 * SYLK exporter. See https://en.wikipedia.org/wiki/Symbolic_Link_(SYLK).
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slk extends base {

    #[\Override]
    public function get_extension(): string {
        return 'slk';
    }

    #[\Override]
    public function get_filename(): string {
        return $this->get_basename() . '-' . gmdate('Ymd_Hi') . '.slk';
    }

    #[\Override]
    protected function get_mimetype(): string {
        return 'application/download; charset=iso-8859-1';
    }

    #[\Override]
    public function get_content(): string {
        $table = $this->report->table;
        $out = "ID;P\n";
        $rownumber = 1;
        if (!empty($table->head)) {
            $out .= $this->row(array_values((array) $table->head), $rownumber++);
        }
        foreach ($table->data ?? [] as $row) {
            $out .= $this->row(array_values((array) $row), $rownumber++);
        }
        $out .= "E\n";
        return $out;
    }

    /**
     * One SYLK row: a C record per cell.
     *
     * @param array $data
     * @param int $rownumber 1-based
     * @return string
     */
    protected function row(array $data, int $rownumber): string {
        $line = '';
        $col = 1;
        foreach ($data as $datum) {
            $datum = htmlspecialchars_decode(strip_tags(nl2br(str_replace('"', "'", (string) $datum))));
            if (preg_match('!!u', $datum)) {
                $datum = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $datum);
            }
            $line .= "C;Y$rownumber;X$col;K\"$datum\"\n";
            $col++;
        }
        return $line;
    }

}
