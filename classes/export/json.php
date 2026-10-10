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
 * JSON exporter: one object per record keyed by column heading.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class json extends base {

    #[\Override]
    public function get_extension(): string {
        return 'json';
    }

    #[\Override]
    protected function get_mimetype(): string {
        return 'application/json';
    }

    #[\Override]
    public function get_content(): string {
        $table = $this->report->table;
        $headers = $table->head ?? [];
        $records = [];
        foreach ($table->data ?? [] as $row) {
            $record = [];
            foreach ($row as $index => $value) {
                $record[$headers[$index] ?? $index] = format_string((string) $value);
            }
            $records[] = $record;
        }
        return json_encode($records);
    }

}
