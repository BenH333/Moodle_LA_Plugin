<?php 

class render_activity_charts{

    /*
        View.php
        Overall views on the course
    */
    public static function course_views($dates,$date_count){
        $course_views_chart = new core\chart_line;
        $course_series = new core\chart_series('Number of Course Views',$date_count);
        $course_views_chart->set_title('Time of Course Access');
        $course_views_chart->add_series($course_series);
        $yaxis = new core\chart_axis;
        $yaxis->set_stepsize(1);
        $yaxis->set_min(0);
        $course_views_chart->set_yaxis($yaxis);
        $course_views_chart->set_labels($dates);
        return $course_views_chart;
    }

    /*
        View.php
        Overall resource views on the course
    */
    public static function resource($course_modules){
        $resource_chart = new core\chart_pie;
        $resource_series = new core\chart_series("Resource Views", $course_modules[1]);
        $resource_chart->set_title("Resources Accessed by Students");
        $resource_chart->add_series($resource_series);
        $resource_chart->set_labels($course_modules[0]);
        return $resource_chart;
    }

    /*
        View.php
        Overall submissions on the course
    */
    public static function submissions($submissions){
        $submissions_chart = new core\chart_pie;
        $submissions_chart->set_doughnut(true);
        $sub_series = new core\chart_series("Assignment Submissions", $submissions);
        $submissions_chart->set_title("Student Assignments");
        $submissions_chart->add_series($sub_series);
        $submissions_chart->set_labels(["Late Submissions","Submitted","Not-Submitted"]);

        return $submissions_chart;
    }

    /*
        Quiz.php
        Overall Quiz Grades
    */
    public static function quiz_grades($quiz_result, $attempts,$labels){
        $quiz_grades_chart = new core\chart_bar();

        $gradesA=array();
        $gradesB=array();
        $gradesC=array();
        $gradesD=array();

        //There are many quizzes
        foreach($quiz_result as $quiz){

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
        $quiz_grades_chart->add_series($seriesA);
        $quiz_grades_chart->add_series($seriesB);
        $quiz_grades_chart->add_series($seriesC);
        $quiz_grades_chart->add_series($seriesD);
        $quiz_grades_chart->set_stacked(true);
        $quiz_grades_chart->set_title("Performance Distribution per Quiz with: $attempts overall attempts");
        $quiz_grades_chart->set_labels($labels);

        return $quiz_grades_chart;
    }

    public static function quiz_attempts($quiz_attempts,$no_attempts,$labels){
        $seriesAttempted = new \core\chart_series('Attempted', $quiz_attempts);
        $seriesNotAttempted = new \core\chart_series('Not Attempted', $no_attempts);

        $completion_chart = new core\chart_bar();
        $completion_chart->set_labels($labels);
        $completion_chart->set_stacked(true);
        $completion_chart->set_title("Percentage of students attempts");
        $completion_chart->add_series($seriesAttempted);
        $completion_chart->add_series($seriesNotAttempted);
        return $completion_chart;
    }

    public static function assignments($assignTimeCount,$labels){
        $chart = new \core\chart_line();
        $chart->set_labels($labels);
        $chart->set_title("Coursework Submissions over Time");
        
        foreach($assignTimeCount as $assignment){
            //assignment[0] == name
            //assignment[1] == time & count

            $countOfDates=array();
            foreach($labels as $label_date){
                if(isset($assignment[1][$label_date])){
                    array_push($countOfDates, $assignment[1][$label_date]);
                }else{
                    array_push($countOfDates, 0);
                }
            }

            $series = new core\chart_series($assignment[0],$countOfDates);
            $chart->add_series($series);
            
        }
        return $chart;
    }
}