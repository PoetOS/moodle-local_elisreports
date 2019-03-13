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
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * ELIS reports privacy API.
 *
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace local_elisreports\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $items The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection):
    \core_privacy\local\metadata\collection {

        // Add all of the relevant tables and fields to the collection.
        $collection->add_database_table('local_elisreports_schedule', [
            'userid' => 'privacy:metadata:local_elisreports_schedule:userid',
            'report' => 'privacy:metadata:local_elisreports_schedule:report',
            'config' => 'privacy:metadata:local_elisreports_schedule:config',
        ], 'privacy:metadata:local_elisreports_schedule');

        $collection->add_database_table('local_elisreports_links', [
            'scheduleid' => 'privacy:metadata:local_elisreports_links:scheduleid',
            'downloads' => 'privacy:metadata:local_elisreports_links:downloads',
            'link' => 'privacy:metadata:local_elisreports_links:link',
            'exportformat' => 'privacy:metadata:local_elisreports_links:exportformat',
            'timecreated' => 'privacy:metadata:local_elisreports_links:timecreated',
        ], 'privacy:metadata:local_elisreports_links');

        $collection->add_plugintype_link('rlreport', [], 'privacy:metadata:rlreportpluginsummary');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if (self::user_has_data($userid)) {
            $contextlist->add_user_context($userid);
        } else {
            $subplugintypeproviders = self::subplugin_providers();
            foreach ($subplugintypeproviders as $subpluginproviders) {
                foreach ($subpluginproviders as $subplugin) {
                    if ($subplugin->user_has_data($userid)) {
                        $contextlist->add_user_context($userid);
                        break 2;
                    }
                }
            }
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist containing the list of users who have data in this
     * context/plugin combination.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if (self::user_has_data($context->instanceid)) {
            $userlist->add_user($context->instanceid);
        } else {
            $subplugintypeproviders = self::subplugin_providers();
            foreach ($subplugintypeproviders as $subpluginproviders) {
                foreach ($subpluginproviders as $subplugin) {
                    if ($subplugin->user_has_data($context->instanceid)) {
                        $userlist->add_user($context->instanceid);
                        break 2;
                    }
                }
            }
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $CFG;
        require_once($CFG->dirroot . '/local/elisreports/sharedlib.php');

        if (empty($contextlist->count())) {
            return;
        }

        // Export ELIS core data.
        $data = new \stdClass();
        $data->schedules = [];
        $user = $contextlist->get_user();
        $context = \context_user::instance($user->id);

        $scheduledata = self::user_data($user->id);
        foreach ($scheduledata as $schedule) {
            $exportformat = get_attachment_export_format($schedule->exportformat);
            $data->schedules[] = [
                'report' => $schedule->report,
                'config' => $schedule->config,
                'downloads' => $schedule->downloads,
                'link' => $schedule->link,
                'exportformat' => $exportformat,
                'timecreated' => \core_privacy\local\request\transform::datetime($schedule->timecreated),
            ];
        }

        self::add_subplugin_data($data, $user->id);

        \core_privacy\local\request\writer::with_context($context)->export_data([
            get_string('privacy:metadata:local_elisreports', 'local_elisreports')
        ], $data);
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            // Because we only use user contexts the instance ID is the user ID.
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                // Because we only use user contexts the instance ID is the user ID.
                self::delete_user_data($context->instanceid);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved context and user information to delete
     * information for.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist) {
        $context = $userlist->get_context();
        // Because we only use user contexts the instance ID is the user ID.
        if ($context instanceof \context_user) {
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Return true if the specified userid has data in the ELIS reports tables.
     *
     * @param int $userid The Moodle user to check for.
     * @return boolean
     */
    private static function user_has_data(int $userid) {
        return !empty(self::user_data($userid));
    }

    /**
     * Return the ELIS reports records for the specified user.
     *
     * @param int $userid The user to check for.
     * @return array
     */
    private static function user_data(int $userid) {
        global $DB;

        $sqlconcat = $DB->sql_concat_join("'-'", ['ers.id', 'erl.id']);
        $sql = 'SELECT ' . $sqlconcat . ' as erid, erl.*, ers.userid, ers.report, ers.config ' .
            'FROM {local_elisreports_schedule} ers ' .
            'LEFT JOIN {local_elisreports_links} erl ON ers.id = erl.scheduleid ' .
            'WHERE ers.userid = :userid';
        // For this ELIS plugin, userid is the Moodle user id.
        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Delete all plugin data for the specified user id.
     *
     * @param int $userid The Moodle user id to delete data for.
     */
    private static function delete_user_data($userid) {
        global $DB;

        $records = self::user_data($userid);
        foreach ($records as $record) {
            $DB->delete_records('local_elisreports_links', ['scheduleid' => $record->scheduleid]);
        }
        $DB->delete_records('local_elisreports_schedule', ['userid' => $userid]);

        // Now handle any subplugin data.
        $subplugintypeproviders = self::subplugin_providers();
        foreach ($subplugintypeproviders as $subpluginproviders) {
            foreach ($subpluginproviders as $subplugin) {
                $subplugin->delete_user_data($userid);
            }
        }
    }

    /**
     * Get all subplugins that implement subplugin providers.
     * @return array An array by subplugin type of an array of all of that subtype's provider objects.
     */
    private static function subplugin_providers() {
        $providers = [];
        $subplugintypes = \core_component::get_subplugins('local_elisreports');
        foreach ($subplugintypes as $subplugintype => $typeplugins) {
            $providers[$subplugintype] = [];
            foreach ($typeplugins as $typename) {
                $classname = "\\{$subplugintype}_{$typename}\\privacy\\provider";
                $implementations = class_implements($classname);
                if (in_array('local_elisreports\privacy\\' . $subplugintype . '_provider', $implementations)) {
                    $providers[$subplugintype][$typename] = new $classname;
                }
            }
        }

        return $providers;
    }

    /**
     * Add all subplugin export data to the provided object.
     * @param \stdClass $data The object to add data to.
     * @param int $userid The user to add data for.
     */
    private static function add_subplugin_data($data, $userid) {
        $subplugintypeproviders = self::subplugin_providers();
        foreach ($subplugintypeproviders as $subplugintype => $subpluginproviders) {
            $data->{$subplugintype} = [];
            foreach ($subpluginproviders as $subpluginname => $subplugin) {
                $data->{$subplugintype}[$subpluginname] = $subplugin->add_user_data($userid);
            }
        }
    }
}