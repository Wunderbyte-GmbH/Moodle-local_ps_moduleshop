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
    public function get_teachers(int $courseid) {
        global $DB;
        $context = context_course::instance($courseid);
        $allcontexts = str_replace('/', ',', substr($context->path, 1));

        $dbfamily = $DB->get_dbfamily();

        switch ($dbfamily) {
            case 'postgress':
                $sql = "SELECT ra.id, r.id AS roleid, r.name AS rolename, r.shortname AS roleshortname, u.id AS userid,
                u.firstname, u.lastname, u.email,
                MAX(CASE WHEN muif.shortname = 'academic' THEN muid.data END) AS academic,
                MAX(CASE WHEN muif.shortname = 'stations' THEN muid.data END) AS stations,
                MAX(CASE WHEN muif.shortname = 'focus' THEN muid.data END) AS focus
                FROM {role_assignments} ra
                    INNER JOIN {role} r ON ra.roleid = r.id
                    INNER JOIN {user} u ON ra.userid = u.id
                    INNER JOIN {user_info_data} muid ON muid.userid = u.id
                    INNER JOIN {user_info_field} muif ON muid.fieldid = muif.id
                WHERE ra.contextid IN ($allcontexts)
                        AND component = ''
                        AND r.id = 3
                GROUP BY ra.id, r.id, r.name, r.shortname, u.id, u.firstname, u.lastname, u.email";
                break;
            case 'mysql':
                $sql = "SELECT ra.id, r.id roleid, r.name rolename, r.shortname roleshortname,
                u.id userid, u.firstname, u.lastname, u.email,
                MAX(CASE WHEN muif.shortname = 'academic' THEN muid.data END) as academic,
                MAX(CASE WHEN muif.shortname = 'stations' THEN muid.data END) as stations,
                MAX(CASE WHEN muif.shortname = 'focus' THEN muid.data END) as focus
                FROM {role_assignments} ra
                    INNER JOIN {role} r ON ra.roleid = r.id
                    INNER JOIN {user} u ON ra.userid = u.id
                    INNER JOIN {user_info_data} muid ON muid.userid = u.id
                    INNER JOIN {user_info_field} muif ON muid.fieldid = muif.id
                WHERE ra.contextid IN ($allcontexts)
                    AND (component = '')
                    AND (r.id = 3)
                GROUP BY muid.userid;";
                break;
        }

        $records = $DB->get_records_sql($sql);
        $teachers = [];
        foreach ($records as $record) {
            $teachers[] = [
                        "userid" => intval($record->userid),
                        "firstname" => strip_tags($record->firstname),
                        "lastname" => strip_tags($record->lastname),
                        "academic" => $record->academic,
                        "stations" => $record->stations,
                        "focus" => $record->focus,
            ];
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
