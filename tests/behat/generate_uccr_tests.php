<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2017 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2017 Remote-Learner.net Inc (http://www.remote-learner.net)
 */

// Script to generate behat test cases for UCC/UCCD Reports.

die("This script is intended for development. If you are a developer, comment this line to continue.");

$featurefilebase = './uccreport.feature.base';
if (!file_exists($featurefilebase)) {
    die("Error: Feature file base '{$featurefilebase}' not found!");
}
$featurefiletemplate = './uccreport%d.feature';

$uccfilters = [
    'filter-autoc_id' => 0,
    'filter-uid_dropdown' => 0,
    'filter-ccc-curriculum-name' => 1,
    'filter-ccc-courseset-name' => 1,
    'filter-ccc-course-name' => 1,
    'filter-ccc-class-idnumber' => 1,
    'filter-completionstatus' => 0,
    'filter-summarycolumns:user_email' => 0,
    'filter-summarycolumns:cur_name' => 0,
    'filter-summarycolumns:cur_timecompleted' => 0,
    'filter-summarycolumns:cur_certificate' => 0,
    'filter-detailcolumns:cur_name' => 0,
    'filter-detailcolumns:class_role' => 0
];

$filtervalues = [
    'filter-autoc_id' => ['', 'Test%20Elis9010a', 'Test%20Elis9010b'],
    'filter-uid_dropdown' => ['', '{local_elisprogram_uset}+1'],
    'filter-ccc-curriculum-name' => ['', '{local_elisprogram_pgm}+1', '{local_elisprogram_pgm}+2'],
    'filter-ccc-courseset-name' => [''],
    'filter-ccc-course-name' => ['', '{local_elisprogram_crs}+2', '{local_elisprogram_crs}+3',
            '{local_elisprogram_crs}+6', '{local_elisprogram_crs}+7'],
    'filter-ccc-class-idnumber' => ['', '{local_elisprogram_cls}+1', '{local_elisprogram_cls}+2',
            '{local_elisprogram_cls}+11', '{local_elisprogram_cls}+12'],
    'filter-completionstatus' => ['', 0, 1, 2, 9999],
    'filter-summarycolumns' => [0, 1],
    'filter-detailcolumns' => [0, 1]
];

/**
 * Function to return filter and setting.
 * @param string $filter filter name
 * @param bool &$inc true to advance filter, set to false once reset.
 * @return string "filter=value"
 */
function getfilterwithval($filter, &$inc) {
    global $filtervalues;
    static $filterstate = [];
    $filterbase = explode(':', $filter);
    $valarray = $filtervalues[$filterbase[0]];
    if (!isset($filterstate[$filter])) {
        $filterstate[$filter] = $valarray[0];
    }
    $ret = '';
    $ret = "{$filter}={$filterstate[$filter]}";
    // Special case of auto complete filter requires 2nd param!
    if ($filter == 'filter-autoc_id') {
        $ret .= "&filter-autoc_textentry={$filterstate[$filter]}";
    }
    if ($inc) {
        $idx = array_search($filterstate[$filter], $valarray, true) + 1;
        if ($idx >= count($valarray)) {
            $idx = 0;
            $inc = false;
        }
        $filterstate[$filter] = $valarray[$idx];
    }
    return $ret;
}

$fileno = 1;
$recs = 0;
$urlbase = '/local/elisreports/render_report_page.php?report=user_class_completion&filter-uid_usingdropdown=1'; // TBD?
$uccfilterkeys = array_keys($uccfilters);
$lasturl = '';
$fh = null;
foreach ($uccfilters as $curfilter => $curmulti) {
    $inc = 1;
    do {
        $arg = getfilterwithval($curfilter, $inc);
        if ($arg == '') {
           continue;
        }
        $url1 = "{$urlbase}&{$arg}";
        foreach ($uccfilters as $otherfilter => $othermulti) {
            if (array_search($otherfilter, $uccfilterkeys) <= array_search($curfilter, $uccfilterkeys)) {
                continue;
            }
            $inc2 = 1;
            do {
                $arg = getfilterwithval($otherfilter, $inc2);
                if ($arg == '') {
                    continue;
                }
                $url = "{$url1}&{$arg}";
                foreach ($uccfilters as $nextfilter => $nextmulti) {
                    if ($nextfilter == $otherfilter || $nextfilter == $curfilter) {
                        continue;
                    }
                    $inc3 = 0;
                    $arg = getfilterwithval($nextfilter, $inc3);
                    if ($arg != '') {
                        $url .= "&{$arg}";
                    }
                    // echo "{$curfilter};{$otherfilter};{$nextfilter}\n";
                }
                if (!($recs % 100)) {
                    // New file.
                    if ($fileno > 1) {
                       echo "\n";
                    }
                    if ($fh) {
                        fwrite($fh, "\n# This file auto-generated - do not edit!");
                        fclose($fh);
                    }
                    $fname = sprintf($featurefiletemplate, $fileno++);
                    copy($featurefilebase, $fname);
                    $fh = fopen($fname, 'a');
                    if ($fh == false) {
                        die("Error opening feature file: {$fname}");
                    }
                }
                if ($lasturl != $url) {
                  /*
                    fwrite($fh, "    Scenario: UCCR ".(($recs % 100) + 1).
                             "\n        Then I visit all details reports of the following UCC report URLs:\n          | reporturl |\n          | {$url} |\n\n");
                  */
                    fwrite($fh, "          | {$url} |\n");
                    $lasturl = $url;
                    echo '.';
                    ++$recs;
                }
            } while ($inc2);
        }
    } while ($inc);
}

echo "\nDone!\n";
if ($fh) {
    fwrite($fh, "\n# This file auto-generated - do not edit!");
    fclose($fh);
}
