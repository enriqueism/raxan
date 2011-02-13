/*
 * Dragbox Code behind
 */

// include css
Raxan.css('master');
Raxan.css('default/theme');

// include library files
Raxan.include('jquery');
Raxan.include('jquery-ui-interactions');

// main startup function
Raxan.ready(function(){

    // make box draggable
    var box = $('#box').draggable().show("slide", { direction: "left" }, 500);

});

Raxan.bind('#box','click',function(){
    $(this).hide('explode');
})