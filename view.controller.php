<?php

$chk->useredit = optional_param('useredit', false, PARAM_BOOL);

if (!confirm_sesskey()) {
    error('Invalid sesskey');
}

$itemid = optional_param('itemid', 0, PARAM_INT);

switch($action) {
    case 'updatechecks':
        $newchecks = optional_param_array('items', array(), PARAM_INT);
        $chk->updatechecks($newchecks);
        break;

    case 'startadditem':
        $chk->additemafter = $itemid;
        break;

    case 'edititem':
        if ($chk->useritems && isset($chk->useritems[$itemid])) {
            $chk->useritems[$itemid]->editme = true;
        }
        break;

    case 'additem':
        $displaytext = optional_param('displaytext', '', PARAM_TEXT);
        $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
        $position = optional_param('position', false, PARAM_INT);
        $chk->additem($displaytext, $chk->userid, 0, $position);
        $item = $chk->get_item_at_position($position);
        if ($item) {
            $chk->additemafter = $item->id;
        }
        break;

    case 'deleteitem':
        $chk->deleteitem($itemid);
        break;

    case 'updateitem':
        $displaytext = optional_param('displaytext', '', PARAM_TEXT);
        $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
        $chk->updateitemtext($itemid, $displaytext);
        break;

    default:
        error('Invalid action - "'.s($action).'"');
}

if ($action != 'updatechecks') {
    $chk->useredit = true;
}

