/**
 * jQuery Utils
 * A combination of jQuery LoadMask plugin and ui Position
 * For use with Raxan
 *
 * Changes:
 * --------------
 *   Add default style to loadmask plugin
 *
 */



 /**
 * Copyright (c) 2009 Sergiy Kovalchuk (serg472@gmail.com)
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Following code is based on Element.mask() implementation from ExtJS framework (http://extjs.com/)
 *
 */
;(function($){

    /**
     * Displays loading mask over selected element(s). Accepts both single and multiple selectors.
     *
     * @param label Text message that will be displayed on top of the mask besides a spinner (optional).
     *              If not provided only mask will be displayed without a label or a spinner.
     * @param delay Delay in milliseconds before element is masked (optional). If unmask() is called
     *              before the delay times out, no mask is displayed. This can be used to prevent unnecessary
     *              mask display for quick processes.
     */
    $.fn.mask = function(label, delay){
        $(this).each(function() {
            if(delay !== undefined && delay > 0) {
                var element = $(this);
                element.data("_mask_timeout", setTimeout(function() { $.maskElement(element, label)}, delay));
            } else {
                $.maskElement($(this), label);
            }
        });
    };

    /**
     * Removes mask from the element(s). Accepts both single and multiple selectors.
     */
    $.fn.unmask = function(){
        $(this).each(function() {
            $.unmaskElement($(this));
        });
    };

    /**
     * Checks if a single element is masked. Returns false if mask is delayed or not displayed.
     */
    $.fn.isMasked = function(){
        return this.hasClass("masked");
    };

    $.maskElement = function(element, label){

        //if this element has delayed mask scheduled then remove it and display the new one
        if (element.data("_mask_timeout") !== undefined) {
            clearTimeout(element.data("_mask_timeout"));
            element.removeData("_mask_timeout");
        }

        if(element.isMasked()) {
            $.unmaskElement(element);
        }

        if(element.css("position") == "static") {
            element.addClass("masked-relative");
        }

        element.addClass("masked");

        var maskDiv = $('<div class="loadmask"></div>');

        //auto height fix for IE
        if(navigator.userAgent.toLowerCase().indexOf("msie") > -1){
            maskDiv.height(element.height() + parseInt(element.css("padding-top")) + parseInt(element.css("padding-bottom")));
            maskDiv.width(element.width() + parseInt(element.css("padding-left")) + parseInt(element.css("padding-right")));
        }

        //fix for z-index bug with selects in IE6
        if(navigator.userAgent.toLowerCase().indexOf("msie 6") > -1){
            element.find("select").addClass("masked-hidden");
        }

        element.append(maskDiv);

        if(label !== undefined) {
            var maskMsgDiv = $('<div class="loadmask-msg" style="display:none;"></div>');
            maskMsgDiv.append('<div>' + label + '</div>');
            element.append(maskMsgDiv);

            //calculate center position
            maskMsgDiv.css("top", Math.round(element.height() / 2 - (maskMsgDiv.height() - parseInt(maskMsgDiv.css("padding-top")) - parseInt(maskMsgDiv.css("padding-bottom"))) / 2)+"px");
            maskMsgDiv.css("left", Math.round(element.width() / 2 - (maskMsgDiv.width() - parseInt(maskMsgDiv.css("padding-left")) - parseInt(maskMsgDiv.css("padding-right"))) / 2)+"px");

            maskMsgDiv.show();
        }

    };

    $.unmaskElement = function(element){
        //if this element has delayed mask scheduled then remove it
        if (element.data("_mask_timeout") !== undefined) {
            clearTimeout(element.data("_mask_timeout"));
            element.removeData("_mask_timeout");
        }

        element.find(".loadmask-msg,.loadmask").remove();
        element.removeClass("masked");
        element.removeClass("masked-relative");
        element.find("select").removeClass("masked-hidden");
    };

    // setup default style
    $('head').prepend('<style type="text/css">'
    +'.loadmask {'
    +'    top:0; left:0; z-index: 100; background-color: #ccc;'
    +'    position: absolute; width: 100%; height: 100%; zoom: 1;'
    +'    opacity: .50; -moz-opacity: 0.5; filter: alpha(opacity=50);'
    +'}'
    +'.masked { overflow: hidden !important; }'
    +'.masked-relative { position: relative !important; }'
    +'.masked-hidden { visibility: hidden !important; }'
    +'.loadmask-msg div {'
    +'    cursor:wait; padding:5px;color:#555; background-color: #eee;'
    +'}'
    +'.loadmask-msg {'
    +'    padding:2px; border:1px solid #777; background: #ccc;'
    +'    position: absolute; top: 0; left: 0; z-index: 20001;'
    +'}'
    +'</style>');


})(jQuery);



/*
 * jQuery UI Position 1.8.1
 *
 * Copyright (c) 2010 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Position
 */
(function( $ ) {

$.ui = $.ui || {};

var horizontalPositions = /left|center|right/,
    horizontalDefault = "center",
    verticalPositions = /top|center|bottom/,
    verticalDefault = "center",
    _position = $.fn.position,
    _offset = $.fn.offset;

$.fn.position = function( options ) {
    if ( !options || !options.of ) {
        return _position.apply( this, arguments );
    }

    // make a copy, we don't want to modify arguments
    options = $.extend( {}, options );

    var target = $( options.of ),
        collision = ( options.collision || "flip" ).split( " " ),
        offset = options.offset ? options.offset.split( " " ) : [ 0, 0 ],
        targetWidth,
        targetHeight,
        basePosition;

    if ( options.of.nodeType === 9 ) {
        targetWidth = target.width();
        targetHeight = target.height();
        basePosition = { top: 0, left: 0 };
    } else if ( options.of.scrollTo && options.of.document ) {
        targetWidth = target.width();
        targetHeight = target.height();
        basePosition = { top: target.scrollTop(), left: target.scrollLeft() };
    } else if ( options.of.preventDefault ) {
        // force left top to allow flipping
        options.at = "left top";
        targetWidth = targetHeight = 0;
        basePosition = { top: options.of.pageY, left: options.of.pageX };
    } else {
        targetWidth = target.outerWidth();
        targetHeight = target.outerHeight();
        basePosition = target.offset();
    }

    // force my and at to have valid horizontal and veritcal positions
    // if a value is missing or invalid, it will be converted to center
    $.each( [ "my", "at" ], function() {
        var pos = ( options[this] || "" ).split( " " );
        if ( pos.length === 1) {
            pos = horizontalPositions.test( pos[0] ) ?
                pos.concat( [verticalDefault] ) :
                verticalPositions.test( pos[0] ) ?
                    [ horizontalDefault ].concat( pos ) :
                    [ horizontalDefault, verticalDefault ];
        }
        pos[ 0 ] = horizontalPositions.test( pos[0] ) ? pos[ 0 ] : horizontalDefault;
        pos[ 1 ] = verticalPositions.test( pos[1] ) ? pos[ 1 ] : verticalDefault;
        options[ this ] = pos;
    });

    // normalize collision option
    if ( collision.length === 1 ) {
        collision[ 1 ] = collision[ 0 ];
    }

    // normalize offset option
    offset[ 0 ] = parseInt( offset[0], 10 ) || 0;
    if ( offset.length === 1 ) {
        offset[ 1 ] = offset[ 0 ];
    }
    offset[ 1 ] = parseInt( offset[1], 10 ) || 0;

    if ( options.at[0] === "right" ) {
        basePosition.left += targetWidth;
    } else if (options.at[0] === horizontalDefault ) {
        basePosition.left += targetWidth / 2;
    }

    if ( options.at[1] === "bottom" ) {
        basePosition.top += targetHeight;
    } else if ( options.at[1] === verticalDefault ) {
        basePosition.top += targetHeight / 2;
    }

    basePosition.left += offset[ 0 ];
    basePosition.top += offset[ 1 ];

    return this.each(function() {
        var elem = $( this ),
            elemWidth = elem.outerWidth(),
            elemHeight = elem.outerHeight(),
            position = $.extend( {}, basePosition );

        if ( options.my[0] === "right" ) {
            position.left -= elemWidth;
        } else if ( options.my[0] === horizontalDefault ) {
            position.left -= elemWidth / 2;
        }

        if ( options.my[1] === "bottom" ) {
            position.top -= elemHeight;
        } else if ( options.my[1] === verticalDefault ) {
            position.top -= elemHeight / 2;
        }

        // prevent fractions (see #5280)
        position.left = parseInt( position.left );
        position.top = parseInt( position.top );

        $.each( [ "left", "top" ], function( i, dir ) {
            if ( $.ui.position[ collision[i] ] ) {
                $.ui.position[ collision[i] ][ dir ]( position, {
                    targetWidth: targetWidth,
                    targetHeight: targetHeight,
                    elemWidth: elemWidth,
                    elemHeight: elemHeight,
                    offset: offset,
                    my: options.my,
                    at: options.at
                });
            }
        });

        if ( $.fn.bgiframe ) {
            elem.bgiframe();
        }
        elem.offset( $.extend( position, { using: options.using } ) );
    });
};

$.ui.position = {
    fit: {
        left: function( position, data ) {
            var win = $( window ),
                over = position.left + data.elemWidth - win.width() - win.scrollLeft();
            position.left = over > 0 ? position.left - over : Math.max( 0, position.left );
        },
        top: function( position, data ) {
            var win = $( window ),
                over = position.top + data.elemHeight - win.height() - win.scrollTop();
            position.top = over > 0 ? position.top - over : Math.max( 0, position.top );
        }
    },

    flip: {
        left: function( position, data ) {
            if ( data.at[0] === "center" ) {
                return;
            }
            var win = $( window ),
                over = position.left + data.elemWidth - win.width() - win.scrollLeft(),
                myOffset = data.my[ 0 ] === "left" ?
                    -data.elemWidth :
                    data.my[ 0 ] === "right" ?
                        data.elemWidth :
                        0,
                offset = -2 * data.offset[ 0 ];
            position.left += position.left < 0 ?
                myOffset + data.targetWidth + offset :
                over > 0 ?
                    myOffset - data.targetWidth + offset :
                    0;
        },
        top: function( position, data ) {
            if ( data.at[1] === "center" ) {
                return;
            }
            var win = $( window ),
                over = position.top + data.elemHeight - win.height() - win.scrollTop(),
                myOffset = data.my[ 1 ] === "top" ?
                    -data.elemHeight :
                    data.my[ 1 ] === "bottom" ?
                        data.elemHeight :
                        0,
                atOffset = data.at[ 1 ] === "top" ?
                    data.targetHeight :
                    -data.targetHeight,
                offset = -2 * data.offset[ 1 ];
            position.top += position.top < 0 ?
                myOffset + data.targetHeight + offset :
                over > 0 ?
                    myOffset + atOffset + offset :
                    0;
        }
    }
};

// offset setter from jQuery 1.4
if ( !$.offset.setOffset ) {
    $.offset.setOffset = function( elem, options ) {
        // set position first, in-case top/left are set even on static elem
        if ( /static/.test( $.curCSS( elem, "position" ) ) ) {
            elem.style.position = "relative";
        }
        var curElem   = $( elem ),
            curOffset = curElem.offset(),
            curTop    = parseInt( $.curCSS( elem, "top",  true ), 10 ) || 0,
            curLeft   = parseInt( $.curCSS( elem, "left", true ), 10)  || 0,
            props     = {
                top:  (options.top  - curOffset.top)  + curTop,
                left: (options.left - curOffset.left) + curLeft
            };

        if ( 'using' in options ) {
            options.using.call( elem, props );
        } else {
            curElem.css( props );
        }
    };

    $.fn.offset = function( options ) {
        var elem = this[ 0 ];
        if ( !elem || !elem.ownerDocument ) { return null; }
        if ( options ) {
            return this.each(function() {
                $.offset.setOffset( this, options );
            });
        }
        return _offset.call( this );
    };
}

}( jQuery ));
