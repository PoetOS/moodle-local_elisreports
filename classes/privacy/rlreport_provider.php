<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @package    local_elisreports_rlreport
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * ELISreports rlreport subplugin privacy interface. A subplugin should implement this if it stores user data.
 *
 * @package    rlreport
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace local_elisreports\privacy;

defined('MOODLE_INTERNAL') || die();

interface rlreport_provider extends
    // Identifies the subplugin to the privacy API (I think).
    \core_privacy\local\request\plugin\subplugin_provider,

    // This prevents the "Userlist provider missing" flag in the plugin privacy registry, since this interface will pass that data
    // back to the main plugin.
    \core_privacy\local\request\shared_userlist_provider {

    /**
     * Indicates whether or not the subplugin has any user data for the provided user.
     *
     * @param int $userid The user ID to get context IDs for.
     * @return boolean Indicating data presence.
     * @param \core_privacy\local\request\contextlist $contextlist Use add_from_sql with this object to add your context IDs.
     */
    public static function user_has_data(int $userid);

    /**
     * Return all user data for the specified user.
     *
     * @param int $userid The id of the user the data is for.
     * @return array An array of arrays of each data collection for this user and subplugin.
     */
    public static function add_user_data(int $userid);

    /**
     * Delete all subplugin data for the specified user id.
     *
     * @param int $userid The Moodle user id to delete data for.
     */
    public static function delete_user_data($userid);
}