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
require_once(__DIR__.'/library.php');


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

$library = new database_calls();
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
// $PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/learninganalytics/assets/plotly-latest.min.js'));
$PAGE->requires->js(new moodle_url('/mod/learninganalytics/assets/ajax.js'));

$PAGE->requires->js_call_amd('module.js','init');


echo $OUTPUT->header();

$activities = get_array_of_activities($course->id);

//get users with capability to submit an activity i.e. students
//$student_records = get_users_by_capability($modulecontext, 'mod/assign:submit');

$student_records = $library->getStudentRecords($course);

$students = array_keys($student_records);

$log_data = $library->getLogData($student_records,$course);
$course_views = $log_data[0];
$time_created = $log_data[1];
$logs = $log_data[2];

$course_modules = $library->getCourseModules($course);

$templateContext = (object)[
    'title' => 'Overall Student Engagement',
    'student_count' => count($students),
    'course_views' => $course_views,
    'created_at' => $time_created,
    'activities' => $course_modules,
    'courseid' => $course->id
];

echo $OUTPUT->render_from_template('mod_learninganalytics/view', $templateContext);
echo $OUTPUT->footer();
