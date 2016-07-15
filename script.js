/**
 * DokuWiki Select2 plugin
 */

jQuery(function() {
    var select_menu = jQuery('select.select_menu');
    if(!select_menu) return;
    //jQuery('.select_menu').select2({
    select_menu.select2({
        width: 'resolve',
        dropdownAutoWidth: true
    });

    // jump when changed
    jQuery('select.select_menu').changed(function() {
        var token = jQuery(this).val().split("|");
        if (token[0] == '') {
            location.href = token[1];
        } else {
            window.open(token[1],token[0]);
        }
    });
});
