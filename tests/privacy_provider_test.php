<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy test for the ELIS reports local plugin.
 *
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2019 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

use \local_elisreports\privacy\provider;

/**
 * Privacy test for the ELIS reports local plugin.
 *
 * @package    local_elisreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2019 Remote Learner.net Inc http://www.remote-learner.net
 * @group local_elisreports
 */
class local_elisreports_privacy_testcase extends \core_privacy\tests\provider_testcase {
    /**
     * Tests set up.
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Check that a user context is returned if there is any user data for this user.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->assertEmpty(provider::get_contexts_for_userid($user1->id));
        $this->assertEmpty(provider::get_contexts_for_userid($user2->id));

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        self::create_schedule_link($schedid);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user1->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Test that only users with a user context are fetched.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();

        $component = 'local_elisreports';
        // Create some users.
        $user1 = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user1->id);

        // The list of users should not return anything yet (related data still haven't been created).
        $userlist = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        self::create_schedule_link($schedid);

        // The list of users for user context should return the user.
        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
        $expected = [$user1->id];
        $actual = $userlist->get_userids();
        $this->assertEquals($expected, $actual);

        // The list of users for system context should not return any users.
        $userlist = new \core_privacy\local\request\userlist(context_system::instance(), $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data() {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/elisreports/sharedlib.php');

        $this->resetAfterTest();

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        $linkid = self::create_schedule_link($schedid);
        $schedrec = $DB->get_record('local_elisreports_schedule', ['id' => $schedid]);
        $linkrec = $DB->get_record('local_elisreports_links', ['id' => $linkid]);

        $usercontext = \context_user::instance($user1->id);

        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertFalse($writer->has_any_data());
        $approvedlist = new core_privacy\local\request\approved_contextlist($user1, 'local_elisreports', [$usercontext->id]);
        provider::export_user_data($approvedlist);
        $data = $writer->get_data([get_string('privacy:metadata:local_elisreports', 'local_elisreports')]);
        $this->assertEquals($schedrec->report, $data->schedules[0]['report']);
        $this->assertEquals($schedrec->config, $data->schedules[0]['config']);
        $this->assertEquals($linkrec->downloads, $data->schedules[0]['downloads']);
        $this->assertEquals($linkrec->link, $data->schedules[0]['link']);
        $this->assertEquals(get_attachment_export_format($linkrec->exportformat), $data->schedules[0]['exportformat']);
        $this->assertEquals(\core_privacy\local\request\transform::datetime($linkrec->timecreated),
            $data->schedules[0]['timecreated']);
        $this->assertCount(1, $data->schedules);
    }

    /**
     * Test deleting all user data for a specific context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        $user1context = \context_user::instance($user1->id);
        $user2 = $this->getDataGenerator()->create_user();

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        $linkid = self::create_schedule_link($schedid);
        $schedrec = $DB->get_record('local_elisreports_schedule', ['id' => $schedid]);
        $linkrec = $DB->get_record('local_elisreports_links', ['id' => $linkid]);
        $schedid = self::create_schedule($user2->id);
        $linkid = self::create_schedule_link($schedid);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('local_elisreports_schedule', []));

        // Delete everything for the first user context.
        provider::delete_data_for_all_users_in_context($user1context);

        // Only the user1 record should be gone.
        $this->assertCount(0, $DB->get_records('local_elisreports_schedule', ['userid' => $user1->id]));
        $this->assertCount(0, $DB->get_records('local_elisreports_links', ['scheduleid' => $schedrec->id]));
        $this->assertCount(1, $DB->get_records('local_elisreports_schedule', []));
        $this->assertCount(1, $DB->get_records('local_elisreports_links', ['scheduleid' => $schedid]));
    }

    /**
     * This should work identical to the above test.
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest();

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        $user1context = \context_user::instance($user1->id);

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        $linkid = self::create_schedule_link($schedid);
        $schedrec = $DB->get_record('local_elisreports_schedule', ['id' => $schedid]);
        $linkrec = $DB->get_record('local_elisreports_links', ['id' => $linkid]);

        // Create a user record.
        $user2 = $this->getDataGenerator()->create_user();
        $schedid = self::create_schedule($user2->id);
        $linkid = self::create_schedule_link($schedid);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('local_elisreports_schedule', []));
        $this->assertCount(2, $DB->get_records('local_elisreports_links', []));

        // Delete everything for the first user.
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user1, 'local_elisreports', [$user1context->id]);
        provider::delete_data_for_user($approvedlist);

        // Only the user1 record should be gone.
        // Only the user1 record should be gone.
        $this->assertCount(0, $DB->get_records('local_elisreports_schedule', ['userid' => $user1->id]));
        $this->assertCount(0, $DB->get_records('local_elisreports_links', ['scheduleid' => $schedrec->id]));
        $this->assertCount(1, $DB->get_records('local_elisreports_schedule', []));
        $this->assertCount(1, $DB->get_records('local_elisreports_links', ['scheduleid' => $schedid]));
    }

    /**
     * Test that data for users in approved userlist is deleted.
     */
    public function test_delete_data_for_users() {
        global $DB;

        $this->resetAfterTest();

        $component = 'local_elisreports';

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        $user1context = \context_user::instance($user1->id);

        // Create report data instances.
        $schedid = self::create_schedule($user1->id);
        $linkid = self::create_schedule_link($schedid);
        $schedrec = $DB->get_record('local_elisreports_schedule', ['id' => $schedid]);
        $linkrec = $DB->get_record('local_elisreports_links', ['id' => $linkid]);

        // Create a user record.
        $user2 = $this->getDataGenerator()->create_user();
        $user2context = \context_user::instance($user2->id);
        $schedid = self::create_schedule($user2->id);
        $linkid = self::create_schedule_link($schedid);

        // The list of users for usercontext1 should return user1.
        $userlist1 = new \core_privacy\local\request\userlist($user1context, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);
        $expected = [$user1->id];
        $actual = $userlist1->get_userids();
        $this->assertEquals($expected, $actual);

        // The list of users for usercontext2 should return user2.
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
        $expected = [$user2->id];
        $actual = $userlist2->get_userids();
        $this->assertEquals($expected, $actual);

        // Add userlist1 to the approved user list.
        $approvedlist = new \core_privacy\local\request\approved_userlist($user1context, $component, $userlist1->get_userids());

        // Delete user data using delete_data_for_user for usercontext1.
        provider::delete_data_for_users($approvedlist);

        // Re-fetch users in usercontext1 - The user list should now be empty.
        $userlist1 = new \core_privacy\local\request\userlist($user1context, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);

        // User data should be only removed in the user context.
        $systemcontext = context_system::instance();
        // Add userlist2 to the approved user list in the system context.
        $approvedlist = new \core_privacy\local\request\approved_userlist($systemcontext, $component, $userlist2->get_userids());
        // Delete user1 data using delete_data_for_user.
        provider::delete_data_for_users($approvedlist);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }

    /**
     * Create a user schedule instance for testing.
     *
     * @param int $userid Data id of the user record.
     * @return int Data id of the created record.
     */
    private static function create_schedule($userid) {
        global $DB;

        // Create a schedule instance.
        $record = (object)['userid' => $userid, 'report' => 'curricula',
            'config' => 'testconfigdata'];
        return $DB->insert_record('local_elisreports_schedule', $record);
    }

    /**
     * Create a user schedule link instance for testing.
     *
     * @param int $scheduleid Data id of the user record.
     * @return int Data id of the created record.
     */
    private static function create_schedule_link($scheduleid) {
        global $DB;

        // Create a schedule link instance.
        $record = (object)['scheduleid' => $scheduleid, 'downloads' => 5, 'link' => 'http://mytest.link/',
            'exportformat' => 1, 'timecreated' => time()];
        return $DB->insert_record('local_elisreports_links', $record);
    }
}