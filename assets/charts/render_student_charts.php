<?php 

class render_student_charts{

    public static function quiz($quiz_result){
        $quiz_chart = new core\chart_bar();
        $labels = array();

        $gradesA=array();
        $gradesB=array();
        $gradesC=array();
        $gradesD=array();

        //There are many quizzes
        foreach($quiz_result as $key=>$quiz){
            array_push($labels,$key);
            // array_push($results,$quiz);
            $gradeA=0;
            $gradeB=0;
            $gradeC=0;
            $gradeD=0;
            
            //There are many results within a quiz
            if($quiz <=49){
                $gradeD = $quiz;
            }elseif($quiz <=59){
                $gradeC = $quiz;
            }elseif($quiz <=69){
                $gradeB = $quiz;
            }elseif($quiz <=100){
                $gradeA = $quiz;
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

        $quiz_chart->add_series($seriesA);
        $quiz_chart->add_series($seriesB);
        $quiz_chart->add_series($seriesC);
        $quiz_chart->add_series($seriesD);


        $quiz_chart->set_title("Performance Distribution per Quiz");
        $quiz_chart->set_labels($labels);
        return $quiz_chart;
    } 


    public static function forum($discussions, $posts){
        $labelsForum=array();

        foreach($discussions as $key=>$discussion){
            array_push($labelsForum,$key);
        }

        //forums
        $forumChart = new core\chart_bar();
        $forumChart->set_labels($labelsForum);

        $seriesDiscussions = new \core\chart_series('Discussions', array_values($discussions));
        $seriesPosts = new \core\chart_series('Posts', array_values($posts));

        $forumChart->add_series($seriesDiscussions);
        $forumChart->add_series($seriesPosts);

        return $forumChart;
    }

    // public static function submissions($labels,$assignTime){
    //     $coursework_chart = new \core\chart_pie();
    //     $coursework_chart->set_labels($labels);
    //     $coursework_chart->set_title("Coursework Submissions over Time");

    //     // print_r($labels);
    //     print_r($assignTime);
    //     $series = new core\chart_series('Coursework Submission', array_values($assignTime));

    //     $coursework_chart->add_series($series);
    //     // foreach($assignTimeCount as $assignment){
    //     //     $countOfDates=array();

    //     //     foreach($labels as $label_date){
    //     //         if(isset($assignment[1][$label_date])){
    //     //             array_push($countOfDates, $assignment[1][$label_date]);
    //     //         }else{
    //     //             array_push($countOfDates, 0);
    //     //         }
    //     //     }
    //     //     // print_r($labels);
    //     //     $series = new core\chart_series($assignment[0],$countOfDates);
    //     //     $coursework_chart->add_series($series);
    //     // }
    //     return $coursework_chart;
    // }
}