<?php
/**
 * Shoutbox - An Ajax Shoutbox (used in embeddable demo)
 */

require_once('../raxan/pdi/autostart.php');

class ShoutPage extends RaxanWebPage {

    protected $db;
    protected $updateList = false;

    protected function _config() {
        // enable/disable debugging
        $this->Raxan->config('debug', false);
        $this->Raxan->config('debug.output', 'embedded');
    }

    protected function _init() {
        $this->source('views/shoutbox.html');
        // connect to DB in silent mode (errors will be suppressed)
        $dbFile = 'data/shouts.db'; 
        $this->db = $this->Raxan->connect('sqlite:'.$dbFile);
        if (!$this->db) {
            $this->flashmsg('Error while connecting to database.','fade','pad softred');
            $this->endResponse();
            $this->lstShouts->remove();
        }
    }
    
    protected function _load() {
        // bind events - bind to form submit and disbable the save button when clicked
        $this->shoutbox->bind('#submit',array('callback'=>'.save_shout','autoDisable'=>'#cmdsave'));
        $this->lstShouts->delegate('a.remove','#click','.remove_shout');
    }

    protected function _prerender() {         
        if (!$this->isCallback||$this->updateList) {
            $this->showList(); // show list
        }
    }

    // Event handlers -----------------------
    
    protected function save_shout($e) {
        // sanitize request data
        $name = trim($this->post->textVal('name'));
        $message = trim($this->post->textVal('message'));
        
        // validate
        $msg = '';
        if ($name=='') $msg = "* Missing Name<br />";
        if ($message=='') $msg.= "* Missing Message<br />";
        if ($msg) $this->flashmsg($msg,'fade','pad softred'); // show validation messages
        else {
            // insert/update record
            $message = nl2br($message);
            $rt = $this->db->tableInsert('shouts',array(
                'name' => $name,
                'message' => $message,
            ));
            if (!$rt) $this->flashmsg('Error while saving shout. Make sure database is writable.','fade','pad softred');
            else {
                $this->updateList = true;
                $this->resetForm(); // reset form fields
            }
        }
    }


    protected function remove_shout($e) {
        $id = $e->intVal();
        $this->db->tableDelete('shouts','id=?',$id);
        $this->updateList = true;
    }

    protected function resetForm() {
        // clear form text fields on client
        c('#shoutbox .textbox')->val('');
    }

    protected function showList() {
        try {
            $sql = 'SELECT * FROM shouts ORDER BY id desc LIMIT(10)';
            $rs = $this->db->query($sql);
            $this->lstShouts->bind($rs); // bind result to #list
            $this->lstShouts->updateClient(); // update list on client when in ajax mode
        }catch(Exception $e) {
            $this->flashmsg('Error while fetching records','fade','pad softred');
            $this->Raxan->debug('Error while fetching records -> '.$e);
        }
    }

}

?>