/**
 * Bouncing Ball Code-Behind File
 * Wriiten by Raymond Irving
 *
 * Credits:
 * --------------
 * This example is based on the article:Physics in Flash - Gravity - Written by Craig Smith.
 * Visit http://www.spoono.com/flash/tutorials/tutorial.php?url=gravity
 *
 */

// load css framework with default theme
Raxan.css('master')
Raxan.css('default/theme')

// load jquery libraries
Raxan.include('jquery')
Raxan.include('jquery-ui-interactions')

var gravity = 2.5;
var restitution = 0.7;
var friction = 0.9;

var ballSize;
var boundry;
var ball;
var timer = null;
var animateBox = true;
var boxShift = false;

var vel = { x: 1, y:1 };
var old = { x: 10, y: 10 };
var pos = { x: 10, y: 10 };
var lastPos = {};

var dragging = false;

Raxan.ready(function(){
    var box = $('#box');

    ball = $('#ball');
    vel = { x: 1, y: 1   };
    pos = { x: 10, y: 10 };
    old = { x: 10, y: 10 };
    ballSize = ball.height();
    boundry = { width: box.width(), height: box.height() };

    ball.css({ top:1, left:1 })
        .draggable({
            start:function(){dragging = true; clearTimeout(timer)},
            stop:function(){dragging = false; moveBall();},
            drag:function() {
                var b = $(this);
                old.x = pos.x;
                old.y = pos.y;
                pos.x = parseInt(b.css('left'));
                pos.y = parseInt(b.css('top'));
                vel.x = ( pos.x - old.x ) / .5;
                vel.y = ( pos.y - old.y ) / .5  ;
            }
        });

    timer = setTimeout("self.moveBall()",100);

});

// listen to button click event
Raxan.bind('#btnSave','click',function(){
    var f = document.forms['frmSettings'];

    animateBox = (f.elements['anibox'].checked) ? true : false;
    gravity = parseFloat(f.elements['gravity'].value);
    restitution = parseFloat(f.elements['restitution'].value);
    friction = parseFloat(f.elements['friction'].value);

    this.blur();

    $('#msgbox').animate({top:-5})
    .animate({color:'#000'},1500)
    .animate({top:-50});

    return false;
})


// moveball function
self.moveBall = function() {

    if(!dragging) {
        vel.y += gravity;

        pos.x += vel.x;
        pos.y += vel.y;

        if (boxShift) {
            $('#box').css({padding:0,margin:0});
            boxShift = false;
        }

        if( pos.x + ballSize > boundry.width ) {
            pos.x = boundry.width - ballSize;
            vel.x *= -restitution;
            if(animateBox) {
                $('#box').css('padding-right',4);
                boxShift = true;
            }
        }

        // check if ball left the bottom of the stage.
        if( pos.y + ballSize > boundry.height ) {
            pos.y = boundry.height - ballSize;
            vel.y *= -restitution;
            vel.x *= friction;
            if (animateBox && lastPos.y!=pos.y) {
                $('#box').css('padding-bottom',2);
                boxShift = true;
            }
        }

        if( pos.x < 0 ) {
            pos.x = 0;
            vel.x *= -restitution;
            if(animateBox) {
                $('#box').css('margin-left',-4);
                boxShift = true;
            }
        }

        if( pos.y < 0 ) {
            pos.y = 0;
            vel.y *= -restitution;
            if(animateBox) {
                $('#box').css('margin-top',-4);
                boxShift = true;
            }
        }

        // update position of the ball
        ball.css({ top:pos.y, left:pos.x })
        lastPos.x = pos.x; lastPos.y = pos.y

        // set timeout
        timer = setTimeout("self.moveBall()",50);
    }

}
