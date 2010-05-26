<?php

require_once '../raxan/pdi/autostart.php';

/***
 * @property RaxanPDO $db
 */
class NotesPage extends RaxanWebPage {

    protected $db;
    protected $showRenderTime = true;

    protected function _config() {
        $this->autoAppendViews = true; //'index,form';
        $this->preserveFormContent = true;
        $this->masterTemplate = 'views/notes.html';
        //$this->db = $this->Raxan->connect('sqlite:'.dirname(__FILE__).'/notes.db',null,null,true);    // SQLite DB
        $this->db = $this->Raxan->connect('mysql:host=localhost; dbname=notes','user','password',true); // MySQL DB
    }

    // Views ---

    protected function _indexView() {
        if (!$this->isPostBack) $lst = $this->db->table('notes');
        else  {
            $search ='%'.$this->post->querytxt.'%';
            $lst = $this->db->table('notes','subject like ?',$search);
        }
        $this->noteList->bind($lst,array('altClass'=>'alt'));
    }

    protected function _formView() {
        $id = $this->get->intVal('id');
        if ($id && !$this->isPostBack){
            $data = $this->db->table('notes id,subject,message','id=?',$id);
            $this->form1->inputValues($data[0]);
            $this->title->text('Edit Note');
        }
    }

    protected function _detailsView() {
        $id = $this->get->intVal('id');
        $data = $this->db->table('notes id,subject,message','id=?',$id);
        $this->details->bind($data);
    }

    
    // Events

    protected function deleteNote($e) {
        $this->db->tableDelete('notes','id=?',$e->intVal());
        $this->flashmsg('Record sucessfully removed','bounce','notice');
        $this->redirectTo('notes.php');
    }

    protected function saveNote($e) {
        $id = $this->post->intVal('id');
        $data = $this->post->filterValues('subject,message');

        if (!$id) $rt = $this->db->tableInsert('notes',$data);
        else $rt = $this->db->tableUpdate('notes',$data,'id=?',$id);

        if (!$rt) $this->flashmsg('Error while updating record.');
        else $this->flashmsg('Record sucessfully saved','explode','success close click-cursor');

        $this->redirectTo('notes.php');

    }

}

?>