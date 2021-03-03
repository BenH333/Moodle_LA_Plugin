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
require_once(__DIR__.'/../../library.php');
require_once(__DIR__.'/../navigation_menu.php');
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

$PAGE->set_url('/mod/learninganalytics/assets/frontend/quiz.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url('/mod/learninganalytics/assets/ajax.js'));
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));
echo $OUTPUT->header();

echo '<h1>Quiz Engagement</h1>';//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);

$student_records = $library->getStudentRecords($course);

[$attempts, $quiz_result] = $library->quizGrades($course,$student_records);
$chart = new core\chart_bar();

$labels=array();

$gradesA=array();
$gradesB=array();
$gradesC=array();
$gradesD=array();

//There are many quizzes
foreach($quiz_result as $key=>$quiz){
    array_push($labels,$key);

    $gradeA=0;
    $gradeB=0;
    $gradeC=0;
    $gradeD=0;
    
    //There are many results within a quiz
    foreach($quiz as $result){
        if($result <=49){
            $gradeD++;
        }elseif($result <=59){
            $gradeC++;
        }elseif($result <=69){
            $gradeB++;
        }elseif($result <=100){
            $gradeA++;
        }
    }

    array_push($gradesA,$gradeA);
    array_push($gradesB,$gradeB);
    array_push($gradesC,$gradeC);
    array_push($gradesD,$gradeD);
}

$seriesA = new \core\chart_series('Grade A (>70%)', $gradesA);
$seriesB = new \core\chart_series('Grade B (>60%)', $gradesB);
$seriesC = new \core\chart_series('Grade C (>50%)', $gradesC);
$seriesD = new \core\chart_series('Grade D (<49%)', $gradesD);

// print_r($gradesC);
$chart->add_series($seriesA);
$chart->add_series($seriesB);
$chart->add_series($seriesC);
$chart->add_series($seriesD);
$chart->set_stacked(true);
$chart->set_title("Performance Distribution per Quiz with: $attempts overall attempts");
$chart->set_labels($labels);

echo '<div class="overall_activity">';
echo $OUTPUT->render($chart);
echo '</div>';

[$quiz_attempts,$no_attempts] = $library->quizCompletion($course, $student_records);

$seriesAttempted = new \core\chart_series('Attempted', $quiz_attempts);
$seriesNotAttempted = new \core\chart_series('Not Attempted', $no_attempts);

$completion_chart = new core\chart_bar();
$completion_chart->set_labels($labels);
$completion_chart->set_stacked(true);
$completion_chart->set_title("Percentage of students attempts");
$completion_chart->add_series($seriesAttempted);
$completion_chart->add_series($seriesNotAttempted);


echo '<div class="overall_activity">';
echo $OUTPUT->render($completion_chart);
echo '</div>';

echo $OUTPUT->footer();
