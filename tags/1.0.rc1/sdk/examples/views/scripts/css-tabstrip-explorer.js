/**
 * TabStrip Explorer Code-Behind file.
 */

// include main scripts
Raxan.include('jquery');
Raxan.include('jquery-ui-interactions');
Raxan.include('raxan-ui');

// main ready function
Raxan.ready(function(){

    // update tab when form change
    $('#lstTheme, #lstAnimate, #txtDelay').change(updateTab);
    $('#chkAuto, #chkRandom').click(updateTab);

    updateTab();
})

/* Custom animations */
var currentTab = null;
var ani = {
    "default":true,
    "bounce_back": function(i,a,b){
        currentTab = i;
        b.hide('slide',{direction:'down'},'',function(){
            if (currentTab == i)
                a.show('slide',{direction:'down'});
        })
    },
    "roll_up": function(i,a,b){
        currentTab = i;
        b.hide('slide',{direction:'up'},'',function(){
            if (currentTab == i)
                a.show('slide',{direction:'down',easing: "easeInOutBack"});
        })
    },
    "slide_in": function(i,a,b) {
        currentTab = i;
        b.hide('slide',{direction:'left'},'',function(){
            if (currentTab == i)
                a.show('slide',{direction:'right'});
        })
    },
    "push_back": function(i,a,b) {
        currentTab = i;
        b.hide('slide',{direction:'left'},'',function(){
            if (currentTab == i)
                a.show('slide',{direction:'left'});
        })
    },
    "page_scroll": function(i,a,b) {
        currentTab = i;
        if (self.oldindex>i) {
            b.hide('slide',{direction:'right'},'',function(){
                if (currentTab == i)
                    a.show('slide',{direction:'left'});
            })
        }
        else {
            b.hide('slide',{direction:'left'},'',function(){
                if (currentTab == i)
                a.show('slide',{direction:'right'});
            })
        }
        self.oldindex = i;
    }
}

// update tabstrip
function updateTab() {
    var theme = $('#lstTheme').val();
    var animate = $('#lstAnimate').val();
    var o = {
        theme: theme,
        animate: ani[animate] ? ani[animate] : false,
        tabclick: function(e){
            var i = e.data.index;
            $('#pnlText').text('You have selected tab #'+(i+1));
        }
    }

    $('#tab1').removeClass().raxTabStrip(o);
    if ($('#chkAuto').attr('checked')) {
        var d = $('#txtDelay').val();
        var r = $('#chkRandom').attr('checked') ? $('#chkRandom').val() : false;
        if (d<1000) d = 1000;
        $('#tab1').raxTabStrip('autopilot',d,r)
    }
    else {
        $('#tab1').raxTabStrip('autopilot',false);
    }

}

