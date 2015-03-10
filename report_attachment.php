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

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/local/elisreports/sharedlib.php');

global $DB, $ME;

$id = required_param('id', PARAM_INT);
$link = required_param('link', PARAM_CLEAN);
$extcode = required_param('ext', PARAM_INT);
$matches = array();
if (!preg_match('/[0-9a-zA-z]{32}/', $link, $matches) || empty($matches[0]) || $link != $matches[0] ||
        ($ext = get_attachment_export_format($extcode)) == false ||
        ($filename = get_existing_report_attachment($id, $ext, $link)) == null ||
        !($recset = $DB->get_recordset_select('local_elisreports_links', 'scheduleid = ? AND '.$DB->sql_like('link', '?'), array($id, "%s:4:\"link\";s:32:\"{$link}\";%")))) {
    $PAGE->set_context(null);
    $PAGE->set_pagelayout('base'); // No header and footer desired.
    $PAGE->set_title(get_string('attachmentnotfound', 'local_elisreports'));
    $PAGE->set_url($ME);
    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string('attachmentnotfound', 'local_elisreports'), 'errorbox boxaligncenter', 'notice');
    echo $OUTPUT->footer();
} else {
    $rec = $recset->current();
    $data = unserialize($rec->link);
    // Following generic headers mostly from our execl export and tcpdf.
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // TBD: max-age?
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache"); // TBD: public?
    switch ($ext) {
        case 'csv': // From our csv export.
            header("Content-Transfer-Encoding: ascii");
            header('Content-Disposition: attachment; filename="'.$data['name'].'";');
            header("Content-Type: text/comma-separated-values");
            break;

        case 'xls': // From our excel export.
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$data['name'].'";');
            break;

        case 'pdf': // From tcpdf.
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$data['name'].'";');
            break;
    }
    if (readfile($filename)) {
        $rec->downloads++;
        $DB->update_record('local_elisreports_links', $rec);
    }
}

