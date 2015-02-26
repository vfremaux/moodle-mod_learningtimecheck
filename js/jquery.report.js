
function load_filter_rule_form(wwwroot, cmid, component, view, itemid) {

    url = wwwroot+'/'+component+'/learningtimecheck/ajax/services.php?id='+cmid+'&what=getfilterruleform&view='+view+'&itemid='+itemid;

    $.get(url, function(data) {
            newfilterruleid = '#learningtimecheck-filter-new-rule';
            $(newfilterruleid).html(data);
            $(newfilterruleid).removeClass('hidden');
    });
}

function cancel_filter_rule_form() {
    newfilterruleid = '#learningtimecheck-filter-new-rule';
    $(newfilterruleid).html('');
    $(newfilterruleid).addClass('hidden');
}

function rule_filter_toggle(plusurl, minusurl) {
    filterform = '#learningtimecheck-event-filter-form';
    if ('none' == $(filterform).css('display')) {
        $(filterform).css('display', 'block');
        $('#ltc-rule-filter-toggle').attr('src', minusurl); 
    } else {
        $(filterform).css('display', 'none');
        $('#ltc-rule-filter-toggle').attr('src', plusurl); 
    }
}