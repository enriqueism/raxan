/**
 * Raxan Cursor Plugin
 * Displays an mouse image when activated
 * Requires: Raxan and jQuery
 */

var c;
// add to rich namespace
Raxan.rich.cursor = c = function($){
    var cursor;
    return cursor = {
        id: 'rxCursor',
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
            $.fn.cursor = function(cmd,src){
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
            if (src.indexOf('/')==-1) src = html.scriptpath+'rich/cursors/'+src+'.gif';
            if (src) this._img.attr('src',src).show();
            this._src = src;
        },

        // show busy cursor
        _busy: function(){
            var o,url = (this.hourglass) ? this.hourglass : '';
            
            if(!url) {
                o = this._img.get(0);
                o.className = 'busy_cursor'; // get busy cursor from class name
                url = html.scriptpath+'rich/cursors/busy.gif';
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

c.init(); // init plugin

