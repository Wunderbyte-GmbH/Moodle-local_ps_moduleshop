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
 * Tests for ps_moduleshop.
 *
 * @package local_ps_moduleshop
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author David Ala
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ps_moduleshop;
use advanced_testcase;
use calendar_event;
use context_course;
use core_customfield\field_controller;

/**
 * Tests for ps_moduleshop.
 *
 * @package local_ps_moduleshop
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author David Ala
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ps_moduleshop_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test for get_customfields.
     *
     * @covers \ps_moduleshop::get_customfields
     *
     *
     */

    public function test_get_customfields(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

         // 1. Create a category for course custom fields.
         $handler = \core_course\customfield\course_handler::create();
         $categoryid = $handler->create_category('Course Fields');

         // 2. Create a custom field in the category.
         $data = (object)[
             'shortname' => 'psigenerell',
             'name' => 'psigenerell',
             'type' => 'text',
             'categoryid' => $categoryid,
         ];

         $field = field_controller::create(0, $data);
         $field->save();

         // 3. Set a value for the custom field.
         $formdata = (object) [
            'id' => $course->id,
            'customfield_psigenerell' => 'Ja',

         ];
         $handler->instance_form_save($formdata);

         // 4. Assert the value was saved.
         $reloadeddata = $handler->get_instance_data($course->id, true);
         $this->assertEquals('Ja', $reloadeddata[$field->get('id')]->get_value());

         $shop = new ps_moduleshop();
         $fields = $shop->get_customfields($course->id);

         $this->assertIsObject($fields);
         $this->assertObjectHasProperty('psigenerell', $fields);
    }

    /**
     * Test for get_events.
     *
     * @covers \ps_moduleshop::get_events
     *
     *
     */

    public function test_get_events(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $events = calendar_event::create([
            'name' => 'Test Event',
            'description' => 'This is a test event',
            'courseid' => $course->id,
            'eventtype' => 'course',
            'timestart' => time() + 3600,
            'timeduration' => 3600,
            'visible' => 1,
        ]);

        $shop = new ps_moduleshop();
        $events = $shop->get_events($course->id);

        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertEquals('Test Event', reset($events)->name);
    }

    /**
     * Test for get_cohort.
     *
     * @covers \ps_moduleshop::get_cohort
     *
     *
     */
    public function test_get_cohort(): void {
        // Create parent category.
        $parent = $this->getDataGenerator()->create_category();

        $shop = new ps_moduleshop();
        $fetchedcohort = $shop->get_cohort($parent->id);
        $this->assertEmpty($fetchedcohort);


        // Create child category.
        $child = $this->getDataGenerator()->create_category([
            'parent' => $parent->id,
        ]);

        $shop = new ps_moduleshop();
        $fetchedcohort = $shop->get_cohort($child->id);

        // Assert that the correct category name (from path[2]) is returned.
        $this->assertEquals($child->name, $fetchedcohort);
    }

    /**
     * Test for get_teachers.
     *
     * @covers \ps_moduleshop::get_teachers
     *
     *
     */
    public function test_get_teachers(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Assign role to user in course context.
        $context = context_course::instance($course->id);
        // Create role (teacher = 3 by default, but to be safe).
        role_assign(3, $user->id, $context->id);

        // Create required custom profile fields.
        $academicfield = $this->create_custom_profile_field('academic');
        $stationsfield = $this->create_custom_profile_field('stations');
        $focusfield = $this->create_custom_profile_field('focus');

        // Insert user profile data.
        $this->set_user_profile_data($user->id, $academicfield->id, 'Prof. Dr.');
        $this->set_user_profile_data($user->id, $stationsfield->id, 'Cardiology');
        $this->set_user_profile_data($user->id, $focusfield->id, 'Heart failure');

        $shop = new ps_moduleshop();
        $result = $shop->get_teachers($course->id);

        // Check result.
        $this->assertCount(1, $result);
        $lehrende = reset($result);
        $this->assertEquals($user->id, $lehrende['userid']);
        $this->assertEquals('Prof. Dr.', $lehrende['academic']);
        $this->assertEquals('Cardiology', $lehrende['stations']);
        $this->assertEquals('Heart failure', $lehrende['focus']);
    }

    /**
     * Function to create customprofilefields.
     *
     * @param string $shortname
     *
     * @return \stdClass
     *
     */
    protected function create_custom_profile_field(string $shortname) {
        global $DB;

        $field = (object)[
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'datatype' => 'text',
            'categoryid' => 1,
            'sortorder' => 0,
            'required' => 0,
            'locked' => 0,
            'visible' => 1,
        ];
        $field->id = $DB->insert_record('user_info_field', $field);
        return $field;
    }

    /**
     * Function to set user_profile_data.
     *
     * @param int $userid
     * @param int $fieldid
     * @param string $data
     *
     * @return void
     *
     */
    protected function set_user_profile_data(int $userid, int $fieldid, string $data) {
        global $DB;

        $record = (object)[
            'userid' => $userid,
            'fieldid' => $fieldid,
            'data' => $data,
        ];
        $DB->insert_record('user_info_data', $record);
    }
}
