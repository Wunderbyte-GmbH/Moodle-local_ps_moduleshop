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

namespace local_ps_moduleshop;
defined('MOODLE_INTERNAL') || die();


use core_course_category;
use core_customfield\handler;
use stdClass;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * [Description ps_moduleshop]
 * @package ps_moduleshop
 */

class ps_moduleshop {
    /** @var array */
    private array $coursecategories;

    /**
     * Constructor.
     *
     *
     */
    public function __construct() {
        $this->coursecategories = core_course_category::make_categories_list();
    }

    /**
     * Get Export.
     *
     * @return array
     *
     */
    public function get_export(): array {
        $courses = get_courses();
        $result = [];

        foreach ($courses as $course) {
            $item = new stdClass();
            $item->id = $course->id;
            $item->fullname = $course->fullname;
            $item->shortname = $course->shortname;
            $item->summary = $course->summary;
            $item->startdate = $course->startdate;
            $item->enddate = $course->enddate;
            $item->cohort = self::get_cohort($course->category);
            $item->coursefields = self::get_customfields($course->id);

            if (!empty($item->coursefields->psigenerell) && $item->coursefields->psigenerell === 'Ja') {
                $item->teachers = self::get_teachers($course->id);
                $item->events = self::get_events($course->id);
                $result[$course->id] = $item;
            }
        }
        return $result;
    }

    /**
     * Get Customfields.
     *
     * @param int $courseid
     *
     * @return stdClass
     *
     */
    public function get_customfields(int $courseid) {
        $handler = handler::get_handler('core_course', 'course');
        $datas = $handler->get_instance_data($courseid, true);
        $fields = new stdClass();

        foreach ($datas as $data) {
            $shortname = $data->get_field()->get('shortname');
            $value = $data->get_value();
            $type = $data->get_field()->get('type');

            switch ($type) {
                case 'select':
                    $options = $data->get_field()->get_options();
                    $fields->{$shortname} = $options[$value] ?? '';
                    break;
                case 'checkbox':
                    $fields->{$shortname} = $value;
                    break;
                case 'date':
                    $fields->{$shortname} = $value;
                    break;
                default:
                    $fields->{$shortname} = $value;
            }
        }
        return $fields;
    }

    /**
     * Get events.
     *
     * @param int $courseid
     *
     * @return array
     *
     */
    public function get_events(int $courseid): array {
        global $DB;
        return $DB->get_records('event', [
            'courseid' => $courseid,
            'visible' => 1,
            'eventtype' => 'course',
        ], 'timestart');
    }

    /**
     * Get all Teachers.
     *
     * @param int $courseid
     *
     * @return array
     *
     */
    public function get_teachers(int $courseid): array {
        $context = \context_course::instance($courseid);
        $users = get_enrolled_users($context, 'moodle/role:assign', 0, 'u.*', null, 0, 0, true);
        $teachers = [];

        foreach ($users as $user) {
            if (user_has_role_assignment($user->id, 3, $context->id)) {
                $teachers[] = [
                    'userid' => (int) $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'academic' => self::get_user_profile_field($user->id, 'academic'),
                    'stations' => self::get_user_profile_field($user->id, 'stations'),
                    'focus' => self::get_user_profile_field($user->id, 'focus'),
                ];
            }
        }
        return $teachers;
    }

    /**
     * Get Userprofilefields.
     *
     * @param int $userid
     * @param string $shortname
     *
     * @return string|null
     *
     */
    public static function get_user_profile_field(int $userid, string $shortname): ?string {
        global $DB;
        static $fieldsbyname = [];

        if (!isset($fieldsbyname[$shortname])) {
            $fieldsbyname[$shortname] = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        }

        if (!$fieldsbyname[$shortname]) {
            return null;
        }
        // TODO: Nicht immer in die DB schauen.
        return $DB->get_field('user_info_data', 'data', [
            'userid' => $userid,
            'fieldid' => $fieldsbyname[$shortname],
        ]) ?: null;
    }

    /**
     * Get the Cohort.
     *
     * @param int $categoryid
     *
     * @return string|null
     *
     */
    public function get_cohort(int $categoryid): ?string {
        global $DB;

        if (!isset($this->coursecategories[$categoryid])) {
            return null;
        }

        $category = core_course_category::get($categoryid);
        $path = explode('/', $category->path);

        if (count($path) > 2) {
            $targetid = (int) $path[2];
            return $DB->get_field('course_categories', 'name', ['id' => $targetid]) ?: null;
        }
        return null;
    }
}
