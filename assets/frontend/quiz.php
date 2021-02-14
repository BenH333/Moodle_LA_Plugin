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
// $PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/learninganalytics/assets/plotly-latest.min.js'));
$PAGE->requires->js(new moodle_url('/mod/learninganalytics/assets/ajax.js'));
// $PAGE->requires->js_call_amd('module.js','debug');
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));
echo $OUTPUT->header();

echo '<h1>Quiz Engagement</h1>';
 
//menu to select pages
$menu = new navigation_menu();
$menu->create_menu($id);



echo $OUTPUT->footer();
