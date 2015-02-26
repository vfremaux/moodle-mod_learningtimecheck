
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