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

$PAGE->set_url('/mod/learninganalytics/assets/frontend/forum.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url('/mod/learninganalytics/assets/ajax.js'));
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));
echo $OUTPUT->header();

echo '<h1>Forum Engagement</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);

//One Discussion per Forum
$singleDiscussions = $library->getSingleDiscussions($course);
$labelsSingleDiscussion = array();
$countSingleDiscussion = array();

foreach($singleDiscussions as $singleDiscussion){
    array_push($labelsSingleDiscussion, $singleDiscussion[0]);
    array_push($countSingleDiscussion, $singleDiscussion[1]);
}

$singleDiscussionChart = new core\chart_bar();
$seriesPosts = new \core\chart_series('Posts', $countSingleDiscussion);
$singleDiscussionChart->add_series($seriesPosts);
$singleDiscussionChart->set_labels($labelsSingleDiscussion);
$singleDiscussionChart->set_title('Posts in Single Discussion Forums');

echo '<div class="overall_activity">';
echo $OUTPUT->render($singleDiscussionChart);
echo '</div>';

//Multiple Discussions per Forum
$multipleForums = $library->getMultipleDiscussions($course);
$labelForums=$multipleForums[0];
$multipleDiscussions=$multipleForums[1];
$multiplePosts=$multipleForums[2];

$multDiscSeries = new \core\chart_series('Discussions',$multipleDiscussions);
$multDiscPostSeries = new \core\chart_series('Posts',$multiplePosts);


$multipleDiscussionsChart = new core\chart_bar();
$multipleDiscussionsChart->set_labels($labelForums);
$multipleDiscussionsChart->add_series($multDiscSeries);
$multipleDiscussionsChart->add_series($multDiscPostSeries);

echo '<div class="overall_activity">';
echo $OUTPUT->render($multipleDiscussionsChart);
echo '</div>';

//Limited Discussions per Forum
$limitedForums = $library->getLimitedDiscussions($course);
$labelLimited=$limitedForums[0];
$limitedDiscussions=$limitedForums[1];
$limitedPosts=$limitedForums[2];

$limitedDiscSeries = new \core\chart_series('Discussions',$limitedDiscussions);
$limitedDiscPostSeries = new \core\chart_series('Posts',$limitedPosts);

$limitedDiscussionsChart = new core\chart_bar();
$limitedDiscussionsChart->set_labels($labelLimited);
$limitedDiscussionsChart->add_series($limitedDiscSeries);
$limitedDiscussionsChart->add_series($limitedDiscPostSeries);

echo '<div class="overall_activity">';
echo $OUTPUT->render($limitedDiscussionsChart);
echo '</div>';

echo $OUTPUT->footer();
