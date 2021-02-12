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
        //SQL Query to get all students in current course
        //Finding the current course reduces processing time when getting a student object
        //Distinct returns only one of every user incase there are any duplicate users
        //CHECKS:
        // the user is enrolled 
        // the user is not deleted
        // the user is not suspended
        // the role shortname is student
        // the user context is from a course(50)
        // the user enrolment has not ended
        // the course id is the current course
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

    public static function getLogData($student_records,$course){
        global $DB;
        $students = array_keys($student_records);

        $course_views = 0;
        $time_created = [];
        $logs = [];

        foreach($students as $student){
            //log each action
            $log = $DB->get_records_sql('SELECT id, userid, timecreated, action
                                         FROM {logstore_standard_log}
                                         WHERE courseid='.$course->id.' AND userid='.$student);
            array_push($logs,$log);
        }

        $logs = json_decode(json_encode($logs), true);
        //From logs 
        foreach($logs as $student_log){
            foreach($student_log as $course_log){
                $course_views ++;
                array_push($time_created,$course_log['timecreated']);
            }
        }
        return array($course_views, $time_created, $logs);
    }

    public static function getCourseModules($course){
        global $DB;
        $course_modules =[];

        $modules = $DB->get_records_sql('   SELECT DISTINCT m.module AS module
                                            FROM mdl_course_modules m
                                            WHERE visible=1 AND course='.$course->id);

        $modules = json_decode(json_encode($modules), true);
        foreach($modules as $module){
            $activity_modules = $DB->get_record('modules',array('id' =>$module['module']));
            array_push($course_modules,$activity_modules->name);
        }

        return $course_modules;

    }
}