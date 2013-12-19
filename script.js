/* DOKUWIKI:include_once select2.min.js */

function plugin_select2_jump(item){
   var token = item.value.split("|");
   if(token[0] == ''){
   	   location.href = token[1];
   } else {
   	   window.open(token[1],token[0]);
   }
}

jQuery(function() {
    var select_menu = jQuery('.select_menu');
    if(!select_menu) return;
    //jQuery('.select_menu').select2({
    select_menu.select2({
        width: 'resolve',
        dropdownAutoWidth: true
    });
});
