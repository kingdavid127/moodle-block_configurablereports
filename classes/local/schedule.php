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
 * Decides when a scheduled (emailed) report is due.
 *
 * A report runs once per period (day, week, month or quarter), at or after the chosen
 * hour on the first day of that period. Times use the site timezone, which Moodle sets
 * as PHP's default timezone. Weeks start on the site's calendar start day.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule {

    /** @var string[] Supported frequencies, in the order shown to the user. */
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly'];

    /**
     * Frequency select options.
     *
     * @return array
     */
    public static function frequency_options(): array {
        $options = [];
        foreach (self::FREQUENCIES as $frequency) {
            $options[$frequency] = get_string('automatically' . $frequency, 'block_configurable_reports');
        }
        return $options;
    }

    /**
     * Hour-of-day select options, 0 => "00:00" .. 23 => "23:00".
     *
     * @return array
     */
    public static function hour_options(): array {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $options[$hour] = sprintf('%02d:00', $hour);
        }
        return $options;
    }

    /**
     * Every enabled scheduled report that is due at $timenow, keyed by id.
     *
     * @param int $timenow
     * @return stdClass[]
     */
    public static function get_due_reports(int $timenow): array {
        global $DB;

        $due = [];
        $candidates = $DB->get_records_select(
            'block_configurable_reports',
            "enableschedule = 1 AND emailto IS NOT NULL AND emailto <> ''",
            [],
            'id'
        );
        foreach ($candidates as $report) {
            if (self::is_due($report, $timenow)) {
                $due[$report->id] = $report;
            }
        }
        return $due;
    }

    /**
     * Whether a report should run now: the current period's run time has passed and the
     * report has not run since that period began.
     *
     * @param stdClass $report
     * @param int $timenow
     * @return bool
     */
    public static function is_due(stdClass $report, int $timenow): bool {
        $periodstart = self::period_start((string) $report->frequency, $timenow);
        if ($periodstart === null) {
            return false;
        }
        $runtime = $periodstart + ((int) $report->runat) * HOURSECS;

        return $timenow >= $runtime && (int) $report->lastrun < $periodstart;
    }

    /**
     * Midnight at the start of the period containing $timenow, or null for an unknown frequency.
     *
     * @param string $frequency
     * @param int $timenow
     * @return int|null
     */
    public static function period_start(string $frequency, int $timenow): ?int {
        $date = getdate($timenow);
        switch ($frequency) {
            case 'daily':
                return mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']);
            case 'weekly':
                $startwday = (int) (get_config('core', 'calendar_startwday') ?: 1);
                $daysintoweek = ($date['wday'] - $startwday + 7) % 7;
                return mktime(0, 0, 0, $date['mon'], $date['mday'] - $daysintoweek, $date['year']);
            case 'monthly':
                return mktime(0, 0, 0, $date['mon'], 1, $date['year']);
            case 'quarterly':
                $firstmonth = intdiv($date['mon'] - 1, 3) * 3 + 1;
                return mktime(0, 0, 0, $firstmonth, 1, $date['year']);
            default:
                return null;
        }
    }

}
