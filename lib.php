<?php

require_once(dirname(__FILE__).'/../../config.php');

class ps_moduleshop {
    /**
     * [Description for $coursecategories]
     *
     * @var [type]
     */
    public $coursecategories = [];

    public function __construct() {
        global $DB;
        // Prefetch Course Categories
        $this->coursecategories = $DB->get_records_sql('SELECT id, name, path FROM {course_categories};');
    }

    public function get_export() {
        $export = $this->get_courses();
        $ret = array();
        foreach ($export as $course) {
            $item = new stdClass();
            $item->id = intval($course->id);
            $item->fullname = $course->fullname;
            $item->shortname = $course->shortname;
            $item->summary = $course->summary;
            $item->startdate = $course->startdate;
            $item->enddate = $course->enddate;
            $item->cohort = $this->get_cohort($course->category);
            $item->coursefields = $this->get_customfields($course->id);

            if (isset($item->coursefields->psigenerell) && $item->coursefields->psigenerell === 'Ja') {
                $ret[$course->id] = $item;
            } else {
                continue;
            }
            $item->teachers = $this->get_lehrende($course->id);
            $item->events = $this->get_events($course->id);
        }
        return $ret;
    }

    public function get_courses() {
        global $DB;
        return array_values($DB->get_records_sql('SELECT id, fullname, shortname, summary, category, startdate, enddate FROM {course};'));
    }

    public function get_customfields($courseid) {
        global $DB;
        $records = $DB->get_records_sql('SELECT f.id, f.shortname, f.name, f.type, f.configdata, d.value
FROM {customfield_field} f
JOIN {customfield_data} d ON f.id = d.fieldid
WHERE d.instanceid = ?;', array($courseid));
        $ret = new stdClass();
        foreach ($records as $record) {
            if ($record->type == 'select') {
                $opts = json_decode($record->configdata);
                $opts = explode("\r\n", $opts->options);
                $ret->{$record->shortname} = $opts[$record->value-1] ?? '';
            } elseif ($record->type == 'date') {
                $ret->{$record->shortname} =  intval($record->value); # Unixtimestamp
            } elseif ($record->type == 'checkbox') {
                $ret->{$record->shortname} = $record->value == 1 ? true : false;
            } else {
                $ret->{$record->shortname} =  $record->value;
            }
        }
        return $ret;
    }

    public function get_events($courseid) {
        global $DB;
        $records = $DB->get_records_sql('SELECT id, name, description, timestart, timeduration, repeatid
FROM {event}
WHERE courseid = ? AND visible = 1 AND eventtype = \'course\'
ORDER BY timestart;', array($courseid));
        foreach ($records as $record) {
            $record->id = intval($record->id);
            $record->timestart = intval($record->timestart);
            $record->timeduration = intval($record->timeduration);
            $record->repeatid = intval($record->repeatid);
        }
        return $records;
    }

    public function get_lehrende($courseid) {
        global $DB;
        $context = context_course::instance($courseid);
        $allcontexts = str_replace('/', ',', substr($context->path, 1));
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

        $records =  $DB->get_records_sql($sql);
        $ret = array();
        foreach ($records as $record) {
            $ret[] = array(
                        "userid" => intval($record->userid),
                        "firstname" => strip_tags($record->firstname),
                        "lastname" => strip_tags($record->lastname),
                        "academic" => $record->academic,
                        "stations" => $record->stations,
                        "focus" => $record->focus
                    );
        }
        return $ret;
    }

    public function get_cohort($categoryid) {
        if (isset($this->coursecategories[$categoryid])) {
            $path = explode('/',$this->coursecategories[$categoryid]->path);
        } else {
            return null;
        }
        if (sizeof($path) > 2) {
            return $this->coursecategories[$path[2]]->name;
        } else {
            return null;
        }
    }
}
?>
