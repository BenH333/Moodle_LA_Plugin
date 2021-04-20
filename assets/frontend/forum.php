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
require_once(__DIR__.'/../charts/render_activity_charts.php');

$stats_library = new course_activity();
$charts = new render_activity_charts();

$PAGE->set_url('/mod/learninganalytics/assets/frontend/forum.php', array('id' => $cm->id));

echo $OUTPUT->header();

echo '<h1>Forum Engagement</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id,$course,$USER);

// Multiple Discussions per Forum
$multipleForums = $stats_library->getMultipleDiscussions($course);

$multipleDiscussionsChart = $charts->multi_discussion_forum($multipleForums);

echo '<div class="overall_activity">';
echo $OUTPUT->render($multipleDiscussionsChart);
echo '</div>';

// One Discussion per Forum
$singleDiscussions = $stats_library->getSingleDiscussions($course);

$singleDiscussionChart = $charts->single_discussion_forum($singleDiscussions);

echo '<div class="submissions">';
echo $OUTPUT->render($singleDiscussionChart);
echo '</div>';



// Limited Discussions per Forum
$limitedForums = $stats_library->getLimitedDiscussions($course);

$limitedDiscussionsChart = $charts->limited_discussion_forum($limitedForums);
echo '<div class="resource_access">';
echo $OUTPUT->render($limitedDiscussionsChart);
echo '</div>';

echo $OUTPUT->footer();
