<?php 
//moodleform is defined in formslib.php
// require_once("$CFG->libdir/formslib.php");
// require(__DIR__.'/../../config.php');
// require_once(__DIR__.'/lib.php');

class navigation_menu {
    //Add elements to form
    public function create_menu($id) {
        global $CFG;

        $home="/mod/learninganalytics/view.php?id=$id";
        $quiz="/mod/learninganalytics/assets/frontend/quiz.php?id=$id";
        $forum="/mod/learninganalytics/assets/frontend/forum.php?id=$id";
        $assignment="/mod/learninganalytics/assets/frontend/assignment.php?id=$id";
        $activities="/mod/learninganalytics/assets/frontend/activities.php?id=$id";
        $predictions="/mod/learninganalytics/assets/frontend/predictions.php?id=$id";

        $links = [
                    html_writer::link($CFG->wwwroot.$home,'Overview'),
                    html_writer::link($CFG->wwwroot.$quiz,'Quiz'),
                    html_writer::link($CFG->wwwroot.$forum,'Forum'),
                    html_writer::link($CFG->wwwroot.$assignment,'Assignment'),
                    html_writer::link($CFG->wwwroot.$activities,'Activities'),
                    ];

        echo '<ul class="menu">';
        foreach($links as $link){
            echo "<li>$link</li>";
        }
        echo '</ul>';
        // echo html_writer::select($options,'select a value','value4');
        // $mform = $this->_form; // Don't forget the underscore! 
 
        // $mform->addElement('select', 'type', 'Select an Activity:', $options);
        
    }
    
}