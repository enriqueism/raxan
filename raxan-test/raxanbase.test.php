<?php

require_once 'unit.test.php';
require_once '../raxan/pdi/gateway.php';

/**
 * RaxanBase Class Test
 */
class TestRaxanBase extends UnitTest {

    function _setup(){
        $this->name='RaxanBase Test Script';
        $this->description = 'Unit test script for the RaxanBase main class';
    }

    function testBind() {
        $base = new BaseClass();
        $base->bind('event', 'test_callback');
        $e = $base->getEvents();
        $this->ok(count($e),1,'Bind event to callback');
    }

    function testLog() {
        $msg = 'Base Class test log';
        $t = date('Y-m-d H:i:s',time());
        $file = dirname(__FILE__).'/logfile.txt';
        file_put_contents($file, '');
        Raxan::config('log.enable',true);
        Raxan::config('log.file',$file);
        // log
        $base = new BaseClass();
        $base->log($msg);
        $c = file_get_contents($file);
        $this->compare("INFO \t".$t." \t \t".$msg, trim($c),'Log file content');
        // log with params
        file_put_contents($file, '');
        $base->log($msg,'ERROR','Label');
        $t = date('Y-m-d H:i:s',time());
        $c = file_get_contents($file);
        $this->compare("ERROR \t".$t." \t [Label] \t".$msg, trim($c),'Log with label params');
    }

    function testObjectId() {
        $base = new BaseClass();
        $id = $base->objectId();
        $base2 = new BaseClass();
        $id2 = $base2->objectId();
        $this->ok($id!=$id2,'Base Object 1 Id <> Base Object 2 Id');
    }

    function testTrigger() {
        global $eArgs, $eData;
        $eArgs = $eData = null;
        $args = 'This is a test';
        $data = array(1,2,4,5);
        $base = new BaseClass();
        $base->bind('firstevent', 'test_callback');
        $base->bind('secondevent', $data,'test_callback');
        $base->trigger('firstevent',$args);
        $this->ok($eArgs == $args,'Trigger first event callback with optional arguements');
        $base->trigger('secondevent');
        $this->compare($eData,$data,'Trigger second event and compare data ');
    }

    function testUnbind() {
        global $eArgs, $eData;
        $eArgs = $eData = null;
        $args = 'This is a test';
        $data = array(1,2,4,5);
        $base = new BaseClass();
        
        $base->bind('firstevent', 'test_callback');
        $base->bind('secondevent', $data,'test_callback');

        $base->unbind('firstevent');

        $base->trigger('firstevent',$args);
        $this->okFalse($eArgs == $args,'Attempting to trigger first event after unbind()');

        $base->unbind('secondevent');

        $base->trigger('secondevent');
        $this->okFalse($eData===$data,'Attempting to trigger second event after unbind()');
    }

}


class BaseClass extends RaxanBase {

    function getEvents() {
        return $this->events;
    }

}

global $eArgs, $eData;
function test_callback($e,$args = null) {
    global $eArgs, $eData;
    $eArgs  = $args;
    $eData  = $e->data;
}


?>