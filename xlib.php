<?php

require_once $CFG->dirroot.'/mod/learningtimecheck/locallib.php';

function learningtimecheck_get_instances($courseid, $usecredit = null){
	global $DB;
		
	if ($usecredit){
		$creditclause = ' AND usetimecounterpart = 1 ';
	} else if ($usecredit === false) {
		$creditclause = ' AND usetimecounterpart = 0 ';
	} else {
		$creditclause = '';
	}
	
	if ($learningtimechecks = $DB->get_records_select('learningtimecheck', " course = ? $creditclause ", array($courseid))){
		return $learningtimechecks;
	}
	return array();
}

/**
* @param int $learningtimecheckid
* @param int $cmid
* @param int $userid
* @return validated credittimes on course modules with several filters.
* credittime values are normalized in secs.
*
*/
function learningtimecheck_get_credittimes($learningtimecheckid = 0, $cmid = 0, $userid = 0){
    global $CFG, $DB;
    
    $learningtimecheckclause = ($learningtimecheckid) ? " AND ci.learningtimecheck = $learningtimecheckid " : '' ;
    $cmclause = ($cmid) ? " AND cm.id = $cmid " : '' ;
    $userclause = ($userid) ? " AND cc.userid = $userid " : '' ;
    $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => "$learningtimecheckid"));
    $teachermarkclause = '';
    if ($learningtimecheck->teacheredit > learningtimecheck_MARKING_STUDENT){
    	$teachermarkclause = " AND teachermark = 1 ";
    }

	// get only teacher validated marks to assess the credit time
	$sql = "
		SELECT
			ci.id,
			cc.userid,
			ci.moduleid AS cmid,
			ci.credittime * 60 AS credittime,
			m.name AS modname
		FROM
			{learningtimecheck_check} cc
		JOIN
			{learningtimecheck_item} ci
		ON
			ci.id = cc.item
			$learningtimecheckclause
			$userclause
		LEFT JOIN
			{course_modules} cm
		ON
			cm.id = ci.moduleid
		LEFT JOIN
			{modules} m
		ON 
			m.id = cm.module
		WHERE
			ci.enablecredit = 1
			$teachermarkclause
			$cmclause
	";
	
	return $DB->get_records_sql($sql);
}

/**
* @param int $learningtimecheckid
* @param int $cmid
* @param int $userid
* @return validated credittimes on course modules with several filters.
* credittime values are normalized in secs.
*
*/
function learningtimecheck_get_declaredtimes($learningtimecheckid, $cmid = 0, $userid = 0){
    global $CFG, $USER, $DB;
    
    $learningtimecheckclause = ($learningtimecheckid) ? " AND ci.learningtimecheck = $learningtimecheckid " : '' ;
    $cmclause = ($cmid) ? " AND cm.id = $cmid " : '' ;
    $userclause = ($userid) ? " AND cc.userid = $userid " : '' ;
    $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => "$learningtimecheckid"));
    $teachermarkedclause = '';
    if ($learningtimecheck->teacheredit > learningtimecheck_MARKING_STUDENT){
    	$teachermarkedclause = " AND teachermark = 1 ";
    }

	// TODO : resolve inconsistancy for learningtimecheckid = 0 vs. explicit watcher status against learningtimecheck instance   
	$cklcm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheckid);
    $context = context_module::instance($cklcm->id);
    
    if (has_capability('mod/learningtimecheck:updateother', $context) && $userid == $USER->id){

		// assessor case when self viewing 
		// get sum of teacherdelcaredtimes you have for each explicit module, or default module to learningtimecheck itself (NULL)
		// note the primary key is a pseudo key calculated for unicity, not for use.
		$sql = "
			SELECT
			    MAX(ci.id) as id,
				cc.teacherid,
				ci.moduleid as cmid,
				SUM(cc.teacherdeclaredtime) * 60 as declaredtime,
				m.name as modname
			FROM
				{learningtimecheck_check} cc
			JOIN
				{learningtimecheck_item} ci
			ON
				ci.id = cc.item
			LEFT JOIN
				{course_modules} cm
			ON
				cm.id = ci.moduleid
			LEFT JOIN
				{modules} m
			ON 
				m.id = cm.module
			WHERE
				cc.teacherid = $userid
				$teachermarkedclause
				$learningtimecheckclause
				$cmclause
			GROUP BY
				cc.teacherid,
				cmid
		";
		
		// echo "teacher $sql <br/>";
		
		return $DB->get_records_sql($sql);
    	
	} else {

    	// student case.

		// get only teacher validated marks to assess the declared time
		$sql = "
			SELECT
				ci.id,
				cc.userid,
				ci.moduleid as cmid,
				cc.declaredtime * 60 as declaredtime,
				m.name as modname
			FROM
				{learningtimecheck_check} cc
			JOIN
				{learningtimecheck_item} ci
			ON
				ci.id = cc.item
				$userclause
				$learningtimecheckclause
			LEFT JOIN
				{course_modules} cm
			ON
				cm.id = ci.moduleid
			LEFT JOIN
				{modules} m
			ON 
				m.id = cm.module
			WHERE
				1 = 1
				$teachermarkedclause
				$cmclause
		";
		
		// echo "Student : $sql <br/>";
		
		return $DB->get_records_sql($sql);
	}
}

