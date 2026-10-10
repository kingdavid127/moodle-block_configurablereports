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

namespace block_configurable_reports\local;

use stdClass;

/**
 * Fixed width text renderer for report exports.
 *
 * The layout is described by a JSON array stored in the report's "fixedwidthpattern"
 * column. Each entry is a line specification:
 *
 *   {"pattern": "HEADER,10|%%rowcount%%,6"}
 *       A single line. Each "name,width" field either names a report column (its
 *       cell value is written) or is written literally.
 *   {"index": "userid", "pattern": "userid,8|lastname,20", "children": [...]}
 *       One line per report row, keyed by the "index" column. Child specifications
 *       are rendered beneath each parent line and may use %%count%% for a running
 *       counter within the parent.
 *
 * Numeric values are left padded with zeros, text is right padded with spaces, and
 * anything longer than its width is truncated.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fixedwidth {

    /**
     * Render the report as fixed width text.
     *
     * @param stdClass $report The final report object (name, table, fixedwidthpattern).
     * @return string The complete file contents, one line per output row.
     * @throws \moodle_exception When the stored pattern is not valid JSON.
     */
    public function build(stdClass $report): string {
        $patterndata = json_decode($report->fixedwidthpattern ?? '');
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($patterndata)) {
            throw new \moodle_exception('invalidjson', 'block_configurable_reports');
        }

        $txt = '';
        $numberoflines = 0;
        foreach ($patterndata as $item) {
            $data = [];
            $this->print_rows($data, [$item], $report, $numberoflines);

            // Drop the bookkeeping buckets used by %%count%%.
            foreach (array_keys($data) as $index) {
                if (strpos((string) $index, '%%') === 0) {
                    unset($data[$index]);
                }
            }
            array_walk_recursive($data, function($line) use (&$txt) {
                $txt .= "$line\n";
            });

            $numberoflines = substr_count($txt, "\n");
        }

        return $txt;
    }

    /**
     * Render a list of line specifications into $data.
     *
     * @param array $data Output accumulator, grouped by index value.
     * @param array $rows Line specifications (decoded JSON objects).
     * @param stdClass $report The final report object.
     * @param int $numberoflines Lines already emitted (feeds %%rowcount%%).
     * @param bool $hasparent True when rendering children of an indexed line.
     */
    protected function print_rows(array &$data, array $rows, stdClass $report, int $numberoflines,
            bool $hasparent = false): void {
        foreach ($rows as $row) {
            $indexkey = null;
            if (isset($row->index)) {
                $indexkey = $this->find_column($row->index, $report);
            }

            if (is_numeric($indexkey)) {
                $fields = $this->parse_pattern($row->pattern ?? '');
                foreach ($fields as $index => $field) {
                    $fields[$index]['key'] = $this->find_column($field['name'], $report);
                }
                if (!empty($report->table->data) && !empty($fields)) {
                    foreach ($report->table->data as $line) {
                        $linetxt = '';
                        $indexvalue = $this->clean_data($line[$indexkey]);
                        $counter = false;
                        foreach ($fields as $field) {
                            if (is_numeric($field['key'])) {
                                $linetxt .= $this->generate_string($this->clean_data($line[$field['key']]), $field['width']);
                            } else if ($field['name'] === '%%count%%') {
                                $counter = true;
                                $count = 1;
                                if (isset($data['%%' . $indexvalue][$fields[0]['name']])) {
                                    $count = count($data['%%' . $indexvalue][$fields[0]['name']]) + 1;
                                }
                                $linetxt .= $this->generate_string($count, $field['width']);
                            } else {
                                $linetxt .= $this->generate_string($this->clean_data($field['name']), $field['width']);
                            }
                        }

                        // Top level output has to be single.
                        if ($hasparent) {
                            if ($counter) {
                                $data[$indexvalue][$fields[0]['name']] = $linetxt;
                                $data['%%' . $indexvalue][$fields[0]['name']][] = $linetxt;
                            } else {
                                $data[$indexvalue][$fields[0]['name']][] = $linetxt;
                            }
                        } else {
                            $data[$indexvalue][$fields[0]['name']] = $linetxt;
                        }
                    }
                }
            } else if ($fields = $this->parse_pattern($row->pattern ?? '')) {
                $linetxt = '';
                foreach ($fields as $field) {
                    if ($this->normalise($field['name']) === '%%rowcount%%') {
                        $linetxt .= $this->generate_string($numberoflines + 1, $field['width']);
                    } else {
                        $linetxt .= $this->generate_string($field['name'], $field['width']);
                    }
                }
                $data[] = $linetxt;
            }

            if (!empty($row->children)) {
                $this->print_rows($data, $row->children, $report, $numberoflines, true);
            }
        }
    }

    /**
     * Locate a report column by name, ignoring case and treating underscores as spaces.
     *
     * @param string $name
     * @param stdClass $report
     * @return int|false The column offset, or false when no heading matches.
     */
    protected function find_column(string $name, stdClass $report) {
        return array_search($this->normalise($name), array_map([$this, 'normalise'], $report->table->head ?? []));
    }

    /**
     * Normalise a heading or field name for comparison.
     *
     * @param string $name
     * @return string
     */
    protected function normalise(string $name): string {
        return str_replace('_', ' ', strtolower($name));
    }

    /**
     * Strip markup and line breaks from a cell value.
     *
     * @param mixed $tr
     * @return string
     */
    protected function clean_data($tr): string {
        return str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br((string) $tr))));
    }

    /**
     * Split a "name,width|name,width" pattern into fields.
     *
     * @param string $pattern
     * @return array List of ['name' => string, 'width' => int].
     */
    public function parse_pattern(string $pattern): array {
        $fields = [];
        foreach (explode('|', $pattern) as $item) {
            $parts = explode(',', $item);
            if (count($parts) !== 2) {
                continue;
            }
            $fields[] = ['name' => trim($parts[0]), 'width' => (int) trim($parts[1])];
        }
        return $fields;
    }

    /**
     * Pad or truncate a value to exactly $width characters.
     *
     * Numbers are left padded with zeros, everything else is right padded with spaces.
     *
     * @param mixed $data
     * @param int $width
     * @return string
     */
    protected function generate_string($data, int $width): string {
        $data = (string) $data;
        if (is_numeric($data)) {
            $output = str_pad($data, $width, '0', STR_PAD_LEFT);
        } else {
            $output = str_pad($data, $width);
        }
        if (strlen($output) > $width) {
            $output = substr($output, 0, $width);
        }
        return $output;
    }

}
