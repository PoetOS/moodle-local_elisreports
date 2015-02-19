<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote Learner.net Inc http://www.remote-learner.net
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once ($CFG->libdir.'/formslib.php');
require_once ($CFG->dirroot.'/local/elisreports/sharedlib.php');
require_once ($CFG->dirroot.'/local/elisreports/parameter_form.class.php');
require_once ($CFG->dirroot.'/local/eliscore/lib/form/scheduling.php');
require_once ($CFG->dirroot.'/local/eliscore/lib/form/timeselector.php');

class scheduling_form_step_label extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        if (isset($workflow->id)) {
            $mform->addElement('hidden', '_wfid', $workflow->id);
            $mform->setType('_wfid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'report', $page->required_param('report', PARAM_FILE));
            $mform->setType('report', PARAM_FILE);
        }

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_LABEL);
        $mform->setType('_step', PARAM_TEXT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('label_description', 'local_elisreports')).'</h2>');

        $mform->addElement('text', 'label', get_string('label', 'local_elisreports'));
        $mform->setType('label', PARAM_CLEAN);
        $mform->addRule('label', get_string('required_field', 'local_eliscore', get_string('label', 'local_elisreports')), 'required', null, 'server');
        // ^ELIS-3316: server validation so cancel/submit will work

        $options=array('cols'=>'30');
        $mform->addElement('textarea', 'description', get_string('description'),$options);
        $mform->setType('description', PARAM_TEXT);

        workflowpage::add_navigation_buttons($mform);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_parameters extends parameter_form {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        if (isset($this->_customdata['workflow'])) {
            $workflow = $this->_customdata['workflow'];
        } else {
            $page = $this->_customdata['page'];
            $workflow = $page->workflow;
        }

        $mform->addElement('hidden', '_wfid', $workflow->id);
        $mform->setType('_wfid', PARAM_INT);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_PARAMETERS);
        $mform->setType('_step', PARAM_TEXT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('parameters_description', 'local_elisreports')).'</h2>');

        $mform->addElement('header', 'newfilter', get_string('newfilter','filters'));
    }

    function definition_after_data() {
        $mform =& $this->_form;

        if (isset($this->_customdata['filterobject'])) {
            //filter object was passed, because the report uses filters
            $filter_object = $this->_customdata['filterobject'];

            //handle adding of UI fields for secondary filterings
            if (!empty($filter_object->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $filtering) {
                    $this->add_filtering_elements($filtering);
                }
            }

            //add all filter form elements to this form for the main filtering
            $this->add_filtering_elements($filter_object);

            //add any required field rules
            foreach ($filter_object->_fields as $key=>$fields) {
                if (!empty($fields->_isrequired)) {
                    $required_rule_method = $filter_object->reportname . '_report::apply_filter_required_rule';

                    if (is_callable($required_rule_method)) {
                        // We have a custom requirement rule so let's use it
                        $mform = call_user_func_array($required_rule_method, array($mform,$key,$fields));
                    } elseif ($mform->elementExists($key)) {
                        // Basic requirement rule
                        $mform->addRule($key, get_string('required'), 'required', null, 'client');
                    }
                }
            }
        } else {
            //report does not use filters
            $mform->addElement('static', 'noparams', '', get_string('label_no_parameters', 'local_elisreports'));
        }
        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_SCHEDULE);
    }

    /**
     * Performs the server side validation
     * @access    public
     * @since     1.0
     * @return    boolean   true if no error found
     */
    function validate() {
        return $this->_form->validate();
    }

    /**
     * Obtains the errors currently associated with this form
     *
     * @return  array  The errors
     */
    function get_errors() {
        if (empty($this->_form->_errors)) {
            return array();
        }

        return $this->_form->_errors;
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

/**
 * Form that displays available export formats during scheduling,
 * based on the applicable report definition
 */
class scheduling_form_step_format extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        $mform->addElement('hidden', '_wfid', $workflow->id);
        $mform->setType('_wfid', PARAM_INT);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_FORMAT);
        $mform->setType('_step', PARAM_TEXT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('format_description', 'local_elisreports')).'</h2>');

        $radioarray = array();

        //obtain the actual report instance so we can check its export formats
        $data = unserialize($workflow->data);
        $report_shortname = $data['report'];
        $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);

        //global list of all available export formats
        $allowable_export_formats = php_report::get_allowable_export_formats();
        //export formats supported by the report we are scheduling
        $export_formats = $report_instance->get_export_formats();

        //add an export option for each applicable format
        if ($report_instance->can_view_report()) {
            foreach ($allowable_export_formats as $allowable_export_format) {
                if (in_array($allowable_export_format, $export_formats)) {
                    $radioarray[] = $mform->createElement('radio', 'format', '', get_string($allowable_export_format, 'local_elisreports'), $allowable_export_format);
                }
            }
        }

        $mform->addGroup($radioarray, 'format', get_string('format'), '<br/>', false);
        $mform->addRule('format', get_string('required_field', 'local_eliscore', get_string('format')), 'required', null, 'client');
        $mform->setDefault('format', 'csv');

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_PARAMETERS);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_recipients extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        $mform->addElement('hidden', '_wfid', $workflow->id);
        $mform->setType('_wfid', PARAM_INT);
        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_RECIPIENTS);
        $mform->setType('_step', PARAM_TEXT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('html', '<h2>'. htmlspecialchars(get_string('recipients_description', 'local_elisreports')). '</h2>');
        $mform->addElement('html', '<label for="recipients"><b>'. htmlspecialchars(get_string('enter_emails', 'local_elisreports')) .'</b><br/><i>'. htmlspecialchars(get_string('email_instructions', 'local_elisreports')) .'</i></label>');

        $mform->addElement('text', 'recipients', get_string('recipientslist'), array('size' => 60));
        $mform->setType('recipients', PARAM_TEXT);
        $mform->addRule('recipients', get_string('required_field', 'local_eliscore', get_string('recipientslist')), 'required', null, 'server');
        // ^ELIS-3316: server validation so cancel/submit will work

        $mform->addElement('htmleditor', 'message', '<div class="php_report_bold_header">'.htmlspecialchars(get_string('message', 'local_elisreports')).'</div>');

        // ELIS-8981: link instead of attachment if over limit.
        $choices = array(
            0 => get_string('attachlimit_alwayslink', 'local_elisreports'),
            1 => '1M',
            2 => '2M',
            5 => '5M',
            8 => '8M',
            10 => '10M',
            15 => '15M',
            20 => '20M',
            25 => '25M',
            40 => '40M',
            50 => '50M',
            60 => '60M',
            75 => '75M',
            100 => '100M',
            150 => '150M',
            200 => '200M',
            500 => '500M',
            1000 => '1G',
            2000 => '2G',
            9999 => get_string('attachlimit_disable', 'local_elisreports')
        );
        $mform->addElement('select', 'attachlimit', get_string('attachlimit', 'local_elisreports'), $choices);
        $mform->setDefault('attachlimit', 9999);

        $group = array();
        $group[] = $mform->createElement('radio', 'canviewattachment', '', get_string('canviewattachment_anyonewithlink', 'local_elisreports'), 0);
        $group[] = $mform->createElement('radio', 'canviewattachment', '', get_string('canviewattachment_linkandpermissions', 'local_elisreports'), 1, array('disabled' => 'disabled'));
        $mform->addGroup($group, 'canviewattachment', get_string('whocanviewattachment', 'local_elisreports'), '<br/>', false);
        $mform->setDefault('canviewattachment', 0);

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_FORMAT);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_confirm extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;
        $page = $this->_customdata['page'];
        $workflow = $page->workflow;
        $data = $workflow->unserialize_data();

        // Configure the schedule display
        $schedule_string = '';
        $once = '1';

        // Replace timezone with proper text
        $tz = $data['timezone'];
        $timezone = usertimezone($tz);

        debug_error_log("/local/elisreports/form/scheduling.php::scheduling_form_step_confirm::definition() enddate = {$data['schedule']['enddate']}, timezone = {$tz} ({$timezone})");
        // Set up for simple recurrence
        if ($data['recurrencetype'] == elisschedulingworkflowtrait::$RECURRENCE_SIMPLE) {
            if ($data['schedule']['runsremaining'] == '' && $data['schedule']['enddate'] == '') {
                $schedule_string = get_string('indefinitely', 'local_eliscore');
            } elseif ($data['schedule']['enddate'] != ''){
                $enddate = userdate($data['schedule']['enddate'] + DAYSECS - 1, '', $tz);
                // 2nd param to userdate() was: get_string('strftimedaydate')
                $schedule_string = get_string('simple_until', 'local_elisreports', $enddate);
            } else {
                // Frequency schedule
                $a = new stdClass;
                $a->num = $data['schedule']['runsremaining'];
                $a->freq = $data['schedule']['frequency'];
                $a->freqtype = $data['schedule']['frequencytype'];
                $schedule_string = get_string('simple_freq', 'local_elisreports', $a);

            }
        } else {
            // calendar schedule
            $min = str_pad($data['schedule']['minute'],2,'0',STR_PAD_LEFT);
            $hour = $data['schedule']['hour'];
            $schedule_string .= get_string('at_time','local_elisreports',
                                        //   "{$hour}:{$min}");
                                         "{$hour}:{$min} ({$timezone})");
            // days
            if ($data['schedule']['dayofweek'] == '*' && $data['schedule']['day'] == '*') {
                $schedule_string .= get_string('every_day', 'local_elisreports');
            } elseif ($data['schedule']['day'] == '*') {
                //Weekdays specified
                $days_array = array ('1' => get_string('mon', 'calendar'),
                                     '2' => get_string('tue', 'calendar'),
                                     '3' => get_string('wed', 'calendar'),
                                     '4' => get_string('thu', 'calendar'),
                                     '5' => get_string('fri', 'calendar'),
                                     '6' => get_string('sat', 'calendar'),
                                     '7' => get_string('sun', 'calendar')
                                     );
                $days_selected_array = explode(',',$data['schedule']['dayofweek']);
                $days_string = '';
                $count = 0;
                foreach ($days_selected_array as $weekday) {
                    if ($count > 0) {
                        $days_string .= ', ';
                    }
                    $days_string .= $days_array[$weekday];
                    $count++;
                }
                //Replace last , with the word and
                $days_string = strrev(preg_replace('/,/',strrev(get_string('and','local_elisreports')),strrev($days_string), $once));
                $schedule_string .= get_string('on_days', 'local_elisreports',$days_string);
            } else {
                //Specific days of the month
                $days_string = strrev(preg_replace('/,/',strrev(get_string('and','local_elisreports')),strrev($data['schedule']['day']), $once));
                $schedule_string .= get_string('month_days', 'local_eliscore', $days_string);

            }
            // months
            if ($data['schedule']['month'] == '1,2,3,4,5,6,7,8,9,10,11,12' || $data['schedule']['month'] == '*') {
                $months_string = get_string('every_month', 'local_elisreports');
                $data['schedule']['month'] = '1,2,3,4,5,6,7,8,9,10,11,12';
            } else {
                $months_array = array ('1' => strftime('%b', mktime(0, 0, 0, 1, 1)),
                                       '2' => strftime('%b', mktime(0, 0, 0, 2, 1)),
                                       '3' => strftime('%b', mktime(0, 0, 0, 3, 1)),
                                       '4' => strftime('%b', mktime(0, 0, 0, 4, 1)),
                                       '5' => strftime('%b', mktime(0, 0, 0, 5, 1)),
                                       '6' => strftime('%b', mktime(0, 0, 0, 6, 1)),
                                       '7' => strftime('%b', mktime(0, 0, 0, 7, 1)),
                                       '8' => strftime('%b', mktime(0, 0, 0, 8, 1)),
                                       '9' => strftime('%b', mktime(0, 0, 0, 9, 1)),
                                      '10' => strftime('%b', mktime(0, 0, 0, 10, 1)),
                                      '11' => strftime('%b', mktime(0, 0, 0, 11, 1)),
                                      '12' => strftime('%b', mktime(0, 0, 0, 12, 1))
                                       );
                $months_selected_array = explode(',', $data['schedule']['month']);
                $months_string = '';
                $count = 0;
                foreach ($months_selected_array as $month) {
                    if ($count > 0) {
                        $months_string .= ', ';
                    }
                    $months_string .= $months_array[$month];
                    $count++;
                }
                //Replace last , with the word and
                $months_string = strrev(preg_replace('/,/', strrev(get_string('and','local_elisreports')), strrev($months_string), $once));
            }
            $schedule_string .= get_string('for_month', 'local_elisreports',$months_string);

            //Include end date if selected
            if (isset($data['schedule']['enddate']) && $data['schedule']['enddate'] != '') {
                $schedule_string .= get_string('until_date', 'local_elisreports',
                                        userdate($data['schedule']['enddate'] + DAYSECS - 1, '', $tz)); // '' was: get_string('strftimedaydate')
            } else {
                $schedule_string .= get_string('until_indefinitely',
                                               'local_elisreports');
            }

        }

        // Create the param_string for selected parameters and their values
        $param_string = '';

        if (isset($this->_customdata['filterobject']) && isset($data['parameters'])) {
            $report = $page->workflow->get_report_instance();

            // Configure the parameter display
            $parameters = $data['parameters'];

            // Use this object to set up our parameter setting
            $filter_object = $this->_customdata['filterobject'];
            $filter_object_fields = $filter_object->_fields;
            // Merge in any secondary filterings here...
            if (isset($this->_customdata['filterobject']->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $secondary_filtering) {
                    $filter_object_fields = array_merge($filter_object_fields, $secondary_filtering->_fields);
                }
            }

            if (!empty($filter_object_fields)) {
                $fields = $filter_object_fields;
                // Create empty filtering array to be used following...
                $filtering_array = array();
                foreach ($parameters as $pname=>$pvalue) {
                    if (strpos($pname, '_') !== FALSE) {
                        //multi-element group
                        $parts = explode('_', $pname);
                        $group_name = $parts[0];
                    } else {
                        $group_name = '';
                    }
                    if (isset($fields[$pname])) {
                        $sub_data = $fields[$pname]->check_data($parameters);
                        if ($sub_data === false) {
                            continue; // nothing new
                        }
                    } elseif (isset($fields[$group_name])) {
                        $sub_data = $fields[$group_name]->check_data($parameters);
                        if ($sub_data === false) {
                            continue; // nothing new
                        }
                        $pname = $group_name;
                    } else {
                       continue;
                    }
                    //update filter object with check_data contents
                    if (!isset($filtering_array[$pname])) {
                        $filtering_array[$pname][] = $sub_data;
                    }
                }
                //Now get the description etc.
                $count = 0;
                foreach ($filtering_array as $fname=>$datas) {
                    if (!array_key_exists($fname, $filter_object_fields)) {
                        continue; // filter not used
                    }
                    $field = $fields[$fname];
                    foreach($datas as $i=>$sub_data) {
                        $description = $field->get_label($sub_data);
                        if ($count > 0) {
                            $param_string .= '<br>';
                        }
                        $param_string .= $description;
                        $count++;
                    }
                }
            }
        }

        if (isset($this->_customdata['secondary_filterings'])) {
            // Use this object to set up our parameter setting
            $filter_object = $this->_customdata['secondary_filterings'];
            //add additional data from secondary filters
            foreach ($filter_object as $secondary_filtering) {
                //recurse for this filter
                $temp_result = php_report_filtering_get_per_filter_data($secondary_filtering, $form_data);

                //merge data into result
                if (!empty($temp_result)) {
                    foreach ($temp_result as $key => $value) {
                        //each filter should have only been shown once (see form for details)
                        if (!isset($per_filter_data[$key])) {
                            $per_filter_data[$key] = $value;
                        }
                    }
                }
            }
        }

        if ($param_string == '') {
            //report does not use filters
            $param_string = get_string('label_no_parameters', 'local_elisreports');
        }

        // Set proper date display or NA for end date
        if (isset($data['schedule']['enddate']) && $data['schedule']['enddate'] != null) {
            $enddate = userdate($data['schedule']['enddate'],
                                get_string('strftimedaydate'), $tz);
        } else {
            $enddate = get_string('na','local_elisreports');
        }

        $mform->addElement('hidden', '_wfid', $workflow->id);
        $mform->setType('_wfid', PARAM_INT);

        $mform->addElement('static', 'label', get_string('label', 'local_elisreports').':', htmlspecialchars($data['label']));
        $mform->addElement('static', 'description', get_string('description').':', htmlspecialchars($data['description']));
        $mform->addElement('static', 'timezone', get_string('timezone').':', $timezone);
        $mform->addElement('static', 'startdate', get_string('startdate', 'local_eliscore').':', userdate($data['startdate'],get_string('strftimedaydate'), $tz));
        $mform->addElement('static', 'enddate', get_string('enddate', 'local_eliscore').':', $enddate);
        $mform->addElement('static', 'recurrencetype', get_string('recurrencetype', 'local_eliscore').':', htmlspecialchars($data['recurrencetype']));
        $mform->addElement('static', 'schedule', get_string('schedule').':', $schedule_string);
        $mform->addElement('static', 'parameters', get_string('parameters', 'local_elisreports').':', $param_string);
        $mform->addElement('static', 'format', get_string('format').':', get_string($data['format'], 'local_elisreports'));
        $mform->addElement('static', 'recipients', get_string('recipientslist').':', htmlspecialchars($data['recipients']));
        $mform->addElement('static', 'message', get_string('message', 'message').':', $data['message']);
        if (empty($data['attachlimit'])) {
            $attachlimit = get_string('attachlimit_alwayslink', 'local_elisreports');
        } else if ($data['attachlimit'] >= 9999) {
            $attachlimit = get_string('attachlimit_disable', 'local_elisreports');
        } else {
            $attachlimit = ($data['attachlimit'] >= 1000) ? ((string)($data['attachlimit']/1000).'G') : ((string)$data['attachlimit'].'M');
        }
        $mform->addElement('static', 'attachlimit', get_string('attachlimit', 'local_elisreports').':', $attachlimit);
        $mform->addElement('static', 'canviewattachment', get_string('whocanviewattachment', 'local_elisreports').':', get_string('canviewattachment_anyonewithlink', 'local_elisreports'));

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_RECIPIENTS, workflow::STEP_FINISH);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

