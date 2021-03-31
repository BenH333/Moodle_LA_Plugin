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
require_once(__DIR__.'/../../activity_library.php');

$stats_library = new course_activity();

$PAGE->set_url('/mod/learninganalytics/assets/frontend/activities.php', array('id' => $cm->id));

echo $OUTPUT->header();

echo '<h1>Enrolled Students</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);


//table displays every enrolled student
$activity_library = new student_activity();
$profiles = $activity_library->getStudents($course);

$table = new html_table();
$table->head = array('Username','First Name', 'Last Name', 'Last Login', 'Action',);
foreach ($profiles as $prof) {
    
    $username = $prof->u_username;
    $first_name = $prof->u_fname;
    $last_name = $prof->u_lname;
    $session = $prof->u_lastlogin;

    $dir = "/mod/learninganalytics/assets/frontend/student.php?id=$id"."&u=$prof->userid";
    $link = html_writer::link($CFG->wwwroot.$dir,'View');

    $table->data[] = array($username, $first_name, $last_name, $session, $link);
}

echo html_writer::table($table);

echo $OUTPUT->footer();
