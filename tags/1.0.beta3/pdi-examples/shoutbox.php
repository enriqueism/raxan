<?php
/**
 * Embedded Shoutbox Demo
 * An Ajax Shoutbox
 */

require_once('../raxan/pdi/gateway.php');

// Enable or disable debugging
//RichAPI::config('debug', true);
//RichAPI::config('debug.output', 'embedded');

class ShoutPage extends RichWebPage {

    private $db;

    protected function _init() {
        $this->source('views/shoutbox.html');
        $this->loadCSS('master');
        $this->loadCSS('default/theme');

        // connect to DB
        $dbFile = './shouts.db'; // for demo only - change db path
        $this->db = RichAPI::Connect('sqlite:'.$dbFile);
        if (!$this->db) {
            $this['body']->text('Error while connecting to database');
            $this->reply();
            exit();
        }

        // create the table
        if (filesize($dbFile)==0) {
            $this->db->query('CREATE TABLE shouts(
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL, message TEXT
            )');
        }
    }
    
    protected function _load() {
        // bind events
        // bind to form submit and disbable the save button when clicked
        $this['#shoutbox']->bind('#submit',array('callback'=>'.save_shout','autoDisable'=>'#cmdsave'));
        $this['#cmdcancel']->bind('#click','.cancel_edit');
        $this['#list a.remove']->delegate('#click','.remove_shout');

        // show list if not ajax request
        if (!$this->isCallback) $this->showList();
    }

    protected function cancel_edit($e) {
        $this->resetForm(true);
    }

    // save shout
    protected function save_shout($e) {
        // sanitize request data
        $rq = $e->page()->clientRequest();
        $name = trim($rq->text('name'));
        $message = trim($rq->text('message'));
        
        // validate
        $msg = '';
        if ($name=='') $msg = "* Missing Name<br />";
        if ($message=='') $msg.= "* Missing Message<br />";
        if ($msg) {
            // show validation messages
            C('#msg')->hide()->html($msg)
                ->addClass('error notice')
                ->fadeIn();
        }
        else {
            // insert/update record
            $message = nl2br($message);
            $sql = 'INSERT INTO shouts (name,message) VALUES(:name,:message)';
            $qs = $this->db->prepare($sql);
            $qs->bindParam(':name',$name, PDO::PARAM_STR);
            $qs->bindParam(':message',$message, PDO::PARAM_STR);
            $qs->execute();

            $this->showList();
            C('#msg')->fadeOut(); // hide mesages if previously displayed
            $this->resetForm(); // reset form fields
        }
    }


    protected function remove_shout($e) {
        $id = (int)$e->value;
        $sql = 'DELETE FROM shouts WHERE id='.$id;
        $this->db->exec($sql);

        $this->showList();
    }

    protected function resetForm() {
        C('#shoutbox .textbox')->val(''); // clear form text fields
    }

    protected function showList() {
        $sql = 'SELECT * FROM shouts ORDER BY id desc LIMIT(10)';
        try {
            $rs = $this->db->query($sql);
            $this['#list']->bind($rs); // bind result to #list
            if ($this->isCallback) {
                // update list on client when in ajax mode
                C('#list')->html($this['#list']->html());
            }
        }catch(Exception $e) {
            RichAPI::debug('Error while fetching records -> '.$e);
        }
    }


}

$page = new ShoutPage();
$page->reply();

?>