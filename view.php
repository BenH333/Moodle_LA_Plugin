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
$PAGE->requires->jquery();
echo $OUTPUT->header();

$activities = get_array_of_activities($course->id);

//get users with capability to submit an activity i.e. students
//$student_records = get_users_by_capability($modulecontext, 'mod/assign:submit');

//https://stackoverflow.com/questions/22161606/sql-query-for-courses-enrolment-on-moodle
//SQL Query to get all students in current course
//Finding the current course reduces processing time when getting a student object
//Distinct returns only one of every user incase there are any duplicate users
//CHECKS:
// the user is enrolled 
// the user is not deleted
// the user is not suspended
// the role shortname is student
// the user context is from a course(50)
// the user enrolment has not ended
// the course id is the current course
$student_records = $DB->get_records_sql(' SELECT DISTINCT u.id AS userid, c.id AS courseid
                                            FROM mdl_user u
                                            JOIN mdl_user_enrolments ue ON ue.userid = u.id
                                            JOIN mdl_enrol e ON e.id = ue.enrolid
                                            JOIN mdl_role_assignments ra ON ra.userid = u.id
                                            JOIN mdl_context ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                            JOIN mdl_course c ON c.id = ct.instanceid AND e.courseid ='.$course->id.'
                                            JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = "student"
                                            WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                            AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP(NOW())) AND ue.status = 0
                                        ');

$students = array_keys($student_records);

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

$modules = $DB->get_records_sql('SELECT DISTINCT m.module AS module
                                 FROM mdl_course_modules m
                                 WHERE visible=1 AND course='.$course->id);

$modules = json_decode(json_encode($modules), true);
$course_modules =[];
foreach($modules as $module){
    $activity_modules = $DB->get_record('modules',array('id' =>$module['module']));
    array_push($course_modules,$activity_modules->name);
}

//print_r($course_modules);
//Get Module Name from id
// $activity_modules = $DB->get_record('modules',array('id' =>$module['module']));
// $dynamic_activity_modules_data = $DB->get_record($activity_modules->name,array('id' =>$instanceid));
// echo $dynamic_activity_modules_data->name;


$templateContext = (object)[
    'title' => 'Overall Student Engagement',
    //'course_name' => $course->fullname,
    'student_count' => count($students),
    'course_views' => $course_views,
    'created_at' => $time_created,
    'activities' => $course_modules
];

echo $OUTPUT->render_from_template('mod_learninganalytics/view', $templateContext);
echo $OUTPUT->footer();
