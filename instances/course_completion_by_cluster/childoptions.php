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

/**
 * Generate a JSON data set containing all the courses belonging to the specified courseset.
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
require_once($CFG->dirroot.'/local/elisprogram/lib/data/crssetcourse.class.php');

if (!isloggedin() || isguestuser()) {
    mtrace("ERROR: must be logged in!");
    exit;
}

$ids = array();
if (array_key_exists('id', $_REQUEST)) {
    $dirtyids = $_REQUEST['id'];
    if (is_array($dirtyids)) {
        foreach ($dirtyids as $dirty) {
            $ids[] = clean_param($dirty, PARAM_INT);
        }
    } else {
        $ids[] = clean_param($dirtyids, PARAM_INT);
    }
    foreach ($ids as $key => $id) {
        if ($id == 0) {
            unset($ids[$key]);
        }
    }
}

// Must have blank value as the default here (instead of zero) or it breaks the report.
$choices = array(array('', get_string('anyvalue', 'filters')));
list($inoreq, $params) = !empty($ids) ? $DB->get_in_or_equal($ids) : array(false, array());
$additionalfilters = '';
$contexts = get_contexts_by_capability_for_user('course', 'local/elisreports:view', $USER->id);
$filterobj = $contexts->get_filter('id', 'course');
$filtersql = $filterobj->get_sql(false, 'crs');
if (isset($filtersql['where'])) {
    $additionalfilters = $filtersql['where'];
    $params = array_merge($params, $filtersql['where_parameters']);
}
if (!empty($inoreq)) {
    $sql = 'SELECT DISTINCT crs.id AS courseid, crs.name AS coursename
              FROM {'.courseset::TABLE.'} ccs
              JOIN {'.crssetcourse::TABLE.'} csc ON csc.crssetid = ccs.id
              JOIN {'.course::TABLE."} crs ON crs.id = csc.courseid
             WHERE ccs.id $inoreq
                   $additionalfilters
          ORDER BY coursename ASC";
} else {
    $sql = 'SELECT DISTINCT crs.id AS courseid, crs.name AS coursename
              FROM {'.course::TABLE.'} crs '.
            (!empty($additionalfilters) ? "WHERE $additionalfilters" : '').'
          ORDER BY coursename ASC';
}
$records = $DB->get_recordset_sql($sql, $params);
if ($records && $records->valid()) {
    foreach ($records as $record) {
        $crsname = (strlen($record->coursename) > 80) ? substr($record->coursename, 0, 80).'...' : $record->coursename;
        $choices[] = array($record->courseid, $crsname);
    }
}
unset($records);
echo json_encode($choices);
