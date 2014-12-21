<?php

require_once(__DIR__.'/../../../../lib/behat/behat_files.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Behat\Context\SnippetAcceptingContext,
    Behat\Gherkin\Node\PyStringNode as PyStringNode,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Mink\Exception\DriverException as DriverException,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

class behat_local_elisreports extends behat_files implements SnippetAcceptingContext {
    public $detailsreports = [];

    /**
     * @Given /^the following ELIS users exist2:$/
     */
    public function theFollowingElisUsersExist2(TableNode $table) {
        global $CFG;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/user.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $user = new user();
            $user->idnumber = $datarow['idnumber'];
            $user->username = $datarow['username'];
            $user->email = $datarow['idnumber'].'@example.com';
            $user->firstname = empty($datarow['firstname']) ? 'Student' : $datarow['firstname'];
            $user->lastname = empty($datarow['lastname']) ? 'Test' : $datarow['lastname'];
            $user->save();
        }
    }

    /**
     * @Given /^the following ELIS program course assignments exist:$/
     */
    public function theFollowingElisProgramCourseAssignmentsExist(TableNode $table) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/elisprogram/lib/data/curriculumcourse.class.php');
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cc = new curriculumcourse();
            $cc->courseid = $DB->get_field(course::TABLE, 'id', ['idnumber' => $datarow['course_idnumber']]);
            $cc->curriculumid = $DB->get_field(curriculum::TABLE, 'id', ['idnumber' => $datarow['program_idnumber']]);
            $cc->required = !empty($datarow['required']);
            $cc->save();
        }
    }

    /**
     * @Given /^the following scheduled report jobs exist:$/
     */
    public function theFollowingScheduledReportJobsExist(TableNode $table) {
        $page = $this->getSession()->getPage();
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $plugin = $datarow['plugin'];
            $dhschedpage = '/local/datahub/schedulepage.php?plugin='.$plugin.'&action=list';
            $this->getSession()->visit($this->locate_path($dhschedpage));
            $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
            $this->find_button('New job')->press();
            $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
            // Enter label.
            $page = $this->getSession()->getPage();
            $page->fillField('id_label', $datarow['label']);
            $this->find_button('Next')->press();
            $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
            // Select schedule type: period | advanced (default)
            if ($datarow['type'] == 'period') {
                $this->find_link('Basic Period Scheduling')->click();
                $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
                $page = $this->getSession()->getPage();
                $page->fillField('idperiod', $datarow['params']);
            } else {
                $page = $this->getSession()->getPage();
                $this->selectOption('id_timezone', '8.0');
                $params = json_decode($datarow['params']);
                if (!empty($params->startdate)) {
                    $this->clickRadio('starttype', '1');
                    $dateobj = $this->filloutScheduleDateField('id_startdate_', $params->startdate);
                }
                if (isset($params->recurrence) && $params->recurrence == 'calendar') {
                    $this->clickRadio('recurrencetype', 'calendar');
                    // enddate(enable checkbox), time, days(radio), months.
                    if (!empty($params->enddate)) {
                        $this->filloutScheduleDateField('id_calenddate_', $params->enddate);
                    }
                    if (!empty($dateobj->hour) && empty($params->hour)) {
                        $params->hour = $dateobj->hour;
                    }
                    if (!empty($params->hour)) {
                        $this->selectOption('id_time_hour', $params->hour);
                    }
                    if (!empty($dateobj->minute) && empty($params->minute)) {
                        $params->minute = $dateobj->minute;
                    }
                    if (!empty($params->minute)) {
                        $this->selectOption('id_time_minute', $params->minute);
                    }
                    if (!empty($params->weekdays)) {
                        $this->clickRadio('caldaystype', '1');
                        foreach (explode(',', $params->weekdays) as $day) {
                            $this->checkCheckbox("id_dayofweek_{$day}");
                        }
                    } else if (!empty($params->monthdays)) {
                        $this->clickRadio('caldaystype', '2');
                        $page->fillField('id_monthdays', $params->monthdays);
                    } else {
                        $this->clickRadio('caldaystype', '0');
                    }
                    if (!empty($params->months)) {
                        if ((int)$params->months < 1) {
                            $params->month = date('n');
                        }
                        foreach (explode(',', $params->months) as $month) {
                            $this->checkCheckbox("id_month_{$month}");
                        }
                    } else {
                        $this->checkCheckbox('id_allmonths');
                    }
                } else { // Recurrence simple.
                    if (!empty($params->enddate)) {
                        $this->clickRadio('runtype', '1');
                        $this->filloutScheduleDateField('id_enddate_', $params->enddate);
                    } else if (!empty($params->runs) && !empty($params->frequency) && !empty($params->units)) {
                        $this->clickRadio('runtype', '2');
                        $page->fillField('id_runsremaining', $params->runs);
                        $page->fillField('id_frequency', $params->frequency);
                        $this->selectOption('id_frequencytype', $params->units);
                    }
                }
            }
            $this->find_button('Save')->press();
            if (($cntlink = $this->find_link('Continue'))) {
                $cntlink->click();
            }
        }
    }

    /**
     * Save screenshot.
     * @param string $fname the path and filename to sabe the screenshot to.
     */
    public function saveScreenshot($fname) {
        $idata = $this->getSession()->getDriver()->getScreenshot();
        file_put_contents($fname, $idata);
    }

    /**
     * Save unique details report screenshot.
     * @param string $fname the path and filename to sabe the screenshot to.
     * @param string $url  the report url.
     */
    public function saveUniqueDetailsScreenshot($fname, $url) {
        $idata = $this->getSession()->getDriver()->getScreenshot();
        $key = $url.'_'.strlen($idata);
        if (!isset($this->detailsreports[$key])) {
            $this->detailsreports[$key] = $fname;
            file_put_contents($fname, $idata);
        } else {
            symlink($this->detailsreports[$key], $fname);
        }
    }

    /**
     * @Given /^I visit all details reports of the following UCC report URLs:$/
     */
    public function iVisitAllDetailsReportsOfTheFollowingUCCreportURLs(TableNode $table) {
        global $CFG, $DB;
        static $cnt = 0;
        if (empty($CFG->behat_faildump_path)) {
            throw new \Exception('This step needs $CFG->behat_faildump_path configured.');
        }
        $stamp = date('Y-m-d_h_i_s');
        $fpath = $CFG->behat_faildump_path.'/local_elisreports_'.$stamp;
        if (!file_exists($fpath)) {
            mkdir($fpath, 0777, true);
            // echo "Creating directories: {$fpath}\n";
        }
        if (!is_writable($fpath)) {
            throw new \Exception('$CFG->behat_faildump_path is not writable.');
        }
        // Setup UCC autocomplete filter:
        $DB->insert_record('config_plugins', (object)[
            'plugin' => 'filter-autocomplete',
            'name'   => 'user_class_completion/filter-autoc',
            'value'  => 'a:1:{s:8:"instance";a:3:{s:8:"idnumber";a:2:{s:6:"search";i:1;s:4:"disp";i:1;}s:9:"firstname";a:2:{s:6:"search";i:1;s:4:"disp";i:1;}s:8:"lastname";a:2:{s:6:"search";i:1;s:4:"disp";i:1;}}}'
        ]);
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cnt++;
            $url = preg_replace_callback('/\{(local_elis[_a-z]+)\}([+][0-9]*)/', function($matches) {
                        global $DB;
                        $startid = $DB->get_field_sql("SELECT MIN(id) FROM {".$matches[1]."} WHERE id > 0");
                        if ($startid) {
                            $startid--;
                        } else {
                            return ''; // TBD?
                        }
                        $id = eval("return {$startid}{$matches[2]};");
                      /*
                        ob_start();
                        var_dump($DB->get_records($matches[1]));
                        $tmp = ob_get_contents();
                        ob_end_clean();
                        error_log("Table filter param: {$matches[1]}{$matches[2]} => {$startid}{$matches[2]} => {$id}\n{$matches[1]}: {$tmp}\n");
                      */
                        return $id;
                    }, $datarow['reporturl']);
            file_put_contents("{$fpath}/uccr_{$cnt}_p1.txt", $url);
            $url = $CFG->wwwroot.$url;
            $this->getSession()->visit($url);
            // $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS); // TBD?
            if (($testurl = $this->getSession()->getCurrentUrl()) != $url) {
                // TBD: Error (404?)
                // error_log("Current URL {$testurl} != expected URL {$url}\n");
                continue;
            }
            $this->saveScreenshot("{$fpath}/uccr_{$cnt}_p1.jpg");
            $detailreports = [];
            $pg = 1;
            do {
                $j = 1;
                try {
                    foreach ($this->find_all('xpath', "//a[text()='Details']") as $detailreport) {
                        $detailreports["p{$pg}_{$j}"] = $detailreport->getAttribute('href');
                        $j++;
                    }
                } catch (Exception $e) {
                    ; // Ignore if no Details reports.
                }
                // Visit next page if present.
                try {
                    $nextlink = $this->find_link('Next');
                } catch (Exception $e) {
                    $nextlink = false;
                }
                $pg++;
                if ($nextlink) {
                    $nextlink->click();
                    try {
                        $timeout = 12;
                        while ($this->find_link($pg) && $timeout--) {
                           sleep(5);
                        }
                    } catch (Exception $e) {
                        ;  // Page link gone, ok to continue.
                    }
                    // $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS); // TBD?
                    $this->saveScreenshot("{$fpath}/uccr_{$cnt}_p{$pg}.jpg");
                    if ($timeout <= 0) {
                        break;
                    }
                } else {
                    break;
                }
            } while (true);
            // Visit all Details links from all pages.
            foreach ($detailreports as $key => $detailreporturl) {
                $this->getSession()->visit($detailreporturl);
                // $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS); // TBD?
                if (($testurl = $this->getSession()->getCurrentUrl()) != $detailreporturl) {
                    // TBD: Error (404?)
                    // error_log("Current URL {$testurl} != expected URL {$detailreporturl}\n");
                    continue;
                }
                $this->saveUniqueDetailsScreenshot("{$fpath}/uccdr_{$cnt}_{$key}.jpg", $detailreporturl);
            }
        }
    }
}
