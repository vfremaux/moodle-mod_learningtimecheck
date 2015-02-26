function learningtimecheck_release_declaredtime_select(sourceelm, itemid) {
    targetelm = $('#declaredtime' + itemid);
    if (sourceelm.options[sourceelm.options.selectedIndex].value != itemid + ':0') {
        targetelm.disabled = false;
    } else {
        targetelm.options.selectedIndex = 0;
        targetelm.disabled = true;
    }
}

function learningtimecheck_updatechecks_show() {
    $('#learningtimechecksavechecks').css('display', 'block');
}

