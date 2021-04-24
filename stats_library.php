<?php

class course_activity{
    
    public static function getStudentRecords($course){
        global $DB;
        /*  https://stackoverflow.com/questions/22161606/sql-query-for-courses-enrolment-on-moodle
            https://docs.moodle.org/dev/New_enrolments_in_2.0
        SQL Query to get all students in current course
        Finding the current course reduces processing time when getting a student object
        Distinct returns only one of every user incase there are any duplicate users
        CHECKS:
            --the user is enrolled on a course
            --the user is not deleted
            --the user is not suspended
            --the role shortname is student
            --the user context is from a course(50) = student
            --the user enrolment has not ended && status = 0(active)
            --the course id is the current course
            --the user is not enrolled as a guest or optional enrollment
        */
        $student_records = $DB->get_records_sql("   SELECT DISTINCT u.id AS userid, c.id AS courseid
                                                    FROM {user} u
                                                    JOIN {user_enrolments} ue ON ue.userid = u.id
                                                    JOIN {enrol} e ON e.id = ue.enrolid
                                                    JOIN {role_assignments} ra ON ra.userid = u.id
                                                    JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                                    JOIN {course} c ON c.id = ct.instanceid AND e.courseid = c.id
                                                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                                    WHERE c.id=$course->id AND e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                                    AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP(NOW())) AND ue.status = 0
                                                ");

       
        return $student_records;
    }

    public static function courseAccess($student_records,$course){
        //retrieve all course interactions from database

        global $DB;
        
        $course_views = 0;
        $time_created = [];

        $students = array_keys($student_records);
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
        $module_top_10 =[];

        //Select course modules id from current course
        $modules = $DB->get_records_sql('   SELECT DISTINCT m.module AS m_id
                                            FROM {course_modules} m
                                            WHERE visible=1 AND course='.$course->id);

        $modules = json_decode(json_encode($modules), true);
        foreach($modules as $module){
            //get instances each module e.g. quiz 1, quiz 2
            $instances = $DB->get_records('course_modules',array('course'=>$course->id,'module'=>$module['m_id']));

            foreach($instances as $instance){
                $activity_module = $DB->get_record('modules',array('id' =>$instance->module)); //get module name e.g. quiz or forum

                if($activity_module->name != 'assign' && $activity_module->name != 'assignment' && $activity_module->name != 'learninganalytics'){
                    $dynamic_activity_modules_data = $DB->get_record($activity_module->name,array('id' =>$instance->instance)); 
                    $module_name = $dynamic_activity_modules_data->name;
                    list($insql,$inparams) = $DB->get_in_or_equal($students);
                    $sql = "SELECT id FROM {logstore_standard_log} WHERE courseid=$course->id AND objectid=$instance->instance AND objecttable='$activity_module->name' AND action='viewed' AND userid $insql";
                    $module_views = count($DB->get_records_sql($sql,$inparams));
                    $module_top_10[$module_name] = $module_views; 
                }
            }
            
        }

        function maxNitems($array, $n=10){
            asort($array);
            return array_slice(array_reverse($array, true),0,$n,true);
        }
        $top10= maxNitems($module_top_10);
        return $top10;

    }

    public static function lateSubmissions($course, $student_records){
        global $DB;
        $late_assignments=0;
        $submitted_assignments=0;
        $non_submissions =0;

        //get all assignments from course
        $assign_modules = $DB->get_records_sql("SELECT * FROM {assign} WHERE course=$course->id");

        //all assignments = number of students * assignments
        $required_submissions = count(array_keys($student_records)) * count($assign_modules);

        // select 'submitted' assign_submission id from current course
        $submitted_assign_modules = $DB->get_records_sql("  SELECT s.id AS submit, a.duedate AS a_due, s.timemodified AS s_sub
                                                            FROM {course_modules} cm
                                                            JOIN {modules} m ON m.id = cm.module
                                                            JOIN {assign} a ON a.course = cm.course
                                                            JOIN {assign_submission} s ON s.assignment = a.id
                                                            WHERE cm.visible=1 AND m.name='assign' AND s.status='submitted' AND cm.course=$course->id");

        $submitted_assign_modules = json_decode(json_encode($submitted_assign_modules), true);

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
                                            FROM {course_modules} cm
                                            JOIN {modules} m ON m.id = cm.module
                                            JOIN {quiz} q on q.id = cm.instance
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

        $db = new course_activity;
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

        $db = new course_activity;
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

    public static function getMultipleDiscussions($course,$student_records){
        global $DB;
        
        $students = array_keys($student_records);
        list($insql,$inparams) = $DB->get_in_or_equal($students);
        //There are many forums
        //There are many discussions
        //There are many posts per discussion
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS forum_name, m.id AS module_id, cm.module AS course_module_id
                                         FROM {forum} f
                                         JOIN {modules} m ON m.name='forum'
                                         JOIN {course_modules} cm ON cm.id=m.id
                                         WHERE cm.course=$course->id AND type='blog' OR type='general' AND f.course=$course->id AND cm.visible=1");
        
        $forums = json_decode(json_encode($forums), true);
        
        $labelForums=array();
        $multipleDiscussions=array();
        $multiplePosts=array();

        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            array_push($labelForums, $forum['forum_name']);

            $discussions = $DB->get_records_sql("SELECT fd.id AS forum_discussion_id, fd.name AS forum_name, fd.userid AS userid
                                                 FROM {forum_discussions} fd
                                                 JOIN {forum} f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id ");
            
            $discussions = json_decode(json_encode($discussions), true);
            $sql = "SELECT fd.id AS forum_discussion_id, fd.name AS forum_name, fd.userid AS userid
                                                 FROM {forum_discussions} fd
                                                 JOIN {forum} f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id AND fd.userid $insql";
            
            $student_discussions = $DB->get_records_sql($sql,$inparams);
            array_push($multipleDiscussions, count($student_discussions));

            if(count($discussions) == 0){
                array_push($multiplePosts, 0);
            } else{
                foreach($discussions as $discussion){
                    $d_id = $discussion['forum_discussion_id'];

                    $sql = " SELECT fp.id as post_id, fp.userid AS userid
                                                    FROM {forum_posts} fp 
                                                    JOIN {forum_discussions} fd ON fp.discussion = fd.id
                                                    WHERE fd.course=$course->id AND fd.forum=$forumId AND fp.discussion=$d_id AND fp.userid $insql
                                                 ";
                    $posts = $DB->get_records_sql($sql,$inparams);
                    $posts = json_decode(json_encode($posts), true);
                    array_push($multiplePosts, count($posts));
                }
            }
        }
        return ([$labelForums, $multipleDiscussions, $multiplePosts]);

    }

    public static function getSingleDiscussions($course, $student_records){
        global $DB;
        $forumPosts=array();
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS name, m.id AS module_id, cm.module AS course_module_id, cm.course as cm_course, f.course AS f_course
                                         FROM {forum} f
                                         JOIN {modules} m ON m.name='forum'
                                         JOIN {course_modules} cm ON cm.id=m.id
                                         WHERE (f.course=$course->id AND type='single') OR (type='qanda' AND f.course=$course->id) AND cm.visible=1");

        $forums = json_decode(json_encode($forums), true);
        $students = array_keys($student_records);
        list($insql,$inparams) = $DB->get_in_or_equal($students);

        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            $sql = "SELECT fd.id AS forum_disc_id, fp.id AS forum_id, fp.userid AS userid
                                            FROM {forum_discussions} fd
                                            JOIN {forum_posts} fp ON fp.discussion = fd.id
                                            WHERE fp.userid $insql AND fd.course=$course->id AND fd.forum=$forumId  
                                            ";
            $posts = $DB->get_records_sql($sql,$inparams);
            $posts = json_decode(json_encode($posts), true);
            
            array_push($forumPosts,[$forum['name'], count($posts)]);
        }
        return $forumPosts;
        
    }


    public static function getLimitedDiscussions($course, $student_records){
        global $DB;
        //There are many forums
        //There is one discussion per user
        //There are many posts per discussion
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS forum_name, m.id AS module_id, cm.module AS course_module_id
                                         FROM {forum} f
                                         JOIN {modules} m ON m.name='forum'
                                         JOIN {course_modules} cm ON cm.id=m.id
                                         WHERE f.course=$course->id AND type='eachuser' AND cm.visible=1");

        $forums = json_decode(json_encode($forums), true);
        
        $labelForums=array();
        $multipleDiscussions=array();
        $multiplePosts=array();

        $students = array_keys($student_records);
        list($insql,$inparams) = $DB->get_in_or_equal($students);
        
        foreach ($forums as $forum){
            $forumId = $forum['forum_id'];
            array_push($labelForums, $forum['forum_name']);
            $discussions = $DB->get_records_sql("SELECT fd.id AS forum_discussion_id, fd.name AS forum_name
                                                 FROM {forum_discussions} fd
                                                 JOIN {forum} f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id");
            $discussions = json_decode(json_encode($discussions), true);

            $sql = "SELECT fd.id AS forum_discussion_id, fd.name AS forum_name, fd.userid AS userid
                                                 FROM {forum_discussions} fd
                                                 JOIN {forum} f ON f.id = fd.forum
                                                 WHERE f.id=$forumId AND f.course=$course->id AND fd.userid $insql";
            
            $student_discussions = $DB->get_records_sql($sql,$inparams);
            array_push($multipleDiscussions, count($student_discussions));

            if(count($discussions) == 0){
                array_push($multiplePosts, 0);
            } else{
                foreach($discussions as $discussion){
                    $d_id = $discussion['forum_discussion_id'];

                    $posts = $DB->get_records_sql(" SELECT fp.id as post_id
                                                    FROM {forum_posts} fp 
                                                    JOIN {forum_discussions} fd ON fp.discussion = fd.id
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
                                                 FROM {assign} a
                                                 JOIN {course_modules} cm ON a.course=cm.course
                                                 JOIN {modules} m ON m.id = cm.module
                                                 WHERE cm.visible=1 AND cm.course=$course->id AND m.name='assign' " );
        $assign_modules = json_decode(json_encode($assign_modules), true);
        
        //get each submitted assignment and its date
        $submissions = $DB->get_records_sql("  SELECT s.id AS s_id, cm.id AS id, cm.module AS cm_id, m.id AS m_id, m.name AS m_name, a.id AS a_id, a.name AS a_name, a.course AS course, s.timecreated AS s_sub
                                                            FROM {assign_submission} s 
                                                            JOIN {assign} a ON a.id = s.assignment
                                                            JOIN {course_modules} cm ON a.course=cm.course
                                                            JOIN {modules} m ON m.id = cm.module
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

        //date sort from stack overflow to order dates
        //https://stackoverflow.com/questions/40462778/how-to-sort-date-array-in-php
        function date_sort($a,$b){
            $date = strtotime($a) - strtotime($b);
            return strval($date);
        }
        $submissionDateTime = array();
        foreach($moduleSubmissions as $key=> $moduleSub){
            //count the time of each submission in an array
            $timeCount=array();
            
            foreach($moduleSub as $sub){
                array_push($timeCount,$sub);
            }
            usort($timeCount,"date_sort");

            //count the number of items in the array
            $counts = array_count_values($timeCount);
            
            array_push($allDates,array_keys($counts));
            array_push($modSubDateCount, [$key,$counts]);
            $submissionDateTime[$key] = $counts;
        }
        $labels=array();
        //add each date from all assignments to the chart labels
        foreach($allDates as $date){
            foreach($date as $item){
                array_push($labels,$item);
            }
        }
        
        $labels= array_values(array_unique($labels));
        usort($labels,"date_sort");
        
        return [$labels,$modSubDateCount];
    }
    
}