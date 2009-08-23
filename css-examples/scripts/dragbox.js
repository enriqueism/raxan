/*
 * Dragbox Code behind
 */

// include css
html.css('master');
html.css('default/theme');

// include library files
html.include('jquery');
html.include('jquery-ui-interactions');

// main startup function
html.ready(function(){

    // make box draggable
    var box = $('#box').draggable().show("slide", { direction: "left" }, 500);

});

html.bind('#box','click',function(){
    $(this).hide('explode');
})