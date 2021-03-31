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

require(__DIR__.'/../../../../config.php');
require_once(__DIR__.'/../../lib.php');
require_once(__DIR__.'/../../activity_library.php');
require_once(__DIR__.'/../charts/render_student_charts.php');
require_once(__DIR__.'/../menu/navigation_menu.php');
$CFG->cachejs = false;

// Course_module ID, or
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$l  = optional_param('l', 0, PARAM_INT);

$user = optional_param('u', 0, PARAM_INT);

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


$PAGE->set_url('/mod/learninganalytics/assets/frontend/student.php', array('id' => $cm->id, 'user' =>$user));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->jquery();
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));

echo $OUTPUT->header();

//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);

//summary of quiz scores, forum posts and login frequency
$library = new student_activity();
$charts = new render_student_charts();

//header
$student = $library->getStudentFromId($user);
$dir= "/mod/learninganalytics/assets/frontend/activities.php?id=$id";
echo "<h1>Course Analytics for: ".$student['firstname']." ".$student['lastname']."</h1>";
echo "<button>".html_writer::link($CFG->wwwroot.$dir,'Return')."</button>";

//quizzes
$quiz_result = $library->quizGrades($course,$user);
$quiz_chart = $charts->quiz($quiz_result);
echo '<div class="overall_activity">';
echo $OUTPUT->render($quiz_chart);
echo '</div>';

//forums
$forumData = $library->forumActivity($course,$user);
$discussions = $forumData[0];
$posts = $forumData[1];
$forum_chart = $charts->forum($discussions,$posts);
echo '<div class="overall_activity">';
echo $OUTPUT->render($forum_chart);
echo '</div>';

//coursework submissions
[$labels, $assignmentTime] = $library->getAssignmentSubmissionTime($course,$user);
$table = new html_table();
$table->head = array('Coursework','Submission Time');
foreach ($labels as $coursework) {
    
    $coursework = $coursework;
    $time = $assignmentTime[$coursework];
    $table->data[] = array($coursework, $time);
}

echo html_writer::table($table);

echo $OUTPUT->footer();