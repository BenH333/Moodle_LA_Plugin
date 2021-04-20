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

require_once(__DIR__.'/context.php');

$PAGE->set_url('/mod/learninganalytics/view.php', array('id' => $cm->id));

//header
echo $OUTPUT->header();

echo '<h1>Student Engagement</h1>';
$menu = new navigation_menu();
$menu->create_menu($id,$course,$USER);

$activities = get_array_of_activities($course->id);

//get users with capability to submit an activity i.e. students
$student_records = $stats_library->getStudentRecords($course);
$students = array_keys($student_records);

//log data might not be needed
$log_data = $stats_library->courseAccess($student_records,$course);

$course_views = $log_data[0];
$time_created = $log_data[1];
$logs = $log_data[2];

//course views
$chart_time = array_count_values($time_created);
$date_count =[]; 
$dates =[]; 

foreach($chart_time as $key => $value){
    array_push($dates,$key);
    array_push($date_count,$value);
}

$course_views_chart = $charts->course_views($dates,$date_count);

echo '<div class="overall_activity">';
echo $OUTPUT->render($course_views_chart);
echo '</div>';

//resource access
$course_modules = $stats_library->resourceAccess($course, $student_records);
$resource_chart = $charts->resource($course_modules);

echo '<div class="resource_access">';
echo $OUTPUT->render($resource_chart);
echo '</div>';

//submissions
$submissions_with_late = $stats_library->lateSubmissions($course, $student_records);
$submissions_chart = $charts->submissions($submissions_with_late);

echo '<div class="submissions">';
echo $OUTPUT->render($submissions_chart);
echo '</div>';

echo $OUTPUT->footer();
