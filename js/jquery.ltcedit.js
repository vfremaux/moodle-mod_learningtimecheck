
function load_add_item_form(wwwroot, cmid, itemid) {

    url = wwwroot+'/mod/learningtimecheck/ajax/services.php?what=getadditemform&id='+cmid+'&itemid='+itemid;

    $.get(url, function(data) {
            additempodid = '#add-after-'+itemid;
            rowadditempodid = '#row-add-after-'+itemid;
            $(additempodid).html(data);
            $(rowadditempodid).removeClass('hidden');
    });
}

function cancel_add_item_form(itemid) {
    additempodid = '#add-after-'+itemid;
    rowadditempodid = '#row-add-after-'+itemid;
    $(additempodid).html('');
    $(rowadditempodid).addClass('hidden');
}

// force mandatory check on when choosing a credittime
function set_mandatory(selectobjid, checkobjid) {
    if ($('#'+selectobjid).val() > 0) {
        $('#'+checkobjid).attr('checked', true);
    } else {
        $('#'+checkobjid).attr('checked', null);
    }
}

function checktimecreditlist(itemid, selector) {
    if (selector.options[selector.selectedIndex].value == 1 || selector.options[selector.selectedIndex].value == 3) {
        $('#ltc-time-settings-'+itemid).addClass('isdeclarative');
        // $('#creditselect'+itemid).attr('disabled', 'disabled');
    } else {
        $('#ltc-time-settings-'+itemid).removeClass('isdeclarative');
        // $('#creditselect'+itemid).attr('disabled', null);
    }
}