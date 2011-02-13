<?php

require_once '../raxan/pdi/autostart.php';

// system configuration
Raxan::loadConfig('config.php'); // load external config file
Raxan::config('site.timezone','America/Toronto'); // set timezone

class EmployeePage extends RaxanWebPage {

    protected $db;
    protected $pgNumber, $pageSize;

    protected function _config() {
        $this->pageSize = 10;
        // enable or diable debugging
        $this->Raxan->config('debug',false);
    }

    protected function _init() {
        $this->source('views/employees.html');
        try {
            // see config.php for connection info
            // For employee sample data visit http://dev.mysql.com/doc/
            $this->db = $this->Raxan->connect('employees'); // connect to db
        }
        catch (Exception $e) {
            $this->db = null;
            $msg = $this->getView('connection-failed.html');
            $this->flashmsg($msg,'bounce','rax-box error');
        }
    }

    protected function _load() {
        // get current page. Defaults to 1 if 'emp-page-number' is not set
        $this->pgNumber = & $this->data('emp-page-number',1,true);

        // delegate events
        $this->pager->delegate('a','click','.changePage');
        $this->emplist->delegate('tr','click','.rowClick');
    }

    protected function _prerender() {
        if ($this->db) $this->loadEmployees();
        else  $this->panel1->remove(); // remove table content
    }

    // -- Event handlers

    protected function changePage($e){
        $this->pgNumber = $e->intVal();
        if (!$this->pgNumber) $this->pgNumber = 1;
    }

    protected function rowClick($e){
        $id = $e->intVal() | 0;   // sanitize: convert to number
        if (!$e->ctrlKey && !$e->metaKey) $this->data('emp-selected-ids',$id);
        else {  // multiple selection
            $oldId = & $this->data('emp-selected-ids');
            if (!is_array($oldId)) $oldId = array($oldId);
            $oldId[] = $id;
        }
    }

    protected function loadEmployees() {

         // count # of rows in database
        $rowCount = & $this->data('emp-row-count');
        if (!$rowCount) {
            $rows = $this->db->query('select count(*) as total from employees');
            $rowCount = $rows->fetchColumn(0);
        }

        // load employees for current page
        $lower = (($this->pgNumber-1) * $this->pageSize)+1;
        $offset = $this->pageSize;
        $sql = "select *,concat(first_name,' ',last_name) as 'name' from employees order by emp_no limit ".$lower.', '.$offset;
        $rows = $this->db->query($sql);
        $this->emplistBody->bind($rows,array(
            'altClass' => 'even',
            'selectClass' => 'rax-selected-pal rax-metalic',
            'key'=>'emp_no',
            'selected' => $this->data('emp-selected-ids'),
            'initRowCount' => $lower,
            'format' => array(
                'name'=>'capitalize',
                'birth_date'=>'date:d M, Y'
             )
        ));

        // add hover effect to table rows
        c('#emplist tbody tr')->hoverClass('hover');
    
        // setup pager
        $maxpage = ceil($rowCount/$this->pageSize);
        $pages = $this->Raxan->paginate($maxpage,$this->pgNumber,array(
            'tpl' => '<a href="#{VALUE}" title="Page {VALUE}" class="{ROWCLASS}">{VALUE}</a>',
            'itemClass' => 'rax-active-pal',
            'selectClass' => 'rax-selected-pal rax-metalic border1',
            'delimiter'=>'',
        ));
        if ($maxpage > 1) {
            $pages.='<a href="#'.($this->pgNumber+1).'" title="Next Page" class="rax-active-pal">'.
            '<span class="ui-icon ui-icon-triangle-1-e"></span></a>';
        }
        if ($this->pgNumber> 1 && $this->pgNumber < $maxpage ) {
            $pages = '<a href="#'.($this->pgNumber-1).'" title="Prvious Page" class="rax-active-pal">'.
            '<span class="ui-icon ui-icon-triangle-1-w"></span></a>'.$pages;
        }
        $this->pager->html($pages);
    }

}

?>
