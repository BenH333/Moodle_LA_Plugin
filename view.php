<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_learninganalytics.
 *
 * @package     mod_learninganalytics
 * @copyright   2021 Ben Hadden
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

// Course_module ID, or
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$l  = optional_param('l', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('learninganalytics', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('learninganalytics', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($l) {
    $moduleinstance = $DB->get_record('learninganalytics', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('learninganalytics', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_learninganalytics'));
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

// $event = \mod_learninganalytics\event\course_module_viewed::create(array(
//     'objectid' => $moduleinstance->id,
//     'context' => $modulecontext
// ));
// $event->add_record_snapshot('course', $course);
// $event->add_record_snapshot('learninganalytics', $moduleinstance);
// $event->trigger();

$PAGE->set_url('/mod/learninganalytics/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

echo $OUTPUT->header();

//$user_records = get_enrolled_users($modulecontext, '', 0, '*');
//$users = array_keys($user_records);

$activities = get_array_of_activities($course->id);

//get users with capability to submit an activity i.e. students
//$student_records = get_users_by_capability($modulecontext, 'mod/assign:submit');

//Get all students
$role_id = 5;
$student_records = $DB->get_records_sql('SELECT u.id, u.username 
                                            FROM  {user} AS u
                                            INNER JOIN {context} AS c
                                                    on u.id = c.instanceid
                                            INNER JOIN {role_assignments} AS a
                                                    on a.userid = u.id
                                            WHERE a.roleid ='. $role_id.' 
                                        ');

$students = array_keys($student_records);
//print_r($student_records);

//Example SQL Query get student log
//print_r($DB->get_records_sql('SELECT * FROM {logstore_standard_log} WHERE userid=3'));
$course_views = 0;
$time_created = [];
$logs = [];

foreach($students as $student){
    //log each action
    $log = $DB->get_records_sql('SELECT id, userid, timecreated, action FROM {logstore_standard_log} WHERE courseid='.$course->id.' AND userid='.$student);
    array_push($logs,$log);
}

$logs = json_decode(json_encode($logs), true);
//From logs 
foreach($logs as $student_log){
    foreach($student_log as $course_log){
        $course_views ++;
        array_push($time_created,$course_log['timecreated']);
    }
}
//print_r($logs);
//print_r($time_created);

$templateContext = (object)[
    'title' => 'Overall Student Engagement',
    //'course_name' => $course->fullname,
    'student_count' => count($students),
    'course_views' => $course_views,
    'created_at' => $time_created
];

echo $OUTPUT->render_from_template('mod_learninganalytics/view', $templateContext);
echo $OUTPUT->footer();
