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

    public static function getCourseModules($course, $student_records){
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
                $dynamic_activity_modules_data = $DB->get_record($activity_module->name,array('id' =>$instance->instance)); 
                $module_name = $dynamic_activity_modules_data->name;

                list($insql,$inparams) = $DB->get_in_or_equal($students);
                $sql = "SELECT id FROM {logstore_standard_log} WHERE courseid=$course->id AND objectid=$instance->instance AND objecttable LIKE '$activity_module->name' AND userid $insql";
                $module_views = count($DB->get_records_sql($sql,$inparams));
                

                array_push($module_names,$module_name);
                array_push($module_usage,$module_views);
            }
            
        }
        return [$module_names,$module_usage];

    }
}