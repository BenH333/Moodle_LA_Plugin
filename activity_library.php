<?php 
/*
    Class functions return information on individual students
*/
class student_activity{
    
    public static function getStudents($course){
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
        $student_records = $DB->get_records_sql("   SELECT DISTINCT u.id AS userid, c.id AS courseid, u.firstname AS u_fname, u.lastname AS u_lname, u.lastlogin AS u_lastlogin, u.username AS u_username
                                                    FROM {user} u
                                                    JOIN {user_enrolments} ue ON ue.userid = u.id
                                                    JOIN {enrol} e ON e.id = ue.enrolid
                                                    JOIN {role_assignments} ra ON ra.userid = u.id
                                                    JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                                    JOIN {course} c ON c.id = ct.instanceid AND e.courseid ='$course->id'
                                                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                                    WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                                    AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP(NOW())) AND ue.status = 0
                                                ");
                                                
        return $student_records;
    }

    public static function isStudent($course, $user){
        global $DB;
        // print_r($course->id);
        $student_records = $DB->get_records_sql("   SELECT DISTINCT u.id AS userid, c.id AS courseid, u.firstname AS u_fname, u.lastname AS u_lname, u.lastlogin AS u_lastlogin, u.username AS u_username
                                                    FROM {user} u
                                                    JOIN {user_enrolments} ue ON ue.userid = u.id
                                                    JOIN {enrol} e ON e.id = ue.enrolid
                                                    JOIN {role_assignments} ra ON ra.userid = u.id
                                                    JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                                    JOIN {course} c ON c.id = ct.instanceid AND e.courseid ='$course->id'
                                                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                                    WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                                    AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP(NOW())) AND ue.status = 0 AND u.id=$user->id
                                                ");
        if(empty($student_records)){
            return false;
        }else{
            return true;
        }
    }

    public static function getStudentFromId($student_id){
        global $DB;
        $student = $DB->get_record_select("user", "id=$student_id");
        return json_decode(json_encode($student), true);
        
    }

    public static function quizGrades($course, $student_id){
        global $DB;
        
        $quiz_result = array();

        // get all quizzes from course
        $quizzes = $DB->get_records('quiz',array('course'=>$course->id));
        $quizzes = json_decode(json_encode($quizzes), true);
        
        // iterate through every quiz
        $quiz_result= array();
        foreach($quizzes as $quiz){
            //
            $id= $quiz['id'];
            $quiz_grade = $quiz['grade'];
            $select = "userid=$student_id AND quiz=$id";
            $grade = $DB->get_record_select('quiz_grades',$select,array('quiz' =>$id)); 
            $grade = json_decode(json_encode($grade), true);
            // print_r($grade);
            if(is_array($grade)){
                $percent = ($grade['grade'] / $quiz_grade) * 100;
            }else {
                $percent = 0;
            }
            $quiz_result[$quiz['name']] = $percent;
            
        }
        // print_r($quiz_result);
        return $quiz_result;
    }

    public static function forumActivity($course, $student_id){
        global $DB;
        $forums = $DB->get_records_sql(" SELECT f.id AS forum_id, f.name AS forum_name, m.id AS module_id, cm.id AS course_module_id
                                         FROM {forum} f
                                         JOIN {modules} m ON m.name='forum'
                                         JOIN {course_modules} cm ON cm.id=m.id
                                         WHERE f.course=$course->id AND type!='news' AND cm.visible=1");
        // print_r($forums);
        $forums = json_decode(json_encode($forums), true);

        $student_discussions=array();
        $student_posts=array();
        foreach ($forums as $forum){
            $forum_id = $forum['forum_id'];
            $forum_name = $forum['forum_name'];
            $student_posts[$forum_name] = 0;

            $discussions = $DB->get_records_sql("SELECT fd.id AS forum_discussion_id, fd.name AS forum_name
                                                 FROM {forum_discussions} fd
                                                 JOIN {forum} f ON f.id = fd.forum
                                                 WHERE f.id=$forum_id AND f.course=$course->id AND fd.userid=$student_id");
            $discussions = json_decode(json_encode($discussions), true);
            $student_discussions[$forum_name] = count($discussions);
         
            //get count of posts within a forum
            $forumDiscussions = $DB->get_records_select("forum_discussions","forum=$forum_id");
            $forumDiscussions = json_decode(json_encode($forumDiscussions), true);

            foreach($forumDiscussions as $discussion){
                $discussion_id = $discussion["id"];
                $posts = $DB->get_records_select("forum_posts","userid=$student_id AND discussion=$discussion_id");
                // print_r($posts);
                $student_posts[$forum_name] = count($posts);
            }
            
        }

        return [$student_discussions,$student_posts];
    }

    public static function getAssignmentSubmissionTime($course,$student_id){
        global $DB;
        //get all dates, and count each date for each assignment
        //return every date with a submission as $labels
        //"mod sub date count" is the modulename, submission date and submission count at that date

        $moduleSubmissions=array();
        $moduleNames=array();

        // Get assignments from current course
        $assign_modules = $DB->get_records_sql(" SELECT a.id AS a_id, a.name AS name, a.course AS course, m.name AS m_name
                                                 FROM {assign} a
                                                 JOIN {course_modules} cm ON a.course=cm.course
                                                 JOIN {modules} m ON m.id = cm.module
                                                 WHERE cm.visible=1 AND cm.course=$course->id AND m.name='assign' " );
        $assign_modules = json_decode(json_encode($assign_modules), true);
        
        //get each submitted assignment and its date
        $submissions = $DB->get_records_sql("  SELECT s.id AS s_id, s.userid AS s_user, cm.id AS id, cm.module AS cm_id, m.id AS m_id, m.name AS m_name, a.id AS a_id, a.name AS a_name, a.course AS course, s.timemodified AS s_sub
                                                            FROM {assign_submission} s 
                                                            JOIN {assign} a ON a.id = s.assignment
                                                            JOIN {course_modules} cm ON a.course=cm.course
                                                            JOIN {modules} m ON m.id = cm.module
                                                            WHERE cm.visible=1 AND m.name='assign' AND s.status='submitted' AND cm.course=$course->id AND s.userid=$student_id");

        $submissions = json_decode(json_encode($submissions), true);
        //set an empty array with the module name as the key
        foreach($assign_modules as $module){
            array_push($moduleNames,$module['name']);
        }

        foreach($assign_modules as $module){
            foreach($submissions as $submission){
                if($module['name'] == $submission['a_name']){
                    //get module and associate with every submission date
                    $moduleSubmissions[$module['name']] = date('d-m-Y',$submission['s_sub']);
                } 
            }
        }
        return [$moduleNames,$moduleSubmissions];
    }
    
}

?>