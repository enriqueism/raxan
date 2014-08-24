/**
 * Sortable Columns Code behind file
 */

Raxan.include('jquery');
Raxan.include('jquery-ui-interactions');

Raxan.ready(function(){
    $('.container .column').sortable({
        connectWith:['.column'],
        placeholder: 'place',
        items:'div'
    });
})