/**
 * Raxan TabStrip Plugin
 * Adds tab interaction to <ul> with tabstrip classes
 * Requires: Raxan and jQuery
 */

var c;
// add to rich namespace
Raxan.rich.tabstrip = c = function($){
    var tab;
    return tab = {
        id: 'rxTabStrip',
        autodelay: 3500,
        allowed: 'select;autopilot;',
        options: {animate:true, theme:null, tabclick:null},
        init: function(){
            $.fn.tabstrip = function(options){
                if (this.length==0) return this;
                var isMethod = (typeof options == 'string') 
                    && tab.allowed.indexOf(options+';')>=0;

                if (isMethod) {
                    // method call
                    var args = Array.prototype.slice.call(arguments, 1);
                    return tab[options].apply(this,args);
                }
                else {
                    // constructor
                    options = $.extend(tab.options,options);
                    return tab.construct.call(this,options);
                }
            }
        },

        construct: function(o){
            this.addClass('tabstrip');
            if (o.theme) this.addClass(o.theme);
            return this.each(function(){
                 var bag = $.data(this,tab.id); // check for previous bag
                 if (bag) bag.options = o;  // update option
                 else bag = $.data(this,tab.id,{options:o}); // store options for ul in data bag

                // handle tab clicks
                $('li',this).unbind('.'+tab.id) // clean up
                $('li',this).bind('click.'+tab.id, tab._clickHandle);
                
                // setup tab containers
                $('li > a',this)
                .unbind('.'+tab.id) // clean up 
                .bind('click.'+tab.id, function(e){ e.preventDefault(); })// prevent clicking from <a> tag
                .each(function(){
                    var a,u = tab.parseTag(this);
                    if (!u.id) return;
                    a = $(this);
                    if (!a.parent().hasClass('selected')) $('#'+u.id).hide();
                    else {
                        $('#'+u.id).show();
                        bag.current = u.id;
                    }
                });
            })
        },

        autopilot: function(delay,rand) {
            delay = (delay===true) ? tab.autodelay : delay;
            return this.each(function() {
                var me=this, bag = $.data(this,tab.id); // data bag
                if (bag.autoid) window.clearTimeout(bag.autoid);
                if (delay===false) bag.autoid = 0;
                else {
                    bag.autoid = window.setInterval(function(){
                        var li = $('li',me);
                        var i = li.index($('li.selected',me).get(0))+1;
                        if (rand) i = parseInt(Math.random() * li.length);
                        if (i > li.length-1) i = 0;
                        tab.select.call($(me),i);
                    }, delay);
                }
            })
        },
        
        // returns the id, url and selector from <a> tag
        parseTag: function(a) {
            var s,i,u,l = (a ? a.href : '').split('#');
            u = l.shift(); i = l.join('#');
            if (i && i.indexOf(';')>=0) {
                l = i.split(';'); i = l[0]; s = unescape(l[1]);
            }
            return {id:i, url:u, css:s};
        },

        // selects a tab
        select: function(n) {
            return this.each(function() {
                $('li',this).eq(n).click();
            })            
        },

        // handle tab clicks
        _clickHandle: function(e){
            var u = tab.parseTag($('a',this).get(0));
            var ul = $(this).parent().get(0);
            var bag = $.data(ul,tab.id); // data bag
            var o = bag.options;

            var li = $('li',ul).removeClass('selected');
            $(this).addClass('selected');

            if (!u.id && u.url) window.location.href = u.url;
            else if (u.id && bag.current != u.id) {
                if (typeof o.animate == 'function') {
                    //custom animation: index, current, previous
                    o.animate.call(this,li.index(this),$('#'+u.id),$('#'+bag.current));
                } else {
                    if (o.animate) $('#'+u.id).fadeIn();
                    else $('#'+u.id).show();
                    if (bag.current) $('#'+bag.current).hide();
                }
                if (u.url && window.location.href.indexOf(u.url)<0) {
                    $('#'+u.id).load(u.url+(u.css ? ' '+u.css :'')); // ajax loading
                }
                bag.current = u.id;
                if (o.tabclick) {               // event call
                    e.data = {
                        index:li.index(this),  // set tab index
                        container: bag.current              // set tab container id
                    }
                    o.tabclick.call(this,e);
                }
            }
        }

    }
}(jQuery);

c.init(); // init plugin
