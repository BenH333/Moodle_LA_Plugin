<?php

class database_calls{
    //Figure out how to pass data between this class and ajax
    public static function getactivities($activity,$courseid){
        $object=[$activity,$courseid];
        return  json_encode(array_values($object));
    }

    public static function getStudentRecords($course){
        global $DB;
        //https://stackoverflow.com/questions/22161606/sql-query-for-courses-enrolment-on-moodle
        //https://docs.moodle.org/dev/New_enrolments_in_2.0
        //SQL Query to get all students in current course
        //Finding the current course reduces processing time when getting a student object
        //Distinct returns only one of every user incase there are any duplicate users
        //CHECKS:
        // dynamically that the user is enrolled on a course
        // the user is not deleted
        // the user is not suspended
        // the role shortname is student
        // the user context is from a course(50) = student
        // the user enrolment has not ended && status = 0(active)
        // the course id is the current course
        // the user is not enrolled as a guest or optional enrollment
        $student_records = $DB->get_records_sql('   SELECT DISTINCT u.id AS userid, c.id AS courseid
                                                    FROM mdl_user u
                                                    JOIN mdl_user_enrolments ue ON ue.userid = u.id
                                                    JOIN mdl_enrol e ON e.id = ue.enrolid
                                                    JOIN mdl_role_assignments ra ON ra.userid = u.id
                                                    JOIN mdl_context ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                                    JOIN mdl_course c ON c.id = ct.instanceid AND e.courseid ='.$course->id.'
                                                    JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = "student"
                                                    WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                                    AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP(NOW())) AND ue.status = 0
                                                ');
        return $student_records;
    }

    public static function courseAccess($student_records,$course){
        //retrieve all course "clicks" from database

        global $DB;
        $students = array_keys($student_records);

        $course_views = 0;
        $time_created = [];

        list($insql,$inparams) = $DB->get_in_or_equal($students);
        $sql = "SELECT * FROM {logstore_standard_log} WHERE courseid=$course->id AND target LIKE 'course' AND component LIKE 'core' AND userid $insql";
        $course_logs = $DB->get_records_sql($sql,$inparams);
        $course_views = count($course_logs);
        $logs = json_decode(json_encode($course_logs), true);

        foreach($logs as $view){
            $date_time = date('d-m-Y',$view['timecreated']);
            array_push($time_created,$date_time);
        }
        
        return array($course_views, $time_created, $logs);
    }

    public static function resourceAccess($course, $student_records){
        global $DB;
        $students = array_keys($student_records);
        $module_names = [];
        $module_usage = [];

        //Select course modules id from current course
        $modules = $DB->get_records_sql('   SELECT DISTINCT m.module AS m_id
                                            FROM mdl_course_modules m
                                            WHERE visible=1 AND course='.$course->id);

        $modules = json_decode(json_encode($modules), true);
        foreach($modules as $module){
            //get instances each module e.g. quiz 1, quiz 2
            $instances = $DB->get_records('course_modules',array('course'=>$course->id,'module'=>$module['m_id']));
            foreach($instances as $instance){
                $activity_module = $DB->get_record('modules',array('id' =>$instance->module)); //get module name e.g. quiz or forum
                if($activity_module->name != 'assign' && $activity_module->name != 'assignment'){
                    $dynamic_activity_modules_data = $DB->get_record($activity_module->name,array('id' =>$instance->instance)); 
                    $module_name = $dynamic_activity_modules_data->name;
                    list($insql,$inparams) = $DB->get_in_or_equal($students);
                    $sql = "SELECT id FROM {logstore_standard_log} WHERE courseid=$course->id AND objectid=$instance->instance AND objecttable='$activity_module->name' AND action='viewed' AND userid $insql";
                    $module_views = count($DB->get_records_sql($sql,$inparams));
                    array_push($module_names,$module_name);
                    array_push($module_usage,$module_views);
                }
            }
            
        }
        return [$module_names,$module_usage];

    }

    public static function lateSubmissions($course, $student_records){
        global $DB;
        $late_assignments=0;
        $submitted_assignments=0;
        $non_submissions =0;
        $assign_modules = $DB->get_records_sql("SELECT * FROM mdl_assign WHERE course=$course->id");
        $required_submissions = count(array_keys($student_records)) * count($assign_modules);

        // select 'assign' module id from current course
        $submitted_assign_modules = $DB->get_records_sql('  SELECT cm.id AS id, cm.module AS cm_id, m.id AS m_id, m.name AS m_name, a.id AS a_id, a.duedate AS a_due, s.timemodified AS s_sub
                                                            FROM mdl_course_modules cm
                                                            JOIN mdl_modules m ON m.id = cm.module
                                                            JOIN mdl_assign a ON a.course = cm.course
                                                            JOIN mdl_assign_submission s ON s.assignment = a.id
                                                            WHERE cm.visible=1 AND m.name="assign" AND s.status="submitted" AND cm.course='.$course->id );

        $submitted_assign_modules = json_decode(json_encode($submitted_assign_modules), true);
        // print_r($submitted_assign_modules);
        foreach($submitted_assign_modules as $module){
            if($module['s_sub'] > $module['a_due']){
                $late_assignments++;
            } else if($module['s_sub'] <= $module['a_due']){
                $submitted_assignments++;
            }
        }
        
        $non_submissions = $required_submissions - ($submitted_assignments + $late_assignments);
        return([$late_assignments, $submitted_assignments, $non_submissions]);
    }

    public static function getQuizzes($course){
        global $DB;

        $quizzes = $DB->get_records_sql("   SELECT cm.id AS cm_id, cm.module AS cm_module, cm.instance AS cm_instance, m.id AS m_id, m.name AS m_name, q.name AS q_name, q.id AS q_id, q.grade AS quiz_grade
        FROM mdl_course_modules cm
        JOIN mdl_modules m ON m.id = cm.module
        JOIN mdl_quiz q on q.id = cm.instance
        WHERE cm.visible=1 AND m.name='quiz' AND cm.course=$course->id");

        $quizzes = json_decode(json_encode($quizzes), true);
        return $quizzes;
    }

    public static function quizGrades($course, $student_records){
        global $DB;
        //Get all Quiz scores and categorise the average score per student
        //plot each grade % per quiz
        $participants_count=0;
        $quiz_result = array();

        $db = new database_calls;
        $quizzes = $db->getQuizzes($course);

        $quiz_result= array();
        foreach($quizzes as $quiz){
            $quiz_percentages= array();

            //Get all user scores from the queried quizzes
            $id= $quiz['q_id'];
            $quiz_grade = $quiz['quiz_grade'];
            $quiz_grades = $DB->get_records('quiz_grades',array('quiz' =>$id)); 
            $quiz_grades = json_decode(json_encode($quiz_grades), true);

            foreach($quiz_grades as $grade){
                $participants_count++;
                $percent = ($grade['grade'] / $quiz_grade) * 100;
                array_push($quiz_percentages,$percent);
            }
            $quiz_result[$quiz['q_name']] = $quiz_percentages;
        }
        return [$participants_count, $quiz_result];
    }

    public static function quizCompletion($course, $student_records){
        global $DB;
        //for every quiz find the percentage of students that have completed it

        $enrolled_students= count(array_keys($student_records));
        $quiz_completions=array();
        $uncompleted=array();

        $db = new database_calls;
        $quizzes = $db->getQuizzes($course);

        foreach($quizzes as $quiz){
            $id= $quiz['q_id'];
            $attempts = $DB->get_records('quiz_grades',array('quiz' =>$id)); 
            $attempted = (count($attempts) / $enrolled_students) * 100;
            $not_attempted = 100 - $attempted;
            array_push($quiz_completions, $attempted);
            array_push($uncompleted, $not_attempted);
        }

        return [$quiz_completions,$uncompleted];
    }

    public static function getMultipleDiscussions($course){
        global $DB;
        //There are many forums
        //There are many discussions
        //There are many posts per discussion
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS forum_name, m.id AS module_id, cm.id AS course_module_id
                                         FROM mdl_forum f
                                         JOIN mdl_modules m ON m.name='forum'
                                         JOIN mdl_course_modules cm ON cm.id=m.id
                                         WHERE f.course=$course->id AND type='general' OR type='blog' AND cm.visible=1");

        $forums = json_decode(json_encode($forums), true);
        
        $labelForums=array();
        $multipleDiscussions=array();
        $multiplePosts=array();

        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            array_push($labelForums, $forum['forum_name']);

            $discussions = $DB->get_records_sql("SELECT fd.id AS forum_discussion_id, fd.name AS forum_name
                                                 FROM mdl_forum_discussions fd
                                                 JOIN mdl_forum f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id");
            $discussions = json_decode(json_encode($discussions), true);
            array_push($multipleDiscussions, count($discussions));

            if(count($discussions) == 0){
                array_push($multiplePosts, 0);
            } else{
                foreach($discussions as $discussion){
                    $d_id = $discussion['forum_discussion_id'];

                    $posts = $DB->get_records_sql(" SELECT fp.id as post_id
                                                    FROM mdl_forum_posts fp 
                                                    JOIN mdl_forum_discussions fd ON fp.discussion = fd.id
                                                    WHERE fd.course=$course->id AND fd.forum=$forumId AND fp.discussion=$d_id
                                                ");
                    $posts = json_decode(json_encode($posts), true);
                    array_push($multiplePosts, count($posts));
                }
            }
        }
        return ([$labelForums, $multipleDiscussions, $multiplePosts]);

    }

    public static function getSingleDiscussions($course){
        global $DB;
        $forumPosts=array();

        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS name, m.id AS module_id, cm.id AS course_module_id
                                         FROM mdl_forum f
                                         JOIN mdl_modules m ON m.name='forum'
                                         JOIN mdl_course_modules cm ON cm.id=m.id
                                         WHERE f.course=$course->id AND type='single' OR type='qanda' AND cm.visible=1");

        $forums = json_decode(json_encode($forums), true);

        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            $posts = $DB->get_records_sql(" SELECT fd.id AS forum_disc_id, fp.id as forum_id
                                            FROM mdl_forum_discussions fd
                                            JOIN mdl_forum_posts fp ON fp.discussion = fd.id
                                            WHERE fd.course=$course->id AND fd.forum=$forumId
                                            ");

            $posts = json_decode(json_encode($posts), true);
            
            array_push($forumPosts,[$forum['name'], count($posts)]);
        }
        return $forumPosts;
        
    }


    public static function getLimitedDiscussions($course){
        global $DB;
        //There are many forums
        //There is one discussion per user
        //There are many posts per discussion
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS forum_name, m.id AS module_id, cm.id AS course_module_id
                                         FROM mdl_forum f
                                         JOIN mdl_modules m ON m.name='forum'
                                         JOIN mdl_course_modules cm ON cm.id=m.id
                                         WHERE f.course=$course->id AND type='eachuser' AND cm.visible=1");

        $forums = json_decode(json_encode($forums), true);
        
        $labelForums=array();
        $multipleDiscussions=array();
        $multiplePosts=array();

        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            array_push($labelForums, $forum['forum_name']);
            $discussions = $DB->get_records_sql("SELECT fd.id AS forum_discussion_id, fd.name AS forum_name
                                                 FROM mdl_forum_discussions fd
                                                 JOIN mdl_forum f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id");
            $discussions = json_decode(json_encode($discussions), true);
            array_push($multipleDiscussions, count($discussions));

            if(count($discussions) == 0){
                array_push($multiplePosts, 0);
            } else{
                foreach($discussions as $discussion){
                    $d_id = $discussion['forum_discussion_id'];

                    $posts = $DB->get_records_sql(" SELECT fp.id as post_id
                                                    FROM mdl_forum_posts fp 
                                                    JOIN mdl_forum_discussions fd ON fp.discussion = fd.id
                                                    WHERE fd.course=$course->id AND fd.forum=$forumId AND fp.discussion=$d_id
                                                ");
                    $posts = json_decode(json_encode($posts), true);

                    array_push($multiplePosts, count($posts));
                }
            }
        }
        return ([$labelForums, $multipleDiscussions, $multiplePosts]);
    }

    public static function getAssignmentSubmissionTime($course){
        global $DB;
        //get all dates, and count each date for each assignment
        //return every date with a submission as $labels
        //"mod sub date count" is the modulename, submission date and submission count at that date

        $allDates=array();
        $moduleSubmissions=[];
        $modSubDateCount=[];

        // Get assignments from current course
        $assign_modules = $DB->get_records_sql(" SELECT a.id AS a_id, a.name AS name, a.course AS course, m.name AS m_name
                                                 FROM mdl_assign a
                                                 JOIN mdl_course_modules cm ON a.course=cm.course
                                                 JOIN mdl_modules m ON m.id = cm.module
                                                 WHERE cm.visible=1 AND cm.course=$course->id AND m.name='assign' " );
        $assign_modules = json_decode(json_encode($assign_modules), true);
        
        //get each submitted assignment and its date
        $submissions = $DB->get_records_sql("  SELECT s.id AS s_id, cm.id AS id, cm.module AS cm_id, m.id AS m_id, m.name AS m_name, a.id AS a_id, a.name AS a_name, a.course AS course, s.timecreated AS s_sub
                                                            FROM mdl_assign_submission s 
                                                            JOIN mdl_assign a ON a.id = s.assignment
                                                            JOIN mdl_course_modules cm ON a.course=cm.course
                                                            JOIN mdl_modules m ON m.id = cm.module
                                                            WHERE cm.visible=1 AND m.name='assign' AND s.status='submitted' AND cm.course=$course->id ");

        $submissions = json_decode(json_encode($submissions), true);

        //set an empty array with the module name as the key
        foreach($assign_modules as $module){
            $moduleSubmissions[$module['name']]=array();
        }

        foreach($assign_modules as $module){
            foreach($submissions as $submission){
                if($module['name'] == $submission['a_name']){
                    //get module and associate with every submission date
                    array_push($moduleSubmissions[$module['name']], date('d-m-Y',$submission['s_sub']))."\n";
                } 
            }
        }

        foreach($moduleSubmissions as $key=> $moduleSub){
            //count the time of each submission in an array
            $timeCount=array();
            
            foreach($moduleSub as $sub){
                array_push($timeCount,$sub);
            }
            //count the number of items in the array
            $counts = array_count_values($timeCount);
            
            array_push($allDates,array_keys($counts));
            array_push($modSubDateCount, [$key,$counts]);
        }

        $labels=array();
        //add each date from all assignments to the chart labels
        foreach($allDates as $date){
            foreach($date as $item){
                array_push($labels,$item);
            }
        }

        //date sort from stack overflow to order dates
        //https://stackoverflow.com/questions/40462778/how-to-sort-date-array-in-php
        function date_sort($a,$b){
            return strtotime($a) - strtotime($b);
        }
        usort($labels,"date_sort");
       
        return [$labels,$modSubDateCount];
    }
    
}