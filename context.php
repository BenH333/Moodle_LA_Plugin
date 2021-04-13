<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/assets/menu/navigation_menu.php');
require_once(__DIR__.'/assets/charts/render_activity_charts.php');
require_once(__DIR__.'/stats_library.php');

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

$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->requires->jquery();
$PAGE->requires->css(new moodle_url('/mod/learninganalytics/assets/style.css'));

$stats_library = new course_activity();
$charts = new render_activity_charts();
$activity_library = new student_activity();
$isStudent = $activity_library->isStudent($course,$USER);
?>