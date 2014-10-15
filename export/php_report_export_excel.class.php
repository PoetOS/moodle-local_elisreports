<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * TO DO: enable wrapping of table headers
 *
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once($CFG->dirroot.'/local/elisreports/export/php_report_export.class.php');

class php_report_export_excel extends php_report_export {
    protected $currentcellrow = 1;
    protected $headerscount = 0;

    /**
     * Create a new instance of a Excel report export.
     *
     * @param php_report $report A reference to the report being exported
     */
    public function __construct(&$report) {
        $this->report =& $report;
        $this->headerscount = count($this->report->headers) - 1;
    }

    /**
     * Sets up a new Excel object with the necessary settings.
     *
     * @return PHPExcel A new Excel spreadsheet object
     */
    protected function initialize_excel() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/phpexcel/PHPExcel.php');

        $creator = get_string('blockname', 'local_elisreports');
        $title = $this->report->title;

        $excel = new PHPExcel();

        $excel->getProperties()->setCreator($creator)->setLastModifiedBy($creator)->setTitle($title)->setSubject($title)->setDescription($title);

        return $excel;
    }

    /**
     * Performs the raw output of the Excel file.
     *
     * @param PHPExcel $excel The Excel object to write to file
     * @param string $storagepath Path to save the file to, or null if sending to browser
     * @param string $filename Filename to use if sending the file to the browser (including extension)
     */
    protected function output_excel_file(&$excel, $storagepath, $filename) {
        // Invoke the Excel IO Writer object.
        $excelwriter = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');

        if ($storagepath === null) {
            // Output to browser.
            header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            $excelwriter->save('php://output');
        } else {
            // Write to file.
            $excelwriter->save($storagepath);
        }
    }

    /**
     * Renders the dispay name of the current report at the top of the Excel spreadsheet.
     *
     * @param PHPExcel $excel The Excel object being used
     */
    protected function render_report_name(&$excel) {
        $columns = range('A', 'Z');

        $displayname = $this->report->get_display_name();
        $excel->setActiveSheetIndex(0)->setCellValue('A'.$this->currentcellrow, $displayname);

        $colour = $this->report->get_display_name_colour();
        $colourhex = sprintf("%02X%02X%02X", $colour[0], $colour[1], $colour[2]);
        $cellrange = 'A'.$this->currentcellrow.':'.$columns[$this->headerscount].$this->currentcellrow;
        $excel->getActiveSheet()->getStyle($cellrange)->applyFromArray(
                array('fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => $colourhex))));

        $this->currentcellrow += 2;
    }

    /**
     * Retrieves the summary row object to be displayed within the Excel spreadsheet.
     *
     * @return object The summary row
     */
    protected function get_excel_column_based_summary_row() {
        // Use the column structure to create a summary record.
        $columnbasedsummaryrow = $this->report->column_based_summary_row->get_row_object(array_keys($this->report->headers));

        // Use the special hook to perform any data manipulation needed.
        $columnbasedsummaryrow = $this->report->transform_column_summary_record($columnbasedsummaryrow);

        return $columnbasedsummaryrow;
    }

    /**
     * Renders key => value heading pairs in the Excel spreadsheet.
     *
     * @param PHPExcel $excel The Excel spreadsheet being created
     */
    protected function render_excel_headers(&$excel) {
        $headerentries = $this->report->get_header_entries(php_report::$EXPORT_FORMAT_EXCEL);

        // Add headers to the output.
        if (!empty($headerentries)) {
            // Render the entries.
            foreach ($headerentries as $headerentry) {
                $excel->getActiveSheet()->setCellValue('A'.$this->currentcellrow, $headerentry->label); // Key.
                $excel->getActiveSheet()->setCellValue('C'.$this->currentcellrow, $headerentry->value); // Value.
                $this->currentcellrow++;
            }
            $this->currentcellrow++;
        }
    }

    /**
     * Renders tabular column headers in the Excel spreadsheet being created.
     *
     * @param PHPExcel The Excel object being created
     */
    protected function render_excel_column_headers(&$excel) {
        $columns = range('A', 'Z');
        $columncount = 0;

        $colour = $this->report->get_column_header_colour();
        $colourhex = sprintf("%02X%02X%02X", $colour[0], $colour[1], $colour[2]);

        // Render the column headers.
        foreach ($this->report->headers as $id => $header) {
            if (in_array(php_report::$EXPORT_FORMAT_EXCEL, $this->report->columnexportformats[$id])) {
                // Convert alignment to format used by Excel library.
                switch (strtolower($this->report->align[$id])) {
                    case 'left':
                        $cellalign = PHPExcel_Style_Alignment::HORIZONTAL_LEFT;
                        break;
                    case 'center':
                        $cellalign = PHPExcel_Style_Alignment::HORIZONTAL_CENTER;
                        break;
                    case 'right':
                        $cellalign = PHPExcel_Style_Alignment::HORIZONTAL_RIGHT;
                        break;
                    default:
                        $cellalign = PHPExcel_Style_Alignment::HORIZONTAL_CENTER;
                        break;
                }

                // Apply cell styling.
                $cellname = $columns[$columncount].$this->currentcellrow;
                $excel->getActiveSheet()->getStyle($cellname)->getAlignment()->setHorizontal($cellalign); // Alignment.
                $excel->getActiveSheet()->getStyle($cellname)->getFont()->setBold(true); // Bolding.

                // Apply row colour.
                if ($columncount == 0) {
                    $cellrange = $cellname.':'.$columns[$this->headerscount].$this->currentcellrow;
                    $excel->getActiveSheet()->getStyle($cellrange)->applyFromArray(
                            array('fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => $colourhex))));
                }

                // Output header text.
                $excel->getActiveSheet()->setCellValue($cellname, $header);

                $columncount++;
            }
        }

        $this->currentcellrow++;
    }

    /**
     * Adds a grouping row to the table belonging to this report.
     *
     * @param mixed $data Row contents
     * @param boolean $spanrow If TRUE, spanning row without forcing column header directly after it
     * @param boolean $firstcolumn If TRUE, spanning row, force column header directly after it
     * @param PHPExcel $excel The Excel object we are working with
     * @param int $level Which grouping level we are currently at (0-indexed)
     */
    protected function add_grouping_table_row($data, $spanrow, $firstcolumn, &$excel, $level) {
        $columns = range('A', 'Z');

        // Set the row's color based on the report definition.
        $colours = $this->report->get_grouping_row_colours();
        if ($level < count($colours)) {
            $colour = $colours[$level];
        } else {
            // Ran out of colours, so use the last one.
            $colour = $colours[count($colours) - 1];
        }
        $colourhex = sprintf("%02X%02X%02X", $colour[0], $colour[1], $colour[2]);

        if ($spanrow || $firstcolumn) {
            $cellname = 'A'.$this->currentcellrow;
            $excel->getActiveSheet()->getStyle($cellname)->getFont()->setBold(true);
            $excel->getActiveSheet()->setCellValue($cellname, $data[0]);
            $cellrange = $cellname.':'.$columns[$this->headerscount].$this->currentcellrow;
            $excel->getActiveSheet()->getStyle($cellrange)->applyFromArray(
                    array('fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => $colourhex))));

            $this->currentcellrow++;
        } else {
            // Column-based entry case.
            $this->render_excel_entry($excel, (object)$data, $colour);
        }
    }

    /**
     * Performs all calculations / actions to process additional information based on report groupings.
     *
     * @param object $datum The current (unformatted) row of report data
     * @param array $groupinglast Mapping of column identifiers to the value representing them in the last grouping change
     * @param array $gropuingcurrent Mapping of column identifiers to the value representing them in the current grouping state
     * @param array $groupingfirst Mapping of column identifiers to the value true if they've not been processed yet, or false if they have
     * @param PHPExcel $excel The Excel object being created
     * @param boolean $needcolumnsheader Variable to update with status regarding whether we need to display column headers before our next row of data
     * @param object $nextdatum The next unprocessed row that we be used in the report data, or false if none
     * @param boolean $resetcolumncolours Set to true to signal that column colour state should be reset.
     * @return object Summary record to display after next row of data, or false if none
     */
    protected function update_groupings(&$datum, &$groupinglast, &$groupingcurrent, &$groupingfirst, &$excel, &$needcolumnsheader, $nextdatum, $resetcolumncolours) {
        $result = false;

        // Make sure groupings are set up.
        if (!empty($this->report->groupings) && ! (is_array($datum) && (strtolower($datum[0]) == 'hr'))) {

            // Index to store the a reference to the topmost grouping entry that needs to be displayed.
            $topmostkey = $this->report->get_grouping_topmost_key($groupingfirst, $datum, $groupinglast);

            // Make sure something actually changed.
            if ($topmostkey !== null) {
                $resetcolumncolours = true;

                // Go through only the headers that actually matter.
                $maxgroupings = count($this->report->groupings);
                for ($index = $topmostkey; $index < $maxgroupings; $index++) {
                    $grouping = $this->report->groupings[$index];

                    // Set the information in the current grouping based on our report row.
                    $this->report->update_current_grouping($grouping, $datum, $groupingcurrent);

                    // Handle grouping changes.
                    if ($grouping->position == 'below') {
                        // Make a copy of this row datum to be modified for printing group header inline.
                        $datumgroup = clone($datum);

                        // Remove any unnecessary entries from the header row.
                        $groupingrow = $this->report->clean_header_entry($datum, $grouping, $datumgroup);

                        // Be sure to display a column header before the below-column-header header.
                        if ($needcolumnsheader) {
                            $this->render_excel_column_headers($excel);
                            $needcolumnsheader = false;
                        }

                        if ($groupingrow) {
                            // Handle "Below" position with per-column data.
                            $datumgroupcopy = clone($datumgroup);
                            $datumgroupcopy = $this->report->transform_grouping_header_record($datumgroupcopy, $datum, php_report::$EXPORT_FORMAT_EXCEL);
                            $groupingdisplaytext = $this->report->get_row_content($datumgroupcopy, $groupingrow);
                            $this->add_grouping_table_row($groupingdisplaytext, false, false, $excel, $index);
                        } else {
                            // Handle "Below" position without per-column data.
                            $headers = $this->report->transform_grouping_header_label($groupingcurrent, $grouping, $datum, php_report::$EXPORT_FORMAT_EXCEL);

                            // Add all headers to the table output.
                            if (count($headers) > 0) {
                                foreach ($headers as $header) {
                                    $groupingdisplaytext = array($header);
                                    $this->add_grouping_table_row($groupingdisplaytext, true, false, $excel, $index);
                                }
                            }
                        }
                    } else {
                        // Signal that we need to display column headers before the next header that is not of this type / before the next report data entry.
                        $needcolumnsheader = true;

                        // Handle "Above" position without per-column data (single label and value).
                        $headers = $this->report->transform_grouping_header_label($groupingcurrent, $grouping, $datum, php_report::$EXPORT_FORMAT_EXCEL);

                        // Add all headers to the table output.
                        if (count($headers) > 0) {
                            foreach ($headers as $header) {
                                $groupingdisplaytext = array($header);
                                $this->add_grouping_table_row($groupingdisplaytext, false, true, $excel, $index);
                            }
                        }
                    }

                    // Move on to the next entry.
                    $this->report->update_groupings_after_iteration($grouping, $groupingfirst, $groupingcurrent, $groupinglast);
                }
            }
        }

        // Be sure to display a column header before the report data if necessary.
        if ($needcolumnsheader) {
            $this->render_excel_column_headers($excel);
            $needcolumnsheader = false;
        }

        return $result;
    }

    /**
     * Renders the core tabular data of the report.
     *
     * @param PHPExcel $excel The Excel object being created
     * @param string $query The main report query
     * @param array $params SQL query params
     * @param boolean $needcolumnsheader Flag for needing columns header
     * @return int Number of rows processed
     */
    protected function render_excel_core_data(&$excel, $query, $params, &$needcolumnsheader) {
        global $DB;

        $row = 0;

        $groupingobject = $this->report->initialize_groupings();

        // Iterate through the core report data.
        if ($recordset = $DB->get_recordset_sql($query, $params)) {

            // Need to track both so we can detect grouping changes.
            $datum = $recordset->current();
            $nextdatum = false;

            // Tracks the state of alternating background colours.
            $columncolourstate = 0;

            while ($datum !== false) {
                $curdatum = clone($datum); // Copy BEFORE transform_record().

                // Pre-emptively fetch the next record for grouping changes.
                $recordset->next();

                // Fetch the current record.
                $nextdatum = $recordset->current();
                if (!$recordset->valid()) {
                    // Make sure the current record is a valid one.
                    $nextdatum = false;
                }

                $resetcolumncolours = false;

                $datum = $this->report->transform_record($datum, php_report::$EXPORT_FORMAT_EXCEL);

                // Get the per-group column summary item, if applicable.
                $columnsummaryitem = $this->update_groupings($datum, $groupingobject->grouping_last, $groupingobject->grouping_current,
                        $groupingobject->grouping_first, $excel, $needcolumnsheader, $nextdatum, $resetcolumncolours);

                if ($resetcolumncolours) {
                    // Grouping change, so reset background colour state.
                    $columncolourstate = 0;
                }

                // Must check for multi-line groupby field data to include.
                while ($this->report->multiline_groupby($curdatum, $nextdatum)) {
                    // We want to add only changed data to previous row.
                    $this->report->append_data($datum, $curdatum, $nextdatum, php_report::$EXPORT_FORMAT_EXCEL);
                    $curdatum = clone($nextdatum);
                    // Pre-emptively fetch the next record for grouping changes.
                    $recordset->next();
                    // Fetch the current record.
                    $nextdatum = $recordset->current();
                    if (!$recordset->valid()) {
                        // Make sure the current record is a valid one.
                        $nextdatum = false;
                        break;
                    }
                }

                // Render main data entry.
                $datum = $this->report->get_row_content($datum, false, php_report::$EXPORT_FORMAT_EXCEL);

                // Render the entry, taking into account the current state of the background colour.
                $colours = $this->report->get_row_colours();
                $colour = $colours[$columncolourstate];
                $this->render_excel_entry($excel, (object)$datum, $colour);

                if ($this->report->requires_group_column_summary()) {
                    $groupingchange = $nextdatum === false || $this->report->any_group_will_change($curdatum, $nextdatum);

                    if ($groupingchange) {
                        // Last record or grouping change.

                        // Get the summary record.
                        $grpcolsum = $this->report->transform_group_column_summary($curdatum, $nextdatum, php_report::$EXPORT_FORMAT_EXCEL);

                        if (!empty($grpcolsum)) {
                            // Summary record is valid, so signal to display it after the next record.
                            $columncolourstate = 0;
                            $this->render_excel_entry($excel, $grpcolsum, $this->report->get_grouping_summary_row_colour());
                        }
                    }
                }

                $row++;

                // Update the state of the background colour.
                $columncolourstate = ($columncolourstate + 1) % count($colours);

                // Already tried to fetch the next record, so use it.
                $datum = $nextdatum;
            }
        }

        return $row;
    }

    /**
     * Renders one row of data to the Excel spreadsheet.
     *
     * @param PHPExcel $excel The Excel object we are creating
     * @param stdClass $datum The current row of data
     * @param array|null $colour R, G, and B values for a specific colour, or null if not applicable
     */
    protected function render_excel_entry($excel, $datum, $colour = null) {
        $columns = range('A', 'Z');
        $columncount = 0;

        $colourhex = ($colour !== null) ? sprintf("%02X%02X%02X", $colour[0], $colour[1], $colour[2]) : null;

        foreach ($this->report->headers as $id => $header) {
            if (in_array(php_report::$EXPORT_FORMAT_EXCEL, $this->report->columnexportformats[$id])) {
                $text = '';

                $effectiveid = $this->report->get_object_index($id);

                if (isset($datum->$effectiveid)) {
                    $text = trim(strip_tags($datum->$effectiveid));
                }

                $cellname = $columns[$columncount].$this->currentcellrow;
                $excel->getActiveSheet()->setCellValue($cellname, $text);

                if ($colourhex !== null) {
                    $cellrange = $cellname.':'.$columns[$this->headerscount].$this->currentcellrow;
                    $excel->getActiveSheet()->getStyle($cellrange)->applyFromArray(
                            array('fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => $colourhex))));
                }

                $columncount++;
            }
        }

        $this->currentcellrow++;
    }

    /**
     * Populates the contents of the Excel object being created.
     *
     * @param PHPExcel $excel Reference to the Excel object being created
     * @param string $query The main report query
     * @param array $params SQL query params
     */
    protected function render_excel_instance(&$excel, $query, $params) {
        // Print the report name.
        $this->render_report_name($excel);

        // Print an appropriate header.
        $this->report->print_excel_header($excel);

        $this->render_excel_headers($excel);

        // Used to track if we need to display column headers after a heading entry.
        $needcolumnsheader = false;

        if (empty($this->report->groupings)) {
            // Display column headers now because we know we have at least one record.
            $this->render_excel_column_headers($excel);
        } else {
            // Flag that we need to display headers when we're done with the first set of above-column-header header entries.
            $needcolumnsheader = true;
        }

        $row = $this->render_excel_core_data($excel, $query, $params, $needcolumnsheader);

        $columnbasedsummaryrow = $this->get_excel_column_based_summary_row();
        if ($columnbasedsummaryrow !== null) {
            $colour = $this->report->get_column_based_summary_colour();
            $this->render_excel_entry($excel, $columnbasedsummaryrow, $colour);
        }
    }

    /**
     * Export a report in the Excel format.
     *
     * @param string $query Final form of the main report query
     * @param array $params SQL query params
     * @param string $storagepath Path on the file system to save the output to or null if sending to browser
     * @param $filename Filename to use when sending to browser
     */
    public function export($query, $params, $storagepath, $filename) {
        global $CFG;

        $filename .= '.xlsx';

        $excel = $this->initialize_excel();

        $this->render_excel_instance($excel, $query, $params);

        $this->output_excel_file($excel, $storagepath, $filename);
    }
}
