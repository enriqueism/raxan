/**
 * Sortable Columns Code behind file
 */

html.include('jquery');
html.include('jquery-ui-interactions');

html.ready(function(){
    $('.container .column').sortable({
        connectWith:['.column'],
        placeholder: 'place',
        items:'div'
    });
})