<?php

require_once '../raxan/pdi/autostart.php';

// system configuration
Raxan::loadConfig('config.php'); // load external config file
Raxan::config('site.timezone','America/Toronto'); // set timezone

class SearchBoxPage extends RaxanWebPage {

    protected $db;
    protected $infoTpl, $searchTpl;

    protected function _init() {
        $this->source('views/searchbox.html');
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
        // event to handle employee click
        $this->results->delegate('a','#click','.employeeClick');

        // event to handle auto-complete search
        $this->empName->bind('#keydown',array(
            'callback' => '.searchEmployee',
            'delay' => 600,
            'autoToggle' => 'img#pre',
            'serialize' => '#empName',
            'inputCache' => true   
        ));                    

        // get templates from document
        $this->searchTpl = $this['#results']->html();

        if (!$this->isPostBack) {
            $this->info->html('');
            $this->results->html('');
        }
    }


    // -- Event handlers

    protected function employeeClick($e) {
        $id = $e->intVal();
        $ds = $this->getEmployeeInfo($id);
        $this->info->bind($ds,array(
            'format'=>array(
                'gender'=>'capitalize',
                'hire_date'=>'date: M d, Y',
                'birth_date'=>'date: M d, Y'
            )
        ));
        $this->info->updateClient();
        $this->info->client->hide()->fadeIn();
    }

    protected function searchEmployee($e) {
        $rows = null;
        $name = $this->post->textVal('empName');
        if (!$name) $this->results->html(''); // clear results
        else {
            $name = explode(' ',trim($name),2);
            $fname = $name[0];
            $lname = isset($name[1]) ? trim($name[1]) : '';
            $rows = $this->getEmployees($fname.'%', $lname.'%');
            $this->results->bind($rows);
            $msg = ($rows ? 'Top 10 results for ': 'No results found for ').$fname.' '.$lname;;
            $this->results->prepend("<div class=\"bmb bmm\">$msg</div>");
        }
        $this->results->updateClient();
        if (!$rows) $this->info->html('')->updateClient(); // clear info panel
    }

    protected function getEmployees($fname,$lname) {
        if (!$this->db) return array();
        $filter = 'first_name like ? '.(($fname && $lname) ? ' and ' : ' or ').
                  'last_name like ? limit 10';
        $rows = $this->db->table('employees emp_no,first_name,last_name',$filter,$fname,$lname);
        return $rows;
    }

    protected function getEmployeeInfo($id) {
        if (!$this->db) return array();
        $sql = "select *,
                case gender when 'm' then 'male' when 'f' then 'female' else 'male' end as icon,
                case gender when 'm' then 'male' when 'f' then 'female' else 'male' end as gender
                from employees where emp_no=? ";
        $rows = $this->db->execQuery($sql,$id);
        return $rows;
    }

}

?>