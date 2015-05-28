<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/elisreports/type/table_report.class.php');

class sitewide_course_completion_report extends table_report {
    var $lang_file = 'rlreport_sitewide_course_completion';
    var $show_time_spent;
    var $show_total_grade;

    /** @var int $lastcrssetid to keep track of last courseset id */
    protected $lastcrssetid = 0;

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_COURSE;
    }

    /**
     * Specifies whether the current report is available
     *
     * @uses    $CFG
     * @uses    $DB
     * @return  boolean  True if the report is available, otherwise false
     */
    function is_available() {
        global $CFG, $DB;

        //we need the /local/elisprogram/ directory
        if (!file_exists($CFG->dirroot .'/local/elisprogram/lib/setup.php')) {
            return false;
        }

        //everything needed is present
        return true;
    }

    /**
     * Require any code that this report needs
     * (only called after is_available returns true)
     */
    function require_dependencies() {
        global $CFG;

        require_once($CFG->dirroot .'/local/elisprogram/lib/setup.php');

        //needed for constants that define db tables
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculum.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/user.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/student.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculumstudent.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/course.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/programcrsset.class.php');
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/crssetcourse.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/classmoodlecourse.class.php');
        require_once($CFG->dirroot .'/local/elisprogram/lib/data/pmclass.class.php');

        //needed to include for filters
        require_once($CFG->dirroot .'/local/eliscore/lib/filtering/date.php');
        require_once($CFG->dirroot .'/local/eliscore/lib/filtering/simpleselect.php');
        require_once($CFG->dirroot .'/local/eliscore/lib/filtering/radiobuttons.php');
        require_once($CFG->dirroot.'/local/eliscore/plugins/etl/etl.php');
    }

    /**
     * Specifies available report filters
     * (empty by default but can be implemented by child class)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     *
     * @return  array                The list of available filters
     */
    function get_filters($init_data = true) {
        $courses = array();
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        $course_list = course_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        foreach ($course_list as $course_obj) {
            $courses[$course_obj->id] = $course_obj->name;
        }

        $optcol1_choices = array('1' => get_string('yes', $this->lang_file),
                                 '0' => get_string('no', $this->lang_file),
                                );

        $optcol2_choices = array('1' => get_string('yes', $this->lang_file),
                                 '0' => get_string('no', $this->lang_file),
                                );

        return array(new generalized_filter_entry('sccc', 'crs', 'id',
                                                  get_string('filter_courses', $this->lang_file),
                                                  false, 'simpleselect',
                                                  array('choices'  => $courses,
                                                        'numeric'  => true,
                                                        'anyvalue' => get_string('allcourses', $this->lang_file),
                                                        'help'     => array('sitewide_course_completion_course',
                                                                        get_string('filter_courses', $this->lang_file),
                                                                        $this->lang_file)
                                                       )
                                                 ),
                     new generalized_filter_entry('sccts', '', '', '',
                                                  false, 'radiobuttons',
                                                  array('choices' => $optcol1_choices,
                                                        'checked' => '0',
                                                        'heading' => get_string('filter_users_time_spent', $this->lang_file),
                                                        'help'    => array('sitewide_course_completion_time',
                                                                        get_string('filter_users_time_spent', $this->lang_file),
                                                                        $this->lang_file)
                                                       )
                                                 ),
                     new generalized_filter_entry('sccg', '', '', '',
                                                  false, 'radiobuttons',
                                                  array('choices' => $optcol2_choices,
                                                        'checked' => '0',
                                                        'heading' => get_string('filter_users_grade', $this->lang_file),
                                                        'help'    => array('sitewide_course_completion_grade',
                                                                        get_string('filter_users_grade', $this->lang_file),
                                                                        $this->lang_file)
                                                       )
                                                 ),
                     new generalized_filter_entry('sccdr', 'clsenr', 'completetime', get_string('filter_date_range', $this->lang_file),
                             false, 'date',
                             array(
                                 'never_included' => true,
                                 'help' => array('sitewide_course_completion_date',
                                         get_string('filter_date_range', $this->lang_file),
                                         $this->lang_file)
                                 )
                             )
                     );
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {

        $sql_elements = "SELECT COUNT(id)
                         FROM {". coursecompletion::TABLE ."}
                         WHERE  courseid = crs.id
                         AND required = 1
                        ";

        $sql_percent = "SELECT COUNT(grd.id)
                        FROM {". coursecompletion::TABLE ."} crscomp
                        JOIN {". GRDTABLE ."} grd
                            ON grd.completionid = crscomp.id
                        WHERE crscomp.required = 1
                           AND grd.grade >= crscomp.completion_grade
                           AND grd.locked = 1
                           AND grd.userid = usr.id
                           AND grd.classid = cls.id
                       ";

        $sql_graded = "SELECT COUNT(grd.id)
                       FROM {". coursecompletion::TABLE ."} crscomp
                       JOIN {". GRDTABLE ."} grd
                           ON grd.completionid = crscomp.id
                       WHERE crscomp.required = 1
                           AND grd.locked = 1
                           AND grd.userid = usr.id
                           AND grd.classid = cls.id
                      ";

        $sql_total_elements = "SELECT COUNT(id)
                               FROM {". coursecompletion::TABLE ."}
                               WHERE courseid = crs.id
                              ";

        $columns = array(
                         new table_report_column('usr.lastname AS r_student',
                                 get_string('column_student', $this->lang_file),
                                 'cssstudent', 'left', true, true, true,
                                 array(php_report::$EXPORT_FORMAT_PDF, php_report::$EXPORT_FORMAT_EXCEL, php_report::$EXPORT_FORMAT_HTML)),
                         new table_report_column('usr.lastname AS lastname',
                                 get_string('column_student_lastname', $this->lang_file),
                                 'cssstudent', 'left', true, true, true,
                                 array(php_report::$EXPORT_FORMAT_CSV)),
                         new table_report_column('usr.firstname AS firstname',
                                 get_string('column_student_firstname', $this->lang_file),
                                 'cssstudent', 'left', true, true, true,
                                 array(php_report::$EXPORT_FORMAT_CSV)),
                         new table_report_column('clsenr.completestatusid != 0 AS r_status',
                                                 get_string('column_status', $this->lang_file),
                                                 'cssstatus', 'left', true
                                                ),
                         new table_report_column("({$sql_elements}) AS r_elements",
                                                 get_string('column_num_elements', $this->lang_file),
                                                 'csselements', 'left', true
                                                ),
                         new table_report_column("({$sql_percent}) AS r_percent",
                                                 get_string('column_percent_complete', $this->lang_file),
                                                 'csspercent', 'left', true
                                                ),
                         new table_report_column("({$sql_graded}) AS r_graded",
                                                 get_string('column_num_graded', $this->lang_file),
                                                 'cssgraded', 'left', true
                                                ),
                         new table_report_column("({$sql_total_elements}) AS r_total_elements",
                                                 get_string('column_total_elements', $this->lang_file),
                                                 'csstotalelements', 'left', true
                                                )
                        );

        // Optional column for time spent
        $show_array = php_report_filtering_get_active_filter_values(
                          $this->get_report_shortname(), 'sccts',$this->filter);
        if ($show_array) {
            $this->show_time_spent = ($show_array[0]['value'] == 1) ? true : false;
            if ($this->show_time_spent) {
                $sql_total_duration = 'SELECT SUM(duration)
                                         FROM {'.ETL_TABLE.'} etl
                                        WHERE etl.userid = mdlusr.id AND etl.courseid = clsmdl.moodlecourseid';
                $columns[] = new table_report_column("({$sql_total_duration}) AS r_timespent",
                                                     get_string('column_time_spent', $this->lang_file),
                                                     'csstimespent', 'left', true
                                                    );
            }
        }

        // Optional column for total grade
        $show_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                    'sccg',$this->filter);
        if ($show_array) {
            $this->show_total_grade = ($show_array[0]['value'] == 1) ? true : false;
            if ($this->show_total_grade) {
                $columns[] = new table_report_column('(gg.finalgrade / gi.grademax * 100) AS r_total_grade',
                                                     get_string('column_total_grade', $this->lang_file),
                                                     'csstotalgrade', 'left', true
                                                    );
            }
        }

        return $columns;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  A string representing the default sort field
     */
    function get_default_sort_field() {
        return 'r_status';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  A string representing sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @return  array List of objects containing grouping id, field names, display labels and sort order
     */
     function get_grouping_fields() {
         return array(
                 new table_report_grouping('curriculum_name','cur.name', get_string('grouping_curriculum', $this->lang_file).': ',
                         'ASC',array(),'above','isnull ASC,cur.name ASC'),
                 new table_report_grouping('courseset_name', 'ccs.name', get_string('grouping_courseset', $this->lang_file), 'ASC'),
                 new table_report_grouping('course_name','crs.name', get_string('grouping_course', $this->lang_file).': ', 'ASC'),
                 new table_report_grouping('class_name','cls.idnumber', get_string('grouping_class', $this->lang_file).': ', 'ASC')
         );
     }

    /**
     * Transforms a heading element displayed above the columns into a listing of such heading elements
     *
     * @param   string array           $grouping_current  Mapping of field names to current values in the grouping
     * @param   table_report_grouping  $grouping          Object containing all info about the current level of grouping
     *                                                    being handled
     * @param   stdClass               $datum             The most recent record encountered
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  string array                              Set of text entries to display
     */
    function transform_grouping_header_label($grouping_current, $grouping, $datum, $export_format) {
        $result = array();
        if ($grouping->id == 'courseset_name') {
            $coursesets = array();
            if (!empty($datum->prgid) && !empty($datum->courseset_name)) {
                $prg = new curriculum($datum->prgid);
                if ($prg) {
                    foreach ($prg->crssets as $prgcrsset) {
                        $crssetcrses = crssetcourse::find(new AND_filter(array(new field_filter('crssetid', $prgcrsset->crssetid), new field_filter('courseid', $datum->courseid))));
                        if ($crssetcrses && $crssetcrses->valid()) {
                            foreach ($crssetcrses as $crssetcrs) {
                                if (!isset($coursesets[$crssetcrs->crssetid]) && ($crsset = new courseset($crssetcrs->crssetid))) {
                                    $crsset->load();
                                    $coursesets[$crsset->id] = $crsset->name;
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($coursesets)) {
                $this->lastcrssetid = $datum->crssetid;
                $datum->courseset_name = '';
                foreach ($coursesets as $crssetname) {
                    if (!empty($datum->courseset_name)) {
                        $this->lastcrssetid = -1; // multiple coursesets
                        $datum->courseset_name .= ', ';
                    }
                    $datum->courseset_name .= $crssetname;
                }
            } else {
                $this->lastcrssetid = 0;
                $datum->courseset_name = get_string('na', $this->lang_file);
            }
            $result[] = $this->add_grouping_header(get_string('grouping_courseset', $this->lang_file).': ', $datum->courseset_name, $export_format);
        } else if ($grouping->id == 'course_name') {
            if (!empty($datum->crssetid) && $this->lastcrssetid) {
                if (!($crsset = new courseset($datum->crssetid)) || $crsset->count_courses(new field_filter('courseid', $datum->courseid)) <= 0) {
                    $this->lastcrssetid = 0;
                    $result[] = $this->add_grouping_header(get_string('grouping_courseset', $this->lang_file).': ', get_string('na', $this->lang_file), $export_format);
                }
            }
            $result[] = $this->add_grouping_header($grouping->label, $grouping_current[$grouping->field], $export_format);
        } else {
            $result[] = $this->add_grouping_header($grouping->label, $grouping_current[$grouping->field], $export_format);
        }
        return $result;
    }

    /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return 'clsenr.id';
    }

    /**
     * Takes a record and transforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {
        if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
            $user = new stdClass;
            $user->firstname = $record->firstname;
            $user->lastname = $record->r_student;
            $record->r_student = php_report::fullname($user);
        }

        if (empty($record->r_elements)) {
            $record->r_elements = '0';
            $record->r_percent = '0';
        } else {
            //$percentage = round($record->r_complete / $record->r_elements * 10000) / 100;
            $percentage = round($record->r_percent / $record->r_elements * 10000) / 100;
            $percentage = ($percentage > 100) ? 100 : $percentage;
            $record->r_percent = $percentage;
        }
        if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
            $record->r_percent .= get_string('percent_symbol', $this->lang_file);
        }

        if (empty($record->r_graded)) {
            $record->r_graded = '0';
        }

        if (empty($record->r_total_elements)) {
            $record->r_total_elements = '0';
        }

        $secs_in_hour = 3600;
        if (!empty($record->r_timespent)) {
            if ($record->r_timespent > 0 &&
                $record->r_timespent < $secs_in_hour) {
                $timespent = 1;
                $record->r_timespent =
                        ($export_format == php_report::$EXPORT_FORMAT_HTML)
                        ? '&lt;1' : '1';
             } else {
                $timespent = floor($record->r_timespent / $secs_in_hour);
                $record->r_timespent = $timespent;
            }
        } else {
            $timespent = 0;
            $record->r_timespent = "0";
        }

        if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
            $record->r_timespent .= ' '.
                        get_string(($timespent == 1) ? 'hour' : 'hours',
                                   $this->lang_file);
        }

        // ELIS-4916: now using ELIS grade!
        if (!empty($record->elisgrade)) {
            $record->r_total_grade = pm_display_grade($record->elisgrade);
            if (is_numeric($record->r_total_grade) && $export_format != php_report::$EXPORT_FORMAT_CSV) {
                $record->r_total_grade .= get_string('percent_symbol', $this->lang_file);
            }
        } else {
            $record->r_total_grade = get_string('na', $this->lang_file);
        }

        $record->r_status = ($record->r_status == 1)
                          ? get_string('complete', $this->lang_file)
                          : get_string('incomplete', $this->lang_file);

        $record->curriculum_name = ($record->curriculum_name == '')
                                 ? get_string('na', $this->lang_file)
                                 : $record->curriculum_name;

        return $record;
    }

    /**
     * Specifies an SQL statement that will produce the required report
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement with optional params
     */
    function get_report_sql($columns) {
        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);

        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('crs.id', 'course');
        $params = array();
        $where = array();
        $filter_obj = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_obj->get_sql(false, 'crs', SQL_PARAMS_NAMED);
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = $filter_sql['where_parameters'];
        }
        if (empty(elis::$config->local_elisprogram->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }

        $firstname = 'usr.firstname AS firstname';
        if (stripos($columns, $firstname) === FALSE) {
            $columns .= ", {$firstname}";
        }
        $sql = "SELECT DISTINCT {$columns}, cur.id IS NULL AS isnull, clsenr.grade AS elisgrade, crs.id AS courseid, ccs.id AS crssetid, cur.id AS prgid
                FROM {".pmclass::TABLE."} cls
                JOIN {".course::TABLE."} crs ON crs.id = cls.courseid
                JOIN {".student::TABLE."} clsenr ON clsenr.classid = cls.id
                JOIN {".user::TABLE."} usr ON usr.id = clsenr.userid
           LEFT JOIN ({".curriculumstudent::TABLE."} curass
                      JOIN {".curriculum::TABLE."} cur
                          ON cur.id = curass.curriculumid
                      LEFT JOIN {".curriculumcourse::TABLE."} curcrs ON curcrs.curriculumid = cur.id
                      LEFT JOIN {".programcrsset::TABLE."} pcs ON pcs.prgid = cur.id
                      LEFT JOIN {".crssetcourse::TABLE."} csc ON csc.crssetid = pcs.crssetid
                      LEFT JOIN {".courseset::TABLE."} ccs ON ccs.id = csc.crssetid
                     ) ON curass.userid = usr.id
                     AND (curcrs.courseid = crs.id OR csc.courseid = crs.id)
           LEFT JOIN {".classmoodlecourse::TABLE."} clsmdl ON clsmdl.classid = cls.id
                     AND clsmdl.moodlecourseid > 0
           LEFT JOIN {user} mdlusr ON mdlusr.idnumber = usr.idnumber
           ";

        // Optional query segment for total grade
        if ($this->show_total_grade) {
            $sql .= "LEFT JOIN {grade_items} gi
                         ON gi.courseid = clsmdl.moodlecourseid
                         AND gi.itemtype = 'course'
                     LEFT JOIN {grade_grades} gg
                         ON gg.userid = mdlusr.id
                         AND gg.itemid = gi.id
                    ";
        }

        if (!empty($where)) {
            $sql .= 'WHERE '. implode(' AND ', $where);
        }

        return array($sql, $params);
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     * and php_report:view capability
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //Check for report view capability
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one course context
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(169, 245, 173);
    }

    /**
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(
                array(84, 141, 212),
                array(112, 160, 219),
                array(141, 179, 226),
                array(198, 217, 241));
    }

    /**
     * Overload method to initialize report groupings
     * used here to also init report data!
     *
     * @return parent::initialize_groupings();
     */
    function initialize_groupings() {
        $show_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                    'sccts', $this->filter);
        if (isset($show_array[0]['value'])) {
            $this->show_time_spent = ($show_array[0]['value'] == 1) ? true : false;
        }

        $show_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                    'sccg', $this->filter);
        if (isset($show_array[0]['value'])) {
            $this->show_total_grade = ($show_array[0]['value'] == 1) ? true : false;
        }

        return parent::initialize_groupings();
    }
}

