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
 * @copyright  (C) 2008-2017 Remote Learner.net Inc http://www.remote-learner.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

die("This script is intended for development. If you are a developer, comment this line to continue.");

GLOBAL $globaldir1s, $globaldir2s;
$dir1 = isset($_POST['dir1']) ? 'value="'.$_POST['dir1'].'"' : '';
$path1 = isset($_POST['path1']) ? 'value="'.$_POST['path1'].'"' : '';
$dir2 = isset($_POST['dir2']) ? 'value="'.$_POST['dir2'].'"' : '';
$path2 = isset($_POST['path2']) ? 'value="'.$_POST['path2'].'"' : '';

/**
 * Get matching image file.
 * @param string $fimg the current report image in dir1/path1.
 * @return string The matching report image in dir2/path2.
 */
function getmatchingreportimage($fimg) {
    GLOBAL $globaldir1s, $globaldir2s;
    $dir2 = $_POST['dir2'];
    $path2 = $_POST['path2'].'/';
    $baserep = basename($fimg);
    $dirname = basename(dirname($fimg));
    if (($dirnum = array_search($dirname, $globaldir1s)) !== false) {
        $matchrep = glob("{$dir2}/{$globaldir2s[$dirnum]}/{$baserep}");
        if (!empty($matchrep[0])) {
            return $path2.substr($matchrep[0], strlen($dir2) + 1);
        } else {
            $matchrep = glob("{$dir2}/*/{$baserep}");
            if (!empty($matchrep[0])) {
                return $path2.substr($matchrep[0], strlen($dir2) + 1);
            }
        }
    } else {
        error_log("sscmp.php::getmatchingreportimage: dirname {$dirname} not found in globaldir1s");
    }
    return '';
}

/**
 * Setup global dir arrays.
 */
function setupglobaldirarrays()
{
    GLOBAL $globaldir1s, $globaldir2s;
    $dir1 = $_POST['dir1'];
    $dir2 = $_POST['dir2'];
    $globaldir1s = scandir($dir1);
    foreach ($globaldir1s as $key => $val) {
        if (substr($val, 0, 1) == '.' || !is_dir($dir1.'/'.$val)) {
            unset($globaldir1s[$key]);
        }
    }
    $globaldir2s = scandir($dir2);
    foreach ($globaldir2s as $key => $val) {
        if (substr($val, 0, 1) == '.' || !is_dir($dir2.'/'.$val)) {
            unset($globaldir2s[$key]);
        }
    }
}

/**
 * Generate table row with comparison images.
 * @param string $fimg the image filename.
 * @param bool $pg1 true if this is the first page of the report. Defaults to false.
 */
function gentablerow($fimg, $pg1 = false) {
    $dir1 = $_POST['dir1'];
    $path1 = $_POST['path1'].'/';
    $baserep = basename($fimg);
    $lastdir = dirname($fimg);
    $matches = [];
    if (preg_match('/uccr_([0-9]*)_p([0-9]+)[.]jpg/', $baserep, $matches)) {
        $repno = $matches[1];
        $pg = $matches[2];
    } else {
        echo "<p>Error matching report image number/page from {$baserep}</p>";
    }
    $f1 = $path1.substr($fimg, strlen($dir1) + 1);
    echo "<tr><td>{$baserep}</td><td><img src='{$f1}'/></td><td>";
    $f2 = getmatchingreportimage($fimg);
    if (!empty($f2)) {
        echo "<img src='{$f2}'/>";
    }
    echo '</td></tr>';
    // Details reports:
    if (!empty($repno) && !empty($pg)) {
        // error_log("sscmp.php: Looking for Details uccdr_{$repno}_p{$pg}_[0-9]+[.]jpg , lastdir = {$lastdir} , repno = {$repno}, pg = {$pg}");
        foreach (glob("{$lastdir}/uccdr_{$repno}_p{$pg}_[0-9]*[.]jpg") as $detailrep) {
            $d1 = $path1.substr($detailrep, strlen($dir1) + 1);
            echo "<tr><td>Details: ".basename($detailrep)."</td><td><img src='{$d1}'/></td><td>";
            $d2 = getmatchingreportimage($detailrep);
            if (!empty($d2)) {
                echo "<img src='{$d2}'/>";
            }
            echo '</td></tr>';
        }
        if ($pg1) {
            $nextpg = substr($fimg, 0, strlen($fimg) - 5);
            // echo "Next page: {$nextpg}, repno = {$repno}, pg = {$pg}\n";
            foreach (glob("{$nextpg}*.jpg") as $reppg) {
                $matches = [];
                if (preg_match("/uccr_{$repno}_p([0-9]*)[.]jpg/", $reppg, $matches)) {
                    $dpg = $matches[1];
                } else {
                    echo "<p>Error matching report image number/page from {$reppg}</p>";
                    break;
                }
                if ($dpg <= $pg) {
                    continue;
                }
                // echo "\n<br/> reppg = {$reppg}, dpg = {$dpg}";
                gentablerow($reppg);
            }
        }
    }
}

?>
<html>
    <style>
        label {
            display: inline-block;
            width: 20em;
            font-weight:bold;
        }
    </style>
    <form method="post">
        <h2>ELIS Reports: Screenshot compare tool</h2>
        <label for="dir1">Directory 1 (/behat_screenshorts I)</label>
        <input type="text" name="dir1" id="dir1" size="80" <?php echo $dir1; ?>/><br />
        <label for="path1">WebPath 1 (/behat_screenshorts I)</label>
        <input type="text" name="path1" id="path1" size="80" <?php echo $path1; ?>/><br />
        <label for="dir2">Directory 2 (/behat_screenshorts II)</label>
        <input type="text" name="dir2" id="dir1" size="80" <?php echo $dir2; ?>/><br />
        <label for="path2">WebPath 2 (/behat_screenshorts II)</label>
        <input type="text" name="path2" id="path1" size="80" <?php echo $path2; ?>/><br />
        <label for="nextreport">Start Report: </label>
        <input type="text" name="nextreport" id="nextreport" size="80"/><br />
        <input type="submit" value="Submit"/>
    </form>
</html>

<?php
if (!empty($_POST)) {
    $me = $_SERVER['REQUEST_URI']; // TBD: 'SCRIPT_NAME'?
    $dir1 = $_POST['dir1'];
    $path1 = $_POST['path1'].'/';
    $dir2 = $_POST['dir2'];
    $path2 = $_POST['path2'].'/';
    setupglobaldirarrays();
    if (!empty($_POST['nextreport'])) {
        $nextreport = $_POST['nextreport'];
        $cursub = dirname($nextreport);
        if ($cursub == '.' && ($matches = glob($dir1.'/*/'.$nextreport))) {
            $cursub = basename(dirname($matches[0]));
            $nextreport = $cursub.'/'.$nextreport;
            error_log("sscmp.php: cursub = {$cursub}, nextreport = {$nextreport}");
        }
    } else {
        $nextreport = false;
        $cursub = reset($globaldir1s);
    }
    $done = false;
    $tabinit = false;
    $subinit = false;
    $endtab = false;
    foreach ($globaldir1s as $subdir) {
        if ($cursub && $subdir != $cursub) {
            continue;
        }
        $cursub = false;
        foreach (glob("{$dir1}/{$subdir}/uccr_*_p1.jpg") as $rep1) {
            $baserep = basename($rep1);
            $dirname = basename(dirname($rep1));
            if ($done) {
                $endtab = true;
                echo "\n", '</table>', "\n";
                echo "<br/>\n";
                echo '<form method="post">', "\n";
                echo '<input type="hidden" name="dir1" value="'.$dir1.'"/>', "\n";
                echo '<input type="hidden" name="path1" value="'.substr($path1, 0, -1).'"/>', "\n";
                echo '<input type="hidden" name="dir2" value="'.$dir2.'"/>', "\n";
                echo '<input type="hidden" name="path2" value="'.substr($path2, 0, -1).'"/>', "\n";
                echo '<input type="hidden" name="nextreport" value="'.$dirname.'/'.$baserep.'"/>', "\n";
                echo '<input type="submit" value="Next Report"/>', "\n";
                echo '</form>', "\n";
                break 2;
            }
            // error_log("sscmp.php:: Processing report file {$rep1}");
            if ($nextreport && "{$dirname}/{$baserep}" != $nextreport) {
                // error_log("sscmp.php: Looking for UCCR {$nextreport} != {$dirname}/{$baserep}");
                continue;
            }
            if (!$tabinit) {
                $furl = substr($rep1, 0, -4).'.txt';
                if (file_exists($furl)) {
                    echo '<h3>Filter settings/url:</h3>', file_get_contents($furl), "<br/><br/>\n";
                }
                echo '<table border="1"><tr><th>Label</th><th>Report 1</th><th>Report 2</th></tr>', "\n";
                $tabinit = true;
            }
            gentablerow($rep1, true);
            $done = true;
        }
    }
    if (!$endtab) {
        echo "\n", '</table>', "\n";
    }
}
