
Raxan.include('jquery');
Raxan.ready(function(){
    $('#button1').click(function(){
        var query = $('#query').val();
        $('#list').html('Searching...');
        Raxan.dispatchEvent('filenames',query,function(result,ok){
            if (!ok) return;
            var f,li = '';
            for (f in result) li+= '<li><a href="'+result[f]+'">'+result[f]+'</a></li>';
            li = 'Showing results for '+query+'<hr /><ul>'+li+'</ul>';
            $('#list').html(li);
        })
    })
})