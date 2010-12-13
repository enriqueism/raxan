/**
 * Raxan UI scripts
 * Copright 2010 Raymond Irving
 * Requires: Raxan and jQuery
 */



/**
 * Raxan TabStrip
 * Adds tab interaction to <ul> with tabstrip classes  */
Raxan.UI.tabstrip = function($){    // add to UI namespace
    var tab;
    return tab = {
        id: 'raxTabStrip',
        autodelay: 4500,
        allowed: 'select;autopilot;',
        options: {animate:true, theme:null, tabclick:null},
        init: function(){
            $.fn.raxTabStrip = function(options){       // create raxTabStrip() jQuery method
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
            var me = this;
            var cssClass = o && o.cssClass ? o.cssClass : 'rax-tabstrip';
            this.addClass(cssClass);
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
                .bind('click.'+tab.id, function(e){e.preventDefault();})// prevent clicking from <a> tag
                .each(function(){
                    var a,u = tab.parseTag(this);
                    if (!u.id) return;
                    a = $(this);
                    if (!a.parent().hasClass('selected-tab')) $('#'+u.id).hide();
                    else {
                        $('#'+u.id).show();
                        bag.current = u.id;
                    }
                });
                // select default tab
                if (o && o.selected!==undefined) tab.select.apply(me,[o.selected]);
                // enable autopilot
                if (o && o.autopilot!==undefined) tab.autopilot.apply(me,[o.autopilot]);
            })
        },

        autopilot: function(delay,rand) {
            delay = (delay===true) ? tab.autodelay : delay;
            return this.each(function() {
                var me=this, bag = $.data(this,tab.id); // data bag
                if (bag.autoTmr) window.clearTimeout(bag.autoTmr);
                if (delay===false) bag.autoTmr = 0;
                else {
                    bag.autodelay = delay;
                    bag.autoFn = function(){
                        var li = $('li',me);
                        var i = li.index($('li.selected-tab',me).get(0))+1;
                        if (rand) i = parseInt(Math.random() * li.length);
                        if (i > li.length-1) i = 0;
                        tab.select.call($(me),i);
                        bag.autoTmr = window.setTimeout(bag.autoFn,delay);
                    }
                    bag.autoTmr = window.setTimeout(bag.autoFn, delay);
                }
            })
        },

        // returns the id, url and selector from <a> tag
        parseTag: function(a) {
            var s,i,u,l = (a ? a.href : '').split('#');
            u = l.shift();i = l.join('#');
            if (i && i.indexOf(';')>=0) {
                l = i.split(';');i = l[0];s = unescape(l[1]);
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

            // reset autopilot timer if enabled
            if (bag.autoTmr) {
                window.clearTimeout(bag.autoTmr);
                bag.autoTmr = window.setTimeout(bag.autoFn, bag.autodelay);
            }

            var li = $('li',ul).removeClass('selected-tab');
            $(this).addClass('selected-tab');

            if (!u.id && u.url) window.location.href = u.url;
            else if (u.id && bag.current != u.id) {
                if (typeof o.animate == 'function') {
                    // custom animation: index, current, previous
                    o.animate.call(this, li.index(this), $('#'+u.id), $('#'+bag.current));
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
                        index:li.index(this),   // set tab index
                        container: bag.current  // set tab container id
                    }
                    o.tabclick.call(this,e);
                }

                e.type = 'tabclick';
                e.value = li.index(this);
                $(this).trigger(e);
            }
        }

    }
}(jQuery);
Raxan.UI.tabstrip.init(); // init


/**
 * Raxan Cursor
 * Displays an mouse image when activated */
Raxan.UI.cursor = function($){  // add to UI namespace
    var cursor;
    return cursor = {
        id: 'raxCursor',
        hourglass: '',  // default cursor to show when busy

        // init
        init: function() {
            // setup image
            $(function(){
                var img  = '<img id="'+cursor.id+'" style="position:absolute;left:-200px;display:none;z-index:10000" />';
                $('body').append(img);
                cursor._img = $('#'+cursor.id);
            })

            // setup plugin wrapper for jQuery
            $.fn.raxCursor = function(cmd,src){
                return this.each(function(){
                    var eid = '.'+cursor.id;
                    var move = 'mousemove'+eid;
                    var hover = 'mouseover'+eid+' mouseout'+eid;
                    var cb1 = cursor._eventHover;
                    var cb2 = cursor._eventMove;
                    switch (cmd) {
                        case 'hide':
                        case 'default':
                            $(this).unbind(eid);
                            cursor._img.hide();
                            break;
                        case 'busy':
                            $(this).bind(hover,cb1).bind(move,cb2);
                            break;
                        default: // show or display custom cursors
                            if(!src && cmd!='show') src = cmd;
                            $(this).bind(hover,src,cb1).bind(move,cb2)
                            break;
                    }
                })
            }
        },

        // show custom cursor
        _show: function(src){
            src = src ? src : this._src;
            // check if src has a path
            if (src.indexOf('/')==-1) src = Raxan.scriptpath+'cursors/'+src+'.gif';
            if (src) this._img.attr('src',src).show();
            this._src = src;
        },

        // show busy cursor
        _busy: function(){
            var o,url = (this.hourglass) ? this.hourglass : '';

            if(!url) {
                o = this._img.get(0);
                o.className = 'busy_cursor'; // get busy cursor from class name
                url = Raxan.scriptpath+'cursors/busy.gif';
                url = (o.style.backgroundImage) ? o.style.backgroundImage: url;
                o.className = '';
            }
            this._img.attr('src',url).show();
        },

        // hide cursor
        _hide: function() {
            this._img.hide();
        },

        // handle event callbacks
        _eventHover:  function(e){
            if (e.type!='mouseover') cursor._hide();
            else {
                cursor[e.data ? '_show':'_busy'](e.data);
                e.stopPropagation();
            }
        },
        _eventMove:  function(e){
            var x = e.pageX, y = e.pageY;
            cursor._img.css({left:x+20,top:y+20});
        }

    }

}(jQuery)
Raxan.UI.cursor.init(); // init plugin

