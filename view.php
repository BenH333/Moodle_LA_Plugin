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
require_once(__DIR__.'/assets/navigation_menu.php');

$CFG->cachejs = false;

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

$PAGE->set_url('/mod/learninganalytics/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url('/mod/learninganalytics/assets/ajax.js'));
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));
echo $OUTPUT->header();

$activities = get_array_of_activities($course->id);

//get users with capability to submit an activity i.e. students
//$student_records = get_users_by_capability($modulecontext, 'mod/assign:submit');

$student_records = $library->getStudentRecords($course);

$students = array_keys($student_records);

$log_data = $library->courseAccess($student_records,$course);
$course_views = $log_data[0];
$time_created = $log_data[1];
$logs = $log_data[2];

$course_modules = $library->resourceAccess($course, $student_records);

$late_submissions = $library->lateSubmissions($course, $student_records);

$templateContext = (object)[
    'title' => 'Overall Student Engagement',
    'student_count' => count($students),
    'course_views' => $course_views,
    'created_at' => $time_created,
];
 
$chart_time = array_count_values($time_created);
$date_count =[]; 
$dates =[]; 

foreach($chart_time as $key => $value){
    array_push($dates,$key);
    array_push($date_count,$value);
}

$chart = new core\chart_line;
$chart_series = new core\chart_series('Number of Course Views',$date_count);
$chart->set_title('Time of Course Access');
$chart->add_series($chart_series);
$yaxis = new core\chart_axis;
$yaxis->set_stepsize(1);
$yaxis->set_min(0);
$chart->set_yaxis($yaxis);
$chart->set_labels($dates);

$pie_chart = new core\chart_pie;
$pie_series = new core\chart_series("Resource Views", $course_modules[1]);
$pie_chart->set_title("Resources Accessed by Students");
$pie_chart->add_series($pie_series);
$pie_chart->set_labels($course_modules[0]);

$sub_chart = new core\chart_pie;
$sub_chart->set_doughnut(true);
$sub_series = new core\chart_series("Assignment Submissions", $late_submissions);
$sub_chart->set_title("Student Assignments");
$sub_chart->add_series($sub_series);
$sub_chart->set_labels(["Late Submissions","Submitted","Not-Submitted"]);

echo '<h1>Student Engagement</h1>';

$menu = new navigation_menu();
$menu->create_menu($id);

echo '<div class="overall_activity">';
echo $OUTPUT->render($chart);
echo '</div>';

echo '<div class="resource_access">';
echo $OUTPUT->render($pie_chart);
echo '</div>';

echo '<div class="submissions">';
echo $OUTPUT->render($sub_chart);
echo '</div>';

echo $OUTPUT->render_from_template('mod_learninganalytics/view', $templateContext);

echo $OUTPUT->footer();
