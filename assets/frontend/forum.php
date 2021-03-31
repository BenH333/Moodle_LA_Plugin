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



require(__DIR__.'/../../context.php');
require_once(__DIR__.'/../../stats_library.php');
require_once(__DIR__.'/../menu/navigation_menu.php');

$stats_library = new course_activity();

$PAGE->set_url('/mod/learninganalytics/assets/frontend/forum.php', array('id' => $cm->id));

echo $OUTPUT->header();

echo '<h1>Forum Engagement</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);

//One Discussion per Forum
$singleDiscussions = $stats_library->getSingleDiscussions($course);
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
$multipleForums = $stats_library->getMultipleDiscussions($course);
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
$limitedForums = $stats_library->getLimitedDiscussions($course);
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
