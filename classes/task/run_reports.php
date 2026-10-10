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

namespace block_configurable_reports\task;

use block_configurable_reports\export\base as exporter;
use block_configurable_reports\local\schedule;
use stdClass;

/**
 * Runs scheduled reports and emails their exports to the configured recipients.
 *
 * Each recipient must be a Moodle user (matched by email address). The report is built
 * as that user, so their timezone and filter preferences apply, then every export
 * format the report has enabled is zipped and attached to the message.
 *
 * @package    block_configurable_reports
 * @author     David Saylor
 * @copyright  2026 Dragonfly EdTech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_reports extends \core\task\scheduled_task {

    #[\Override]
    public function get_name(): string {
        return get_string('crontask', 'block_configurable_reports');
    }

    #[\Override]
    public function execute(): void {
        $due = schedule::get_due_reports(time());
        if (!$due) {
            mtrace('... no scheduled reports due');
            return;
        }
        foreach ($due as $report) {
            try {
                $this->run_report($report);
                mtrace("... ran report {$report->id} ({$report->name})");
            } catch (\Throwable $e) {
                mtrace("... report {$report->id} ({$report->name}) FAILED: " . $e->getMessage());
            }
        }
    }

    /**
     * Build and email one report to each of its recipients, then stamp lastrun.
     *
     * @param stdClass $report The block_configurable_reports row.
     */
    public function run_report(stdClass $report): void {
        global $CFG, $DB;

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
        require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
        require_once($CFG->dirroot . '/blocks/configurable_reports/reports/' . $report->type . '/report.class.php');

        $recipients = self::get_recipients($report);
        foreach ($recipients['unknown'] as $email) {
            mtrace("... no active user account for {$email}, skipped");
        }
        foreach ($recipients['users'] as $user) {
            $this->send_to_user($report, $user);
        }

        $DB->set_field('block_configurable_reports', 'lastrun', time(), ['id' => $report->id]);
    }

    /**
     * Resolve the report's emailto list to user accounts.
     *
     * @param stdClass $report
     * @return array ['users' => stdClass[] keyed by id, 'unknown' => string[] addresses with no account]
     */
    public static function get_recipients(stdClass $report): array {
        global $DB;

        $emails = preg_split('/[\s,;]+/', \core_text::strtolower((string) $report->emailto), -1, PREG_SPLIT_NO_EMPTY);
        $emails = array_values(array_unique($emails));
        if (!$emails) {
            return ['users' => [], 'unknown' => []];
        }

        [$insql, $params] = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED);
        $users = $DB->get_records_select('user', "deleted = 0 AND suspended = 0 AND LOWER(email) $insql", $params);

        $found = [];
        foreach ($users as $user) {
            $found[] = \core_text::strtolower($user->email);
        }

        return ['users' => $users, 'unknown' => array_values(array_diff($emails, $found))];
    }

    /**
     * Build the report as $user and email it to them.
     *
     * @param stdClass $report
     * @param stdClass $user
     */
    protected function send_to_user(stdClass $report, stdClass $user): void {
        $classname = 'report_' . $report->type;
        $reportclass = new $classname($report, $user);
        if ($report->type === 'sql') {
            $reportclass->set_forexport(true);
        }
        $reportclass->create_report();

        $files = [];
        if (!empty($reportclass->totalrecords)) {
            foreach (array_filter(explode(',', (string) $report->export)) as $format) {
                $exporter = exporter::instance($format, $reportclass->finalreport);
                if ($exporter === null) {
                    continue;
                }
                $files[$exporter->get_filename()] = $exporter->save_to_temp();
            }
        }

        try {
            $this->email_report($report, $user, $files);
        } finally {
            foreach ($files as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * Send the message, with the exports zipped as one attachment when there are any.
     *
     * @param stdClass $report
     * @param stdClass $user
     * @param array $files filename inside the zip => temp path
     */
    protected function email_report(stdClass $report, stdClass $user, array $files): void {
        $name = format_string($report->name);
        $subject = get_string('emailsubject', 'block_configurable_reports', $name);
        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id' => $report->id]);

        $attachment = '';
        $attachname = '';
        if ($files) {
            $intro = get_string('datareturned', 'block_configurable_reports', $name);
            $attachment = tempnam(make_temp_directory('block_configurable_reports'), 'zip_');
            get_file_packer('application/zip')->archive_to_pathname($files, $attachment);
            $attachname = clean_filename($name ?: 'report') . '.zip';
        } else {
            $intro = get_string('nodatareturned', 'block_configurable_reports', $name);
        }
        $text = $intro . "\n" . $url->out(false);
        $html = \html_writer::tag('p', $intro . ' ' . \html_writer::link($url, $url->out(false)));

        try {
            $sent = email_to_user($user, \core_user::get_noreply_user(), $subject, $text, $html, $attachment, $attachname);
            if (!$sent) {
                mtrace(get_string('emailsentfailed', 'block_configurable_reports', $user->email));
            }
        } finally {
            if ($attachment) {
                @unlink($attachment);
            }
        }
    }

}
