<?php
    require_once '../raxan/pdi/autostart.php';

    class ClientServerPage extends RaxanWebPage {

        protected function _init() {
            $this->loadCSS('master');
            $this->loadTheme('default');
            // registers a custom event
            $this->registerEvent('reversetext','.reverseTextEvent');
        }

        // -- Event handlers

        // normal event handler (full page post back)
        protected function normalEvent($e) {
            $val = $e->textVal();
            if ($val) $val = '. Value from client: '.$val;
            $this->panel1->text('Normal event handler'.$val);
        }

        // ajax event handler
        protected function ajaxEvent($e) {
            $this->panel1->text('Ajax event handler');
            $this->panel1->updateClient();
        }

        // serialize ajax event handler
        protected function serializeAjaxEvent($e) {
            $val = $this->post->textVal("hidden1"); //get form value
            $this->panel1->text('Serialize event handler. The value for hidden element is "'.$val.'"');
            $this->panel1->updateClient();
        }

        // normal event delegate handler
        protected function delegateEvent($e) {
            $this->panel1->text('Normal event delegate handler');
        }

        // ajax event delegate handler
        protected function delegateAjaxEvent($e) {
            $val = $e->textVal();
            $this->panel1->text('Ajax event delegate handler');
            $this->panel1->updateClient();
        }

        // reserve text event handler
        protected function reverseTextEvent($e) {
            $txt = $e->textVal();
            $rev = strrev($txt);
            $this->panel1->text('Reverse text event handler. Original: '.$txt.'. Reverse: '.$rev);
            $this->panel1->updateClient();
            return $rev; // return text to client
        }

        // set variable event handler
        protected function setVariableEvent($e) {
            $value = 'Hello Client!'; // value can be any php data type (e.g. arrays, strings, etc)
            $this->registerVar('message',$value);
        }

        // call client function event handler
        protected function callClientFnEvent($e) {
            $a = 12345;
            $b = 'Hello client';
            $c = array('red','green','blue');
            // call javascript clientUpdate function with parameters a, b and c
            $this->callScriptFn('clientUpdate',$a,$b,$c);
        }



    }
?>

<div class="prepend1">
    <div id="panel1" style="font-size:30px"></div>
    <form id="form1" name="form1" action="" method="post">

        <p>
            <input class="button" type="button" name="btnNormal1" id="btnNormal1" value="Normal Event" xt-bind="click,normalEvent" /> -
            Triggers an event on the server (full page post back).
        </p>

        <p>
            <input class="button" type="button" name="btnNormal2" id="btnNormal2" value="Normal Event with value"
                   data-event-value="BUTTON" xt-bind="click,normalEvent" />&nbsp;/&nbsp;
            <a href="#HYPERLINK" xt-bind="click,normalEvent">Normal link with value</a> -
            Triggers an event on the server with a value from the client. See the data-event-value attribute
        </p>

        <p>
            <input class="button" type="button" name="btnAjax1" id="btnAjax1" value="Ajax Event" xt-bind="#click,ajaxEvent" /> -
            Triggers an event on the server via an Ajax call.
        </p>

        <p xt-delegate=".cmd click,delegateEvent">
            <input class="cmd button" type="button" name="btnDelegate1" id="btnDelegate1" value="Normal Event Delegate" /> -
            Uses an event delegate to trigger an event on the server (full page post back).
        </p>

        <p xt-delegate=".special #click,delegateAjaxEvent">
            <input class="special button" type="button" name="btnDelegateAhax1" id="btnDelegateAhax1" value="Ajax Event Delegate" /> -
            Uses an event delegate to trigger an event on the server via an Ajax call.
        </p>

        <p>
            <input class="button" type="hidden" name="hidden1" id="hidden1" value="I-Am-Not-Visible" />
            <input class="button" type="button" name="btnSerialize1" id="btnSerialize1" value="Ajax Event - serialized #form1" xt-bind="#click,serializeAjaxEvent,#form1" /> -
            Triggers an event and submits the content of #form1 to the server via Ajax.
        </p>

        <p>
            <input class="button" type="button" name="btnDispath" id="btnDispath" value="Dispatch custom event" /> -
            Uses the Raxan.dispatchEvent() method to trigger a custom event on the server.
        </p>
        <script type="text/javascript">
            Raxan.ready(function(){
                $('#btnDispath').click(function(){
                    var txt = "Test message...";
                    Raxan.dispatchEvent('reversetext', txt, function(result, success){
                        if (!success) return;
                        alert("Result from server: " + result);
                    })
                })
            })
        </script>

        <p>
            <input class="button" type="button" name="btnVariable" id="btnVariable" value="Get variable from server"  xt-bind="#click,setVariableEvent" /> -
            Triggers an event and retrieves a variable from the server via Ajax.
        </p>
        <script type="text/javascript">
            Raxan.ready(function(){
                // retrieve the variable when togglecontent mode is off
                $('#btnVariable').bind('togglecontent',function(e,mode){
                    if (mode=='off') {
                        var msg = Raxan.getVar('message');
                        alert("Message from server: " + msg);
                    }
                })
            })
        </script>


        <p>
            <input class="button" type="button" name="btnCallClient" id="btnCallClient" value="Call a JavaScript function from server"  xt-bind="#click,callClientFnEvent" /> -
            Uses the RaxanWebPage->callScriptFn() method to call a JavaScript function from the server.
        </p>
        <script type="text/javascript">
            function clientUpdate(a,b,c){
                alert('Client update method \n' +
                    '-------------------------------\n' +
                    'Parameter A: '+ a + "\n" +
                    'Parameter B: '+ b+ "\n" +
                    'Parameter C: '+ c
            );
            }
        </script>

    </form>
</div>