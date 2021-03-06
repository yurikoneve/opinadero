<?php

namespace UserFrosting;

/**
 * StudentPerformanceController Class
 *
 * Controller class for /StudentsPerformance/* URLs.  Handles StudentsPerformance-related activities, including listing StudentsPerformance, CRUD for StudentsPerformance, etc.
 *
 * @package UserFrosting
 * @author Alex Weissman
 * @link http://www.userfrosting.com/navigating/#structure
 */
class StudentPerformanceController extends \UserFrosting\BaseController {

    /**
     * Create a new StudentsPerformanceController object.
     *
     * @param UserFrosting $app The main UserFrosting app.
     */
    public function __construct($app){
        $this->_app = $app;
    }

    /**
     * Renders the StudentsPerformance listing page.
     *
     * This page renders a table of user StudentsPerformance, with dropdown menus for modifying those StudentsPerformance.
     * This page requires authentication (and should generally be limited to admins or the root user).
     * Request type: GET
     * @todo implement interface to modify authorization hooks and permissions
     */
    public function pageStudentPerformance(){
        // Access-controlled page

        $ClassReferences = StudentsBio::queryBuilder()
            ->groupBy('teacher_id')
            ->groupBy('reference_number')
            ->get(array('teacher_id', 'reference_number'));
            
        $TeacherClasses = array();
        foreach($ClassReferences as $classRef) {
            if (!array_key_exists($classRef['teacher_id'], $TeacherClasses))
                $TeacherClasses[$classRef['teacher_id']] = array();
                
            array_push($TeacherClasses[$classRef['teacher_id']], $classRef['reference_number']);
        }
        
        $AllClasses = StudentsBio::queryBuilder()
            ->groupBy('reference_number')
            ->get(array('reference_number'));
        
        $Terms = StudentsBio::queryBuilder()
            ->groupBy('term')
            ->where('term', '<>', '20152')
            ->get(array('term'));
            
        $this->_app->render('students/student-performance.twig', [
           "classreference" => $TeacherClasses,
           "allclasses" => $AllClasses,
           "terms" => $Terms
        ]);  
    }
    
    public function getPerformanceByClass($classId) {
        
        $arr_param = explode('_', $classId);
        $term = $arr_param[0];
        $classId = $arr_param[1];
        
        $students = StudentsBio::queryBuilder()
            ->where('reference_number', '=', $classId)
            ->where('term', '=', $term)
            ->groupBy('student_id')
            ->orderBy('last_name')
            ->get(array('student_id'));
        
        $pfm_data = array();
        $indexOf = 0;
        foreach($students as $student) {
            $std_pfm_data = array();
        
            $reading_pfms = $this->getRLPerformanceOfStudent($student['student_id'], true, $term);
            $listening_pfms = $this->getRLPerformanceOfStudent($student['student_id'], false, $term);
            if (count($reading_pfms) > 0) $std_pfm_data['R'] = $reading_pfms;
            if (count($listening_pfms) > 0) $std_pfm_data['L'] = $listening_pfms;
            if (count($reading_pfms) > 0 || count($listening_pfms) > 0)
                array_push($pfm_data, $std_pfm_data);
        }
        
        return json_encode($pfm_data);
    }
    
    public function getPerformanceByStudent($studentId) {
        $arr_param = explode('_', $studentId);
        $term = $arr_param[1];
        $studentId = $arr_param[0];
        
        $pfm_data = array();
        $pfm_data[$studentId] = array();
        
        $reading_pfms = $this->getRLPerformanceOfStudent($studentId, true, $term);
        $listening_pfms = $this->getRLPerformanceOfStudent($studentId, false, $term);
        if (count($reading_pfms) > 0) $pfm_data[$studentId]['R'] = $reading_pfms;
        if (count($listening_pfms) > 0) $pfm_data[$studentId]['L'] = $listening_pfms;
        
        return json_encode($pfm_data);
    }
    
    public function getPerformanceByCompetency($compId) {
        $arr_param = explode('_', $compId);
        $classId = $arr_param[0];
        $compId = $arr_param[1];
        $term = $arr_param[2];
        
        $students = StudentsBio::queryBuilder()
            ->where('reference_number', '=', $classId)
            ->where('term', '=', $term)
            ->groupBy('student_id')
            ->orderBy('last_name')
            ->get(array('student_id'));
        
        $pfm_data = array();
        foreach($students as $student) {
            $pfm_data[$student['student_id']] = array();
        
            $reading_pfms = $this->getRLPerformanceOfStudent($student['student_id'], true, $term, $compId);
            $listening_pfms = $this->getRLPerformanceOfStudent($student['student_id'], false, $term, $compId);
            if (count($reading_pfms) > 0) $pfm_data[$student['student_id']]['R'] = $reading_pfms;
            if (count($listening_pfms) > 0) $pfm_data[$student['student_id']]['L'] = $listening_pfms;
        }
        
        return json_encode($pfm_data);
    }
    
    public function getRLPerformanceOfStudent($student_id, $isReading, $term, $compNo="") {
        $pfm_data = array();
        $pfms = StudentPerformance::queryBuilder()
            ->where('term', '=', $term)
            ->where('student_id', '=', $student_id)
            ->where('form', 'regexp', $isReading ? 'R':'L')
            ->orderBy('position', 'asc')
            ->orderBy('main_comp', 'desc')
            ->get();
        
        if(count($pfms) > 0) {
            $accurate = TestResults::queryBuilder()
                ->where('term', '=', $term)
                ->where('student_id', '=', $student_id)
                ->where('form', '=', $pfms[0]['form'])
                ->get();
                
            if(count($accurate) > 0 && $accurate[0]['accurate']=='Yes') {
                $pfm_data['student_id'] = $pfms[0]['student_id'];
                $pfm_data['student_name'] = $pfms[0]['student_name'];
                $pfm_data['form'] = $pfms[0]['form'];
                $pfm_data['score'] = $pfms[0]['scale_score'];
                $pfm_data['test_date'] = $pfms[0]['test_date'];
                $nat_data = NextAssignTest::queryBuilder()
                    ->where('term', '=', $term)
                    ->where('student_id', '=', $student_id)
                    ->where('form', 'regexp', $isReading ? 'R':'L')
                    ->get(array('next_form'));
                if(count($nat_data) > 0) {
                    $nat_data = $nat_data[0]['next_form'];
                    $nat_data = explode('(', $nat_data);
                    if(count($nat_data) > 1) {
                        $pfm_data['NAT'] = substr($nat_data[0], 0, strlen($nat_data[0]) - 1);
                        $pfm_data['series'] = substr($nat_data[1], 1, strlen($nat_data[1]) - 2);
                    }
                }
                
                $pfm_data['pfm'] = array();
                $positions = array();  //positions that have specific competency number
                if ($compNo == "") {
                    $pfm_data['pfm'] = $pfms;
                }
                else {
                    $hasComp = false;
                    foreach($pfms as $pfm) {
                        if ($pfm['comp_number'] == $compNo)
                        {
                            $hasComp = true;
                            $positions[$pfm['position']] = $compNo;
                        }
                    }
                    if (!$hasComp) return $pfm_data;
                    foreach($pfms as $pfm) {
                        if (array_key_exists($pfm['position'], $positions))
                        {
                            array_push($pfm_data['pfm'], $pfm);
                        }
                    }
                }
            }
        }
        
        return $pfm_data;
    }
}
