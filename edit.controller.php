<?php

/**
* this is the master controller of the edit form, except all what
* is being handled by ajax.
*/

// Mark/toggle editdate mode in session.
$editdates = optional_param('editdates', false, PARAM_BOOL);
if ($editdates !== false) {
    if ($editdates) {
        $SESSION->learningtimecheck_editdates = 1;
    } else {
        unset($SESSION->learningtimecheck_editdates);
    }
}

$additemafter = optional_param('additemafter', false, PARAM_INT);
$removeauto = optional_param('removeauto', false, PARAM_TEXT);
$update_complete_scores = optional_param('update_complete_score', false, PARAM_TEXT);
$applyenablecredittoall = optional_param('applyenablecredittoall', false, PARAM_TEXT);
$applycredittimetoall = optional_param('applycredittimetoall', false, PARAM_TEXT);
$applyisdeclarativetoall = optional_param('applyisdeclarativetoall', false, PARAM_TEXT);
$applyteachercredittimetoall = optional_param('applyteachercredittimetoall', false, PARAM_TEXT);
$applyteachercredittimeperusertoall = optional_param('applyteachercredittimeperusertoall', false, PARAM_TEXT);

if ($removeauto) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    // Remove any automatically generated items from the list
    // (if no longer using automatic items)
    $chk->removeauto();
    return;
}

$action = optional_param('what', false, PARAM_TEXT);

// If full from update button is pressed
if ($action == 'update_complete_scores') {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->update_complete_scores();
    return;
}

if ($applyenablecredittoall) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->apply_to_all('enablecredit', optional_param('enablecreditglobal', 0, PARAM_BOOL));
    return;
}

if ($applycredittimetoall) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->apply_to_all('credittime', optional_param('credittimeglobal', 0, PARAM_INT));
    return;
}

if ($applyisdeclarativetoall) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->apply_to_all('isdeclarative', optional_param('isdeclarativeglobal', 0, PARAM_INT));
    return;
}

if ($applyteachercredittimetoall) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->apply_to_all('teachercredittime', optional_param('teachercredittimeglobal', 0, PARAM_INT));
    return;
}

if ($applyteachercredittimeperusertoall) {
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }
    $chk->apply_to_all('teachercredittimeperuser', optional_param('teachercredittimeperuserglobal', 0, PARAM_INT));
    return;
}

// Other controllers from A HREF links
$itemid = optional_param('itemid', 0, PARAM_INT);

if ($action) {
    // Protect all operations with sesskey();
    if (!confirm_sesskey()) {
        print_error('Invalid sesskey');
    }

    switch ($action) {
        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $indent = optional_param('indent', 0, PARAM_INT);
            $position = optional_param('position', false, PARAM_INT);
            $isoptional = optional_param('isoptional', LEARNINGTIMECHECK_OPTIONAL_YES, PARAM_INT);
            if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                $duetime = false;
            } else {
                $duetime = optional_param('duetime', false, PARAM_INT);
            }
            $chk->additem($displaytext, 0, $indent, $position, $duetime, 0, $isoptional);
            break;
    
        case 'startadditem':
            $additemafter = $itemid;
            break;
    
        case 'edititem':
            if (isset($chk->items[$itemid])) {
                $chk->items[$itemid]->editme = true;
            }
            break;
    
    case 'updateitem':
        $displaytext = optional_param('displaytext', '', PARAM_TEXT);
        if (optional_param('duetimedisable', false, PARAM_BOOL)) {
            $duetime = false;
        } else {
            $duetime = optional_param_array('duetime', false, PARAM_INT);
        }
        $chk->updateitemtext($itemid, $displaytext, $duetime);
        break;
    
    case 'deleteitem':
        if (($chk->learningtimecheck->autopopulate) && (isset($chk->items[$itemid])) && ($chk->items[$itemid]->moduleid)) {
            $chk->toggledisableitem($itemid);
        } else {
            $chk->deleteitem($itemid);
        }
        break;
    
    case 'moveitemup':
        $chk->moveitemup($itemid);
        break;
    
    case 'moveitemdown':
        $chk->moveitemdown($itemid);
        break;

    case 'makeoptional':
        $chk->makeoptional($itemid, true);
        break;

    case 'makerequired':
        $chk->makeoptional($itemid, false);
        break;

    case 'makeheading':
        $chk->makeoptional($itemid, true, true);
        break;

    case 'nextcolour':
        $chk->nextcolour($itemid);
        break;

    case 'hideitem':
        $chk->hideitem($itemid);
        break;

    case 'showitem':
        $chk->showitem($itemid);
        break;
    
    default:
        error('Invalid action - "'.s($action).'"');
    }
}