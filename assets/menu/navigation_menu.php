<?php
class navigation_menu {
    //Add elements to form
    public function create_menu($id) {
        global $CFG;

        $home="/mod/learninganalytics/view.php?id=$id";
        $quiz="/mod/learninganalytics/assets/frontend/quiz.php?id=$id";
        $forum="/mod/learninganalytics/assets/frontend/forum.php?id=$id";
        $assignment="/mod/learninganalytics/assets/frontend/assignment.php?id=$id";
        $students="/mod/learninganalytics/assets/frontend/students.php?id=$id";

        $links = [
                    html_writer::link($CFG->wwwroot.$home,'Overview'),
                    html_writer::link($CFG->wwwroot.$quiz,'Quiz'),
                    html_writer::link($CFG->wwwroot.$forum,'Forum'),
                    html_writer::link($CFG->wwwroot.$assignment,'Assignment'),
                    html_writer::link($CFG->wwwroot.$students,'Students'),
                    ];

        echo '<ul class="menu">';
        foreach($links as $link){
            echo "<li>$link</li>";
        }
        echo '</ul>';
    }
    
}