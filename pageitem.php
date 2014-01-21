<?php

/**
* implements a hook for the page_module block to add
* the link allowing editing the expertnote for experts
*
*
*/

function learningtimecheck_set_instance(&$block){
    global $USER, $CFG, $COURSE;

    // transfer content from title to content    
    // $block->content->text = $block->title;
    $block->title = '';

	$context = context_module::instance($block->cm->id);
    $userid = $USER->id;
	$chk = new learningtimecheck_class($block->cm->id, $userid, $block->moduleinstance, $block->cm, $block->course);

	if (has_capability('mod/learningtimecheck:updateother', $context)){
		// get standard module link and icon.
		include_once $CFG->dirroot.'/course/format/page/plugins/page_item_default.php';
		page_item_default_set_instance($block);
	} else {
		$chk->view_own_report();
		if ($block->moduleinstance->usetimecounterpart){
			$completeviewstr = get_string('fullviewdeclare', 'learningtimecheck');
		} else {
			$completeviewstr = get_string('fullview', 'learningtimecheck');
		}
    	$page = course_page::get_current_page($COURSE->id, false);
		echo "<a href=\"{$CFG->wwwroot}/mod/learningtimecheck/view.php?id={$block->cm->id}&page={$page->id}\">$completeviewstr</a>";
		echo '<br/><br/>';
	}
	    
    return true;    
}

?>