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

$PAGE->set_url('/mod/learninganalytics/assets/frontend/assignment.php', array('id' => $cm->id));

echo $OUTPUT->header();

echo '<h1>Assignment Engagement</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id,$course,$USER);

[$labels, $assignTimeCount] = $stats_library->getAssignmentSubmissionTime($course);
$chart = $charts->assignments($assignTimeCount,$labels);
echo '<div class="overall_activity">';
echo $OUTPUT->render($chart);
echo '</div>';
echo $OUTPUT->footer();
