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

require_once(__DIR__.'/../charts/render_student_charts.php');
require(__DIR__.'/../../context.php');

// ... user id
$user  = required_param('u', PARAM_INT);

$PAGE->set_url('/mod/learninganalytics/assets/frontend/student.php', array('id' => $cm->id, 'user' =>$user));

echo $OUTPUT->header();

//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id,$course,$USER);
if($isStudent == false){
    //summary of quiz scores, forum posts and login frequency
    
    $charts = new render_student_charts();

    //header
    $student = $activity_library->getStudentFromId($user);
    $dir= "/mod/learninganalytics/assets/frontend/students.php?id=$id";
    echo "<h1>Course Engagement Review for: ".$student['firstname']." ".$student['lastname']."</h1>";
    echo html_writer::link($CFG->wwwroot.$dir,'Return',array('class'=>'btn btn-outline-primary'));

    //quizzes
    $quiz_result = $activity_library->quizGrades($course,$user);
    $quiz_chart = $charts->quiz($quiz_result);
    echo '<div class="overall_activity">';
    echo $OUTPUT->render($quiz_chart);
    echo '</div>';

    //forums
    $forumData = $activity_library->forumActivity($course,$user);
    $discussions = $forumData[0];
    $posts = $forumData[1];
    $forum_chart = $charts->forum($discussions,$posts);
    echo '<div class="overall_activity">';
    echo $OUTPUT->render($forum_chart);
    echo '</div>';

    //coursework submissions
    [$labels, $assignmentTime] = $activity_library->getAssignmentSubmissionTime($course,$user);
    $table = new html_table();
    $table->head = array('Coursework','Submission Time');
    foreach ($labels as $coursework) {
        
        $coursework = $coursework;
        $time = $assignmentTime[$coursework];
        $table->data[] = array($coursework, $time);
    }

    echo html_writer::table($table);
}
echo $OUTPUT->footer();