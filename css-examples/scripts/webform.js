/**
 * Web Form Code-Behind script
 */


html.include('jquery');
html.css('master').css('default/theme');

html.bind('#btnSave','click',function(){
    var pnl = $('#pnlError');
    pnl.show('fast');
    pnl.html('Sending data to sever. Click Cancel to continue.');

    // include webform-part2.js
    html.include('scripts/webform-part2.js',true,function(){
        showStatus(); // from webform-part2.js
        // post data to server
        var data = [];
        d = $('#frmStudent').serializeArray()
        $.each(d,function(i,f){ data[f.name] = f.value});
        data.id = 12;
        // html.post('someurl.php',data);
    })
    return false;
})


html.bind('#btnCancel','click',function(){
    var pnl = $('#pnlError');
    pnl.hide('fast');
    // check if the clearStatus() function was loaded
    if(self.clearStatus) clearStatus();
    return false;
})


