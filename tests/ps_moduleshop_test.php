<?php
use core_customfield\field_controller;
use core_calendar\event;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');


/**
 * Tests for booking rules.
 *
 * @package ps_moduleshop
 * @category test
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ps_moduleshop_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test for getcourse.
     *
     * @covers \ps_moduleshop::getcourse
     *
     *
     */
    public function test_get_courses() {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $shop = new ps_moduleshop();
        $courses = $shop->get_courses();

        // One Course is created automatically
        $this->assertIsArray($courses);
        $this->assertCount(3, $courses);
    }

    public function test_get_customfields() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

         // 1. Create a category for course custom fields.
         $handler = \core_course\customfield\course_handler::create();
         $categoryid = $handler->create_category('Course Fields');

         // 2. Create a custom field in the category.
         $data = (object)[
             'shortname' => 'mycustomfield',
             'name' => 'My Custom Field',
             'type' => 'text', // Other types: 'checkbox', 'textarea', etc.
             'categoryid' => $categoryid,
         ];

         $field = field_controller::create(0, $data);
         $field->save();

         // 3. Set a value for the custom field.
         $formdata = (object) [
            'id' => $course->id,
            'customfield_mycustomfield' => 'Custom value',

         ];
         $handler->instance_form_save($formdata);

         // 5. Assert the value was saved.
         $reloadeddata = $handler->get_instance_data($course->id, true);
         $this->assertEquals('Custom value', $reloadeddata[$field->get('id')]->get_value());

         $shop = new ps_moduleshop();
         $fields = $shop->get_customfields($course->id);

         $this->assertIsObject($fields);
         $this->assertObjectHasAttribute('psigenerell', $fields);
    }



    public function test_get_events() {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();


        // Event via Moodle-API erzeugen
        $event = calendar_event::create([
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

    public function test_get_lehrende() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Rolle: editingteacher (standard in Moodle)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 3);

        $shop = new ps_moduleshop();
        $teachers = $shop->get_lehrende($course->id);

        $this->assertIsArray($teachers);
        $this->assertCount(1, $teachers);
        $this->assertEquals($user->id, $teachers[0]['userid']);
    }

    public function test_get_cohort() {
        global $DB;
        // Create a category
        $category = $this->getDataGenerator()->create_category();


        // Create a cohort in the category
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Test Cohort',
            'categoryid' => $category->id,
        ]);

        // Now run the test
        $shop = new ps_moduleshop();
        //$shop->course_categories = [$category->id => $category];
        $fetchedcohort = $shop->get_cohort($category->id);

        // Assert that the cohort is correctly fetched
        $this->assertNotNull($fetchedcohort);
    }
}
