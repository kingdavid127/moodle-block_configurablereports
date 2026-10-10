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

/**
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');

/**
 * Class report_edit_form
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class report_edit_form extends moodleform {

    /**
     * Form definition
     */
    public function definition(): void {
        global $CFG;

        $adminmode = optional_param('adminmode', null, PARAM_INT);

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['maxlength' => 128, 'size' => 58]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_NOTAGS);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'summary_editor', get_string('summary'), null, $this->get_editor_options());
        $mform->setType('summary_editor', PARAM_RAW);
        $typeoptions = cr_get_report_plugins($this->_customdata['courseid']);

        $eloptions = [];
        if (isset($this->_customdata['report']->id) && $this->_customdata['report']->id) {
            $eloptions = ['disabled' => 'disabled'];
        }
        $select = $mform->addElement(
            'select',
            'type',
            get_string('typeofreport', 'block_configurable_reports'),
            $typeoptions,
            $eloptions
        );
        $mform->addHelpButton('type', 'typeofreport', 'block_configurable_reports');
        $select->setSelected('sql');

        for ($i = 0; $i <= 100; $i++) {
            $pagoptions[$i] = $i;
        }
        $mform->addElement('select', 'pagination', get_string('pagination', 'block_configurable_reports'), $pagoptions);
        $mform->setDefault('pagination', 0);
        $mform->addHelpButton('pagination', 'pagination', 'block_configurable_reports');

        $mform->addElement(
            'checkbox',
            'global',
            get_string('global', 'block_configurable_reports'),
            get_string('enableglobal', 'block_configurable_reports')
        );
        $mform->addHelpButton('global', 'global', 'block_configurable_reports');
        $mform->setDefault('global', 0);

        $mform->addElement(
            'checkbox',
            'jsordering',
            get_string('ordering', 'block_configurable_reports'),
            get_string('enablejsordering', 'block_configurable_reports')
        );
        $mform->addHelpButton('jsordering', 'jsordering', 'block_configurable_reports');
        $mform->setDefault('jsordering', 1);

        $mform->addElement('checkbox', 'displaytotalrecords', get_string('displaytotalrecords', 'block_configurable_reports'), get_string('displaytotalrecordsdescription', 'block_configurable_reports'));
        $mform->addElement('checkbox', 'displayprintbutton', get_string('displayprintbutton', 'block_configurable_reports'), get_string('displayprintbuttondescription', 'block_configurable_reports'));

        $mform->addElement(
            'checkbox',
            'converttime',
            get_string('converttime', 'block_configurable_reports'),
            get_string('converttimedescription', 'block_configurable_reports')
        );
        $mform->addHelpButton('converttime', 'converttime', 'block_configurable_reports');
        $mform->setDefault('converttime', 0);
        $mform->disabledIf('converttime', 'type', 'neq', 'sql');
        $mform->addElement('text', 'timeformat', get_string('timeformat', 'block_configurable_reports'), ['size' => 30]);
        $mform->setType('timeformat', PARAM_TEXT);
        $mform->setDefault('timeformat', '%m/%d/%Y');
        $mform->addHelpButton('timeformat', 'timeformat', 'block_configurable_reports');
        $mform->disabledIf('timeformat', 'converttime', 'notchecked');

        $mform->addElement('checkbox', 'cron', get_string('cron', 'block_configurable_reports'), get_string('crondescription', 'block_configurable_reports'));
        $mform->addHelpButton('cron', 'cron', 'block_configurable_reports');
        $mform->setDefault('cron', 0);
        $mform->disabledIf('cron', 'type', 'neq', 'sql');

        $mform->addElement(
            'checkbox',
            'remote',
            get_string('remote', 'block_configurable_reports'),
            get_string('remotedescription', 'block_configurable_reports')
        );
        $mform->addHelpButton('remote', 'remote', 'block_configurable_reports');
        $mform->setDefault('remote', 0);

        // Adds an embed link for easy copy/paste once the report is saved.
        if (isset($this->_customdata['report']->id) && $this->_customdata['report']->id) {

            $params = [
                'id' => $this->_customdata['report']->id,
                'courseid' => $this->_customdata['courseid'],
                'embed' => true
            ];
            $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', $params);

            $mform->addElement('static', 'embedlink',
                get_string('embedlink', 'block_configurable_reports'),
                html_writer::tag('pre', $url, ['class' => 'mb-0']).
                get_string('embedlinkdescription', 'block_configurable_reports')
            );
        }


        $mform->addElement('header', 'exportoptions', get_string('exportoptions', 'block_configurable_reports'));
        $options = cr_get_export_plugins();

        foreach ($options as $key => $val) {
            $mform->addElement('checkbox', 'export_' . $key, null, $val);
        }

        $mform->addElement(
            'textarea',
            'fixedwidthpattern',
            get_string('fixedwidthpattern', 'block_configurable_reports'),
            ['rows' => 8, 'cols' => 60]
        );
        $mform->setType('fixedwidthpattern', PARAM_RAW);
        $mform->addHelpButton('fixedwidthpattern', 'fixedwidthpattern', 'block_configurable_reports');
        $mform->hideIf('fixedwidthpattern', 'export_fixedwidth', 'notchecked');

        $mform->addElement('header', 'scheduleoptions', get_string('scheduleoptions', 'block_configurable_reports'));

        $mform->addElement(
            'advcheckbox',
            'enableschedule',
            get_string('enableschedule', 'block_configurable_reports'),
            get_string('enableschedule_desc', 'block_configurable_reports')
        );
        $mform->addHelpButton('enableschedule', 'enableschedule', 'block_configurable_reports');
        $mform->setDefault('enableschedule', 0);

        $frequencygroup = [
            $mform->createElement('select', 'frequency', null, \block_configurable_reports\local\schedule::frequency_options()),
            $mform->createElement('static', 'runatlabel', null, get_string('at', 'block_configurable_reports')),
            $mform->createElement('select', 'runat', null, \block_configurable_reports\local\schedule::hour_options()),
        ];
        $mform->addGroup($frequencygroup, 'frequencygroup', get_string('frequency', 'block_configurable_reports'), ' ', false);
        $mform->setDefault('frequency', 'daily');
        $mform->setDefault('runat', 0);
        $mform->disabledIf('frequencygroup', 'enableschedule', 'notchecked');

        $mform->addElement('text', 'emailto', get_string('emailto', 'block_configurable_reports'), ['size' => 70]);
        $mform->setType('emailto', PARAM_TEXT);
        $mform->addHelpButton('emailto', 'emailto', 'block_configurable_reports');
        $mform->disabledIf('emailto', 'enableschedule', 'notchecked');

        if (isset($this->_customdata['report']->id) && $this->_customdata['report']->id) {
            $mform->addElement('hidden', 'id', $this->_customdata['report']->id);
            $mform->setType('id', PARAM_INT);
        }
        if (!empty($adminmode)) {
            $mform->addElement(
                'text',
                'courseid',
                get_string('setcourseid', 'block_configurable_reports'),
                $this->_customdata['courseid']
            );
            $mform->setType('courseid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        }

        // Submit button string.
        $submitstring = get_string('add');
        if (!empty($this->_customdata['report']->id)) {
            $submitstring = get_string('update');
        }

        // Buttons.
        $this->add_action_buttons(true, $submitstring);
    }

    /**
     * The fixed width export needs a well formed JSON pattern to lay the file out.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['export_fixedwidth'])) {
            if (trim($data['fixedwidthpattern'] ?? '') === '') {
                $errors['fixedwidthpattern'] = get_string('required');
            } else {
                $pattern = json_decode($data['fixedwidthpattern']);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($pattern)) {
                    $errors['fixedwidthpattern'] = get_string('invalidjson', 'block_configurable_reports');
                }
            }
        }

        if (!empty($data['enableschedule'])) {
            $emails = preg_split('/[\s,;]+/', trim($data['emailto'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            if (!$emails) {
                $errors['emailto'] = get_string('required');
            } else {
                $invalid = array_filter($emails, fn($email) => !validate_email($email));
                if ($invalid) {
                    $errors['emailto'] = get_string('invalidemails', 'block_configurable_reports', implode(', ', $invalid));
                }
            }
        }

        return $errors;
    }

    /**
     * Used to reformat the data from the editor component.
     *
     * @return stdClass
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data !== null && isset($data->summary_editor)) {
            $data->summaryformat = $data->summary_editor['format'];
            $data->summary = $data->summary_editor['text'];
        }

        return $data;
    }

    /**
     * Load in existing data as form defaults.
     *
     * @param stdClass|array $defaultvalues object or array of default values.
     */
    public function set_data($defaultvalues) {
        if (!is_object($defaultvalues)) {
            // We need object for file_prepare_standard_editor.
            $defaultvalues = (object) $defaultvalues;
        }
        $defaultvalues = file_prepare_standard_editor($defaultvalues, 'summary', $this->get_editor_options());

        parent::set_data($defaultvalues);
    }

    /**
     * Get editor options for this form.
     *
     * @return array An array of options.
     */
    public function get_editor_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 0,
            'noclean' => false,
            'trusttext' => false,
        ];
    }

}
