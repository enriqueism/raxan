<?php
/**
 * Contact List Demo
 * An Ajax Web form to Database example
 */

require_once('../raxan/pdi/gateway.php');

// Enable or disable debugging
//RichAPI::config('debug', true);
//RichAPI::config('debug.output', 'embedded');

class ContactPage extends RichWebPage {

    private $db;

    protected function _init() {
        $this->source('views/contactlist.html');
        $this->loadCSS('master');
        $this->loadCSS('default/theme');

        // connect to DB
        $dbFile = './contacts.db'; // for demo only - change db path
        $this->db = RichAPI::Connect('sqlite:'.$dbFile);
        if (!$this->db) {
            $this['body']->text('Error while connecting to database');
            $this->reply();
            exit();
        }

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
    
    protected function _load() {
        // bind events
        // bind to form submit and disbable the save button when clicked
        $this['#contact']->bind('#submit',array('callback'=>'.save_contact','autoDisable'=>'#cmdsave'));
        $this['#cmdcancel']->bind('#click','.cancel_edit');
        $this['#list a.edit']->delegate('#click','.edit_contact'); // bind to hyperlinks
        $this['#list a.remove']->delegate('#click','.remove_contact'); 

        // show list if not ajax request
        if (!$this->isCallback) $this->showList();
    }

    protected function cancel_edit($e) {
        $this->resetForm(true);
    }

    // add or update contact
    protected function save_contact($e) {
        // sanitize request data
        $rq = $e->page()->clientRequest();
        $name = $rq->text('name');
        $address = $rq->text('address');
        $phone = $rq->text('phone');
        
        // validate
        $msg = '';
        if ($name=='') $msg = "* Missing Name<br />";
        if ($address=='') $msg.= "* Missing Address<br />";
        if ($msg) {
            // show validation messages
            C('#msg')->hide()->html($msg)
                ->addClass('error notice')
                ->fadeIn();
        }
        else {
            // insert/update record
            $id = (int)$e->value;
            if ($id) {
                $sql = 'UPDATE contacts set name=:name,address=:address,phone=:phone WHERE id=:id';
            } else {
                $sql = 'INSERT INTO contacts (name,address,phone) VALUES(:name,:address,:phone)';
            }

            $qs = $this->db->prepare($sql);
            $qs->bindParam(':name',$name, PDO::PARAM_STR);
            $qs->bindParam(':address',$address, PDO::PARAM_STR);
            $qs->bindParam(':phone',$phone, PDO::PARAM_STR);
            if ($id) $qs->bindParam(':id',$id, PDO::PARAM_STR);
            $qs->execute();

            $this->showList();
            C('#msg')->fadeOut(); // hide mesages if previously displayed
            $this->resetForm($id ? true : false); // reset form fields
        }
    }

    protected function edit_contact($e) {
        $id = (int)$e->value;
        $sql = 'SELECT * FROM contacts WHERE id='.$id;
        $rs = $this->db->query($sql);
        $row = $rs->fetch(PDO::FETCH_ASSOC);
        // populate form field
        C('input[name="name"]')->val($row['name']);
        C('input[name="address"]')->val($row['address']);
        C('input[name="phone"]')->val($row['phone']);
        
        // set value to be returned by event when form is submitted
        C('#contact')->attr('class','v:'.$id); // set event value using form class

        // setup buttons
        C('#cmdcancel')->show();
        C('#cmdsave')->val('Save Contact')
            ->addClass('process');

    }

    protected function remove_contact($e) {
        $id = (int)$e->value;
        $sql = 'DELETE FROM contacts WHERE id='.$id;
        $this->db->exec($sql);

        $this->showList();
    }

    protected function resetForm($isEdit) {
        C('#contact input.textbox')->val(''); // clear form text fields
        if ($isEdit) {
            C('#contact')->removeClass(); // remove event value from form class
            C('#cmdcancel')->hide();
            C('#cmdsave')->val('Add Contact')
                ->removeClass('process');
        }
    }

    protected function showList() {
        $sql = 'SELECT * FROM contacts ORDER BY id desc';
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

$page = new ContactPage();
$page->reply();

?>