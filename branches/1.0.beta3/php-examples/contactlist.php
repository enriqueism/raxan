<?php
/**
 * Contact List Demo
 * A degradable Ajax Web form exmaple database storage
 */

require_once('../raxan/pdi/autostart.php');

class ContactPage extends RaxanWebPage {

    protected $degradable = true;
    protected $preserveFormContent = true;
    
    private $db;

    protected function _init() {

        // enable or disable debugging
        Raxan::config('debug', false);

        // load html source file
        $this->source('views/contactlist.html');
        // load Raxan css framework
        $this->loadCSS('master')->loadCSS('default/theme');

        $this->connectToDB();

    }
    
    protected function _prerender() {        
            $this->showList(); // show list if not ajax request
    }

    protected function cancelEdit($e) {
        $this->resetForm(true);
    }

    // add or update contact
    protected function saveContact($e) {
        // sanitize request data
        $post = $this->sanitizePostBack();
        $name = $post->text('name');
        $address = $post->text('address');
        $phone = $post->text('phone');

        // validate
        $msg = '';
        if ($name=='') $msg = "* Missing Name<br />";
        if ($address=='') $msg.= "* Missing Address<br />";
        if ($msg) {
            // show validation messages
            $this->msg->show()
                ->html($msg)
                ->addClass('error notice')
                ->client->hide()->fadeIn();
                return;
        }
        else {
            // insert/update record
            $id = $this->rowid->intval();
            if ($id) {
                $sql = 'UPDATE contacts set name=:name,address=:address,phone=:phone WHERE id=:id';
            } else {
                $sql = 'INSERT INTO contacts (name,address,phone) VALUES(:name,:address,:phone)';
            }

            try {
                $qs = $this->db->prepare($sql);
                $qs->bindParam(':name',$name, PDO::PARAM_STR);
                $qs->bindParam(':address',$address, PDO::PARAM_STR);
                $qs->bindParam(':phone',$phone, PDO::PARAM_STR);
                if ($id) $qs->bindParam(':id',$id, PDO::PARAM_STR);
                $qs->execute();
                if ($this->isCallback) $this->showList();
            }
            catch(Exception $e) {
                $msg = 'Error while saving record';
                Raxan::debug($msg.' '.$e);
                $this->content($msg)->endResponse();
                return;
            }

            $this->msg->client->fadeOut(); // hide mesages if previously displayed
            $this->resetForm($id ? true : false); // reset form fields
        }
    }

    protected function editContact($e) {
        $id = (int)$e->value;

        try {
            $sql = 'SELECT * FROM contacts WHERE id='.$id;
            $rs = $this->db->query($sql);
            $row = $rs->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            $msg = 'Error while ediing record';
            Raxan::debug($msg.' '.$e);
            $this->content($msg)->endResponse();
            return;
        }

        // populate form field
        $this->name->val($row['name']);
        $this->address->val($row['address']);
        $this->phone->val($row['phone']);

        // set value to be returned by event when form is submitted
        $this->rowid->val($id); // set event value using form class

        // setup buttons
        $this->cmdcancel->show();
        $this->cmdsave->val('Save Contact')->addClass('process');
        
        $this->contact->updateClient(); // update web form
    }

    protected function removeContact($e) {
        $id = $e->intval();

        try {
            $sql = 'DELETE FROM contacts WHERE id='.$id;
            $this->db->exec($sql);
            if ($this->isCallback) $this->showList();
        }
        catch(Exception $e) {
            $msg = 'Error while deleting records';
            Raxan::debug($msg.' '.$e);
            $this->content($msg)->endResponse();
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

    protected function showList() {
        $sql = 'SELECT * FROM contacts ORDER BY id desc';
        try {
            $rs = $this->db->query($sql);
            $this->list->bind($rs); // bind result to #list
            if ($this->isCallback) $this->list->updateClient(); // manually update list on client
        }catch(Exception $e) {
            $msg = 'Error while fetching records';
            Raxan::debug($msg.' '.$e);
            $this->content($msg)->endResponse();
            return;
        }

    }

    protected function connectToDB() {
        // connect to SQLite database
        $dbFile = './contacts.db'; // for demo only - change db path and enable read/write permissions on file
        try {
            $this->db = Raxan::connect('sqlite:'.$dbFile,null,null,true); // last param will enable exception error mode
            // create the table
            if (filesize($dbFile)==0) {
                $this->db->query('CREATE TABLE contacts(
                    id INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    address TEXT,
                    phone TEXT
                )');
            }
        }
        catch(Exception $e) {
            $msg ='Error while connecting to database';
            Raxan::debug($msg.' '.$e);
            $this->content($msg)->endResponse();
        }
    }

}

?>