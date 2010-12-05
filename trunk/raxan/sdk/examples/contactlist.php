<?php
/**
 * Contact List Demo
 * A degradable Ajax Web form example
 */

require_once('../raxan/pdi/autostart.php');

class ContactPage extends RaxanWebPage {

    protected $db;
    protected $updateList;
    
    protected function _config() {
        $this->degradable = true;
        $this->preserveFormContent = true;
        // enable or disable debugging
        $this->Raxan->config('debug', false);
        $this->icon = '<span title="Close" class="right close ui-icon ui-icon-close click-cursor"></span>';
    }

    protected function _init() {        
        $this->source('views/contactlist.html'); // load html source file
        $this->loadCSS('master');   // load css framework and default theme
        $this->loadTheme('default'); // load default/theme.css
        $this->connectToDB();   // connect to db
    }
    
    protected function _prerender() {
        // show contacts
        if (!$this->isCallback||$this->updateList) $this->showContacts(); 
    }

    // Events Handlers -------------------

    protected function cancelEdit($e) {
        $this->resetForm(true);
    }

    // add or update contact
    protected function saveContact($e) {
        // sanitize input
        $data = $this->post->filterValues('name,address,phone');

        // validate
        $msg = '';
        if ($data['name']=='') $msg = "* Missing Name<br />";
        if ($data['address']=='') $msg.= "* Missing Address<br />";        
        if ($msg) {
            // flash validation message to screen
            $this->flashmsg($this->icon.$msg,'fade','rax-box error');
        }
        else {
            try {
                // insert/update record
                $id = $this->rowid->intVal(); // get row id
                if ($id) $this->db->tableUpdate('contacts',$data,'id=?',$id);
                else $this->db->tableInsert('contacts',$data);
                $this->flashmsg($this->icon.'Record successfully '.($id ? 'modified':'created'),'fade','rax-box success');
            }
            catch(Exception $e) {
                $msg = 'Error while saving record';
                $this->flashmsg($this->icon.$msg,'fade','rax-box error');
                $this->Raxan->debug($msg.' '.$e);
                return;
            }
            $this->updateList = true;
            $this->resetForm($id ? true : false); // reset form fields
        }
    }

    protected function editContact($e) {
        try {
            $id = $e->intVal() | 0;
            $row = $this->db->table('contacts','id=?',$id);
            if (!$row) throw new Exception('Invalid redord');
            
            // populate form field
            $this->name->val($row[0]['name']);
            $this->address->val($row[0]['address']);
            $this->phone->val($row[0]['phone']);

            // set value to be returned by event when form is submitted
            $this->rowid->val($id); // set event value using form class

            // setup buttons
            $this->cmdcancel->show();
            $this->cmdsave->val('Save Contact')->addClass('process');

            // update web form
            $this->contact->updateClient();
        }
        catch(Exception $e) {
            $msg = 'Error while ediing record';
            $this->flashmsg($this->icon.$msg,'fade','rax-box error');
            $this->Raxan->debug($msg.' '.$e);
        }
    }

    protected function removeContact($e) {
        try {
            $id = $e->intval();
            $this->db->tableDelete('contacts','id=?',$id);
            $this->updateList = true;
            $this->flashmsg($this->icon.'Record successfully removed','fade','rax-box success');
        } catch(Exception $e) {
            $msg = 'Error while deleting records';
            $this->flashmsg($this->icon.$msg,'fade','rax-box error');
            $this->Raxan->debug($msg.' '.$e);
            return;
        }
    }

    protected function resetForm($isEdit) {
        $selector = '#contact input.textbox, #rowid';
        $this[$selector]->val(''); // clear form text fields
        if ($isEdit) {
            $this->cmdcancel->hide();
            $this->cmdsave->val('Add Contact')
                 ->removeClass('process');
        }
        $this->contact->updateClient(); // update web form
    }

    protected function showContacts() {        
        try {
            if (!$this->db) $this->list1->remove(); // remove list1
            else {
                $rs = $this->db->query('SELECT * FROM contacts ORDER BY id desc');
                $this->list1->bind($rs); // bind result to #list
                $this->list1->updateClient(); // manually update list on client
            }
        } catch(Exception $e) {
            $msg = 'Error while fetching records';
            $this->flashmsg($this->icon.$msg,'fade','rax-box error');
            $this->Raxan->debug($msg.' '.$e);
        }
    }

    protected function connectToDB() {
        try {
            // path to SQLite database - enable read/write permissions on file
            $dbFile = 'data/contacts.db';
            // connect to db - last param will enable exception error mode
            $this->db = $this->Raxan->connect('sqlite:'.$dbFile,null,null,true);
        } catch(Exception $e) {
            $msg ='Error while connecting to database '.$dbFile.'.';
            $this->Raxan->debug($msg.' '.$e);
            $this->flashmsg($this->icon.$msg,'fade','rax-box error');
        }
    }

}

?>